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
$export = optional_param('export', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = get_config('local_quicknote', 'perpage');
if ($perpage === false) {
    $perpage = 12;
}
$perpage = (int)$perpage;

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

// Get only courses where the user has notes.
$sqlcourses = "SELECT DISTINCT c.id, c.fullname
               FROM {course} c
               JOIN {local_quicknote_notes} qn ON qn.courseid = c.id
               WHERE qn.userid = :userid
               ORDER BY c.fullname ASC";
$usercourses = $DB->get_records_sql($sqlcourses, ['userid' => $USER->id]);

$courses = [];
if ($usercourses) {
    foreach ($usercourses as $c) {
        $courses[] = [
            'id' => $c->id,
            'fullname' => format_string($c->fullname, true, ['context' => context_course::instance($c->id)]),
            'selected' => ($c->id == $coursefilter),
        ];
    }
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
$sqlfrom = "FROM {local_quicknote_notes} qn
        JOIN {course} c ON c.id = qn.courseid
        WHERE qn.userid = :userid";
$params = ['userid' => $USER->id];

if ($coursefilter > 0) {
    $sqlfrom .= " AND qn.courseid = :courseid";
    $params['courseid'] = $coursefilter;
}

if ($searchterm !== '') {
    $contentlike = $DB->sql_like('qn.content', ':searchcontent', false, false);
    $quotelike = $DB->sql_like('qn.quote', ':searchquote', false, false);
    $sqlfrom .= " AND ({$contentlike} OR {$quotelike})";

    $params['searchcontent'] = '%' . $DB->sql_like_escape($searchterm) . '%';
    $params['searchquote'] = '%' . $DB->sql_like_escape($searchterm) . '%';
}

$sqlorder = " ORDER BY qn.timemodified DESC";

$sqlcount = "SELECT COUNT(qn.id) " . $sqlfrom;
$totalcount = $DB->count_records_sql($sqlcount, $params);

$sql = "SELECT qn.id, qn.content, qn.url, qn.quote, qn.quoteurl, qn.timemodified, c.fullname as coursefullname, c.id as courseid
        " . $sqlfrom . $sqlorder;

// If export, process immediately with recordset to save memory.
if ($export === 'pdf' || $export === 'md') {
    $rs = $DB->get_recordset_sql($sql, $params);

    if ($export === 'pdf') {
        $pdfcontent = \local_quicknote\output\exporter::export_to_pdf($rs);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="my_quicknotes.pdf"');
        echo $pdfcontent;
        die();
    }

    if ($export === 'md') {
        $mdcontent = \local_quicknote\output\exporter::export_to_md($rs);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="my_quicknotes.md"');
        echo $mdcontent;
        die();
    }
}

// Execute query for web view.
if ($perpage === 0) {
    $noterecords = $DB->get_records_sql($sql, $params);
} else {
    $noterecords = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
}

$notes = [];
foreach ($noterecords as $record) {
    // Only format notes with content or quote.
    if (empty(trim($record->content)) && empty(trim($record->quote))) {
        continue;
    }

    // Prepare variables for the template. Mustache escapes standard tags {{ }} automatically.
    $notes[] = [
        'coursefullname' => format_string(
            $record->coursefullname,
            true,
            [
                'context' => context_course::instance($record->courseid),
            ]
        ),
        'content' => $record->content,
        'timeupdated' => userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'url' => !empty(clean_param($record->url, PARAM_URL)) ? (new moodle_url($record->url))->out(false) : null,
        'quote' => !empty($record->quote) ? $record->quote : null,
        'quoteurl' => !empty(clean_param($record->quoteurl, PARAM_URL)) ? (new moodle_url($record->quoteurl))->out(false) : null,
    ];
}

if ($perpage > 0) {
    $pagingbar = new paging_bar($totalcount, $page, $perpage, $url);
    $pagingbarhtml = $OUTPUT->render($pagingbar);
} else {
    $pagingbarhtml = '';
}

// Prepare template context.
$templatecontext = [
    'pagingbar' => $pagingbarhtml,
    'filterbycourse' => get_string('filterbycourse', 'local_quicknote'),
    'allcourses' => get_string('allcourses', 'local_quicknote'),
    'nonotesfound' => get_string('note:empty', 'local_quicknote'),
    'noresultstext' => get_string('search:noresultstext', 'local_quicknote'),
    'searchnotes' => get_string('search:placeholder', 'local_quicknote'),
    'search' => get_string('search', 'local_quicknote'),
    'exportpdf' => get_string('exportpdf', 'local_quicknote'),
    'exportmd' => get_string('exportmd', 'local_quicknote'),
    'hasnotestosearch' => $hasnotestosearch,
    'hasnotes' => !empty($notes),
    'coursefilter' => $coursefilter,
    'searchterm' => $searchterm,
    'courses' => $courses,
    'notes' => $notes,
];

// Output page.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_quicknote/view', $templatecontext);
echo $OUTPUT->footer();
