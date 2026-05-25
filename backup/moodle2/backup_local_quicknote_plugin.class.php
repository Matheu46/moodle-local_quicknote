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
     *
     * @return backup_nested_element
     */
    protected function define_course_plugin_structure(): backup_nested_element {
        $plugin = $this->get_plugin_element();

        $coursenode = new backup_nested_element('quicknote_course', null, ['enabled']);
        $plugin->add_child($coursenode);

        $courseid = $this->step->get_task()->get_courseid();
        $enabled = get_config('local_quicknote_course_' . $courseid, 'enabled');

        $coursenode->set_source_array([['enabled' => $enabled !== false ? $enabled : '']]);

        return $plugin;
    }

    /**
     * Define module-level plugin structure.
     *
     * @return backup_nested_element
     */
    protected function define_module_plugin_structure(): backup_nested_element {
        $plugin = $this->get_plugin_element();

        $modulesetting = new backup_nested_element('quicknote_module', null, ['value']);
        $plugin->add_child($modulesetting);

        $cmid = $this->step->get_task()->get_moduleid();
        $courseid = $this->step->get_task()->get_courseid();

        $settingsjson = get_config('local_quicknote_course_' . $courseid, 'module_settings');
        $settings = $settingsjson ? json_decode($settingsjson, true) : [];
        $settings = is_array($settings) ? $settings : [];

        if (isset($settings[$cmid])) {
            $modulesetting->set_source_array([['value' => $settings[$cmid]]]);
        } else {
            $modulesetting->set_source_array([]);
        }

        return $plugin;
    }
}
