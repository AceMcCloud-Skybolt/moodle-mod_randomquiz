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
 * Output renderer for the random quiz allocator activity.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomquiz\output;

/**
 * Render theme-overridable random quiz allocator views.
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the student launch card.
     *
     * @param \moodle_url $actionurl
     * @return string
     */
    public function student_launch_card(\moodle_url $actionurl): string {
        return $this->render_from_template('mod_randomquiz/student_launch_card', [
            'title' => get_string('studentready', 'mod_randomquiz'),
            'intro' => get_string('studentreadyintro', 'mod_randomquiz'),
            'lockedmessage' => get_string('allocationlockedonstart', 'mod_randomquiz'),
            'buttonlabel' => get_string('startquiz', 'mod_randomquiz'),
            'actionurl' => $actionurl->out(false),
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Render the teacher dashboard introduction.
     *
     * @param \stdClass $randomquiz
     * @return string
     */
    public function teacher_dashboard_header(\stdClass $randomquiz): string {
        return $this->render_from_template('mod_randomquiz/teacher_dashboard_header', [
            'pluginname' => get_string('pluginname', 'mod_randomquiz'),
            'title' => get_string('teacherdashboard', 'mod_randomquiz'),
            'intro' => get_string('teacherdashboardintro', 'mod_randomquiz'),
            'allocationmode' => get_string('allocationmode:' . $randomquiz->allocationmode, 'mod_randomquiz'),
            'lockslabel' => get_string('badgelocksonlaunch', 'mod_randomquiz'),
            'quizzeslabel' => get_string('badgeusesmoodlequizzes', 'mod_randomquiz'),
            'gradebooklabel' => get_string('badgehighestgradecategory', 'mod_randomquiz'),
        ]);
    }
}
