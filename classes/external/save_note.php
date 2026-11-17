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

use context_course;
use core_text;
use invalid_parameter_exception;
use local_quicknote\local\screenshot_manager;

/**
 * Save a quick note.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_note extends \core_external\external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'id' => new \core_external\external_value(PARAM_INT, 'Note id, 0 for new.', VALUE_DEFAULT, 0),
            'courseid' => new \core_external\external_value(PARAM_INT, 'Course id.'),
            'content' => new \core_external\external_value(PARAM_RAW, 'Note content.', VALUE_DEFAULT, ''),
            'url' => new \core_external\external_value(PARAM_RAW, 'Current page URL.'),
            'quote' => new \core_external\external_value(PARAM_RAW, 'Selected quote text.', VALUE_DEFAULT, null),
            'quoteurl' => new \core_external\external_value(PARAM_RAW, 'URL pointing to the selected quote.', VALUE_DEFAULT, null),
            'pagetitle' => new \core_external\external_value(PARAM_TEXT, 'Current page title.', VALUE_DEFAULT, null),
            'remindertime' => new \core_external\external_value(
                PARAM_INT,
                'Reminder timestamp, 0 or null to remove.',
                VALUE_DEFAULT,
                null
            ),
            'remindertimestr' => new \core_external\external_value(
                PARAM_TEXT,
                'Reminder ISO string (YYYY-MM-DDTHH:mm), empty to remove.',
                VALUE_DEFAULT,
                null
            ),
        ]);
    }

    /**
     * Create or update a note.
     *
     * @param int $id
     * @param int $courseid
     * @param string $content
     * @param string $url
     * @param string|null $quote
     * @param string|null $quoteurl
     * @param string|null $pagetitle
     * @param int|null $remindertime
     * @param string|null $remindertimestr
     * @return array
     */
    public static function execute(
        int $id,
        int $courseid,
        string $content,
        string $url,
        ?string $quote = null,
        ?string $quoteurl = null,
        ?string $pagetitle = null,
        ?int $remindertime = null,
        ?string $remindertimestr = null
    ): array {
        global $DB, $USER;

        $input = [
            'id' => $id,
            'courseid' => $courseid,
            'content' => $content,
            'url' => $url,
        ];

        if ($quote !== null) {
            $input['quote'] = $quote;
        }

        if ($quoteurl !== null) {
            $input['quoteurl'] = $quoteurl;
        }

        if ($pagetitle !== null) {
            $input['pagetitle'] = $pagetitle;
        }

        if ($remindertime !== null) {
            $input['remindertime'] = $remindertime;
        }

        if ($remindertimestr !== null) {
            $input['remindertimestr'] = $remindertimestr;
        }

        $params = self::validate_parameters(self::execute_parameters(), $input);

        $course = \local_quicknote\util::validate_note_access((int) $params['courseid']);

        $cleanurl = \local_quicknote\util::clean_url($params['url'], true, true);
        if ($cleanurl === null && trim((string)$params['url']) !== '') {
            throw new \invalid_parameter_exception('Invalid URL scheme provided.');
        }

        $now = time();
        $record = (object) [
            'userid' => $USER->id,
            'courseid' => $course->id,
            'content' => core_text::substr($params['content'], 0, 20000),
            'url' => core_text::substr($cleanurl ?? '', 0, 255),
            'pagehash' => \local_quicknote\util::calculate_page_hash($cleanurl),
            'timemodified' => $now,
        ];

        if (array_key_exists('quote', $params) && $params['quote'] !== null) {
            $record->quote = core_text::substr($params['quote'], 0, 5000);
        }

        if (array_key_exists('quoteurl', $params) && $params['quoteurl'] !== null) {
            $cleanquoteurl = \local_quicknote\util::clean_url($params['quoteurl'], true, false);
            $record->quoteurl = $cleanquoteurl !== null ? core_text::substr($cleanquoteurl, 0, 1024) : null;
        }

        if (array_key_exists('pagetitle', $params) && $params['pagetitle'] !== null) {
            $record->pagetitle = \local_quicknote\util::clean_page_title($params['pagetitle'], $course);
        }

        $enablereminders = (bool) get_config('local_quicknote', 'enable_calendar_reminders');

        // Resolve reminder timestamp.
        $remindervalue = null;
        $reminderprovided = false;

        if (array_key_exists('remindertimestr', $params) && $params['remindertimestr'] !== null) {
            $reminderprovided = true;
            $trimmed = trim($params['remindertimestr']);
            if ($trimmed !== '') {
                $remindervalue = \local_quicknote\util::parse_user_iso_datetime($trimmed);
            } else {
                $remindervalue = 0;
            }
        } else if (array_key_exists('remindertime', $params) && $params['remindertime'] !== null) {
            $reminderprovided = true;
            $remindervalue = (int) $params['remindertime'];
        }

        if (!empty($params['id'])) {
            $existing = $DB->get_record('local_quicknote_notes', [
                'id' => $params['id'],
                'userid' => $USER->id,
            ], '*', MUST_EXIST);

            if ((int) $existing->courseid !== (int) $course->id) {
                throw new invalid_parameter_exception('The note does not belong to the provided course.');
            }

            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;

            if ($record->pagehash === '' && !empty($existing->pagehash)) {
                $record->pagehash = $existing->pagehash;
            }

            if (!property_exists($record, 'quote')) {
                $record->quote = $existing->quote ?? null;
            }

            if (!property_exists($record, 'quoteurl')) {
                $record->quoteurl = $existing->quoteurl ?? null;
            }

            if (!property_exists($record, 'pagetitle') || $record->pagetitle === null) {
                $record->pagetitle = $existing->pagetitle ?? null;
            }

            $record->eventid = $existing->eventid ?? null;
            $record->remindertime = $existing->remindertime ?? null;

            if ($enablereminders && $reminderprovided) {
                global $CFG;
                require_once($CFG->dirroot . '/calendar/lib.php');

                if ($remindervalue !== null && $remindervalue > 0) {
                    $eventdata = new \stdClass();
                    $eventname = get_string('pluginname', 'local_quicknote');
                    $cleancontent = trim(strip_tags($record->content));
                    if ($cleancontent !== '') {
                        $eventname .= ': ' . core_text::substr($cleancontent, 0, 50);
                    }
                    $eventdata->name = $eventname;
                    $description = format_text($record->content, FORMAT_PLAIN);
                    if (!empty($record->url)) {
                        $description .= '<br><br><a href="' . s($record->url) . '">' .
                            get_string('note:viewintext', 'local_quicknote') . '</a>';
                    }
                    $eventdata->description = $description;
                    $eventdata->format = FORMAT_HTML;
                    $eventdata->eventtype = 'user';
                    $eventdata->timestart = $remindervalue;
                    $eventdata->timeduration = 0;
                    $eventdata->userid = $USER->id;
                    $eventdata->courseid = 0;
                    $eventdata->repeatid = 0;

                    if (!empty($record->eventid) && $DB->record_exists('event', ['id' => $record->eventid])) {
                        $event = \calendar_event::load($record->eventid);
                        $event->update($eventdata, false);
                    } else {
                        $event = \calendar_event::create($eventdata, false);
                        $record->eventid = $event->id;
                    }
                    $record->remindertime = $remindervalue;
                } else if (!empty($record->eventid)) {
                    if ($DB->record_exists('event', ['id' => $record->eventid])) {
                        $event = \calendar_event::load($record->eventid);
                        $event->delete();
                    }
                    $record->eventid = null;
                    $record->remindertime = null;
                } else {
                    $record->eventid = null;
                    $record->remindertime = null;
                }
            }

            $DB->update_record('local_quicknote_notes', $record);
            $saved = $DB->get_record('local_quicknote_notes', ['id' => $record->id], '*', MUST_EXIST);
        } else {
            $record->timecreated = $now;
            $record->quote = $record->quote ?? null;
            $record->quoteurl = $record->quoteurl ?? null;
            $record->pagetitle = $record->pagetitle ?? null;
            $record->eventid = null;
            $record->remindertime = null;

            if ($enablereminders && $reminderprovided) {
                if ($remindervalue !== null && $remindervalue > 0) {
                    global $CFG;
                    require_once($CFG->dirroot . '/calendar/lib.php');

                    $eventdata = new \stdClass();
                    $eventname = get_string('pluginname', 'local_quicknote');
                    $cleancontent = trim(strip_tags($record->content));
                    if ($cleancontent !== '') {
                        $eventname .= ': ' . core_text::substr($cleancontent, 0, 50);
                    }
                    $eventdata->name = $eventname;
                    $description = format_text($record->content, FORMAT_PLAIN);
                    if (!empty($record->url)) {
                        $description .= '<br><br><a href="' . s($record->url) . '">' .
                            get_string('note:viewintext', 'local_quicknote') . '</a>';
                    }
                    $eventdata->description = $description;
                    $eventdata->format = FORMAT_HTML;
                    $eventdata->eventtype = 'user';
                    $eventdata->timestart = $remindervalue;
                    $eventdata->timeduration = 0;
                    $eventdata->userid = $USER->id;
                    $eventdata->courseid = 0;
                    $eventdata->repeatid = 0;

                    $event = \calendar_event::create($eventdata, false);
                    $record->eventid = $event->id;
                    $record->remindertime = $remindervalue;
                }
            }

            $record->id = $DB->insert_record('local_quicknote_notes', $record);
            $saved = $DB->get_record('local_quicknote_notes', ['id' => $record->id], '*', MUST_EXIST);
        }

        return self::export_note($saved);
    }

    /**
     * Define the return structure for execute().
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return self::note_structure();
    }

    /**
     * External structure for a single note.
     *
     * @return \core_external\external_single_structure
     */
    public static function note_structure(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'id' => new \core_external\external_value(PARAM_INT, 'Note id.'),
            'userid' => new \core_external\external_value(PARAM_INT, 'Owner user id.'),
            'courseid' => new \core_external\external_value(PARAM_INT, 'Course id.'),
            'content' => new \core_external\external_value(PARAM_RAW, 'Note content.'),
            'quote' => new \core_external\external_value(PARAM_RAW, 'Selected quote text.'),
            'hasquote' => new \core_external\external_value(PARAM_BOOL, 'Whether the note contains a quote.'),
            'quotetext' => new \core_external\external_value(PARAM_RAW, 'Quote text safe for template rendering.'),
            'quoteurl' => new \core_external\external_value(PARAM_RAW, 'URL pointing to the selected quote.'),
            'url' => new \core_external\external_value(PARAM_RAW, 'Last saved page URL.'),
            'pagehash' => new \core_external\external_value(PARAM_ALPHANUM, 'Canonical source page SHA-256 hash.'),
            'pagetitle' => new \core_external\external_value(PARAM_TEXT, 'Source page title.'),
            'screenshots' => new \core_external\external_multiple_structure(
                screenshot_manager::external_structure()
            ),
            'eventid' => new \core_external\external_value(PARAM_INT, 'Calendar event id.', VALUE_OPTIONAL),
            'remindertime' => new \core_external\external_value(PARAM_INT, 'Reminder timestamp.', VALUE_OPTIONAL),
            'remindertimeiso' => new \core_external\external_value(
                PARAM_TEXT,
                'Reminder ISO string in user timezone.',
                VALUE_OPTIONAL
            ),
            'remindertimeformatted' => new \core_external\external_value(
                PARAM_TEXT,
                'Reminder human formatted string in user timezone.',
                VALUE_OPTIONAL
            ),
            'timecreated' => new \core_external\external_value(PARAM_INT, 'Creation timestamp.'),
            'timemodified' => new \core_external\external_value(PARAM_INT, 'Last modification timestamp.'),
        ]);
    }

    /**
     * Convert a DB record into an external structure.
     *
     * @param \stdClass $note
     * @param array|null $screenshots Pre-loaded screenshots. If null, they will be fetched.
     * @return array
     */
    public static function export_note(\stdClass $note, ?array $screenshots = null): array {
        $quote = (string) ($note->quote ?? '');
        $pagehash = !empty($note->pagehash)
            ? (string) $note->pagehash
            : \local_quicknote\util::calculate_page_hash($note->url ?? null);

        $remindertime = !empty($note->remindertime) ? (int) $note->remindertime : null;
        $remindertimeiso = $remindertime ? \local_quicknote\util::format_user_iso_datetime($remindertime) : null;
        $remindertimeformatted = $remindertime ?
            userdate($remindertime, get_string('strftimedatetimeshort', 'langconfig')) :
            null;

        return [
            'id' => (int) $note->id,
            'userid' => (int) $note->userid,
            'courseid' => (int) $note->courseid,
            'content' => (string) ($note->content ?? ''),
            'quote' => $quote,
            'hasquote' => trim($quote) !== '',
            'quotetext' => $quote,
            'quoteurl' => \local_quicknote\util::clean_url($note->quoteurl ?? null, true, false) ?? '',
            'url' => \local_quicknote\util::clean_url($note->url ?? null, true, true) ?? '',
            'pagehash' => $pagehash,
            'pagetitle' => (string) ($note->pagetitle ?? ''),
            'screenshots' => $screenshots ?? screenshot_manager::get_for_note((int) $note->id),
            'eventid' => !empty($note->eventid) ? (int) $note->eventid : null,
            'remindertime' => $remindertime,
            'remindertimeiso' => $remindertimeiso,
            'remindertimeformatted' => $remindertimeformatted,
            'timecreated' => (int) $note->timecreated,
            'timemodified' => (int) $note->timemodified,
        ];
    }
}
