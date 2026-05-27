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

/**
 * Legacy JSON API for local_admindashboard.
 *
 * The endpoint is retained for backwards compatibility with older dashboard
 * clients, but all actions now require a normal Moodle session, sesskey and
 * the dashboard view capability. New browser UI calls should use registered
 * external services or data.php.
 *
 * @package    local_admindashboard
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

$canonicalurl = getenv('MOODLE_URL') ?: '';
$incominghost = $_SERVER['HTTP_HOST'] ?? '';
$incominghostname = parse_url('http://' . $incominghost, PHP_URL_HOST) ?: $incominghost;
$isloopbackrequest = in_array($incominghostname, ['10.0.2.2', '127.0.0.1'], true);
if ($isloopbackrequest) {
    $canonicalhost = parse_url($canonicalurl, PHP_URL_HOST);
    $canonicalport = parse_url($canonicalurl, PHP_URL_PORT);
    $canonicalscheme = parse_url($canonicalurl, PHP_URL_SCHEME);

    if (empty($canonicalhost)) {
        $canonicalhost = 'localhost';
    }

    if (empty($canonicalport) && !empty($_SERVER['SERVER_PORT'])) {
        $canonicalport = (int)$_SERVER['SERVER_PORT'];
    }

    if (!empty($canonicalhost)) {
        $_SERVER['HTTP_HOST'] = $canonicalhost . (!empty($canonicalport) ? ':' . $canonicalport : '');
        $_SERVER['SERVER_NAME'] = $canonicalhost;
        if (!empty($canonicalport)) {
            $_SERVER['SERVER_PORT'] = (string)$canonicalport;
        }
        if (!empty($canonicalscheme)) {
            $_SERVER['REQUEST_SCHEME'] = $canonicalscheme;
            if ($canonicalscheme === 'https') {
                $_SERVER['HTTPS'] = 'on';
            } else {
                unset($_SERVER['HTTPS']);
            }
        }
    }
}

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/admindashboard/lib.php');
require_once($CFG->dirroot . '/local/admindashboard/metricslib.php');

require_login();
$PAGE->set_context(context_system::instance());
local_admindashboard_require_view_access();

/**
 * Send a JSON response and terminate the script.
 *
 * @param int $httpstatus
 * @param array $payload
 * @return void
 */
