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
 * @module      local_quicknote/repository
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {
    return {
        /**
         * Save or update a note.
         *
         * @param {Object} noteData The note data.
         * @return {Promise}
         */
        saveNote: function(noteData) {
            return Ajax.call([{
                methodname: 'local_quicknote_save_note',
                args: noteData
            }])[0];
        },

        /**
         * Delete a note.
         *
         * @param {Number} noteId The ID of the note to delete.
         * @return {Promise}
         */
        deleteNote: function(noteId) {
            return Ajax.call([{
                methodname: 'local_quicknote_delete_note',
                args: {
                    noteid: noteId
                }
            }])[0];
        },

        /**
         * Get all notes for the current user in a specific course.
         *
         * @param {Number} courseId The course ID.
         * @return {Promise}
         */
        getNotes: function(courseId) {
            return Ajax.call([{
                methodname: 'local_quicknote_get_notes',
                args: {
                    courseid: courseId
                }
            }])[0];
        }
    };
});

