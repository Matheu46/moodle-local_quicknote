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

/**
 * Visibility test for QuickNote hook.
 *
 * @package    local_quicknote
 * @category   test
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Visibility tests for QuickNote plugin.
 *
 * @package    local_quicknote
 * @category   test
 */
class local_quicknote_visibility_test extends advanced_testcase {

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Test that rendering is blocked on the Site Home (is_sitecourse).
     */
    public function test_blocked_on_site_home() {
        global $PAGE;

        $this->setAdminUser();

        $sitecourse = get_site();
        $PAGE->set_course($sitecourse);
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url('/');
        $PAGE->set_pagetype('site-index');

        $hook = $this->createMock(\core\hook\output\before_standard_top_of_body_html_generation::class);
        $hook->expects($this->never())->method('add_html');

        \local_quicknote\hooks::before_standard_top_of_body_html_generation($hook);
    }

    /**
     * Test that rendering is blocked in System Context (CONTEXT_SYSTEM).
     */
    public function test_blocked_in_system_context() {
        global $PAGE;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        
        $PAGE->set_course($course);
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url('/admin/search.php');
        $PAGE->set_pagetype('admin-setting-search');

        $hook = $this->createMock(\core\hook\output\before_standard_top_of_body_html_generation::class);
        $hook->expects($this->never())->method('add_html');

        \local_quicknote\hooks::before_standard_top_of_body_html_generation($hook);
    }

    /**
     * Test that rendering is allowed in a Course Context (CONTEXT_COURSE).
     */
    public function test_allowed_in_course_context() {
        global $PAGE;

        // Ensure the plugin is enabled by default.
        set_config('default_enabled', 1, 'local_quicknote');

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $coursecontext = context_course::instance($course->id);
        
        $PAGE->set_course($course);
        $PAGE->set_context($coursecontext);
        $PAGE->set_url('/course/view.php?id=' . $course->id);
        $PAGE->set_pagetype('course-view-topics');

        $hook = $this->createMock(\core\hook\output\before_standard_top_of_body_html_generation::class);
        $hook->expects($this->once())->method('add_html');

        \local_quicknote\hooks::before_standard_top_of_body_html_generation($hook);
    }
}
