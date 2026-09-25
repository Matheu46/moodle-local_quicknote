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
use local_quicknote\external\save_note;
use local_quicknote\external\get_notes;
use local_quicknote\external\delete_note;

/**
 * External library tests for local_quicknote.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quicknote\external\save_note
 * @covers     \local_quicknote\external\get_notes
 * @covers     \local_quicknote\external\delete_note
 */
final class externallib_test extends advanced_testcase {
    public function test_save_note(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);

        // Create new note.
        $result = save_note::execute(
            0,
            $course->id,
            'My first note',
            'https://example.com/page1',
            'Quote text',
            'https://example.com/quote1'
        );
        $result = \core_external\external_api::clean_returnvalue(save_note::execute_returns(), $result);

        $this->assertNotEmpty($result['id']);
        $this->assertEquals($user->id, $result['userid']);
        $this->assertEquals($course->id, $result['courseid']);
        $this->assertEquals('My first note', $result['content']);
        $this->assertEquals('Quote text', $result['quote']);

        $noteid = $result['id'];

        // Update existing note.
        $result2 = save_note::execute(
            $noteid,
            $course->id,
            'Updated note',
            'https://example.com/page2'
        );
        $result2 = \core_external\external_api::clean_returnvalue(save_note::execute_returns(), $result2);

