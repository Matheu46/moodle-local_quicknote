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

define(['local_quicknote/repository', 'core/notification', 'core/str'], function(Repository, Notification, Str) {
    return {
        init: function() {
            var select = document.getElementById('coursefilter');
            var isKeyboardNav = false;

            if (!select) {
                return;
            }

            select.addEventListener('keydown', function(e) {
                // Up, Down, Left, Right arrows
                if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) !== -1) {
                    isKeyboardNav = true;
                }
                // Enter key
                if (e.key === 'Enter') {
                    if (this.form) {
                        this.form.submit();
                    }
                }
            });

            select.addEventListener('mousedown', function() {
                isKeyboardNav = false;
            });

            select.addEventListener('change', function() {
                if (!isKeyboardNav) {
                    if (this.form) {
                        this.form.submit();
                    }
                }
                isKeyboardNav = false; // Reset for next interaction
            });

            var searchInput = document.getElementById('searchterm');
            var clearSearchBtn = document.getElementById('clearsearch');

            if (searchInput && clearSearchBtn) {
                searchInput.addEventListener('input', function() {
                    if (this.value.trim().length > 0) {
                        clearSearchBtn.removeAttribute('hidden');
                    } else {
                        clearSearchBtn.setAttribute('hidden', 'hidden');
                    }
                });

                clearSearchBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    clearSearchBtn.setAttribute('hidden', 'hidden');
                    if (searchInput.form) {
                        searchInput.form.submit();
                    }
                });
            }

            // Handle delete buttons.
            document.addEventListener('click', function(e) {
                var deleteBtn = e.target.closest('.local-quicknote-delete-btn');
                if (deleteBtn) {
                    e.preventDefault();
                    var noteId = deleteBtn.getAttribute('data-id');

                    Str.get_strings([
                        {key: 'note:delete_confirm', component: 'local_quicknote'},
                        {key: 'delete', component: 'core'},
                        {key: 'cancel', component: 'core'}
                    ]).done(function(strings) {
                        Notification.confirm(
                            strings[1], // title
                            strings[0], // message
                            strings[1], // yes
                            strings[2], // no
                            function() {
                                Repository.deleteNote(noteId).done(function() {
                                    var cardCol = deleteBtn.closest('.col-12.col-md-6.col-xl-4');
                                    if (cardCol) {
                                        cardCol.remove();
                                        // If no cards left, reload to show empty state.
                                        if (document.querySelectorAll('.local-quicknote-delete-btn').length === 0) {
                                            window.location.reload();
                                        }
                                    }
                                }).fail(Notification.exception);
                            }
                        );
                    }).fail(Notification.exception);
                }
            });
        }
    };
});
