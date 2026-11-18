<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace local_quicknote\reportbuilder\datasource;

use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use core_reportbuilder_generator;
use local_quicknote\reportbuilder\local\entities\note;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for QuickNote Report Builder datasource.
 *
 * @package     local_quicknote
 * @category    test
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(notes::class)]
#[CoversClass(note::class)]
final class notes_test extends core_reportbuilder_testcase {
    /**
     * Helper to insert a QuickNote record directly for testing.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function create_note(array $overrides = []): \stdClass {
        global $DB;

        $now = time();
        $record = (object) array_merge([
            'userid' => 2,
            'courseid' => SITEID,
            'content' => 'Top secret private student note',
            'quote' => null,
            'quoteurl' => null,
            'url' => 'https://example.com/course/view.php?id=1',
            'pagehash' => hash('sha256', 'https://example.com/course/view.php?id=1'),
            'pagetitle' => 'Default page',
            'timecreated' => $now,
            'timemodified' => $now,
        ], $overrides);

        $record->id = $DB->insert_record('local_quicknote_notes', $record);
        return $record;
    }

    /**
     * Test datasource is discovered by Report Builder manager.
     */
    public function test_datasource_registered(): void {
        $this->resetAfterTest();

        $datasources = manager::get_report_datasources();
        $pluginname = get_string('pluginname', 'local_quicknote');

        $this->assertArrayHasKey($pluginname, $datasources);
        $this->assertArrayHasKey(notes::class, $datasources[$pluginname]);
        $this->assertEquals(
            get_string('report:notesusage', 'local_quicknote'),
            $datasources[$pluginname][notes::class]
        );
    }

