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
 * Gradebook setup completed event.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomquiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when the gradebook helper completes.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradebook_setup_completed extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'randomquiz';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventgradebooksetupcompleted', 'mod_randomquiz');
    }

    /**
     * Return event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' completed gradebook setup for random quiz allocator id " .
            "'{$this->objectid}', updating '{$this->other['count']}' quiz grade items.";
    }

    /**
     * Return relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/randomquiz/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['randomquizid']) || !isset($this->other['category']) || !isset($this->other['count'])) {
            throw new \coding_exception('The \'randomquizid\', \'category\' and \'count\' values must be set in other.');
        }
    }

    /**
     * Return object id mapping.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'randomquiz', 'restore' => 'randomquiz'];
    }
}
