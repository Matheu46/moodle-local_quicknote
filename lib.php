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
 * Plugin callbacks.
 *
 * @package     local_quicknote
 * @copyright   2026 IFRN
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use context_course;

/**
 * Checks whether QuickNote is enabled for the current course.
 *
 * @param stdClass $course
 * @return bool
 */
function local_quicknote_is_enabled_for_course($course) {
    // Busca a configuração específica do curso.
    $enabled = get_config('local_quicknote_course_' . $course->id, 'enabled');

    // Se o professor nunca alterou a configuração neste curso (valor é nulo/vazio).
    if ($enabled === false || $enabled === null || $enabled === '') {
        // Retorna o padrão definido pelo administrador em Administração do Site.
        return (bool) get_config('local_quicknote', 'default_enabled');
    }

    return (string) $enabled !== '0';
}

function local_quicknote_before_standard_top_of_body_html() {
    global $OUTPUT, $PAGE, $USER;

    if (during_initial_install() || (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
        return '';
    }

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    if (empty($PAGE->course->id) || (int) $PAGE->course->id === SITEID) {
        return '';
    }

    $course = get_course($PAGE->course->id);
    $context = context_course::instance($course->id, IGNORE_MISSING);

    if (!$context) {
        return '';
    }

    if (!has_capability('moodle/course:view', $context)) {
        return '';
    }

    if (!local_quicknote_is_enabled_for_course($course)) {
        return '';
    }

    // A chamada do CSS foi removida daqui! O Moodle carrega o styles.css nativamente.

    // A chamada do JS (AMD) é permitida aqui, pois o Moodle a empurra para o rodapé automaticamente.
    $PAGE->requires->js_call_amd('local_quicknote/notes', 'init', [[
        'courseid' => (int) $course->id,
    ]]);

    return $OUTPUT->render_from_template('local_quicknote/sidebar', [
        'courseid' => (int) $course->id,
        'title' => 'Anotações Rápidas',
        'togglelabel' => 'Abrir Notas',
        'closelabel' => 'Fechar',
        'addlabel' => 'Adicionar',
        'placeholder' => 'Escreva sua reflexão...',
        'emptytext' => 'Nenhuma anotação.',
        'savingtext' => 'Salvando...',
        'savedtext' => 'Salvo',
        'errortext' => 'Erro',
        'updatedlabel' => 'Atualizado',
        'locationlabel' => 'Local',
    ]);
}
