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

namespace local_quicknote\reportbuilder\datasource;

use core_course\reportbuilder\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use local_quicknote\reportbuilder\local\entities\note;

/**
 * QuickNote usage datasource for Moodle Report Builder.
 *
 * Provides administrative and institutional usage reporting across courses and site-wide
 * (such as note timestamps, page locations, and author user details for engagement metrics)
 * without exposing private note contents or quoted text.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notes extends datasource {
    /**
     * Whether the datasource is available on the site.
     *
     * Controlled by the administrator setting local_quicknote/enable_reportbuilder.
     *
     * @return bool
     */
    public static function is_available(): bool {
        $enabled = get_config('local_quicknote', 'enable_reportbuilder');
        return $enabled === false || !empty($enabled);
    }

    /**
     * Return user friendly name of the report source.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('report:usage', 'local_quicknote');
    }

    /**
     * Initialise report.
     */
    protected function initialise(): void {
        $noteentity = new note();
        $notealias = $noteentity->get_table_alias('local_quicknote_notes');

        $this->set_main_table('local_quicknote_notes', $notealias);
        $this->add_entity($noteentity);

        // Join the course entity.
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity
            ->add_join("LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$notealias}.courseid"));

        // Join the course category entity.
        $coursecatentity = new course_category();
        $coursecatalias = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_joins($courseentity->get_joins())
            ->add_join("LEFT JOIN {course_categories} {$coursecatalias} ON {$coursecatalias}.id = {$coursealias}.category"));

        // Join the user entity (author metadata for distinct user counts and institutional filters).
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$notealias}.userid"));

        // Add all columns, filters and conditions from registered entities.
        $this->add_all_from_entities();
    }

    /**
     * Return the columns that will be added to the report upon creation.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'course:fullname',
            'note:scope',
            'note:pagetitle',
            'note:hasquote',
            'note:timecreated',
        ];
    }

    /**
     * Return the default sorting that will be added to the report upon creation.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'course:fullname' => SORT_ASC,
            'note:timecreated' => SORT_DESC,
        ];
    }

    /**
     * Return the filters that will be added to the report upon creation.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'course:courseselector',
            'course_category:name',
            'note:scope',
            'note:timecreated',
        ];
    }

    /**
     * Return the conditions that will be added to the report upon creation.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'course:courseselector',
            'note:scope',
            'note:timecreated',
        ];
    }
}
