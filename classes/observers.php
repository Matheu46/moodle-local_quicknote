<?php
namespace local_quicknote;

defined('MOODLE_INTERNAL') || die();

class observers {
    /**
     * Save QuickNote configuration when the course is updated.
     */
    public static function course_updated(\core\event\course_updated $event) {
        $courseid = $event->objectid;
        
        $enabled = optional_param('local_quicknote_enabled', null, PARAM_INT);
        
        if ($enabled !== null) {
            set_config('enabled', $enabled, 'local_quicknote_course_' . $courseid);
        }
    }
}