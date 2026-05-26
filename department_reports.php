<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

admindash_setup_page('/local/admindashboard/department_reports.php', 'Department Completion Report', 'reports.departmentcompletion');
admindash_render_header('reports.departmentcompletion');
?>

<h2 class="mb-3">Department Completion Report</h2>

<?php
$courseid = optional_param('courseid', 0, PARAM_INT);
$meta = admindash_get_meta($courseid);
?>

<form method="get" class="admindash-filters admindash-card">
    <div class="title">Filters</div>

    <label class="mb-0" for="courseSelect">Select Course</label>
    <select id="courseSelect" name="courseid" class="form-select" style="max-width:360px">
        <option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
        <?php foreach ($meta['courses'] as $course): ?>
            <option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
                <?php echo s($course['fullname']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
</form>

<?php
$userfilter = admindash_build_user_filter('');
[$userwhere, $userparams] = $userfilter;

$params = $userparams;
$coursefilter = '';
if ($courseid > 0) {
    $coursefilter = ' AND c.id = :courseid';
    $params['courseid'] = $courseid;
}

// Use a DISTINCT enrolled-user list so multiple enrol methods don't duplicate counts.
$enrolledsql = "SELECT DISTINCT u.id AS userid, u.department AS department, c.id AS courseid
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                  JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                                 WHERE {$userwhere}
                   AND u.department <> ''
                   {$coursefilter}";

$sql = "SELECT dept.department,
               COUNT(1) AS enrolments,
            ROUND(100 * SUM(
                CASE
                WHEN (cc.timecompleted IS NOT NULL AND cc.timecompleted > 0)
                    THEN 1 ELSE 0
                END
            ) / COUNT(1)) AS completion
        FROM ({$enrolledsql}) dept
    LEFT JOIN {course_completions} cc ON cc.userid = dept.userid AND cc.course = dept.courseid
     GROUP BY dept.department
     ORDER BY enrolments DESC";

// If a single course is selected, show Pass % based on the course's last module's last quiz.
// This matches Dashboard/Course Analytics KPI logic.
if ($courseid > 0) {
    $assessment = admindash_pick_course_assessment_quiz($courseid, '', []);
    if ($assessment && !empty($assessment->gradeitemid) && !empty($assessment->gradepass)) {
       $params['gradeitemid_dept_pass'] = (int)$assessment->gradeitemid;
       $params['gradepass_dept_pass'] = (float)$assessment->gradepass;

       $sql = "SELECT dept.department,
                   COUNT(1) AS enrolments,
                   ROUND(100 * SUM(
                       CASE
                       WHEN (gg.finalgrade IS NOT NULL AND gg.finalgrade >= :gradepass_dept_pass)
                          THEN 1 ELSE 0
                       END
                   ) / COUNT(1)) AS completion
               FROM ({$enrolledsql}) dept
           LEFT JOIN {grade_grades} gg ON gg.itemid = :gradeitemid_dept_pass AND gg.userid = dept.userid
            GROUP BY dept.department
            ORDER BY enrolments DESC";
    }
}

$rows = $DB->get_records_sql($sql, $params);

$table = new html_table();
$table->attributes['class'] = 'table table-striped table-hover admindash-card admindash-report-table';
$table->head = ['Department', 'Enrolments', ($courseid > 0 ? 'Pass %' : 'Completion %')];
foreach ($rows as $r) {
    $table->data[] = [s($r->department), (int)$r->enrolments, (int)$r->completion];
}

echo html_writer::tag('div', html_writer::table($table), ['class' => 'mt-3']);
?>

<div class="admindash-card bg-white p-3 mt-3">
    <h5 class="mb-2">Reports</h5>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="<?php echo (new moodle_url('/local/admindashboard/department_reports.php')); ?>">Completion</a>
        <a class="btn btn-outline-primary" href="<?php echo (new moodle_url('/local/admindashboard/department_reports_engagement.php')); ?>">Engagement</a>
    </div>
</div>

<?php
admindash_render_footer();
