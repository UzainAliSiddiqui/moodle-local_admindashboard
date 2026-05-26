<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\\local_admindashboard\\task\\send_module_reports',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => '\\local_admindashboard\\task\\refresh_at_risk_cache',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
