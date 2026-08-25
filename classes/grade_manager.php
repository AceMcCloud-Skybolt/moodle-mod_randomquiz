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
 * Gradebook and quiz settings management for the random quiz allocator activity.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_randomquiz;

use grade_category;
use grade_item;
use moodle_exception;
use stdClass;

/**
 * Handles gradebook category setup and cross-variant quiz settings synchronisation.
 *
 * @package    mod_randomquiz
 * @copyright  2026 Murdoch University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_manager {
    /**
     * Get grade items for all quiz variants keyed by quiz course module id.
     *
     * All quiz grade items for the course are fetched in a single query and
     * matched against the variants in PHP.
     *
     * @param int $randomquizid
     * @param int $courseid
     * @return array
     */
    public static function get_variant_grade_items(int $randomquizid, int $courseid): array {
        $variants = randomquiz_get_variant_details($randomquizid);
        if (!$variants) {
            return [];
        }

        $courseitems = grade_item::fetch_all([
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'outcomeid' => null,
        ]) ?: [];

        $itemsbyquizid = [];
        foreach ($courseitems as $item) {
            $itemsbyquizid[(int)$item->iteminstance] = $item;
        }

        $items = [];
        foreach ($variants as $variant) {
            if (isset($itemsbyquizid[(int)$variant->quizid])) {
                $items[$variant->quizcmid] = $itemsbyquizid[(int)$variant->quizid];
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
    public static function get_gradebook_status(stdClass $randomquiz, int $courseid): array {
        $variants = randomquiz_get_variant_details((int)$randomquiz->id);
        $items = self::get_variant_grade_items((int)$randomquiz->id, $courseid);
        $messages = [];

        if (count($items) !== count($variants)) {
            $messages[] = ['error', get_string('gradebookmissingitems', 'randomquiz')];
        }

        $category = self::get_common_grade_category($items, $courseid, $messages);
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
     * Return the shared grade category for variant grade items.
     *
     * @param array $items
     * @param int $courseid
     * @param array $messages
     * @return grade_category|null
     */
    public static function get_common_grade_category(array $items, int $courseid, array &$messages): ?grade_category {
        $categoryids = array_values(array_unique(array_map(fn($item) => (int)$item->categoryid, $items)));
        if (count($categoryids) === 1 && $categoryids[0] > 0) {
            return grade_category::fetch(['id' => $categoryids[0], 'courseid' => $courseid]) ?: null;
        }

        if ($items) {
            $messages[] = ['warning', get_string('gradebooknocategory', 'randomquiz')];
        }

        return null;
    }

    /**
     * Create or repair the grade category used by the allocator's variants.
     *
     * The full course regrade is queued as an adhoc task rather than run inline,
     * because it is too expensive to block the web request with.
     *
     * @param stdClass $randomquiz
     * @param int $courseid
     * @return array
     */
    public static function setup_gradebook_category(stdClass $randomquiz, int $courseid): array {
        $items = self::get_variant_grade_items((int)$randomquiz->id, $courseid);
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

        task\regrade_course::queue($courseid);

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
    public static function match_settings_from_first_variant(int $randomquizid): array {
        global $DB;

        randomquiz_require_manage_variant_quizzes($randomquizid);

        $randomquiz = $DB->get_record('randomquiz', ['id' => $randomquizid], '*', MUST_EXIST);
        $variants = array_values(randomquiz_get_variant_details($randomquizid));

        // Never write to quiz rows outside the allocator's own course.
        foreach ($variants as $variant) {
            if ((int)$variant->quizcourse !== (int)$randomquiz->course) {
                throw new moodle_exception('invalidcoursemodule');
            }
        }

        if (count($variants) < 2) {
            return ['source' => '', 'count' => 0];
        }

        $quizids = array_map(static fn($variant): int => (int)$variant->quizid, $variants);
        $quizzes = $DB->get_records_list('quiz', 'id', $quizids);
        if (count($quizzes) !== count($quizids)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        $sourcevariant = array_shift($variants);
        $sourcequiz = $quizzes[$sourcevariant->quizid];

        $fields = randomquiz_syncable_quiz_fields();
        $transaction = $DB->start_delegated_transaction();
        $count = 0;

        foreach ($variants as $variant) {
            $quiz = $quizzes[$variant->quizid];
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
}
