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

local_admindashboard_setup_page('/local/admindashboard/user_progress.php', 'User Progress Report', 'reports.userprogress');
local_admindashboard_render_header('reports.userprogress');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$q = trim(optional_param('q', '', PARAM_TEXT));
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 25;

$meta = local_admindashboard_get_meta($courseid);
?>

<h2 class="mb-3">User Progress Report</h2>

<form method="get" class="admindash-filters admindash-card">
    <div class="title">Filters</div>

    <label class="mb-0" for="courseSelect">Select Course</label>
    <select id="courseSelect" name="courseid" class="form-select" style="max-width:320px">
        <option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
        <?php foreach ($meta['courses'] as $course): ?>
            <option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
                <?php echo s($course['fullname']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="mb-0" for="deptSelect" style="margin-left:12px">Select Department</label>
    <select id="deptSelect" name="department" class="form-select" style="max-width:320px">
        <option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
        <?php foreach ($meta['departments'] as $dept): ?>
            <option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                <?php echo s($dept); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="mb-0" for="qInput" style="margin-left:12px">Search</label>
    <input id="qInput" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Name or email…" />

    <button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
</form>

<?php
[$userwhere, $params] = local_admindashboard_build_user_filter($department);

// Server-side search (works across pagination). Moodle SQL does not allow reusing the same named placeholder.
if ($q !== '') {
    $userwhere .= ' AND (u.firstname LIKE :q_fn OR u.lastname LIKE :q_ln OR u.email LIKE :q_em)';
    $like = '%' . $q . '%';
    $params['q_fn'] = $like;
    $params['q_ln'] = $like;
    $params['q_em'] = $like;
}

// Build a subquery for certificate-issued courses so "Completed" works even without course completion.
require_once($CFG->libdir . '/xmldb/xmldb_table.php');
require_once($CFG->libdir . '/xmldb/xmldb_field.php');
$manager = $DB->get_manager();

$hascustomcerttables = $manager->table_exists(new xmldb_table('customcert'))
    && $manager->table_exists(new xmldb_table('customcert_issues'));
$hascertificatetables = $manager->table_exists(new xmldb_table('certificate'))
    && $manager->table_exists(new xmldb_table('certificate_issues'));
$hastoolcerttables = $manager->table_exists(new xmldb_table('tool_certificate_templates'))
    && $manager->table_exists(new xmldb_table('tool_certificate_issues'));

// All-courses (userid, courseid) issued mapping.
$issuedparts = [];
if ($hascustomcerttables) {
    $issuedparts[] = "SELECT ci.userid AS userid, ccert.course AS courseid
                        FROM {customcert_issues} ci
                        JOIN {customcert} ccert ON ccert.id = ci.customcertid";
}
if ($hascertificatetables) {
    $issuedparts[] = "SELECT ci.userid AS userid, cert.course AS courseid
                        FROM {certificate_issues} ci
                        JOIN {certificate} cert ON cert.id = ci.certificateid";
}
if ($hastoolcerttables) {
    $issues = new xmldb_table('tool_certificate_issues');
    $templates = new xmldb_table('tool_certificate_templates');
    $issueshascourseid = $manager->field_exists($issues, new xmldb_field('courseid'));
    $issueshastemplateid = $manager->field_exists($issues, new xmldb_field('templateid'));
    $templateshascourseid = $manager->field_exists($templates, new xmldb_field('courseid'));

    if ($issueshascourseid) {
        $issuedparts[] = "SELECT tci.userid AS userid, tci.courseid AS courseid
                            FROM {tool_certificate_issues} tci";
    } else if ($issueshastemplateid && $templateshascourseid) {
        $issuedparts[] = "SELECT tci.userid AS userid, tct.courseid AS courseid
                            FROM {tool_certificate_issues} tci
                            JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid";
    }
}

$issuedcoursesql = '';
if (!empty($issuedparts)) {
    $issuedcoursesql = 'SELECT DISTINCT userid, courseid FROM (' . implode(' UNION ALL ', $issuedparts) . ') x';
} else {
    // Empty mapping.
    $issuedcoursesql = 'SELECT 0 AS userid, 0 AS courseid WHERE 1=0';
}

if ($courseid > 0) {
    // Moodle doesn't allow reusing the same named placeholder in a single query.
    $params['courseid_enrol'] = $courseid;
    $params['courseid_cc'] = $courseid;

    $total = (int)$DB->count_records_sql(
        "SELECT COUNT(1)
           FROM {user} u
          WHERE {$userwhere}",
        $params
    );

    // For single-course view, treat "Completed" as course completion OR certificate issued in that course.
    $issueusersparts = [];
    if ($hascustomcerttables) {
        $params['cc_course_issued'] = $courseid;
        $issueusersparts[] = "SELECT ci.userid AS userid
                                FROM {customcert_issues} ci
                                JOIN {customcert} ccert ON ccert.id = ci.customcertid
                               WHERE ccert.course = :cc_course_issued";
    }
    if ($hascertificatetables) {
        $params['cert_course_issued'] = $courseid;
        $issueusersparts[] = "SELECT ci.userid AS userid
                                FROM {certificate_issues} ci
                                JOIN {certificate} cert ON cert.id = ci.certificateid
                               WHERE cert.course = :cert_course_issued";
    }
    if ($hastoolcerttables) {
        if (!empty($issueshascourseid)) {
            $params['tc_course_issued'] = $courseid;
            $issueusersparts[] = "SELECT tci.userid AS userid
                                    FROM {tool_certificate_issues} tci
                                   WHERE tci.courseid = :tc_course_issued";
        } else if (!empty($issueshastemplateid) && !empty($templateshascourseid)) {
            $params['tc_course_issued_t'] = $courseid;
            $issueusersparts[] = "SELECT tci.userid AS userid
                                    FROM {tool_certificate_issues} tci
                                    JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid
                                   WHERE tct.courseid = :tc_course_issued_t";
        }
    }

    $issueuserssql = '';
    if (!empty($issueusersparts)) {
        $issueuserssql = 'SELECT DISTINCT userid FROM (' . implode(' UNION ALL ', $issueusersparts) . ') iu';
    } else {
        $issueuserssql = 'SELECT 0 AS userid WHERE 1=0';
    }

    $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.lastaccess,
                   MAX(CASE WHEN e.id IS NULL THEN 0 ELSE 1 END) AS enrolled,
                   MAX(CASE WHEN (cc.timecompleted IS NOT NULL AND cc.timecompleted > 0) OR iu.userid IS NOT NULL THEN 1 ELSE 0 END) AS completed
              FROM {user} u
         LEFT JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
         LEFT JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_enrol
         LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = :courseid_cc
         LEFT JOIN ({$issueuserssql}) iu ON iu.userid = u.id
             WHERE {$userwhere}
          GROUP BY u.id, u.firstname, u.lastname, u.email, u.department, u.lastaccess
          ORDER BY completed DESC, enrolled DESC, u.lastname ASC, u.firstname ASC";

    $rows = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
} else {
    $total = (int)$DB->count_records_sql(
        "SELECT COUNT(1)
           FROM {user} u
          WHERE {$userwhere}",
        $params
    );

    $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.lastaccess,
                   COUNT(DISTINCT c.id) AS enrolledcourses,
                   COUNT(DISTINCT CASE
                       WHEN (cc.timecompleted IS NOT NULL AND cc.timecompleted > 0) OR ic.courseid IS NOT NULL
                       THEN c.id END) AS completedcourses
              FROM {user} u
         LEFT JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
         LEFT JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
         LEFT JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
         LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
         LEFT JOIN ({$issuedcoursesql}) ic ON ic.userid = u.id AND ic.courseid = c.id
             WHERE {$userwhere}
          GROUP BY u.id, u.firstname, u.lastname, u.email, u.department, u.lastaccess
          ORDER BY completedcourses DESC, enrolledcourses DESC, u.lastname ASC, u.firstname ASC";

    $rows = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
}

$table = new html_table();
// Use server-side search/paging on this page; avoid JS-only search which only filters the current page.
$table->attributes['class'] = 'table table-striped table-hover admindash-card';

if ($courseid > 0) {
    $table->head = ['User', 'Email', 'Department', 'Last access', 'Enrolled', 'Completed'];
    foreach ($rows as $r) {
        $fullname = fullname($r);
        $lastaccess = $r->lastaccess ? userdate($r->lastaccess) : '-';
        $enrolled = ((int)$r->enrolled) === 1 ? 'Yes' : 'No';
        $completed = ((int)$r->completed) === 1 ? 'Yes' : 'No';
        $table->data[] = [s($fullname), s($r->email), s($r->department ?? ''), s($lastaccess), $enrolled, $completed];
    }
} else {
    $table->head = ['User', 'Email', 'Department', 'Last access', 'Enrolled courses', 'Completed courses'];
    foreach ($rows as $r) {
        $fullname = fullname($r);
        $lastaccess = $r->lastaccess ? userdate($r->lastaccess) : '-';
        $table->data[] = [
            s($fullname),
            s($r->email),
            s($r->department ?? ''),
            s($lastaccess),
            (int)$r->enrolledcourses,
            (int)$r->completedcourses,
        ];
    }
}

echo html_writer::tag('div', html_writer::table($table), ['class' => 'mt-3']);

$baseurl = new moodle_url('/local/admindashboard/user_progress.php', [
    'courseid' => $courseid,
    'department' => $department,
    'q' => $q,
]);
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

local_admindashboard_render_footer();
