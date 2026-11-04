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

    public function test_user_enrolment_deleted_multiple_enrolments(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();

        // Enrol via manual.
        $generator->enrol_user($user->id, $course->id, 'student', 'manual');

        // Enrol via self.
        $selfplugin = enrol_get_plugin('self');
        $selfinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$selfinstance) {
            $selfinstanceid = $selfplugin->add_instance($course);
            $selfinstance = $DB->get_record('enrol', ['id' => $selfinstanceid]);
        }
        $selfplugin->enrol_user($selfinstance, $user->id);

        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'Note by doubly enrolled user',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_quicknote_notes', $record);

        // Add screenshot.
        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        \local_quicknote\local\screenshot_manager::create($record, 'teste.gif', 'image/gif', $gif);

        // Unenrol from self enrolment only. User is still enrolled via manual.
        $selfplugin->unenrol_user($selfinstance, $user->id);

        // Verify notes and screenshots still exist because user is still enrolled.
        $this->assertEquals(1, $DB->count_records('local_quicknote_notes', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]));
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertGreaterThan(0, $filescount);

        // Now unenrol from the remaining manual method (last enrolment).
        $manualplugin = enrol_get_plugin('manual');
        $manualinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $manualplugin->unenrol_user($manualinstance, $user->id);

        // Verify notes and screenshots are now deleted.
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

    public function test_user_enrolment_deleted_fallback_when_lastenrol_missing(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'Note for fallback test',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_quicknote_notes', $record);

        // Trigger event without lastenrol while user is still enrolled.
        $event = \core\event\user_enrolment_deleted::create([
            'courseid' => $course->id,
            'context' => \context_course::instance($course->id),
            'relateduserid' => $user->id,
            'objectid' => 1,
            'other' => [
                'enrol' => 'manual',
                'userenrolment' => ['userid' => $user->id],
            ],
        ]);
        \local_quicknote\observers::user_enrolment_deleted($event);

        // Notes should remain because user is still enrolled.
        $this->assertEquals(1, $DB->count_records('local_quicknote_notes', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]));

        // Now actually unenrol user.
        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $plugin = enrol_get_plugin('manual');
        $plugin->unenrol_user($enrol, $user->id);

        // Trigger synthetic event without lastenrol when user is not enrolled.
        \local_quicknote\observers::user_enrolment_deleted($event);

        // Notes should now be deleted.
        $this->assertEquals(0, $DB->count_records('local_quicknote_notes', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]));
    }
}
