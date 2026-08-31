<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\course_deleted',
        'callback' => '\\local_spotaward\\observer::course_deleted',
    ],
];
