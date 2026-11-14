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
 * Hooks tests for local_quicknote.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quicknote\hooks
 */
final class hooks_test extends \advanced_testcase {
    /**
     * Test course_edit_submission persists setting for newly created courses.
     */
    public function test_course_edit_submission_new_course_disabled(): void {
        global $DB;
        $this->resetAfterTest();

        // Site default is enabled (1).
        set_config('default_enabled', 1, 'local_quicknote');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Simulate core_course\hook\after_form_submission dispatched during course creation.
        // When a new course is created, the HTTP request has no 'id' parameter,
        // but $hook->get_data()->id contains the newly assigned course id.
        $data = (object)[
            'id' => $course->id,
            'local_quicknote_enabled' => 0,
        ];
        $hook = new \core_course\hook\after_form_submission($data, true);
        \local_quicknote\hooks::course_edit_submission($hook);

        // Verify the setting was saved and QuickNote is disabled for this course.
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $course->id]);
        $this->assertNotEmpty($record);
        $this->assertEquals(0, (int)$record->enabled);
        $this->assertFalse(\local_quicknote\hooks::is_enabled_for_course($course));
    }

    /**
     * Test course_edit_submission persists setting when explicitly enabling a course.
     */
    public function test_course_edit_submission_new_course_enabled(): void {
        global $DB;
        $this->resetAfterTest();

        // Site default is disabled (0).
        set_config('default_enabled', 0, 'local_quicknote');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $data = (object)[
            'id' => $course->id,
            'local_quicknote_enabled' => 1,
        ];
        $hook = new \core_course\hook\after_form_submission($data, true);
        \local_quicknote\hooks::course_edit_submission($hook);

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $course->id]);
        $this->assertNotEmpty($record);
        $this->assertEquals(1, (int)$record->enabled);
        $this->assertTrue(\local_quicknote\hooks::is_enabled_for_course($course));
    }

    /**
     * Test course_edit_submission updates an existing course setting.
     */
    public function test_course_edit_submission_update_existing(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // First save as enabled.
        $data = (object)[
            'id' => $course->id,
            'local_quicknote_enabled' => 1,
        ];
        $hook = new \core_course\hook\after_form_submission($data, false);
        \local_quicknote\hooks::course_edit_submission($hook);

        $this->assertTrue(\local_quicknote\hooks::is_enabled_for_course($course));

        // Now update to disabled.
        $data->local_quicknote_enabled = 0;
        $hook = new \core_course\hook\after_form_submission($data, false);
        \local_quicknote\hooks::course_edit_submission($hook);

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $course->id]);
        $this->assertEquals(0, (int)$record->enabled);
        $this->assertFalse(\local_quicknote\hooks::is_enabled_for_course($course));
    }

    /**
     * Test that course_edit_submission ignores submissions without local_quicknote_enabled.
     */
    public function test_course_edit_submission_ignores_unrelated(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $data = (object)[
            'id' => $course->id,
            'fullname' => 'Updated Name',
        ];
        $hook = new \core_course\hook\after_form_submission($data, false);
        \local_quicknote\hooks::course_edit_submission($hook);

        $this->assertFalse($DB->record_exists('local_quicknote_course', ['courseid' => $course->id]));
    }

    /**
     * Test module callbacks save settings on new module creation and edit.
     */
    public function test_coursemodule_edit_post_actions_new_and_update(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/quicknote/lib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);

        // 1. Simulate new module creation: coursemodule is set, update is 0.
        $data = (object)[
            'coursemodule' => $page->cmid,
            'update' => 0,
            'local_quicknote_module' => '0',
        ];
        local_quicknote_coursemodule_edit_post_actions($data, $course);

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $course->id]);
        $this->assertNotEmpty($record);
        $settings = json_decode($record->module_settings, true);
        $this->assertEquals(0, $settings[$page->cmid]);

        // 2. Simulate module update: update is set.
        $data = (object)[
            'coursemodule' => 0,
            'update' => $page->cmid,
            'local_quicknote_module' => '1',
        ];
        local_quicknote_coursemodule_edit_post_actions($data, $course);

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $course->id]);
        $settings = json_decode($record->module_settings, true);
        $this->assertEquals(1, $settings[$page->cmid]);

        // 3. Reset to default (empty string) removes the setting.
        $data = (object)[
            'coursemodule' => $page->cmid,
            'update' => $page->cmid,
            'local_quicknote_module' => '',
        ];
        local_quicknote_coursemodule_edit_post_actions($data, $course);

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $course->id]);
        $settings = json_decode($record->module_settings, true);
        $this->assertArrayNotHasKey($page->cmid, $settings);
    }

    /**
     * Test is_voice_dictation_enabled returns true by default when not set.
     */
    public function test_is_voice_dictation_enabled_default(): void {
        $this->resetAfterTest();
        unset_config('enable_voice_dictation', 'local_quicknote');

        $this->assertTrue(\local_quicknote\hooks::is_voice_dictation_enabled());
    }

    /**
     * Test is_voice_dictation_enabled respects explicitly configured values.
     */
    public function test_is_voice_dictation_enabled_configured(): void {
        $this->resetAfterTest();

        // When set to 0, voice dictation should be disabled.
        set_config('enable_voice_dictation', 0, 'local_quicknote');
        $this->assertFalse(\local_quicknote\hooks::is_voice_dictation_enabled());

        // When set to 1, voice dictation should be enabled.
        set_config('enable_voice_dictation', 1, 'local_quicknote');
        $this->assertTrue(\local_quicknote\hooks::is_voice_dictation_enabled());
    }

    /**
     * Test sidebar template renders voice dictation elements correctly.
     */
    public function test_sidebar_template_voice_dictation_rendering(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $context = [
            'courseid' => 1,
            'pageurl' => 'https://example.com/test',
            'pagehash' => 'dummyhash',
            'pagetitle' => 'Test Page',
            'positionclass' => 'local-quicknote--right',
            'title' => get_string('sidebar:title', 'local_quicknote'),
            'togglelabel' => get_string('sidebar:toggle', 'local_quicknote'),
            'closelabel' => get_string('sidebar:close', 'local_quicknote'),
            'addlabel' => get_string('note:add', 'local_quicknote'),
            'placeholder' => get_string('note:placeholder', 'local_quicknote'),
            'emptytext' => get_string('note:empty', 'local_quicknote'),
            'savingtext' => get_string('note:saving', 'local_quicknote'),
            'savedtext' => get_string('note:saved', 'local_quicknote'),
            'errortext' => get_string('note:error', 'local_quicknote'),
            'updatedlabel' => get_string('note:updated', 'local_quicknote'),
            'locationlabel' => get_string('note:location', 'local_quicknote'),
            'deleteconfirm' => get_string('note:delete_confirm', 'local_quicknote'),
            'noresultstext' => get_string('search:noresultstext', 'local_quicknote'),
            'highlightlabel' => get_string('select:highlightlabel', 'local_quicknote'),
            'enablescreenshots' => false,
            'enablevoicedictation' => true,
            'voicelabel' => get_string('voice:dictate', 'local_quicknote'),
            'voicestoplabel' => get_string('voice:stop', 'local_quicknote'),
            'voicelisteninglabel' => get_string('voice:listening', 'local_quicknote'),
            'voiceendedlabel' => get_string('voice:ended', 'local_quicknote'),
        ];

        // 1. Render with voice dictation enabled.
        $html = $OUTPUT->render_from_template('local_quicknote/sidebar', $context);

        $this->assertStringContainsString('data-enablevoicedictation="1"', $html);
        $this->assertStringContainsString('data-action="voice-dictate"', $html);
        $this->assertStringContainsString('id="local-quicknote-a11y-announcer"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('local-quicknote__voice-btn', $html);

        // 2. Render with voice dictation disabled.
        $context['enablevoicedictation'] = false;
        $html = $OUTPUT->render_from_template('local_quicknote/sidebar', $context);
        $this->assertStringContainsString('data-enablevoicedictation="0"', $html);
    }
}
