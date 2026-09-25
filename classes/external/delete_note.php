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

        \local_quicknote\util::validate_note_access((int) $note->courseid);

        \local_quicknote\local\screenshot_manager::delete_for_note((int) $note->id);

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
