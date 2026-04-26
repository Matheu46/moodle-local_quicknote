<?php
namespace local_quicknote;

defined('MOODLE_INTERNAL') || die();

class observers {
    /**
     * Salva a configuração do QuickNote quando o curso é atualizado.
     */
    public static function course_updated(\core\event\course_updated $event) {
        $courseid = $event->objectid;
        
        // Captura o valor do nosso checkbox direto do POST do formulário
        $enabled = optional_param('local_quicknote_enabled', null, PARAM_INT);
        
        if ($enabled !== null) {
            set_config('enabled', $enabled, 'local_quicknote_course_' . $courseid);
        }
    }
}