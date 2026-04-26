define([
    'jquery',
    'core/ajax',
    'core/notification'
], function($, Ajax, Notification) {
    var SELECTORS = {
        root: '#local-quicknote-root',
        panel: '[data-region="panel"]',
        list: '[data-region="notes-list"]',
        noteTemplate: '[data-region="note-template"]',
        toggle: '[data-action="toggle"]',
        close: '[data-action="close"]',
        add: '[data-action="add"]',
        search: '[data-action="search"]',
        deletebutton: '[data-action="delete-note"]',
        textarea: '.local-quicknote__textarea',
        note: '.local-quicknote__note',
        emptystate: '.local-quicknote__empty',
        status: '[data-region="note-status"]',
        updated: '[data-region="note-updated"]',
        location: '[data-region="note-location"]'
    };

    var SAVE_DELAY = 500;

    var state = null;

    var escapeHtml = function(value) {
        return $('<div>').text(String(value || '')).html();
    };

    var formatTimestamp = function(timestamp) {
        if (!timestamp) {
            return '';
        }

        return new Date(timestamp * 1000).toLocaleString();
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

    var normaliseNote = function(note) {
        return $.extend({}, note, {
            clientid: note.clientid || ('note-' + note.id),
            content: note.content || '',
            url: note.url || '',
            status: note.status || ''
        });
    };

    var getRoot = function() {
        return $(SELECTORS.root);
    };

    var getList = function() {
        return state.root.find(SELECTORS.list);
    };

    var getSearchTerm = function() {
        var value = state.root.find(SELECTORS.search).val() || '';
        return String(value).toLowerCase();
    };

    var getNoteByKey = function(key) {
        return state.notes.find(function(note) {
            return note.clientid === key;
        }) || null;
    };

    var getNoteElementByKey = function(key) {
        return state.root.find(SELECTORS.note + '[data-note-key="' + key + '"]').first();
    };

    var setNoteStatus = function($note, text) {
        $note.find(SELECTORS.status).text(text || '');
    };

    var setNoteUpdated = function($note, timestamp) {
        $note.find(SELECTORS.updated).text(
            state.strings.updatedlabel + ': ' + formatTimestamp(timestamp)
        );
    };

    var setNoteLocation = function($note, url) {
        var $location = $note.find(SELECTORS.location);

        if (!url) {
            $location.empty();
            return;
        }

        $location.html(
            escapeHtml(state.strings.locationlabel) + ': ' +
            '<a href="' + escapeHtml(url) + '">' + escapeHtml(url) + '</a>'
        );
    };

    var updateNoteElement = function(note, $note, preservecontent) {
        var $textarea = $note.find(SELECTORS.textarea);
        var currentcontent = preservecontent ? $textarea.val() : note.content;
        var textareaid = 'local-quicknote-textarea-' + note.clientid;

        $note.attr('data-note-key', note.clientid);
        $textarea.attr('id', textareaid);
        $textarea.attr('data-note-key', note.clientid);
        $textarea.attr('placeholder', state.strings.placeholder);
        $note.find('label').attr('for', textareaid);
        $note.find(SELECTORS.deletebutton).attr('data-noteid', note.id || 0);

        setNoteStatus($note, note.status);
        setNoteUpdated($note, note.timemodified);
        setNoteLocation($note, note.url);

        if ($textarea.val() !== currentcontent) {
            $textarea.val(currentcontent);
        }
    };

    var createNoteElement = function(note) {
        var template = state.root.find(SELECTORS.noteTemplate).get(0);
        var element = template.content.firstElementChild.cloneNode(true);
        var $note = $(element);

        updateNoteElement(note, $note, false);

        return $note;
    };

    var renderEmptyState = function() {
        getList().html(
            '<p class="local-quicknote__empty">' + escapeHtml(state.strings.emptytext) + '</p>'
        );
    };

    var renderNoResultsState = function() {
        getList().html(
            '<p class="local-quicknote__empty">' + escapeHtml(state.strings.noresultstext) + '</p>'
        );
    };

    var noteMatchesSearch = function(note, term) {
        if (!term) {
            return true;
        }

        return String(note.content || '').toLowerCase().indexOf(term) !== -1;
    };

    var applyFilter = function() {
        var term = getSearchTerm();
        var visiblecount = 0;

        if (!state.notes.length) {
            renderEmptyState();
            return;
        }

        getList().find(SELECTORS.note).each(function() {
            var $note = $(this);
            var note = getNoteByKey($note.attr('data-note-key'));
            var matches = note && noteMatchesSearch(note, term);

            $note.toggle(!!matches);

            if (matches) {
                visiblecount += 1;
            }
        });

        getList().find(SELECTORS.emptystate).remove();

        if (!visiblecount) {
            getList().find(SELECTORS.note).hide();
            renderNoResultsState();
        }
    };

    var renderNotes = function() {
        var $list = getList();

        if (!state.notes.length) {
            renderEmptyState();
            return;
        }

        $list.empty();
        state.notes.forEach(function(note) {
            $list.append(createNoteElement(note));
        });

        applyFilter();
    };

    var setOpenState = function(isopen) {
        var $panel = state.root.find(SELECTORS.panel);
        var $toggle = state.root.find(SELECTORS.toggle);

        state.root.toggleClass('is-open', isopen);
        $panel.attr('aria-hidden', isopen ? 'false' : 'true');
        $toggle.attr('aria-expanded', isopen ? 'true' : 'false');
    };

    var saveNote = function(note) {
        var request;
        var $note = getNoteElementByKey(note.clientid);

        if ($note.length) {
            setNoteStatus($note, state.strings.savingtext);
        }

        request = Ajax.call([{
            methodname: 'local_quicknote_save_note',
            args: {
                id: note.id || 0,
                courseid: state.courseid,
                content: note.content,
                url: window.location.href
            }
        }])[0];

        request.done(function(response) {
            var savednote = normaliseNote(response);
            var $currentnote = getNoteElementByKey(note.clientid);
            var $textarea;
            var currentcontent;

            note.id = savednote.id;
            note.courseid = savednote.courseid;
            note.userid = savednote.userid;
            note.url = savednote.url;
            note.timecreated = savednote.timecreated;
            note.timemodified = savednote.timemodified;
            note.status = state.strings.savedtext;

            if ($currentnote.length) {
                $textarea = $currentnote.find(SELECTORS.textarea);
                currentcontent = $textarea.val();
                updateNoteElement(note, $currentnote, true);
                $textarea.val(currentcontent);
            }
        }).fail(function(error) {
            note.status = state.strings.errortext;

            if ($note.length) {
                setNoteStatus($note, note.status);
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
        var request = Ajax.call([{
            methodname: 'local_quicknote_get_notes',
            args: {
                courseid: state.courseid
            }
        }])[0];

        request.done(function(response) {
            state.notes = response.map(function(note) {
                return normaliseNote(note);
            });

            renderNotes();
        }).fail(function(error) {
            Notification.exception(error);
        });
    };

    var deleteNote = function(note, $note) {
        var request = Ajax.call([{
            methodname: 'local_quicknote_delete_note',
            args: {
                noteid: note.id
            }
        }])[0];

        request.done(function(response) {
            if (!response.deleted) {
                return;
            }

            state.notes = state.notes.filter(function(item) {
                return item.clientid !== note.clientid;
            });

            if (state.timers[note.clientid]) {
                window.clearTimeout(state.timers[note.clientid]);
                delete state.timers[note.clientid];
            }

            $note.remove();

            if (!state.notes.length) {
                renderEmptyState();
            }
        }).fail(function(error) {
            Notification.exception(error);
        });
    };

    var bindEvents = function() {
        state.root.on('click', SELECTORS.toggle, function() {
            setOpenState(!state.root.hasClass('is-open'));
        });

        state.root.on('click', SELECTORS.close, function() {
            setOpenState(false);
        });

        state.root.on('click', SELECTORS.add, function() {
            var note = createDraftNote();
            var $list = getList();
            var $note;
            var $textarea;

            state.notes.unshift(note);
            $list.find(SELECTORS.emptystate).remove();

            $note = createNoteElement(note);
            $list.prepend($note);
            applyFilter();

            $textarea = $note.find(SELECTORS.textarea);
            if ($textarea.length) {
                $textarea.trigger('focus');
            }
        });

        state.root.on('input', SELECTORS.textarea, function() {
            var $textarea = $(this);
            var note = getNoteByKey($textarea.attr('data-note-key'));

            if (!note) {
                return;
            }

            note.content = $textarea.val();
            note.url = window.location.href;
            note.timemodified = Math.floor(Date.now() / 1000);

            scheduleSave(note);
            applyFilter();
        });

        state.root.on('click', SELECTORS.deletebutton, function(e) {
            var $button = $(e.currentTarget);
            var $note = $button.closest(SELECTORS.note);
            var note = getNoteByKey($note.attr('data-note-key'));

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

                $note.remove();

                if (!state.notes.length) {
                    renderEmptyState();
                } else {
                    applyFilter();
                }
                return;
            }

            if (!window.confirm('Delete this note?')) {
                return;
            }

            deleteNote(note, $note);
        });

        state.root.on('input keyup', SELECTORS.search, function() {
            var term = getSearchTerm();

            if (!state.notes.length) {
                renderEmptyState();
                return;
            }

            renderNotes();

            if (!term) {
                getList().find(SELECTORS.emptystate).remove();
                getList().find(SELECTORS.note).show();
            }
        });
    };

    return {
        init: function(config) {
            var $root = getRoot();

            if (!$root.length) {
                return;
            }

            state = {
                root: $root,
                courseid: Number(config.courseid || $root.attr('data-courseid')),
                notes: [],
                timers: {},
                strings: {
                    placeholder: $root.attr('data-placeholder'),
                    emptytext: $root.attr('data-emptytext'),
                    savingtext: $root.attr('data-savingtext'),
                    savedtext: $root.attr('data-savedtext'),
                    errortext: $root.attr('data-errortext'),
                    updatedlabel: $root.attr('data-updatedlabel'),
                    locationlabel: $root.attr('data-locationlabel'),
                    noresultstext: 'Nenhuma anotacao encontrada para este termo.'
                }
            };

            bindEvents();
            loadNotes();
        }
    };
});
