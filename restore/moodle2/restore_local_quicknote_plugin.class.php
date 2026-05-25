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
 * Restore plugin class for local_quicknote.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @author      Matthias Giger <https://github.com/mattgig>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the steps to restore QuickNote plugin data.
 *
 * @package    local_quicknote
 */
class restore_local_quicknote_plugin extends restore_local_plugin {
    /**
     * Define course-level restore structure.
     *
     * @return array
     */
    protected function define_course_plugin_structure(): array {
        $paths = [];

        $elename = 'quicknote_course';
        $elepath = $this->get_pathfor('/quicknote_course');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Define module-level restore structure.
     *
     * @return array
     */
    protected function define_module_plugin_structure(): array {
        $paths = [];

        $elename = 'quicknote_module';
        $elepath = $this->get_pathfor('/quicknote_module');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Process the course-level setting element.
     *
     * @param array $data
     */
    public function process_quicknote_course(array $data): void {
        $courseid = $this->get_courseid();

        if (isset($data['enabled']) && $data['enabled'] !== '') {
            set_config('enabled', $data['enabled'], 'local_quicknote_course_' . $courseid);
        }
    }

    /**
     * Process a module-level setting element.
     *
     * @param array $data
     */
    public function process_quicknote_module(array $data): void {
        $courseid = $this->get_courseid();
        $oldcmid = $this->task->get_moduleid();
        $newcmid = $this->get_mappingid('course_module', $oldcmid);

        if (!isset($data['value'])) {
            return;
        }

        $settingsjson = get_config('local_quicknote_course_' . $courseid, 'module_settings');
        $settings = $settingsjson ? json_decode($settingsjson, true) : [];
        $settings = is_array($settings) ? $settings : [];

        $settings[$newcmid] = (int) $data['value'];

        set_config('module_settings', json_encode($settings), 'local_quicknote_course_' . $courseid);
    }
}
