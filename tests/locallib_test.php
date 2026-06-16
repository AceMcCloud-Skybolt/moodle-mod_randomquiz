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
 * Unit tests for random quiz allocator local library functions.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomquiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/randomquiz/locallib.php');

/**
 * Unit tests for random quiz allocator local library functions.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \advanced_testcase {

    /**
     * Create a course, student, two quiz variants and a random quiz allocator.
     *
     * @param string $allocationmode
     * @return array
     */
    private function create_randomquiz_fixture(string $allocationmode = RANDOMQUIZ_ALLOC_BALANCED): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $quiz1 = $generator->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Quiz variant A',
            'sumgrades' => 1,
        ]);
        $quiz2 = $generator->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Quiz variant B',
            'sumgrades' => 1,
        ]);

        $module = $generator->create_module('randomquiz', [
            'course' => $course->id,
            'name' => 'Random quiz allocator',
            'allocationmode' => $allocationmode,
            'variantcmids' => [$quiz1->cmid, $quiz2->cmid],
        ]);
        $randomquiz = $DB->get_record('randomquiz', ['id' => $module->id], '*', MUST_EXIST);

        return [$course, $student, $teacher, $randomquiz, $quiz1, $quiz2];
    }

    /**
     * Insert a stored allocation for a student.
     *
     * @param \stdClass $randomquiz
     * @param \stdClass $student
     * @param \stdClass $quiz
     * @return \stdClass
     */
    private function create_allocation(\stdClass $randomquiz, \stdClass $student, \stdClass $quiz): \stdClass {
        global $DB;

        $allocation = (object) [
            'randomquizid' => $randomquiz->id,
            'userid' => $student->id,
            'quizcmid' => $quiz->cmid,
            'timeallocated' => time(),
        ];
        $allocation->id = $DB->insert_record('randomquiz_allocations', $allocation);

        return $allocation;
    }

    /**
     * Insert the minimum records needed for randomquiz_count_allocation_attempts().
     *
     * @param \stdClass $student
     * @param \stdClass $quiz
     * @return void
     */
    private function create_quiz_attempt(\stdClass $student, \stdClass $quiz): void {
        global $DB;

        $context = \context_module::instance($quiz->cmid);
        $uniqueid = $DB->insert_record('question_usages', (object) [
            'contextid' => $context->id,
            'component' => 'mod_quiz',
            'preferredbehaviour' => 'deferredfeedback',
        ]);

        $DB->insert_record('quiz_attempts', (object) [
            'quiz' => $quiz->id,
            'userid' => $student->id,
            'attempt' => 1,
            'uniqueid' => $uniqueid,
            'layout' => '',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'inprogress',
            'timestart' => time(),
            'timefinish' => 0,
            'timemodified' => time(),
            'timemodifiedoffline' => 0,
            'timecheckstate' => 0,
            'sumgrades' => null,
        ]);
    }

    /**
     * Assert that an event of the requested class was emitted.
     *
     * @param array $events
     * @param string $classname
     * @return \core\event\base
     */
    private function assert_event_emitted(array $events, string $classname): \core\event\base {
        foreach ($events as $event) {
            if ($event instanceof $classname) {
                $this->assertInstanceOf($classname, $event);
                return $event;
            }
        }

        $this->fail('Event was not emitted: ' . $classname);
    }

    /**
     * Assert that a callable throws a Moodle exception with the requested error code.
     *
     * @param string $errorcode
     * @param callable $callback
     * @return void
     */
    private function assert_moodle_exception(string $errorcode, callable $callback): void {
        try {
            $callback();
            $this->fail('Expected moodle_exception was not thrown: ' . $errorcode);
        } catch (\moodle_exception $exception) {
            $this->assertSame($errorcode, $exception->errorcode);
        }
    }

    /**
     * A student's first start creates an allocation.
     */
    public function test_first_start_creates_allocation(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, , $randomquiz] = $this->create_randomquiz_fixture();
        $this->setUser($student);

        $sink = $this->redirectEvents();
        $allocation = randomquiz_get_or_create_allocation($randomquiz, $student->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($DB->record_exists('randomquiz_allocations', ['id' => $allocation->id]));
        $this->assertSame((int)$student->id, (int)$allocation->userid);

        $event = $this->assert_event_emitted($events, \mod_randomquiz\event\allocation_created::class);
        $this->assertSame((int)$student->id, (int)$event->relateduserid);
        $this->assertSame((int)$allocation->id, (int)$event->objectid);
    }

    /**
     * A student's later start reuses the existing allocation.
     */
    public function test_second_start_returns_same_allocation(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, , $randomquiz] = $this->create_randomquiz_fixture();

        $allocation1 = randomquiz_get_or_create_allocation($randomquiz, $student->id);
        $allocation2 = randomquiz_get_or_create_allocation($randomquiz, $student->id);

        $this->assertSame((int)$allocation1->id, (int)$allocation2->id);
        $this->assertSame(1, $DB->count_records('randomquiz_allocations', [
            'randomquizid' => $randomquiz->id,
            'userid' => $student->id,
        ]));
    }

    /**
     * Balanced allocation chooses from variants with the lowest allocation count.
     */
    public function test_balanced_allocation_uses_lowest_count_variant(): void {
        $this->resetAfterTest();
        [, $student, , $randomquiz, $quiz1, $quiz2] = $this->create_randomquiz_fixture();
        $extrauser = $this->getDataGenerator()->create_user();
        $this->create_allocation($randomquiz, $extrauser, $quiz1);

        $allocation = randomquiz_get_or_create_allocation($randomquiz, $student->id);

        $this->assertSame((int)$quiz2->cmid, (int)$allocation->quizcmid);
    }

    /**
     * Random allocation only chooses from selected launchable variants.
     */
    public function test_random_allocation_uses_selected_launchable_variant(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, , $randomquiz, $quiz1, $quiz2] = $this->create_randomquiz_fixture(RANDOMQUIZ_ALLOC_RANDOM);
        $validcmids = [(int)$quiz1->cmid, (int)$quiz2->cmid];

        for ($i = 0; $i < 10; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $randomquiz->course, 'student');
            $allocation = randomquiz_get_or_create_allocation($randomquiz, $user->id);
            $this->assertContains((int)$allocation->quizcmid, $validcmids);
        }

        $allocation = randomquiz_get_or_create_allocation($randomquiz, $student->id);
        $this->assertContains((int)$allocation->quizcmid, $validcmids);
        $this->assertSame(11, $DB->count_records('randomquiz_allocations', ['randomquizid' => $randomquiz->id]));
    }

    /**
     * Hidden unattempted allocations are replaced with a launchable variant.
     */
    public function test_hidden_unattempted_allocation_is_replaced(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, , $randomquiz, $quiz1, $quiz2] = $this->create_randomquiz_fixture();
        $staleallocation = $this->create_allocation($randomquiz, $student, $quiz1);
        set_coursemodule_visible($quiz1->cmid, 0);

        $allocation = randomquiz_get_or_create_allocation($randomquiz, $student->id);

        $this->assertFalse($DB->record_exists('randomquiz_allocations', ['id' => $staleallocation->id]));
        $this->assertNotSame((int)$staleallocation->id, (int)$allocation->id);
        $this->assertSame((int)$quiz2->cmid, (int)$allocation->quizcmid);
    }

    /**
     * Hidden attempted allocations are preserved but blocked from launch.
     */
    public function test_hidden_attempted_allocation_is_preserved_but_not_launchable(): void {
        $this->resetAfterTest();
        [, $student, , $randomquiz, $quiz1] = $this->create_randomquiz_fixture();
        $allocation = $this->create_allocation($randomquiz, $student, $quiz1);
        $this->create_quiz_attempt($student, $quiz1);
        set_coursemodule_visible($quiz1->cmid, 0);

        $result = randomquiz_get_or_create_allocation($randomquiz, $student->id);

        $this->assertSame((int)$allocation->id, (int)$result->id);
        $this->assertFalse(randomquiz_allocation_is_launchable($result, $student->id));
    }

    /**
     * Starting with no launchable variants fails clearly.
     */
    public function test_no_launchable_variants_throws_exception(): void {
        $this->resetAfterTest();
        [, $student, , $randomquiz, $quiz1, $quiz2] = $this->create_randomquiz_fixture();
        set_coursemodule_visible($quiz1->cmid, 0);
        set_coursemodule_visible($quiz2->cmid, 0);

        $this->assert_moodle_exception('nolaunchablevariants', function() use ($randomquiz, $student): void {
            randomquiz_get_or_create_allocation($randomquiz, $student->id);
        });
    }

    /**
     * Launching an assigned quiz emits an audit event.
     */
    public function test_assigned_quiz_launch_emits_event(): void {
        $this->resetAfterTest();
        [, $student, , $randomquiz] = $this->create_randomquiz_fixture();
        $allocation = randomquiz_get_or_create_allocation($randomquiz, $student->id);

        $sink = $this->redirectEvents();
        randomquiz_trigger_assigned_quiz_launched($randomquiz, $allocation);
        $events = $sink->get_events();
        $sink->close();

        $event = $this->assert_event_emitted($events, \mod_randomquiz\event\assigned_quiz_launched::class);
        $this->assertSame((int)$student->id, (int)$event->relateduserid);
        $this->assertSame((int)$allocation->id, (int)$event->objectid);
    }

    /**
     * Resetting an allocation emits an audit event.
     */
    public function test_allocation_reset_emits_event(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, , $randomquiz, $quiz1] = $this->create_randomquiz_fixture();
        $allocation = $this->create_allocation($randomquiz, $student, $quiz1);

        $sink = $this->redirectEvents();
        randomquiz_reset_allocation_if_unattempted($randomquiz->id, $allocation->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($DB->record_exists('randomquiz_allocations', ['id' => $allocation->id]));
        $event = $this->assert_event_emitted($events, \mod_randomquiz\event\allocation_reset::class);
        $this->assertSame((int)$student->id, (int)$event->relateduserid);
        $this->assertSame((int)$allocation->id, (int)$event->objectid);
    }

    /**
     * Attempted allocations cannot be reset.
     */
    public function test_allocation_reset_is_blocked_after_attempt(): void {
        $this->resetAfterTest();
        [, $student, , $randomquiz, $quiz1] = $this->create_randomquiz_fixture();
        $allocation = $this->create_allocation($randomquiz, $student, $quiz1);
        $this->create_quiz_attempt($student, $quiz1);

        $this->assert_moodle_exception('allocationresetblocked', function() use ($randomquiz, $allocation): void {
            randomquiz_reset_allocation_if_unattempted($randomquiz->id, $allocation->id);
        });
    }

    /**
     * Manual allocation emits an audit event.
     */
    public function test_manual_allocation_emits_event(): void {
        $this->resetAfterTest();
        [, $student, $teacher, $randomquiz, , $quiz2] = $this->create_randomquiz_fixture();
        $this->setUser($teacher);

        $sink = $this->redirectEvents();
        randomquiz_set_manual_allocation($randomquiz, $student->id, $quiz2->cmid);
        $events = $sink->get_events();
        $sink->close();

        $event = $this->assert_event_emitted($events, \mod_randomquiz\event\manual_allocation_updated::class);
        $this->assertSame((int)$student->id, (int)$event->relateduserid);
        $this->assertSame((int)$quiz2->cmid, (int)$event->other['quizcmid']);
    }

    /**
     * Manual allocation rejects a course module that is not a selected variant.
     */
    public function test_manual_allocation_rejects_invalid_cmid(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher, $randomquiz] = $this->create_randomquiz_fixture();
        $this->setUser($teacher);
        $otherquiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Unlinked quiz',
        ]);

        $this->assert_moodle_exception('invalidcoursemodule', function() use ($randomquiz, $student, $otherquiz): void {
            randomquiz_set_manual_allocation($randomquiz, $student->id, $otherquiz->cmid);
        });
    }

    /**
     * Manual allocation rejects deleted users.
     */
    public function test_manual_allocation_rejects_deleted_user(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, $teacher, $randomquiz, , $quiz2] = $this->create_randomquiz_fixture();
        $this->setUser($teacher);
        $DB->set_field('user', 'deleted', 1, ['id' => $student->id]);

        $this->assert_moodle_exception('invalidallocationuser', function() use ($randomquiz, $student, $quiz2): void {
            randomquiz_set_manual_allocation($randomquiz, $student->id, $quiz2->cmid);
        });
    }

    /**
     * Manual allocation rejects suspended users.
     */
    public function test_manual_allocation_rejects_suspended_user(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, $teacher, $randomquiz, , $quiz2] = $this->create_randomquiz_fixture();
        $this->setUser($teacher);
        $DB->set_field('user', 'suspended', 1, ['id' => $student->id]);

        $this->assert_moodle_exception('invalidallocationuser', function() use ($randomquiz, $student, $quiz2): void {
            randomquiz_set_manual_allocation($randomquiz, $student->id, $quiz2->cmid);
        });
    }

    /**
     * Manual allocation rejects users who are not actively enrolled in the course.
     */
    public function test_manual_allocation_rejects_unenrolled_user(): void {
        $this->resetAfterTest();
        [, , $teacher, $randomquiz, , $quiz2] = $this->create_randomquiz_fixture();
        $this->setUser($teacher);
        $outsider = $this->getDataGenerator()->create_user();

        $this->assert_moodle_exception('invalidallocationuser', function() use ($randomquiz, $outsider, $quiz2): void {
            randomquiz_set_manual_allocation($randomquiz, $outsider->id, $quiz2->cmid);
        });
    }

    /**
     * Manual allocation rejects users without permission to attempt the selected quiz.
     */
    public function test_manual_allocation_rejects_user_without_quiz_attempt_capability(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, $teacher, $randomquiz, , $quiz2] = $this->create_randomquiz_fixture();
        $this->setUser($teacher);

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/quiz:attempt', CAP_PROHIBIT, $studentroleid, \context_module::instance($quiz2->cmid));
        accesslib_clear_all_caches_for_unit_testing();

        $this->assert_moodle_exception('invalidallocationuser', function() use ($randomquiz, $student, $quiz2): void {
            randomquiz_set_manual_allocation($randomquiz, $student->id, $quiz2->cmid);
        });
    }

    /**
     * Manual allocation rejects changes after the current allocation has been attempted.
     */
    public function test_manual_allocation_is_blocked_after_attempt(): void {
        $this->resetAfterTest();
        [, $student, $teacher, $randomquiz, $quiz1, $quiz2] = $this->create_randomquiz_fixture();
        $this->setUser($teacher);
        $this->create_allocation($randomquiz, $student, $quiz1);
        $this->create_quiz_attempt($student, $quiz1);

        $this->assert_moodle_exception('manualallocationblocked', function() use ($randomquiz, $student, $quiz2): void {
            randomquiz_set_manual_allocation($randomquiz, $student->id, $quiz2->cmid);
        });
    }
}
