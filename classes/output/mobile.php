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

namespace local_quicknote\output;

/**
 * QuickNote Mobile output class
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the template, javascript and data for the mobile course view.
     *
     * @param array $args Arguments passed by the mobile app.
     * @return array Data for the mobile app view.
     */
    public static function mobile_course_view($args) {
        global $OUTPUT;

        $courseid = $args['courseid'] ?? 0;

        $js = file_get_contents(__DIR__ . '/mobile.js');
        $js = str_replace('%%DELETE_CONFIRM%%', get_string('note:delete_confirm', 'local_quicknote'), $js);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('local_quicknote/mobile_view', []),
                ],
            ],
            'javascript' => $js,
            'otherdata' => [
                'courseid' => $courseid,
            ],
        ];
    }
}
