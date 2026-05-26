<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

admindash_setup_page('/local/admindashboard/passfail_report.php', 'Pass/Fail Report', 'reports.passfail');
admindash_render_header('reports.passfail');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$moduleid = optional_param('moduleid', 0, PARAM_INT); // course_modules.id (quiz only)
$statusfilter = strtolower(trim(optional_param('status', '', PARAM_ALPHA))); // passed|failed|notattempted
$q = trim(optional_param('q', '', PARAM_TEXT));
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 25;

$meta = admindash_get_meta($courseid);
?>

<h2 class="mb-3">Pass/Fail Report</h2>

<form method="get" class="admindash-filters admindash-card">
    <div class="title">Filters</div>

    <label class="mb-0" for="courseSelect">Select Course</label>
    <select id="courseSelect" name="courseid" class="form-select" style="max-width:360px">
        <option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>Select a course…</option>
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

    <label class="mb-0" for="moduleSelect" style="margin-left:12px">Select Test/Quiz</label>
    <select id="moduleSelect" name="moduleid" class="form-select" style="max-width:360px">
        <option value="0" <?php echo $moduleid === 0 ? 'selected' : ''; ?>>Final assessment (last module’s last quiz)</option>
        <?php
        if ($courseid > 0) {
            require_once($CFG->dirroot . '/course/lib.php');
            $modinfo = get_fast_modinfo($courseid);
            $sections = $modinfo->get_section_info_all();
            foreach ($sections as $sectionnum => $sectioninfo) {
                $cmids = $modinfo->sections[$sectionnum] ?? [];
                if (empty($cmids)) {
                    continue;
                }

                $items = [];
                foreach ($cmids as $cmid) {
                    if (empty($modinfo->cms[$cmid])) {
                        continue;
                    }
                    $cm = $modinfo->cms[$cmid];
                    if (!empty($cm->deletioninprogress) || empty($cm->visible) || empty($cm->visibleoncoursepage)) {
                        continue;
                    }
                    if ($cm->modname !== 'quiz') {
                        continue;
                    }

                    $name = format_string($cm->name, true, ['context' => $cm->context]);
                    $items[] = (object)[
                        'cmid' => (int)$cm->id,
                        'name' => $name,
                    ];
                }

                if (empty($items)) {
                    continue;
                }

                $sectionlabel = ($sectionnum > 0) ? ('Module ' . $sectionnum) : 'General';
                echo '<optgroup label="' . s($sectionlabel) . '">';
                foreach ($items as $it) {
                    $sel = ($moduleid === (int)$it->cmid) ? 'selected' : '';
                    echo '<option value="' . (int)$it->cmid . '" ' . $sel . '>' . s($it->name) . '</option>';
                }
                echo '</optgroup>';
            }
        }
        ?>
    </select>

    <label class="mb-0" for="statusSelect" style="margin-left:12px">Status</label>
    <select id="statusSelect" name="status" class="form-select" style="max-width:220px">
        <option value="" <?php echo $statusfilter === '' ? 'selected' : ''; ?>>All</option>
        <option value="passed" <?php echo $statusfilter === 'passed' ? 'selected' : ''; ?>>Passed</option>
        <option value="failed" <?php echo $statusfilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
        <option value="notattempted" <?php echo $statusfilter === 'notattempted' ? 'selected' : ''; ?>>Not Attempted</option>
    </select>

    <label class="mb-0" for="qInput" style="margin-left:12px">Search</label>
    <input id="qInput" name="q" class="form-control" style="max-width:260px" value="<?php echo s($q); ?>" placeholder="Name or email…" />

    <button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
</form>

