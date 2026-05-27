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
declare(strict_types=1);

namespace local_admindashboard\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/admindashboard/lib.php');
require_once($CFG->dirroot . '/local/admindashboard/metricslib.php');

/**
 * External function returning the users represented by a KPI card.
 */
class get_kpi_users extends external_api {
    /**
     * Input contract.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Selected course ID', VALUE_DEFAULT, 0),
            'department' => new external_value(PARAM_TEXT, 'Selected department', VALUE_DEFAULT, ''),
            'moduleid' => new external_value(PARAM_INT, 'Selected module ID', VALUE_DEFAULT, 0),
            'metric' => new external_value(PARAM_ALPHANUMEXT, 'KPI metric key'),
        ]);
    }

    /**
     * Return rows for the selected KPI.
     *
     * @param int $courseid
     * @param string $department
     * @param int $moduleid
     * @param string $metric
     * @return array<string,mixed>
     */
    public static function execute(int $courseid = 0, string $department = '', int $moduleid = 0, string $metric = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'department' => $department,
            'moduleid' => $moduleid,
            'metric' => $metric,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_login();
        require_capability('local/admindashboard:view', $context);

        $metric = strtolower(trim((string)$params['metric']));
        $users = \local_admindashboard_get_kpi_user_rows(
            (int)$params['courseid'],
            trim((string)$params['department']),
            (int)$params['moduleid'],
            $metric
        );

        return [
            'metric' => $metric,
            'count' => count($users),
            'users' => array_values($users),
        ];
    }

    /**
     * Output contract.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'metric' => new external_value(PARAM_ALPHANUMEXT, 'KPI metric key'),
            'count' => new external_value(PARAM_INT, 'Number of rows returned'),
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'name' => new external_value(PARAM_TEXT, 'User full name'),
                    'department' => new external_value(PARAM_TEXT, 'User department'),
                    'clinicname' => new external_value(PARAM_TEXT, 'Clinic or institution name', VALUE_OPTIONAL),
                    'course_name' => new external_value(PARAM_TEXT, 'Course name for overview rows', VALUE_OPTIONAL),
                    'enrolment_label' => new external_value(PARAM_TEXT, 'Enrolment or record label', VALUE_OPTIONAL),
                ])
            ),
        ]);
    }
}
