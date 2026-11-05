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
use local_quicknote\output\mobile;

/**
 * Mobile output tests for local_quicknote.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quicknote\output\mobile
 */
final class mobile_test extends advanced_testcase {
    /**
     * Test mobile_course_view structure and string escaping in otherdata.
     */
    public function test_mobile_course_view_structure(): void {
        $this->resetAfterTest();

        $courseid = 42;
        $result = mobile::mobile_course_view(['courseid' => $courseid]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('templates', $result);
        $this->assertArrayHasKey('javascript', $result);
        $this->assertArrayHasKey('otherdata', $result);

        // Verify otherdata contains courseid and deleteconfirm.
        $this->assertEquals($courseid, $result['otherdata']['courseid']);
        $this->assertArrayHasKey('deleteconfirm', $result['otherdata']);
        $this->assertEquals(
            get_string('note:delete_confirm', 'local_quicknote'),
            $result['otherdata']['deleteconfirm']
        );

        // Verify the raw JS template does NOT contain raw %%DELETE_CONFIRM%% placeholder.
        $this->assertStringNotContainsString('%%DELETE_CONFIRM%%', $result['javascript']);
        $this->assertStringContainsString('deleteconfirm', $result['javascript']);
    }

    /**
     * Test mobile_course_view with default/missing arguments.
     */
    public function test_mobile_course_view_default_args(): void {
        $this->resetAfterTest();

        $result = mobile::mobile_course_view([]);

        $this->assertEquals(0, $result['otherdata']['courseid']);
        $this->assertNotEmpty($result['otherdata']['deleteconfirm']);
    }
}