<script>
(() => {
    const wwwroot = <?php echo json_encode($CFG->wwwroot); ?>;
    const courseSelect = document.getElementById('courseSelect');
    const moduleSelect = document.getElementById('moduleSelect');

    if (!courseSelect || !moduleSelect) {
        return;
    }

    const defaultOptionText = 'Final assessment (last module\u2019s last quiz)';

    function setLoading() {
        moduleSelect.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '0';
        opt.textContent = 'Loading quizzes…';
        moduleSelect.appendChild(opt);
    }

    function setDefaultOnly(message) {
        moduleSelect.innerHTML = '';

        const opt0 = document.createElement('option');
        opt0.value = '0';
        opt0.textContent = defaultOptionText;
        moduleSelect.appendChild(opt0);

        if (message) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = message;
            opt.disabled = true;
            moduleSelect.appendChild(opt);
        }

        moduleSelect.value = '0';
    }

    function isQuizLabel(name) {
        return typeof name === 'string' && name.toLowerCase().startsWith('quiz:');
    }

    async function refreshQuizOptions(courseid) {
        if (!courseid || Number(courseid) <= 0) {
            setDefaultOnly();
            return;
        }

        setLoading();

        try {
            const url = `${wwwroot}/local/admindashboard/data.php?mode=meta&courseid=${encodeURIComponent(courseid)}`;
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            const meta = await res.json();
            const groups = Array.isArray(meta?.modulegroups) ? meta.modulegroups : [];

            moduleSelect.innerHTML = '';

            const opt0 = document.createElement('option');
            opt0.value = '0';
            opt0.textContent = defaultOptionText;
            moduleSelect.appendChild(opt0);

            let quizCount = 0;
            for (const g of groups) {
                const items = Array.isArray(g?.items) ? g.items : [];
                const quizItems = items.filter(it => isQuizLabel(it?.name));
                if (!quizItems.length) {
                    continue;
                }

                const og = document.createElement('optgroup');
                og.label = String(g?.label || 'Module');
                for (const it of quizItems) {
                    const opt = document.createElement('option');
                    opt.value = String(it.id);
                    opt.textContent = String(it.name || 'Quiz');
                    og.appendChild(opt);
                    quizCount++;
                }
                moduleSelect.appendChild(og);
            }

            moduleSelect.value = '0';

            if (quizCount === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No quizzes found in this course.';
                opt.disabled = true;
                moduleSelect.appendChild(opt);
            }
        } catch (e) {
            setDefaultOnly('Failed to load quizzes.');
        }
    }

    courseSelect.addEventListener('change', () => {
        refreshQuizOptions(courseSelect.value);
    });
})();
</script>

<?php
if ($courseid <= 0) {
    echo html_writer::div('Select a course to view pass/fail.', 'alert alert-info mt-3');
    admindash_render_footer();
    exit;
}

if (!in_array($statusfilter, ['', 'passed', 'failed', 'notattempted'], true)) {
    $statusfilter = '';
}

[$userwhere, $userparams] = admindash_build_user_filter($department);

// Server-side search.
$searchsql = '';
$params = $userparams;
if ($q !== '') {
    $searchsql = ' AND (u.firstname LIKE :q_fn OR u.lastname LIKE :q_ln OR u.email LIKE :q_em)';
    $like = '%' . $q . '%';
    $params['q_fn'] = $like;
    $params['q_ln'] = $like;
    $params['q_em'] = $like;
}

// Determine grade item for selected quiz or the course final assessment quiz.
$gradeitemid = 0;
$gradepass = 0.0;
$selectedlabel = '';

if ($moduleid > 0) {
    $cm = $DB->get_record_sql(
        "SELECT cm.id, cm.course, m.name AS modname, cm.instance
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.id = :cmid",
        ['cmid' => $moduleid]
    );

    if (!$cm || (int)$cm->course !== (int)$courseid || $cm->modname !== 'quiz') {
        echo html_writer::div('Selected module is not a quiz in this course.', 'alert alert-warning mt-3');
        admindash_render_footer();
        exit;
    }

    $gi = $DB->get_record_sql(
        "SELECT gi.id, gi.gradepass
           FROM {grade_items} gi
          WHERE gi.itemtype = 'mod'
            AND gi.itemmodule = 'quiz'
            AND gi.iteminstance = :quizid
       ORDER BY gi.id ASC",
        ['quizid' => (int)$cm->instance],
        IGNORE_MULTIPLE
    );

    $gradeitemid = (int)($gi->id ?? 0);
    $gradepass = (float)($gi->gradepass ?? 0);

    if ($gradeitemid <= 0 || $gradepass <= 0) {
        echo html_writer::div('Selected quiz has no pass mark configured (gradepass). Set a pass grade for this quiz and try again.', 'alert alert-warning mt-3');
        admindash_render_footer();
        exit;
    }

    $qrec = $DB->get_record('quiz', ['id' => (int)$cm->instance], 'name', IGNORE_MISSING);
    $selectedlabel = $qrec ? ('Quiz: ' . format_string($qrec->name)) : 'Selected quiz';
} else {
    $assessment = admindash_pick_course_assessment_quiz($courseid, $userwhere, $userparams);
    if ($assessment && !empty($assessment->gradeitemid) && !empty($assessment->gradepass)) {
        $gradeitemid = (int)$assessment->gradeitemid;
        $gradepass = (float)$assessment->gradepass;
        $selectedlabel = 'Final assessment: ' . s($assessment->quizname ?? 'Quiz');
    }
}

