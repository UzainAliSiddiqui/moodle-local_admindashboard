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

local_admindashboard_setup_page('/local/admindashboard/certificate_status.php', 'Certificate Status', 'skills.certificates');
local_admindashboard_render_header('skills.certificates');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$q = trim(optional_param('q', '', PARAM_TEXT));

$meta = local_admindashboard_get_meta($courseid);
$tabs = local_admindashboard_get_skill_certifications_suite_tabs();
$certunion = local_admindashboard_get_certificate_issue_union_sql();

[$userwhere, $userparams] = local_admindashboard_build_user_filter($department);
$extraconditions = [];
$extraparams = [];
if ($courseid > 0) {
	$extraconditions[] = 'cert.courseid = :courseid';
	$extraparams['courseid'] = $courseid;
}
if ($q !== '') {
	$extraconditions[] = '(c.fullname LIKE :qcourse OR u.firstname LIKE :qfn OR u.lastname LIKE :qln)';
	$like = '%' . $q . '%';
	$extraparams['qcourse'] = $like;
	$extraparams['qfn'] = $like;
	$extraparams['qln'] = $like;
}
$extrawhere = empty($extraconditions) ? '' : ' AND ' . implode(' AND ', $extraconditions);

$summary = (object)[
	'totalissues' => 0,
	'userscovered' => 0,
	'coursescovered' => 0,
	'recentissues' => 0,
];
$sourcerows = [];
$courserows = [];
$issuerows = [];

if ($certunion['available']) {
	$summary = $DB->get_record_sql(
		"SELECT COUNT(1) AS totalissues,
				COUNT(DISTINCT cert.userid) AS userscovered,
				COUNT(DISTINCT cert.courseid) AS coursescovered,
				SUM(CASE WHEN cert.issuedat >= :recentcutoff THEN 1 ELSE 0 END) AS recentissues
		   FROM ({$certunion['sql']}) cert
		   JOIN {user} u ON u.id = cert.userid
	  LEFT JOIN {course} c ON c.id = cert.courseid
		  WHERE {$userwhere}{$extrawhere}",
		['recentcutoff' => time() - (90 * DAYSECS)] + $userparams + $extraparams
	);

	$sourcerows = $DB->get_records_sql(
		"SELECT cert.source, COUNT(1) AS issuecount
		   FROM ({$certunion['sql']}) cert
		   JOIN {user} u ON u.id = cert.userid
	  LEFT JOIN {course} c ON c.id = cert.courseid
		  WHERE {$userwhere}{$extrawhere}
	   GROUP BY cert.source
	   ORDER BY issuecount DESC, cert.source ASC",
		$userparams + $extraparams,
		0,
		8
	);

	$courserows = $DB->get_records_sql(
		"SELECT cert.courseid, COALESCE(c.fullname, 'Unknown course') AS coursename,
				COUNT(1) AS issuecount,
				COUNT(DISTINCT cert.userid) AS userscovered,
				MAX(cert.issuedat) AS latestissuedat
		   FROM ({$certunion['sql']}) cert
		   JOIN {user} u ON u.id = cert.userid
	  LEFT JOIN {course} c ON c.id = cert.courseid
		  WHERE {$userwhere}{$extrawhere}
	   GROUP BY cert.courseid, c.fullname
	   ORDER BY issuecount DESC, userscovered DESC, coursename ASC",
		$userparams + $extraparams,
		0,
		10
	);

	$issuerows = $DB->get_records_sql(
		"SELECT cert.userid, cert.courseid, cert.source, cert.issuedat,
				u.firstname, u.lastname, COALESCE(u.department, '') AS department,
				COALESCE(c.fullname, 'Unknown course') AS coursename
		   FROM ({$certunion['sql']}) cert
		   JOIN {user} u ON u.id = cert.userid
	  LEFT JOIN {course} c ON c.id = cert.courseid
		  WHERE {$userwhere}{$extrawhere}
	   ORDER BY cert.issuedat DESC, coursename ASC, u.lastname ASC, u.firstname ASC",
		$userparams + $extraparams,
		0,
		25
	);
}

