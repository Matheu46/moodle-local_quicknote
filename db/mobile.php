<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$addons = [
    'local_quicknote' => [
        'handlers' => [
            'quicknote' => [
                'delegate' => 'CoreCourseOptionsDelegate',
                'method' => 'mobile_course_view',
                'displaydata' => [
                    'icon' => 'fas-sticky-note', // Ícone FontAwesome suportado pelo app
                    'title' => 'pluginname', // Chave da string em lang/en/local_quicknote.php
                    'class' => '',
                ]
            ]
        ],
        'lang' => [
            ['pluginname', 'local_quicknote'],
        ],
    ]
];
