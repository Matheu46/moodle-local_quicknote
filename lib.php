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
 * Plugin callbacks.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add QuickNote settings to the module edit form.
 *
 * @param moodleform_mod $formwrapper The module form wrapper.
 * @param MoodleQuickForm $mform The form element.
 */
function local_quicknote_coursemodule_standard_elements($formwrapper, $mform) {
    $cmid = optional_param('update', 0, PARAM_INT);
    $current = null;

    if ($cmid > 0) {
        $courseid = $formwrapper->get_course()->id;
        $settingsjson = get_config('local_quicknote_course_' . $courseid, 'module_settings');
        $settings = $settingsjson ? json_decode($settingsjson, true) : [];
        $settings = is_array($settings) ? $settings : [];
        $current = $settings[$cmid] ?? null;
    }

    $mform->addElement('header', 'local_quicknote_header', get_string('pluginname', 'local_quicknote'));
    $mform->addElement('select', 'local_quicknote_module', get_string('module:setting', 'local_quicknote'), [
        '' => get_string('module:default', 'local_quicknote'),
        '0' => get_string('module:disabled', 'local_quicknote'),
        '1' => get_string('module:enabled', 'local_quicknote'),
    ]);
    $mform->setDefault('local_quicknote_module', $current !== null ? (string) $current : '');
}

/**
 * Save QuickNote settings when a module form is submitted.
 *
 * @param stdClass $data The submitted form data.
 * @param stdClass $course The course object.
 * @return stdClass
 */
function local_quicknote_coursemodule_edit_post_actions($data, $course) {
    if (!empty($data->update) && isset($data->local_quicknote_module)) {
        $cmid = $data->update;
        $courseid = $course->id;

        $settingsjson = get_config('local_quicknote_course_' . $courseid, 'module_settings');
        $settings = $settingsjson ? json_decode($settingsjson, true) : [];
        $settings = is_array($settings) ? $settings : [];

        if ($data->local_quicknote_module === '' || $data->local_quicknote_module === false) {
            unset($settings[$cmid]);
        } else {
            $settings[$cmid] = (int) $data->local_quicknote_module;
        }

        set_config('module_settings', json_encode($settings), 'local_quicknote_course_' . $courseid);
    }

    return $data;
}

/**
 * Legacy callback to inject the QuickNote UI in Moodle < 4.4.
 * In Moodle 4.4+, this is handled by the Hooks API (db/hooks.php).
 *
 * @return string HTML to inject.
 */
function local_quicknote_before_standard_top_of_body_html() {
    // If the new Hook class exists, Moodle 4.4+ Hooks API will handle it.
    if (class_exists(\core\hook\output\before_standard_top_of_body_html_generation::class)) {
        return '';
    }

    // Otherwise, generate and return the HTML for older Moodle versions.
    return \local_quicknote\hooks::get_top_of_body_html();
}
