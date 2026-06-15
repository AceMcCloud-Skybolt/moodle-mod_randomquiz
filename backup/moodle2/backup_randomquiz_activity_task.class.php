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
 * Backup activity task for the random quiz allocator activity.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/randomquiz/backup/moodle2/backup_randomquiz_stepslib.php');

class backup_randomquiz_activity_task extends backup_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new backup_randomquiz_activity_structure_step('randomquiz_structure', 'randomquiz.xml'));
    }

    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = "/(" . $base . "\/mod\/randomquiz\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@RANDOMQUIZINDEX*$2@$', $content);

        $search = "/(" . $base . "\/mod\/randomquiz\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@RANDOMQUIZVIEWBYID*$2@$', $content);

        return $content;
    }
}
