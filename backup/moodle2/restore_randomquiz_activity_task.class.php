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
 * Restore activity task for the random quiz allocator activity.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/randomquiz/backup/moodle2/restore_randomquiz_stepslib.php');

class restore_randomquiz_activity_task extends restore_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new restore_randomquiz_activity_structure_step('randomquiz_structure', 'randomquiz.xml'));
    }

    public static function define_decode_contents() {
        return [
            new restore_decode_content('randomquiz', ['intro'], 'randomquiz'),
        ];
    }

    public static function define_decode_rules() {
        return [
            new restore_decode_rule('RANDOMQUIZVIEWBYID', '/mod/randomquiz/view.php?id=$1', 'course_module'),
            new restore_decode_rule('RANDOMQUIZINDEX', '/mod/randomquiz/index.php?id=$1', 'course'),
        ];
    }

    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('randomquiz', 'add', 'view.php?id={course_module}', '{randomquiz}'),
            new restore_log_rule('randomquiz', 'update', 'view.php?id={course_module}', '{randomquiz}'),
            new restore_log_rule('randomquiz', 'view', 'view.php?id={course_module}', '{randomquiz}'),
        ];
    }

    public static function define_restore_log_rules_for_course() {
        return [
            new restore_log_rule('randomquiz', 'view all', 'index.php?id={course}', null),
        ];
    }
}
