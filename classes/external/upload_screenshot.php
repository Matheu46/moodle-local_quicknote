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
 * Attach a pasted screenshot to an owned note.
 *
 * @package     local_quicknote
 * @copyright   2026 Andreas Giesen
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_screenshot extends \core_external\external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'noteid' => new \core_external\external_value(PARAM_INT, 'Owning note id.'),
            'filename' => new \core_external\external_value(PARAM_FILE, 'Original screenshot filename.'),
            'mimetype' => new \core_external\external_value(PARAM_RAW_TRIMMED, 'Screenshot MIME type.'),
            'data' => new \core_external\external_value(PARAM_RAW, 'Base64 screenshot content.'),
        ]);
    }

    /**
     * Attach a pasted screenshot to an owned note.
     *
     * @param int $noteid Owning note id.
     * @param string $filename Original screenshot filename.
     * @param string $mimetype Screenshot MIME type.
     * @param string $data Base64 screenshot content.
     * @return array
     */
    public static function execute(int $noteid, string $filename, string $mimetype, string $data): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'noteid' => $noteid,
            'filename' => $filename,
            'mimetype' => $mimetype,
            'data' => $data,
        ]);

        require_login();

        $note = $DB->get_record('local_quicknote_notes', [
            'id' => $params['noteid'],
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        $course = get_course($note->courseid);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/quicknote:use', $context);
        require_capability('local/quicknote:uploadscreenshot', $context);

        if (!\local_quicknote\hooks::is_enabled_for_course($course)) {
            throw new \moodle_exception('disabledforcourse', 'local_quicknote');
        }

        if (!get_config('local_quicknote', 'enable_screenshots')) {
            throw new \moodle_exception('screenshot:disabled', 'local_quicknote');
        }

        return screenshot_manager::create($note, $params['filename'], $params['mimetype'], $params['data']);
    }

    /**
     * Define the return structure for execute().
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return screenshot_manager::external_structure();
    }
}
