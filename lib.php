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
 * Moodle module callbacks for the random quiz allocator activity.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/randomquiz/locallib.php');

/**
 * Declare supported Moodle module features.
 *
 * @param string $feature
 * @return mixed
 */
function randomquiz_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Add a random quiz allocator instance.
 *
 * @param stdClass $data
 * @param mod_randomquiz_mod_form|null $mform
 * @return int
 */
function randomquiz_add_instance($data, $mform = null) {
    global $DB;

    unset($mform);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->allocationmode = $data->allocationmode ?? RANDOMQUIZ_ALLOC_BALANCED;
    $data->id = $DB->insert_record('randomquiz', $data);
    randomquiz_save_variants((int)$data->id, $data->variantcmids ?? []);

    return $data->id;
}

/**
 * Update a random quiz allocator instance.
 *
 * @param stdClass $data
 * @param mod_randomquiz_mod_form|null $mform
 * @return bool
 */
function randomquiz_update_instance($data, $mform = null) {
    global $DB;

    unset($mform);
    $data->id = $data->instance;
    $data->timemodified = time();
    $data->allocationmode = $data->allocationmode ?? RANDOMQUIZ_ALLOC_BALANCED;
    $DB->update_record('randomquiz', $data);
    randomquiz_save_variants((int)$data->id, $data->variantcmids ?? []);

    return true;
}

/**
 * Delete a random quiz allocator instance.
 *
 * @param int $id
 * @return bool
 */
function randomquiz_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('randomquiz', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('randomquiz_allocations', ['randomquizid' => $id]);
    $DB->delete_records('randomquiz_variants', ['randomquizid' => $id]);
    $DB->delete_records('randomquiz', ['id' => $id]);

    return true;
}

/**
 * Return cached course module information.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 */
function randomquiz_get_coursemodule_info($coursemodule) {
    global $DB;

    $randomquiz = $DB->get_record('randomquiz', ['id' => $coursemodule->instance], 'id, name, intro, introformat');
    if (!$randomquiz) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $randomquiz->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('randomquiz', $randomquiz, $coursemodule->id, false);
    }

    return $info;
}
