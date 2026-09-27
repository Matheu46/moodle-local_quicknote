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

use invalid_parameter_exception;

/**
 * Delete multiple quick notes.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_notes extends \core_external\external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'noteids' => new \core_external\external_multiple_structure(
                new \core_external\external_value(PARAM_INT, 'Note id to delete.')
            ),
        ]);
    }

    /**
     * Delete multiple notes owned by the current user.
     *
     * @param array $noteids
     * @return array
     */
    public static function execute(array $noteids): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'noteids' => $noteids,
        ]);

        $ids = $params['noteids'];
        if (empty($ids)) {
            return [
                'deletedids' => [],
                'success' => true,
            ];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'nid');
        $notes = $DB->get_records_select('local_quicknote_notes', "id $insql", $inparams);

        $validids = [];
        foreach ($notes as $note) {
            if ((int) $note->userid === (int) $USER->id) {
                \local_quicknote\util::validate_note_access((int) $note->courseid);
                $validids[] = (int) $note->id;
            }
        }

        if (!empty($validids)) {
            $transaction = $DB->start_delegated_transaction();

            foreach ($validids as $vid) {
                \local_quicknote\local\screenshot_manager::delete_for_note($vid);
            }

            [$vinsql, $vinparams] = $DB->get_in_or_equal($validids, SQL_PARAMS_NAMED, 'vnid');
            $DB->delete_records_select('local_quicknote_notes', "id $vinsql", $vinparams);

            $transaction->allow_commit();
        }

        return [
            'deletedids' => $validids,
            'success' => true,
        ];
    }

    /**
     * Define the return structure for execute().
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'deletedids' => new \core_external\external_multiple_structure(
                new \core_external\external_value(PARAM_INT, 'Deleted note id.')
            ),
            'success' => new \core_external\external_value(PARAM_BOOL, 'Whether the operation was successful.'),
        ]);
    }
}
