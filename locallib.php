<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

define('RANDOMQUIZ_ALLOC_RANDOM', 'random');
define('RANDOMQUIZ_ALLOC_BALANCED', 'balanced');

/**
 * Quiz settings that are safe to copy across quiz variants.
 *
 * This deliberately excludes question slots, marks, names, descriptions and sumgrades.
 */
function randomquiz_syncable_quiz_fields(): array {
    return [
        'timeopen',
        'timeclose',
        'timelimit',
        'overduehandling',
        'graceperiod',
        'preferredbehaviour',
        'attempts',
        'attemptonlast',
        'grademethod',
        'decimalpoints',
        'questiondecimalpoints',
        'reviewattempt',
        'reviewcorrectness',
        'reviewmarks',
        'reviewspecificfeedback',
        'reviewgeneralfeedback',
        'reviewrightanswer',
        'reviewoverallfeedback',
        'questionsperpage',
        'navmethod',
        'shuffleanswers',
        'browsersecurity',
        'delay1',
        'delay2',
        'showuserpicture',
        'showblocks',
        'subnet',
        'quizpassword',
    ];
}

/**
 * Return quiz activities in the course for use as allocator variants.
 *
 * @param int $courseid
 * @return array cmid => formatted quiz name
 */
function randomquiz_get_course_quiz_options(int $courseid): array {
    global $DB;

    $sql = "SELECT cm.id, q.name
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {quiz} q ON q.id = cm.instance
             WHERE cm.course = :courseid
               AND m.name = :modname
               AND cm.deletioninprogress = 0
          ORDER BY cm.section, cm.id";

    $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'modname' => 'quiz']);
    $options = [];
    foreach ($records as $record) {
        $options[(int)$record->id] = format_string($record->name);
    }

    return $options;
}

/**
 * Persist selected variant quiz course module ids.
 *
 * @param int $randomquizid
 * @param array $quizcmids
 * @return void
 */
