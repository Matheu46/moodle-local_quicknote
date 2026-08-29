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

namespace local_quicknote;

use advanced_testcase;
use local_quicknote\output\exporter;

/**
 * Exporter tests for local_quicknote.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quicknote\output\exporter
 */
final class exporter_test extends advanced_testcase {
    /**
     * Test exporting an empty recordset to Markdown.
     */
    public function test_export_to_md_empty(): void {
        $this->resetAfterTest();
        global $DB;

        // Create an empty recordset.
        $rs = $DB->get_recordset_sql("SELECT * FROM {local_quicknote_notes} WHERE id = -1");
        $md = exporter::export_to_md($rs);

        $this->assertStringContainsString(get_string('note:empty', 'local_quicknote'), $md);
    }

    /**
     * Test exporting notes to Markdown.
     */
    public function test_export_to_md_with_notes(): void {
        $this->resetAfterTest();
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test Course MD']);
        $user = $generator->create_user();

        // Insert a dummy note.
        $note = new \stdClass();
        $note->userid = $user->id;
        $note->courseid = $course->id;
        $note->content = 'This is a test note content';
        $note->quote = 'This is a quote';
        $note->quoteurl = 'http://example.com/quote';
        $note->url = 'http://example.com/note';
        $note->timecreated = time();
        $note->timemodified = time();
        $note->id = $DB->insert_record('local_quicknote_notes', $note);

        // Build the same query used in view.php.
        $sql = "SELECT qn.id, qn.content, qn.url, qn.quote, qn.quoteurl, qn.timemodified,
                       c.fullname as coursefullname, c.id as courseid
                FROM {local_quicknote_notes} qn
                JOIN {course} c ON c.id = qn.courseid
                WHERE qn.userid = :userid";
        $rs = $DB->get_recordset_sql($sql, ['userid' => $user->id]);

        $md = exporter::export_to_md($rs);

        // Assert that the generated MD contains the expected text.
        $this->assertStringContainsString('Test Course MD', $md);
        $this->assertStringContainsString('This is a test note content', $md);
        $this->assertStringContainsString('This is a quote', $md);
        $this->assertStringContainsString('http://example.com/quote', $md);
    }

    /**
     * Test exporting an empty recordset to PDF.
     */
    public function test_export_to_pdf_empty(): void {
        $this->resetAfterTest();
        global $DB;

        // Create an empty recordset.
        $rs = $DB->get_recordset_sql("SELECT * FROM {local_quicknote_notes} WHERE id = -1");
        $pdf = exporter::export_to_pdf($rs);

        // PDF document starts with %PDF marker.
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * Test exporting notes to PDF.
     */
    public function test_export_to_pdf_with_notes(): void {
        $this->resetAfterTest();
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Test Course PDF']);
        $user = $generator->create_user();

        // Insert a dummy note.
        $note = new \stdClass();
        $note->userid = $user->id;
        $note->courseid = $course->id;
        $note->content = 'This is a PDF test note content';
        $note->quote = 'PDF quote';
        $note->quoteurl = 'http://example.com/pdfquote';
        $note->url = 'http://example.com/pdfnote';
        $note->timecreated = time();
        $note->timemodified = time();
        $note->id = $DB->insert_record('local_quicknote_notes', $note);

        $sql = "SELECT qn.id, qn.content, qn.url, qn.quote, qn.quoteurl, qn.timemodified,
                       c.fullname as coursefullname, c.id as courseid
                FROM {local_quicknote_notes} qn
                JOIN {course} c ON c.id = qn.courseid
                WHERE qn.userid = :userid";
        $rs = $DB->get_recordset_sql($sql, ['userid' => $user->id]);

        $pdf = exporter::export_to_pdf($rs);

        // PDF is binary, but we can verify it was generated properly.
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(100, strlen($pdf));
    }
}
