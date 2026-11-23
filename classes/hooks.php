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
    /** @var bool Whether QuickNote UI is active on the current page. */
    private static bool $isactiveonpage = false;

    /**
     * Checks whether QuickNote UI is active on the current page.
     *
     * @return bool True if active on current page, false otherwise.
     */
    public static function is_active_on_page(): bool {
        return self::$isactiveonpage;
    }

    /**
     * Sets whether QuickNote UI is active on the current page (useful for testing).
     *
     * @param bool $active Active status.
     * @return void
     */
    public static function set_active_on_page(bool $active): void {
        self::$isactiveonpage = $active;
    }

    /**
     * Adds QuickNote settings to the course/module edit form using Hook API.
     *
     * @param \core_course\hook\after_form_definition $hook The hook object.
     */
    public static function course_edit_form(\core_course\hook\after_form_definition $hook) {
        $mform = $hook->mform;

        $mform->addElement('header', 'local_quicknote_header', get_string('pluginname', 'local_quicknote'));

        // Course edit form — per-course on/off.
        $course = method_exists($hook->formwrapper, 'get_course') ? $hook->formwrapper->get_course() : null;
        $courseid = !empty($course->id) ? (int)$course->id : optional_param('id', 0, PARAM_INT);

        $globaldefault = get_config('local_quicknote', 'default_enabled');
        $enabled = ($globaldefault === false || $globaldefault === null) ? 1 : $globaldefault;

        if ($courseid > 0) {
            $settings = \local_quicknote\local\course_settings::get_settings($courseid);
            if ($settings['enabled'] !== null) {
                $enabled = $settings['enabled'];
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
        $data = $hook->get_data();
        if (empty($data->id) || !property_exists($data, 'local_quicknote_enabled')) {
            return;
        }

        $courseid = (int) $data->id;
        $enabled = (int) $data->local_quicknote_enabled;
        \local_quicknote\local\course_settings::set_course_enabled($courseid, $enabled);
    }

    /**
     * Injects the QuickNote UI in the standard top of body HTML.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook The hook object.
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook) {
        $html = self::get_top_of_body_html();
        if ($html !== '') {
            $hook->add_html($html);
        }
    }

    /**
     * Generates the QuickNote UI HTML.
     *
     * @return string
     */
    public static function get_top_of_body_html(): string {
        global $OUTPUT, $PAGE, $USER;

        self::$isactiveonpage = false;

        if (during_initial_install() || (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
            return '';
        }

        $excludedlayouts = ['popup', 'frametop', 'maintenance', 'print', 'secure'];
        if (isset($PAGE->pagelayout) && in_array($PAGE->pagelayout, $excludedlayouts)) {
            return '';
        }

        if (!$PAGE->get_popup_notification_allowed()) {
            return '';
        }

        if (!isloggedin() || isguestuser()) {
            return '';
        }

        if (self::plugin_needs_upgrading()) {
            return '';
        }

        $isdashboard = ($PAGE->pagetype === 'my-index' || $PAGE->pagelayout === 'mydashboard');
        $isfrontpage = ($PAGE->pagetype === 'site-index' || $PAGE->pagelayout === 'frontpage');

        if ($isdashboard || $isfrontpage) {
            if ($isdashboard && !get_config('local_quicknote', 'enable_dashboard')) {
                return '';
            }
            if ($isfrontpage && !get_config('local_quicknote', 'enable_frontpage')) {
                return '';
            }
            $course = get_site();
            $context = \context_system::instance();
            if (!has_capability('local/quicknote:use', $context)) {
                return '';
            }
            $skipatterncheck = true;
        } else {
            if (empty($PAGE->course->id) || $PAGE->course->id === SITEID) {
                return '';
            }

            if (empty($PAGE->context->contextlevel) || !in_array($PAGE->context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE])) {
                return '';
            }

            $course = get_course($PAGE->course->id);

            if ($PAGE->pagelayout === 'embedded') {
                // In H5P Core, inserting JS worked, but it didn't in mod_hvp and SCORM.
                $PAGE->requires->js_call_amd('local_quicknote/notes', 'initIframe', [[
                    'highlightlabel' => get_string('select:highlightlabel', 'local_quicknote'),
                ]]);
                return '';
            }

            $context = \context_course::instance($course->id, IGNORE_MISSING);

            if (!$context) {
                return '';
            }

            if (!has_capability('local/quicknote:use', $context)) {
                return '';
            }

            if (!self::is_enabled_for_course($course)) {
                return '';
            }

            // Check per-module override.
            $skipatterncheck = false;
            if ($PAGE->cm) {
                $modulevalue = \local_quicknote\local\course_settings::get_module_setting(
                    (int) $course->id,
                    (int) $PAGE->cm->id
                );
                if ($modulevalue === 0) {
                    return '';
                }
                if ($modulevalue === 1) {
                    // If explicitly enabled, skip site-wide pattern check.
                    $skipatterncheck = true;
                }
            }
        }

        // Check site-wide page type patterns (unless overridden by an explicit per-module enable).
        if (empty($skipatterncheck) && self::is_pagetype_disabled($PAGE->pagetype)) {
            return '';
        }

        $canuploadscreenshots = (bool) get_config('local_quicknote', 'enable_screenshots') &&
            has_capability('local/quicknote:uploadscreenshot', $context);

        $enablevoicedictation = self::is_voice_dictation_enabled();

        $maxfiles = (int) get_config('local_quicknote', 'max_files_per_note');
        $maxbytes = (int) get_config('local_quicknote', 'max_bytes');
        $rawtitle = trim((string) ($PAGE->title ?: $PAGE->heading));
        $pagetitle = \local_quicknote\util::clean_page_title($rawtitle, $course) ?? '';
        $pageurl = \local_quicknote\util::clean_url($PAGE->url->out(false), true, true) ?? '';
        $pagehash = \local_quicknote\util::calculate_page_hash($pageurl);

        $PAGE->requires->js_call_amd('local_quicknote/notes', 'init', [[
            'courseid' => (int) $course->id,
            'pageurl' => $pageurl,
            'pagehash' => $pagehash,
            'pagetitle' => $pagetitle,
            'enable_screenshots' => $canuploadscreenshots,
            'enable_voice_dictation' => $enablevoicedictation,
            'max_files_per_note' => $maxfiles,
            'max_bytes' => $maxbytes,
        ]]);

        $position = get_config('local_quicknote', 'position');
        if (empty($position)) {
            $position = 'right';
        }
        $positionclass = 'local-quicknote--' . ($position === 'left' ? 'left' : 'right');
        $showfab = ($position !== 'navbar');

        $html = $OUTPUT->render_from_template('local_quicknote/sidebar', [
            'courseid' => (int) $course->id,
            'pageurl' => $pageurl,
            'pagehash' => $pagehash,
            'pagetitle' => $pagetitle,
            'positionclass' => $positionclass,
            'showfab' => $showfab,
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
            'enablescreenshots' => $canuploadscreenshots,
            'enablevoicedictation' => $enablevoicedictation,
            'voicelabel' => get_string('voice:dictate', 'local_quicknote'),
            'voicestoplabel' => get_string('voice:stop', 'local_quicknote'),
            'voicelisteninglabel' => get_string('voice:listening', 'local_quicknote'),
            'voiceendedlabel' => get_string('voice:ended', 'local_quicknote'),
        ]);

        self::$isactiveonpage = true;
        return $html;
    }

    /**
     * Checks whether QuickNote is enabled for the current course.
     *
     * @param \stdClass $course The course object.
     * @return bool
     */
    public static function is_enabled_for_course(\stdClass $course): bool {
        return \local_quicknote\local\course_settings::is_enabled_for_course((int) $course->id);
    }

    /**
     * Checks whether voice dictation is enabled globally.
     *
     * @return bool True if voice dictation is enabled.
     */
    public static function is_voice_dictation_enabled(): bool {
        $enabled = get_config('local_quicknote', 'enable_voice_dictation');
        if ($enabled === false || $enabled === null || $enabled === '') {
            return true;
        }

        return (string) $enabled !== '0';
    }

    /**
     * Checks whether the local_quicknote plugin itself has pending schema upgrades.
     *
     * @return bool True if an upgrade is pending for this plugin.
     */
    public static function plugin_needs_upgrading(): bool {
        $installed = (int) get_config('local_quicknote', 'version');
        if ($installed <= 0) {
            return false;
        }

        $plugin = new \stdClass();
        require(__DIR__ . '/../version.php');
        return ($installed < (int) $plugin->version);
    }

    /**
     * Checks whether a page type matches the disabled page type patterns.
     *
     * @param string $pagetype The page type to check (e.g. $PAGE->pagetype).
     * @param string|null $patterns Optional patterns override for testing.
     * @return bool True if the page type is disabled.
     */
    public static function is_pagetype_disabled(string $pagetype, ?string $patterns = null): bool {
        if ($patterns === null) {
            $patterns = get_config('local_quicknote', 'disabled_pagetypes');
            if ($patterns === false) {
                $patterns = "mod-quiz-attempt\n";
            }
        }

        if (empty($patterns)) {
            return false;
        }

        $patternlines = explode("\n", $patterns);
        foreach ($patternlines as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                continue;
            }
            if (fnmatch($pattern, $pagetype)) {
                return true;
            }
        }

        return false;
    }
}
