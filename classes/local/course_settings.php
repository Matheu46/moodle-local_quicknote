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

namespace local_quicknote\local;

use cache;
use stdClass;

/**
 * Course and module settings manager with MUC application caching for QuickNote.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_settings {
    /** @var string Cache component name. */
    private const CACHE_COMPONENT = 'local_quicknote';

    /** @var string Cache area name. */
    private const CACHE_AREA = 'course_settings';

    /**
     * Get the MUC cache instance.
     *
     * @return \core_cache\cache|\cache_application The cache instance.
     */
    private static function get_cache(): \core_cache\cache|\cache_application {
        return cache::make(self::CACHE_COMPONENT, self::CACHE_AREA);
    }

    /**
     * Retrieve the cached settings array for a course, querying DB on cache miss.
     *
     * @param int $courseid The course ID.
     * @return array{enabled: ?int, module_settings: array<string|int, int>} Course settings array.
     */
    public static function get_settings(int $courseid): array {
        global $DB;

        if ($courseid <= 0) {
            return [
                'enabled' => null,
                'module_settings' => [],
            ];
        }

        $cache = self::get_cache();
        $cached = $cache->get($courseid);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $courseid], 'enabled, module_settings');
        if ($record) {
            $enabled = ($record->enabled !== null && $record->enabled !== '') ? (int) $record->enabled : null;
            $modules = [];
            if (!empty($record->module_settings)) {
                $decoded = json_decode($record->module_settings, true);
                if (is_array($decoded)) {
                    $modules = $decoded;
                }
            }
            $data = [
                'enabled' => $enabled,
                'module_settings' => $modules,
            ];
        } else {
            // Negative cache hit for courses without a custom record.
            $data = [
                'enabled' => null,
                'module_settings' => [],
            ];
        }

        $cache->set($courseid, $data);
        return $data;
    }

    /**
     * Check whether QuickNote is enabled for a given course.
     *
     * @param int $courseid The course ID.
     * @return bool True if enabled, false otherwise.
     */
    public static function is_enabled_for_course(int $courseid): bool {
        if ($courseid <= 0) {
            return false;
        }

        $settings = self::get_settings($courseid);
        $enabled = $settings['enabled'];

        if ($enabled === null) {
            return (bool) get_config('local_quicknote', 'default_enabled');
        }

        return $enabled !== 0;
    }

    /**
     * Get module-level override setting for an activity within a course.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @return int|null 1 (enabled), 0 (disabled), or null (inherit default).
     */
    public static function get_module_setting(int $courseid, int $cmid): ?int {
        if ($courseid <= 0 || $cmid <= 0) {
            return null;
        }

        $settings = self::get_settings($courseid);
        if (isset($settings['module_settings'][$cmid])) {
            return (int) $settings['module_settings'][$cmid];
        }
        if (isset($settings['module_settings'][(string) $cmid])) {
            return (int) $settings['module_settings'][(string) $cmid];
        }

        return null;
    }

    /**
     * Set and persist course-level enablement, invalidating cache.
     *
     * @param int $courseid The course ID.
     * @param int|null $enabled 1 (enabled), 0 (disabled), or null (inherit).
     * @return void
     */
    public static function set_course_enabled(int $courseid, ?int $enabled): void {
        global $DB;

        if ($courseid <= 0) {
            return;
        }

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $courseid]);
        if ($record) {
            $record->enabled = $enabled;
            $DB->update_record('local_quicknote_course', $record);
        } else {
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->enabled = $enabled;
            $record->module_settings = '';
            $DB->insert_record('local_quicknote_course', $record);
        }

        self::purge_course_cache($courseid);
    }

    /**
     * Set and persist module-level override, invalidating cache.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @param int|null $value 1 (enabled), 0 (disabled), or null to remove override.
     * @return void
     */
    public static function set_module_setting(int $courseid, int $cmid, ?int $value): void {
        global $DB;

        if ($courseid <= 0 || $cmid <= 0) {
            return;
        }

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $courseid]);
        $settingsjson = $record ? $record->module_settings : null;
        $settings = $settingsjson ? json_decode($settingsjson, true) : [];
        $settings = is_array($settings) ? $settings : [];

        if ($value === null) {
            unset($settings[$cmid], $settings[(string) $cmid]);
        } else {
            $settings[$cmid] = $value;
        }

        $json = json_encode($settings);
        if ($record) {
            $record->module_settings = $json;
            $DB->update_record('local_quicknote_course', $record);
        } else {
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->enabled = null;
            $record->module_settings = $json;
            $DB->insert_record('local_quicknote_course', $record);
        }

        self::purge_course_cache($courseid);
    }

    /**
     * Remove module override when an activity is deleted.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @return bool True if a setting was removed, false otherwise.
     */
    public static function delete_module_setting(int $courseid, int $cmid): bool {
        global $DB;

        if ($courseid <= 0 || $cmid <= 0) {
            return false;
        }

        $record = $DB->get_record('local_quicknote_course', ['courseid' => $courseid]);
        if (!$record || empty($record->module_settings)) {
            return false;
        }

        $settings = json_decode($record->module_settings, true);
        if (!is_array($settings)) {
            return false;
        }

        $modified = false;
        if (array_key_exists($cmid, $settings)) {
            unset($settings[$cmid]);
            $modified = true;
        }
        if (array_key_exists((string) $cmid, $settings)) {
            unset($settings[(string) $cmid]);
            $modified = true;
        }

        if ($modified) {
            $record->module_settings = json_encode($settings);
            $DB->update_record('local_quicknote_course', $record);
            self::purge_course_cache($courseid);
            return true;
        }

        return false;
    }

    /**
     * Invalidate the cached settings for a single course.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public static function purge_course_cache(int $courseid): void {
        if ($courseid > 0) {
            self::get_cache()->delete($courseid);
        }
    }

    /**
     * Purge all course settings from cache.
     *
     * @return void
     */
    public static function purge_all(): void {
        self::get_cache()->purge();
    }
}
