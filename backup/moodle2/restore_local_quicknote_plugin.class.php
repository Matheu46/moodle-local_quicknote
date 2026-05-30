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
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_quicknote_plugin extends restore_local_plugin {
    /** @var stdClass|null Temporary storage for course settings during XML reading. */
    protected $courseconfig = null;

    /** @var array Temporary storage for module settings during XML reading. */
    protected $moduleconfigs = [];

    /**
     * Define the restore plugin structure.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure(): array {
        return [
            new restore_path_element('quicknote_course', $this->get_pathfor('/quicknote_course')),
            new restore_path_element('quicknote_module', $this->get_pathfor('/quicknote_modules/quicknote_module')),
        ];
    }

    /**
     * Temporarily store the course-level setting element from the XML.
     *
     * @param array $data Record data from the backup file.
     * @return void
     */
    public function process_quicknote_course($data) {
        $this->courseconfig = (object)$data;
    }

    /**
     * Temporarily store a module-level setting element from the XML.
     *
     * @param array $data Record data from the backup file.
     * @return void
     */
    public function process_quicknote_module($data) {
        $this->moduleconfigs[] = (object)$data;
    }

    /**
     * Executes after the course and all its modules have been fully restored.
     * Processes and maps IDs for the stored configurations.
     *
     * @return void
     */
    public function after_restore_course() {
        $courseid = $this->task->get_courseid();

        // Restore course-level configuration.
        if ($this->courseconfig && isset($this->courseconfig->enabled)) {
            set_config('enabled', $this->courseconfig->enabled, 'local_quicknote_course_' . $courseid);
        }

        // Restore and map module-level configurations.
        if (!empty($this->moduleconfigs)) {
            $settings = [];
            foreach ($this->moduleconfigs as $moddata) {
                // Map the original module ID to the newly restored module ID.
                $newcmid = $this->get_mappingid('course_module', $moddata->cmid);

                // Fallback to 'module' mapping if 'course_module' is not found.
                if (empty($newcmid)) {
                    $newcmid = $this->get_mappingid('module', $moddata->cmid);
                }

                if (!empty($newcmid)) {
                    $settings[$newcmid] = (int)$moddata->value;
                }
            }

            // Save the newly mapped settings to the database.
            if (!empty($settings)) {
                set_config('module_settings', json_encode($settings), 'local_quicknote_course_' . $courseid);
            }
        }
    }
}
