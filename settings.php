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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_quicknote', get_string('pluginname', 'local_quicknote'));

    // Adds global configuration checkbox.
    $settings->add(new admin_setting_configcheckbox(
        'local_quicknote/default_enabled',
        get_string('default_enabled', 'local_quicknote'),
        get_string('default_enabled_desc', 'local_quicknote'),
        1
    ));

    // Adds position option.
    $settings->add(new admin_setting_configselect(
        'local_quicknote/position',
        get_string('position', 'local_quicknote'),
        get_string('position_desc', 'local_quicknote'),
        'right',
        [
            'right' => get_string('position_right', 'local_quicknote'),
            'left'  => get_string('position_left', 'local_quicknote'),
        ]
    ));

    // Adds site-wide disabled page type patterns.
    $settings->add(new admin_setting_configtextarea(
        'local_quicknote/disabled_pagetypes',
        get_string('setting:disabled_pagetypes', 'local_quicknote'),
        get_string('setting:disabled_pagetypes_desc', 'local_quicknote'),
        ''
    ));

    // Adds notes per page option.
    $settings->add(new admin_setting_configselect(
        'local_quicknote/perpage',
        get_string('perpage', 'local_quicknote'),
        get_string('perpage_desc', 'local_quicknote'),
        12,
        [
            12 => '12',
            24 => '24',
            48 => '48',
            0  => get_string('all', 'core'),
        ]
    ));

    $ADMIN->add('localplugins', $settings);
}
