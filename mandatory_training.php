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

local_admindashboard_setup_page('/local/admindashboard/mandatory_training.php', 'Mandatory Training', 'compliance.mandatory');
local_admindashboard_render_header('compliance.mandatory');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$status = trim(optional_param('status', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));

$meta = local_admindashboard_get_meta($courseid);
$tabs = local_admindashboard_get_compliance_suite_tabs();

$statusoptions = [
	'all' => 'All learners',
	'overdue' => 'Overdue',
	'incomplete' => 'Incomplete',
	'completed' => 'Completed',
];
if (!array_key_exists($status, $statusoptions)) {
	$status = 'all';
}

[$userwhere, $userparams] = local_admindashboard_build_user_filter($department);

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
$atriskrows = $resolvedcourseid > 0 ? local_admindashboard_get_at_risk_participants($resolvedcourseid, '', 8) : [];
$atriskcount = is_array($atriskrows) ? count($atriskrows) : 0;

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

local_admindashboard_render_workspace_header(
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
	<div class="title"><?php echo get_string('ui_mandatory_training_filters', 'local_admindashboard'); ?></div>

	<label class="mb-0" for="mandatoryCourse"><?php echo get_string('ui_mandatory_training_course', 'local_admindashboard'); ?></label>
	<select id="mandatoryCourse" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
		<?php foreach ($meta['courses'] as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="mandatoryDepartment"><?php echo get_string('ui_mandatory_training_department', 'local_admindashboard'); ?></label>
	<select id="mandatoryDepartment" name="department" class="form-select" style="max-width:320px">
		<option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
		<?php foreach ($meta['departments'] as $dept): ?>
			<option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
				<?php echo s($dept); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="mandatoryStatus"><?php echo get_string('ui_mandatory_training_status', 'local_admindashboard'); ?></label>
	<select id="mandatoryStatus" name="status" class="form-select" style="max-width:240px">
		<?php foreach ($statusoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="mandatorySearch"><?php echo get_string('ui_mandatory_training_search', 'local_admindashboard'); ?></label>
	<input id="mandatorySearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Course or learner name" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_mandatory_training_apply', 'local_admindashboard'); ?></button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/mandatory_training.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_mandatory_training_tracks', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $summary['tracks']; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_mandatory_training_visible_completion_enabled_courses_currently_treated_as_mandato_e74b74e4', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_mandatory_training_completed', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $summary['completed']; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_mandatory_training_learner_completions_already_recorded_within_the_filtered_mandatory_scope', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_mandatory_training_incomplete', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $summary['incomplete']; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_mandatory_training_learners_still_incomplete_across_the_tracked_mandatory_courses', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_mandatory_training_overdue', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $summary['overdue']; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_mandatory_training_learners_still_incomplete_after_the_course_end_date_has_already_passed', 'local_admindashboard'); ?></div>
	</div>
</div>


<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_mandatory_training_track_pressure', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_mandatory_training_where_to_focus', 'local_admindashboard'); ?></span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($courserows)): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo get_string('ui_mandatory_training_no_tracks_in_scope', 'local_admindashboard'); ?></span>
					<span class="admindash-admin-list__value"><?php echo get_string('ui_mandatory_training_no_visible_completion_enabled_courses_matched_the_active_filter_scope', 'local_admindashboard'); ?></span>
				</li>
			<?php else: ?>
				<?php foreach ($courserows as $row): ?>
					<?php $rate = (int)$row->enrolledcount > 0 ? (int)round(((int)$row->completedcount / (int)$row->enrolledcount) * 100) : 0; ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($row->fullname); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->completedcount; ?>/<?php echo (int)$row->enrolledcount; ?> <?php echo get_string('ui_mandatory_training_complete', 'local_admindashboard'); ?> <?php echo $rate; ?>% · <?php echo (int)$row->overduecount; ?> <?php echo get_string('ui_mandatory_training_overdue', 'local_admindashboard'); ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_mandatory_training_selected_course_risk', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_mandatory_training_engagement_overlay', 'local_admindashboard'); ?></span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($atriskrows)): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo get_string('ui_mandatory_training_no_at_risk_learners', 'local_admindashboard'); ?></span>
					<span class="admindash-admin-list__value"><?php echo $resolvedcourseid > 0 ? 'No at-risk learners were returned for the selected course.' : 'Select a course to load risk context.'; ?></span>
				</li>
			<?php else: ?>
				<?php foreach ($atriskrows as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s((string)($row['name'] ?? 'Unknown user')); ?></span>
						<span class="admindash-admin-list__value"><?php echo get_string('ui_mandatory_training_risk', 'local_admindashboard'); ?> <?php echo (int)($row['risk_score'] ?? 0); ?><?php echo get_string('ui_mandatory_training_3', 'local_admindashboard'); ?> <?php echo s((string)($row['department'] ?? 'Unassigned')); ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1"><?php echo get_string('ui_mandatory_training_learner_attention_queue', 'local_admindashboard'); ?></h5>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_mandatory_training_completion_driven_queue_for_direct_follow_up_filtered_by_learne_8739994e', 'local_admindashboard'); ?></p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th><?php echo get_string('ui_mandatory_training_learner', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_mandatory_training_department', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_mandatory_training_course', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_mandatory_training_last_access', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_mandatory_training_completion', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_mandatory_training_status', 'local_admindashboard'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($learnerrows)): ?>
					<tr>
						<td colspan="6" class="text-center py-4"><?php echo get_string('ui_mandatory_training_no_learner_rows_matched_the_current_filters', 'local_admindashboard'); ?></td>
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
local_admindashboard_render_footer();