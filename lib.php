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

defined('MOODLE_INTERNAL') || die();

use context_course;

/**
 * Checks whether QuickNote is enabled for the current course.
 *
 * @param stdClass $course
 * @return bool
 */
function local_quicknote_is_enabled_for_course($course) {

    $enabled = get_config('local_quicknote_course_' . $course->id, 'enabled');

    if ($enabled === false || $enabled === null || $enabled === '') {
        // Return the default setting defined by the administrator in Site Administration.
        return (bool) get_config('local_quicknote', 'default_enabled');
    }

    return (string) $enabled !== '0';
}

function local_quicknote_before_standard_top_of_body_html() {
    global $OUTPUT, $PAGE, $USER;

    if (during_initial_install() || (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
        return '';
    }

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    if (empty($PAGE->course->id) || (int) $PAGE->course->id === SITEID) {
        return '';
    }

    $course = get_course($PAGE->course->id);
    $context = context_course::instance($course->id, IGNORE_MISSING);

    if (!$context) {
        return '';
    }

    if (!has_capability('moodle/course:view', $context)) {
        return '';
    }

    if (!local_quicknote_is_enabled_for_course($course)) {
        return '';
    }

    $PAGE->requires->js_call_amd('local_quicknote/notes', 'init', [[
        'courseid' => (int) $course->id,
    ]]);

    return $OUTPUT->render_from_template('local_quicknote/sidebar', [
        'courseid' => (int) $course->id,
        'hasquote' => false,
        'quotetext' => '',
        'title' => get_string('sidebar:title', 'local_quicknote'),
        'togglelabel' => get_string('note:toggle', 'local_quicknote'),
        'closelabel' => get_string('note:close', 'local_quicknote'),
        'addlabel' => get_string('note:add', 'local_quicknote'),
        'placeholder' => get_string('note:placeholder', 'local_quicknote'),
        'emptytext' => get_string('note:empty', 'local_quicknote'),
        'savingtext' => get_string('note:saving', 'local_quicknote'),
        'savedtext' => get_string('note:saved', 'local_quicknote'),
        'errortext' => get_string('note:error', 'local_quicknote'),
        'updatedlabel' => get_string('note:updated', 'local_quicknote'),
        'locationlabel' => get_string('note:location', 'local_quicknote'),
        'deleteconfirm' => get_string('note:delete_confirm', 'local_quicknote'),
        'noresultstext' => get_string('search:noresultstext', 'local_quicknote'),
        'highlightlabel' => get_string('select:highlightlabel', 'local_quicknote'),
    ]);
}
