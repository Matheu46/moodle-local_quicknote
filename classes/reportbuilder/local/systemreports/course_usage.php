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

declare(strict_types=1);

namespace local_quicknote\reportbuilder\local\systemreports;

use context_course;
use core\lang_string;
use core\output\html_writer;
use core_reportbuilder\local\aggregation\count;
use core_reportbuilder\local\aggregation\countdistinct;
use core_reportbuilder\local\aggregation\max;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use local_quicknote\reportbuilder\local\entities\note;
use local_quicknote\util;
use stdClass;

/**
 * Course-level QuickNote usage system report.
 *
 * Displays aggregated, anonymous usage statistics per page within a single course.
 * Never exposes private note contents, quotes, or individual student identities.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_usage extends system_report {
    /**
     * Initialise report, set main table, base conditions, columns and filters.
     */
    protected function initialise(): void {
        $noteentity = new note();
        $notealias = $noteentity->get_table_alias('local_quicknote_notes');

        $this->set_main_table('local_quicknote_notes', $notealias);
        $this->add_entity($noteentity);

        // Restrict strictly to the requested course (uses coursecreated_ix index).
        $courseid = $this->get_parameter('courseid', 0, PARAM_INT);
        $this->add_base_condition_simple("{$notealias}.courseid", $courseid);

        // Honour course separate groups mode when the viewer cannot access all groups.
        $this->add_group_restriction($notealias, $courseid);

        $this->add_columns();
        $this->add_filters();

        $this->set_downloadable(true, get_string('report:courseusage', 'local_quicknote'));
        $this->set_default_no_results_notice(new lang_string('report:nonotes', 'local_quicknote'));
    }

    /**
     * Honour separate groups mode: a viewer who cannot access all groups only sees
     * aggregated metrics from members of their own groups.
     *
     * @param string $notealias Table alias for local_quicknote_notes.
     * @param int $courseid Target course ID.
     */
    private function add_group_restriction(string $notealias, int $courseid): void {
        global $DB, $USER;

        if ($courseid <= 0 || $courseid === (int) SITEID) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return;
        }

        $context = context_course::instance($course->id);
        if (
            groups_get_course_groupmode($course) != SEPARATEGROUPS
            || has_capability('moodle/site:accessallgroups', $context)
        ) {
            return;
        }

        $groupids = array_keys(groups_get_all_groups($course->id, $USER->id));
        if ($groupids) {
            [$groupselect, $groupparams] = $DB->get_in_or_equal(
                $groupids,
                SQL_PARAMS_NAMED,
                database::generate_param_name()
            );
            $this->add_base_condition_sql(
                "{$notealias}.userid IN (
                    SELECT gm.userid
                      FROM {groups_members} gm
                     WHERE gm.groupid {$groupselect})",
                $groupparams
            );
        } else {
            // Viewer belongs to no group in a separate-groups course.
            $this->add_base_condition_sql('1 = 2');
        }
    }

    /**
     * Validate access to view this report.
     *
     * Always checks the capability against the requested courseid parameter, because
     * system report parameters round-trip through the client on AJAX requests.
     *
     * @return bool
     */
    protected function can_view(): bool {
        $courseid = $this->get_parameter('courseid', 0, PARAM_INT);
        if ($courseid <= 0 || $courseid === (int) SITEID) {
            return false;
        }

        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        return has_capability('local/quicknote:viewreport', $context);
    }

    /**
     * Return user friendly name of this system report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('report:courseusage', 'local_quicknote');
    }

    /**
     * Add columns to the report (aggregated per page).
     */
    protected function add_columns(): void {
        $noteentity = $this->get_entity('note');
        $notealias = $noteentity->get_table_alias('local_quicknote_notes');

        // Page (grouped by pagetitle + canonical URL, linked to the target page).
        $this->add_column((new column(
            'page',
            new lang_string('report:page', 'local_quicknote'),
            $noteentity->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$notealias}.pagetitle, {$notealias}.url")
            ->set_is_sortable(true, ["{$notealias}.pagetitle", "{$notealias}.url"])
            ->add_callback(static function (?string $pagetitle, stdClass $row): string {
                $cleanurl = util::clean_url($row->url ?? null, false, true);
                $label = ($pagetitle !== null && trim($pagetitle) !== '')
                    ? format_string($pagetitle)
                    : ($cleanurl !== null ? s($cleanurl) : get_string('report:untitledpage', 'local_quicknote'));

                if ($cleanurl !== null) {
                    return html_writer::link($cleanurl, $label);
                }

                return $label;
            }));

        // Total notes count.
        $this->add_column_from_entity('note:id')
            ->set_title(new lang_string('report:totalnotes', 'local_quicknote'))
            ->set_aggregation(count::get_class_name());

        // Distinct unique users (counted directly on userid — anonymous, no join to {user}).
        $this->add_column((new column(
            'uniqueusers',
            new lang_string('report:uniqueusers', 'local_quicknote'),
            $noteentity->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$notealias}.userid")
            ->set_is_sortable(true)
            ->set_aggregation(countdistinct::get_class_name()));

        // Most recent note timestamp.
        $this->add_column_from_entity('note:timecreated')
            ->set_title(new lang_string('report:lastactivity', 'local_quicknote'))
            ->set_aggregation(max::get_class_name());

        // Default sort by highest total notes first.
        $this->set_initial_sort_column('note:id', SORT_DESC);
    }

    /**
     * Add filters to the report.
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'note:pagetitle',
            'note:timecreated',
            'note:hasquote',
        ]);
    }
}
