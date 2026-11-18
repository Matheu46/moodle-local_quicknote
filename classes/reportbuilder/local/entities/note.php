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

namespace local_quicknote\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use local_quicknote\util;

/**
 * QuickNote note entity for Report Builder.
 *
 * Exposes strictly non-sensitive usage metadata (never note content or quoted text).
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class note extends base {
    /** @var string Scope identifier for notes created inside a course. */
    public const SCOPE_COURSE = 'course';

    /** @var string Scope identifier for general notes created on the dashboard or front page. */
    public const SCOPE_GENERAL = 'general';

    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'local_quicknote_notes',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_note', 'local_quicknote');
    }

    /**
     * Initialise the entity.
     *
     * Explicitly registers columns, filters, and conditions for compatibility across Moodle 5.0-5.3.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_available_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($this->get_available_filters() as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns SQL expression determining whether a note belongs to a course or general scope.
     *
     * @param string $notealias Table alias for local_quicknote_notes.
     * @return string
     */
    private function get_scope_sql(string $notealias): string {
        $siteid = (int) SITEID;
        $general = self::SCOPE_GENERAL;
        $course = self::SCOPE_COURSE;

        return "CASE WHEN {$notealias}.courseid = {$siteid} THEN '{$general}' ELSE '{$course}' END";
    }

    /**
     * Returns cross-database SQL expression determining whether a note has a non-empty quote.
     *
     * @param string $notealias Table alias for local_quicknote_notes.
     * @return string
     */
    private function get_has_quote_sql(string $notealias): string {
        global $DB;

        $isnotempty = $DB->sql_isnotempty('local_quicknote_notes', "{$notealias}.quote", true, true);
        return "CASE WHEN {$notealias}.quote IS NOT NULL AND {$isnotempty} THEN 1 ELSE 0 END";
    }

    /**
     * Returns list of all available columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $notealias = $this->get_table_alias('local_quicknote_notes');
        $scopesql = $this->get_scope_sql($notealias);
        $hasquotesql = $this->get_has_quote_sql($notealias);

        $columns = [];

        // Note ID (useful for exact COUNT aggregations).
        $columns[] = (new column(
            'id',
            new lang_string('report:noteid', 'local_quicknote'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$notealias}.id")
            ->set_is_sortable(true);

        // Scope (course vs. general notes).
        $columns[] = (new column(
            'scope',
            new lang_string('report:scope', 'local_quicknote'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($scopesql, 'scope')
            ->set_is_sortable(true)
            ->add_callback(static function (?string $scope): string {
                if ($scope === self::SCOPE_GENERAL) {
                    return get_string('general_notes', 'local_quicknote');
                }
                if ($scope === self::SCOPE_COURSE) {
                    return get_string('report:scope_course', 'local_quicknote');
                }
                return '';
            });

        // Source page title.
        $columns[] = (new column(
            'pagetitle',
            new lang_string('report:pagetitle', 'local_quicknote'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$notealias}.pagetitle")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $pagetitle): string {
                if ($pagetitle === null || $pagetitle === '') {
                    return '';
                }
                return format_string($pagetitle);
            });

        // Canonical source page URL (stripped of text fragments).
        $columns[] = (new column(
            'url',
            new lang_string('report:url', 'local_quicknote'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$notealias}.url")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $url): string {
                $clean = util::clean_url($url, false, true);
                return $clean !== null ? s($clean) : '';
            });

        // Whether the note includes a highlighted quote (boolean only, never exposes quote text).
        $columns[] = (new column(
            'hasquote',
            new lang_string('report:hasquote', 'local_quicknote'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field($hasquotesql, 'hasquote')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

        // Time created.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$notealias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Time modified.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$notealias}.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        return $columns;
    }

    /**
     * Return list of all available filters.
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
        $notealias = $this->get_table_alias('local_quicknote_notes');
        $scopesql = $this->get_scope_sql($notealias);
        $hasquotesql = $this->get_has_quote_sql($notealias);

        $filters = [];

        // Scope filter.
        $filters[] = (new filter(
            select::class,
            'scope',
            new lang_string('report:scope', 'local_quicknote'),
            $this->get_entity_name(),
            "({$scopesql})"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function (): array {
                return [
                    self::SCOPE_COURSE => new lang_string('report:scope_course', 'local_quicknote'),
                    self::SCOPE_GENERAL => new lang_string('general_notes', 'local_quicknote'),
                ];
            });

        // Page title filter.
        $filters[] = (new filter(
            text::class,
            'pagetitle',
            new lang_string('report:pagetitle', 'local_quicknote'),
            $this->get_entity_name(),
            "{$notealias}.pagetitle"
        ))
            ->add_joins($this->get_joins());

        // Page URL filter.
        $filters[] = (new filter(
            text::class,
            'url',
            new lang_string('report:url', 'local_quicknote'),
            $this->get_entity_name(),
            "{$notealias}.url"
        ))
            ->add_joins($this->get_joins());

        // Has quote filter.
        $filters[] = (new filter(
            boolean_select::class,
            'hasquote',
            new lang_string('report:hasquote', 'local_quicknote'),
            $this->get_entity_name(),
            "({$hasquotesql})"
        ))
            ->add_joins($this->get_joins());

        // Time created filter.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$notealias}.timecreated"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_LAST,
                date::DATE_CURRENT,
            ]);

        // Time modified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$notealias}.timemodified"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_LAST,
                date::DATE_CURRENT,
            ]);

        return $filters;
    }
}
