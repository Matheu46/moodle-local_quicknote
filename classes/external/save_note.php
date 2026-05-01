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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use context_course;
use core_text;
use invalid_parameter_exception;

/**
 * Save a quick note.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_note extends \external_api {
    /**
     * Define the parameters for execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'id' => new \external_value(PARAM_INT, 'Existing note id, or 0 to create a new note.', VALUE_DEFAULT, 0),
            'courseid' => new \external_value(PARAM_INT, 'Course id.'),
            'content' => new \external_value(PARAM_RAW, 'Note content.'),
            'url' => new \external_value(PARAM_RAW_TRIMMED, 'Current page URL.'),
            'quote' => new \external_value(PARAM_RAW, 'Selected quote text.', VALUE_OPTIONAL),
            'quoteurl' => new \external_value(PARAM_RAW, 'URL pointing to the selected quote.', VALUE_OPTIONAL),
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
     * @return array
     */
    public static function execute(
        int $id,
        int $courseid,
        string $content,
        string $url,
        ?string $quote = null,
        ?string $quoteurl = null
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

        $params = self::validate_parameters(self::execute_parameters(), $input);

        require_sesskey();

        $course = get_course($params['courseid']);
        require_login($course);

        $context = context_course::instance($course->id);
        self::validate_context($context);

        $now = time();
        $record = (object) [
            'userid' => $USER->id,
            'courseid' => $course->id,
            'content' => $params['content'],
            'url' => core_text::substr($params['url'], 0, 255),
            'timemodified' => $now,
        ];

        if (array_key_exists('quote', $params)) {
            $record->quote = $params['quote'];
        }

        if (array_key_exists('quoteurl', $params)) {
            $record->quoteurl = $params['quoteurl'];
        }

        if (!empty($params['id'])) {
            $existing = $DB->get_record('local_quicknotes', [
                'id' => $params['id'],
                'userid' => $USER->id,
            ], '*', MUST_EXIST);

            if ((int) $existing->courseid !== (int) $course->id) {
                throw new invalid_parameter_exception('The note does not belong to the provided course.');
            }

            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;

            if (!property_exists($record, 'quote')) {
                $record->quote = $existing->quote ?? null;
            }

            if (!property_exists($record, 'quoteurl')) {
                $record->quoteurl = $existing->quoteurl ?? null;
            }

            $DB->update_record('local_quicknotes', $record);
            $saved = $DB->get_record('local_quicknotes', ['id' => $record->id], '*', MUST_EXIST);
        } else {
            $record->timecreated = $now;
            $record->quote = $record->quote ?? null;
            $record->quoteurl = $record->quoteurl ?? null;
            $record->id = $DB->insert_record('local_quicknotes', $record);
            $saved = $DB->get_record('local_quicknotes', ['id' => $record->id], '*', MUST_EXIST);
        }

        return self::export_note($saved);
    }

    /**
     * Define the return structure for execute().
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'Note id.'),
            'userid' => new \external_value(PARAM_INT, 'Owner user id.'),
            'courseid' => new \external_value(PARAM_INT, 'Course id.'),
            'content' => new \external_value(PARAM_RAW, 'Note content.'),
            'quote' => new \external_value(PARAM_RAW, 'Selected quote text.'),
            'hasquote' => new \external_value(PARAM_BOOL, 'Whether the note contains a quote.'),
            'quotetext' => new \external_value(PARAM_RAW, 'Quote text safe for template rendering.'),
            'quoteurl' => new \external_value(PARAM_RAW, 'URL pointing to the selected quote.'),
            'url' => new \external_value(PARAM_RAW_TRIMMED, 'Last saved page URL.'),
            'timecreated' => new \external_value(PARAM_INT, 'Creation timestamp.'),
            'timemodified' => new \external_value(PARAM_INT, 'Last modification timestamp.'),
        ]);
    }

    /**
     * Convert a DB record into an external structure.
     *
     * @param \stdClass $note
     * @return array
     */
    public static function export_note(\stdClass $note): array {
        $quote = (string) ($note->quote ?? '');

        return [
            'id' => (int) $note->id,
            'userid' => (int) $note->userid,
            'courseid' => (int) $note->courseid,
            'content' => (string) ($note->content ?? ''),
            'quote' => $quote,
            'hasquote' => trim($quote) !== '',
            'quotetext' => $quote,
            'quoteurl' => (string) ($note->quoteurl ?? ''),
            'url' => (string) $note->url,
            'timecreated' => (int) $note->timecreated,
            'timemodified' => (int) $note->timemodified,
        ];
    }
}
