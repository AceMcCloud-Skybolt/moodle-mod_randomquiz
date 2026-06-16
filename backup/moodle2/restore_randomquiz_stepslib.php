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
 * Restore structure step for the random quiz allocator activity.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class restore_randomquiz_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('randomquiz', '/activity/randomquiz');
        $paths[] = new restore_path_element('randomquiz_variant', '/activity/randomquiz/variants/variant');
        if ($userinfo) {
            $paths[] = new restore_path_element('randomquiz_allocation', '/activity/randomquiz/allocations/allocation');
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function process_randomquiz($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('randomquiz', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('randomquiz', $oldid, $newitemid);
    }

    protected function process_randomquiz_variant($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->randomquizid = $this->get_new_parentid('randomquiz');
        $data->quizcmid = $this->get_mappingid('course_module', $data->quizcmid, 0);

        if (empty($data->quizcmid)) {
            return;
        }

        $newitemid = $DB->insert_record('randomquiz_variants', $data);
        $this->set_mapping('randomquiz_variant', $oldid, $newitemid);
    }

    protected function process_randomquiz_allocation($data) {
        global $DB;

        $data = (object)$data;
        $data->randomquizid = $this->get_new_parentid('randomquiz');
        $data->quizcmid = $this->get_mappingid('course_module', $data->quizcmid, 0);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);

        if (empty($data->quizcmid) || empty($data->userid)) {
            return;
        }

        $DB->insert_record('randomquiz_allocations', $data);
    }

    protected function after_execute() {
        global $DB;

        $randomquizid = $this->get_new_parentid('randomquiz');
        $variantcount = $DB->count_records('randomquiz_variants', ['randomquizid' => $randomquizid]);
        if ($variantcount < 2) {
            $randomquiz = $DB->get_record('randomquiz', ['id' => $randomquizid], 'id, name');
            $name = $randomquiz ? format_string($randomquiz->name) : get_string('modulename', 'randomquiz');
            $this->log(get_string('restoreinsufficientvariants', 'randomquiz', (object) [
                'name' => $name,
                'count' => $variantcount,
            ]), backup::LOG_WARNING, null, null, true);
        }

        $this->add_related_files('mod_randomquiz', 'intro', null);
    }
}
