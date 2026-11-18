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
 * Course-level QuickNote usage report page.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\html_writer;
use core\report_helper;
use core_reportbuilder\system_report_factory;
use local_quicknote\reportbuilder\local\systemreports\course_usage;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

if ($id <= 0 || $id === (int) SITEID) {
    throw new moodle_exception('invalidcourseid');
}

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/quicknote:viewreport', $context);

$reportname = get_string('report:courseusage', 'local_quicknote');

$PAGE->set_url('/local/quicknote/report.php', ['id' => $course->id]);
$PAGE->set_pagelayout('report');
$PAGE->set_context($context);
$PAGE->set_title($course->shortname . ': ' . $reportname);
$PAGE->set_heading($course->fullname);

$report = system_report_factory::create(
    course_usage::class,
    $context,
    'local_quicknote',
    '',
    0,
    ['courseid' => $course->id]
);

echo $OUTPUT->header();
report_helper::print_report_selector($reportname);
echo html_writer::tag('p', get_string('report:privacy_notice', 'local_quicknote'), ['class' => 'text-muted']);
echo $report->output();
echo $OUTPUT->footer();
