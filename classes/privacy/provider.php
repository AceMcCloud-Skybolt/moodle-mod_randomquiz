<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_randomquiz\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('randomquiz_allocations', [
            'randomquizid' => 'privacy:metadata:allocations:randomquizid',
            'userid' => 'privacy:metadata:allocations:userid',
            'quizcmid' => 'privacy:metadata:allocations:quizcmid',
            'timeallocated' => 'privacy:metadata:allocations:timeallocated',
        ], 'privacy:metadata:allocations');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {randomquiz} rq ON rq.id = cm.instance
                  JOIN {randomquiz_allocations} rqa ON rqa.randomquizid = rq.id
                 WHERE rqa.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'randomquiz',
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT rqa.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {randomquiz} rq ON rq.id = cm.instance
                  JOIN {randomquiz_allocations} rqa ON rqa.randomquizid = rq.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, [
            'cmid' => $context->instanceid,
            'modname' => 'randomquiz',
        ]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $sql = "SELECT ctx.id AS contextid,
                       cm.id AS cmid,
                       q.name AS quizname,
                       rqa.quizcmid,
                       rqa.timeallocated
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {randomquiz} rq ON rq.id = cm.instance
                  JOIN {randomquiz_allocations} rqa ON rqa.randomquizid = rq.id
             LEFT JOIN {course_modules} qcm ON qcm.id = rqa.quizcmid
             LEFT JOIN {modules} qm ON qm.id = qcm.module AND qm.name = :quizmodname
             LEFT JOIN {quiz} q ON q.id = qcm.instance
                 WHERE ctx.id {$contextsql}
                   AND rqa.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'randomquiz',
            'quizmodname' => 'quiz',
            'userid' => $user->id,
        ] + $contextparams;

        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $context = \context_module::instance($record->cmid);
            $contextdata = helper::get_context_data($context, $user);
            $allocationdata = [
                'allocatedquiz' => $record->quizname,
                'allocatedquizcmid' => $record->quizcmid,
                'timeallocated' => transform::datetime($record->timeallocated),
            ];
            $data = (object)array_merge((array)$contextdata, $allocationdata);
            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $user);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('randomquiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('randomquiz_allocations', ['randomquizid' => $cm->instance]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('randomquiz', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('randomquiz_allocations', [
                'randomquizid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('randomquiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('randomquiz_allocations',
            "randomquizid = :randomquizid AND userid {$usersql}",
            ['randomquizid' => $cm->instance] + $userparams);
    }
}
