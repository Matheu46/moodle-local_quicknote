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

    public function test_clean_url(): void {
        // Valid simple URLs.
        $this->assertEquals('http://example.com', \local_quicknote\util::clean_url('http://example.com'));
        $this->assertEquals('https://example.com/path', \local_quicknote\util::clean_url('https://example.com/path'));
        $this->assertEquals('#section2', \local_quicknote\util::clean_url('#section2'));

        // Complex fragment URLs (H5P style).
        $complex = 'http://localhost:8000/mod/h5pactivity/view.php?id=10#h5pbookid=2&section=top#:~:text=teste';
        $this->assertEquals($complex, \local_quicknote\util::clean_url($complex));

        // Invalid or dangerous schemes should be blocked.
        $this->assertNull(\local_quicknote\util::clean_url('javascript:alert(1)'));
        $this->assertNull(\local_quicknote\util::clean_url('data:text/html,<script>alert(1)</script>'));
        $this->assertNull(\local_quicknote\util::clean_url('vbscript:msgbox(1)'));

        // Null and empty URLs.
        $this->assertNull(\local_quicknote\util::clean_url(null));
        $this->assertNull(\local_quicknote\util::clean_url('   '));
        $this->assertNull(\local_quicknote\util::clean_url(''));

        // Control characters injection should be blocked.
        $this->assertNull(\local_quicknote\util::clean_url("https://example.com\r\njavascript:alert(1)"));
        $this->assertNull(\local_quicknote\util::clean_url("javascript\x00:alert(1)"));

        // Volatile query parameters (sesskey, _qf__, local_quicknote_open) stripped and remaining sorted.
        $messy = 'https://example.com/mod/page/view.php?sesskey=abc123&b=2&a=1&_qf__form=1&local_quicknote_open=1#sec1';
        $this->assertEquals(
            'https://example.com/mod/page/view.php?a=1&b=2#sec1',
            \local_quicknote\util::clean_url($messy)
        );

        // Strip text fragment when $striptextfragment = true, while keeping H5P hash.
        $this->assertEquals(
            'http://localhost:8000/mod/h5pactivity/view.php?id=10#h5pbookid=2&section=top',
            \local_quicknote\util::clean_url($complex, false, true)
        );
    }

    public function test_canonicalise_and_page_hash(): void {
        global $CFG;

        $url1 = 'http://old-domain.edu/ava/mod/page/view.php?sesskey=xyz&b=2&a=1#section1';
        $url2 = rtrim($CFG->wwwroot, '/') . '/mod/page/view.php?a=1&b=2&_qf__test=1#:~:text=hello';

        $this->assertEquals('/mod/page/view.php?a=1&b=2', \local_quicknote\util::canonicalise_url($url1));
        $this->assertEquals('/mod/page/view.php?a=1&b=2', \local_quicknote\util::canonicalise_url($url2));

        $hash1 = \local_quicknote\util::calculate_page_hash($url1);
        $hash2 = \local_quicknote\util::calculate_page_hash($url2);

        $this->assertSame(64, strlen($hash1));
        $this->assertSame($hash1, $hash2);

        // Rebased URL points to current $CFG->wwwroot and preserves fragment.
        $this->assertEquals(
            rtrim($CFG->wwwroot, '/') . '/mod/page/view.php?a=1&b=2#section1',
            \local_quicknote\util::clean_url($url1, true, false)
        );
    }

    public function test_clean_page_title(): void {
        $this->resetAfterTest();
        $site = get_site();
        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'PHYS101',
            'fullname' => 'Physics 1',
        ]);

        // Strips site suffix and course prefix.
        $raw = 'PHYS101: Lesson 3 - Newton Laws | ' . $site->shortname;
        $this->assertEquals('Lesson 3 - Newton Laws', \local_quicknote\util::clean_page_title($raw, $course));

        // Strips "Course: " prefix.
        $rawcourse = get_string('course') . ': Physics 1 | ' . $site->fullname;
        $this->assertEquals('Physics 1', \local_quicknote\util::clean_page_title($rawcourse, $course));

        // Null and empty titles.
        $this->assertNull(\local_quicknote\util::clean_page_title(null, $course));
        $this->assertNull(\local_quicknote\util::clean_page_title('   ', $course));
    }
}
