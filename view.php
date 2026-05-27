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
 * QuickNote Notes Center page.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Require login and set context.
require_login();
$context = context_system::instance();

// Get optional parameters.
$coursefilter = optional_param('coursefilter', 0, PARAM_INT);
$searchterm = optional_param('searchterm', '', PARAM_TEXT);

// Set up page.
$url = new moodle_url('/local/quicknote/view.php');
if ($coursefilter) {
    $url->param('coursefilter', $coursefilter);
}
if ($searchterm !== '') {
    $url->param('searchterm', $searchterm);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('notescenter', 'local_quicknote'));
$PAGE->set_heading(get_string('notescenter', 'local_quicknote'));
$PAGE->set_pagelayout('standard');

// Get courses the user is enrolled in.
$usercourses = enrol_get_users_courses($USER->id, true);
$courses = [];
foreach ($usercourses as $c) {
    $courses[] = [
        'id' => $c->id,
        'fullname' => format_string($c->fullname, true, ['context' => context_course::instance($c->id)]),
        'selected' => ($c->id == $coursefilter)
    ];
}

// Check if user has notes to show the search bar.
$countsql = "SELECT COUNT('x') FROM {local_quicknote_notes} WHERE userid = :userid";
$countparams = ['userid' => $USER->id];
if ($coursefilter > 0) {
    $countsql .= " AND courseid = :courseid";
    $countparams['courseid'] = $coursefilter;
}
$hasnotestosearch = $DB->count_records_sql($countsql, $countparams) > 0;

// Build SQL query for notes.
$sql = "SELECT qn.id, qn.content, qn.quote, qn.quoteurl, qn.timemodified, c.fullname as coursefullname, c.id as courseid
        FROM {local_quicknote_notes} qn
        JOIN {course} c ON c.id = qn.courseid
        WHERE qn.userid = :userid";
$params = ['userid' => $USER->id];

if ($coursefilter > 0) {
    $sql .= " AND qn.courseid = :courseid";
    $params['courseid'] = $coursefilter;
}

if ($searchterm !== '') {
    $sql .= " AND (" . $DB->sql_like('qn.content', ':searchcontent', false, false) . " OR " . $DB->sql_like('qn.quote', ':searchquote', false, false) . ")";
    $params['searchcontent'] = '%' . $DB->sql_like_escape($searchterm) . '%';
    $params['searchquote'] = '%' . $DB->sql_like_escape($searchterm) . '%';
}

$sql .= " ORDER BY qn.timemodified DESC";

// Execute query.
$noterecords = $DB->get_records_sql($sql, $params);

$notes = [];
foreach ($noterecords as $record) {
    // Only format notes with content or quote.
    if (empty(trim($record->content)) && empty(trim($record->quote))) {
        continue;
    }

    // Prepare variables for the template. Mustache escapes standard tags {{ }} automatically.
    $notes[] = [
        'coursefullname' => format_string($record->coursefullname, true, ['context' => context_course::instance($record->courseid)]),
        'content' => $record->content,
        'timeupdated' => userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'quote' => !empty($record->quote) ? $record->quote : null,
        'quoteurl' => !empty($record->quoteurl) ? (new moodle_url($record->quoteurl))->out(false) : null,
    ];
}

// Prepare template context.
$templatecontext = [
    'filterbycourse' => get_string('filterbycourse', 'local_quicknote'),
    'allcourses' => get_string('allcourses', 'local_quicknote'),
    'nonotesfound' => get_string('note:empty', 'local_quicknote'),
    'noresultstext' => get_string('search:noresultstext', 'local_quicknote'),
    'searchnotes' => get_string('search:placeholder', 'local_quicknote'),
    'search' => get_string('search', 'local_quicknote'),
    'hasnotestosearch' => $hasnotestosearch,
    'searchterm' => $searchterm,
    'courses' => $courses,
    'notes' => $notes
];

// Output page.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_quicknote/view', $templatecontext);
echo $OUTPUT->footer();
