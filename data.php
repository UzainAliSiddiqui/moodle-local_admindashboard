<?php
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

require_login();
$PAGE->set_context(context_system::instance());
admindash_require_view_access();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$mode = optional_param('mode', 'metrics', PARAM_ALPHANUMEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$moduleid = optional_param('moduleid', 0, PARAM_INT);

$jsonflags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonflags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

if ($mode === 'meta') {
    $out = json_encode(admindash_get_meta($courseid), $jsonflags);
    echo ($out !== false) ? $out : '{}';
    exit;
}

if ($mode === 'feedback_insights') {
    $out = json_encode(admindash_get_feedback_insights($courseid), $jsonflags);
    echo ($out !== false) ? $out : '{}';
    exit;
}

if ($mode === 'live_feed') {
    $out = json_encode([
        'live_feed' => admindash_get_live_feed_rows($courseid, $department, 8),
    ], $jsonflags);
    echo ($out !== false) ? $out : '{}';
    exit;
}


if ($mode === 'courses_overview') {
    $out = json_encode(admindash_get_courses_overview($department), $jsonflags);
    echo ($out !== false) ? $out : '{}';
    exit;
}

if ($mode === 'multi_course_leaders') {
    $courseidsraw = optional_param('courseids', '', PARAM_SEQUENCE);
    $courseids = array_filter(array_map('intval', explode(',', $courseidsraw)));
    $out = json_encode(admindash_get_multi_course_leaders($courseids, $department, 10), $jsonflags);
    echo ($out !== false) ? $out : '{}';
    exit;
}

if ($mode === 'upcoming_event') {
    $out = json_encode(admindash_get_upcoming_event($courseid), $jsonflags);
    echo ($out !== false) ? $out : '{}';
    exit;
}

try {
    $payload = admindash_get_metrics($courseid, $department, $moduleid);
    $out = json_encode($payload, $jsonflags);
    if ($out === false) {
        throw new \RuntimeException('json_encode failed: ' . json_last_error_msg());
    }
    echo $out;
} catch (\Throwable $e) {
    http_response_code(500);
    $err = json_encode([
        'error' => 'metrics_failed',
        'message' => $e->getMessage(),
    ], $jsonflags);
    echo ($err !== false) ? $err : '{"error":"metrics_failed"}';
}
