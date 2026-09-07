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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_quicknote\privacy\provider;

/**
 * Privacy provider tests for local_quicknote.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quicknote\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Test get_metadata.
     */
    public function test_get_metadata(): void {
        $collection = new collection('local_quicknote');
        $newcollection = provider::get_metadata($collection);
        $items = $newcollection->get_collection();
        $this->assertCount(1, $items);
        $this->assertEquals('local_quicknote_notes', $items[0]->get_name());
    }

    /**
     * Test get_contexts_for_userid.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        // Create a note.
        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'Test note',
            'url' => 'https://example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        global $DB;
        $DB->insert_record('local_quicknote_notes', $record);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contexts = $contextlist->get_contexts();
        $this->assertCount(1, $contexts);
        $this->assertEquals(\context_course::instance($course->id)->id, $contexts[0]->id);
    }

    /**
     * Test export_user_data.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $coursecontext = \context_course::instance($course->id);

        global $DB;
        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'Test export note',
            'url' => 'https://example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_quicknote_notes', $record);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $approvedcontextlist = new approved_contextlist($user, 'local_quicknote', $contextlist->get_contextids());

        $writer = writer::with_context($coursecontext);
        $this->assertFalse($writer->has_any_data());

        provider::export_user_data($approvedcontextlist);

        $data = $writer->get_data([get_string('pluginname', 'local_quicknote'), $record->id]);
        $this->assertNotNull($data);
        $this->assertEquals('Test export note', $data->content);
    }

    /**
     * Test delete_data_for_all_users_in_context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $coursecontext = \context_course::instance($course->id);

        global $DB;
        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'content' => 'Note to delete context',
            'url' => 'https://example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_quicknote_notes', $record);

        $this->assertEquals(1, $DB->count_records('local_quicknote_notes'));

        // Add screenshot to the note.
        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        \local_quicknote\local\screenshot_manager::create($record, 'teste.gif', 'image/gif', $gif);
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertGreaterThan(0, $filescount);

        provider::delete_data_for_all_users_in_context($coursecontext);

        $this->assertEquals(0, $DB->count_records('local_quicknote_notes'));
        $filescountafter = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record->id,
        ]);
        $this->assertEquals(0, $filescountafter);
    }

    /**
     * Test delete_data_for_user.
     */
    public function test_delete_data_for_user(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        global $DB;
        $record1 = (object) [
            'userid' => $user1->id,
            'courseid' => $course->id,
            'content' => 'User 1 note',
            'url' => 'https://example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record1->id = $DB->insert_record('local_quicknote_notes', $record1);

        $record2 = (object) [
            'userid' => $user2->id,
            'courseid' => $course->id,
            'content' => 'User 2 note',
            'url' => 'https://example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record2->id = $DB->insert_record('local_quicknote_notes', $record2);

        $this->assertEquals(2, $DB->count_records('local_quicknote_notes'));

        // Add screenshot to user 1's note.
        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        \local_quicknote\local\screenshot_manager::create($record1, 'teste.gif', 'image/gif', $gif);
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record1->id,
        ]);
        $this->assertGreaterThan(0, $filescount);

        $contextlist = provider::get_contexts_for_userid($user1->id);
        $approvedcontextlist = new approved_contextlist($user1, 'local_quicknote', $contextlist->get_contextids());
        provider::delete_data_for_user($approvedcontextlist);

        // User 1 note should be gone, user 2 note should remain.
        $this->assertEquals(0, $DB->count_records('local_quicknote_notes', ['userid' => $user1->id]));
        $this->assertEquals(1, $DB->count_records('local_quicknote_notes', ['userid' => $user2->id]));

        // Verify user 1 screenshot was deleted.
        $filescountafter = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record1->id,
        ]);
        $this->assertEquals(0, $filescountafter);
    }

    /**
     * Test get_users_in_context.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');

        global $DB;
        $record = (object) [
            'userid' => $user1->id,
            'courseid' => $course->id,
            'content' => 'Test note',
            'url' => 'https://example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('local_quicknote_notes', $record);

        $coursecontext = \context_course::instance($course->id);
        $userlist = new userlist($coursecontext, 'local_quicknote');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        $this->assertCount(1, $userids);
        $this->assertEquals($user1->id, $userids[0]);
    }

    /**
     * Test delete_data_for_users.
     */
    public function test_delete_data_for_users(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        global $DB;
        $record1 = (object) [
            'userid' => $user1->id,
            'courseid' => $course->id,
            'content' => 'User 1 note',
            'url' => 'https://example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record1->id = $DB->insert_record('local_quicknote_notes', $record1);

        $record2 = (object) [
            'userid' => $user2->id,
            'courseid' => $course->id,
            'content' => 'User 2 note',
            'url' => 'https://example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record2->id = $DB->insert_record('local_quicknote_notes', $record2);

        $coursecontext = \context_course::instance($course->id);

        // Add screenshot to user 1's note.
        $gif = base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        \local_quicknote\local\screenshot_manager::create($record1, 'teste.gif', 'image/gif', $gif);
        $filescount = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record1->id,
        ]);
        $this->assertGreaterThan(0, $filescount);

        // Delete only user 1.
        $approveduserlist = new approved_userlist($coursecontext, 'local_quicknote', [$user1->id]);
        provider::delete_data_for_users($approveduserlist);

        $this->assertEquals(0, $DB->count_records('local_quicknote_notes', ['userid' => $user1->id]));
        $this->assertEquals(1, $DB->count_records('local_quicknote_notes', ['userid' => $user2->id]));
        $filescountafter = $DB->count_records('files', [
            'component' => 'local_quicknote',
            'filearea' => 'screenshot',
            'itemid' => $record1->id,
        ]);
        $this->assertEquals(0, $filescountafter);
    }
}
