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
 * Local library for the random quiz allocator activity.
 *
 * Allocation and gradebook logic lives in \mod_randomquiz\allocation_manager and
 * \mod_randomquiz\grade_manager; the randomquiz_* functions below delegate to them.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

use mod_randomquiz\allocation_manager;
use mod_randomquiz\grade_manager;

define('RANDOMQUIZ_ALLOC_RANDOM', 'random');
define('RANDOMQUIZ_ALLOC_BALANCED', 'balanced');

/**
 * Quiz settings that are safe to copy across quiz variants.
 *
 * This deliberately excludes question slots, marks, names, descriptions and sumgrades.
 */
function randomquiz_syncable_quiz_fields(): array {
    return [
        'timeopen',
        'timeclose',
        'timelimit',
        'overduehandling',
        'graceperiod',
        'preferredbehaviour',
        'attempts',
        'attemptonlast',
        'grademethod',
        'decimalpoints',
        'questiondecimalpoints',
        'reviewattempt',
        'reviewcorrectness',
        'reviewmarks',
        'reviewspecificfeedback',
        'reviewgeneralfeedback',
        'reviewrightanswer',
        'reviewoverallfeedback',
        'questionsperpage',
        'navmethod',
        'shuffleanswers',
        'browsersecurity',
        'delay1',
        'delay2',
        'showuserpicture',
        'showblocks',
        'subnet',
        'quizpassword',
    ];
}

/**
 * Return quiz activities in the course for use as allocator variants.
 *
 * @param int $courseid
 * @return array cmid => formatted quiz name
 */
function randomquiz_get_course_quiz_options(int $courseid): array {
    global $DB;

    $sql = "SELECT cm.id, q.name
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {quiz} q ON q.id = cm.instance
             WHERE cm.course = :courseid
               AND m.name = :modname
               AND cm.deletioninprogress = 0
          ORDER BY cm.section, cm.id";

    $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'modname' => 'quiz']);
    $options = [];
    foreach ($records as $record) {
        $options[(int)$record->id] = format_string($record->name);
    }

    return $options;
}

/**
 * Persist selected variant quiz course module ids.
 *
 * @param int $randomquizid
 * @param array $quizcmids
 * @return void
 */
