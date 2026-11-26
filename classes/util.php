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
        require_login();
        if (isguestuser()) {
            throw new \require_login_exception('Guests cannot use QuickNote');
        }

        if ($courseid == SITEID) {
            if (!get_config('local_quicknote', 'enable_dashboard') && !get_config('local_quicknote', 'enable_frontpage')) {
                throw new \moodle_exception('disabledforcourse', 'local_quicknote');
            }
            $course = get_site();
            $context = \context_system::instance();
            \core_external\external_api::validate_context($context);
            require_capability('local/quicknote:use', $context);

            if ($checkscreenshotcap) {
                require_capability('local/quicknote:uploadscreenshot', $context);
            }
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

    /**
     * Resolves a trusted server-side display label and link URL for a note within a specific course.
     *
     * Ensures that teacher reports do not display untrusted student-controlled page titles or link
     * to arbitrary same-site or external URLs.
     *
     * @param string|null $url The note URL to resolve.
     * @param int $courseid The course ID.
     * @return array Array with keys:
     *               - 'label' (string): Human-readable server-resolved page title or canonical path.
     *               - 'linkurl' (string|null): Validated target URL inside this course, or null if not linkable.
     */
    public static function resolve_course_page_info(?string $url, int $courseid): array {
        global $DB;

        $cleanurl = self::clean_url($url, false, true);
        if ($cleanurl === null || $cleanurl === '') {
            return [
                'label' => get_string('report:untitledpage', 'local_quicknote'),
                'linkurl' => null,
            ];
        }

        $canonical = self::canonicalise_url($cleanurl);
        if ($canonical === '') {
            return [
                'label' => get_string('report:untitledpage', 'local_quicknote'),
                'linkurl' => null,
            ];
        }

        // Extract fragment anchor (e.g. #intro) from clean URL if present.
        $fragment = '';
        $hashpos = strpos($cleanurl, '#');
        if ($hashpos !== false) {
            $fragment = substr($cleanurl, $hashpos + 1);
        }

        $parts = parse_url($canonical);
        $path = $parts['path'] ?? '';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        // 1. Course activity / module URL (e.g. /mod/page/view.php?id=123).
        if (preg_match('#^/mod/([^/]+)/#', $path)) {
            $cmid = (int) ($query['id'] ?? $query['cmid'] ?? 0);
            if ($cmid > 0 && $courseid > 0) {
                try {
                    $modinfo = get_fast_modinfo($courseid);
                    $cm = $modinfo->get_cm($cmid);
                    if ($cm && (int) $cm->course === (int) $courseid) {
                        $label = $cm->get_formatted_name();

                        // Resolve subpage / chapter / page details for known modules.
                        if ($cm->modname === 'book' && !empty($query['chapterid'])) {
                            $chapter = $DB->get_record('book_chapters', [
                                'id' => (int) $query['chapterid'],
                                'bookid' => $cm->instance,
                            ], 'id, title');
                            if ($chapter && !empty($chapter->title)) {
                                $label .= ' > ' . format_string($chapter->title);
                            } else {
                                $label .= ' (' . get_string('report:chapter', 'local_quicknote', (int) $query['chapterid']) . ')';
                            }
                        } else if ($cm->modname === 'lesson' && !empty($query['pageid'])) {
                            $lessonpage = $DB->get_record('lesson_pages', [
                                'id' => (int) $query['pageid'],
                                'lessonid' => $cm->instance,
                            ], 'id, title');
                            if ($lessonpage && !empty($lessonpage->title)) {
                                $label .= ' > ' . format_string($lessonpage->title);
                            } else {
                                $label .= ' (' . get_string('page') . ' ' . (int) $query['pageid'] . ')';
                            }
                        } else if ($cm->modname === 'wiki' && !empty($query['pageid'])) {
                            $wikipage = $DB->get_record('wiki_pages', [
                                'id' => (int) $query['pageid'],
                            ], 'id, title');
                            if ($wikipage && !empty($wikipage->title)) {
                                $label .= ' > ' . format_string($wikipage->title);
                            }
                        } else if ($cm->modname === 'quiz') {
                            if (strpos($path, '/mod/quiz/review.php') !== false) {
                                $label .= ' (' . get_string('review', 'mod_quiz') . ')';
                            } else if (strpos($path, '/mod/quiz/attempt.php') !== false) {
                                $label .= ' (' . get_string('attempt', 'mod_quiz') . ')';
                            }
                        } else if (isset($query['page']) && is_numeric($query['page']) && (int) $query['page'] > 0) {
                            $label .= ' (' . get_string('page') . ' ' . (int) $query['page'] . ')';
                        }

                        if ($fragment !== '') {
                            $label .= ' (#' . s($fragment) . ')';
                        }
                        return [
                            'label' => $label,
                            'linkurl' => $cleanurl,
                        ];
                    }
                } catch (\Exception $e) {
                    $cm = null;
                }
            } else if ($path === '/mod/forum/discuss.php' && !empty($query['d']) && $courseid > 0) {
                $disc = $DB->get_record('forum_discussions', ['id' => (int) $query['d']], 'id, forum, course, name');
                if ($disc && (int) $disc->course === (int) $courseid) {
                    try {
                        $modinfo = get_fast_modinfo($courseid);
                        $cm = $modinfo->instances['forum'][$disc->forum] ?? null;
                        $forumname = $cm ? $cm->get_formatted_name() : get_string('pluginname', 'mod_forum');
                        $label = $forumname . ' > ' . format_string($disc->name);
                        if ($fragment !== '') {
                            $label .= ' (#' . s($fragment) . ')';
                        }
                        return [
                            'label' => $label,
                            'linkurl' => $cleanurl,
                        ];
                    } catch (\Exception $e) {
                        $label = null;
                    }
                }
            }

            // A /mod/ path that cannot be resolved to an active module in this course.
            return [
                'label' => s($path),
                'linkurl' => null,
            ];
        }

        // 2. Course main page or section (e.g. /course/view.php?id=5).
        if ($path === '/course/view.php' || $path === '/course/section.php') {
            $urlcourseid = (int) ($query['id'] ?? 0);
            if ($path === '/course/section.php' && $urlcourseid > 0) {
                $section = $DB->get_record('course_sections', ['id' => $urlcourseid], 'id, course, section, name');
                if ($section && (int) $section->course === (int) $courseid) {
                    $course = $DB->get_record('course', ['id' => $courseid]);
                    $coursename = $course ? format_string($course->fullname) : get_string('course');
                    $sectionname = $section->name ?
                        format_string($section->name) : (get_string('section') . ' ' . $section->section);
                    $label = $coursename . ' (' . $sectionname . ')';
                    if ($fragment !== '') {
                        $label .= ' (#' . s($fragment) . ')';
                    }
                    return [
                        'label' => $label,
                        'linkurl' => $cleanurl,
                    ];
                }
            } else if ($urlcourseid === $courseid || ($urlcourseid === 0 && !empty($query['name']))) {
                $course = $DB->get_record('course', ['id' => $courseid]);
                $coursename = $course ? format_string($course->fullname) : get_string('course');
                $sectionnum = isset($query['section']) ? (int) $query['section'] : null;
                if ($sectionnum !== null && $sectionnum > 0) {
                    try {
                        $modinfo = get_fast_modinfo($courseid);
                        $sectioninfo = $modinfo->get_section_info($sectionnum);
                        $sectionname = ($sectioninfo && $sectioninfo->name) ?
                            format_string($sectioninfo->name) : (get_string('section') . ' ' . $sectionnum);
                    } catch (\Exception $e) {
                        $sectionname = get_string('section') . ' ' . $sectionnum;
                    }
                    $coursename .= ' (' . $sectionname . ')';
                }
                if ($fragment !== '') {
                    $coursename .= ' (#' . s($fragment) . ')';
                }
                return [
                    'label' => $coursename,
                    'linkurl' => $cleanurl,
                ];
            }
        }

        // 3. Known course subpages with id matching $courseid (e.g. /grade/report/..., /user/index.php).
        $firstsegment = explode('/', trim($path, '/'))[0] ?? '';
        if (in_array($firstsegment, ['grade', 'group', 'user', 'badges', 'report', 'analytics', 'enrol'], true)) {
            $urlcourseid = (int) ($query['id'] ?? $query['course'] ?? $query['courseid'] ?? 0);
            if ($urlcourseid === $courseid) {
                $label = s($path);
                if ($fragment !== '') {
                    $label .= ' (#' . s($fragment) . ')';
                }
                return [
                    'label' => $label,
                    'linkurl' => $cleanurl,
                ];
            }
        }

        // 4. Any other URL (e.g. external, other courses, user profile, admin settings).
        // Untrusted student pagetitle is suppressed and link is omitted to prevent content spoofing.
        return [
            'label' => s($path ?: $cleanurl),
            'linkurl' => null,
        ];
    }
}
