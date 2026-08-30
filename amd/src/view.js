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
            var searchInput = document.getElementById('searchterm');
            var clearSearchBtn = document.getElementById('clearsearch');
            var select = document.getElementById('coursefilter');
            var searchTimer = null;
            var activeRequest = null;
            var searchForm = searchInput ? searchInput.form : null;
            var center = document.querySelector('.local-quicknote-center');
            var submittedSearch = searchInput ? searchInput.value.trim() : '';

            var replaceRegion = function(nextDocument, selector) {
                var currentRegion = document.querySelector(selector);
                var nextRegion = nextDocument.querySelector(selector);
                if (currentRegion && nextRegion) {
                    currentRegion.innerHTML = nextRegion.innerHTML;
                    if (nextRegion.hasAttribute('hidden')) {
                        currentRegion.setAttribute('hidden', 'hidden');
                    } else {
                        currentRegion.removeAttribute('hidden');
                    }
                }
            };

            var submitSearch = function(force) {
                if (!searchForm || !center || !searchInput) {
                    return;
                }
                var nextSearch = searchInput.value.trim();

                // If not forced and search hasn't changed, don't submit.
                // However, we want to allow forced submits (like when dropdown changes).
                if (!force && nextSearch === submittedSearch) {
                    return;
                }

                submittedSearch = nextSearch;

                if (activeRequest) {
                    activeRequest.abort();
                }
                var request = new AbortController();
                activeRequest = request;

                var url = new URL(searchForm.action, window.location.href);
                new FormData(searchForm).forEach(function(value, name) {
                    if (name === 'searchterm') {
                        value = nextSearch;
                    }
                    if (String(value).length > 0 && String(value) !== '0') {
                        url.searchParams.set(name, value);
                    } else {
                        url.searchParams.delete(name);
                    }
                });

                center.setAttribute('aria-busy', 'true');

                var cleanupRequest = function() {
                    if (activeRequest === request) {
                        center.removeAttribute('aria-busy');
                        activeRequest = null;
                    }
                };

                fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    signal: request.signal
                }).then(function(response) {
                    if (!response.ok) {
                        throw new Error('QuickNote search request failed.');
                    }
                    return response.text();
                }).then(function(html) {
                    var nextDocument = new DOMParser().parseFromString(html, 'text/html');
                    replaceRegion(nextDocument, '[data-region="quicknote-results"]');
                    replaceRegion(nextDocument, '[data-region="quicknote-pagination"]');
                    replaceRegion(nextDocument, '[data-region="quicknote-exports"]');
                    window.history.replaceState({}, '', url.toString());

                    // Accessibility Announcement
                    var noteCount = document.querySelectorAll('[data-region="quicknote-results"] .card').length;

                    // eslint-disable-next-line promise/catch-or-return, promise/no-nesting
                    Str.get_string('search:results', 'local_quicknote', noteCount).then(function(announcement) {
                        var announcer = document.getElementById('quicknote-a11y-announcer');
                        if (announcer) {
                            announcer.textContent = announcement;
                        }
                        return null;
                    }).catch(function() {
                        return null;
                    });

                    cleanupRequest();
                    return null;
                }).catch(function(error) {
                    if (error.name !== 'AbortError') {
                        // Fallback to normal page load if fetch fails
                        window.location.assign(url.toString());
                    }
                    cleanupRequest();
                });
            };

            // Bind filter dropdown
            if (select) {
                var isKeyboardNav = false;
                select.addEventListener('keydown', function(e) {
                    if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) !== -1) {
                        isKeyboardNav = true;
                    }
                    if (e.key === 'Enter') {
                        submitSearch(true);
                    }
                });

                select.addEventListener('mousedown', function() {
                    isKeyboardNav = false;
                });

                select.addEventListener('change', function() {
                    if (!isKeyboardNav) {
                        submitSearch(true);
                    }
                    isKeyboardNav = false;
                });
            }

            // Bind search input and clear button
            if (searchInput && clearSearchBtn) {
                searchInput.addEventListener('input', function() {
                    if (this.value.trim().length > 0) {
                        clearSearchBtn.removeAttribute('hidden');
                    } else {
                        clearSearchBtn.setAttribute('hidden', 'hidden');
                    }

                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(function() {
                        submitSearch(false);
                    }, 400); // Debounce delay
                });

                clearSearchBtn.addEventListener('click', function() {
                    window.clearTimeout(searchTimer);
                    searchInput.value = '';
                    clearSearchBtn.setAttribute('hidden', 'hidden');
                    submitSearch(true);
                    searchInput.focus();
                });

                searchForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    window.clearTimeout(searchTimer);
                    submitSearch(true);
                });
            }

            // Handle delete buttons.
            document.addEventListener('click', function(e) {
                var deleteBtn = e.target.closest('.local-quicknote-delete-btn');
                if (deleteBtn) {
                    e.preventDefault();
                    var noteId = deleteBtn.getAttribute('data-id');

                    Str.get_strings([
                        {key: 'confirm', component: 'core'},
                        {key: 'note:delete_confirm', component: 'local_quicknote'},
                        {key: 'delete', component: 'core'},
                        {key: 'cancel', component: 'core'}
                    ]).done(function(strings) {
                        Notification.confirm(
                            strings[0],
                            strings[1],
                            strings[2],
                            strings[3],
                            function() {
                                Repository.deleteNote(noteId).done(function() {
                                    // Refresh the entire grid silently to handle pagination
                                    // (e.g. pulling a note from the next page to fill the gap).
                                    submitSearch(true);
                                }).fail(Notification.exception);
                            }
                        );
                    }).fail(Notification.exception);
                }
            });
        }
    };
});
