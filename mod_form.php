<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/randomquiz/locallib.php');

class mod_randomquiz_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $COURSE;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('modulename', 'randomquiz'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'variantssection', get_string('teacherdashboard', 'randomquiz'));
        $mform->setExpanded('variantssection', true);

        $quizoptions = randomquiz_get_course_quiz_options((int)$COURSE->id);
        if ($quizoptions) {
            $attributes = [
                'multiple' => 'multiple',
                'size' => min(12, max(4, count($quizoptions))),
            ];
            $mform->addElement('select', 'variantcmids', get_string('variantcmids', 'randomquiz'), $quizoptions, $attributes);
            $mform->addHelpButton('variantcmids', 'variantcmids', 'randomquiz');
            $mform->setType('variantcmids', PARAM_INT);
        } else {
            $mform->addElement('static', 'variantcmidsnone', get_string('variantcmids', 'randomquiz'),
                get_string('novariants', 'randomquiz'));
        }

        $mform->addElement('select', 'allocationmode', get_string('allocationmode', 'randomquiz'), [
            RANDOMQUIZ_ALLOC_BALANCED => get_string('allocationmode:balanced', 'randomquiz'),
            RANDOMQUIZ_ALLOC_RANDOM => get_string('allocationmode:random', 'randomquiz'),
        ]);
        $mform->addHelpButton('allocationmode', 'allocationmode', 'randomquiz');
        $mform->setDefault('allocationmode', RANDOMQUIZ_ALLOC_BALANCED);
        $mform->setType('allocationmode', PARAM_ALPHA);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        if (!empty($this->current->instance)) {
            $defaultvalues['variantcmids'] = randomquiz_get_variant_cmids((int)$this->current->instance);
        }
    }

    public function validation($data, $files) {
        global $COURSE, $DB;

        $errors = parent::validation($data, $files);

        $variantcmids = $data['variantcmids'] ?? [];
        if (!is_array($variantcmids)) {
            $variantcmids = [$variantcmids];
        }
        $variantcmids = array_values(array_unique(array_filter(array_map('intval', $variantcmids))));

        if (count($variantcmids) < 2) {
            $errors['variantcmids'] = get_string('notenoughvariants', 'randomquiz');
        }

        foreach ($variantcmids as $cmid) {
            $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm || (int)$cm->course !== (int)$COURSE->id) {
                $errors['variantcmids'] = get_string('invalidcoursemodule');
                break;
            }
        }

        return $errors;
    }
}