    /**
     * Test that private note fields are never exposed as columns, filters, or conditions.
     */
    public function test_privacy_excludes_private_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'QuickNote usage', 'source' => notes::class, 'default' => 0]);

        $instance = manager::get_report_from_persistent($report);

        $columnnames = array_keys($instance->get_columns());
        $filternames = array_keys($instance->get_filters());
        $conditionnames = array_keys($instance->get_conditions());

        foreach (['note:content', 'note:quote', 'note:quoteurl'] as $forbidden) {
            $this->assertNotContains($forbidden, $columnnames);
            $this->assertNotContains($forbidden, $filternames);
            $this->assertNotContains($forbidden, $conditionnames);
        }

        // Also ensure a raw text-fragment in URL is stripped by the URL column callback.
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Privacy Course']);
        $user = $this->getDataGenerator()->create_user();
        $this->create_note([
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'SUPER_SECRET_CONTENT',
            'quote' => 'SUPER_SECRET_QUOTE',
            'url' => 'https://example.com/mod/page/view.php?id=9#:~:text=SUPER_SECRET_QUOTE',
            'pagetitle' => 'Week 1 Reading',
        ]);

        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'note:url']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'note:hasquote']);
        $content = $this->get_custom_report_content($report->get('id'));

        $this->assertCount(1, $content);
        $values = array_values($content[0]);
        $this->assertEquals('https://example.com/mod/page/view.php?id=9', $values[0]);
        $this->assertEquals(get_string('yes'), $values[1]);
        $this->assertStringNotContainsString('SUPER_SECRET', json_encode($content));
    }

    /**
     * Test default datasource columns and sorting.
     */
    public function test_datasource_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $coursea = $this->getDataGenerator()->create_course(['fullname' => 'Alpha Course']);
        $courseb = $this->getDataGenerator()->create_course(['fullname' => 'Beta Course']);
        $user = $this->getDataGenerator()->create_user();

        $note1 = $this->create_note([
            'userid' => $user->id,
            'courseid' => $coursea->id,
            'quote' => 'Highlighted passage',
            'pagetitle' => 'Lesson 1',
            'timecreated' => 1700000100,
            'timemodified' => 1700000100,
        ]);

        $note2 = $this->create_note([
            'userid' => $user->id,
            'courseid' => $coursea->id,
            'quote' => null,
            'pagetitle' => 'Lesson 2',
            'timecreated' => 1700000200,
            'timemodified' => 1700000200,
        ]);

        $note3 = $this->create_note([
            'userid' => $user->id,
            'courseid' => $courseb->id,
            'quote' => '',
            'pagetitle' => 'Quiz Intro',
            'timecreated' => 1700000300,
            'timemodified' => 1700000300,
        ]);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'QuickNote usage', 'source' => notes::class, 'default' => 1]);

        $content = $this->get_custom_report_content($report->get('id'));

        $coursescope = get_string('report:scope_course', 'local_quicknote');
        $this->assertEquals([
            ['Alpha Course', $coursescope, 'Lesson 2', get_string('no'), userdate($note2->timecreated)],
            ['Alpha Course', $coursescope, 'Lesson 1', get_string('yes'), userdate($note1->timecreated)],
            ['Beta Course', $coursescope, 'Quiz Intro', get_string('no'), userdate($note3->timecreated)],
        ], array_map('array_values', $content));
    }

    /**
     * Test non-default columns including general (site) scope, category, user, and timestamps.
     */
    public function test_datasource_non_default_columns(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category(['name' => 'Engineering']);
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Algorithms',
            'category' => $category->id,
        ]);
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);

        $coursenote = $this->create_note([
            'userid' => $user->id,
            'courseid' => $course->id,
            'url' => 'https://example.com/mod/page/view.php?id=42',
            'pagetitle' => 'Sorting Algorithms',
            'timecreated' => 1700001000,
            'timemodified' => 1700002000,
        ]);

        $generalnote = $this->create_note([
            'userid' => $user->id,
            'courseid' => SITEID,
            'url' => 'https://example.com/my/',
            'pagetitle' => 'Dashboard',
            'timecreated' => 1700003000,
            'timemodified' => 1700004000,
        ]);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'QuickNote details', 'source' => notes::class, 'default' => 0]);

        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'note:id', 'sortenabled' => 1]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'note:scope']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'note:url']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'note:timemodified']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course_category:name']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(2, $content);

        $this->assertEquals([
            [
                $coursenote->id,
                get_string('report:scope_course', 'local_quicknote'),
                'https://example.com/mod/page/view.php?id=42',
                userdate($coursenote->timemodified),
                'Engineering',
                fullname($user),
            ],
            [
                $generalnote->id,
                get_string('general_notes', 'local_quicknote'),
                'https://example.com/my/',
                userdate($generalnote->timemodified),
                '',
                fullname($user),
            ],
        ], array_map('array_values', $content));
    }

    /**
     * Test aggregating note counts and distinct active users per course.
     */
    public function test_datasource_course_aggregation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $coursea = $this->getDataGenerator()->create_course(['fullname' => 'Course A']);
        $courseb = $this->getDataGenerator()->create_course(['fullname' => 'Course B']);
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        // Course A: 3 notes by 2 distinct users.
        $this->create_note(['userid' => $student1->id, 'courseid' => $coursea->id, 'pagetitle' => null]);
        $this->create_note(['userid' => $student1->id, 'courseid' => $coursea->id, 'pagetitle' => 'Page 1']);
        $this->create_note(['userid' => $student2->id, 'courseid' => $coursea->id, 'pagetitle' => 'Page 2']);

        // Course B: 1 note by 1 user.
        $this->create_note(['userid' => $student2->id, 'courseid' => $courseb->id, 'pagetitle' => 'Page 1']);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Notes per course', 'source' => notes::class, 'default' => 0]);

        $generator->create_column([
            'reportid' => $report->get('id'),
            'uniqueidentifier' => 'course:fullname',
            'sortenabled' => 1,
        ]);
        $generator->create_column([
            'reportid' => $report->get('id'),
            'uniqueidentifier' => 'note:id',
            'aggregation' => 'count',
        ]);
        $generator->create_column([
            'reportid' => $report->get('id'),
            'uniqueidentifier' => 'user:username',
            'aggregation' => 'countdistinct',
        ]);

        $content = $this->get_custom_report_content($report->get('id'));

        $this->assertEquals([
            ['Course A', 3, 2],
            ['Course B', 1, 1],
        ], array_map('array_values', $content));
    }

    /**
     * Data provider for {@see test_datasource_filters}.
     *
     * @return array[]
     */
    public static function datasource_filters_provider(): array {
        return [
            // Note entity filters.
            'Filter scope (course match)' => ['note:scope', [
                'note:scope_operator' => select::EQUAL_TO,
                'note:scope_value' => note::SCOPE_COURSE,
            ], true],
            'Filter scope (general no match)' => ['note:scope', [
                'note:scope_operator' => select::EQUAL_TO,
                'note:scope_value' => note::SCOPE_GENERAL,
            ], false],
            'Filter page title (match)' => ['note:pagetitle', [
                'note:pagetitle_operator' => text::CONTAINS,
                'note:pagetitle_value' => 'Calculus',
            ], true],
            'Filter page title (no match)' => ['note:pagetitle', [
                'note:pagetitle_operator' => text::IS_EQUAL_TO,
                'note:pagetitle_value' => 'History',
            ], false],
            'Filter URL (match)' => ['note:url', [
                'note:url_operator' => text::CONTAINS,
                'note:url_value' => 'mod/page/view.php',
            ], true],
            'Filter URL (no match)' => ['note:url', [
                'note:url_operator' => text::CONTAINS,
                'note:url_value' => 'mod/quiz/view.php',
            ], false],
            'Filter has quote (checked match)' => ['note:hasquote', [
                'note:hasquote_operator' => boolean_select::CHECKED,
            ], true],
            'Filter has quote (not checked no match)' => ['note:hasquote', [
                'note:hasquote_operator' => boolean_select::NOT_CHECKED,
            ], false],
            'Filter time created (match)' => ['note:timecreated', [
                'note:timecreated_operator' => date::DATE_RANGE,
                'note:timecreated_from' => 1700000000,
            ], true],
            'Filter time created (no match)' => ['note:timecreated', [
                'note:timecreated_operator' => date::DATE_RANGE,
                'note:timecreated_to' => 1600000000,
            ], false],
            'Filter time modified (match)' => ['note:timemodified', [
                'note:timemodified_operator' => date::DATE_RANGE,
                'note:timemodified_from' => 1700000000,
            ], true],
            'Filter time modified (no match)' => ['note:timemodified', [
                'note:timemodified_operator' => date::DATE_RANGE,
                'note:timemodified_to' => 1600000000,
            ], false],

            // Course & category entity filters.
            'Filter course fullname (match)' => ['course:fullname', [
                'course:fullname_operator' => text::IS_EQUAL_TO,
                'course:fullname_value' => 'Mathematics 101',
            ], true],
            'Filter course fullname (no match)' => ['course:fullname', [
                'course:fullname_operator' => text::IS_EQUAL_TO,
                'course:fullname_value' => 'Physics 101',
            ], false],
            'Filter course category (match)' => ['course_category:text', [
                'course_category:text_operator' => text::IS_EQUAL_TO,
                'course_category:text_value' => 'STEM',
            ], true],
            'Filter course category (no match)' => ['course_category:text', [
                'course_category:text_operator' => text::IS_EQUAL_TO,
                'course_category:text_value' => 'Arts',
            ], false],

            // User entity filter.
            'Filter user username (match)' => ['user:username', [
                'user:username_operator' => text::IS_EQUAL_TO,
                'user:username_value' => 'notetaker',
            ], true],
            'Filter user username (no match)' => ['user:username', [
                'user:username_operator' => text::IS_EQUAL_TO,
                'user:username_value' => 'someoneelse',
            ], false],
        ];
    }

    /**
     * Test datasource filters.
     *
     * @param string $filtername
     * @param array $filtervalues
     * @param bool $expectmatch
     */
    #[DataProvider('datasource_filters_provider')]
    public function test_datasource_filters(
        string $filtername,
        array $filtervalues,
        bool $expectmatch
    ): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category(['name' => 'STEM']);
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Mathematics 101',
            'category' => $category->id,
        ]);
        $user = $this->getDataGenerator()->create_user(['username' => 'notetaker']);

        $this->create_note([
            'userid' => $user->id,
            'courseid' => $course->id,
            'quote' => 'Important theorem',
            'url' => 'https://example.com/mod/page/view.php?id=77',
            'pagetitle' => 'Calculus Chapter 1',
            'timecreated' => 1710000000,
            'timemodified' => 1710000500,
        ]);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        $report = $generator->create_report(['name' => 'Filtered notes', 'source' => notes::class, 'default' => 0]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'note:pagetitle']);
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => $filtername]);

        $content = $this->get_custom_report_content($report->get('id'), 0, $filtervalues);

        if ($expectmatch) {
            $this->assertCount(1, $content);
            $this->assertEquals('Calculus Chapter 1', reset($content[0]));
        } else {
            $this->assertEmpty($content);
        }
    }

    /**
     * Stress test datasource columns, aggregations, and conditions.
     */
    public function test_stress_datasource(): void {
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->create_note([
            'userid' => $user->id,
            'courseid' => $course->id,
            'quote' => 'Quoted text',
            'pagetitle' => 'Stress test page',
        ]);

        $this->datasource_stress_test_columns(notes::class);
        $this->datasource_stress_test_columns_aggregation(notes::class);
        $this->datasource_stress_test_conditions(notes::class, 'note:pagetitle');
    }
}
