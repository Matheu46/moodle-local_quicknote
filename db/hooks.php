<?php
/**
 * Hook callbacks for local_quicknote.
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core_course\hook\after_form_definition::class,
        'callback' => [\local_quicknote\hooks::class, 'course_edit_form'],
    ],
    [
        'hook' => \core_course\hook\after_form_submission::class,
        'callback' => [\local_quicknote\hooks::class, 'course_edit_submission'],
    ],
];