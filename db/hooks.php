<?php
// Hook listener callbacks for local_admindashboard.

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => core\hook\navigation\primary_extend::class,
        'callback' => 'local_admindashboard\\local\\hooks\\navigation\\primary_extend::callback',
        'priority' => 0,
    ],
];
