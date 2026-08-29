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
use invalid_parameter_exception;

/**
 * Delete a quick note.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_note extends \core_external\external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'noteid' => new \core_external\external_value(PARAM_INT, 'Note id to delete.'),
        ]);
    }

    /**
     * Delete a note owned by the current user.
     *
     * @param int $noteid
     * @return array
     */
    public static function execute(int $noteid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'noteid' => $noteid,
        ]);

        $note = $DB->get_record('local_quicknote_notes', ['id' => $params['noteid']]);

        if (!$note || (int) $note->userid !== (int) $USER->id) {
            throw new invalid_parameter_exception('Note not found or you do not have permission to delete it.');
        }

        $course = get_course($note->courseid);
        require_login($course);

        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/quicknote:use', $context);

        if (!\local_quicknote\hooks::is_enabled_for_course($course)) {
            throw new \moodle_exception('disabledforcourse', 'local_quicknote');
        }

        $DB->delete_records('local_quicknote_notes', ['id' => $note->id]);

        return [
            'noteid' => (int) $note->id,
            'deleted' => true,
        ];
    }

    /**
     * Define the return structure for execute().
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'noteid' => new \core_external\external_value(PARAM_INT, 'Deleted note id.'),
            'deleted' => new \core_external\external_value(PARAM_BOOL, 'Whether the note was deleted.'),
        ]);
    }
}
