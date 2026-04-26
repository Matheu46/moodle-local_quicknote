<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\course_updated',
        'callback'    => '\local_quicknote\observers::course_updated',
    ],
];