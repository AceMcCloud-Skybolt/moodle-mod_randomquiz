<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

class backup_randomquiz_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $randomquiz = new backup_nested_element('randomquiz', ['id'], [
            'course',
            'name',
            'intro',
            'introformat',
            'allocationmode',
            'timemodified',
        ]);

        $variants = new backup_nested_element('variants');
        $variant = new backup_nested_element('variant', ['id'], [
            'quizcmid',
            'sortorder',
            'enabled',
            'timecreated',
            'timemodified',
        ]);

        $allocations = new backup_nested_element('allocations');
        $allocation = new backup_nested_element('allocation', ['id'], [
            'userid',
            'quizcmid',
            'timeallocated',
        ]);

        $randomquiz->add_child($variants);
        $variants->add_child($variant);

        $randomquiz->add_child($allocations);
        $allocations->add_child($allocation);

        $randomquiz->set_source_table('randomquiz', ['id' => backup::VAR_ACTIVITYID]);
        $variant->set_source_table('randomquiz_variants', ['randomquizid' => backup::VAR_PARENTID], 'sortorder ASC');

        if ($userinfo) {
            $allocation->set_source_table('randomquiz_allocations', ['randomquizid' => backup::VAR_PARENTID]);
        }

        $variant->annotate_ids('course_module', 'quizcmid');
        $allocation->annotate_ids('course_module', 'quizcmid');
        $allocation->annotate_ids('user', 'userid');

        $randomquiz->annotate_files('mod_randomquiz', 'intro', null);

        return $this->prepare_activity_structure($randomquiz);
    }
}
