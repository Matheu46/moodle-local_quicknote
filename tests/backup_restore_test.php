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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/tests/backup_restore_base_testcase.php');

/**
 * Backup and restore tests for local_quicknote.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_quicknote_plugin
 * @covers     \restore_local_quicknote_plugin
 */
final class backup_restore_test extends \core_backup_backup_restore_base_testcase {
    /**
     * Restore a backup to a brand new course.
     *
     * @param string $backupid Backup ID.
     * @param \stdClass $sourcecourse Original course.
     * @return \stdClass The restored course.
     */
    protected function restore_to_new_course(string $backupid, \stdClass $sourcecourse): \stdClass {
        global $USER, $DB;

        $newcourseid = \restore_dbops::create_new_course(
            $sourcecourse->fullname . ' Restored',
            $sourcecourse->shortname . '_restored_' . time(),
            $sourcecourse->category
        );

        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $DB->get_record('course', ['id' => $newcourseid], '*', MUST_EXIST);
    }

    /**
     * Test that restoring a course with no explicit setting inherits site default and does not collapse to 0.
     */
    public function test_backup_restore_course_without_explicit_setting(): void {
        global $DB;

        set_config('default_enabled', 1, 'local_quicknote');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Ensure source course has no local_quicknote_course record.
        $this->assertFalse($DB->record_exists('local_quicknote_course', ['courseid' => $course->id]));
        $this->assertTrue(\local_quicknote\hooks::is_enabled_for_course($course));

        // Backup and restore to a new course.
        $backupid = $this->perform_backup($course);
        $newcourse = $this->restore_to_new_course($backupid, $course);

        // Restored course must NOT have a record with enabled = 0.
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $newcourse->id]);
        $enabled = $record ? $record->enabled : null;
        $this->assertTrue($record === false || $enabled === null);

        // It should inherit the site default (currently 1).
        $this->assertTrue(\local_quicknote\hooks::is_enabled_for_course($newcourse));

        // Change site default to 0; restored course must dynamically reflect the change.
        set_config('default_enabled', 0, 'local_quicknote');
        $this->assertFalse(\local_quicknote\hooks::is_enabled_for_course($newcourse));
    }

    /**
     * Test that restoring a course explicitly enabled preserves enabled = 1.
     */
    public function test_backup_restore_course_explicitly_enabled(): void {
        global $DB;

        set_config('default_enabled', 0, 'local_quicknote');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Explicitly enable in course.
        $DB->insert_record('local_quicknote_course', (object)[
            'courseid' => $course->id,
            'enabled' => 1,
            'module_settings' => '',
        ]);
        $this->assertTrue(\local_quicknote\hooks::is_enabled_for_course($course));

        // Backup and restore to a new course.
        $backupid = $this->perform_backup($course);
        $newcourse = $this->restore_to_new_course($backupid, $course);

        // Restored course must have enabled = 1.
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $newcourse->id]);
        $this->assertNotEmpty($record);
        $this->assertEquals(1, (int)$record->enabled);
        $this->assertTrue(\local_quicknote\hooks::is_enabled_for_course($newcourse));
    }

    /**
     * Test that restoring a course explicitly disabled preserves enabled = 0.
     */
    public function test_backup_restore_course_explicitly_disabled(): void {
        global $DB;

        set_config('default_enabled', 1, 'local_quicknote');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Explicitly disable in course.
        $DB->insert_record('local_quicknote_course', (object)[
            'courseid' => $course->id,
            'enabled' => 0,
            'module_settings' => '',
        ]);
        $this->assertFalse(\local_quicknote\hooks::is_enabled_for_course($course));

        // Backup and restore to a new course.
        $backupid = $this->perform_backup($course);
        $newcourse = $this->restore_to_new_course($backupid, $course);

        // Restored course must have enabled = 0.
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $newcourse->id]);
        $this->assertNotEmpty($record);
        $this->assertEquals(0, (int)$record->enabled);
        $this->assertFalse(\local_quicknote\hooks::is_enabled_for_course($newcourse));
    }

    /**
     * Test that when only module settings exist, course-level enabled remains null (not hardcoded to 1).
     */
    public function test_backup_restore_course_with_only_module_settings(): void {
        global $DB;

        set_config('default_enabled', 0, 'local_quicknote');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);

        // Insert course record with module setting but null enabled.
        $DB->insert_record('local_quicknote_course', (object)[
            'courseid' => $course->id,
            'enabled' => null,
            'module_settings' => json_encode([$page->cmid => 1]),
        ]);

        // Course should inherit default_enabled = 0.
        $this->assertFalse(\local_quicknote\hooks::is_enabled_for_course($course));

        // Backup and restore to a new course.
        $backupid = $this->perform_backup($course);
        $newcourse = $this->restore_to_new_course($backupid, $course);

        // Restored course should have module settings preserved, but enabled must remain null.
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $newcourse->id]);
        $this->assertNotEmpty($record);
        $this->assertNull($record->enabled);
        $this->assertNotEmpty($record->module_settings);

        // Course should still inherit default_enabled = 0 (not forced to 1).
        $this->assertFalse(\local_quicknote\hooks::is_enabled_for_course($newcourse));
    }

    /**
     * Test backward compatibility with legacy backups having empty string enabled.
     */
    public function test_restore_legacy_backup_empty_string(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Create a dummy restore step and task to run the restore plugin.
        $task = $this->createMock(\restore_course_task::class);
        $task->method('get_courseid')->willReturn($course->id);

        $step = $this->createMock(\restore_structure_step::class);
        $step->method('get_task')->willReturn($task);

        $restoreplugin = new \restore_local_quicknote_plugin('local', 'quicknote', $step);
        $restoreplugin->process_quicknote_course(['id' => $course->id, 'enabled' => '']);
        $restoreplugin->after_restore_course();

        // Must NOT save any record with enabled = 0.
        $this->assertFalse($DB->record_exists('local_quicknote_course', ['courseid' => $course->id]));
    }
}
