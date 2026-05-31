<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/randomquiz/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'randomquiz'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'randomquiz'));

$instances = get_all_instances_in_course('randomquiz', $course);
if (!$instances) {
    echo $OUTPUT->notification(get_string('thereareno', 'moodle', get_string('modulenameplural', 'randomquiz')), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('name')];
foreach ($instances as $instance) {
    $table->data[] = [html_writer::link(new moodle_url('/mod/randomquiz/view.php', ['id' => $instance->coursemodule]), format_string($instance->name))];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
