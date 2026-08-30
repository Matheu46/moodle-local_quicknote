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
 * @module      local_quicknote/notes
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'local_quicknote/repository',
    'core/notification',
    'core/str',
    'core/user_date'
], function(Repository, Notification, Str, UserDate) {
    var SELECTORS = {
        root: '#local-quicknote-root',
        panel: '[data-region="panel"]',
        list: '[data-region="notes-list"]',
        noteTemplate: '[data-region="note-template"]',
        toggle: '[data-action="toggle"]',
        close: '[data-action="close"]',
        add: '[data-action="add"]',
        search: '[data-action="search"]',
        searchwrapper: '.local-quicknote__search',
        clearsearch: '[data-action="clear-search"]',
        deletebutton: '[data-action="delete-note"]',
        textarea: '.local-quicknote__textarea',
        note: '.local-quicknote__note',
        emptystate: '.local-quicknote__empty',
        status: '[data-region="note-status"]',
        updated: '[data-region="note-updated"]',
        location: '[data-region="note-location"]',
        quotewrapper: '[data-region="note-quote-wrapper"]',
        quote: '[data-region="note-quote"]',
        quotelink: '[data-region="note-quote-link"]'
    };

    var SAVE_DELAY = 500;
    var MIN_SELECTION_LENGTH = 5;
    var HIGHLIGHT_BUTTON_CLASS = 'local-quicknote__highlight-action';

    var state = null;

    var escapeHtml = function(value) {
        var div = document.createElement('div');
        div.textContent = String(value || '');
        return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };

    var updateSearchVisibility = function() {
        var searchwrapper = state.root.querySelector(SELECTORS.searchwrapper);
        var searchInput = state.root.querySelector(SELECTORS.search);
        if (state.notes.length > 0) {
            if (searchwrapper) {
                searchwrapper.style.display = 'block';
            }
        } else {
            if (searchwrapper) {
                searchwrapper.style.display = 'none';
            }
            if (searchInput) {
                searchInput.value = '';
            }
        }
    };

    var createDraftNote = function() {
        var now = Math.floor(Date.now() / 1000);

        return {
            id: 0,
            clientid: 'draft-' + Date.now() + '-' + Math.random().toString(16).slice(2),
            content: '',
            url: window.location.href,
            timecreated: now,
            timemodified: now,
            status: ''
        };
    };

    var normaliseSelectionText = function(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    };

    var formatQuotedNote = function(text) {
        return '"' + normaliseSelectionText(text) + '"';
    };

    var normaliseNote = function(note) {
        var normalisednote = Object.assign({}, note, {
            clientid: note.clientid || ('note-' + note.id),
            content: note.content || '',
            quote: note.quote || '',
            quoteurl: note.quoteurl || '',
            url: note.url || '',
            status: note.status || ''
        });

        normalisednote.hasquote = !!(normalisednote.quote && normalisednote.quote.trim() !== '');
        normalisednote.quotetext = normalisednote.quote;

        return normalisednote;
    };

    var getRoot = function() {
        return document.querySelector(SELECTORS.root);
    };

    var getList = function() {
        return state.root.querySelector(SELECTORS.list);
    };

    var getSearchTerm = function() {
        var searchInput = state.root.querySelector(SELECTORS.search);
        var value = searchInput ? searchInput.value : '';
        return String(value || '').toLowerCase();
    };

    var getNoteByKey = function(key) {
        return state.notes.find(function(note) {
            return note.clientid === key;
        }) || null;
    };

    var getNoteElementByKey = function(key) {
        return state.root.querySelector(SELECTORS.note + '[data-note-key="' + key + '"]');
    };

    var setNoteStatus = function(noteEl, text, timestamp) {
        var statusEl = noteEl.querySelector(SELECTORS.status);
        if (statusEl) {
            if (text) {
                statusEl.textContent = text;
            } else if (timestamp) {
                Str.get_string('strftimedatetimeshort', 'langconfig').then(function(format) {
                    return UserDate.get([{
                        timestamp: timestamp,
                        format: format
                    }]);
                }).then(function(dates) {
                    var noteKey = noteEl.getAttribute('data-note-key');
                    var note = getNoteByKey(noteKey);
                    // Only update if the note hasn't changed its status while we were fetching the date
                    if (note && !note.status && note.timemodified === timestamp) {
                        statusEl.textContent = state.strings.updatedlabel + ': ' + dates[0];
                    }
                    return true;
                }).catch(function() {
                    statusEl.textContent = state.strings.updatedlabel + ': ' + new Date(timestamp * 1000).toLocaleString();
                });
            } else {
                statusEl.textContent = '';
            }
        }
    };

    var setNoteLocation = function(noteEl, url, hasquote) {
        var locationEl = noteEl.querySelector(SELECTORS.location);
        if (!locationEl) {
            return;
        }

        if (hasquote || !url) {
            locationEl.innerHTML = '';
            return;
        }

        locationEl.textContent = state.strings.locationlabel + ': ';
        var a = document.createElement('a');
        a.setAttribute('href', url);
        a.textContent = url;
        locationEl.appendChild(a);
    };

    var setNoteQuote = function(noteEl, note) {
        var wrapper = noteEl.querySelector(SELECTORS.quotewrapper);
        var quote = noteEl.querySelector(SELECTORS.quote);
        var link = noteEl.querySelector(SELECTORS.quotelink);

        if (!note.hasquote) {
            if (wrapper) {
                wrapper.setAttribute('hidden', 'hidden');
            }
            if (quote) {
                quote.textContent = '';
            }
            if (link) {
                link.setAttribute('href', '#');
                link.setAttribute('hidden', 'hidden');
            }
            return;
        }

        if (quote) {
            quote.textContent = note.quotetext;
        }
        if (link) {
            var safeHref = '#';
            if (note.quoteurl && /^(https?:\/\/|#)/i.test(note.quoteurl)) {
                safeHref = note.quoteurl;
            }
            link.setAttribute('href', safeHref);
            if (note.quoteurl) {
                link.removeAttribute('hidden');
            } else {
                link.setAttribute('hidden', 'hidden');
            }
        }
        if (wrapper) {
            wrapper.removeAttribute('hidden');
        }
    };

    var updateNoteElement = function(note, noteEl, preservecontent) {
        var textarea = noteEl.querySelector(SELECTORS.textarea);
        var currentcontent = preservecontent && textarea ? textarea.value : note.content;
        var textareaid = 'local-quicknote-textarea-' + note.clientid;

        noteEl.setAttribute('data-note-key', note.clientid);

        if (textarea) {
            textarea.setAttribute('id', textareaid);
            textarea.setAttribute('data-note-key', note.clientid);
            textarea.setAttribute('placeholder', state.strings.placeholder);

            if (textarea.value !== currentcontent) {
                textarea.value = currentcontent;
            }
        }

        var label = noteEl.querySelector('label');
        if (label) {
            label.setAttribute('for', textareaid);
        }

        var deletebutton = noteEl.querySelector(SELECTORS.deletebutton);
        if (deletebutton) {
            deletebutton.setAttribute('data-noteid', note.id || 0);
        }

        setNoteStatus(noteEl, note.status, note.timemodified);
        setNoteQuote(noteEl, note);
        setNoteLocation(noteEl, note.url, note.hasquote);

        var copyBtn = noteEl.querySelector('[data-action="copy-note"]');
        if (copyBtn) {
            if (currentcontent.trim().length > 0) {
                copyBtn.style.display = '';
            } else {
                copyBtn.style.display = 'none';
            }
        }
    };

    var createNoteElement = function(note) {
        var template = state.root.querySelector(SELECTORS.noteTemplate);
        var element = document.importNode(template.content.firstElementChild, true);

        updateNoteElement(note, element, false);

        return element;
    };

    var renderEmptyState = function() {
        getList().innerHTML = '<p class="local-quicknote__empty">' + escapeHtml(state.strings.emptytext) + '</p>';
    };

    var renderNoResultsState = function() {
        getList().innerHTML = '<p class="local-quicknote__empty">' + escapeHtml(state.strings.noresultstext) + '</p>';
    };

    var noteMatchesSearch = function(note, term) {
        if (!term) {
            return true;
        }

        return String(note.content || '').toLowerCase().indexOf(term) !== -1 ||
            String(note.quote || '').toLowerCase().indexOf(term) !== -1;
    };

    var applyFilter = function() {
        var term = getSearchTerm();
        var visiblecount = 0;
        var list = getList();

        if (!state.notes.length) {
            renderEmptyState();
            return;
        }

        var noteElements = list.querySelectorAll(SELECTORS.note);
        noteElements.forEach(function(noteEl) {
            var note = getNoteByKey(noteEl.getAttribute('data-note-key'));
            var matches = note && noteMatchesSearch(note, term);

            if (matches) {
                noteEl.style.display = '';
                visiblecount += 1;
            } else {
                noteEl.style.display = 'none';
            }
        });

        var emptyState = list.querySelector(SELECTORS.emptystate);
        if (emptyState) {
            emptyState.remove();
        }

        if (!visiblecount) {
            noteElements.forEach(function(n) {
                n.style.display = 'none';
            });
            renderNoResultsState();
        }
    };

    var renderNotes = function() {
        var list = getList();

        updateSearchVisibility();

        if (!state.notes.length) {
            renderEmptyState();
            return;
        }

        list.innerHTML = '';
        state.notes.forEach(function(note) {
            list.appendChild(createNoteElement(note));
        });

        applyFilter();
    };

    var openSidebar = function() {
        setOpenState(true);
    };

    var createHighlightButton = function() {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = HIGHLIGHT_BUTTON_CLASS;
        button.setAttribute('aria-label', state.strings.highlightlabel);
        button.textContent = '+';
        button.setAttribute('hidden', 'hidden');
        document.body.appendChild(button);
        return button;
    };

    var hideHighlightButton = function(clearselection) {
        if (!state || !state.highlightbutton) {
            return;
        }

        state.highlightbutton.setAttribute('hidden', 'hidden');
        state.highlightselectiontext = '';

        if (clearselection) {
            try {
                window.getSelection().removeAllRanges();
            } catch (e) {
                // Ignore — selection API may not be available.
            }
        }
    };

    var showHighlightButton = function(rect, text) {
        var buttonwidth = 40;
        var buttonheight = 40;
        var spacing = 10;
        var top = rect.top - buttonheight - spacing;
        var left = rect.left + (rect.width / 2) - (buttonwidth / 2);
        var maxleft = Math.max(spacing, window.innerWidth - buttonwidth - spacing);

        if (top < spacing) {
            top = rect.bottom + spacing;
        }

        left = Math.max(spacing, Math.min(left, maxleft));

        state.highlightselectiontext = text;
        state.highlightbutton.style.top = top + 'px';
        state.highlightbutton.style.left = left + 'px';
        state.highlightbutton.removeAttribute('hidden');
    };

    var getValidSelection = function(targetWindow) {
        var selection;
        var text;
        var range;
        var container;
        var win = targetWindow || window;

        try {
            selection = win.getSelection();

            if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
                return null;
            }

            text = normaliseSelectionText(selection.toString());

            if (text.length <= MIN_SELECTION_LENGTH) {
                return null;
            }

            range = selection.getRangeAt(0);
            container = range.commonAncestorContainer;

            if (container && container.nodeType === Node.TEXT_NODE) {
                container = container.parentNode;
            }

            if (!container || container.closest(SELECTORS.root)) {
                return null;
            }

            if (container.closest('input, textarea, button')) {
                return null;
            }

            return {
                text: text,
                rect: range.getBoundingClientRect()
            };
        } catch (e) {
            return null;
        }
    };

    var prependNote = function(note) {
        state.notes.unshift(note);
        renderNotes();
    };

    var setOpenState = function(isopen) {
        var panel = state.root.querySelector(SELECTORS.panel);
        var toggle = state.root.querySelector(SELECTORS.toggle);

        if (isopen) {
            state.root.classList.add('is-open');
        } else {
            state.root.classList.remove('is-open');
        }

        if (panel) {
            panel.setAttribute('aria-hidden', isopen ? 'false' : 'true');
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', isopen ? 'true' : 'false');
        }

        if (isopen) {
            var closeBtn = panel ? panel.querySelector(SELECTORS.close) : null;
            if (closeBtn) {
                closeBtn.focus();
            }
        } else {
            if (toggle) {
                toggle.focus();
            }
        }
    };

    var saveNote = function(note) {
        var request;
        var noteEl = getNoteElementByKey(note.clientid);

        if (noteEl) {
            setNoteStatus(noteEl, state.strings.savingtext, note.timemodified);
        }

        request = Repository.saveNote({
            id: note.id || 0,
            courseid: state.courseid,
            content: note.content,
            url: note.url || window.location.href,
            quote: note.quote || '',
            quoteurl: note.quoteurl || ''
        });

        request.then(function(response) {
            var savednote = normaliseNote(response);
            var currentnoteEl = getNoteElementByKey(note.clientid);

            savednote.hasquote = !!(savednote.quote && savednote.quote.trim() !== '');
            savednote.quotetext = savednote.quote;

            note.id = savednote.id;
            note.courseid = savednote.courseid;
            note.userid = savednote.userid;
            note.url = savednote.url;
            note.quote = savednote.quote;
            note.quoteurl = savednote.quoteurl;
            note.hasquote = savednote.hasquote;
            note.quotetext = savednote.quotetext;
            note.timecreated = savednote.timecreated;
            note.timemodified = savednote.timemodified;
            note.status = state.strings.savedtext;

            if (currentnoteEl) {
                setNoteStatus(currentnoteEl, note.status, note.timemodified);
                setNoteQuote(currentnoteEl, note);
                setNoteLocation(currentnoteEl, note.url, note.hasquote);

                setTimeout(function() {
                    note.status = '';
                    setNoteStatus(currentnoteEl, note.status, note.timemodified);
                }, 3000);
            }
            return response;
        }).catch(function(error) {
            note.status = state.strings.errortext;

            if (noteEl) {
                setNoteStatus(noteEl, note.status, note.timemodified);
            }

            Notification.exception(error);
        });
    };

    var scheduleSave = function(note) {
        var existingtimer = state.timers[note.clientid];

        if (existingtimer) {
            window.clearTimeout(existingtimer);
        }

        state.timers[note.clientid] = window.setTimeout(function() {
            delete state.timers[note.clientid];
            saveNote(note);
        }, SAVE_DELAY);
    };

    var loadNotes = function() {
        var request = Repository.getNotes(state.courseid);

        request.then(function(response) {
            state.notes = response.map(function(note) {
                return normaliseNote(note);
            });

            renderNotes();
            return response;
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    var deleteNote = function(note, noteEl) {
        var request = Repository.deleteNote(note.id);

        request.then(function(response) {
            if (!response.deleted) {
                return response;
            }

            state.notes = state.notes.filter(function(item) {
                return item.clientid !== note.clientid;
            });

            if (state.timers[note.clientid]) {
                window.clearTimeout(state.timers[note.clientid]);
                delete state.timers[note.clientid];
            }

            if (noteEl) {
                noteEl.remove();
            }

            if (!state.notes.length) {
                renderEmptyState();
            }
            updateSearchVisibility();

            // Return focus to a logical element to prevent focus loss
            var addBtn = state.root.querySelector(SELECTORS.add);
            if (addBtn) {
                addBtn.focus();
            }
            return response;
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    var createHighlightNote = function(text) {
        var note = createDraftNote();
        var quoteurl = window.location.href + '#:~:text=' + encodeURIComponent(text);

        note.content = '';
        note.url = window.location.href;
        note.quote = formatQuotedNote(text);
        note.quoteurl = quoteurl;
        note.timemodified = Math.floor(Date.now() / 1000);
        note.status = state.strings.savingtext;

        prependNote(note);
        openSidebar();

        var noteEl = getNoteElementByKey(note.clientid);
        var textarea = noteEl ? noteEl.querySelector(SELECTORS.textarea) : null;
        if (textarea) {
            textarea.focus();
        }

        saveNote(note);
    };

    var bindEvents = function() {
        state.highlightbutton.addEventListener('mousedown', function(e) {
            e.preventDefault();
        });

        state.highlightbutton.addEventListener('click', function() {
            var text = state.highlightselectiontext;
            hideHighlightButton(true);

            if (!text) {
                return;
            }

            createHighlightNote(text);
        });

        // Handle text selection highlight on mouseup.
        document.addEventListener('mouseup', function(e) {
            if (e.target.closest('.' + HIGHLIGHT_BUTTON_CLASS)) {
                return;
            }
            var result = getValidSelection();
            if (result && result.rect && result.rect.width) {
                showHighlightButton(result.rect, result.text);
            } else {
                hideHighlightButton(false);
            }
        });

        document.addEventListener('keyup', function(e) {
            if (e.key === 'Escape' && state.root.classList.contains('is-open')) {
                setOpenState(false);
            }
        });

        var handleToggleClick = function() {
            setOpenState(!state.root.classList.contains('is-open'));
        };

        var handleCloseClick = function() {
            setOpenState(false);
        };

        var handleAddClick = function() {
            var note = createDraftNote();
            prependNote(note);

            var noteEl = getNoteElementByKey(note.clientid);
            var textarea = noteEl ? noteEl.querySelector(SELECTORS.textarea) : null;
            if (textarea) {
                textarea.focus();
            }
        };

        var handleCopyClick = function(e, copyBtn) {
            e.preventDefault();
            var icon = copyBtn.querySelector('i');
            var noteEl = copyBtn.closest(SELECTORS.note);
            var textarea = noteEl ? noteEl.querySelector(SELECTORS.textarea) : null;
            if (!textarea) {
                return;
            }

            var textToCopy = textarea.value;
            if (!textToCopy) {
                return;
            }

            navigator.clipboard.writeText(textToCopy).then(function() {
                if (icon) {
                    icon.classList.remove('fa-regular', 'fa-copy');
                    icon.classList.add('fa-solid', 'fa-check');
                }
                copyBtn.style.color = '#28a745';

                setTimeout(function() {
                    if (icon) {
                        icon.classList.remove('fa-solid', 'fa-check');
                        icon.classList.add('fa-regular', 'fa-copy');
                    }
                    copyBtn.style.color = '#6c757d';
                }, 2000);

                return true;
            }).catch(function() {
                // Ignore.
            });
        };

        var handleDeleteClick = function(deleteBtn) {
            var noteEl = deleteBtn.closest(SELECTORS.note);
            var note = noteEl ? getNoteByKey(noteEl.getAttribute('data-note-key')) : null;

            if (!note) {
                return;
            }

            if (!note.id) {
                if (state.timers[note.clientid]) {
                    window.clearTimeout(state.timers[note.clientid]);
                    delete state.timers[note.clientid];
                }

                state.notes = state.notes.filter(function(item) {
                    return item.clientid !== note.clientid;
                });

                if (noteEl) {
                    noteEl.remove();
                }

                if (!state.notes.length) {
                    renderEmptyState();
                } else {
                    applyFilter();
                }
                updateSearchVisibility();

                var rootAddBtn = state.root.querySelector(SELECTORS.add);
                if (rootAddBtn) {
                    rootAddBtn.focus();
                }
                return;
            }

            Str.get_strings([
                {key: 'confirm', component: 'core'},
                {key: 'delete', component: 'core'},
                {key: 'cancel', component: 'core'}
            ]).then(function(strings) {
                Notification.confirm(
                    strings[0],
                    state.strings.deleteconfirm,
                    strings[1],
                    strings[2],
                    function() {
                        deleteNote(note, noteEl);
                    }
                );
                return true;
            }).catch(Notification.exception);
        };

        var handleQuoteClick = function(e, quoteLink) {
            var targetUrl = quoteLink.getAttribute('href');
            var currentUrl = window.location.href.split('#')[0];

            if (targetUrl && !/^(https?:\/\/|#)/i.test(targetUrl)) {
                e.preventDefault();
                return;
            }

            if (targetUrl && (targetUrl.indexOf(currentUrl) === 0 || targetUrl.indexOf('#') === 0)) {
                e.preventDefault();

                var hashIndex = targetUrl.indexOf('#');
                if (hashIndex === -1) {
                    return;
                }

                setOpenState(false);

                var noteEl = quoteLink.closest(SELECTORS.note);
                var quoteElement = noteEl ? noteEl.querySelector(SELECTORS.quote) : null;
                var originalText = quoteElement ? quoteElement.textContent : '';
                if (quoteElement) {
                    quoteElement.textContent = '';
                }

                window.setTimeout(function() {
                    window.location.hash = targetUrl.substring(hashIndex + 1);

                    window.setTimeout(function() {
                        if (quoteElement) {
                            quoteElement.textContent = originalText;
                        }
                    }, 100);
                }, 10);
            }
        };

        state.root.addEventListener('click', function(e) {
            var toggleBtn = e.target.closest(SELECTORS.toggle);
            if (toggleBtn) {
                handleToggleClick();
                return;
            }

            var closeBtn = e.target.closest(SELECTORS.close);
            if (closeBtn) {
                handleCloseClick();
                return;
            }

            var addBtn = e.target.closest(SELECTORS.add);
            if (addBtn) {
                handleAddClick();
                return;
            }

            var clearSearchBtn = e.target.closest(SELECTORS.clearsearch);
            if (clearSearchBtn) {
                var searchInput = state.root.querySelector(SELECTORS.search);
                if (searchInput) {
                    searchInput.value = '';
                    handleSearchInput();
                    searchInput.focus();
                }
                return;
            }

            var copyBtn = e.target.closest('[data-action="copy-note"]');
            if (copyBtn) {
                handleCopyClick(e, copyBtn);
                return;
            }

            var deleteBtn = e.target.closest(SELECTORS.deletebutton);
            if (deleteBtn) {
                handleDeleteClick(deleteBtn);
                return;
            }

            var quoteLink = e.target.closest(SELECTORS.quotelink);
            if (quoteLink) {
                handleQuoteClick(e, quoteLink);
                return;
            }
        });

        state.root.addEventListener('input', function(e) {
            var textarea = e.target.closest(SELECTORS.textarea);
            if (textarea) {
                var note = getNoteByKey(textarea.getAttribute('data-note-key'));
                if (!note) {
                    return;
                }

                note.content = textarea.value;
                note.url = window.location.href;
                note.timemodified = Math.floor(Date.now() / 1000);

                var noteEl = textarea.closest(SELECTORS.note);
                var cBtn = noteEl ? noteEl.querySelector('[data-action="copy-note"]') : null;

                if (cBtn) {
                    if (note.content.trim().length > 0) {
                        cBtn.style.display = '';
                    } else {
                        cBtn.style.display = 'none';
                    }
                }

                scheduleSave(note);
                applyFilter();
                return;
            }

            var search = e.target.closest(SELECTORS.search);
            if (search) {
                handleSearchInput();
            }
        });

        state.root.addEventListener('keyup', function(e) {
            var search = e.target.closest(SELECTORS.search);
            if (search) {
                handleSearchInput();
            }
        });

        var handleSearchInput = function() {
            var term = getSearchTerm();
            var clearBtn = state.root.querySelector(SELECTORS.clearsearch);

            if (clearBtn) {
                if (term) {
                    clearBtn.removeAttribute('hidden');
                } else {
                    clearBtn.setAttribute('hidden', 'hidden');
                }
            }

            if (!state.notes.length) {
                renderEmptyState();
                return;
            }

            renderNotes();

            if (!term) {
                var emptyState = getList().querySelector(SELECTORS.emptystate);
                if (emptyState) {
                    emptyState.remove();
                }

                var noteEls = getList().querySelectorAll(SELECTORS.note);
                noteEls.forEach(function(n) {
                    n.style.display = '';
                });
            }
        };

        // Listen for highlight messages triggered inside iframes.
        window.addEventListener('message', function(event) {
            if (event.origin !== window.location.origin) {
                return;
            }
            if (event.data && event.data.app === 'quicknote' && event.data.action === 'iframe_highlight') {
                var text = event.data.text;
                if (text && text.length > MIN_SELECTION_LENGTH) {
                    createHighlightNote(text);
                }
            }
        });
    };

    return {
        init: function(config) {
            var rootEl = getRoot();

            if (!rootEl) {
                return;
            }

            state = {
                root: rootEl,
                courseid: Number(config.courseid || rootEl.getAttribute('data-courseid')),
                notes: [],
                timers: {},
                strings: {
                    placeholder: rootEl.getAttribute('data-placeholder'),
                    emptytext: rootEl.getAttribute('data-emptytext'),
                    savingtext: rootEl.getAttribute('data-savingtext'),
                    savedtext: rootEl.getAttribute('data-savedtext'),
                    errortext: rootEl.getAttribute('data-errortext'),
                    updatedlabel: rootEl.getAttribute('data-updatedlabel'),
                    locationlabel: rootEl.getAttribute('data-locationlabel'),
                    highlightlabel: rootEl.getAttribute('data-highlightlabel'),
                    deleteconfirm: rootEl.getAttribute('data-deleteconfirm'),
                    noresultstext: rootEl.getAttribute('data-noresultstext')
                }
            };

            state.highlightbutton = createHighlightButton();
            state.highlightselectiontext = '';

            bindEvents();
            loadNotes();
        },

        initIframe: function(config) {
            state = {
                highlightselectiontext: '',
                timers: {},
                strings: {
                    highlightlabel: config.highlightlabel || '+'
                }
            };

            state.highlightbutton = createHighlightButton();

            // Central handler for mouseup events across contexts.
            var handleMouseUp = function(e, win) {
                if (e.target.closest('.' + HIGHLIGHT_BUTTON_CLASS)) {
                    return;
                }

                window.setTimeout(function() {
                    var result = getValidSelection(win);

                    if (result && result.rect && result.rect.width) {
                        showHighlightButton(result.rect, result.text);
                    } else {
                        hideHighlightButton(false);
                    }
                }, 10);
            };

            // Listen on the current iframe context (e.g., embed.php).
            document.addEventListener('mouseup', function(e) {
                handleMouseUp(e, window);
            }, true);

            // Attempt to locate an inner iframe (e.g., H5P) and attach the listener.
            var attachToInnerIframe = function(attempts) {
                if (attempts <= 0) {
                    return;
                }

                var h5pIframe = document.querySelector('.h5p-iframe');
                if (h5pIframe) {
                    var bindInner = function() {
                        try {
                            var innerWin = h5pIframe.contentWindow;
                            var innerDoc = h5pIframe.contentDocument;

                            if (innerDoc) {
                                innerDoc.addEventListener('mouseup', function(e) {
                                    handleMouseUp(e, innerWin);
                                }, true);
                            }
                        } catch (err) {
                            // Cross-origin boundaries may prevent attachment.
                        }
                    };

                    if (h5pIframe.contentDocument && h5pIframe.contentDocument.readyState === 'complete') {
                        bindInner();
                    } else {
                        h5pIframe.addEventListener('load', bindInner);
                    }
                } else {
                    setTimeout(function() {
                        attachToInnerIframe(attempts - 1);
                    }, 500);
                }
            };

            // Retry for up to 5 seconds (10 attempts * 500ms).
            attachToInnerIframe(10);

            state.highlightbutton.addEventListener('mousedown', function(e) {
                e.preventDefault();
            });
            state.highlightbutton.addEventListener('click', function() {
                var text = state.highlightselectiontext;
                hideHighlightButton(true);

                if (text) {
                    window.parent.postMessage({
                        app: 'quicknote',
                        action: 'iframe_highlight',
                        text: text
                    }, window.location.origin);
                }
            });
        }
    };
});
