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
 * QuickNote Mobile App Javascript.
 *
 * @package     local_quicknote
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var that = this;
this.notes = [];
this.newNoteContent = '';

// The courseid is injected by the Moodle App into the component context.
var courseId = this.courseid || this.courseId;

// Capture internal Moodle App services (Works in App v3 and v4).
var SitesService = this.CoreSites || this.CoreSitesProvider;
var DomUtils = this.CoreDomUtils || this.CoreDomUtilsProvider;

this.loadNotes = function () {
    var preSets = { getFromCache: false, emergencyCache: true };
    SitesService.getCurrentSite().read('local_quicknote_get_notes', { courseid: courseId }, preSets)
        .then(function (result) {
            setTimeout(function () {
                that.notes = result.notes !== undefined ? result.notes : result;
            }, 0);
        }).catch(function (error) {
            DomUtils.showErrorModal(error);
        });
};

this.saveNote = function () {
    if (!that.newNoteContent || that.newNoteContent.trim() === '') {
        return;
    }

    SitesService.getCurrentSite().write('local_quicknote_save_note', {
        id: 0,
        courseid: courseId,
        content: that.newNoteContent,
        url: '',
        quote: '',
        quoteurl: ''
    }).then(function (newNote) {
        setTimeout(function () {
            that.newNoteContent = '';
            that.notes = [newNote].concat(that.notes);
        }, 0);
    }).catch(function (error) {
        DomUtils.showErrorModal(error);
    });
};

this.deleteNote = function (noteId) {
    DomUtils.showConfirm('%%DELETE_CONFIRM%%')
        .then(function () {
            SitesService.getCurrentSite().write('local_quicknote_delete_note', { noteid: noteId })
                .then(function () {
                    setTimeout(function () {
                        that.notes = that.notes.filter(function (n) { return n.id !== noteId; });
                    }, 0);
                }).catch(function (error) {
                    DomUtils.showErrorModal(error);
                });
        }).catch(function () {
            // User cancelled the deletion.
        });
};

// Initial fetch of notes.
this.loadNotes();