function randomquiz_save_variants(int $randomquizid, array $quizcmids): void {
    global $DB;

    $quizcmids = array_values(array_unique(array_filter(array_map('intval', $quizcmids))));
    $now = time();

    $DB->delete_records('randomquiz_variants', ['randomquizid' => $randomquizid]);

    foreach ($quizcmids as $sortorder => $quizcmid) {
        $record = (object) [
            'randomquizid' => $randomquizid,
            'quizcmid' => $quizcmid,
            'sortorder' => $sortorder,
            'enabled' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('randomquiz_variants', $record);
    }
}

/**
 * Get selected quiz cmids for an allocator.
 *
 * @param int $randomquizid
 * @return array
 */
function randomquiz_get_variant_cmids(int $randomquizid): array {
    global $DB;

    $records = $DB->get_records('randomquiz_variants', ['randomquizid' => $randomquizid], 'sortorder ASC', 'id, quizcmid');
    return array_map(fn($record) => (int)$record->quizcmid, $records);
}

/**
 * Get variant records with quiz and course-module details.
 *
 * @param int $randomquizid
 * @return array
 */
function randomquiz_get_variant_details(int $randomquizid): array {
    global $DB;

    $sql = "SELECT rv.id,
                   rv.randomquizid,
                   rv.quizcmid,
                   rv.sortorder,
                   rv.enabled,
                   cm.visible,
                   cm.visibleoncoursepage,
                   cm.course AS quizcourse,
                   q.id AS quizid,
                   q.name AS quizname,
                   q.timeopen,
                   q.timeclose,
                   q.timelimit,
                   q.attempts,
                   q.grademethod,
                   q.grade,
                   q.sumgrades,
                   q.shuffleanswers,
                   q.navmethod,
                   q.browsersecurity
              FROM {randomquiz_variants} rv
              JOIN {course_modules} cm ON cm.id = rv.quizcmid
              JOIN {modules} m ON m.id = cm.module
              JOIN {quiz} q ON q.id = cm.instance
             WHERE rv.randomquizid = :randomquizid
               AND m.name = :modname
          ORDER BY rv.sortorder ASC, rv.id ASC";

    return $DB->get_records_sql($sql, ['randomquizid' => $randomquizid, 'modname' => 'quiz']);
}

/**
 * Get grade items for all quiz variants.
 *
 * @param int $randomquizid
 * @param int $courseid
 * @return array
 */
function randomquiz_get_variant_grade_items(int $randomquizid, int $courseid): array {
    return grade_manager::get_variant_grade_items($randomquizid, $courseid);
}

/**
 * Return gradebook readiness information for this allocator's quiz variants.
 *
 * @param stdClass $randomquiz
 * @param int $courseid
 * @return array
 */
function randomquiz_get_gradebook_status(stdClass $randomquiz, int $courseid): array {
    return grade_manager::get_gradebook_status($randomquiz, $courseid);
}

/**
 * Return the shared grade category for variant grade items.
 *
 * @param array $items
 * @param int $courseid
 * @param array $messages
 * @return grade_category|null
 */
function randomquiz_get_common_grade_category(array $items, int $courseid, array &$messages): ?grade_category {
    return grade_manager::get_common_grade_category($items, $courseid, $messages);
}

/**
 * Create or repair the grade category used by the allocator's variants.
 *
 * @param stdClass $randomquiz
 * @param int $courseid
 * @return array
 */
function randomquiz_setup_gradebook_category(stdClass $randomquiz, int $courseid): array {
    return grade_manager::setup_gradebook_category($randomquiz, $courseid);
}

/**
 * Copy safe quiz settings from the first selected variant to all later variants.
 *
 * @param int $randomquizid
 * @return array source quiz name and number of updated variants
 */
function randomquiz_match_settings_from_first_variant(int $randomquizid): array {
    return grade_manager::match_settings_from_first_variant($randomquizid);
}

/**
 * Check whether the current user can manage every linked quiz variant.
 *
 * @param int $randomquizid
 * @return bool
 */
function randomquiz_can_manage_variant_quizzes(int $randomquizid): bool {
    foreach (randomquiz_get_variant_details($randomquizid) as $variant) {
        if (!has_capability('mod/quiz:manage', \context_module::instance($variant->quizcmid))) {
            return false;
        }
    }

    return true;
}

/**
 * Require quiz management capability on every linked quiz variant.
 *
 * @param int $randomquizid
 * @return void
 */
function randomquiz_require_manage_variant_quizzes(int $randomquizid): void {
    foreach (randomquiz_get_variant_details($randomquizid) as $variant) {
        require_capability('mod/quiz:manage', \context_module::instance($variant->quizcmid));
    }
}

/**
 * Get variants that are enabled and launchable, optionally for a specific student.
 *
 * @param stdClass $randomquiz
 * @param int $userid 0 to skip per-user availability checks
 * @return array
 */
function randomquiz_get_launchable_variants(stdClass $randomquiz, int $userid = 0): array {
    return allocation_manager::get_launchable_variants($randomquiz, $userid);
}

/**
 * Get the module context for a random quiz allocator instance.
 *
 * @param int $randomquizid
 * @return context_module
 */
function randomquiz_get_module_context(int $randomquizid): context_module {
    $cm = get_coursemodule_from_instance('randomquiz', $randomquizid, 0, false, MUST_EXIST);
    return context_module::instance($cm->id);
}

/**
 * Return an existing allocation, or create one for the user.
 *
 * @param stdClass $randomquiz
 * @param int $userid
 * @return stdClass
 */
function randomquiz_get_or_create_allocation(stdClass $randomquiz, int $userid): stdClass {
    return allocation_manager::get_or_create_allocation($randomquiz, $userid);
}

/**
 * Return or create an allocation while the activity allocation lock is held.
 *
 * @param stdClass $randomquiz
 * @param int $userid
 * @return stdClass
 */
function randomquiz_get_or_create_allocation_locked(stdClass $randomquiz, int $userid): stdClass {
    return allocation_manager::get_or_create_allocation_locked($randomquiz, $userid);
}

/**
 * Check whether an existing allocation can still be launched by the student.
 *
 * @param stdClass $allocation
 * @param int $userid
 * @return bool
 */
function randomquiz_allocation_is_launchable(stdClass $allocation, int $userid): bool {
    return allocation_manager::allocation_is_launchable($allocation, $userid);
}

/**
 * Record that a student launched their assigned quiz.
 *
 * @param stdClass $randomquiz
 * @param stdClass $allocation
 * @return void
 */
function randomquiz_trigger_assigned_quiz_launched(stdClass $randomquiz, stdClass $allocation): void {
    \mod_randomquiz\event\assigned_quiz_launched::create([
        'objectid' => (int)$allocation->id,
        'context' => randomquiz_get_module_context((int)$randomquiz->id),
        'relateduserid' => (int)$allocation->userid,
        'other' => [
            'randomquizid' => (int)$randomquiz->id,
            'quizcmid' => (int)$allocation->quizcmid,
        ],
    ])->trigger();
}

/**
 * Count non-preview attempts for a student's allocated quiz.
 *
 * @param stdClass $allocation
 * @return int
 */
function randomquiz_count_allocation_attempts(stdClass $allocation): int {
    return allocation_manager::count_allocation_attempts($allocation);
}

/**
 * Preload non-preview attempt counts for every allocation of an allocator.
 *
 * @param int $randomquizid
 * @return array allocationid => attempt count (allocations without attempts are omitted)
 */
function randomquiz_get_allocation_attempt_counts(int $randomquizid): array {
    return allocation_manager::get_allocation_attempt_counts($randomquizid);
}

/**
 * Reset a stored allocation only if the assigned quiz has not been attempted.
 *
 * @param int $randomquizid
 * @param int $allocationid
 * @return string Reset user's full name.
 */
function randomquiz_reset_allocation_if_unattempted(int $randomquizid, int $allocationid): string {
    return allocation_manager::reset_allocation_if_unattempted($randomquizid, $allocationid);
}

/**
 * Manually set a student's allocation if they have not started the current assigned quiz.
 *
 * @param stdClass $randomquiz
 * @param int $userid
 * @param int $quizcmid
 * @return string Updated user's full name.
 */
function randomquiz_set_manual_allocation(stdClass $randomquiz, int $userid, int $quizcmid): string {
    return allocation_manager::set_manual_allocation($randomquiz, $userid, $quizcmid);
}

/**
 * Require an active enrolled user for manual allocation.
 *
 * @param stdClass $randomquiz
 * @param int $userid
 * @return stdClass
 * @throws moodle_exception
 */
function randomquiz_require_manual_allocation_user(stdClass $randomquiz, int $userid): stdClass {
    return allocation_manager::require_manual_allocation_user($randomquiz, $userid);
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
function randomquiz_require_manual_allocation_quiz(stdClass $randomquiz, int $userid, int $quizcmid): void {
    allocation_manager::require_manual_allocation_quiz($randomquiz, $userid, $quizcmid);
}

/**
 * Return an existing manual allocation only when it can still be changed.
 *
 * @param stdClass $randomquiz
 * @param int $userid
 * @return stdClass|null
 * @throws moodle_exception
 */
function randomquiz_get_changeable_allocation(stdClass $randomquiz, int $userid): ?stdClass {
    return allocation_manager::get_changeable_allocation($randomquiz, $userid);
}

/**
 * Get enrolled users who can be manually allocated.
 *
 * @param stdClass $course
 * @return array userid => fullname
 */
function randomquiz_get_allocatable_user_options(stdClass $course): array {
    return allocation_manager::get_allocatable_user_options($course);
}

/**
 * Choose a variant according to the instance allocation mode.
 *
 * @param stdClass $randomquiz
 * @param array $variants
 * @return stdClass
 */
function randomquiz_choose_variant(stdClass $randomquiz, array $variants): stdClass {
    return allocation_manager::choose_variant($randomquiz, $variants);
}

/**
 * Get allocation records for teacher reporting.
 *
 * @param int $randomquizid
 * @return array
 */
function randomquiz_get_allocations(int $randomquizid): array {
    return allocation_manager::get_allocations($randomquizid);
}

/**
 * Build readiness checks for a variant.
 *
 * @param stdClass $variant
 * @param stdClass|null $template
 * @return array
 */
function randomquiz_variant_checks(stdClass $variant, ?stdClass $template): array {
    $checks = [];

    if (!(int)$variant->visible) {
        $checks[] = ['error', get_string('hiddenfromstudents', 'randomquiz')];
    } else if (!(int)$variant->visibleoncoursepage) {
        $checks[] = ['ok', get_string('availablehidden', 'randomquiz')];
    } else {
        $checks[] = ['warning', get_string('visibleoncoursepage', 'randomquiz')];
    }

    if ((float)$variant->sumgrades > 0) {
        $checks[] = ['ok', get_string('hasquestions', 'randomquiz')];
    } else {
        $checks[] = ['warning', get_string('noquestions', 'randomquiz')];
    }

    if ($template) {
        $fields = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'grademethod', 'grade', 'shuffleanswers', 'browsersecurity'];
        foreach ($fields as $field) {
            if ((string)$variant->{$field} !== (string)$template->{$field}) {
                $checks[] = ['warning', randomquiz_setting_difference_label($field)];
            }
        }
    }

    return $checks;
}

/**
 * Return a teacher-facing label for a mismatched quiz setting.
 *
 * @param string $field
 * @return string
 */
function randomquiz_setting_difference_label(string $field): string {
    $stringid = 'settingdiff:' . $field;
    if (get_string_manager()->string_exists($stringid, 'randomquiz')) {
        return get_string($stringid, 'randomquiz');
    }

    return get_string('settings') . ': ' . s($field);
}

/**
 * Convert a readiness state to a Bootstrap badge class.
 *
 * @param string $state
 * @return string
 */
function randomquiz_badge_class(string $state): string {
    if ($state === 'ok') {
        return 'text-bg-success';
    }
    if ($state === 'error') {
        return 'text-bg-danger';
    }
    return 'text-bg-warning';
}
