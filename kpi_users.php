<?php
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

require_login();
admindash_require_view_access();

header('Content-Type: application/json; charset=utf-8');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$moduleid = optional_param('moduleid', 0, PARAM_INT);
$metric = strtolower(trim(optional_param('metric', '', PARAM_ALPHANUMEXT)));

$allowedmetrics = ['participants', 'attempted', 'passed', 'certified', 'failed', 'dropped_midway', 'not_attempted', 'notattempted', 'resigned_midcourse', 'total_enrollments'];
if (!in_array($metric, $allowedmetrics, true)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid KPI metric.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $users = admindash_get_kpi_user_rows($courseid, $department, $moduleid, $metric);
    $payload = [
        'metric' => $metric,
        'count' => count($users),
        'users' => array_values($users),
    ];
    $jsonflags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonflags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $encoded = json_encode($payload, $jsonflags);
    if ($encoded === false) {
        throw new \RuntimeException('json_encode failed: ' . json_last_error_msg());
    }
    echo $encoded;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load KPI users.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}