        $this->assertEquals($noteid, $result2['id']);
        $this->assertEquals('Updated note', $result2['content']);
        $this->assertEquals('Quote text', $result2['quote']); // Preserved from previous.
    }

    public function test_save_note_wrong_course(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $user = $generator->create_user();

        $generator->enrol_user($user->id, $course1->id, 'student');
        $generator->enrol_user($user->id, $course2->id, 'student');

        $this->setUser($user);

        $result = save_note::execute(0, $course1->id, 'Note in course 1', 'https://example.com');
        $noteid = $result['id'];

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('The note does not belong to the provided course.');

        save_note::execute($noteid, $course2->id, 'Update in wrong course', 'https://example.com');
    }

    public function test_get_notes(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();

        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        // User 1 creates a note.
        $this->setUser($user1);
        save_note::execute(0, $course->id, 'User 1 note', 'https://example.com');

        // User 2 creates two notes.
        $this->setUser($user2);
        save_note::execute(0, $course->id, 'User 2 note 1', 'https://example.com/1');
        save_note::execute(0, $course->id, 'User 2 note 2', 'https://example.com/2');

        // Check User 2 gets their 2 notes.
        $result = get_notes::execute($course->id);
        $result = \core_external\external_api::clean_returnvalue(get_notes::execute_returns(), $result);

        $this->assertCount(2, $result);
        $this->assertEquals('User 2 note 2', $result[0]['content']);
        $this->assertEquals('User 2 note 1', $result[1]['content']);

        // Check User 1 gets their 1 note.
        $this->setUser($user1);
        $result = get_notes::execute($course->id);
        $result = \core_external\external_api::clean_returnvalue(get_notes::execute_returns(), $result);
        $this->assertCount(1, $result);
        $this->assertEquals('User 1 note', $result[0]['content']);
    }

    public function test_delete_note(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();

        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        $this->setUser($user1);
        $result = save_note::execute(0, $course->id, 'Note to delete', 'https://example.com');
        $noteid = $result['id'];

        // Add a screenshot to the note to test if it gets deleted.
        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $note = $DB->get_record('local_quicknote_notes', ['id' => $noteid]);
        \local_quicknote\local\screenshot_manager::create($note, 'teste.gif', 'image/gif', $gif);

        // Verify the file was stored in the database.
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $noteid,
        ]);
        $this->assertGreaterThan(0, $filescount);

        // User 2 tries to delete User 1's note (should fail).
        $this->setUser($user2);
        try {
            delete_note::execute($noteid);
            $this->fail('User 2 should not be able to delete User 1 note');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString(
                'Note not found or you do not have permission to delete it.',
                $e->getMessage()
            );
        }

        // User 1 deletes their own note (should succeed).
        $this->setUser($user1);
        $deleteresult = delete_note::execute($noteid);
        $deleteresult = \core_external\external_api::clean_returnvalue(delete_note::execute_returns(), $deleteresult);
        $this->assertTrue($deleteresult['deleted']);

        $notesresult = get_notes::execute($course->id);
        $notesresult = \core_external\external_api::clean_returnvalue(get_notes::execute_returns(), $notesresult);
        $this->assertCount(0, $notesresult);

        // Verify the file was deleted from the database.
        $filescountafter = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $noteid,
        ]);
        $this->assertEquals(0, $filescountafter);
    }

    public function test_disabled_course(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();

        $generator->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // Disable QuickNote for this course.
        $record = new \stdClass();
        $record->courseid = $course->id;
        $record->enabled = 0;
        $record->module_settings = '';
        $DB->insert_record('local_quicknote_course', $record);

        // Attempting to use the API should throw an exception.
        $this->expectException(\moodle_exception::class);

        save_note::execute(0, $course->id, 'This should fail', 'https://example.com');
    }

    public function test_upload_screenshot(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();

        $generator->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $result = save_note::execute(0, $course->id, 'Note to attach screenshot', 'https://example.com');
        $noteid = $result['id'];

        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        // Ensure screenshots are enabled globally.
        set_config('enable_screenshots', 1, 'local_quicknote');

        $uploadresult = \local_quicknote\external\upload_screenshot::execute($noteid, 'test.gif', 'image/gif', $gif);
        $uploadresult = \core_external\external_api::clean_returnvalue(
            \local_quicknote\external\upload_screenshot::execute_returns(),
            $uploadresult
        );

        $this->assertNotEmpty($uploadresult['id']);
        $this->assertNotEmpty($uploadresult['url']);

        // Verify the file was stored in the database.
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $noteid,
        ]);
        $this->assertGreaterThan(0, $filescount);

        // Revoke capability for student.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('local/quicknote:uploadscreenshot', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(\required_capability_exception::class);
        \local_quicknote\external\upload_screenshot::execute($noteid, 'test2.gif', 'image/gif', $gif);
    }

    public function test_dashboard_notes(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $course = $generator->create_course();

        $this->setUser($user1);

        // Enable dashboard notes.
        set_config('enable_dashboard', 1, 'local_quicknote');

        // 1. Create a note in the dashboard.
        $result = save_note::execute(0, SITEID, 'Global note 1', '');
        $result = \core_external\external_api::clean_returnvalue(save_note::execute_returns(), $result);

        $noteid = $result['id'];
        $this->assertNotEmpty($noteid);

        // 2. Fetch notes from dashboard.
        $notes = get_notes::execute(SITEID);
        $notes = \core_external\external_api::clean_returnvalue(get_notes::execute_returns(), $notes);
        $this->assertCount(1, $notes);
        $this->assertEquals('Global note 1', $notes[0]['content']);

        // 3. User2 cannot see User1's global notes.
        $this->setUser($user2);
        $notes2 = get_notes::execute(SITEID);
        $notes2 = \core_external\external_api::clean_returnvalue(get_notes::execute_returns(), $notes2);
        $this->assertCount(0, $notes2);

        // 4. Global notes do not leak into regular courses.
        $this->setUser($user1);
        $generator->enrol_user($user1->id, $course->id, 'student');
        $coursenotes = get_notes::execute($course->id);
        $coursenotes = \core_external\external_api::clean_returnvalue(get_notes::execute_returns(), $coursenotes);
        $this->assertCount(0, $coursenotes); // Should be empty in the course!

        // 5. Delete global note.
        $deleteresult = delete_note::execute($noteid);
        $deleteresult = \core_external\external_api::clean_returnvalue(delete_note::execute_returns(), $deleteresult);
        $this->assertTrue($deleteresult['deleted']);
    }
}
