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

namespace local_quicknote\external;

use context_course;

/**
 * Retrieve quick notes for the current user and course.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_notes extends \core_external\external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'courseid' => new \core_external\external_value(PARAM_INT, 'Course id.'),
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

        $course = get_course($params['courseid']);
        require_login($course);

        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/quicknote:use', $context);

        if (!\local_quicknote\hooks::is_enabled_for_course($course)) {
            throw new \moodle_exception('disabledforcourse', 'local_quicknote');
        }

        $records = $DB->get_records('local_quicknote_notes', [
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
     * @return \core_external\external_multiple_structure
     */
    public static function execute_returns(): \core_external\external_multiple_structure {
        return new \core_external\external_multiple_structure(
            new \core_external\external_single_structure([
                'id' => new \core_external\external_value(PARAM_INT, 'Note id.'),
                'userid' => new \core_external\external_value(PARAM_INT, 'Owner user id.'),
                'courseid' => new \core_external\external_value(PARAM_INT, 'Course id.'),
                'content' => new \core_external\external_value(PARAM_RAW, 'Note content.'),
                'quote' => new \core_external\external_value(PARAM_RAW, 'Selected quote text.'),
                'hasquote' => new \core_external\external_value(PARAM_BOOL, 'Whether the note contains a quote.'),
                'quotetext' => new \core_external\external_value(PARAM_RAW, 'Quote text safe for template rendering.'),
                'quoteurl' => new \core_external\external_value(PARAM_URL, 'URL pointing to the selected quote.'),
                'url' => new \core_external\external_value(PARAM_URL, 'Last saved page URL.'),
                'timecreated' => new \core_external\external_value(PARAM_INT, 'Creation timestamp.'),
                'timemodified' => new \core_external\external_value(PARAM_INT, 'Last modification timestamp.'),
            ])
        );
    }
}
