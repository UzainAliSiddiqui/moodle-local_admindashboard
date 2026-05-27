<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
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