<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new admin_settingpage('local_quicknote', 'QuickNote');

    // Adds global configuration checkbox.
    $settings->add(new admin_setting_configcheckbox(
        'local_quicknote/default_enabled',
        get_string('default_enabled', 'local_quicknote'),
        get_string('default_enabled_desc', 'local_quicknote'),
        1                                 
    ));

    $ADMIN->add('localplugins', $settings);
}