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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_quicknote
 * @category    upgrade
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/upgradelib.php');

/**
 * Execute local_quicknote upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_quicknote_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042604) {
        $table = new xmldb_table('local_quicknote_notes');

        $quotefield = new xmldb_field('quote', XMLDB_TYPE_TEXT, null, null, null, null, null, 'content');
        if (!$dbman->field_exists($table, $quotefield)) {
            $dbman->add_field($table, $quotefield);
        }

        $quoteurlfield = new xmldb_field('quoteurl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'quote');
        if (!$dbman->field_exists($table, $quoteurlfield)) {
            $dbman->add_field($table, $quoteurlfield);
        }

        upgrade_plugin_savepoint(true, 2026042604, 'local', 'quicknote');
    }

    if ($oldversion < 2026080901) {
        // Define table local_quicknote_course to be created.
        $table = new xmldb_table('local_quicknote_course');

        // Adding fields to table local_quicknote_course.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
        $table->add_field('module_settings', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table local_quicknote_course.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_quicknote_course.
        $table->add_index('courseid_ix', XMLDB_INDEX_UNIQUE, ['courseid']);

        // Conditionally launch create table for local_quicknote_course.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Migrate existing data from config_plugins.
        $sql = "SELECT * FROM {config_plugins} WHERE plugin LIKE 'local_quicknote_course_%'";
        $rs = $DB->get_recordset_sql($sql);

        $coursedata = [];
        foreach ($rs as $record) {
            $courseid = (int) str_replace('local_quicknote_course_', '', $record->plugin);
            if (!isset($coursedata[$courseid])) {
                $coursedata[$courseid] = new stdClass();
                $coursedata[$courseid]->courseid = $courseid;
            }
            if ($record->name === 'enabled') {
                $coursedata[$courseid]->enabled = (int) $record->value;
            } else if ($record->name === 'module_settings') {
                $coursedata[$courseid]->module_settings = $record->value;
            }
        }
        $rs->close();

        // Insert migrated records into the new table.
        foreach ($coursedata as $data) {
            if (!$DB->record_exists('local_quicknote_course', ['courseid' => $data->courseid])) {
                $DB->insert_record('local_quicknote_course', $data);
            }
        }

        // Clean up config_plugins.
        $DB->execute("DELETE FROM {config_plugins} WHERE plugin LIKE 'local_quicknote_course_%'");

        // Quicknote savepoint reached.
        upgrade_plugin_savepoint(true, 2026080901, 'local', 'quicknote');
    }

    if ($oldversion < 2026101800) {
        $table = new xmldb_table('local_quicknote_notes');
        $field = new xmldb_field('pagetitle', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'url');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026101800, 'local', 'quicknote');
    }

    if ($oldversion < 2026101801) {
        $table = new xmldb_table('local_quicknote_notes');
        $field = new xmldb_field('pagehash', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'url');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Backfill pagehash for existing notes using a cursor recordset to avoid memory exhaustion.
        $rs = $DB->get_recordset_select(
            'local_quicknote_notes',
            "pagehash IS NULL OR pagehash = ''",
            null,
            'id ASC',
            'id, url'
        );
        foreach ($rs as $note) {
            $hash = \local_quicknote\util::calculate_page_hash($note->url ?? null);
            if ($hash !== '') {
                $DB->set_field('local_quicknote_notes', 'pagehash', $hash, ['id' => $note->id]);
            }
        }
        $rs->close();

        $index = new xmldb_index('userpage_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'pagehash']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026101801, 'local', 'quicknote');
    }

    return true;
}