if ($gradeitemid <= 0) {
    echo html_writer::div('No suitable quiz with a configured pass mark (gradepass) was found for this course.', 'alert alert-warning mt-3');
    admindash_render_footer();
    exit;
}

// Build a distinct enrolled-user list to avoid duplicates from multiple enrol methods.
$params['courseid_enrol'] = $courseid;
$enrolleduserssql = "SELECT DISTINCT u.id
                       FROM {user} u
                       JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                       JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_enrol
                      WHERE {$userwhere}{$searchsql}";

// Summary counts.
$summaryparams = $params + [
    'gradeitemid' => $gradeitemid,
    'gradepass' => $gradepass,
];
$summarysql = "SELECT
                    COUNT(1) AS participants,
                    SUM(CASE WHEN gg.finalgrade IS NOT NULL THEN 1 ELSE 0 END) AS attempted,
                    SUM(CASE
                        WHEN gg.finalgrade IS NOT NULL AND (gp.gradepass <= 0 OR gg.finalgrade >= gp.gradepass) THEN 1
                        ELSE 0
                    END) AS passed,
                    SUM(CASE
                        WHEN gg.finalgrade IS NOT NULL AND gp.gradepass > 0 AND gg.finalgrade < gp.gradepass THEN 1
                        ELSE 0
                    END) AS failed
               FROM ({$enrolleduserssql}) eu
               JOIN {user} u ON u.id = eu.id
          CROSS JOIN (SELECT :gradepass AS gradepass) gp
          LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :gradeitemid";

$summary = $DB->get_record_sql($summarysql, $summaryparams);
$participants = (int)($summary->participants ?? 0);
$attempted = (int)($summary->attempted ?? 0);
$passed = (int)($summary->passed ?? 0);
$failed = (int)($summary->failed ?? 0);
$dropped = max(0, $participants - $attempted);

echo html_writer::div(
    html_writer::tag('h5', 'Selected assessment', ['class' => 'mb-2']) .
    html_writer::div(s($selectedlabel), 'mb-2') .
    html_writer::div(
        '<strong>Participants:</strong> ' . $participants .
        ' &nbsp; <strong>Attempted:</strong> ' . $attempted .
        ' &nbsp; <strong>Passed:</strong> ' . $passed .
        ' &nbsp; <strong>Failed:</strong> ' . $failed .
        ' &nbsp; <strong>Dropped:</strong> ' . $dropped,
        'small text-muted'
    ),
    'admindash-card bg-white p-3 mt-3'
);

// Pagination.

$statuswhere = '';
$statusparams = [];
if ($statusfilter === 'passed') {
    $statuswhere = ' AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= gp.gradepass';
} else if ($statusfilter === 'failed') {
    $statuswhere = ' AND gg.finalgrade IS NOT NULL AND gg.finalgrade < gp.gradepass';
} else if ($statusfilter === 'notattempted') {
    $statuswhere = ' AND gg.finalgrade IS NULL';
}

$totalparams = $params + [
    'gradeitemid_total' => $gradeitemid,
    'gradepass_total' => $gradepass,
];

$totalsql = "SELECT COUNT(1)
              FROM ({$enrolleduserssql}) eu
              JOIN {user} u ON u.id = eu.id
         CROSS JOIN (SELECT :gradepass_total AS gradepass) gp
         LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :gradeitemid_total
             WHERE 1=1{$statuswhere}";

$total = (int)$DB->count_records_sql($totalsql, $totalparams);

$listparams = $params + [
    'gradeitemid_list' => $gradeitemid,
    'gradepass_list' => $gradepass,
];

