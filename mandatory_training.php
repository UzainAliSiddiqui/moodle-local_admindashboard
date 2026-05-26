<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

admindash_setup_page('/local/admindashboard/mandatory_training.php', 'Mandatory Training', 'compliance.mandatory');
admindash_render_header('compliance.mandatory');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$status = trim(optional_param('status', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));

$meta = admindash_get_meta($courseid);
$tabs = admindash_get_compliance_suite_tabs();

$statusoptions = [
	'all' => 'All learners',
	'overdue' => 'Overdue',
	'incomplete' => 'Incomplete',
	'completed' => 'Completed',
];
if (!array_key_exists($status, $statusoptions)) {
	$status = 'all';
}

[$userwhere, $userparams] = admindash_build_user_filter($department);

$courseconditions = [
	'c.id > 1',
	'c.visible = 1',
	'c.enablecompletion = 1',
];
$courseparams = [];
if ($courseid > 0) {
	$courseconditions[] = 'c.id = :courseid';
	$courseparams['courseid'] = $courseid;
}

$coursewhere = implode(' AND ', $courseconditions);
$now = time();

$courserows = $DB->get_records_sql(
	"SELECT c.id,
			c.fullname,
			c.enddate,
			COUNT(DISTINCT ue.userid) AS enrolledcount,
			COUNT(DISTINCT CASE WHEN cc.timecompleted > 0 THEN ue.userid ELSE NULL END) AS completedcount,
			COUNT(DISTINCT CASE WHEN cc.timecompleted IS NULL OR cc.timecompleted = 0 THEN ue.userid ELSE NULL END) AS incompletecount,
			COUNT(DISTINCT CASE
				WHEN c.enddate > 0 AND c.enddate < :coursenow AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)
				THEN ue.userid ELSE NULL END) AS overduecount
	   FROM {course} c
	   JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
	   JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
	   JOIN {user} u ON u.id = ue.userid
  LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
	  WHERE {$coursewhere}
		AND {$userwhere}
   GROUP BY c.id, c.fullname, c.enddate
   ORDER BY overduecount DESC, incompletecount DESC, c.fullname ASC",
	['coursenow' => $now] + $courseparams + $userparams,
	0,
	12
);

$summary = [
	'tracks' => count($courserows),
	'enrolled' => 0,
	'completed' => 0,
	'incomplete' => 0,
	'overdue' => 0,
];
foreach ($courserows as $row) {
	$summary['enrolled'] += (int)$row->enrolledcount;
	$summary['completed'] += (int)$row->completedcount;
	$summary['incomplete'] += (int)$row->incompletecount;
	$summary['overdue'] += (int)$row->overduecount;
}

$learnerconditions = [
	'c.id > 1',
	'c.visible = 1',
	'c.enablecompletion = 1',
	$userwhere,
];
$learnerparams = $userparams;
if ($courseid > 0) {
	$learnerconditions[] = 'c.id = :learnercourseid';
	$learnerparams['learnercourseid'] = $courseid;
}
if ($q !== '') {
	$like = '%' . $q . '%';
	$learnerconditions[] = '(u.firstname LIKE :qfn OR u.lastname LIKE :qln OR c.fullname LIKE :qcourse)';
	$learnerparams['qfn'] = $like;
	$learnerparams['qln'] = $like;
	$learnerparams['qcourse'] = $like;
}
if ($status === 'overdue') {
	$learnerconditions[] = 'c.enddate > 0 AND c.enddate < :overduenow AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)';
	$learnerparams['overduenow'] = $now;
} else if ($status === 'incomplete') {
	$learnerconditions[] = '(cc.timecompleted IS NULL OR cc.timecompleted = 0)';
} else if ($status === 'completed') {
	$learnerconditions[] = 'cc.timecompleted > 0';
}

$learnerwhere = implode(' AND ', $learnerconditions);
$learnerrows = $DB->get_records_sql(
	"SELECT u.id,
			u.firstname,
			u.lastname,
			COALESCE(u.department, '') AS department,
			COALESCE(u.lastaccess, 0) AS lastaccess,
			c.id AS courseid,
			c.fullname AS coursename,
			c.enddate,
			COALESCE(cc.timecompleted, 0) AS timecompleted
	   FROM {course} c
	   JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
	   JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
	   JOIN {user} u ON u.id = ue.userid
  LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
	  WHERE {$learnerwhere}
   ORDER BY c.enddate ASC, cc.timecompleted ASC, u.lastname ASC, u.firstname ASC",
	$learnerparams,
	0,
	40
);

$resolvedcourseid = $courseid;
if ($resolvedcourseid <= 0 && !empty($meta['courses'])) {
	$resolvedcourseid = (int)($meta['courses'][0]['id'] ?? 0);
}
$atriskrows = $resolvedcourseid > 0 ? admindash_get_at_risk_participants($resolvedcourseid, '', 8) : [];
$atriskcount = is_array($atriskrows) ? count($atriskrows) : 0;

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

