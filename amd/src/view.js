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
 * @module      local_quicknote/view
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        init: function() {
            var $select = $('#coursefilter');
            var isKeyboardNav = false;

            if (!$select.length) {
                return;
            }

            $select.on('keydown', function(e) {
                // Up, Down, Left, Right arrows
                if (e.which >= 37 && e.which <= 40) {
                    isKeyboardNav = true;
                }
                // Enter key
                if (e.which === 13) {
                    this.form.submit();
                }
            });

            $select.on('mousedown', function() {
                isKeyboardNav = false;
            });

            $select.on('change', function() {
                if (!isKeyboardNav) {
                    this.form.submit();
                }
                isKeyboardNav = false; // Reset for next interaction
            });
        }
    };
});
