<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');
require_once($CFG->libdir . '/completionlib.php');

admindash_setup_page('/local/admindashboard/progress_ticks_report.php', 'Progress Ticks Report', 'reports.ticks');
admindash_render_header('reports.ticks');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$sectionnum = optional_param('section', 0, PARAM_INT); // course_sections.section (module number)
$userid = optional_param('userid', 0, PARAM_INT); // user.id (participant)

$meta = admindash_get_meta($courseid);
?>

<h2 class="mb-3">Progress Ticks Report</h2>

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

    <?php
    $sectionsopts = [];
    $participantsopts = [];
    if ($courseid > 0) {
        require_once($CFG->dirroot . '/course/lib.php');
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $completion = new completion_info($course);
        $modinfo = get_fast_modinfo($courseid);

        $coursecontext = context_course::instance($courseid);
        $enrolled = get_enrolled_users(
            $coursecontext,
            '',
            0,
            'u.id,u.firstname,u.lastname,u.email',
            'u.lastname ASC, u.firstname ASC',
            0,
            0,
            true
        );
        foreach ($enrolled as $u) {
            $label = trim((string)$u->lastname . ', ' . (string)$u->firstname);
            if (!empty($u->email)) {
                $label .= ' (' . (string)$u->email . ')';
            }
            $participantsopts[] = ['id' => (int)$u->id, 'label' => $label];
        }

        $secs = [];
        foreach ($completion->get_activities() as $cmid => $cm) {
            if (empty($modinfo->cms[$cmid])) {
                continue;
            }
            $cm = $modinfo->cms[$cmid];
            $sec = (int)($cm->sectionnum ?? 0);
            if ($sec > 0) {
                $secs[$sec] = true;
            }
        }

        $secnums = array_keys($secs);
        sort($secnums, SORT_NUMERIC);
        foreach ($secnums as $sec) {
            $sectionsopts[] = ['sec' => (int)$sec, 'label' => 'Module ' . (int)$sec];
        }
    }
    ?>

    <label class="mb-0" for="participantSelect" style="margin-left:12px">Participant</label>
    <select id="participantSelect" name="userid" class="form-select" style="max-width:360px">
        <?php if ($courseid <= 0): ?>
            <option value="0" selected>Select a course first…</option>
        <?php else: ?>
            <option value="0" <?php echo $userid === 0 ? 'selected' : ''; ?>>All Participants</option>
            <?php foreach ($participantsopts as $opt): ?>
                <option value="<?php echo (int)$opt['id']; ?>" <?php echo $userid === (int)$opt['id'] ? 'selected' : ''; ?>>
                    <?php echo s($opt['label']); ?>
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>

    <label class="mb-0" for="sectionSelect" style="margin-left:12px">Select Module</label>
    <select id="sectionSelect" name="section" class="form-select" style="max-width:220px">
        <option value="0" <?php echo $sectionnum === 0 ? 'selected' : ''; ?>>All Modules</option>
        <?php foreach ($sectionsopts as $opt): ?>
            <option value="<?php echo (int)$opt['sec']; ?>" <?php echo $sectionnum === (int)$opt['sec'] ? 'selected' : ''; ?>>
                <?php echo s($opt['label']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
</form>

<?php
if ($courseid <= 0) {
    echo html_writer::div('Select a course to view progress ticks.', 'alert alert-info mt-3');
    admindash_render_footer();
    exit;
}

require_once($CFG->dirroot . '/course/lib.php');

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$completion = new completion_info($course);
if (!$completion->is_enabled()) {
    echo html_writer::div('Completion tracking is disabled for this course. Enable completion to use this report.', 'alert alert-warning mt-3');
    admindash_render_footer();
    exit;
}

// Activities with completion enabled (matches Moodle Activity completion report behaviour).
$modinfo = get_fast_modinfo($courseid);

// Map each course module to its position within its section, based on course page ordering.
$sectionpos = [];
foreach (($modinfo->sections ?? []) as $sec => $cmidsinsec) {
    $i = 0;
    foreach (($cmidsinsec ?? []) as $cmid) {
        $sectionpos[(int)$cmid] = $i;
        $i++;
    }
}

$activities = [];
foreach ($completion->get_activities() as $cmid => $cm) {
    if (empty($modinfo->cms[$cmid])) {
        continue;
    }
    $cm = $modinfo->cms[$cmid];

    $sec = (int)($cm->sectionnum ?? 0);
    if ($sec <= 0) {
        continue;
    }

    if ($sectionnum > 0 && $sec !== $sectionnum) {
        continue;
    }

    $activities[] = [
        'cmid' => (int)$cm->id,
        'name' => format_string($cm->name, true, ['context' => $cm->context]),
        'section' => $sec,
        'completion' => (int)($cm->completion ?? COMPLETION_TRACKING_NONE),
        'sectionpos' => (int)($sectionpos[(int)$cm->id] ?? 999999),
    ];
}

usort($activities, static function(array $a, array $b): int {
    if ((int)$a['section'] !== (int)$b['section']) {
        return (int)$a['section'] <=> (int)$b['section'];
    }
    if ((int)$a['sectionpos'] !== (int)$b['sectionpos']) {
        return (int)$a['sectionpos'] <=> (int)$b['sectionpos'];
    }
    return (int)$a['cmid'] <=> (int)$b['cmid'];
});

if (empty($activities)) {
    echo html_writer::div('No completion-tracked activities found for the selected module(s).', 'alert alert-warning mt-3');
    admindash_render_footer();
    exit;
}

// Clinic name can be stored either in a custom user profile field or in the built-in institution field.
// Prefer a custom profile field (if present) and fall back to u.institution.
$clinicfieldid = 0;
$clinicshortnames = ['clinicname', 'clinic_name', 'clinic', 'cn', 'profile_field_cn'];
try {
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
    $clinicfieldid = 0;
}

$clinicselect = 'u.institution AS clinicname';
$clinicjoin = '';
$params = [];
if ($clinicfieldid > 0) {
    $clinicselect = "COALESCE(NULLIF(uic.data, ''), u.institution, '') AS clinicname";
    $clinicjoin = ' LEFT JOIN {user_info_data} uic ON uic.userid = u.id AND uic.fieldid = :clinicfieldid';
    $params['clinicfieldid'] = $clinicfieldid;
}

[$userwhere, $userparams] = admindash_build_user_filter($department);

// Get progress using Moodle's built-in completion API (same source as the official report/progress).
$coursecontext = context_course::instance($courseid);
$progressusers = $completion->get_progress_all(
    $userwhere,
    $userparams,
    0,
    'u.lastname ASC, u.firstname ASC',
    0,
    0,
    $coursecontext
);

// If a participant is selected, narrow down to that one user.
if ($userid > 0) {
    $userid = (int)$userid;
    if (empty($progressusers[$userid])) {
        echo html_writer::div('Selected participant was not found for the current filters.', 'alert alert-warning mt-3');
        admindash_render_footer();
        exit;
    }
    $progressusers = [$userid => $progressusers[$userid]];
}

if (empty($progressusers)) {
    echo html_writer::div('No users found for the selected filters.', 'alert alert-warning mt-3');
    admindash_render_footer();
    exit;
}

$userids = array_map('intval', array_keys($progressusers));

// Enrich user info (email/department/clinic).
list($uinq, $uinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
$usersql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, {$clinicselect}
              FROM {user} u
              {$clinicjoin}
             WHERE u.id {$uinq}";
$userrecs = $DB->get_records_sql($usersql, $params + $uinparams);

// Preserve order from completion API output.
$users = [];
foreach ($progressusers as $uid => $pu) {
    $uid = (int)$uid;
    if (!empty($userrecs[$uid])) {
        $users[$uid] = $userrecs[$uid];
    } else {
        // Fallback to whatever the completion API returned.
        $fallback = new stdClass();
        $fallback->id = $uid;
        $fallback->firstname = (string)($pu->firstname ?? '');
        $fallback->lastname = (string)($pu->lastname ?? '');
        $fallback->email = (string)($pu->email ?? '');
        $fallback->department = (string)($pu->department ?? '');
        $fallback->clinicname = '';
        $users[$uid] = $fallback;
    }
}

// Build completion state map for quick lookup.
$progress = [];
foreach ($progressusers as $uid => $pu) {
    $uid = (int)$uid;
    $progress[$uid] = [];
    if (empty($pu->progress) || !is_array($pu->progress)) {
        continue;
    }
    foreach ($pu->progress as $cmid => $p) {
        $progress[$uid][(int)$cmid] = (int)($p->completionstate ?? COMPLETION_INCOMPLETE);
    }
}

$trackabletotal = count($activities);

echo html_writer::div(
    html_writer::div(
        html_writer::span('Activities: ', 'text-muted') . count($activities)
        . html_writer::span(' &nbsp; | &nbsp; Trackable: ', 'text-muted') . $trackabletotal,
        'small'
    ),
    'mt-3'
);

echo html_writer::start_div('admindash-ticks-wrap mt-2');
echo '<table class="admindash-ticks-table">';

// Header.
echo '<thead><tr>';
echo '<th>First name</th>';
echo '<th>Last name</th>';
echo '<th>Email address</th>';
echo '<th>Department</th>';
echo '<th>Clinic Name</th>';
echo '<th style="min-width:200px">Progress</th>';

foreach ($activities as $a) {
    $label = 'Module ' . (int)$a['section'] . ' — ' . (string)$a['name'];
    echo '<th class="admindash-rot"><div>' . s($label) . '</div></th>';
}

echo '</tr></thead>';

// Body.
echo '<tbody>';
foreach ($users as $u) {
    $uid = (int)$u->id;
    $done = 0;

    echo '<tr>';
    echo '<td>' . s($u->firstname) . '</td>';
    echo '<td>' . s($u->lastname) . '</td>';
    echo '<td>' . s($u->email) . '</td>';
    echo '<td>' . s($u->department ?? '') . '</td>';
    echo '<td>' . s($u->clinicname ?? '') . '</td>';

    foreach ($activities as $a) {
        $cmid = (int)$a['cmid'];
        $state = (int)($progress[$uid][$cmid] ?? COMPLETION_INCOMPLETE);
        if ($state > 0) {
            $done++;
        }
    }

    $pct = ($trackabletotal > 0) ? (int)round(100 * ($done / $trackabletotal)) : 0;
    $progresslabel = ($trackabletotal > 0) ? ($done . '/' . $trackabletotal . ' (' . $pct . '%)') : '—';

    echo '<td>';
    echo '<div class="admindash-progresscell">';
    echo '<div class="small text-muted mb-1">' . s($progresslabel) . '</div>';
    echo '<div class="progress" style="height:10px">';
    echo '<div class="progress-bar" role="progressbar" style="width:' . (int)$pct . '%" aria-valuenow="' . (int)$pct . '" aria-valuemin="0" aria-valuemax="100"></div>';
    echo '</div>';
    echo '</div>';
    echo '</td>';

    foreach ($activities as $a) {
        $cmid = (int)$a['cmid'];
        $state = (int)($progress[$uid][$cmid] ?? COMPLETION_INCOMPLETE);

        echo '<td class="admindash-tickcell">';
        if ($state === COMPLETION_COMPLETE_PASS) {
            echo '<span class="admindash-box done" title="Completed (Pass)"></span>';
        } else if ($state === COMPLETION_COMPLETE_FAIL || $state === COMPLETION_COMPLETE_FAIL_HIDDEN) {
            echo '<span class="admindash-box done" title="Completed (Fail)"></span>';
        } else if ($state === COMPLETION_COMPLETE) {
            echo '<span class="admindash-box done" title="Completed"></span>';
        } else {
            echo '<span class="admindash-box" title="Not completed"></span>';
        }
        echo '</td>';
    }

    echo '</tr>';
}
echo '</tbody>';

echo '</table>';
echo html_writer::end_div();

admindash_render_footer();
