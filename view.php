<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/randomquiz/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$r = optional_param('r', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('randomquiz', $id, 0, false, MUST_EXIST);
    $randomquiz = $DB->get_record('randomquiz', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $randomquiz = $DB->get_record('randomquiz', ['id' => $r], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('randomquiz', $randomquiz->id, $randomquiz->course, false, MUST_EXIST);
}

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/randomquiz:view', $context);

$PAGE->set_url('/mod/randomquiz/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($randomquiz->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$canmanage = has_capability('mod/randomquiz:manage', $context);
$action = optional_param('action', '', PARAM_ALPHA);

if (!$canmanage) {
    $allocation = randomquiz_get_or_create_allocation($randomquiz, (int)$USER->id);
    $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $allocation->quizcmid]);

    if ($action === 'startquiz') {
        require_sesskey();
        redirect($quizurl);
    }

    echo $OUTPUT->header();
    echo html_writer::start_div('container-fluid p-0');
    echo html_writer::start_div('border rounded p-4 bg-light');
    echo html_writer::tag('h3', get_string('studentready', 'randomquiz'), ['class' => 'mb-3']);
    echo html_writer::tag('p', get_string('studentreadyintro', 'randomquiz'), ['class' => 'mb-3']);
    echo html_writer::tag('p', get_string('allocationlockedonstart', 'randomquiz'), ['class' => 'text-muted']);
    $starturl = new moodle_url('/mod/randomquiz/view.php', [
        'id' => $cm->id,
        'action' => 'startquiz',
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($starturl, get_string('startquiz', 'randomquiz'), 'post', ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'syncsettings') {
    require_sesskey();
    require_capability('mod/randomquiz:manage', $context);
    $result = randomquiz_match_settings_from_first_variant((int)$randomquiz->id);
    if ($result['count'] > 0) {
        redirect($PAGE->url, get_string('settingsmatchedcount', 'randomquiz', (object)$result), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($PAGE->url, get_string('settingsmatchnone', 'randomquiz'), null,
        \core\output\notification::NOTIFY_WARNING);
} else if ($action === 'gradebooksetup') {
    require_sesskey();
    require_capability('mod/randomquiz:manage', $context);
    $result = randomquiz_setup_gradebook_category($randomquiz, (int)$course->id);
    redirect($PAGE->url, get_string('gradebooksetupdone', 'randomquiz', (object)$result), null,
        \core\output\notification::NOTIFY_SUCCESS);
} else if ($action === 'resetallocation') {
    require_sesskey();
    require_capability('mod/randomquiz:manage', $context);
    $allocationid = required_param('allocationid', PARAM_INT);
    $username = randomquiz_reset_allocation_if_unattempted((int)$randomquiz->id, $allocationid);
    redirect($PAGE->url, get_string('allocationreset', 'randomquiz', $username), null,
        \core\output\notification::NOTIFY_SUCCESS);
} else if ($action === 'manualallocation') {
    require_sesskey();
    require_capability('mod/randomquiz:manage', $context);
    $userid = required_param('userid', PARAM_INT);
    $quizcmid = required_param('quizcmid', PARAM_INT);
    $username = randomquiz_set_manual_allocation($randomquiz, $userid, $quizcmid);
    redirect($PAGE->url, get_string('allocationupdated', 'randomquiz', $username), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

$variants = array_values(randomquiz_get_variant_details((int)$randomquiz->id));
$template = $variants[0] ?? null;

echo html_writer::start_div('container-fluid p-0');
echo html_writer::start_div('p-4 mb-4 border rounded bg-light');
echo html_writer::tag('p', get_string('pluginname', 'randomquiz'), ['class' => 'text-uppercase text-muted small mb-1']);
echo html_writer::tag('h3', get_string('teacherdashboard', 'randomquiz'), ['class' => 'mb-2']);
echo html_writer::tag('p',
    'Students see this one activity. On first launch Moodle stores their allocation and redirects them to the assigned quiz variant.',
    ['class' => 'mb-3']);
echo html_writer::start_div('d-flex flex-wrap gap-2');
echo html_writer::span(get_string('allocationmode:' . $randomquiz->allocationmode, 'randomquiz'),
    'badge rounded-pill text-bg-primary');
echo html_writer::span('Locks on first launch', 'badge rounded-pill text-bg-secondary');
echo html_writer::span('Uses normal Moodle quizzes', 'badge rounded-pill text-bg-secondary');
echo html_writer::span('Gradebook: highest grade category', 'badge rounded-pill text-bg-secondary');
echo html_writer::end_div();
echo html_writer::end_div();

if (count($variants) > 1) {
    $syncurl = new moodle_url('/mod/randomquiz/view.php', [
        'id' => $cm->id,
        'action' => 'syncsettings',
    ]);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $syncurl->out(false),
        'class' => 'mb-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('button', get_string('matchsettings', 'randomquiz'), [
        'type' => 'submit',
        'class' => 'btn btn-outline-secondary',
    ]);
    echo html_writer::end_tag('form');
}

$gradebookstatus = randomquiz_get_gradebook_status($randomquiz, (int)$course->id);
echo html_writer::start_div('border rounded p-3 mb-3');
echo html_writer::tag('h4', get_string('gradebookcategory', 'randomquiz'), ['class' => 'h5 mb-2']);
echo html_writer::start_div('mb-3');
foreach ($gradebookstatus['messages'] as [$state, $message]) {
    echo html_writer::span($message, 'badge rounded-pill ' . randomquiz_badge_class($state) . ' me-1 mb-1');
}
echo html_writer::end_div();
$gradebookurl = new moodle_url('/mod/randomquiz/view.php', [
    'id' => $cm->id,
    'action' => 'gradebooksetup',
]);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $gradebookurl->out(false),
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('button', get_string('gradebooksetup', 'randomquiz'), [
    'type' => 'submit',
    'class' => 'btn btn-outline-secondary',
]);
echo html_writer::end_tag('form');
echo html_writer::end_div();

if (!$variants) {
    echo $OUTPUT->notification(get_string('novariants', 'randomquiz'), 'warning');
} else {
    $table = new html_table();
    $table->head = ['Variant', 'Quiz shell', 'Readiness checks', 'Shared settings'];
    $table->attributes['class'] = 'table table-sm table-bordered align-middle';

    foreach ($variants as $index => $variant) {
        $checks = randomquiz_variant_checks($variant, $template);
        $badges = [];
        foreach ($checks as [$state, $label]) {
            $badges[] = html_writer::span($label, 'badge rounded-pill ' . randomquiz_badge_class($state) . ' me-1 mb-1');
        }

        $settings = [
            get_string('timelimit', 'quiz') . ': ' . format_time((int)$variant->timelimit),
            get_string('attemptsallowed', 'quiz') . ': ' . ((int)$variant->attempts === 0 ? get_string('unlimited') : (int)$variant->attempts),
            'Navigation: ' . ($variant->navmethod === 'sequential' ? 'Sequential' : 'Free'),
            get_string('grademax', 'grades') . ': ' . format_float($variant->grade, 2),
        ];

        $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $variant->quizcmid]);
        $table->data[] = [
            'Variant ' . chr(65 + $index),
            html_writer::link($quizurl, format_string($variant->quizname)),
            implode(' ', $badges),
            implode(html_writer::empty_tag('br'), $settings),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->notification(
    'MVP note: the gradebook helper creates a highest-grade category for the variant quiz grade items. Review the gradebook if your course already uses a complex grading structure.',
    'info'
);

$allocations = randomquiz_get_allocations((int)$randomquiz->id);
echo $OUTPUT->heading(get_string('allocations', 'randomquiz'), 3);
if (!$allocations) {
    echo html_writer::tag('p', get_string('noallocations', 'randomquiz'), ['class' => 'text-muted']);
} else {
    $allocationtable = new html_table();
    $allocationtable->head = ['Student', 'Allocated quiz', 'Attempt status', 'Time', 'Actions'];
    $allocationtable->attributes['class'] = 'table table-sm table-striped';
    foreach ($allocations as $allocation) {
        $attemptcount = randomquiz_count_allocation_attempts($allocation);
        if ($attemptcount > 0) {
            $attemptstatus = html_writer::span(get_string('attemptstarted', 'randomquiz'), 'badge rounded-pill text-bg-danger');
            $actions = html_writer::span(get_string('attemptlocked', 'randomquiz'), 'text-muted');
        } else {
            $attemptstatus = html_writer::span(get_string('attemptnotstarted', 'randomquiz'), 'badge rounded-pill text-bg-success');
            $reseturl = new moodle_url('/mod/randomquiz/view.php', [
                'id' => $cm->id,
                'action' => 'resetallocation',
                'allocationid' => $allocation->id,
            ]);
            $actions = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $reseturl->out(false),
                'class' => 'm-0',
            ]);
            $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $actions .= html_writer::tag('button', get_string('resetallocation', 'randomquiz'), [
                'type' => 'submit',
                'class' => 'btn btn-sm btn-outline-danger',
            ]);
            $actions .= html_writer::end_tag('form');
        }

        $allocationtable->data[] = [
            fullname($allocation),
            format_string($allocation->quizname),
            $attemptstatus,
            userdate($allocation->timeallocated),
            $actions,
        ];
    }
    echo html_writer::table($allocationtable);
}

$useroptions = randomquiz_get_allocatable_user_options($course, $context);
$variantoptions = [];
foreach ($variants as $variant) {
    $variantoptions[(int)$variant->quizcmid] = format_string($variant->quizname);
}
if ($useroptions && $variantoptions) {
    echo html_writer::start_div('border rounded p-3 mt-3');
    echo html_writer::tag('h4', get_string('manualallocation', 'randomquiz'), ['class' => 'h5 mb-2']);
    echo html_writer::tag('p', get_string('manualallocation_help', 'randomquiz'), ['class' => 'text-muted mb-3']);
    $manualurl = new moodle_url('/mod/randomquiz/view.php', [
        'id' => $cm->id,
        'action' => 'manualallocation',
    ]);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $manualurl->out(false),
        'class' => 'row g-2 align-items-end',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::start_div('col-md-5');
    echo html_writer::label(get_string('selectstudent', 'randomquiz'), 'randomquiz-userid', true, ['class' => 'form-label']);
    echo html_writer::select($useroptions, 'userid', '', false, ['id' => 'randomquiz-userid', 'class' => 'form-select']);
    echo html_writer::end_div();
    echo html_writer::start_div('col-md-5');
    echo html_writer::label(get_string('selectvariant', 'randomquiz'), 'randomquiz-quizcmid', true, ['class' => 'form-label']);
    echo html_writer::select($variantoptions, 'quizcmid', '', false, ['id' => 'randomquiz-quizcmid', 'class' => 'form-select']);
    echo html_writer::end_div();
    echo html_writer::start_div('col-md-2');
    echo html_writer::tag('button', get_string('setallocation', 'randomquiz'), [
        'type' => 'submit',
        'class' => 'btn btn-outline-secondary w-100',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $OUTPUT->footer();