function randomquiz_save_variants(int $randomquizid, array $quizcmids): void {
    global $DB;

    $quizcmids = array_values(array_unique(array_filter(array_map('intval', $quizcmids))));
    $now = time();

    $DB->delete_records('randomquiz_variants', ['randomquizid' => $randomquizid]);

    foreach ($quizcmids as $sortorder => $quizcmid) {
        $record = (object) [
            'randomquizid' => $randomquizid,
            'quizcmid' => $quizcmid,
            'sortorder' => $sortorder,
            'enabled' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('randomquiz_variants', $record);
    }
}

/**
 * Get selected quiz cmids for an allocator.
 *
 * @param int $randomquizid
 * @return array
 */
function randomquiz_get_variant_cmids(int $randomquizid): array {
    global $DB;

    $records = $DB->get_records('randomquiz_variants', ['randomquizid' => $randomquizid], 'sortorder ASC', 'id, quizcmid');
    return array_map(fn($record) => (int)$record->quizcmid, $records);
}

/**
 * Get variant records with quiz and course-module details.
 *
 * @param int $randomquizid
 * @return array
 */
function randomquiz_get_variant_details(int $randomquizid): array {
    global $DB;

    $sql = "SELECT rv.id,
                   rv.randomquizid,
                   rv.quizcmid,
                   rv.sortorder,
                   rv.enabled,
                   cm.visible,
                   cm.visibleoncoursepage,
                   q.id AS quizid,
                   q.name AS quizname,
                   q.timeopen,
                   q.timeclose,
                   q.timelimit,
                   q.attempts,
                   q.grademethod,
                   q.grade,
                   q.sumgrades,
                   q.shuffleanswers,
                   q.navmethod,
                   q.browsersecurity
              FROM {randomquiz_variants} rv
              JOIN {course_modules} cm ON cm.id = rv.quizcmid
              JOIN {modules} m ON m.id = cm.module
              JOIN {quiz} q ON q.id = cm.instance
             WHERE rv.randomquizid = :randomquizid
               AND m.name = :modname
          ORDER BY rv.sortorder ASC, rv.id ASC";

    return $DB->get_records_sql($sql, ['randomquizid' => $randomquizid, 'modname' => 'quiz']);
}

/**
 * Get grade items for all quiz variants.
 *
 * @param int $randomquizid
 * @param int $courseid
 * @return array
 */
function randomquiz_get_variant_grade_items(int $randomquizid, int $courseid): array {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/grade/grade_item.php');

    $items = [];
    foreach (randomquiz_get_variant_details($randomquizid) as $variant) {
        $item = grade_item::fetch([
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $variant->quizid,
            'outcomeid' => null,
        ]);
        if ($item) {
            $items[$variant->quizcmid] = $item;
        }
    }

    return $items;
}

/**
 * Return gradebook readiness information for this allocator's quiz variants.
 *
 * @param stdClass $randomquiz
 * @param int $courseid
 * @return array
 */
function randomquiz_get_gradebook_status(stdClass $randomquiz, int $courseid): array {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/grade/grade_category.php');

    $variants = randomquiz_get_variant_details((int)$randomquiz->id);
    $items = randomquiz_get_variant_grade_items((int)$randomquiz->id, $courseid);
    $messages = [];

    if (count($items) !== count($variants)) {
        $messages[] = ['error', get_string('gradebookmissingitems', 'randomquiz')];
    }

    $categoryids = array_values(array_unique(array_map(fn($item) => (int)$item->categoryid, $items)));
    $category = null;
    if (count($categoryids) === 1 && $categoryids[0] > 0) {
        $category = grade_category::fetch(['id' => $categoryids[0], 'courseid' => $courseid]);
    } else if ($items) {
        $messages[] = ['warning', get_string('gradebooknocategory', 'randomquiz')];
    }

    if ($category && (int)$category->aggregation !== GRADE_AGGREGATE_MAX) {
        $messages[] = ['warning', get_string('gradebookwrongaggregation', 'randomquiz')];
    }

    if (!$messages && $category) {
        $messages[] = ['ok', get_string('gradebookready', 'randomquiz') . ': ' . format_string($category->fullname)];
    }

    return [
        'ready' => $category && count($items) === count($variants) && (int)$category->aggregation === GRADE_AGGREGATE_MAX,
        'category' => $category,
        'itemcount' => count($items),
        'variantcount' => count($variants),
        'messages' => $messages,
    ];
}

/**
 * Create or repair the grade category used by the allocator's variants.
 *
 * @param stdClass $randomquiz
 * @param int $courseid
 * @return array
 */
function randomquiz_setup_gradebook_category(stdClass $randomquiz, int $courseid): array {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/grade/grade_category.php');
    require_once($CFG->libdir . '/grade/grade_item.php');

    $items = randomquiz_get_variant_grade_items((int)$randomquiz->id, $courseid);
    $categoryname = format_string($randomquiz->name);
    $category = grade_category::fetch(['courseid' => $courseid, 'fullname' => $categoryname]);

    if (!$category) {
        $category = new grade_category([
            'courseid' => $courseid,
            'fullname' => $categoryname,
        ], false);
        $category->apply_default_settings();
        $category->aggregation = GRADE_AGGREGATE_MAX;
        $category->insert('mod/randomquiz');
    } else if ((int)$category->aggregation !== GRADE_AGGREGATE_MAX) {
        $category->aggregation = GRADE_AGGREGATE_MAX;
        $category->update('mod/randomquiz');
    }

    foreach ($items as $item) {
        $item->set_parent($category->id);
    }

    grade_force_full_regrading($courseid);
    grade_regrade_final_grades($courseid);

    return [
        'category' => format_string($category->fullname),
        'count' => count($items),
    ];
}

/**
 * Copy safe quiz settings from the first selected variant to all later variants.
 *
 * @param int $randomquizid
 * @return array source quiz name and number of updated variants
 */
function randomquiz_match_settings_from_first_variant(int $randomquizid): array {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/quiz/lib.php');
    randomquiz_require_manage_variant_quizzes($randomquizid);

    $variants = array_values(randomquiz_get_variant_details($randomquizid));
    if (count($variants) < 2) {
        return ['source' => '', 'count' => 0];
    }

    $sourcevariant = array_shift($variants);
    $sourcequiz = $DB->get_record('quiz', ['id' => $sourcevariant->quizid], '*', MUST_EXIST);

    $fields = randomquiz_syncable_quiz_fields();
    $transaction = $DB->start_delegated_transaction();
    $count = 0;

    foreach ($variants as $variant) {
        $quiz = $DB->get_record('quiz', ['id' => $variant->quizid], '*', MUST_EXIST);
        foreach ($fields as $field) {
            if (property_exists($sourcequiz, $field) && property_exists($quiz, $field)) {
                $quiz->{$field} = $sourcequiz->{$field};
            }
        }

        // Keep each variant's content and question marks intact, but align the gradebook maximum.
        $quiz->grade = $sourcequiz->grade;
        $quiz->timemodified = time();
        $quiz->coursemodule = $variant->quizcmid;
        $DB->update_record('quiz', $quiz);

        quiz_update_events($quiz);
        quiz_grade_item_update($quiz);
        $count++;
    }

    $transaction->allow_commit();

    return ['source' => format_string($sourcequiz->name), 'count' => $count];
}

/**
 * Check whether the current user can manage every linked quiz variant.
 *
 * @param int $randomquizid
 * @return bool
 */
function randomquiz_can_manage_variant_quizzes(int $randomquizid): bool {
    foreach (randomquiz_get_variant_details($randomquizid) as $variant) {
        if (!has_capability('mod/quiz:manage', \context_module::instance($variant->quizcmid))) {
            return false;
        }
    }

    return true;
}

/**
 * Require quiz management capability on every linked quiz variant.
 *
 * @param int $randomquizid
 * @return void
 */
function randomquiz_require_manage_variant_quizzes(int $randomquizid): void {
    foreach (randomquiz_get_variant_details($randomquizid) as $variant) {
        require_capability('mod/quiz:manage', \context_module::instance($variant->quizcmid));
    }
}

/**
 * Get variants that are enabled and launchable by students.
 *
 * Variants may be hidden from the course page, but they must not be fully hidden
 * from students or the redirect into Moodle Quiz will fail.
 *
 * @param int $randomquizid
 * @return array
 */
function randomquiz_get_launchable_variants(int $randomquizid): array {
    return array_values(array_filter(randomquiz_get_variant_details($randomquizid), function($variant): bool {
        return (int)$variant->enabled === 1 && (int)$variant->visible === 1;
    }));
}

/**
 * Return an existing allocation, or create one for the user.
 *
 * @param stdClass $randomquiz
 * @param int $userid
 * @return stdClass
 */
function randomquiz_get_or_create_allocation(stdClass $randomquiz, int $userid): stdClass {
    global $DB;

    $variants = randomquiz_get_launchable_variants((int)$randomquiz->id);
    $launchablecmids = array_map(fn($variant) => (int)$variant->quizcmid, $variants);

    $existing = $DB->get_record('randomquiz_allocations', [
        'randomquizid' => $randomquiz->id,
        'userid' => $userid,
    ]);
    if ($existing) {
        if (in_array((int)$existing->quizcmid, $launchablecmids, true) ||
                randomquiz_count_allocation_attempts($existing) > 0) {
            return $existing;
        }
        $DB->delete_records('randomquiz_allocations', ['id' => $existing->id]);
    }

    if (!$variants) {
        throw new moodle_exception('nolaunchablevariants', 'randomquiz');
    }

    $chosen = randomquiz_choose_variant($randomquiz, $variants);
    $allocation = (object) [
        'randomquizid' => $randomquiz->id,
        'userid' => $userid,
        'quizcmid' => $chosen->quizcmid,
        'timeallocated' => time(),
    ];
    $allocation->id = $DB->insert_record('randomquiz_allocations', $allocation);

    return $allocation;
}

/**
 * Count non-preview attempts for a student's allocated quiz.
 *
 * @param stdClass $allocation
 * @return int
 */
function randomquiz_count_allocation_attempts(stdClass $allocation): int {
    global $DB;

    $cm = get_coursemodule_from_id('quiz', $allocation->quizcmid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return 0;
    }

    return (int)$DB->count_records('quiz_attempts', [
        'quiz' => $cm->instance,
        'userid' => $allocation->userid,
        'preview' => 0,
    ]);
}

/**
 * Reset a stored allocation only if the assigned quiz has not been attempted.
 *
 * @param int $randomquizid
 * @param int $allocationid
 * @return string Reset user's full name.
 */
function randomquiz_reset_allocation_if_unattempted(int $randomquizid, int $allocationid): string {
    global $DB;

    $allocation = $DB->get_record('randomquiz_allocations', [
        'id' => $allocationid,
        'randomquizid' => $randomquizid,
    ]);
    if (!$allocation) {
        throw new moodle_exception('allocationnotfound', 'randomquiz');
    }

    if (randomquiz_count_allocation_attempts($allocation) > 0) {
        throw new moodle_exception('allocationresetblocked', 'randomquiz');
    }

    $user = $DB->get_record('user', ['id' => $allocation->userid], '*', MUST_EXIST);
    $DB->delete_records('randomquiz_allocations', ['id' => $allocation->id]);

    return fullname($user);
}

/**
 * Manually set a student's allocation if they have not started the current assigned quiz.
 *
 * @param stdClass $randomquiz
 * @param int $userid
 * @param int $quizcmid
 * @return string Updated user's full name.
 */
function randomquiz_set_manual_allocation(stdClass $randomquiz, int $userid, int $quizcmid): string {
    global $DB;

    $validcmids = randomquiz_get_variant_cmids((int)$randomquiz->id);
    if (!in_array($quizcmid, $validcmids, true)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*');
    if (!$user || !empty($user->suspended)) {
        throw new moodle_exception('invalidallocationuser', 'randomquiz');
    }

    $coursecontext = \context_course::instance($randomquiz->course);
    if (!is_enrolled($coursecontext, $user, '', true)) {
        throw new moodle_exception('invalidallocationuser', 'randomquiz');
    }

    $quizcm = get_coursemodule_from_id('quiz', $quizcmid, 0, false, MUST_EXIST);
    if ((int)$quizcm->course !== (int)$randomquiz->course || !(int)$quizcm->visible ||
            !has_capability('mod/quiz:attempt', \context_module::instance($quizcmid), $userid)) {
        throw new moodle_exception('invalidallocationuser', 'randomquiz');
    }

    $existing = $DB->get_record('randomquiz_allocations', [
        'randomquizid' => $randomquiz->id,
        'userid' => $userid,
    ]);
    if ($existing && randomquiz_count_allocation_attempts($existing) > 0) {
        throw new moodle_exception('manualallocationblocked', 'randomquiz');
    }

    $now = time();
    if ($existing) {
        $existing->quizcmid = $quizcmid;
        $existing->timeallocated = $now;
        $DB->update_record('randomquiz_allocations', $existing);
    } else {
        $DB->insert_record('randomquiz_allocations', (object) [
            'randomquizid' => $randomquiz->id,
            'userid' => $userid,
            'quizcmid' => $quizcmid,
            'timeallocated' => $now,
        ]);
    }

    return fullname($user);
}

/**
 * Get enrolled users who can be manually allocated.
 *
 * @param stdClass $course
 * @param context_module $context
 * @return array userid => fullname
 */
function randomquiz_get_allocatable_user_options(stdClass $course, context_module $context): array {
    $coursecontext = \context_course::instance($course->id);
    $users = get_enrolled_users($coursecontext, 'mod/quiz:attempt', 0, 'u.id, u.firstname, u.lastname',
        'u.lastname, u.firstname');
    if (!$users) {
        $users = get_enrolled_users($coursecontext, '', 0, 'u.id, u.firstname, u.lastname', 'u.lastname, u.firstname');
    }

    $options = [];
    foreach ($users as $user) {
        $options[(int)$user->id] = fullname($user);
    }

    return $options;
}

/**
 * Choose a variant according to the instance allocation mode.
 *
 * @param stdClass $randomquiz
 * @param array $variants
 * @return stdClass
 */
function randomquiz_choose_variant(stdClass $randomquiz, array $variants): stdClass {
    global $DB;

    if (($randomquiz->allocationmode ?? RANDOMQUIZ_ALLOC_BALANCED) !== RANDOMQUIZ_ALLOC_BALANCED) {
        return $variants[random_int(0, count($variants) - 1)];
    }

    [$insql, $params] = $DB->get_in_or_equal(array_map(fn($variant) => (int)$variant->quizcmid, $variants), SQL_PARAMS_NAMED);
    $params['randomquizid'] = $randomquiz->id;
    $counts = $DB->get_records_sql_menu(
        "SELECT quizcmid, COUNT(1)
           FROM {randomquiz_allocations}
          WHERE randomquizid = :randomquizid
            AND quizcmid {$insql}
       GROUP BY quizcmid",
        $params
    );

    $lowest = null;
    $candidates = [];
    foreach ($variants as $variant) {
        $count = (int)($counts[$variant->quizcmid] ?? 0);
        if ($lowest === null || $count < $lowest) {
            $lowest = $count;
            $candidates = [$variant];
        } else if ($count === $lowest) {
            $candidates[] = $variant;
        }
    }

    return $candidates[random_int(0, count($candidates) - 1)];
}

/**
 * Get allocation records for teacher reporting.
 *
 * @param int $randomquizid
 * @return array
 */
function randomquiz_get_allocations(int $randomquizid): array {
    global $DB;

    $fields = \core_user\fields::for_name()->with_identity(null, false)->get_sql('u', false, '', '', false)->selects;
    $sql = "SELECT a.id,
                   a.userid,
                   a.quizcmid,
                   a.timeallocated,
                   {$fields},
                   q.id AS quizid,
                   q.name AS quizname
              FROM {randomquiz_allocations} a
              JOIN {user} u ON u.id = a.userid
              JOIN {course_modules} cm ON cm.id = a.quizcmid
              JOIN {quiz} q ON q.id = cm.instance
             WHERE a.randomquizid = :randomquizid
          ORDER BY a.timeallocated DESC, a.id DESC";

    return $DB->get_records_sql($sql, ['randomquizid' => $randomquizid]);
}

/**
 * Build readiness checks for a variant.
 *
 * @param stdClass $variant
 * @param stdClass|null $template
 * @return array
 */
function randomquiz_variant_checks(stdClass $variant, ?stdClass $template): array {
    $checks = [];

    if (!(int)$variant->visible) {
        $checks[] = ['error', get_string('hiddenfromstudents', 'randomquiz')];
    } else if (!(int)$variant->visibleoncoursepage) {
        $checks[] = ['ok', get_string('availablehidden', 'randomquiz')];
    } else {
        $checks[] = ['warning', get_string('visibleoncoursepage', 'randomquiz')];
    }

    if ((float)$variant->sumgrades > 0) {
        $checks[] = ['ok', get_string('hasquestions', 'randomquiz')];
    } else {
        $checks[] = ['warning', get_string('noquestions', 'randomquiz')];
    }

    if ($template) {
        $fields = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'grademethod', 'grade', 'shuffleanswers', 'browsersecurity'];
        foreach ($fields as $field) {
            if ((string)$variant->{$field} !== (string)$template->{$field}) {
                $checks[] = ['warning', randomquiz_setting_difference_label($field)];
            }
        }
    }

    return $checks;
}

/**
 * Return a teacher-facing label for a mismatched quiz setting.
 *
 * @param string $field
 * @return string
 */
function randomquiz_setting_difference_label(string $field): string {
    $stringid = 'settingdiff:' . $field;
    if (get_string_manager()->string_exists($stringid, 'randomquiz')) {
        return get_string($stringid, 'randomquiz');
    }

    return get_string('settings') . ': ' . s($field);
}

/**
 * Convert a readiness state to a Bootstrap badge class.
 *
 * @param string $state
 * @return string
 */
function randomquiz_badge_class(string $state): string {
    if ($state === 'ok') {
        return 'text-bg-success';
    }
    if ($state === 'error') {
        return 'text-bg-danger';
    }
    return 'text-bg-warning';
}
