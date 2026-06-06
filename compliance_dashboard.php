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

local_admindashboard_setup_page('/local/admindashboard/compliance_dashboard.php', 'Compliance Dashboard', 'compliance.dashboard');
local_admindashboard_render_header('compliance.dashboard');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));

$meta = local_admindashboard_get_meta($courseid);
$tabs = local_admindashboard_get_compliance_suite_tabs();
$certunion = local_admindashboard_get_certificate_issue_union_sql();

$courseoptions = $meta['courses'] ?? [];
$resolvedcourseid = $courseid;
if ($resolvedcourseid <= 0 && !empty($courseoptions)) {
	$resolvedcourseid = (int)($courseoptions[0]['id'] ?? 0);
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

$mandatorytracks = count($courserows);
$totalenrolled = 0;
$totalcompleted = 0;
$totalincomplete = 0;
$totaloverdue = 0;
foreach ($courserows as $row) {
	$totalenrolled += (int)($row->enrolledcount ?? 0);
	$totalcompleted += (int)($row->completedcount ?? 0);
	$totalincomplete += (int)($row->incompletecount ?? 0);
	$totaloverdue += (int)($row->overduecount ?? 0);
}

$completionrate = $totalenrolled > 0 ? (int)round(($totalcompleted / $totalenrolled) * 100) : 0;

$certsummary = (object)[
	'readycount' => 0,
	'riskcount' => 0,
	'datagapcount' => 0,
	'coveredcount' => 0,
];
$renewalrows = [];

if ($certunion['available']) {
	$certwhere = $userwhere;
	$certparams = $userparams;
	if ($courseid > 0) {
		$certwhere .= ' AND cert.courseid = :certcourseid';
		$certparams['certcourseid'] = $courseid;
	}

	$issuedsubsql = "SELECT cert.userid,
							cert.courseid,
							MAX(cert.issuedat) AS latestissuedat,
							MAX(u.lastaccess) AS lastaccess,
							MAX(COALESCE(c.fullname, 'Unknown course')) AS coursename,
							MAX(COALESCE(u.department, '')) AS department,
							MAX(u.firstname) AS firstname,
							MAX(u.lastname) AS lastname
					   FROM ({$certunion['sql']}) cert
					   JOIN {user} u ON u.id = cert.userid
				  LEFT JOIN {course} c ON c.id = cert.courseid
					  WHERE {$certwhere}
				   GROUP BY cert.userid, cert.courseid";

	$certsummary = $DB->get_record_sql(
		"SELECT COUNT(1) AS coveredcount,
				SUM(CASE
					WHEN issued.latestissuedat > 0
					 AND issued.latestissuedat > :readythreshold
					 AND issued.latestissuedat <= :watchthresholdready
					 AND issued.lastaccess >= :activecutoff
					THEN 1 ELSE 0 END) AS readycount,
				SUM(CASE
					WHEN issued.latestissuedat <= 0
					THEN 1 ELSE 0 END) AS datagapcount,
				SUM(CASE
					WHEN issued.latestissuedat > 0
					 AND (
						issued.latestissuedat <= :riskthreshold
						OR (
							issued.latestissuedat <= :watchthresholdrisk
							AND (issued.lastaccess = 0 OR issued.lastaccess < :activecutoff2)
						)
					 )
					THEN 1 ELSE 0 END) AS riskcount
		   FROM ({$issuedsubsql}) issued",
		[
			'readythreshold' => $now - (365 * DAYSECS),
			'watchthresholdready' => $now - (300 * DAYSECS),
			'watchthresholdrisk' => $now - (300 * DAYSECS),
			'riskthreshold' => $now - (365 * DAYSECS),
			'activecutoff' => $now - (90 * DAYSECS),
			'activecutoff2' => $now - (90 * DAYSECS),
		] + $certparams
	);

	$renewalrows = $DB->get_records_sql(
		"SELECT issued.*
		   FROM ({$issuedsubsql}) issued
	   ORDER BY issued.latestissuedat ASC, issued.lastaccess ASC, issued.coursename ASC",
		$certparams,
		0,
		8
	);
}

$atriskrows = $resolvedcourseid > 0 ? local_admindashboard_get_at_risk_participants($resolvedcourseid, '', 8) : [];
$atriskcount = is_array($atriskrows) ? count($atriskrows) : 0;

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

local_admindashboard_render_workspace_header(
	'Reports & Analytics',
	'Compliance Dashboard',
	'Executive compliance control room combining completion-enabled learning tracks, overdue learner volume, certificate-age risk, and active outreach signals.',
	'compliance',
	'compliance.dashboard',
	$tabs,
	[
		['label' => 'License Expiry', 'url' => new moodle_url('/local/admindashboard/license_expiry.php'), 'primary' => true],
		['label' => 'Mandatory Training', 'url' => new moodle_url('/local/admindashboard/mandatory_training.php'), 'primary' => false],
		['label' => 'Renewal Readiness', 'url' => new moodle_url('/local/admindashboard/renewal_readiness.php'), 'primary' => false],
	],
	[
		$mandatorytracks . ' mandatory track proxies',
		$certunion['available'] ? ((int)($certsummary->riskcount ?? 0) . ' renewal risk') : 'No certificate source',
		$resolvedcourseid > 0 ? $atriskcount . ' at-risk learners' : 'No course selected',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title"><?php echo get_string('ui_compliance_dashboard_filters', 'local_admindashboard'); ?></div>

	<label class="mb-0" for="complianceCourse"><?php echo get_string('ui_compliance_dashboard_course', 'local_admindashboard'); ?></label>
	<select id="complianceCourse" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
		<?php foreach ($courseoptions as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="complianceDepartment"><?php echo get_string('ui_compliance_dashboard_department', 'local_admindashboard'); ?></label>
	<select id="complianceDepartment" name="department" class="form-select" style="max-width:320px">
		<option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
		<?php foreach ($meta['departments'] as $dept): ?>
			<option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
				<?php echo s($dept); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_compliance_dashboard_apply', 'local_admindashboard'); ?></button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/compliance_dashboard.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_compliance_dashboard_mandatory_tracks', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $mandatorytracks; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_compliance_dashboard_visible_completion_enabled_courses_used_as_the_mandatory_learni_1eee574b', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_compliance_dashboard_completion_rate', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $completionrate; ?>%</div>
		<div class="admindash-module-stat__meta"><?php echo $totalcompleted; ?> <?php echo get_string('ui_compliance_dashboard_completions_across', 'local_admindashboard'); ?> <?php echo $totalenrolled; ?> <?php echo get_string('ui_compliance_dashboard_tracked_enrolments_in_the_current_scope', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_compliance_dashboard_overdue_learners', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $totaloverdue; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_compliance_dashboard_learners_still_incomplete_after_the_course_end_date_has_already_passed', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_compliance_dashboard_renewal_risk', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($certsummary->riskcount ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo $certunion['available'] ? ((int)($certsummary->coveredcount ?? 0) . ' certificate-backed records assessed for issue age and inactivity.') : 'No supported certificate issue source was detected.'; ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_compliance_dashboard_mandatory_track_health', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_compliance_dashboard_top_exposure', 'local_admindashboard'); ?></span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($courserows)): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo get_string('ui_compliance_dashboard_no_compliance_tracks', 'local_admindashboard'); ?></span>
					<span class="admindash-admin-list__value"><?php echo get_string('ui_compliance_dashboard_no_visible_completion_enabled_courses_matched_the_active_filter_scope', 'local_admindashboard'); ?></span>
				</li>
			<?php else: ?>
				<?php foreach ($courserows as $row): ?>
					<?php $rowrate = (int)$row->enrolledcount > 0 ? (int)round(((int)$row->completedcount / (int)$row->enrolledcount) * 100) : 0; ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($row->fullname); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->completedcount; ?>/<?php echo (int)$row->enrolledcount; ?> <?php echo get_string('ui_compliance_dashboard_complete', 'local_admindashboard'); ?> <?php echo $rowrate; ?>% · <?php echo (int)$row->overduecount; ?> <?php echo get_string('ui_compliance_dashboard_overdue', 'local_admindashboard'); ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_compliance_dashboard_renewal_risk_queue', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_compliance_dashboard_oldest_records', 'local_admindashboard'); ?></span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($renewalrows)): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo get_string('ui_compliance_dashboard_no_certificate_risk_queue', 'local_admindashboard'); ?></span>
					<span class="admindash-admin-list__value"><?php echo $certunion['available'] ? 'No certificate-backed records matched the current filters.' : 'No supported certificate plugin data was detected.'; ?></span>
				</li>
			<?php else: ?>
				<?php foreach ($renewalrows as $row): ?>
					<?php $age = (int)$row->latestissuedat > 0 ? (int)floor(($now - (int)$row->latestissuedat) / DAYSECS) . ' days old' : 'Unknown age'; ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s(trim((string)$row->firstname . ' ' . (string)$row->lastname)); ?></span>
						<span class="admindash-admin-list__value"><?php echo s($row->coursename); ?> · <?php echo s($age); ?><?php echo (int)$row->lastaccess > 0 ? ' · last active ' . s(userdate((int)$row->lastaccess, '%d %b %Y')) : ' · no recent access'; ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>

</div>

<?php if ($resolvedcourseid > 0): ?>
	<div class="admindash-card admindash-admin-panel mt-3">
		<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
			<div>
				<h5 class="mb-1"><?php echo get_string('ui_compliance_dashboard_at_risk_learners_in_selected_course', 'local_admindashboard'); ?></h5>
				<p class="admindash-admin-note mb-0"><?php echo get_string('ui_compliance_dashboard_this_panel_brings_active_outreach_pressure_into_the_compliance__67829c7d', 'local_admindashboard'); ?></p>
			</div>
		</div>
		<div class="admindash-tablewrap">
			<table class="table table-striped table-hover admindash-admin-table">
				<thead>
					<tr>
						<th><?php echo get_string('ui_compliance_dashboard_learner', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_compliance_dashboard_department', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_compliance_dashboard_completion', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_compliance_dashboard_risk_score', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_compliance_dashboard_primary_reason', 'local_admindashboard'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($atriskrows)): ?>
						<tr>
							<td colspan="5" class="text-center py-4"><?php echo get_string('ui_compliance_dashboard_no_at_risk_learners_were_returned_for_the_selected_course', 'local_admindashboard'); ?></td>
						</tr>
					<?php else: ?>
						<?php foreach ($atriskrows as $row): ?>
							<tr>
								<td><?php echo s((string)($row['name'] ?? 'Unknown user')); ?></td>
								<td><?php echo s((string)($row['department'] ?? 'Unassigned')); ?></td>
								<td><?php echo s(rtrim(rtrim(number_format((float)($row['completion_pct'] ?? 0), 1, '.', ''), '0'), '.')); ?>%</td>
								<td><?php echo (int)($row['risk_score'] ?? 0); ?><?php echo get_string('ui_compliance_dashboard_3', 'local_admindashboard'); ?></td>
								<td><?php echo s((string)($row['reasons'][0] ?? 'No reason available')); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>

<?php
local_admindashboard_render_footer();
