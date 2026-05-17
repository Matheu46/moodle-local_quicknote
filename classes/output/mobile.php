<?php
namespace local_quicknote\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    public static function mobile_course_view($args) {
        global $OUTPUT;
        
        $courseid = $args['courseid'] ?? 0;

        $js = "
            var that = this;
            this.notes = [];
            this.newNoteContent = '';
            
            var courseId = this.courseid || this.courseId || " . $courseid . ";

            // Captura os serviços internos do Moodle App (Funciona no App v3 e v4)
            var SitesService = this.CoreSites || this.CoreSitesProvider;
            var DomUtils = this.CoreDomUtils || this.CoreDomUtilsProvider;

            this.loadNotes = function() {
                SitesService.getCurrentSite().read('local_quicknote_get_notes', { courseid: courseId })
                    .then(function(result) {
                        that.notes = result.notes !== undefined ? result.notes : result;
                    }).catch(function(error) {
                        DomUtils.showErrorModal(error);
                    });
            };

            this.saveNote = function() {
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
                }).then(function() {
                    that.newNoteContent = '';
                    that.loadNotes();
                }).catch(function(error) {
                    DomUtils.showErrorModal(error);
                });
            };

            this.deleteNote = function(noteId) {
                DomUtils.showConfirm('Tem certeza que deseja excluir esta anotação?')
                    .then(function() {
                        SitesService.getCurrentSite().write('local_quicknote_delete_note', { noteid: noteId })
                            .then(function() {
                                that.loadNotes();
                            }).catch(function(error) {
                                DomUtils.showErrorModal(error);
                            });
                    }).catch(function() {
                        // Usuário cancelou
                    });
            };

            // Inicia a busca das notas
            this.loadNotes();
        ";

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('local_quicknote/mobile_view', []),
                ],
            ],
            'javascript' => $js,
            'otherdata' => [
                'courseid' => $courseid,
            ],
        ];
    }
}