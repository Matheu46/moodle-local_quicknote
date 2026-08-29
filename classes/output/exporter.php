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
 * Class exporter for QuickNote.
 *
 * Handles the logic for exporting notes to different formats (PDF, Markdown).
 *
 * @package    local_quicknote
 * @copyright  2026 Matheus Mathias
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /**
     * Generates a PDF file string from a recordset of notes.
     *
     * @param \moodle_recordset $rs The recordset containing note records.
     * @return string The binary PDF data.
     */
    public static function export_to_pdf(\moodle_recordset $rs): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $pdf = new \pdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);

        $title = get_string('notescenter', 'local_quicknote');
        $pdf->writeHTML('<h2 style="margin-bottom: 16px;">' . $title . '</h2>', true, false, true, false, '');

        if (!$rs->valid()) {
            $pdf->writeHTML('<p>' . get_string('note:empty', 'local_quicknote') . '</p>', true, false, true, false, '');
        } else {
            $currentcourseid = null;

            foreach ($rs as $record) {
                if (empty(trim($record->content)) && empty(trim($record->quote))) {
                    continue;
                }

                $html = '';

                if ($currentcourseid !== $record->courseid) {
                    $coursefullname = format_string(
                        $record->coursefullname,
                        true,
                        [
                            'context' => \context_course::instance($record->courseid),
                        ]
                    );

                    $html .= '<h3 style="color: #0056b3; margin-top: 25px; border-bottom: 1px solid #eee;">'
                        . $coursefullname
                        . '</h3>';

                    $currentcourseid = $record->courseid;
                }

                $timeupdated = userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
                $content = format_text($record->content, FORMAT_PLAIN);

                $html .= '<p style="text-align: right;"><small><i>' . $timeupdated . '</i></small></p>';

                if (!empty($record->quote)) {
                    $quote = format_text($record->quote, FORMAT_PLAIN);
                    $html .= '<blockquote style="margin-bottom: 4px; color: #555;"><i>' . $quote . '</i>';
                    if (!empty($record->quoteurl)) {
                        $html .= '<br><small><a href="'
                            . s(clean_param($record->quoteurl, PARAM_URL))
                            . '">'
                            . get_string('note:viewintext', 'local_quicknote')
                            . '</a></small>';
                    }
                    $html .= '</blockquote><br>';
                } else {
                    if (!empty($record->url)) {
                        $html .= '<p style="margin-bottom: 4px;"><small><a href="'
                            . s(clean_param($record->url, PARAM_URL))
                            . '" style="color: #6c757d; text-decoration: none;">'
                            . get_string('note:viewintext', 'local_quicknote')
                            . '</a></small></p>';
                    }
                }
                $html .= '<p>' . nl2br($content) . '</p>';
                $html .= '<hr style="color: #f8f9fa;">';

                $pdf->writeHTML($html, true, false, true, false, '');
            }
        }

        $rs->close();

        // Return the PDF document as a string so it can be tested or output by the caller.
        return $pdf->Output('my_quicknotes.pdf', 'S');
    }

    /**
     * Generates a Markdown file string from a recordset of notes.
     *
     * @param \moodle_recordset $rs The recordset containing note records.
     * @return string The Markdown string.
     */
    public static function export_to_md(\moodle_recordset $rs): string {
        $md = "# " . get_string('notescenter', 'local_quicknote') . "\n\n";

        if (!$rs->valid()) {
            $md .= get_string('note:empty', 'local_quicknote') . "\n";
        } else {
            $currentcourseid = null;

            foreach ($rs as $record) {
                if (empty(trim($record->content)) && empty(trim($record->quote))) {
                    continue;
                }

                if ($currentcourseid !== $record->courseid) {
                    $coursefullname = format_string(
                        $record->coursefullname,
                        true,
                        [
                            'context' => \context_course::instance($record->courseid),
                        ]
                    );

                    $md .= "## " . $coursefullname . "\n\n";
                    $currentcourseid = $record->courseid;
                }

                $timeupdated = userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
                $content = format_text($record->content, FORMAT_PLAIN);

                $md .= "**" . $timeupdated . "**\n";

                if (!empty($record->quote)) {
                    $quote = html_entity_decode(format_text($record->quote, FORMAT_PLAIN), ENT_QUOTES, 'UTF-8');
                    $md .= "> " . str_replace("\n", "\n> ", $quote) . "\n";
                    if (!empty($record->quoteurl)) {
                        $md .= "> [_" . get_string('note:viewintext', 'local_quicknote') . "_](" .
                            s(clean_param($record->quoteurl, PARAM_URL)) . ")\n";
                    }
                    $md .= "\n";
                } else {
                    if (!empty($record->url)) {
                        $md .= "[_" . get_string('note:viewintext', 'local_quicknote') . "_](" .
                            s(clean_param($record->url, PARAM_URL)) . ")\n\n";
                    } else {
                        $md .= "\n";
                    }
                }
                $md .= $content . "\n\n";
                $md .= "---\n\n";
            }
        }
        $rs->close();

        return $md;
    }
}
