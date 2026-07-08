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
 * Adhoc task to regrade a course after gradebook category changes.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomquiz\task;

/**
 * Runs a full course regrade asynchronously after gradebook setup.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regrade_course extends \core\task\adhoc_task {
    /**
     * Queue a regrade for the given course, avoiding duplicate queued tasks.
     *
     * @param int $courseid
     * @return void
     */
    public static function queue(int $courseid): void {
        $task = new self();
        $task->set_custom_data(['courseid' => $courseid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Perform the full course regrade.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/gradelib.php');

        $courseid = (int)$this->get_custom_data()->courseid;
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return;
        }

        grade_force_full_regrading($courseid);
        grade_regrade_final_grades($courseid);
    }
}
