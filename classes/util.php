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

namespace local_quicknote;

/**
 * Utility functions for QuickNote.
 *
 * @package    local_quicknote
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {
    /**
     * Validates access, login and context for a note in a given course or dashboard.
     *
     * @param int $courseid The course ID (SITEID for Dashboard).
     * @param bool $checkscreenshotcap Whether to also check the uploadscreenshot capability.
     * @return \stdClass The validated course record.
     * @throws \moodle_exception
     */
    public static function validate_note_access(int $courseid, bool $checkscreenshotcap = false): \stdClass {
        if ($courseid == SITEID) {
            require_login();
            if (!get_config('local_quicknote', 'enable_dashboard') && !get_config('local_quicknote', 'enable_frontpage')) {
                throw new \moodle_exception('disabledforcourse', 'local_quicknote');
            }
            $course = get_site();
            $context = \context_system::instance();
            \core_external\external_api::validate_context($context);
        } else {
            $course = get_course($courseid);
            require_login($course);

            $context = \context_course::instance($course->id);
            \core_external\external_api::validate_context($context);
            require_capability('local/quicknote:use', $context);

            if ($checkscreenshotcap) {
                require_capability('local/quicknote:uploadscreenshot', $context);
            }

            if (!\local_quicknote\hooks::is_enabled_for_course($course)) {
                throw new \moodle_exception('disabledforcourse', 'local_quicknote');
            }
        }

        return $course;
    }

    /**
     * Sanitizes and validates a URL allowing complex fragments (e.g. H5P/Text fragments)
     * while strictly blocking dangerous schemes like javascript:, data:, etc.
     *
     * @param string|null $url The raw URL to sanitize.
     * @return string|null Clean URL or null if invalid/dangerous.
     */
    public static function clean_url(?string $url): ?string {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Only permit http, https, or relative fragment anchors.
        if (!preg_match('/^(https?:\/\/|#)/i', $url)) {
            return null;
        }

        // Reject control characters or newlines that could bypass protocol checks.
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }

        return $url;
    }
}
