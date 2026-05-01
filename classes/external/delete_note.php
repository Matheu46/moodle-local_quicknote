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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use context_course;
use invalid_parameter_exception;

/**
 * Delete a quick note.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_note extends \external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'noteid' => new \external_value(PARAM_INT, 'Note id to delete.'),
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

        require_sesskey();

        $note = $DB->get_record('local_quicknotes', ['id' => $params['noteid']], '*', MUST_EXIST);

        if ((int) $note->userid !== (int) $USER->id) {
            throw new invalid_parameter_exception('You can only delete your own notes.');
        }

        $course = get_course($note->courseid);
        require_login($course);

        $context = context_course::instance($course->id);
        self::validate_context($context);

        $DB->delete_records('local_quicknotes', ['id' => $note->id]);

        return [
            'noteid' => (int) $note->id,
            'deleted' => true,
        ];
    }

    /**
     * Define the return structure for execute().
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'noteid' => new \external_value(PARAM_INT, 'Deleted note id.'),
            'deleted' => new \external_value(PARAM_BOOL, 'Whether the note was deleted.'),
        ]);
    }
}
