<?php
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->libdir . '/pdflib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

require_login();
admindash_require_view_access();
require_sesskey();

$format = required_param('format', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));

$metrics = admindash_get_metrics($courseid, $department);

if ($format === 'csv') {
    $filename = 'moodle_admin_dashboard_' . date('Ymd_His') . '.csv';

    $csv = new csv_export_writer();
    $csv->set_filename($filename);

    $csv->add_data(['Metric', 'Value']);
    $csv->add_data(['Total Students', $metrics['total_students']]);
    $csv->add_data(['Active Courses', $metrics['active_courses']]);
    $csv->add_data(['Completion Rate', $metrics['completion_rate'] . '%']);
    $csv->add_data(['Pending Modules', $metrics['pending_modules']]);

    $csv->add_data([]);
    $csv->add_data(['Course Completion by Department', 'Completion %']);
    foreach ($metrics['bar_data'] as $row) {
        $csv->add_data([$row['department'], $row['completion']]);
    }

    $csv->add_data([]);
    $csv->add_data(['Student Engagement', 'Count']);
    foreach ($metrics['engagement'] as $label => $count) {
        $csv->add_data([$label, $count]);
    }

    $csv->download_file();
    exit;
}

if ($format === 'pdf') {
    $pdf = new pdf();
    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor('Moodle Admin Dashboard');
    $pdf->SetTitle('Admin Dashboard Export');
    $pdf->SetSubject('Admin Dashboard Metrics');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    $title = 'Admin Dashboard Export';
    $subtitle = 'Generated: ' . userdate(time());

    $html = '<h2>' . s($title) . '</h2>';
    $html .= '<p>' . s($subtitle) . '</p>';

    if ($courseid > 0) {
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', IGNORE_MISSING);
        if ($course) {
            $html .= '<p><b>Course:</b> ' . s($course->fullname) . '</p>';
        }
    }
    if ($department !== '') {
        $html .= '<p><b>Department:</b> ' . s($department) . '</p>';
    }

    $html .= '<h3>KPIs</h3>';
    $html .= '<table border="1" cellpadding="6" cellspacing="0">
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Total Students</td><td>' . (int)$metrics['total_students'] . '</td></tr>
        <tr><td>Active Courses</td><td>' . (int)$metrics['active_courses'] . '</td></tr>
        <tr><td>Completion Rate</td><td>' . (int)$metrics['completion_rate'] . '%</td></tr>
        <tr><td>Pending Modules</td><td>' . (int)$metrics['pending_modules'] . '</td></tr>
    </table>';

    $html .= '<h3>Course Completion by Department</h3>';
    $html .= '<table border="1" cellpadding="6" cellspacing="0">
        <tr><th>Department</th><th>Completion %</th></tr>';
    foreach ($metrics['bar_data'] as $row) {
        $html .= '<tr><td>' . s($row['department']) . '</td><td>' . (int)$row['completion'] . '</td></tr>';
    }
    $html .= '</table>';

    $html .= '<h3>Student Engagement</h3>';
    $html .= '<table border="1" cellpadding="6" cellspacing="0">
        <tr><th>Status</th><th>Count</th></tr>';
    foreach ($metrics['engagement'] as $label => $count) {
        $html .= '<tr><td>' . s($label) . '</td><td>' . (int)$count . '</td></tr>';
    }
    $html .= '</table>';

    $pdf->writeHTML($html);

    $outname = 'moodle_admin_dashboard_' . date('Ymd_His') . '.pdf';
    $pdf->Output($outname, 'D');
    exit;
}

throw new moodle_exception('invalidparameter', 'core_error', '', 'format');
