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
 * Plugin upgrade helper functions are defined here.
 *
 * @package     local_quicknote
 * @category    upgrade
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Computes a stable 64-character SHA-256 hash of the canonical page identity for upgrade step 2026101801.
 *
 * This function is an isolated, frozen snapshot of page hashing used during historical upgrades
 * to prevent dependency on runtime plugin class methods.
 *
 * @param string|null $url The page URL.
 * @return string 64-character hex hash, or "" if the URL has no canonical page identity.
 */
function local_quicknote_upgrade_calculate_page_hash(?string $url): string {
    global $CFG;

    if ($url === null) {
        return '';
    }

    $url = trim($url);
    if ($url === '' || !preg_match('/^https?:\/\//i', $url) || preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return '';
    }

    // Strip fragment.
    $hashpos = strpos($url, '#');
    $base = $hashpos !== false ? substr($url, 0, $hashpos) : $url;

    $parts = parse_url($base);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
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
        $path = $matches[1];
    }

    $querystring = '';
    if (isset($parts['query']) && $parts['query'] !== '') {
        $query = [];
        parse_str($parts['query'], $query);
        local_quicknote_upgrade_clean_and_sort_query($query);
        if (!empty($query)) {
            $querystring = '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
    }

    $canonical = $path . $querystring;
    if ($canonical === '') {
        return '';
    }

    return hash('sha256', $canonical);
}

/**
 * Strips volatile query keys and sorts query parameters recursively by key for upgrade hashing.
 *
 * @param array $query Query array parsed via parse_str().
 * @return void
 */
function local_quicknote_upgrade_clean_and_sort_query(array &$query): void {
    static $volatilekeys = ['sesskey', 'local_quicknote_open', 'local_sidenotes_open'];

    foreach (array_keys($query) as $key) {
        $keystr = (string) $key;
        if (in_array($keystr, $volatilekeys, true) || strpos($keystr, '_qf__') === 0) {
            unset($query[$key]);
        } else if (is_array($query[$key])) {
            local_quicknote_upgrade_clean_and_sort_query($query[$key]);
        }
    }
    ksort($query, SORT_STRING);
}
