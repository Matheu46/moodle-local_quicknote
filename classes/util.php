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

    /** @var string[] Query parameters that are session-volatile and should be stripped from note URLs. */
    private const VOLATILE_QUERY_KEYS = ['sesskey', 'local_quicknote_open', 'local_sidenotes_open'];

    /**
     * Sanitizes and normalizes a URL allowing complex fragments (e.g. H5P/Text fragments)
     * while strictly blocking dangerous schemes like javascript:, data:, etc., stripping
     * volatile query parameters (e.g. sesskey), and sorting query parameters deterministically.
     *
     * @param string|null $url The raw URL to sanitize.
     * @param bool $rebase Whether to rebase the scheme, host and base path to the current $CFG->wwwroot.
     * @param bool $striptextfragment Whether to strip browser text-fragment directives (#:~:text=...).
     * @return string|null Clean URL or null if invalid/dangerous.
     */
    public static function clean_url(?string $url, bool $rebase = false, bool $striptextfragment = false): ?string {
        global $CFG;

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

        // Split at the first '#' so multi-hash fragments (e.g. H5P + #:~:text=) remain intact.
        $fragment = '';
        $base = $url;
        $hashpos = strpos($url, '#');
        if ($hashpos !== false) {
            $base = substr($url, 0, $hashpos);
            $fragment = substr($url, $hashpos + 1);
        }

        if ($striptextfragment && $fragment !== '') {
            $tfpos = strpos($fragment, ':~:text=');
            if ($tfpos !== false) {
                $fragment = rtrim(substr($fragment, 0, $tfpos), '#&');
            }
        }

        // Fragment-only URL (e.g. "#section2").
        if ($base === '') {
            return $fragment !== '' ? '#' . $fragment : null;
        }

        $parts = parse_url($base);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $querystring = '';
        if (isset($parts['query']) && $parts['query'] !== '') {
            $query = [];
            parse_str($parts['query'], $query);
            self::clean_and_sort_query($query);
            if (!empty($query)) {
                $querystring = '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            }
        }

        if ($rebase && !empty($CFG->wwwroot)) {
            $canonical = self::canonicalise_url($base);
            $rebased = rtrim($CFG->wwwroot, '/') . $canonical;
            return $fragment !== '' ? $rebased . '#' . $fragment : $rebased;
        }

        $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        $path = $parts['path'] ?? '';

        $normalised = $origin . $path . $querystring;
        return $fragment !== '' ? $normalised . '#' . $fragment : $normalised;
    }

    /**
     * Extracts the installation-independent canonical path and sorted query string for a URL.
     *
     * Excludes scheme, host, port, Moodle subdirectory prefix, volatile parameters, and fragments
     * so that notes remain linked to the same logical page across domain or protocol migrations.
     *
     * @param string|null $url The URL to canonicalise.
     * @return string Canonical path + query (e.g. "/mod/page/view.php?id=10"), or "" if invalid.
     */
    public static function canonicalise_url(?string $url): string {
        global $CFG;

        $clean = self::clean_url($url, false, true);
        if ($clean === null || strpos($clean, '#') === 0) {
            return '';
        }

        $hashpos = strpos($clean, '#');
        if ($hashpos !== false) {
            $clean = substr($clean, 0, $hashpos);
        }

        $parts = parse_url($clean);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        // Strip current $CFG->wwwroot subdirectory prefix if present.
        $basepath = !empty($CFG->wwwroot) ? rtrim((string) parse_url($CFG->wwwroot, PHP_URL_PATH), '/') : '';
        if ($basepath !== '' && ($path === $basepath || strpos($path, $basepath . '/') === 0)) {
            $path = substr($path, strlen($basepath));
            if ($path === '' || $path === false) {
                $path = '/';
            }
        } else if (
            preg_match(
                '~^/[^/]+(/(?:mod|course|my|local|blocks|user|grade|calendar|badges|competency|enrol|group|'
                . 'message|notes|question|report|tag|admin|cohort|contentbank|files|filter|h5p|login|payment|'
                . 'portfolio|privacy|rating|repository|rss|search|theme|webservice)(?:/.*|$)|/index\.php$)~i',
                $path,
                $matches
            )
        ) {
            // Strip legacy subdirectory prefix if the site previously lived under another subdir (e.g. /moodle/mod/...).
            $path = $matches[1];
        }

        $querystring = '';
        if (isset($parts['query']) && $parts['query'] !== '') {
            $query = [];
            parse_str($parts['query'], $query);
            self::clean_and_sort_query($query);
            if (!empty($query)) {
                $querystring = '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            }
        }

        return $path . $querystring;
    }

    /**
     * Computes a stable 64-character SHA-256 hash of the canonical page identity.
     *
     * @param string|null $url The page URL.
     * @return string 64-character hex hash, or "" if the URL has no canonical page identity.
     */
    public static function calculate_page_hash(?string $url): string {
        $canonical = self::canonicalise_url($url);
        if ($canonical === '') {
            return '';
        }
        return hash('sha256', $canonical);
    }

    /**
     * Strips volatile query keys and sorts query parameters recursively by key.
     *
     * @param array $query Query array parsed via parse_str().
     * @return void
     */
    private static function clean_and_sort_query(array &$query): void {
        foreach (array_keys($query) as $key) {
            $keystr = (string) $key;
            if (in_array($keystr, self::VOLATILE_QUERY_KEYS, true) || strpos($keystr, '_qf__') === 0) {
                unset($query[$key]);
            }
        }
        ksort($query);
        foreach ($query as &$value) {
            if (is_array($value)) {
                self::clean_and_sort_query($value);
            }
        }
    }

    /**
     * Sanitizes and normalizes a page title by removing redundant site suffixes and course prefixes.
     *
     * @param string|null $title The raw page title.
     * @param \stdClass|null $course Optional course object to strip leading course prefix.
     * @return string|null Clean page title or null if empty.
     */
    public static function clean_page_title(?string $title, ?\stdClass $course = null): ?string {
        if ($title === null) {
            return null;
        }

        $title = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $title);
        $title = trim(preg_replace('/\s+/u', ' ', (string) $title));
        if ($title === '') {
            return null;
        }

        // Remove trailing site name suffix (e.g. " | Site Name").
        $site = get_site();
        if ($site) {
            foreach ([$site->fullname ?? '', $site->shortname ?? ''] as $sitename) {
                $sitename = trim((string) $sitename);
                if ($sitename !== '') {
                    $stripped = preg_replace('/\s*\|\s*' . preg_quote($sitename, '/') . '\s*$/iu', '', $title);
                    if ($stripped !== null && trim($stripped) !== '') {
                        $title = trim($stripped);
                    }
                }
            }
        }

        // Remove leading course shortname/fullname prefix (e.g. "PHYS101: Lesson 1").
        if ($course && !empty($course->id) && $course->id != SITEID) {
            foreach ([$course->shortname ?? '', $course->fullname ?? '', get_string('course')] as $prefix) {
                $prefix = trim((string) $prefix);
                if ($prefix !== '') {
                    $stripped = preg_replace('/^' . preg_quote($prefix, '/') . '\s*:\s*/iu', '', $title);
                    if ($stripped !== null && trim($stripped) !== '') {
                        $title = trim($stripped);
                    }
                }
            }
        }

        $title = \core_text::substr($title, 0, 255);
        return $title !== '' ? $title : null;
    }
}
