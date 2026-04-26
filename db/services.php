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
 * External service definitions.
 *
 * @package     local_quicknote
 * @copyright   2026 IFRN
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_quicknote_save_note' => [
        'classname' => 'local_quicknote\\external\\save_note',
        'methodname' => 'execute',
        'description' => 'Create or update a private quick note for the current user.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quicknote_get_notes' => [
        'classname' => 'local_quicknote\\external\\get_notes',
        'methodname' => 'execute',
        'description' => 'Retrieve the current user private quick notes for a course.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quicknote_delete_note' => [
        'classname' => 'local_quicknote\\external\\delete_note',
        'methodname' => 'execute',
        'description' => 'Delete a private quick note owned by the current user.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];

$services = [
    'Local quicknote AJAX services' => [
        'functions' => [
            'local_quicknote_save_note',
            'local_quicknote_get_notes',
            'local_quicknote_delete_note',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];
