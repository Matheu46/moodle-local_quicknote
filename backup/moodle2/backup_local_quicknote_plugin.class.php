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
 * Backup plugin class for local_quicknote.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @author      Matthias Giger <https://github.com/mattgig>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the steps to backup QuickNote plugin data.
 *
 * @package    local_quicknote
 */
class backup_local_quicknote_plugin extends backup_local_plugin {
    /**
     * Define course-level plugin structure.
     * All data is wrapped at the course level as this is a local plugin.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure(): backup_nested_element {
        $plugin = $this->get_plugin_element(null);

        // Use the recommended plugin wrapper to encapsulate all plugin data.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        // Course-level settings node.
        $coursenode = new backup_nested_element('quicknote_course', ['id'], ['enabled']);
        $pluginwrapper->add_child($coursenode);

        $courseid = $this->step->get_task()->get_courseid();
        $enabled = get_config('local_quicknote_course_' . $courseid, 'enabled');
        $coursenode->set_source_array([['id' => $courseid, 'enabled' => $enabled !== false ? $enabled : '']]);

        // Module-level settings container node.
        $modulesnode = new backup_nested_element('quicknote_modules');
        $pluginwrapper->add_child($modulesnode);

        $modulenode = new backup_nested_element('quicknote_module', ['id'], ['cmid', 'value']);
        $modulesnode->add_child($modulenode);

        // Retrieve the JSON string containing module-specific settings.
        $settingsjson = get_config('local_quicknote_course_' . $courseid, 'module_settings');
        $settings = $settingsjson ? json_decode($settingsjson, true) : [];
        $settings = is_array($settings) ? $settings : [];

        // Transform the JSON settings into an array format suitable for XML generation.
        $modulesdata = [];
        $i = 1;
        foreach ($settings as $cmid => $val) {
            $modulesdata[] = ['id' => $i++, 'cmid' => $cmid, 'value' => $val];
        }
        $modulenode->set_source_array($modulesdata);

        return $plugin;
    }
}
