<?php

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_admindashboard_get_kpi_users' => [
        'classname' => 'local_admindashboard\\external\\get_kpi_users',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Returns the users represented by a dashboard KPI card.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/admindashboard:view',
    ],
];