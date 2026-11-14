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

/**
 * Class observers for QuickNote.
 *
 * @package    local_quicknote
 */
class observers {
    /**
     * Delete the per-course synthetic config when a course is deleted.
     *
     * @param \core\event\course_deleted $event The event triggered.
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;
        $courseid = $event->objectid;

        // Delete screenshots before deleting notes.
        \local_quicknote\local\screenshot_manager::delete_for_select(
            'courseid = :courseid',
            ['courseid' => $courseid]
        );

        // Remove any notes still attached to this course (covers non-enrolled authors).
        $DB->delete_records('local_quicknote_notes', ['courseid' => $courseid]);

        // Remove the per-course configuration.
        $DB->delete_records('local_quicknote_course', ['courseid' => $courseid]);
    }

    /**
     * Remove per-activity overrides when a course module is deleted.
     *
     * @param \core\event\course_module_deleted $event The event triggered.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;

        $cmid = (int) $event->objectid;
        $courseid = (int) $event->courseid;

        if ($cmid <= 0 || $courseid <= 0) {
            return;
        }

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $courseid]);
        if (!$record || empty($record->module_settings)) {
            return;
        }

        $settings = json_decode($record->module_settings, true);
        if (!is_array($settings)) {
            return;
        }

        $modified = false;
        if (array_key_exists($cmid, $settings)) {
            unset($settings[$cmid]);
            $modified = true;
        }
        if (array_key_exists((string) $cmid, $settings)) {
            unset($settings[(string) $cmid]);
            $modified = true;
        }

        if ($modified) {
            $record->module_settings = !empty($settings) ? json_encode($settings) : '';
            $DB->update_record('local_quicknote_course', $record);
        }
    }

    /**
     * Delete all notes for a user in a course when they are unenrolled.
     *
     * @param \core\event\user_enrolment_deleted $event The event triggered.
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        $ue = $event->other['userenrolment'] ?? [];
        $lastenrol = $ue['lastenrol'] ?? null;
        if ($lastenrol === false) {
            return;
        }
        if ($lastenrol === null && is_enrolled(\context_course::instance($event->courseid), $event->relateduserid)) {
            return;
        }

        $userid = $event->relateduserid;
        $courseid = $event->courseid;

        \local_quicknote\local\screenshot_manager::delete_for_select(
            'userid = :userid AND courseid = :courseid',
            ['userid' => $userid, 'courseid' => $courseid]
        );

        $DB->delete_records('local_quicknote_notes', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
    }

    /**
     * Delete all notes across all courses when a user is fully deleted.
     *
     * @param \core\event\user_deleted $event The event triggered.
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;

        $userid = $event->objectid;

        \local_quicknote\local\screenshot_manager::delete_for_select(
            'userid = :userid',
            ['userid' => $userid]
        );

        $DB->delete_records('local_quicknote_notes', [
            'userid' => $userid,
        ]);
    }
}
