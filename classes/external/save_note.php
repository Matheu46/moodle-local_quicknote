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

        $course = \local_quicknote\util::validate_note_access((int) $params['courseid']);

        $cleanurl = \local_quicknote\util::clean_url($params['url']);
        if ($cleanurl === null && trim((string)$params['url']) !== '') {
            throw new \invalid_parameter_exception('Invalid URL scheme provided.');
        }

        $now = time();
        $record = (object) [
            'userid' => $USER->id,
            'courseid' => $course->id,
            'content' => core_text::substr($params['content'], 0, 20000),
            'url' => core_text::substr($cleanurl ?? '', 0, 255),
            'timemodified' => $now,
        ];

        if (array_key_exists('quote', $params) && $params['quote'] !== null) {
            $record->quote = core_text::substr($params['quote'], 0, 5000);
        }

        if (array_key_exists('quoteurl', $params) && $params['quoteurl'] !== null) {
            $cleanquoteurl = \local_quicknote\util::clean_url($params['quoteurl']);
            $record->quoteurl = $cleanquoteurl !== null ? core_text::substr($cleanquoteurl, 0, 1024) : null;
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

            if (!property_exists($record, 'quote')) {
                $record->quote = $existing->quote ?? null;
            }

            if (!property_exists($record, 'quoteurl')) {
                $record->quoteurl = $existing->quoteurl ?? null;
            }

            $DB->update_record('local_quicknote_notes', $record);
            $saved = $DB->get_record('local_quicknote_notes', ['id' => $record->id], '*', MUST_EXIST);
        } else {
            $record->timecreated = $now;
            $record->quote = $record->quote ?? null;
            $record->quoteurl = $record->quoteurl ?? null;
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
            'screenshots' => new \core_external\external_multiple_structure(
                screenshot_manager::external_structure()
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

        return [
            'id' => (int) $note->id,
            'userid' => (int) $note->userid,
            'courseid' => (int) $note->courseid,
            'content' => (string) ($note->content ?? ''),
            'quote' => $quote,
            'hasquote' => trim($quote) !== '',
            'quotetext' => $quote,
            'quoteurl' => \local_quicknote\util::clean_url($note->quoteurl ?? null) ?? '',
            'url' => \local_quicknote\util::clean_url($note->url ?? null) ?? '',
            'screenshots' => $screenshots ?? screenshot_manager::get_for_note((int) $note->id),
            'timecreated' => (int) $note->timecreated,
            'timemodified' => (int) $note->timemodified,
        ];
    }
}
