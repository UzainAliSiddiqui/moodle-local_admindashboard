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
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

local_admindashboard_setup_page('/local/admindashboard/department_reports_engagement.php', 'Department Engagement Report', 'reports.departmentengagement');
local_admindashboard_render_header('reports.departmentengagement');

$courseid = optional_param('courseid', 0, PARAM_INT);

$meta = local_admindashboard_get_meta($courseid);

[$userwhere, $userparams] = local_admindashboard_build_user_filter('');

$params = $userparams;
$coursefilter = '';
if ($courseid > 0) {
    // Engagement is user-based; for course-scoped engagement we restrict to enrolled users in course.
    $coursefilter = "AND u.id IN (
                SELECT DISTINCT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e ON e.id = ue.enrolid
         WHERE ue.status = 0 AND e.status = 0 AND e.courseid = :courseid
    )";
        $params['courseid'] = $courseid;
}

$now = time();
$activesince = $now - (7 * 24 * 60 * 60);
$params['activesince_gt'] = $activesince;
$params['activesince_le'] = $activesince;

$sql = "SELECT u.department,
                             SUM(CASE WHEN u.lastaccess > :activesince_gt THEN 1 ELSE 0 END) AS active,
               SUM(CASE WHEN u.lastaccess = 0 THEN 1 ELSE 0 END) AS pending,
                             SUM(CASE WHEN u.lastaccess <= :activesince_le AND u.lastaccess > 0 THEN 1 ELSE 0 END) AS inactive
          FROM {user} u
                 WHERE {$userwhere}
           AND u.department <> ''
           {$coursefilter}
      GROUP BY u.department
      ORDER BY active DESC";

$rows = $DB->get_records_sql($sql, $params);

$table = new html_table();
$table->attributes['class'] = 'table table-striped table-hover admindash-card admindash-report-table';
$table->head = ['Department', 'Active (7d)', 'Inactive', 'Pending'];
foreach ($rows as $r) {
    $table->data[] = [s($r->department), (int)$r->active, (int)$r->inactive, (int)$r->pending];
}

echo html_writer::tag('h2', 'Department Engagement Report', ['class' => 'mb-3']);

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'admindash-filters admindash-card']);
echo html_writer::div('Filters', 'title');
echo html_writer::label('Select Course', 'courseid', false, ['class' => 'mb-0']);
echo html_writer::start_tag('select', ['name' => 'courseid', 'id' => 'courseid', 'class' => 'form-select', 'style' => 'max-width:360px']);
echo html_writer::tag('option', 'All Courses', ['value' => 0, 'selected' => ($courseid === 0) ? 'selected' : null]);
foreach ($meta['courses'] as $course) {
    $attrs = ['value' => (int)$course['id']];
    if ($courseid === (int)$course['id']) {
        $attrs['selected'] = 'selected';
    }
    echo html_writer::tag('option', s($course['fullname']), $attrs);
}
echo html_writer::end_tag('select');
echo html_writer::tag('button', 'Apply', ['type' => 'submit', 'class' => 'btn btn-primary', 'style' => 'margin-left:auto']);
echo html_writer::end_tag('form');

echo html_writer::tag('div', html_writer::table($table), ['class' => 'mt-3']);

echo html_writer::tag('div',
    html_writer::tag('h5', 'Reports', ['class' => 'mb-2']) .
    html_writer::div(
        html_writer::link(new moodle_url('/local/admindashboard/department_reports.php'), 'Completion', ['class' => 'btn btn-outline-primary']) .
        ' ' .
        html_writer::link(new moodle_url('/local/admindashboard/department_reports_engagement.php'), 'Engagement', ['class' => 'btn btn-outline-primary']),
        'd-flex gap-2 flex-wrap'
    ),
    ['class' => 'admindash-card bg-white p-3 mt-3']
);

local_admindashboard_render_footer();
