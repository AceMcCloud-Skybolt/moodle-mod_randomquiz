<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/randomquiz/locallib.php');

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

function randomquiz_add_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->allocationmode = $data->allocationmode ?? RANDOMQUIZ_ALLOC_BALANCED;
    $data->id = $DB->insert_record('randomquiz', $data);
    randomquiz_save_variants((int)$data->id, $data->variantcmids ?? []);

    return $data->id;
}

function randomquiz_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $data->allocationmode = $data->allocationmode ?? RANDOMQUIZ_ALLOC_BALANCED;
    $DB->update_record('randomquiz', $data);
    randomquiz_save_variants((int)$data->id, $data->variantcmids ?? []);

    return true;
}

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
