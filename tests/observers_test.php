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

/**
 * Observers tests for local_quicknote.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quicknote\observers
 */
final class observers_test extends \advanced_testcase {
    public function test_course_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'Note in course',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_quicknote_notes', $record);

        // Add screenshot.
        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        \local_quicknote\local\screenshot_manager::create($record, 'teste.gif', 'image/gif', $gif);
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertGreaterThan(0, $filescount);

        // Delete course.
        delete_course($course->id, false);

        // Verify notes and screenshots are deleted.
        $this->assertEquals(0, $DB->count_records('local_quicknote_notes', ['courseid' => $course->id]));
        $filescountafter = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertEquals(0, $filescountafter);
    }

    public function test_user_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'Note by user',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_quicknote_notes', $record);

        // Add screenshot.
        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        \local_quicknote\local\screenshot_manager::create($record, 'teste.gif', 'image/gif', $gif);
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertGreaterThan(0, $filescount);

        // Delete user.
        delete_user($user);

        // Verify notes and screenshots are deleted.
        $this->assertEquals(0, $DB->count_records('local_quicknote_notes', ['userid' => $user->id]));
        $filescountafter = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertEquals(0, $filescountafter);
    }

    public function test_user_enrolment_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'Note by enrolled user',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_quicknote_notes', $record);

        // Add screenshot.
        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        \local_quicknote\local\screenshot_manager::create($record, 'teste.gif', 'image/gif', $gif);
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertGreaterThan(0, $filescount);

        // Unenrol user.
        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $plugin = enrol_get_plugin('manual');
        $plugin->unenrol_user($enrol, $user->id);

        // Verify notes and screenshots are deleted.
        $this->assertEquals(0, $DB->count_records('local_quicknote_notes', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]));
        $filescountafter = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertEquals(0, $filescountafter);
    }
}