function local_admindashboard_api_respond(int $httpstatus, array $payload): void {
    @header_remove('Set-Cookie');
    http_response_code($httpstatus);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a successful JSON payload.
 *
 * @param array $data
 * @return void
 */
function local_admindashboard_api_success(array $data): void {
    local_admindashboard_api_respond(200, [
        'status' => 'success',
        'data' => $data,
    ]);
}

/**
 * Send an error payload.
 *
 * @param int $httpstatus
 * @param string $message
 * @param null|int $code
 * @return void
 */
function local_admindashboard_api_error(int $httpstatus, string $message, ?int $code = null): void {
    local_admindashboard_api_respond($httpstatus, [
        'status' => 'error',
        'message' => $message,
        'code' => $code ?? $httpstatus,
    ]);
}

/**
 * Normalise the department filter for API use.
 *
 * @param string $department
 * @return string
 */
function local_admindashboard_api_normalize_department(string $department): string {
    $department = trim($department);
    if ($department === '' || core_text::strtolower($department) === 'all departments') {
        return '';
    }

    return $department;
}

/**
 * Return a sensible default course id when one is not supplied.
 *
 * @return int
 */
function local_admindashboard_api_default_courseid(): int {
    global $DB;

    $courseid = $DB->get_field_select('course', 'id', 'id > 1 AND visible = 1', null, IGNORE_MULTIPLE, 'id DESC');
    return $courseid ? (int)$courseid : 0;
}

/**
 * Resolve the requested course id.
 *
 * @param int $courseid
 * @return int
 */
function local_admindashboard_api_resolve_courseid(int $courseid): int {
    global $DB;

    if ($courseid > 0 && $DB->record_exists('course', ['id' => $courseid, 'visible' => 1])) {
        return $courseid;
    }

    $fallbackcourseid = local_admindashboard_api_default_courseid();
    if ($fallbackcourseid > 0) {
        return $fallbackcourseid;
    }

    local_admindashboard_api_error(404, 'No visible course is available for dashboard analytics.', 404);
}

/**
 * Return tracked activity count for a course.
 *
 * @param int $courseid
 * @return int
 */
function local_admindashboard_api_count_tracked_activities(int $courseid): int {
    global $DB;

    $sql = "SELECT COUNT(1)
              FROM {course_modules} cm
             WHERE cm.course = :courseid
               AND cm.deletioninprogress = 0
               AND cm.completion > 0";

    return (int)$DB->count_records_sql($sql, ['courseid' => $courseid]);
}

/**
 * Fetch available departments.
 *
 * @return string[]
 */
function local_admindashboard_api_get_departments(int $courseid = 0): array {
    $courseid = $courseid > 0 ? local_admindashboard_api_resolve_courseid($courseid) : 0;
    $meta = local_admindashboard_get_meta($courseid);
    $departments = array_values(array_filter(array_map('trim', $meta['departments'] ?? [])));
    array_unshift($departments, 'All Departments');
    return array_values(array_unique($departments));
}

/**
 * Build filter metadata for the mobile dashboard.
 *
 * @param int $courseid
 * @return array<string,mixed>
 */
function local_admindashboard_api_build_meta(int $courseid = 0): array {
    $resolvedcourseid = $courseid > 0 ? local_admindashboard_api_resolve_courseid($courseid) : 0;
    $meta = local_admindashboard_get_meta($resolvedcourseid);

    $courses = array_map(static function(array $course): array {
        return [
            'id' => (int)($course['id'] ?? 0),
            'fullname' => trim((string)($course['fullname'] ?? '')),
        ];
    }, $meta['courses'] ?? []);

    $departments = local_admindashboard_api_get_departments($resolvedcourseid);
    $modules = [[
        'id' => 0,
        'name' => 'All Modules',
    ]];

    foreach (($meta['modules'] ?? []) as $module) {
        $modules[] = [
            'id' => (int)($module->id ?? 0),
            'name' => trim((string)($module->name ?? '')),
        ];
    }

    $modulegroups = [[
        'label' => 'All Modules',
        'items' => [[
            'id' => 0,
            'name' => 'All Modules',
        ]],
    ]];

    foreach (($meta['modulegroups'] ?? []) as $group) {
        $items = [];
        foreach (($group->items ?? []) as $item) {
            $items[] = [
                'id' => (int)($item->id ?? 0),
                'name' => trim((string)($item->name ?? '')),
            ];
        }

        if (!empty($items)) {
            $modulegroups[] = [
                'label' => trim((string)($group->label ?? 'Modules')),
                'items' => $items,
            ];
        }
    }

    return [
        'filters' => [
            'courseid' => $resolvedcourseid,
        ],
        'courses' => array_values(array_filter($courses, static function(array $course): bool {
            return !empty($course['id']) && $course['fullname'] !== '';
        })),
        'departments' => $departments,
        'modules' => $modules,
        'modulegroups' => $modulegroups,
    ];
}

/**
 * Resolve a module id against available metadata.
 *
 * @param int $moduleid
 * @param array<string,mixed> $meta
 * @return array{id:int,name:string}
 */
function local_admindashboard_api_resolve_module(int $moduleid, array $meta): array {
    foreach (($meta['modules'] ?? []) as $module) {
        $mid = (int)($module['id'] ?? 0);
        if ($mid === $moduleid) {
            return [
                'id' => $moduleid,
                'name' => trim((string)($module['name'] ?? 'All Modules')),
            ];
        }
    }

    return [
        'id' => 0,
        'name' => 'All Modules',
    ];
}

/**
 * Filter block returned to the mobile app (snake_case keys; includes modulename for UI copy).
 *
 * @return array{courseid:int,department:string,moduleid:int,modulename:string}
 */
function local_admindashboard_api_build_mobile_filters(int $resolvedcourseid, string $normalizeddepartment, int $moduleid): array {
    $meta = $resolvedcourseid > 0 ? local_admindashboard_api_build_meta($resolvedcourseid) : [
        'modules' => [
            ['id' => 0, 'name' => 'All Modules'],
        ],
    ];
    $resolvedmodule = local_admindashboard_api_resolve_module($moduleid, $meta);

    return [
        'courseid' => $resolvedcourseid,
        'department' => $normalizeddepartment === '' ? 'All Departments' : $normalizeddepartment,
        'moduleid' => $moduleid,
        'modulename' => $resolvedmodule['name'],
    ];
}

/**
 * Build the canonical dashboard filters and metrics payload.
 *
 * @param int $courseid
 * @param string $department
 * @param int $moduleid
 * @return array<string,mixed>
 */
function local_admindashboard_api_get_dashboard_metrics(int $courseid, string $department, int $moduleid = 0): array {
    $resolvedcourseid = $courseid > 0 ? local_admindashboard_api_resolve_courseid($courseid) : 0;
    $normalizeddepartment = local_admindashboard_api_normalize_department($department);
    $meta = local_admindashboard_api_build_meta($resolvedcourseid);
    $resolvedmodule = $resolvedcourseid > 0 ? local_admindashboard_api_resolve_module($moduleid, $meta) : ['id' => 0, 'name' => 'All Modules'];
    $metrics = local_admindashboard_get_metrics($resolvedcourseid, $normalizeddepartment, $resolvedmodule['id']);

    return [
        'courseid' => $resolvedcourseid,
        'department' => $normalizeddepartment === '' ? 'All Departments' : $normalizeddepartment,
        'moduleid' => $resolvedmodule['id'],
        'modulename' => $resolvedmodule['name'],
        'metrics' => $metrics,
        'courses' => $meta['courses'],
        'departments' => $meta['departments'],
        'modules' => $meta['modules'],
        'modulegroups' => $meta['modulegroups'],
    ];
}

/**
 * Convert a numeric risk score into a mobile-friendly severity label.
 *
 * @param int $riskscore
 * @param int $reasoncount
 * @return string
 */
function local_admindashboard_api_get_risk_level(int $riskscore, int $reasoncount): string {
    if ($riskscore >= 70 || $reasoncount >= 3) {
        return 'high';
    }

    if ($riskscore >= 35 || $reasoncount >= 2) {
        return 'medium';
    }

    return 'low';
}

/**
 * Transform at-risk participants from the dashboard metrics payload.
 *
 * @param array<string,mixed> $metrics
 * @return array<int,array<string,mixed>>
 */
function local_admindashboard_api_format_at_risk_users(array $metrics): array {
    $rows = [];
    foreach (($metrics['at_risk_participants'] ?? []) as $row) {
        $reasons = [];
        if (!empty($row['reasons']) && is_array($row['reasons'])) {
            $reasons = array_values(array_filter(array_map('trim', $row['reasons'])));
        }

        $riskscore = (int)($row['risk_score'] ?? 0);
        $rows[] = [
            'id' => (int)($row['userid'] ?? 0),
            'fullname' => trim((string)($row['name'] ?? '')),
            'department' => trim((string)($row['department'] ?? '')) ?: 'Unassigned',
            'coursefullname' => trim((string)($row['coursefullname'] ?? '')),
            'progress' => max(0, min(100, (int)round((float)($row['completion_pct'] ?? 0)))),
            'risklevel' => local_admindashboard_api_get_risk_level($riskscore, count($reasons)),
            'riskscore' => $riskscore,
            'days_since_login' => (int)($row['days_since_login'] ?? 0),
            'reasons' => $reasons,
        ];
    }

    return $rows;
}

/**
 * Format KPI metrics with percentage-of-enrollments (or participants fallback) for the mobile app.
 *
 * @param array $metrics  Raw metrics array from local_admindashboard_get_metrics()
 * @param int   $courseid Resolved course id (0 = overview)
 * @return array
 */
function local_admindashboard_api_format_kpis(array $metrics, int $courseid): array {
    $participants = (int)($metrics['participants'] ?? 0);
    $totalenrollments = (int)($metrics['total_enrollments'] ?? 0);
    // Match web dashboard KPI logic: when total enrollment exceeds current active
    // enrolment rows (e.g. suspended users counted in participants only), dividing only
    // by total_enrollments makes pct_passed + pct_dropped etc. inconsistent with counts.
    $denominator = $totalenrollments > 0 ? $totalenrollments : $participants;
    if ($participants > $totalenrollments && $totalenrollments > 0) {
        $denominator = $participants;
    }

    $pct = static function(int $n) use ($denominator): ?float {
        return ($denominator > 0) ? round(($n / $denominator) * 100, 1) : null;
    };

    $attempted       = (int)($metrics['attempted']       ?? 0);
    $passed          = (int)($metrics['passed']          ?? 0);
    $certified       = (int)($metrics['certified']       ?? 0);
    $failed          = (int)($metrics['failed']          ?? 0);
    $droppedmidway   = (int)($metrics['dropped_midway']  ?? 0);
    $notattempted    = (int)($metrics['not_attempted']   ?? max(0, $totalenrollments - $attempted));
    $resignedmidcourse = (int)($metrics['resigned_midcourse'] ?? 0);

    return [
        'participants'       => $participants,
        'total_enrollments'  => $totalenrollments,
        'attempted'          => $attempted,
        'passed'             => $passed,
        'certified'          => $certified,
        'failed'             => $failed,
        'dropped_midway'     => $droppedmidway,
        'not_attempted'      => $notattempted,
        'resigned_midcourse' => $resignedmidcourse,   // only meaningful when courseid > 0
        'completion_rate'    => (int)($metrics['completion_rate']  ?? 0),
        'pending_modules'    => (int)($metrics['pending_modules']  ?? 0),
        // percentage fields (null when denominator = 0; uses total_enrollments when present)
        'pct_attempted'          => $pct($attempted),
        'pct_passed'             => $pct($passed),
        'pct_certified'          => $pct($certified),
        'pct_failed'             => $pct($failed),
        'pct_dropped_midway'     => $pct($droppedmidway),
        'pct_not_attempted'      => $pct($notattempted),
        'pct_resigned_midcourse' => $courseid > 0 ? $pct($resignedmidcourse) : null,
    ];
}

/**
 * Build KPI payload for the mobile app.
 *
 * @param int $courseid
 * @param string $department
 * @param int $moduleid
 * @return array
 */
function local_admindashboard_api_build_kpis(int $courseid, string $department, int $moduleid = 0): array {
    $payload = local_admindashboard_api_get_dashboard_metrics($courseid, $department, $moduleid);
    $metrics = $payload['metrics'];

    return [
        'filters' => [
            'courseid' => $payload['courseid'],
            'department' => $payload['department'],
            'moduleid' => $payload['moduleid'],
            'modulename' => $payload['modulename'],
        ],
        'kpis' => local_admindashboard_api_format_kpis($metrics, $payload['courseid']),
        'trends' => $metrics['trends'] ?? [],
        'selected_modname' => (string)($metrics['selected_modname'] ?? ''),
    ];
}

/**
 * Build at-risk user payload for the mobile app.
 *
 * @param int $courseid
 * @param string $department
 * @param int $moduleid
 * @return array
 */
function local_admindashboard_api_build_at_risk(int $courseid, string $department, int $moduleid = 0): array {
    $payload = local_admindashboard_api_get_dashboard_metrics($courseid, $department, $moduleid);
    $users = local_admindashboard_api_format_at_risk_users($payload['metrics']);

    return [
        'filters' => [
            'courseid' => $payload['courseid'],
            'department' => $payload['department'],
            'moduleid' => $payload['moduleid'],
            'modulename' => $payload['modulename'],
        ],
        'users' => $users,
    ];
}

/**
 * Build the full analytics payload used by the mobile dashboard.
 *
 * @param int $courseid
 * @param string $department
 * @param int $moduleid
 * @return array<string,mixed>
 */
function local_admindashboard_api_build_analytics(int $courseid, string $department, int $moduleid = 0): array {
    $payload = local_admindashboard_api_get_dashboard_metrics($courseid, $department, $moduleid);
    $metrics = $payload['metrics'];

    $mapdepartmentchart = static function(array $rows): array {
        return array_map(static function(array $row): array {
            return [
                'department' => (string)($row['department'] ?? ''),
                'value' => (int)($row['completion'] ?? 0),
            ];
        }, $rows);
    };

    $mapseries = static function(array $rows, string $valuekey, callable $labeller): array {
        return array_map(static function(array $row) use ($valuekey, $labeller): array {
            return [
                'label' => $labeller($row),
                'value' => (float)($row[$valuekey] ?? 0),
            ];
        }, $rows);
    };

    return [
        'filters' => [
            'courseid' => $payload['courseid'],
            'department' => $payload['department'],
            'moduleid' => $payload['moduleid'],
            'modulename' => $payload['modulename'],
        ],
        'courses' => $payload['courses'],
        'departments' => $payload['departments'],
        'modules' => $payload['modules'],
        'modulegroups' => $payload['modulegroups'],
        'kpis' => local_admindashboard_api_format_kpis($metrics, $payload['courseid']),
        'trends' => $metrics['trends'] ?? [],
        'charts' => [
            'kpi_distribution' => [
                ['label' => 'Total Enrollment',          'value' => (int)($metrics['participants']        ?? 0), 'color' => 'teal'],
                ['label' => 'Resigned Midway',           'value' => (int)($metrics['resigned_midcourse'] ?? 0), 'color' => 'orange'],
                ['label' => 'Current Total Enrollment',  'value' => (int)($metrics['total_enrollments']   ?? 0), 'color' => 'blue'],
                ['label' => 'Attempted',                 'value' => (int)($metrics['attempted']           ?? 0), 'color' => 'indigo'],
                ['label' => 'Not Attempted',             'value' => (int)($metrics['not_attempted']       ?? 0), 'color' => 'navy'],
                ['label' => 'Passed',                    'value' => (int)($metrics['passed']              ?? 0), 'color' => 'green'],
                ['label' => 'Certificates Issued',       'value' => (int)($metrics['certified']           ?? 0), 'color' => 'pink'],
                ['label' => 'Failed',                    'value' => (int)($metrics['failed']              ?? 0), 'color' => 'rose'],
            ],
            'department_breakdown' => [
                'completion' => $mapdepartmentchart($metrics['bar_data_completion'] ?? []),
                'pass' => $mapdepartmentchart($metrics['bar_data_pass'] ?? []),
                'fail' => $mapdepartmentchart($metrics['bar_data_fail'] ?? []),
                'notattempted' => $mapdepartmentchart($metrics['bar_data_notattempted'] ?? []),
            ],
            'engagement' => [
                ['label' => 'Active', 'value' => (int)(($metrics['engagement']['Active'] ?? 0)), 'color' => 'green'],
                ['label' => 'Inactive', 'value' => (int)(($metrics['engagement']['Inactive'] ?? 0)), 'color' => 'rose'],
                ['label' => 'Pending', 'value' => (int)(($metrics['engagement']['Pending'] ?? 0)), 'color' => 'yellow'],
            ],
            'module_completion' => [
                ['label' => 'Completed', 'value' => (int)(($metrics['module_completion']['Completed'] ?? 0)), 'color' => 'blue'],
                ['label' => 'Incomplete', 'value' => (int)(($metrics['module_completion']['Incomplete'] ?? 0)), 'color' => 'rose'],
            ],
            'performance' => [
                'participants' => array_map(static function(array $item): array {
                    return [
                        'label' => (string)($item['name'] ?? 'Participant'),
                        'value' => (int)($item['overall'] ?? 0),
                        'department' => (string)($item['department'] ?? ''),
                        'clinicname' => (string)($item['clinicname'] ?? ''),
                        'completionpct' => (int)($item['completionpct'] ?? 0),
                    ];
                }, $metrics['performance_leaderboard']['participants'] ?? []),
                'clinics' => array_map(static function(array $item): array {
                    return [
                        'label' => (string)($item['name'] ?? 'Clinic'),
                        'value' => (int)($item['overall'] ?? 0),
                        'participantcount' => (int)($item['participantcount'] ?? 0),
                        'completionpct' => (int)($item['completionpct'] ?? 0),
                    ];
                }, $metrics['performance_leaderboard']['clinics'] ?? []),
            ],
            'content_engagement' => [
                'supervideo' => $mapseries($metrics['supervideo_watch_series'] ?? [], 'seconds', static function(array $row): string {
                    return date('M j', strtotime((string)($row['day'] ?? 'now')));
                }),
                'pdf' => $mapseries($metrics['pdf_view_series'] ?? [], 'views', static function(array $row): string {
                    return date('M j', strtotime((string)($row['day'] ?? 'now')));
                }),
            ],
            'skill_gap' => array_map(static function($label, $required, $current): array {
                return [
                    'label' => (string)$label,
                    'required' => (int)$required,
                    'current' => (int)$current,
                ];
            }, $metrics['skill_gap']['labels'] ?? [], $metrics['skill_gap']['required'] ?? [], $metrics['skill_gap']['current'] ?? []),
            'compliance_heatmap' => $metrics['compliance_heatmap'] ?? ['columns' => [], 'rows' => [], 'summary' => []],
        ],
        'at_risk' => local_admindashboard_api_format_at_risk_users($metrics),
        'live_feed' => $metrics['live_feed'] ?? [],
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        local_admindashboard_api_error(405, 'Method not allowed. Use POST only.', 405);
    }

    require_sesskey();

    $action = optional_param('action', '', PARAM_ALPHAEXT);
    if ($action === '') {
        local_admindashboard_api_error(400, 'The action parameter is required.', 400);
    }

    if ($action === 'login') {
        local_admindashboard_api_error(410, 'Password login through this legacy endpoint is disabled. Use Moodle authentication or registered external services.', 410);
    }

    switch ($action) {
        case 'get_kpis':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $department = optional_param('department', '', PARAM_TEXT);
            $moduleid = optional_param('moduleid', 0, PARAM_INT);

            local_admindashboard_api_success(local_admindashboard_api_build_kpis($courseid, $department, $moduleid));
            break;

        case 'get_at_risk':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $department = optional_param('department', '', PARAM_TEXT);
            $moduleid = optional_param('moduleid', 0, PARAM_INT);

            local_admindashboard_api_success(local_admindashboard_api_build_at_risk($courseid, $department, $moduleid));
            break;

        case 'get_departments':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            local_admindashboard_api_success([
                'filters' => [
                    'courseid' => $courseid > 0 ? local_admindashboard_api_resolve_courseid($courseid) : 0,
                ],
                'departments' => local_admindashboard_api_get_departments($courseid),
            ]);
            break;

        case 'get_meta':
            $courseid = optional_param('courseid', 0, PARAM_INT);

            local_admindashboard_api_success(local_admindashboard_api_build_meta($courseid));
            break;

        case 'get_analytics':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $department = optional_param('department', '', PARAM_TEXT);
            $moduleid = optional_param('moduleid', 0, PARAM_INT);

            local_admindashboard_api_success(local_admindashboard_api_build_analytics($courseid, $department, $moduleid));
            break;

        case 'get_upcoming_event':
            $PAGE->set_context(context_system::instance());
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $resolvedcourseid = $courseid > 0 ? local_admindashboard_api_resolve_courseid($courseid) : 0;
            local_admindashboard_api_success(local_admindashboard_get_upcoming_event($resolvedcourseid));
            break;

        case 'get_courses_overview':
            $department = optional_param('department', '', PARAM_TEXT);
            $normalizeddepartment = local_admindashboard_api_normalize_department($department);
            local_admindashboard_api_success(local_admindashboard_get_courses_overview($normalizeddepartment));
            break;

        case 'get_kpi_participants':
            $courseid  = optional_param('courseid', 0, PARAM_INT);
            $department = optional_param('department', '', PARAM_TEXT);
            $moduleid  = optional_param('moduleid', 0, PARAM_INT);
            // Accept both 'status' (mobile app param) and 'metric' (legacy param).
            $metric = optional_param('status', '', PARAM_ALPHANUMEXT);
            if ($metric === '') {
                $metric = optional_param('metric', 'attempted', PARAM_ALPHANUMEXT);
            }
            if ($metric === '') {
                $metric = 'attempted';
            }

            // Map mobile KPI keys to internal metric names.
            if ($metric === 'pending') {
                // Align with HomeScreen "Pending" metric (droppedMidway count / not-yet-attempted cohort).
                $metric = 'dropped_midway';
            }
            if ($metric === 'notattempted') {
                $metric = 'not_attempted';
            }
            if ($metric === 'resigned') {
                $metric = 'resigned_midcourse';
            }

            $resolvedcourseid  = $courseid > 0 ? local_admindashboard_api_resolve_courseid($courseid) : 0;
            $normalizeddepartment = local_admindashboard_api_normalize_department($department);

            // High-risk uses the at-risk cache, not grade rows.
            if ($metric === 'high_risk') {
                $atriskrows = local_admindashboard_get_at_risk_participants($resolvedcourseid, $normalizeddepartment, 1000);
                $participants = [];
                foreach ($atriskrows as $row) {
                    $riskscore = (int)($row['risk_score'] ?? 0);
                    $reasons   = is_array($row['reasons'] ?? null) ? $row['reasons'] : [];
                    if ($riskscore < 70 && count($reasons) < 3) {
                        continue; // skip non-high-risk
                    }
                    $participants[] = [
                        'id'               => (int)($row['userid'] ?? 0),
                        'fullname'         => trim((string)($row['name'] ?? '')),
                        'department'       => trim((string)($row['department'] ?? '')) ?: 'Unassigned',
                        'email'            => '',
                        'progress'         => 0,
                        'status'           => 'high_risk',
                        'days_since_login' => 0,
                        'coursefullname'   => '',
                    ];
                }
                $filtersout = local_admindashboard_api_build_mobile_filters($resolvedcourseid, $normalizeddepartment, $moduleid);
                $filtersout['metric'] = 'high_risk';
                $filtersout['status'] = 'high_risk';
                local_admindashboard_api_success([
                    'filters' => $filtersout,
                    'participants' => $participants,
                ]);
                break;
            }

            $rows = local_admindashboard_get_kpi_user_rows(
                $resolvedcourseid,
                $normalizeddepartment,
                $moduleid,
                $metric
            );

            $participants = array_map(static function(array $row): array {
                return [
                    'id'         => (int)($row['id'] ?? 0),
                    'fullname'   => trim((string)($row['name'] ?? '')),
                    'department' => trim((string)($row['department'] ?? '')) ?: 'Unassigned',
                    'email'      => '',
                    'progress'   => 0,
                    'status'     => '',
                    'days_since_login' => 0,
                    'coursefullname' => '',
                ];
            }, $rows);

            $filtersout = local_admindashboard_api_build_mobile_filters($resolvedcourseid, $normalizeddepartment, $moduleid);
            $filtersout['metric'] = $metric;
            $filtersout['status'] = $metric;

            local_admindashboard_api_success([
                'filters' => $filtersout,
                'participants' => $participants,
            ]);
            break;
        default:
            local_admindashboard_api_error(400, 'Unknown action requested.', 400);    }
} catch (invalid_parameter_exception $exception) {
    local_admindashboard_api_error(400, 'One or more request parameters are invalid.', 400);
} catch (Throwable $exception) {
    local_admindashboard_api_error(500, 'An unexpected server error occurred.', 500);
}
