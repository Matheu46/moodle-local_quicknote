<?php
namespace local_quicknote;

defined('MOODLE_INTERNAL') || die();



class hooks {
    /**
     * Adds QuickNote settings to the course edit form using Hook API.
     */
    public static function course_edit_form(\core_course\hook\after_form_definition $hook) {
        $mform = method_exists($hook, 'get_mform') ? $hook->get_mform() : $hook->mform;

        $courseid = optional_param('id', 0, PARAM_INT);

        $globaldefault = get_config('local_quicknote', 'default_enabled');
        $enabled = ($globaldefault === false || $globaldefault === null) ? 1 : $globaldefault;

        if ($courseid > 0) {
            $savedvalue = get_config('local_quicknote_course_' . $courseid, 'enabled');
            if ($savedvalue !== false && $savedvalue !== null) {
                $enabled = $savedvalue;
            }
        }

        // Adds a new section and checkbox at the end of the settings page
        $mform->addElement('header', 'local_quicknote_header', get_string('pluginname', 'local_quicknote'));
        $mform->addElement('advcheckbox', 'local_quicknote_enabled', get_string('config:active_course', 'local_quicknote'));

        $mform->setDefault('local_quicknote_enabled', (int) $enabled);
    }

    /**
     * Persists QuickNote settings using Hook API.
     */
    public static function course_edit_submission(\core_course\hook\after_form_submission $hook) {
        $courseid = optional_param('id', 0, PARAM_INT);
        $enabled = optional_param('local_quicknote_enabled', 0, PARAM_INT);

        if ($courseid > 0) {
            set_config('enabled', $enabled, 'local_quicknote_course_' . $courseid);
        }
    }
}