admindash_render_workspace_header(
	'Reports & Analytics',
	'Mandatory Training',
	'Completion-driven mandatory-learning operations board for overdue follow-up, incomplete learner queues, and course-level compliance pressure.',
	'compliance',
	'compliance.mandatory',
	$tabs,
	[
		['label' => 'Compliance Dashboard', 'url' => new moodle_url('/local/admindashboard/compliance_dashboard.php'), 'primary' => true],
		['label' => 'Support Tickets', 'url' => new moodle_url('/local/admindashboard/support_tickets.php'), 'primary' => false],
		['label' => 'Direct Messaging', 'url' => new moodle_url('/local/admindashboard/direct_messaging.php'), 'primary' => false],
	],
	[
		$summary['tracks'] . ' mandatory proxies',
		$summary['overdue'] . ' overdue learners',
		$resolvedcourseid > 0 ? $atriskcount . ' at-risk in selected course' : 'No course selected',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Filters</div>

	<label class="mb-0" for="mandatoryCourse">Course</label>
	<select id="mandatoryCourse" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
		<?php foreach ($meta['courses'] as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="mandatoryDepartment">Department</label>
	<select id="mandatoryDepartment" name="department" class="form-select" style="max-width:320px">
		<option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
		<?php foreach ($meta['departments'] as $dept): ?>
			<option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
				<?php echo s($dept); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="mandatoryStatus">Status</label>
	<select id="mandatoryStatus" name="status" class="form-select" style="max-width:240px">
		<?php foreach ($statusoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="mandatorySearch">Search</label>
	<input id="mandatorySearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Course or learner name" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/mandatory_training.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Tracks</div>
		<div class="admindash-module-stat__value"><?php echo $summary['tracks']; ?></div>
		<div class="admindash-module-stat__meta">Visible completion-enabled courses currently treated as mandatory-learning tracks.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Completed</div>
		<div class="admindash-module-stat__value"><?php echo $summary['completed']; ?></div>
		<div class="admindash-module-stat__meta">Learner completions already recorded within the filtered mandatory scope.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Incomplete</div>
		<div class="admindash-module-stat__value"><?php echo $summary['incomplete']; ?></div>
		<div class="admindash-module-stat__meta">Learners still incomplete across the tracked mandatory courses.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Overdue</div>
		<div class="admindash-module-stat__value"><?php echo $summary['overdue']; ?></div>
		<div class="admindash-module-stat__meta">Learners still incomplete after the course end date has already passed.</div>
	</div>
</div>


<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Track Pressure</h5>
			<span class="admindash-admin-note">Where to focus</span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($courserows)): ?>
				<li>
					<span class="admindash-admin-list__label">No tracks in scope</span>
					<span class="admindash-admin-list__value">No visible completion-enabled courses matched the active filter scope.</span>
				</li>
			<?php else: ?>
				<?php foreach ($courserows as $row): ?>
					<?php $rate = (int)$row->enrolledcount > 0 ? (int)round(((int)$row->completedcount / (int)$row->enrolledcount) * 100) : 0; ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($row->fullname); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->completedcount; ?>/<?php echo (int)$row->enrolledcount; ?> complete · <?php echo $rate; ?>% · <?php echo (int)$row->overduecount; ?> overdue</span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Selected Course Risk</h5>
			<span class="admindash-admin-note">Engagement overlay</span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($atriskrows)): ?>
				<li>
					<span class="admindash-admin-list__label">No at-risk learners</span>
					<span class="admindash-admin-list__value"><?php echo $resolvedcourseid > 0 ? 'No at-risk learners were returned for the selected course.' : 'Select a course to load risk context.'; ?></span>
				</li>
			<?php else: ?>
				<?php foreach ($atriskrows as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s((string)($row['name'] ?? 'Unknown user')); ?></span>
						<span class="admindash-admin-list__value">Risk <?php echo (int)($row['risk_score'] ?? 0); ?>/3 · <?php echo s((string)($row['department'] ?? 'Unassigned')); ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Learner Attention Queue</h5>
			<p class="admindash-admin-note mb-0">Completion-driven queue for direct follow-up, filtered by learner state and course scope.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Learner</th>
					<th>Department</th>
					<th>Course</th>
					<th>Last Access</th>
					<th>Completion</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($learnerrows)): ?>
					<tr>
						<td colspan="6" class="text-center py-4">No learner rows matched the current filters.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($learnerrows as $row): ?>
						<?php
						$completed = (int)$row->timecompleted > 0;
						$overdue = !$completed && (int)$row->enddate > 0 && (int)$row->enddate < $now;
						$label = $completed ? 'Completed' : ($overdue ? 'Overdue' : 'Incomplete');
						$badgeclass = $completed ? 'is-success' : ($overdue ? 'is-danger' : 'is-warn');
						?>
						<tr>
							<td><?php echo s(trim((string)$row->firstname . ' ' . (string)$row->lastname)); ?></td>
							<td><?php echo s(trim((string)$row->department) !== '' ? (string)$row->department : 'Unassigned'); ?></td>
							<td><?php echo s($row->coursename); ?></td>
							<td><?php echo (int)$row->lastaccess > 0 ? s(userdate((int)$row->lastaccess, '%d %b %Y')) : 'No recorded access'; ?></td>
							<td><?php echo $completed ? s(userdate((int)$row->timecompleted, '%d %b %Y')) : 'Not completed'; ?></td>
							<td><?php echo $statusbadge($label, $badgeclass); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
admindash_render_footer();