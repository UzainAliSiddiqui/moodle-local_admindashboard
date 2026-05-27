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
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_sesskey();
local_admindashboard_require_view_access();

header('Content-Type: application/json; charset=utf-8');

$question = trim(optional_param('question', '', PARAM_TEXT));
$currentcourseid = optional_param('courseid', 0, PARAM_INT);
$currentdepartment = trim(optional_param('department', '', PARAM_TEXT));
$currentmoduleid = optional_param('moduleid', 0, PARAM_INT);

if ($question === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a question.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$apikey = trim((string)get_config('local_admindashboard', 'groq_apikey'));
$model = trim((string)get_config('local_admindashboard', 'groq_model')) ?: 'llama-3.3-70b-versatile';
$endpoint = trim((string)get_config('local_admindashboard', 'groq_endpoint')) ?: 'https://api.groq.com/openai/v1/chat/completions';

if ($apikey === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Groq API key is not configured for Ask the Data.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function local_admindashboard_ai_normalize_text(string $value): string {
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/\b(quiz|module|course|department)\b/i', '', $value);
    $value = preg_replace('/[^\pL\pN\s]+/u', ' ', (string)$value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    return trim((string)$value);
}

function local_admindashboard_ai_extract_json_object(string $text): array {
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return [];
    }

    $snippet = substr($text, $start, $end - $start + 1);
    $decoded = json_decode($snippet, true);
    return is_array($decoded) ? $decoded : [];
}

function local_admindashboard_ai_post_to_groq(string $endpoint, string $apikey, string $model, array $messages): array {
    $curl = new curl();
    $payload = json_encode([
        'model' => $model,
        'temperature' => 0.1,
        'messages' => $messages,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $response = $curl->post($endpoint, $payload, [
        'CURLOPT_HTTPHEADER' => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ],
        'CURLOPT_TIMEOUT' => 40,
    ]);

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        throw new moodle_exception('invalidresponse', 'error', '', null, 'Invalid Groq response');
    }

    return $decoded;
}

function local_admindashboard_ai_find_named_match(?string $requested, array $items, string $labelkey = 'label', string $idkey = 'id'): ?array {
    if ($requested === null) {
        return null;
    }

    $requested = trim($requested);
    if ($requested === '' || $requested === '__current__') {
        return null;
    }
    if ($requested === '__all__') {
        return ['id' => 0, 'label' => 'All'];
    }

    $normalizedrequest = local_admindashboard_ai_normalize_text($requested);
    foreach ($items as $item) {
        $label = (string)($item[$labelkey] ?? '');
        if ($label !== '' && local_admindashboard_ai_normalize_text($label) === $normalizedrequest) {
            return ['id' => (int)($item[$idkey] ?? 0), 'label' => $label];
        }
    }
    foreach ($items as $item) {
        $label = (string)($item[$labelkey] ?? '');
        $normalizedlabel = local_admindashboard_ai_normalize_text($label);
        if ($label !== '' && ($normalizedrequest !== '') && (str_contains($normalizedlabel, $normalizedrequest) || str_contains($normalizedrequest, $normalizedlabel))) {
            return ['id' => (int)($item[$idkey] ?? 0), 'label' => $label];
        }
    }

    return null;
}

try {
    $allmeta = local_admindashboard_get_meta(0);
    $currentmeta = local_admindashboard_get_meta($currentcourseid);

    $courses = array_map(static function(array $course): array {
        return ['id' => (int)$course['id'], 'label' => (string)$course['fullname']];
    }, $allmeta['courses'] ?? []);
    $departments = array_map(static function(string $department): array {
        return ['id' => 0, 'label' => $department];
    }, $allmeta['departments'] ?? []);

    $currentcourselabel = 'All Courses';
    foreach ($courses as $course) {
        if ((int)$course['id'] === $currentcourseid) {
            $currentcourselabel = $course['label'];
            break;
        }
    }

    $modulecatalog = [];
    foreach (($currentmeta['modules'] ?? []) as $module) {
        $modulecatalog[] = ['id' => (int)$module->id, 'label' => (string)$module->name];
    }

    $messages = [
        [
            'role' => 'system',
            'content' => 'You convert management analytics questions into dashboard filter intent. Return only valid JSON with keys: course_name, department_name, module_name, metric, chart, notes. Use __current__ for current filter, __all__ for all. metric must be one of pass_rate, fail_rate, completion_rate, participants, attempted, passed, failed, certified, not_attempted, at_risk_count, engagement, module_completion. chart must be one of none, department_bar, engagement_line, module_completion. notes must be an array of short strings.'
        ],
        [
            'role' => 'user',
            'content' => json_encode([
                'question' => $question,
                'current_filters' => [
                    'course' => $currentcourselabel,
                    'department' => $currentdepartment !== '' ? $currentdepartment : 'All Departments',
                    'module' => $currentmoduleid > 0 ? 'Selected module' : 'All Modules',
                ],
                'available_courses' => array_column($courses, 'label'),
                'available_departments' => array_column($departments, 'label'),
                'current_course_modules' => array_column($modulecatalog, 'label'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ],
    ];

    $intentresponse = local_admindashboard_ai_post_to_groq($endpoint, $apikey, $model, $messages);
    $intentcontent = (string)($intentresponse['choices'][0]['message']['content'] ?? '');
    $intent = local_admindashboard_ai_extract_json_object($intentcontent);

    $resolvedcourseid = $currentcourseid;
    $resolvedcourselabel = $currentcourselabel;
    $coursematch = local_admindashboard_ai_find_named_match($intent['course_name'] ?? '__current__', $courses);
    if (($intent['course_name'] ?? '') === '__all__') {
        $resolvedcourseid = 0;
        $resolvedcourselabel = 'All Courses';
    } else if ($coursematch) {
        $resolvedcourseid = (int)$coursematch['id'];
        $resolvedcourselabel = (string)$coursematch['label'];
    }

    $resolveddepartment = $currentdepartment;
    $departmentmatch = local_admindashboard_ai_find_named_match($intent['department_name'] ?? '__current__', $departments, 'label', 'id');
    if (($intent['department_name'] ?? '') === '__all__') {
        $resolveddepartment = '';
    } else if ($departmentmatch) {
        $resolveddepartment = (string)$departmentmatch['label'];
    }

    $resolvedmoduleid = $currentmoduleid;
    $resolvedmodulelabel = 'All Modules';
    if ($resolvedcourseid > 0) {
        $resolvedmeta = local_admindashboard_get_meta($resolvedcourseid);
        $modules = [];
        foreach (($resolvedmeta['modules'] ?? []) as $module) {
            $modules[] = ['id' => (int)$module->id, 'label' => (string)$module->name];
            if ((int)$module->id === $resolvedmoduleid) {
                $resolvedmodulelabel = (string)$module->name;
            }
        }
        if (($intent['module_name'] ?? '') === '__all__') {
            $resolvedmoduleid = 0;
            $resolvedmodulelabel = 'All Modules';
        } else {
            $modulematch = local_admindashboard_ai_find_named_match($intent['module_name'] ?? '__current__', $modules);
            if ($modulematch) {
                $resolvedmoduleid = (int)$modulematch['id'];
                $resolvedmodulelabel = (string)$modulematch['label'];
            }
        }
    } else {
        $resolvedmoduleid = 0;
        $resolvedmodulelabel = 'All Modules';
    }

    $metrics = local_admindashboard_get_metrics($resolvedcourseid, $resolveddepartment, $resolvedmoduleid);
    $metric = (string)($intent['metric'] ?? 'completion_rate');
    $chartrequest = (string)($intent['chart'] ?? 'none');
    $notes = is_array($intent['notes'] ?? null) ? array_values(array_filter(array_map('strval', $intent['notes']))) : [];

    $participants = (int)($metrics['participants'] ?? 0);
    $attempted = (int)($metrics['attempted'] ?? 0);
    $passed = (int)($metrics['passed'] ?? 0);
    $failed = (int)($metrics['failed'] ?? 0);
    $certified = (int)($metrics['certified'] ?? 0);
    $totalenrollments = (int)($metrics['total_enrollments'] ?? 0);
    $pctdenom = $totalenrollments > 0 ? $totalenrollments : $participants;
    $notattemptedcount = max(0, $participants - $attempted);
    $atriskcount = is_array($metrics['at_risk_participants'] ?? null) ? count($metrics['at_risk_participants']) : 0;

    $finddeptvalue = static function(array $rows, string $department): ?float {
        foreach ($rows as $row) {
            if (strcasecmp((string)($row['department'] ?? ''), $department) === 0) {
                return (float)($row['completion'] ?? 0);
            }
        }
        return null;
    };

    $completionrate = $resolveddepartment !== ''
        ? $finddeptvalue($metrics['bar_data_completion'] ?? [], $resolveddepartment)
        : (float)($metrics['completion_rate'] ?? 0);
    $passrate = $resolveddepartment !== ''
        ? $finddeptvalue($metrics['bar_data_pass'] ?? [], $resolveddepartment)
        : ($pctdenom > 0 ? round(($passed / $pctdenom) * 100, 1) : 0.0);
    $failrate = $resolveddepartment !== ''
        ? $finddeptvalue($metrics['bar_data_fail'] ?? [], $resolveddepartment)
        : ($pctdenom > 0 ? round(($failed / $pctdenom) * 100, 1) : 0.0);
    $notattemptedrate = $resolveddepartment !== ''
        ? $finddeptvalue($metrics['bar_data_notattempted'] ?? [], $resolveddepartment)
        : ($pctdenom > 0 ? round(($notattemptedcount / $pctdenom) * 100, 1) : 0.0);

    $answer = '';
    switch ($metric) {
        case 'pass_rate':
            $answer = 'Pass rate is ' . rtrim(rtrim(number_format((float)$passrate, 1, '.', ''), '0'), '.') . '%.';
            break;
        case 'fail_rate':
            $answer = 'Fail rate is ' . rtrim(rtrim(number_format((float)$failrate, 1, '.', ''), '0'), '.') . '%.';
            break;
        case 'completion_rate':
            $answer = 'Completion rate is ' . rtrim(rtrim(number_format((float)$completionrate, 1, '.', ''), '0'), '.') . '%.';
            break;
        case 'participants':
            $answer = 'There are ' . number_format($participants) . ' participants.';
            break;
        case 'attempted':
            $answer = number_format($attempted) . ' participants have attempted the assessment.';
            break;
        case 'passed':
            $answer = number_format($passed) . ' participants have passed.';
            break;
        case 'failed':
            $answer = number_format($failed) . ' participants have failed.';
            break;
        case 'certified':
            $answer = number_format($certified) . ' participants are certified.';
            break;
        case 'not_attempted':
            $answer = number_format($notattemptedcount) . ' participants have not attempted yet, which is ' . rtrim(rtrim(number_format((float)$notattemptedrate, 1, '.', ''), '0'), '.') . '%.';
            break;
        case 'at_risk_count':
            $answer = number_format($atriskcount) . ' participants are currently flagged as at-risk.';
            break;
        case 'module_completion':
            $completed = (int)($metrics['module_completion']['Completed'] ?? 0);
            $pending = (int)($metrics['module_completion']['Pending'] ?? 0);
            $answer = 'Module completion shows ' . number_format($completed) . ' completed and ' . number_format($pending) . ' pending.';
            break;
        case 'engagement':
            $active = (int)($metrics['engagement']['Active'] ?? 0);
            $inactive = (int)($metrics['engagement']['Inactive'] ?? 0);
            $pendingeng = (int)($metrics['engagement']['Pending'] ?? 0);
            $answer = 'Engagement currently shows ' . number_format($active) . ' active, ' . number_format($inactive) . ' inactive, and ' . number_format($pendingeng) . ' pending participants.';
            break;
        default:
            $answer = 'Current snapshot shows ' . number_format($participants) . ' participants, ' . number_format($passed) . ' passed, ' . number_format($failed) . ' failed, and ' . number_format($atriskcount) . ' at-risk participants.';
            break;
    }

    $chart = null;
    if ($chartrequest === 'department_bar') {
        $series = $metrics['bar_data_completion'] ?? [];
        $label = 'Completion %';
        if ($metric === 'pass_rate') {
            $series = $metrics['bar_data_pass'] ?? [];
            $label = 'Pass %';
        } else if ($metric === 'fail_rate') {
            $series = $metrics['bar_data_fail'] ?? [];
            $label = 'Fail %';
        } else if ($metric === 'not_attempted') {
            $series = $metrics['bar_data_notattempted'] ?? [];
            $label = 'Not Attempted %';
        }
        if (!empty($series)) {
            $chart = [
                'type' => 'bar',
                'title' => $label . ' by Department',
                'labels' => array_values(array_map(static function(array $row): string {
                    return (string)($row['department'] ?? '');
                }, $series)),
                'datasets' => [[
                    'label' => $label,
                    'data' => array_values(array_map(static function(array $row): float {
                        return (float)($row['completion'] ?? 0);
                    }, $series)),
                ]],
            ];
        }
    } else if ($chartrequest === 'engagement_line') {
        $series = $metrics['pdf_view_series'] ?? [];
        if (!empty($series)) {
            $chart = [
                'type' => 'line',
                'title' => 'PDF Views Trend',
                'labels' => array_values(array_map(static function(array $row): string {
                    return (string)($row['day'] ?? '');
                }, $series)),
                'datasets' => [[
                    'label' => 'PDF Views',
                    'data' => array_values(array_map(static function(array $row): float {
                        return (float)($row['views'] ?? 0);
                    }, $series)),
                ]],
            ];
        }
    } else if ($chartrequest === 'module_completion') {
        $series = $metrics['module_completion'] ?? [];
        if (!empty($series)) {
            $chart = [
                'type' => 'bar',
                'title' => 'Module Completion Breakdown',
                'labels' => array_values(array_keys($series)),
                'datasets' => [[
                    'label' => 'Participants',
                    'data' => array_values(array_map('floatval', array_values($series))),
                ]],
            ];
        }
    }

    if (preg_match('/\b(this month|monthly|month)\b/i', $question)) {
        $notes[] = 'Month-specific slicing is not modelled separately in the current dashboard payload, so this answer uses the live filtered snapshot.';
    }

    echo json_encode([
        'answer' => $answer,
        'resolved_filters' => [
            'course' => $resolvedcourselabel,
            'department' => $resolveddepartment !== '' ? $resolveddepartment : 'All Departments',
            'module' => $resolvedmodulelabel,
        ],
        'metric' => $metric,
        'chart' => $chart,
        'notes' => $notes,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Ask the Data request failed.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}