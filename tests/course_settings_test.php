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
use local_quicknote\local\course_settings;

/**
 * Unit tests for course_settings cache and persistence manager.
 *
 * @package     local_quicknote
 * @category    test
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_quicknote\local\course_settings
 */
final class course_settings_test extends advanced_testcase {
    public function test_is_enabled_for_course_defaults_and_overrides(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // 1. Inherit default when enabled globally.
        set_config('default_enabled', 1, 'local_quicknote');
        $this->assertTrue(course_settings::is_enabled_for_course($course->id));

        // 2. Change global default to disabled.
        // Cache stores enabled => null, so global default change reflects immediately without cache purge.
        set_config('default_enabled', 0, 'local_quicknote');
        $this->assertFalse(course_settings::is_enabled_for_course($course->id));

        // 3. Explicitly enable for this course.
        course_settings::set_course_enabled($course->id, 1);
        $this->assertTrue(course_settings::is_enabled_for_course($course->id));

        // Changing global default does not affect course with explicit override.
        set_config('default_enabled', 0, 'local_quicknote');
        $this->assertTrue(course_settings::is_enabled_for_course($course->id));

        // 4. Explicitly disable for this course.
        course_settings::set_course_enabled($course->id, 0);
        $this->assertFalse(course_settings::is_enabled_for_course($course->id));

        // 5. Reset course override to inherit (null).
        course_settings::set_course_enabled($course->id, null);
        set_config('default_enabled', 1, 'local_quicknote');
        $this->assertTrue(course_settings::is_enabled_for_course($course->id));

        // Invalid course ID returns false.
        $this->assertFalse(course_settings::is_enabled_for_course(0));
        $this->assertFalse(course_settings::is_enabled_for_course(-1));
    }

    public function test_module_settings_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $cm1 = 101;
        $cm2 = 102;

        // Initially both modules return null (inherit default).
        $this->assertNull(course_settings::get_module_setting($course->id, $cm1));
        $this->assertNull(course_settings::get_module_setting($course->id, $cm2));

        // Set overrides for both modules.
        course_settings::set_module_setting($course->id, $cm1, 0);
        course_settings::set_module_setting($course->id, $cm2, 1);

        $this->assertSame(0, course_settings::get_module_setting($course->id, $cm1));
        $this->assertSame(1, course_settings::get_module_setting($course->id, $cm2));

        // Verify database state matches.
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $course->id]);
        $this->assertNotNull($record);
        $decoded = json_decode($record->module_settings, true);
        $this->assertSame(0, $decoded[$cm1]);
        $this->assertSame(1, $decoded[$cm2]);

        // Remove override for cm1.
        course_settings::set_module_setting($course->id, $cm1, null);
        $this->assertNull(course_settings::get_module_setting($course->id, $cm1));
        $this->assertSame(1, course_settings::get_module_setting($course->id, $cm2));

        // Delete module setting via delete_module_setting helper.
        $deleted = course_settings::delete_module_setting($course->id, $cm2);
        $this->assertTrue($deleted);
        $this->assertNull(course_settings::get_module_setting($course->id, $cm2));

        // Deleting already removed module returns false.
        $deletedagain = course_settings::delete_module_setting($course->id, $cm2);
        $this->assertFalse($deletedagain);

        // Record now has empty module_settings.
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $course->id]);
        $this->assertEmpty(json_decode($record->module_settings, true));

        // Invalid inputs return null.
        $this->assertNull(course_settings::get_module_setting(0, $cm1));
        $this->assertNull(course_settings::get_module_setting($course->id, 0));
    }

    public function test_cache_purge(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();

        course_settings::set_course_enabled($course1->id, 1);
        course_settings::set_course_enabled($course2->id, 1);

        // Populate cache.
        $this->assertTrue(course_settings::is_enabled_for_course($course1->id));
        $this->assertTrue(course_settings::is_enabled_for_course($course2->id));

        // Directly manipulate DB without cache helper to verify cache hit.
        $DB->set_field('local_quicknote_course', 'enabled', 0, ['courseid' => $course1->id]);
        $DB->set_field('local_quicknote_course', 'enabled', 0, ['courseid' => $course2->id]);

        // Cache hit: still returns true before purge.
        $this->assertTrue(course_settings::is_enabled_for_course($course1->id));
        $this->assertTrue(course_settings::is_enabled_for_course($course2->id));

        // Purge only course1 cache.
        course_settings::purge_course_cache($course1->id);

        // Course 1 now re-reads DB and returns false; course 2 still returns cached true.
        $this->assertFalse(course_settings::is_enabled_for_course($course1->id));
        $this->assertTrue(course_settings::is_enabled_for_course($course2->id));

        // Purge all caches.
        course_settings::purge_all();
        $this->assertFalse(course_settings::is_enabled_for_course($course2->id));
    }
}
