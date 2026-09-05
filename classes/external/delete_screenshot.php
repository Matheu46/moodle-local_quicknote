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

use context_system;
use local_quicknote\local\screenshot_manager;

/**
 * Delete one screenshot attached to an owned note.
 *
 * @package     local_quicknote
 * @copyright   2026 Andreas Giesen
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_screenshot extends \core_external\external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'noteid' => new \core_external\external_value(PARAM_INT, 'Owning note id.'),
            'fileid' => new \core_external\external_value(PARAM_INT, 'Stored file id.'),
        ]);
    }

    /**
     * Delete one screenshot attached to an owned note.
     *
     * @param int $noteid Note id.
     * @param int $fileid File id.
     * @return array
     */
    public static function execute(int $noteid, int $fileid): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['noteid' => $noteid, 'fileid' => $fileid]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/quicknote:use', $context);
        $DB->get_record('local_quicknote_notes', [
            'id' => $params['noteid'],
            'userid' => $USER->id,
        ], 'id', MUST_EXIST);

        return ['fileid' => $params['fileid'], 'deleted' => screenshot_manager::delete_file($params['fileid'], $params['noteid'])];
    }

    /**
     * Define the return structure for execute().
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'fileid' => new \core_external\external_value(PARAM_INT, 'Deleted file id.'),
            'deleted' => new \core_external\external_value(PARAM_BOOL, 'Whether the file was deleted.'),
        ]);
    }
}
