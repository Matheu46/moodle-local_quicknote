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

define([
    'local_quicknote/repository',
    'core/notification',
    'core/str',
    'local_quicknote/lightbox'
], function(Repository, Notification, Str, Lightbox) {
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

            // Bulk actions state
            var bulkMode = false;
            var selectedNoteIds = new Set();
            var lastCheckedCheckbox = null;

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

            var updateBulkVisuals = function() {
                var bulkBar = document.getElementById('quicknote-bulk-bar');
                if (!bulkBar) {
                    return;
                }

                var allCards = document.querySelectorAll('[data-region="quicknote-results"] .card');
                var visibleCheckedCount = 0;

                allCards.forEach(function(card) {
                    var checkbox = card.querySelector('.local-quicknote-card-select');
                    var checkContainer = card.querySelector('.local-quicknote-bulk-check');
                    if (!checkbox) {
                        return;
                    }

                    var id = parseInt(checkbox.getAttribute('data-id'), 10);
                    if (selectedNoteIds.has(id)) {
                        checkbox.checked = true;
                        card.classList.add('local-quicknote-center__card--selected');
                        visibleCheckedCount++;
                    } else {
                        checkbox.checked = false;
                        card.classList.remove('local-quicknote-center__card--selected');
                    }

                    if (bulkMode) {
                        checkContainer.classList.remove('d-none');
                    } else {
                        checkContainer.classList.add('d-none');
                    }
                });

                var bulkSelectAll = document.getElementById('quicknote-bulk-select-all');
                if (bulkSelectAll) {
                    bulkSelectAll.checked = allCards.length > 0 && visibleCheckedCount === allCards.length;
                }

                var count = selectedNoteIds.size;
                var bulkCount = document.getElementById('quicknote-bulk-count');
                if (bulkCount) {
                    Str.get_string('bulkselection', 'core', count).then(function(str) {
                        bulkCount.textContent = str;
                        return null;
                    }).catch(function() {
                        bulkCount.textContent = count;
                    });
                }

                var bulkExportMd = document.getElementById('quicknote-bulk-export-md');
                var bulkExportPdf = document.getElementById('quicknote-bulk-export-pdf');
                var bulkDelete = document.getElementById('quicknote-bulk-delete');

                if (bulkExportMd) {
                    bulkExportMd.disabled = count === 0;
                }
                if (bulkExportPdf) {
                    bulkExportPdf.disabled = count === 0;
                }
                if (bulkDelete) {
                    bulkDelete.disabled = count === 0;
                }
            };

            var toggleBulkMode = function() {
                var bulkBar = document.getElementById('quicknote-bulk-bar');
                bulkMode = !bulkMode;
                lastCheckedCheckbox = null;
                if (bulkBar) {
                    if (bulkMode) {
                        bulkBar.classList.remove('d-none');
                        document.body.classList.add('has-local-quicknote-bulk-bar');
                    } else {
                        bulkBar.classList.add('d-none');
                        document.body.classList.remove('has-local-quicknote-bulk-bar');
                        selectedNoteIds.clear();
                    }
                }
                updateBulkVisuals();
            };

            var submitBulkExport = function(format) {
                if (selectedNoteIds.size === 0 || !searchForm) {
                    return;
                }

                var form = document.createElement('form');
                form.method = 'POST';
                form.action = searchForm.action;
                form.style.display = 'none';

                var sesskeyInput = document.createElement('input');
                sesskeyInput.type = 'hidden';
                sesskeyInput.name = 'sesskey';
                sesskeyInput.value = M.cfg.sesskey;
                form.appendChild(sesskeyInput);

                var exportInput = document.createElement('input');
                exportInput.type = 'hidden';
                exportInput.name = 'export';
                exportInput.value = format;
                form.appendChild(exportInput);

                selectedNoteIds.forEach(function(id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'noteids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            };

            var submitSearch = function(force, overrideUrl) {
                if (!searchForm || !center || !searchInput) {
                    return;
                }
                var nextSearch = searchInput.value.trim();

                // If not forced and search hasn't changed, don't submit.
                // However, we want to allow forced submits (like when dropdown changes).
                if (!overrideUrl) {
                    if (!force && nextSearch === submittedSearch) {
                        return;
                    }
                    submittedSearch = nextSearch;
                }

                if (activeRequest) {
                    activeRequest.abort();
                }
                var request = new AbortController();
                activeRequest = request;

                var url;
                if (overrideUrl) {
                    url = new URL(overrideUrl, window.location.href);
                } else {
                    url = new URL(searchForm.action, window.location.href);
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
                }

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

                    updateBulkVisuals();
                    lastCheckedCheckbox = null;

                    // Accessibility Announcement
                    var noteCount = document.querySelectorAll('[data-region="quicknote-results"] .card').length;

                    // eslint-disable-next-line promise/no-nesting
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

            // Handle delete buttons and lightbox.
            document.addEventListener('click', function(e) {
                var paginationLink = e.target.closest('[data-region="quicknote-pagination"] a');
                if (paginationLink) {
                    e.preventDefault();
                    submitSearch(true, paginationLink.getAttribute('href'));
                    return;
                }

                if (e.target.closest('#toggle-bulk-actions')) {
                    toggleBulkMode();
                    return;
                }

                if (e.target.closest('#quicknote-bulk-close')) {
                    if (bulkMode) {
                        toggleBulkMode();
                    }
                    return;
                }

                if (e.target.closest('#quicknote-bulk-select-all')) {
                    var allCards = document.querySelectorAll('[data-region="quicknote-results"] .card');
                    var isChecked = e.target.closest('#quicknote-bulk-select-all').checked;
                    allCards.forEach(function(card) {
                        var checkbox = card.querySelector('.local-quicknote-card-select');
                        if (checkbox) {
                            var id = parseInt(checkbox.getAttribute('data-id'), 10);
                            if (isChecked) {
                                selectedNoteIds.add(id);
                            } else {
                                selectedNoteIds.delete(id);
                            }
                        }
                    });
                    updateBulkVisuals();
                    lastCheckedCheckbox = null;
                }

                if (e.target.closest('.local-quicknote-card-select')) {
                    var cb = e.target.closest('.local-quicknote-card-select');
                    var allCheckboxes = Array.from(document.querySelectorAll('.local-quicknote-card-select'));

                    if (e.shiftKey && lastCheckedCheckbox && lastCheckedCheckbox !== cb) {
                        var start = allCheckboxes.indexOf(lastCheckedCheckbox);
                        var end = allCheckboxes.indexOf(cb);

                        if (start !== -1 && end !== -1) {
                            var min = Math.min(start, end);
                            var max = Math.max(start, end);
                            var stateToApply = cb.checked;

                            for (var i = min; i <= max; i++) {
                                var rangeCb = allCheckboxes[i];
                                rangeCb.checked = stateToApply;
                                var rangeId = parseInt(rangeCb.getAttribute('data-id'), 10);
                                if (stateToApply) {
                                    selectedNoteIds.add(rangeId);
                                } else {
                                    selectedNoteIds.delete(rangeId);
                                }
                            }
                        }
                    } else {
                        var id = parseInt(cb.getAttribute('data-id'), 10);
                        if (cb.checked) {
                            selectedNoteIds.add(id);
                        } else {
                            selectedNoteIds.delete(id);
                        }
                    }

                    lastCheckedCheckbox = cb;
                    updateBulkVisuals();
                }

                if (e.target.closest('#quicknote-bulk-export-md')) {
                    submitBulkExport('md');
                    return;
                }

                if (e.target.closest('#quicknote-bulk-export-pdf')) {
                    submitBulkExport('pdf');
                    return;
                }

                if (e.target.closest('#quicknote-bulk-delete')) {
                    var count = selectedNoteIds.size;
                    if (count === 0) {
                        return;
                    }

                    Str.get_strings([
                        {key: 'confirm', component: 'core'},
                        {key: 'bulkdeleteconfirm', component: 'local_quicknote'},
                        {key: 'delete', component: 'core'},
                        {key: 'cancel', component: 'core'}
                    ]).done(function(strings) {
                        var confirmMsg = strings[1].replace('{$a}', count);
                        Notification.confirm(
                            strings[0],
                            confirmMsg,
                            strings[2],
                            strings[3],
                            function() {
                                var idsArray = Array.from(selectedNoteIds);
                                Repository.deleteNotes(idsArray).done(function() {
                                    selectedNoteIds.clear();
                                    submitSearch(true);

                                    Str.get_string('bulkdelete_success', 'local_quicknote', count).done(function(successMsg) {
                                        Notification.addNotification({
                                            message: successMsg,
                                            type: 'success'
                                        });
                                    }).fail(Notification.exception);
                                }).fail(Notification.exception);
                            }
                        );
                    }).fail(Notification.exception);
                    return;
                }

                var screenshotLink = e.target.closest('.local-quicknote__screenshot a');
                if (screenshotLink) {
                    e.preventDefault();
                    var container = screenshotLink.closest('[data-region="screenshots"]');
                    var allLinks = container ?
                        Array.prototype.slice.call(container.querySelectorAll('.local-quicknote__screenshot a')) :
                        [screenshotLink];
                    var gallery = allLinks.map(function(link) {
                        var img = link.querySelector('img');
                        return {src: link.href, alt: img ? img.alt : ''};
                    });
                    var currentIndex = allLinks.indexOf(screenshotLink);
                    Lightbox.show(gallery, Math.max(0, currentIndex));
                    return;
                }

                var deleteScreenshotBtn = e.target.closest('[data-action="delete-screenshot"]');
                if (deleteScreenshotBtn) {
                    e.preventDefault();
                    var fileId = deleteScreenshotBtn.getAttribute('data-fileid');
                    var noteCard = deleteScreenshotBtn.closest('.card');
                    var noteDeleteBtn = noteCard ? noteCard.querySelector('.local-quicknote-delete-btn') : null;
                    var screenshotNoteId = noteDeleteBtn ? noteDeleteBtn.getAttribute('data-id') : null;

                    if (screenshotNoteId && fileId) {
                        Str.get_strings([
                            {key: 'confirm', component: 'core'},
                            {key: 'screenshot:delete', component: 'local_quicknote'},
                            {key: 'delete', component: 'core'},
                            {key: 'cancel', component: 'core'}
                        ]).done(function(strings) {
                            Notification.confirm(
                                strings[0],
                                strings[1],
                                strings[2],
                                strings[3],
                                function() {
                                    Repository.deleteScreenshot(Number(screenshotNoteId), Number(fileId)).done(function() {
                                        submitSearch(true);
                                    }).fail(Notification.exception);
                                }
                            );
                        }).fail(Notification.exception);
                    }
                    return;
                }
                var deleteBtn = e.target.closest('.local-quicknote-delete-btn');
                if (deleteBtn) {
                    e.preventDefault();
                    var deleteNoteId = deleteBtn.getAttribute('data-id');

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
                                Repository.deleteNote(deleteNoteId).done(function() {
                                    // Refresh the entire grid silently to handle pagination
                                    // (e.g. pulling a note from the next page to fill the gap).
                                    if (selectedNoteIds.has(parseInt(deleteNoteId, 10))) {
                                        selectedNoteIds.delete(parseInt(deleteNoteId, 10));
                                    }
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