local_admindashboard_render_workspace_header(
	'Reports & Analytics / Skill Gap & Certifications',
	'Certificate Status',
	'Certification issuance board that shows which courses are producing certificates, who is covered, and where issuance activity is concentrated.',
	'certification',
	'skills.certificates',
	$tabs,
	[
		['label' => 'Renewal readiness', 'url' => new moodle_url('/local/admindashboard/renewal_readiness.php'), 'primary' => true],
		['label' => 'Skill gap matrix', 'url' => new moodle_url('/local/admindashboard/skill_gap_matrix.php'), 'primary' => false],
		['label' => 'Export center', 'url' => new moodle_url('/local/admindashboard/export_center.php'), 'primary' => false],
	],
	[
		$certunion['available'] ? 'Certificate data live' : 'No certificate plugin detected',
		$certunion['hastimestamps'] ? 'Issue dates available' : 'Issue dates limited',
		'Course aware',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title"><?php echo get_string('ui_certificate_status_filters', 'local_admindashboard'); ?></div>

	<label class="mb-0" for="courseSelect"><?php echo get_string('ui_certificate_status_course', 'local_admindashboard'); ?></label>
	<select id="courseSelect" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
		<?php foreach ($meta['courses'] as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="deptSelect"><?php echo get_string('ui_certificate_status_department', 'local_admindashboard'); ?></label>
	<select id="deptSelect" name="department" class="form-select" style="max-width:320px">
		<option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
		<?php foreach ($meta['departments'] as $dept): ?>
			<option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
				<?php echo s($dept); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="statusSearch"><?php echo get_string('ui_certificate_status_search', 'local_admindashboard'); ?></label>
	<input id="statusSearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Course or learner name" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_certificate_status_apply', 'local_admindashboard'); ?></button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/certificate_status.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_certificate_status_issues', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->totalissues ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_certificate_status_certificate_issues_returned_by_installed_certificate_plugins_fo_0a49a8aa', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_certificate_status_users_covered', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->userscovered ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_certificate_status_distinct_learners_who_already_hold_at_least_one_certificate_in__9ad95c4e', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_certificate_status_courses_covered', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->coursescovered ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_certificate_status_courses_currently_issuing_certificates_through_supported_plugins', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_certificate_status_recent_90d', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->recentissues ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo $certunion['hastimestamps'] ? 'Issues created in the last 90 days.' : 'Issue timestamps are not available from every detected certificate source.'; ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_certificate_status_issue_sources', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_certificate_status_plugin_mix', 'local_admindashboard'); ?></span>
		</div>
		<?php if (!empty($sourcerows)): ?>
			<ul class="admindash-admin-list">
				<?php foreach ($sourcerows as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($row->source); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->issuecount; ?> <?php echo get_string('ui_certificate_status_issues', 'local_admindashboard'); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_certificate_status_no_certificate_issue_data_was_detected_from_installed_plugins', 'local_admindashboard'); ?></p>
		<?php endif; ?>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_certificate_status_courses_with_certification_activity', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_certificate_status_top_issuers', 'local_admindashboard'); ?></span>
		</div>
		<?php if (!empty($courserows)): ?>
			<ul class="admindash-admin-list">
				<?php foreach ($courserows as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($row->coursename); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->issuecount; ?> <?php echo get_string('ui_certificate_status_issues', 'local_admindashboard'); ?> <?php echo (int)$row->userscovered; ?> <?php echo get_string('ui_certificate_status_users', 'local_admindashboard'); ?><?php echo ((int)$row->latestissuedat > 0) ? ' · latest ' . s(userdate((int)$row->latestissuedat, '%d %b %Y')) : ''; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_certificate_status_no_certificate_enabled_courses_were_returned_for_the_current_filters', 'local_admindashboard'); ?></p>
		<?php endif; ?>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_certificate_status_recent_issue_feed', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_certificate_status_latest_25_records', 'local_admindashboard'); ?></span>
		</div>
		<div class="admindash-tablewrap">
			<table class="table table-striped table-hover admindash-admin-table">
				<thead>
					<tr>
						<th><?php echo get_string('ui_certificate_status_learner', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_certificate_status_department', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_certificate_status_course', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_certificate_status_source', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_certificate_status_issued', 'local_admindashboard'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($issuerows)): ?>
						<tr>
							<td colspan="5" class="text-center py-4"><?php echo get_string('ui_certificate_status_no_certificate_issues_were_returned', 'local_admindashboard'); ?></td>
						</tr>
					<?php else: ?>
						<?php foreach ($issuerows as $row): ?>
							<tr>
								<td><?php echo s(trim(fullname($row))); ?></td>
								<td><?php echo s(trim((string)$row->department) !== '' ? $row->department : 'Unassigned'); ?></td>
								<td><?php echo s($row->coursename); ?></td>
								<td><?php echo s($row->source); ?></td>
								<td><?php echo (int)$row->issuedat > 0 ? s(userdate((int)$row->issuedat)) : 'Unknown'; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
local_admindashboard_render_footer();