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
        global $DB;
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $courseid]);
        $settingsjson = $record ? $record->module_settings : null;
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

        global $DB;
        $record = $DB->get_record('local_quicknote_course', ['courseid' => $courseid]);
        $settingsjson = $record ? $record->module_settings : null;
        $settings = $settingsjson ? json_decode($settingsjson, true) : [];
        $settings = is_array($settings) ? $settings : [];

        if ($data->local_quicknote_module === '' || $data->local_quicknote_module === false) {
            unset($settings[$cmid]);
        } else {
            $settings[$cmid] = (int) $data->local_quicknote_module;
        }

        $json = json_encode($settings);
        if ($record) {
            $record->module_settings = $json;
            $DB->update_record('local_quicknote_course', $record);
        } else {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->enabled = 1;
            $record->module_settings = $json;
            $DB->insert_record('local_quicknote_course', $record);
        }
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

/**
 * Serve a screenshot only to the owner of its note.
 *
 * @param stdClass $course Unused course record.
 * @param stdClass|null $cm Unused course module.
 * @param context $context File context.
 * @param string $filearea File area.
 * @param array $args Item id, path and filename.
 * @param bool $forcedownload Whether download was requested.
 * @param array $options File serving options.
 * @return bool|void False when the request is not valid.
 */
function local_quicknote_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== \local_quicknote\local\screenshot_manager::FILEAREA) {
        return false;
    }

    require_login();
    require_capability('local/quicknote:use', $context);
    $noteid = (int) array_shift($args);
    if (!$DB->record_exists('local_quicknote_notes', ['id' => $noteid, 'userid' => $USER->id])) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'local_quicknote',
        $filearea,
        $noteid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}
