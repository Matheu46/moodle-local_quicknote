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

namespace local_quicknote\reportbuilder\local\systemreports;

use context_course;
use core_reportbuilder\exception\report_access_exception;
use core_reportbuilder\system_report_factory;
use DOMDocument;
use DOMXPath;
use moodle_url;
use navigation_node;
use stdClass;

/**
 * Unit tests for the course_usage system report.
 *
 * @package     local_quicknote
 * @category    test
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_quicknote\reportbuilder\local\systemreports\course_usage
 */
final class course_usage_test extends \advanced_testcase {
    /**
     * Helper to insert a QuickNote record directly for testing.
     *
     * @param array $overrides
     * @return stdClass
     */
    private function create_note(array $overrides = []): stdClass {
        global $DB;

        $now = time();
        $record = (object) array_merge([
            'userid' => 2,
            'courseid' => SITEID,
            'content' => 'PRIVATE_NOTE_CONTENT',
            'quote' => 'PRIVATE_QUOTE_TEXT',
            'quoteurl' => null,
            'url' => 'https://example.com/mod/page/view.php?id=1',
            'pagehash' => hash('sha256', 'https://example.com/mod/page/view.php?id=1'),
            'pagetitle' => 'Default page',
            'timecreated' => $now,
            'timemodified' => $now,
        ], $overrides);

        $record->id = $DB->insert_record('local_quicknote_notes', $record);
        return $record;
    }

    /**
     * Extract rendered table body rows (text + href of first cell link) from system report HTML.
     *
     * @param string $html
     * @return array[]
     */
    private function extract_table_rows(string $html): array {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);

        $rows = [];
        foreach ($xpath->query('//table//tbody/tr[not(contains(@class, "emptyrow"))]') as $tr) {
            $cells = [];
            foreach ($xpath->query('./td|./th', $tr) as $cell) {
                $cells[] = trim($cell->textContent);
            }
            $linknodes = $xpath->query('.//a', $tr);
            $href = $linknodes->length > 0 ? $linknodes->item(0)->getAttribute('href') : '';
            $rows[] = ['cells' => $cells, 'href' => $href];
        }

