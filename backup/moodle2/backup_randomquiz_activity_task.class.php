<?php
// This file is part of Moodle - http://moodle.org/

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
