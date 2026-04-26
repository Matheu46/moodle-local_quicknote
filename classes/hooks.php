<?php
namespace local_quicknote;

defined('MOODLE_INTERNAL') || die();



class hooks {
    /**
     * Adds QuickNote settings to the course edit form using Hook API.
     */
    public static function course_edit_form(\core_course\hook\after_form_definition $hook) {
        // Pega a instância do formulário de forma compatível
        $mform = method_exists($hook, 'get_mform') ? $hook->get_mform() : $hook->mform;

        // O pulo do gato: ignoramos o bloqueio do form e pegamos o ID direto da requisição
        $courseid = optional_param('id', 0, PARAM_INT);

        // Busca o padrão global definido pelo administrador.
        $globaldefault = get_config('local_quicknote', 'default_enabled');
        $enabled = ($globaldefault === false || $globaldefault === null) ? 1 : $globaldefault;

        if ($courseid > 0) {
            $savedvalue = get_config('local_quicknote_course_' . $courseid, 'enabled');
            if ($savedvalue !== false && $savedvalue !== null) {
                $enabled = $savedvalue;
            }
        }

        // Adiciona a nova seção e o checkbox no final da página de configurações
        $mform->addElement('header', 'local_quicknote_header', 'Anotações Rápidas');
        $mform->addElement('advcheckbox', 'local_quicknote_enabled', 'Ativar QuickNote neste curso');
        
        // Define o valor atual (marcado ou desmarcado)
        $mform->setDefault('local_quicknote_enabled', (int) $enabled);
    }

    /**
     * Persists QuickNote settings using Hook API.
     */
    public static function course_edit_submission(\core_course\hook\after_form_submission $hook) {
        // Ignoramos a propriedade protegida do Moodle e pegamos os valores diretos da submissão de forma segura.
        $courseid = optional_param('id', 0, PARAM_INT);
        $enabled = optional_param('local_quicknote_enabled', 0, PARAM_INT);

        if ($courseid > 0) {
            set_config('enabled', $enabled, 'local_quicknote_course_' . $courseid);
        }
    }
}