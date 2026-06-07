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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicknote;

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Class hooks for QuickNote.
 *
 * @package    local_quicknote
 */
class hooks {
    /**
     * Adds QuickNote settings to the course/module edit form using Hook API.
     *
     * @param \core_course\hook\after_form_definition $hook The hook object.
     */
    public static function course_edit_form(\core_course\hook\after_form_definition $hook) {
        $mform = method_exists($hook, 'get_mform') ? $hook->get_mform() : $hook->mform;

        $cmid = optional_param('update', 0, PARAM_INT);

        if ($cmid > 0) {
            // Module forms are handled via coursemodule_standard_elements in lib.php.
            return;
        }

        $mform->addElement('header', 'local_quicknote_header', get_string('pluginname', 'local_quicknote'));

        // Course edit form — per-course on/off.
        $courseid = optional_param('id', 0, PARAM_INT);

        $globaldefault = get_config('local_quicknote', 'default_enabled');
        $enabled = ($globaldefault === false || $globaldefault === null) ? 1 : $globaldefault;

        if ($courseid > 0) {
            $savedvalue = get_config('local_quicknote_course_' . $courseid, 'enabled');
            if ($savedvalue !== false && $savedvalue !== null) {
                $enabled = $savedvalue;
            }
        }

        $mform->addElement('advcheckbox', 'local_quicknote_enabled', get_string('config:active_course', 'local_quicknote'));
        $mform->setDefault('local_quicknote_enabled', (int) $enabled);
    }

    /**
     * Persists QuickNote settings using Hook API.
     *
     * @param \core_course\hook\after_form_submission $hook The hook object.
     */
    public static function course_edit_submission(\core_course\hook\after_form_submission $hook) {
        $cmid = optional_param('update', 0, PARAM_INT);

        if ($cmid > 0) {
            // Module forms are handled via coursemodule_edit_post_actions in lib.php.
            return;
        }

        // Course form — save per-course on/off.
        $courseid = optional_param('id', 0, PARAM_INT);
        $enabled = optional_param('local_quicknote_enabled', 0, PARAM_INT);

        if ($courseid > 0) {
            set_config('enabled', $enabled, 'local_quicknote_course_' . $courseid);
        }
    }

    /**
     * Injects the QuickNote UI in the standard top of body HTML.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook The hook object.
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook) {
        global $OUTPUT, $PAGE, $USER;

        if (during_initial_install() || (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
            return;
        }

        if (!isloggedin() || isguestuser()) {
            return;
        }

        if (empty($PAGE->course->id) || $PAGE->course->id === SITEID) {
            return;
        }

        if (empty($PAGE->context->contextlevel) || !in_array($PAGE->context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE])) {
            return;
        }

        $course = get_course($PAGE->course->id);

        if ($PAGE->pagelayout === 'embedded') {
            // In H5P Core, inserting JS worked, but it didn't in mod_hvp and SCORM.
            $PAGE->requires->js_call_amd('local_quicknote/notes', 'initIframe', [[
                'highlightlabel' => get_string('select:highlightlabel', 'local_quicknote'),
            ]]);
            return;
        }

        $excludedlayouts = ['popup', 'frametop', 'maintenance', 'print'];
        if (in_array($PAGE->pagelayout, $excludedlayouts)) {
            return;
        }

        $context = \context_course::instance($course->id, IGNORE_MISSING);

        if (!$context) {
            return;
        }

        if (!is_enrolled($context) && !has_capability('moodle/course:view', $context)) {
            return;
        }

        if (!self::is_enabled_for_course($course)) {
            return;
        }

        // Check per-module override.
        $skipatterncheck = false;
        if ($PAGE->cm) {
            $modulesettings = get_config('local_quicknote_course_' . $course->id, 'module_settings');
            if ($modulesettings !== false) {
                $modulesettings = json_decode($modulesettings, true);
                if (!is_array($modulesettings)) {
                    $modulesettings = [];
                }
                if (isset($modulesettings[$PAGE->cm->id])) {
                    $modulevalue = $modulesettings[$PAGE->cm->id];
                    if ($modulevalue === 0) {
                        return;
                    }
                    // If explicitly enabled, skip site-wide pattern check.
                    $skipatterncheck = true;
                }
            }
        }

        // Check site-wide page type patterns (unless overridden by an explicit per-module enable).
        if (empty($skipatterncheck)) {
            $disabledpatterns = get_config('local_quicknote', 'disabled_pagetypes');
            if (!empty($disabledpatterns)) {
                $patterns = explode("\n", $disabledpatterns);
                $pagetype = $PAGE->pagetype;
                foreach ($patterns as $pattern) {
                    $pattern = trim($pattern);
                    if ($pattern === '') {
                        continue;
                    }
                    if (fnmatch($pattern, $pagetype)) {
                        return;
                    }
                }
            }
        }

        $PAGE->requires->js_call_amd('local_quicknote/notes', 'init', [[
            'courseid' => (int) $course->id,
        ]]);

        $position = get_config('local_quicknote', 'position');
        if (empty($position)) {
            $position = 'right';
        }
        $positionclass = 'local-quicknote--' . $position;

        $html = $OUTPUT->render_from_template('local_quicknote/sidebar', [
            'courseid' => (int) $course->id,
            'positionclass' => $positionclass,
            'hasquote' => false,
            'quotetext' => '',
            'title' => get_string('sidebar:title', 'local_quicknote'),
            'togglelabel' => get_string('sidebar:toggle', 'local_quicknote'),
            'closelabel' => get_string('sidebar:close', 'local_quicknote'),
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

        $hook->add_html($html);
    }

    /**
     * Checks whether QuickNote is enabled for the current course.
     *
     * @param \stdClass $course The course object.
     * @return bool
     */
    private static function is_enabled_for_course(\stdClass $course): bool {
        $enabled = get_config('local_quicknote_course_' . $course->id, 'enabled');

        if ($enabled === false || $enabled === null || $enabled === '') {
            // Return the default setting defined by the administrator in Site Administration.
            return (bool) get_config('local_quicknote', 'default_enabled');
        }

        return (string) $enabled !== '0';
    }
}
