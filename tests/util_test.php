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

/**
 * Utility tests for local_quicknote.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quicknote\util
 */
final class util_test extends advanced_testcase {
    public function test_validate_note_access_dashboard_enabled(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Enable dashboard notes.
        set_config('enable_dashboard', 1, 'local_quicknote');
        set_config('enable_frontpage', 0, 'local_quicknote');

        // Should return the site course.
        $course = \local_quicknote\util::validate_note_access(SITEID);
        $this->assertEquals(SITEID, $course->id);
    }

    public function test_validate_note_access_frontpage_enabled(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Disable dashboard, but enable frontpage.
        set_config('enable_dashboard', 0, 'local_quicknote');
        set_config('enable_frontpage', 1, 'local_quicknote');

        // Should return the site course.
        $course = \local_quicknote\util::validate_note_access(SITEID);
        $this->assertEquals(SITEID, $course->id);
    }

    public function test_validate_note_access_siteid_disabled(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Disable both dashboard and frontpage notes.
        set_config('enable_dashboard', 0, 'local_quicknote');
        set_config('enable_frontpage', 0, 'local_quicknote');

        $this->expectException(\moodle_exception::class);
        \local_quicknote\util::validate_note_access(SITEID);
    }

    public function test_validate_note_access_regular_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        
        $generator->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // Should validate normally for a student in the course.
        $validatedcourse = \local_quicknote\util::validate_note_access($course->id);
        $this->assertEquals($course->id, $validatedcourse->id);
    }
}