// Clinic name can be stored either in a custom user profile field or in the built-in institution field.
// Prefer a custom profile field (if present) and fall back to u.institution.
$clinicfieldid = 0;
$clinicshortnames = ['clinicname', 'clinic_name', 'clinic', 'cn', 'profile_field_cn'];
try {
    // Moodle user-upload headers use profile_field_<shortname>. In DB the shortname is usually just <shortname>.
    // We do a case-insensitive lookup and include both variants.
    $clinicshortnameslower = array_values(array_unique(array_map('strtolower', $clinicshortnames)));
    $extra = [];
    foreach ($clinicshortnameslower as $sn) {
        if (str_starts_with($sn, 'profile_field_')) {
            $extra[] = substr($sn, strlen('profile_field_'));
        }
    }
    if (!empty($extra)) {
        $clinicshortnameslower = array_values(array_unique(array_merge($clinicshortnameslower, $extra)));
    }

    list($insql, $inparams) = $DB->get_in_or_equal($clinicshortnameslower, SQL_PARAMS_NAMED, 'clsn');
    $clinicfields = $DB->get_records_sql(
        "SELECT id, shortname
           FROM {user_info_field}
                    WHERE LOWER(shortname) {$insql}",
        $inparams
    );

    if (!empty($clinicfields)) {
        $byshortname = [];
        foreach ($clinicfields as $f) {
            $byshortname[strtolower((string)$f->shortname)] = (int)$f->id;
        }
        foreach ($clinicshortnameslower as $sn) {
            if (!empty($byshortname[$sn])) {
                $clinicfieldid = (int)$byshortname[$sn];
                break;
            }
        }
    }
} catch (Exception $e) {
    // If profile tables aren't available, silently fall back to institution.
    $clinicfieldid = 0;
}

// Build list query with clinic column.
$clinicselect = 'u.institution AS clinicname';
$clinicjoin = '';
if ($clinicfieldid > 0) {
    $clinicselect = "COALESCE(NULLIF(uic.data, ''), u.institution, '') AS clinicname";
    $clinicjoin = ' LEFT JOIN {user_info_data} uic ON uic.userid = u.id AND uic.fieldid = :clinicfieldid';
    $listparams['clinicfieldid'] = $clinicfieldid;
}

$listsql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, {$clinicselect}, gg.finalgrade
              FROM ({$enrolleduserssql}) eu
              JOIN {user} u ON u.id = eu.id
         CROSS JOIN (SELECT :gradepass_list AS gradepass) gp
         {$clinicjoin}
         LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :gradeitemid_list
             WHERE 1=1{$statuswhere}
          ORDER BY
               CASE
                   WHEN gg.finalgrade IS NULL THEN 2
                   WHEN gp.gradepass > 0 AND gg.finalgrade >= gp.gradepass THEN 0
                   WHEN gp.gradepass <= 0 AND gg.finalgrade IS NOT NULL THEN 0
                   ELSE 1
               END ASC,
               u.lastname ASC,
               u.firstname ASC";

$rows = $DB->get_records_sql($listsql, $listparams, $page * $perpage, $perpage);

$table = new html_table();
$table->attributes['class'] = 'table table-striped table-hover admindash-card admindash-report-table';
$table->head = ['User', 'Email', 'Department', 'Clinic Name', 'Attempted', 'Status', 'Grade'];

foreach ($rows as $r) {
    $name = fullname($r);
    $final = $r->finalgrade;
    $isattempted = ($final !== null);
    $ispassed = $isattempted && ($gradepass <= 0 || (float)$final >= $gradepass);
    $isfailed = $isattempted && ($gradepass > 0 && (float)$final < $gradepass);

    $rowstatus = $ispassed ? 'Passed' : ($isfailed ? 'Failed' : 'Not Attempted');
    $attemptedlabel = $isattempted ? 'Yes' : 'No';
    $gradelabel = $isattempted ? format_float((float)$final, 2) : '-';

    $table->data[] = [
        s($name),
        s($r->email),
        s($r->department ?? ''),
        s($r->clinicname ?? ''),
        $attemptedlabel,
        $rowstatus,
        $gradelabel,
    ];
}

echo html_writer::tag('div', html_writer::table($table), ['class' => 'mt-3']);

$baseurl = new moodle_url('/local/admindashboard/passfail_report.php', [
    'courseid' => $courseid,
    'department' => $department,
    'moduleid' => $moduleid,
    'status' => $statusfilter,
    'q' => $q,
]);

echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

admindash_render_footer();
