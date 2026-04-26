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

namespace local_quicknote\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use context_course;

/**
 * Retrieve quick notes for the current user and course.
 *
 * @package     local_quicknote
 * @copyright   2026 IFRN
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_notes extends \external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course id.'),
        ]);
    }

    /**
     * Return all notes belonging to the current user in the given course.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        require_sesskey();

        $course = get_course($params['courseid']);
        require_login($course);

        $context = context_course::instance($course->id);
        self::validate_context($context);

        $records = $DB->get_records('local_quicknotes', [
            'userid' => $USER->id,
            'courseid' => $course->id,
        ], 'timemodified DESC, id DESC');

        $notes = [];
        foreach ($records as $record) {
            $notes[] = save_note::export_note($record);
        }

        return $notes;
    }

    /**
     * Define the return structure for execute().
     *
     * @return \external_multiple_structure
     */
    public static function execute_returns(): \external_multiple_structure {
        return new \external_multiple_structure(
            new \external_single_structure([
                'id' => new \external_value(PARAM_INT, 'Note id.'),
                'userid' => new \external_value(PARAM_INT, 'Owner user id.'),
                'courseid' => new \external_value(PARAM_INT, 'Course id.'),
                'content' => new \external_value(PARAM_RAW, 'Note content.'),
                'quote' => new \external_value(PARAM_RAW, 'Selected quote text.'),
                'quoteurl' => new \external_value(PARAM_RAW, 'URL pointing to the selected quote.'),
                'url' => new \external_value(PARAM_RAW_TRIMMED, 'Last saved page URL.'),
                'timecreated' => new \external_value(PARAM_INT, 'Creation timestamp.'),
                'timemodified' => new \external_value(PARAM_INT, 'Last modification timestamp.'),
            ])
        );
    }
}