        return $rows;
    }

    /**
     * Test teacher sees aggregated, anonymous usage per page scoped only to their course.
     */
    public function test_teacher_sees_aggregated_course_usage(): void {
        global $PAGE;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $coursea = $generator->create_course(['fullname' => 'Course A']);
        $courseb = $generator->create_course(['fullname' => 'Course B']);
        $contexta = context_course::instance($coursea->id);

        $teacher = $generator->create_and_enrol($coursea, 'editingteacher');
        $student1 = $generator->create_and_enrol($coursea, 'student', ['firstname' => 'HiddenStudentOne']);
        $student2 = $generator->create_and_enrol($coursea, 'student', ['firstname' => 'HiddenStudentTwo']);

        $page1 = $generator->create_module('page', ['course' => $coursea->id, 'name' => 'Chapter 1']);
        $page2 = $generator->create_module('page', ['course' => $coursea->id, 'name' => 'Chapter 2']);

        // Page 1 in Course A: 3 notes from 2 distinct users with different student-side page titles.
        $this->create_note([
            'userid' => $student1->id,
            'courseid' => $coursea->id,
            'pagetitle' => 'Chapter 1 - Intro',
            'url' => 'https://example.com/mod/page/view.php?id=' . $page1->cmid . '#:~:text=LEAKED',
            'timecreated' => 1710000100,
        ]);
        $this->create_note([
            'userid' => $student1->id,
            'courseid' => $coursea->id,
            'pagetitle' => 'Chapter 1 - Section A',
            'url' => 'https://example.com/mod/page/view.php?id=' . $page1->cmid . '#:~:text=LEAKED',
            'timecreated' => 1710000200,
        ]);
        $this->create_note([
            'userid' => $student2->id,
            'courseid' => $coursea->id,
            'pagetitle' => 'Chapter 1 - Summary',
            'url' => 'https://example.com/mod/page/view.php?id=' . $page1->cmid . '#:~:text=LEAKED',
            'timecreated' => 1710000300,
        ]);

        // Page 2 in Course A: 1 note from 1 user.
        $this->create_note([
            'userid' => $student2->id,
            'courseid' => $coursea->id,
            'pagetitle' => 'Chapter 2',
            'url' => 'https://example.com/mod/page/view.php?id=' . $page2->cmid,
            'timecreated' => 1710000500,
        ]);

        // Unrelated notes in Course B and Site Dashboard must not appear.
        $this->create_note([
            'userid' => $student1->id,
            'courseid' => $courseb->id,
            'pagetitle' => 'Course B Secret Page',
            'url' => 'https://example.com/mod/page/view.php?id=99',
        ]);
        $this->create_note([
            'userid' => $student1->id,
            'courseid' => SITEID,
            'pagetitle' => 'Personal Dashboard',
            'url' => 'https://example.com/my/',
        ]);

        $this->setUser($teacher);
        $PAGE->set_url('/local/quicknote/report.php', ['id' => $coursea->id]);

        $report = system_report_factory::create(
            course_usage::class,
            $contexta,
            'local_quicknote',
            '',
            0,
            ['courseid' => $coursea->id]
        );

        $output = $report->output();

        // Privacy assertions: no student names, private content, private quote, or text-fragment leaked.
        $this->assertStringNotContainsString('HiddenStudentOne', $output);
        $this->assertStringNotContainsString('HiddenStudentTwo', $output);
        $this->assertStringNotContainsString('PRIVATE_NOTE_CONTENT', $output);
        $this->assertStringNotContainsString('PRIVATE_QUOTE_TEXT', $output);
        $this->assertStringNotContainsString('LEAKED', $output);

        // Scope assertions: only Course A pages appear.
        $this->assertStringContainsString('Chapter 1', $output);
        $this->assertStringContainsString('Chapter 2', $output);
        $this->assertStringNotContainsString('Course B Secret Page', $output);
        $this->assertStringNotContainsString('Personal Dashboard', $output);

        // Verify exact aggregated rows & default sort order (Chapter 1 with 3 notes first, Chapter 2 with 1 note second).
        $rows = $this->extract_table_rows($output);
        $this->assertCount(2, $rows);

        $this->assertEquals('https://example.com/mod/page/view.php?id=' . $page1->cmid, $rows[0]['href']);
        $this->assertEquals(['Chapter 1', '3', '2', userdate(1710000300)], $rows[0]['cells']);

        $this->assertEquals('https://example.com/mod/page/view.php?id=' . $page2->cmid, $rows[1]['href']);
        $this->assertEquals(['Chapter 2', '1', '1', userdate(1710000500)], $rows[1]['cells']);
    }

    /**
     * Test students cannot access the course usage report.
     */
    public function test_student_cannot_view_report(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $student = $generator->create_and_enrol($course, 'student');

        $this->setUser($student);
        $this->expectException(report_access_exception::class);

        system_report_factory::create(
            course_usage::class,
            $context,
            'local_quicknote',
            '',
            0,
            ['courseid' => $course->id]
        );
    }

    /**
     * Test teacher in Course A cannot view Course B usage by tampering with the courseid parameter.
     */
    public function test_cross_course_access_denied(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $coursea = $generator->create_course();
        $courseb = $generator->create_course();
        $contexta = context_course::instance($coursea->id);

        $teachera = $generator->create_and_enrol($coursea, 'editingteacher');
        $this->setUser($teachera);

        $this->expectException(report_access_exception::class);

        system_report_factory::create(
            course_usage::class,
            $contexta,
            'local_quicknote',
            '',
            0,
            ['courseid' => $courseb->id]
        );
    }

    /**
     * Test site course (SITEID) is rejected by the course usage system report.
     */
    public function test_site_course_denied(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $sitecontext = context_course::instance(SITEID);
        $this->expectException(report_access_exception::class);

        system_report_factory::create(
            course_usage::class,
            $sitecontext,
            'local_quicknote',
            '',
            0,
            ['courseid' => SITEID]
        );
    }

    /**
     * Test separate groups mode isolates metrics for non-editing teachers without accessallgroups.
     */
    public function test_separate_groups_restriction(): void {
        global $PAGE;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $context = context_course::instance($course->id);

        $group1 = $generator->create_group(['courseid' => $course->id]);
        $group2 = $generator->create_group(['courseid' => $course->id]);

        $noneditingteacher = $generator->create_and_enrol($course, 'teacher');
        $editingteacher = $generator->create_and_enrol($course, 'editingteacher');
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');

        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $noneditingteacher->id]);
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student1->id]);
        $generator->create_group_member(['groupid' => $group2->id, 'userid' => $student2->id]);

        $page1 = $generator->create_module('page', ['course' => $course->id, 'name' => 'Group 1 Page']);
        $page2 = $generator->create_module('page', ['course' => $course->id, 'name' => 'Group 2 Page']);

        $this->create_note([
            'userid' => $student1->id,
            'courseid' => $course->id,
            'pagetitle' => 'Group 1 Page',
            'url' => 'https://example.com/mod/page/view.php?id=' . $page1->cmid,
        ]);
        $this->create_note([
            'userid' => $student2->id,
            'courseid' => $course->id,
            'pagetitle' => 'Group 2 Page',
            'url' => 'https://example.com/mod/page/view.php?id=' . $page2->cmid,
        ]);

        // Non-editing teacher in Group 1 sees only Group 1 activity.
        $this->setUser($noneditingteacher);
        $PAGE->set_url('/local/quicknote/report.php', ['id' => $course->id]);
        $report = system_report_factory::create(
            course_usage::class,
            $context,
            'local_quicknote',
            '',
            0,
            ['courseid' => $course->id]
        );
        $output = $report->output();
        $this->assertStringContainsString('Group 1 Page', $output);
        $this->assertStringNotContainsString('Group 2 Page', $output);

        // Editing teacher (with accessallgroups) sees both groups.
        $this->setUser($editingteacher);
        $reportall = system_report_factory::create(
            course_usage::class,
            $context,
            'local_quicknote',
            '',
            0,
            ['courseid' => $course->id]
        );
        $outputall = $reportall->output();
        $this->assertStringContainsString('Group 1 Page', $outputall);
        $this->assertStringContainsString('Group 2 Page', $outputall);
    }

    /**
     * Test course navigation callback registers report link only for authorised users in courses.
     */
    public function test_extend_navigation_course(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/quicknote/lib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');

        // Teacher with coursereports container gets link inside coursereports.
        $this->setUser($teacher);
        $root = navigation_node::create('Course admin', null, navigation_node::TYPE_COURSE);
        $reports = $root->add('Reports', null, navigation_node::TYPE_CONTAINER, null, 'coursereports');
        local_quicknote_extend_navigation_course($root, $course, $context);
        $node = $reports->find('local_quicknote_report', navigation_node::TYPE_SETTING);
        $this->assertNotFalse($node);
        $this->assertTrue($node->action->compare(
            new moodle_url('/local/quicknote/report.php', ['id' => $course->id]),
            URL_MATCH_EXACT
        ));

        // Student does not get link.
        $this->setUser($student);
        $studentroot = navigation_node::create('Course admin', null, navigation_node::TYPE_COURSE);
        $studentreports = $studentroot->add('Reports', null, navigation_node::TYPE_CONTAINER, null, 'coursereports');
        local_quicknote_extend_navigation_course($studentroot, $course, $context);
        $this->assertFalse($studentreports->find('local_quicknote_report', navigation_node::TYPE_SETTING));

        // Site course does not get link even for admin.
        $this->setAdminUser();
        $siteroot = navigation_node::create('Site', null, navigation_node::TYPE_COURSE);
        local_quicknote_extend_navigation_course($siteroot, get_site(), context_course::instance(SITEID));
        $this->assertFalse($siteroot->find('local_quicknote_report', navigation_node::TYPE_SETTING));
    }

    /**
     * Test student-controlled page titles and external URLs cannot spoof content or links in report.
     */
    public function test_spoofed_pagetitle_and_external_url_neutralized(): void {
        global $PAGE;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');

        // Legitimate page with spoofed title.
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'Legit Page Name']);
        $this->create_note([
            'userid' => $student->id,
            'courseid' => $course->id,
            'pagetitle' => 'ATTACKER SPOOFED TITLE',
            'url' => 'https://example.com/mod/page/view.php?id=' . $page->cmid,
        ]);

        // Arbitrary same-site URL with social engineering title (Finding 5 POC).
        $this->create_note([
            'userid' => $student->id,
            'courseid' => $course->id,
            'pagetitle' => 'ACTION REQUIRED: re-confirm your teacher profile here',
            'url' => 'https://example.com/user/edit.php',
        ]);

        $this->setUser($teacher);
        $PAGE->set_url('/local/quicknote/report.php', ['id' => $course->id]);

        $report = system_report_factory::create(
            course_usage::class,
            $context,
            'local_quicknote',
            '',
            0,
            ['courseid' => $course->id]
        );
        $output = $report->output();

        // Attacker-supplied titles must never appear.
        $this->assertStringNotContainsString('ATTACKER SPOOFED TITLE', $output);
        $this->assertStringNotContainsString('ACTION REQUIRED', $output);

        // Official activity name must be shown for the course activity.
        $this->assertStringContainsString('Legit Page Name', $output);

        // The non-course URL must be rendered as plain text without link.
        $rows = $this->extract_table_rows($output);
        $usereditrow = null;
        $legitrow = null;
        foreach ($rows as $row) {
            if (in_array('/user/edit.php', $row['cells'], true)) {
                $usereditrow = $row;
            }
            if (in_array('Legit Page Name', $row['cells'], true)) {
                $legitrow = $row;
            }
        }

        $this->assertNotNull($legitrow);
        $this->assertNotEmpty($legitrow['href']);
        $this->assertStringContainsString('id=' . $page->cmid, $legitrow['href']);

        $this->assertNotNull($usereditrow);
        $this->assertEmpty($usereditrow['href']);
    }
}
