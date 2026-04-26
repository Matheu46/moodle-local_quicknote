<?php
defined('MOODLE_INTERNAL') || die();

// Verifica se o menu de administração está sendo carregado.
if ($ADMIN->fulltree) {
    $settings = new admin_settingpage('local_quicknote', 'QuickNote');

    // Adiciona o checkbox de configuração global.
    $settings->add(new admin_setting_configcheckbox(
        'local_quicknote/default_enabled', // Nome da configuração.
        'Ativar por padrão',              // Rótulo.
        'Define se o ícone de anotações estará ativo por padrão em cursos que ainda não foram configurados manualmente.', // Descrição.
        1                                  // Valor padrão (1 = Ativado).
    ));

    $ADMIN->add('localplugins', $settings);
}