<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Allocation management for the random quiz allocator activity.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomquiz;

use context_module;
use moodle_exception;
use stdClass;

/**
 * Handles student allocation lifecycle: creation, launch checks, resets and manual overrides.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation_manager {
    /**
     * Get variants that are enabled and launchable, optionally for a specific student.
     *
     * Variants may be hidden from the course page, but they must not be fully hidden
     * from students or the redirect into Moodle Quiz will fail. When a user id is
     * given, conditional availability restrictions are also enforced so a student
     * is never allocated a quiz they cannot access.
     *
     * @param stdClass $randomquiz
     * @param int $userid 0 to skip per-user availability checks
     * @return array
     */
    public static function get_launchable_variants(stdClass $randomquiz, int $userid = 0): array {
        $variants = array_values(array_filter(
            randomquiz_get_variant_details((int)$randomquiz->id),
            fn($variant): bool => (int)$variant->enabled === 1 && (int)$variant->visible === 1
        ));

        if (!$userid || !$variants) {
            return $variants;
        }

        $modinfo = get_fast_modinfo((int)$randomquiz->course, $userid);
        return array_values(array_filter($variants, function ($variant) use ($modinfo): bool {
            $cms = $modinfo->get_cms();
            if (!isset($cms[$variant->quizcmid])) {
                return false;
            }
            return $cms[$variant->quizcmid]->uservisible;
        }));
    }

    /**
     * Return an existing allocation, or create one for the user.
     *
     * @param stdClass $randomquiz
     * @param int $userid
     * @return stdClass
     */
    public static function get_or_create_allocation(stdClass $randomquiz, int $userid): stdClass {
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_randomquiz_allocation');
        $allocationlock = $lockfactory->get_lock('randomquiz:' . (int)$randomquiz->id, 10, MINSECS);

        if (!$allocationlock) {
            throw new moodle_exception('allocationlocktimeout', 'randomquiz');
        }

        try {
            return self::get_or_create_allocation_locked($randomquiz, $userid);
        } finally {
            $allocationlock->release();
        }
    }

    /**
     * Return or create an allocation while the activity allocation lock is held.
     *
     * @param stdClass $randomquiz
     * @param int $userid
     * @return stdClass
     */
    public static function get_or_create_allocation_locked(stdClass $randomquiz, int $userid): stdClass {
        global $DB;

        $variants = self::get_launchable_variants($randomquiz, $userid);
        $launchablecmids = array_map(fn($variant) => (int)$variant->quizcmid, $variants);

        $existing = $DB->get_record('randomquiz_allocations', [
            'randomquizid' => $randomquiz->id,
            'userid' => $userid,
        ]);
        if ($existing) {
            if (
                in_array((int)$existing->quizcmid, $launchablecmids, true) ||
                    self::count_allocation_attempts($existing) > 0
            ) {
                return $existing;
            }
            $DB->delete_records('randomquiz_allocations', ['id' => $existing->id]);
        }

        if (!$variants) {
            throw new moodle_exception('nolaunchablevariants', 'randomquiz');
        }

        $chosen = self::choose_variant($randomquiz, $variants);
        $allocation = (object) [
            'randomquizid' => $randomquiz->id,
            'userid' => $userid,
            'quizcmid' => $chosen->quizcmid,
            'timeallocated' => time(),
        ];
        $allocation->id = $DB->insert_record('randomquiz_allocations', $allocation);

        \mod_randomquiz\event\allocation_created::create([
            'objectid' => $allocation->id,
            'context' => randomquiz_get_module_context((int)$randomquiz->id),
            'relateduserid' => $userid,
            'other' => [
                'randomquizid' => (int)$randomquiz->id,
                'quizcmid' => (int)$allocation->quizcmid,
            ],
        ])->trigger();

        return $allocation;
    }

    /**
     * Check whether an existing allocation can still be launched by the student.
     *
     * @param stdClass $allocation
     * @param int $userid
     * @return bool
     */
    public static function allocation_is_launchable(stdClass $allocation, int $userid): bool {
        $cm = get_coursemodule_from_id('quiz', (int)$allocation->quizcmid, 0, false, IGNORE_MISSING);
        if (!$cm || !(int)$cm->visible) {
            return false;
        }

        $modinfo = get_fast_modinfo((int)$cm->course, $userid);
        $cms = $modinfo->get_cms();
        if (!isset($cms[$cm->id]) || !$cms[$cm->id]->uservisible) {
            return false;
        }

        return has_capability('mod/quiz:attempt', context_module::instance($cm->id), $userid);
    }

    /**
     * Choose a variant according to the instance allocation mode.
     *
     * @param stdClass $randomquiz
     * @param array $variants
     * @return stdClass
     */
    public static function choose_variant(stdClass $randomquiz, array $variants): stdClass {
        global $DB;

        if (($randomquiz->allocationmode ?? RANDOMQUIZ_ALLOC_BALANCED) !== RANDOMQUIZ_ALLOC_BALANCED) {
            return $variants[random_int(0, count($variants) - 1)];
        }

        [$insql, $params] = $DB->get_in_or_equal(
            array_map(fn($variant) => (int)$variant->quizcmid, $variants),
            SQL_PARAMS_NAMED
        );
        $params['randomquizid'] = $randomquiz->id;
        $counts = $DB->get_records_sql_menu(
            "SELECT quizcmid, COUNT(1)
               FROM {randomquiz_allocations}
              WHERE randomquizid = :randomquizid
                AND quizcmid {$insql}
           GROUP BY quizcmid",
            $params
        );

        $lowest = null;
        $candidates = [];
        foreach ($variants as $variant) {
            $count = (int)($counts[$variant->quizcmid] ?? 0);
            if ($lowest === null || $count < $lowest) {
                $lowest = $count;
                $candidates = [$variant];
            } else if ($count === $lowest) {
                $candidates[] = $variant;
            }
        }

        return $candidates[random_int(0, count($candidates) - 1)];
    }

    /**
     * Count non-preview attempts for a student's allocated quiz.
     *
     * @param stdClass $allocation
     * @return int
     */
    public static function count_allocation_attempts(stdClass $allocation): int {
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $allocation->quizcmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return 0;
        }

        return (int)$DB->count_records('quiz_attempts', [
            'quiz' => $cm->instance,
            'userid' => $allocation->userid,
            'preview' => 0,
        ]);
    }

    /**
     * Preload non-preview attempt counts for every allocation of an allocator.
     *
     * @param int $randomquizid
     * @return array allocationid => attempt count (allocations without attempts are omitted)
     */
    public static function get_allocation_attempt_counts(int $randomquizid): array {
        global $DB;

        $sql = "SELECT a.id, COUNT(qa.id) AS attemptcount
                  FROM {randomquiz_allocations} a
                  JOIN {course_modules} cm ON cm.id = a.quizcmid
                  JOIN {quiz_attempts} qa ON qa.quiz = cm.instance AND qa.userid = a.userid AND qa.preview = 0
                 WHERE a.randomquizid = :randomquizid
              GROUP BY a.id";

        return array_map('intval', $DB->get_records_sql_menu($sql, ['randomquizid' => $randomquizid]));
    }

    /**
     * Reset a stored allocation only if the assigned quiz has not been attempted.
     *
     * @param int $randomquizid
     * @param int $allocationid
     * @return string Reset user's full name.
     */
    public static function reset_allocation_if_unattempted(int $randomquizid, int $allocationid): string {
        global $DB;

        $allocation = $DB->get_record('randomquiz_allocations', [
            'id' => $allocationid,
            'randomquizid' => $randomquizid,
        ]);
        if (!$allocation) {
            throw new moodle_exception('allocationnotfound', 'randomquiz');
        }

        if (self::count_allocation_attempts($allocation) > 0) {
            throw new moodle_exception('allocationresetblocked', 'randomquiz');
        }

        $user = $DB->get_record('user', ['id' => $allocation->userid], '*', MUST_EXIST);
        $DB->delete_records('randomquiz_allocations', ['id' => $allocation->id]);

        \mod_randomquiz\event\allocation_reset::create([
            'objectid' => (int)$allocation->id,
            'context' => randomquiz_get_module_context($randomquizid),
            'relateduserid' => (int)$allocation->userid,
            'other' => [
                'randomquizid' => $randomquizid,
                'quizcmid' => (int)$allocation->quizcmid,
            ],
        ])->trigger();

        return fullname($user);
    }

    /**
     * Manually set a student's allocation if they have not started the current assigned quiz.
     *
     * @param stdClass $randomquiz
     * @param int $userid
     * @param int $quizcmid
     * @return string Updated user's full name.
     */
    public static function set_manual_allocation(stdClass $randomquiz, int $userid, int $quizcmid): string {
        global $DB;

        $user = self::require_manual_allocation_user($randomquiz, $userid);
        self::require_manual_allocation_quiz($randomquiz, $userid, $quizcmid);
        $existing = self::get_changeable_allocation($randomquiz, $userid);

        $previousquizcmid = $existing ? (int)$existing->quizcmid : 0;
        $now = time();
        if ($existing) {
            $existing->quizcmid = $quizcmid;
            $existing->timeallocated = $now;
            $DB->update_record('randomquiz_allocations', $existing);
            $allocationid = (int)$existing->id;
        } else {
            $allocationid = $DB->insert_record('randomquiz_allocations', (object) [
                'randomquizid' => $randomquiz->id,
                'userid' => $userid,
                'quizcmid' => $quizcmid,
                'timeallocated' => $now,
            ]);
        }

        \mod_randomquiz\event\manual_allocation_updated::create([
            'objectid' => $allocationid,
            'context' => randomquiz_get_module_context((int)$randomquiz->id),
            'relateduserid' => $userid,
            'other' => [
                'randomquizid' => (int)$randomquiz->id,
                'quizcmid' => $quizcmid,
                'previousquizcmid' => $previousquizcmid,
            ],
        ])->trigger();

        return fullname($user);
    }

    /**
     * Require an active enrolled user for manual allocation.
     *
     * @param stdClass $randomquiz
     * @param int $userid
     * @return stdClass
     * @throws moodle_exception
     */
    public static function require_manual_allocation_user(stdClass $randomquiz, int $userid): stdClass {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*');
        if (!$user || !empty($user->suspended)) {
            throw new moodle_exception('invalidallocationuser', 'randomquiz');
        }

        $coursecontext = \context_course::instance($randomquiz->course);
        if (!is_enrolled($coursecontext, $user, '', true)) {
            throw new moodle_exception('invalidallocationuser', 'randomquiz');
        }

        return $user;
    }

    /**
     * Require a quiz variant that the selected user can attempt.
     *
     * @param stdClass $randomquiz
     * @param int $userid
     * @param int $quizcmid
     * @return void
     * @throws moodle_exception
     */
    public static function require_manual_allocation_quiz(stdClass $randomquiz, int $userid, int $quizcmid): void {
        $validcmids = randomquiz_get_variant_cmids((int)$randomquiz->id);
        if (!in_array($quizcmid, $validcmids, true)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        $quizcm = get_coursemodule_from_id('quiz', $quizcmid, 0, false, MUST_EXIST);
        if (
            (int)$quizcm->course !== (int)$randomquiz->course || !(int)$quizcm->visible ||
                !has_capability('mod/quiz:attempt', \context_module::instance($quizcmid), $userid)
        ) {
            throw new moodle_exception('invalidallocationuser', 'randomquiz');
        }
    }

    /**
     * Return an existing manual allocation only when it can still be changed.
     *
     * @param stdClass $randomquiz
     * @param int $userid
     * @return stdClass|null
     * @throws moodle_exception
     */
    public static function get_changeable_allocation(stdClass $randomquiz, int $userid): ?stdClass {
        global $DB;

        $existing = $DB->get_record('randomquiz_allocations', [
            'randomquizid' => $randomquiz->id,
            'userid' => $userid,
        ]);
        if ($existing && self::count_allocation_attempts($existing) > 0) {
            throw new moodle_exception('manualallocationblocked', 'randomquiz');
        }

        return $existing ?: null;
    }

    /**
     * Get allocation records for teacher reporting.
     *
     * @param int $randomquizid
     * @return array
     */
    public static function get_allocations(int $randomquizid): array {
        global $DB;

        $fields = \core_user\fields::for_name()->with_identity(null, false)->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT a.id,
                       a.userid,
                       a.quizcmid,
                       a.timeallocated,
                       {$fields},
                       q.id AS quizid,
                       q.name AS quizname
                  FROM {randomquiz_allocations} a
                  JOIN {user} u ON u.id = a.userid
                  JOIN {course_modules} cm ON cm.id = a.quizcmid
                  JOIN {quiz} q ON q.id = cm.instance
                 WHERE a.randomquizid = :randomquizid
              ORDER BY a.timeallocated DESC, a.id DESC";

        return $DB->get_records_sql($sql, ['randomquizid' => $randomquizid]);
    }

    /**
     * Get enrolled users who can be manually allocated.
     *
     * @param stdClass $course
     * @return array userid => fullname
     */
    public static function get_allocatable_user_options(stdClass $course): array {
        $coursecontext = \context_course::instance($course->id);
        $namefields = 'u.id, ' . implode(', ', array_map(
            fn($field) => 'u.' . $field,
            \core_user\fields::for_name()->get_required_fields()
        ));
        $users = get_enrolled_users(
            $coursecontext,
            'mod/quiz:attempt',
            0,
            $namefields,
            'u.lastname, u.firstname'
        );
        if (!$users) {
            $users = get_enrolled_users($coursecontext, '', 0, $namefields, 'u.lastname, u.firstname');
        }

        $options = [];
        foreach ($users as $user) {
            $options[(int)$user->id] = fullname($user);
        }

        return $options;
    }
}
