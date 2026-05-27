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

local_admindashboard_setup_page('/local/admindashboard/license_expiry.php', 'License Expiry', 'compliance.expiry');
local_admindashboard_render_header('compliance.expiry');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$status = trim(optional_param('status', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));

$meta = local_admindashboard_get_meta($courseid);
$tabs = local_admindashboard_get_compliance_suite_tabs();
$certunion = local_admindashboard_get_certificate_issue_union_sql();

$statusoptions = [
	'all' => 'All windows',
	'expired' => 'Expired proxy',
	'due' => 'Due soon',
	'watch' => 'Watch window',
	'current' => 'Current',
	'datagap' => 'Data gap',
];
if (!array_key_exists($status, $statusoptions)) {
	$status = 'all';
}

[$userwhere, $userparams] = local_admindashboard_build_user_filter($department);

$rows = [];
$summary = [
	'expired' => 0,
	'due' => 0,
	'watch' => 0,
	'current' => 0,
	'datagap' => 0,
];

if ($certunion['available']) {
	$extrawhere = '';
	$extraparams = [];
	if ($courseid > 0) {
		$extrawhere .= ' AND cert.courseid = :courseid';
		$extraparams['courseid'] = $courseid;
	}
	if ($q !== '') {
		$like = '%' . $q . '%';
		$extrawhere .= ' AND (u.firstname LIKE :qfn OR u.lastname LIKE :qln OR c.fullname LIKE :qcourse)';
		$extraparams['qfn'] = $like;
		$extraparams['qln'] = $like;
		$extraparams['qcourse'] = $like;
	}

	$issuedrows = $DB->get_records_sql(
		"SELECT cert.userid,
				cert.courseid,
				MAX(cert.issuedat) AS latestissuedat,
				MAX(cert.source) AS source,
				u.firstname,
				u.lastname,
				COALESCE(u.department, '') AS department,
				COALESCE(u.lastaccess, 0) AS lastaccess,
				COALESCE(c.fullname, 'Unknown course') AS coursename
		   FROM ({$certunion['sql']}) cert
		   JOIN {user} u ON u.id = cert.userid
	  LEFT JOIN {course} c ON c.id = cert.courseid
		  WHERE {$userwhere}{$extrawhere}
	   GROUP BY cert.userid, cert.courseid, u.firstname, u.lastname, u.department, u.lastaccess, c.fullname
	   ORDER BY latestissuedat ASC, coursename ASC, u.lastname ASC, u.firstname ASC",
		$userparams + $extraparams,
		0,
		150
	);

	$now = time();
	foreach ($issuedrows as $row) {
		$issuedat = (int)($row->latestissuedat ?? 0);
		$dayssinceissue = $issuedat > 0 ? (int)floor(($now - $issuedat) / DAYSECS) : null;
		$window = 'current';
		$label = 'Current';
		$badgeclass = 'is-success';

		if ($issuedat <= 0) {
			$window = 'datagap';
			$label = 'Data gap';
			$badgeclass = 'is-info';
		} else if ($dayssinceissue >= 365) {
			$window = 'expired';
			$label = 'Expired proxy';
			$badgeclass = 'is-danger';
		} else if ($dayssinceissue >= 300) {
			$window = 'due';
			$label = 'Due soon';
			$badgeclass = 'is-warn';
		} else if ($dayssinceissue >= 240) {
			$window = 'watch';
			$label = 'Watch window';
			$badgeclass = 'is-info';
		}

		$summary[$window]++;
		if ($status !== 'all' && $status !== $window) {
			continue;
		}

		$rows[] = [
			'userid' => (int)$row->userid,
			'courseid' => (int)$row->courseid,
			'name' => trim(fullname($row)),
			'department' => (string)$row->department,
			'coursename' => (string)$row->coursename,
			'issuedat' => $issuedat,
			'dayssinceissue' => $dayssinceissue,
			'lastaccess' => (int)$row->lastaccess,
			'source' => (string)$row->source,
			'window' => $window,
			'label' => $label,
			'badgeclass' => $badgeclass,
		];
	}
}

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

local_admindashboard_render_workspace_header(
	'Reports & Analytics',
	'License Expiry',
	'Certificate-age expiry proxy for identifying expired, due-soon, and watch-window records even when formal expiry dates are not stored directly.',
	'compliance',
	'compliance.expiry',
	$tabs,
	[
		['label' => 'Compliance Dashboard', 'url' => new moodle_url('/local/admindashboard/compliance_dashboard.php'), 'primary' => true],
		['label' => 'Renewal Readiness', 'url' => new moodle_url('/local/admindashboard/renewal_readiness.php'), 'primary' => false],
		['label' => 'Export Center', 'url' => new moodle_url('/local/admindashboard/export_center.php'), 'primary' => false],
	],
	[
		$certunion['available'] ? 'Certificate source live' : 'No certificate source',
		$summary['expired'] . ' expired proxy',
		$summary['due'] . ' due soon',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Filters</div>

	<label class="mb-0" for="expiryCourse">Course</label>
	<select id="expiryCourse" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
		<?php foreach ($meta['courses'] as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="expiryDepartment">Department</label>
	<select id="expiryDepartment" name="department" class="form-select" style="max-width:320px">
		<option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
		<?php foreach ($meta['departments'] as $dept): ?>
			<option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
				<?php echo s($dept); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="expiryStatus">Window</label>
	<select id="expiryStatus" name="status" class="form-select" style="max-width:240px">
		<?php foreach ($statusoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="expirySearch">Search</label>
	<input id="expirySearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Course or learner name" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/license_expiry.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Expired Proxy</div>
		<div class="admindash-module-stat__value"><?php echo $summary['expired']; ?></div>
		<div class="admindash-module-stat__meta">Records with certificate issue dates at least 365 days old.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Due Soon</div>
		<div class="admindash-module-stat__value"><?php echo $summary['due']; ?></div>
		<div class="admindash-module-stat__meta">Records 300-364 days old and approaching renewal follow-up.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Watch Window</div>
		<div class="admindash-module-stat__value"><?php echo $summary['watch']; ?></div>
		<div class="admindash-module-stat__meta">Records entering an early monitoring window before urgent outreach is needed.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Data Gaps</div>
		<div class="admindash-module-stat__value"><?php echo $summary['datagap']; ?></div>
		<div class="admindash-module-stat__meta">Certificate-backed records with no usable issue timestamp.</div>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Expiry Queue</h5>
			<p class="admindash-admin-note mb-0">These rows are based on certificate issue age, not a formal expiry engine, so they should be treated as operational guidance.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Learner</th>
					<th>Department</th>
					<th>Course</th>
					<th>Source</th>
					<th>Issue Age</th>
					<th>Last Access</th>
					<th>Window</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($rows)): ?>
					<tr>
						<td colspan="7" class="text-center py-4">No certificate expiry rows matched the current filters.</td>
					</tr>
				<?php else: ?>
					<?php foreach (array_slice($rows, 0, 60) as $row): ?>
						<tr>
							<td><?php echo s($row['name']); ?></td>
							<td><?php echo s(trim($row['department']) !== '' ? $row['department'] : 'Unassigned'); ?></td>
							<td><?php echo s($row['coursename']); ?></td>
							<td><?php echo s($row['source']); ?></td>
							<td><?php echo $row['dayssinceissue'] !== null ? (int)$row['dayssinceissue'] . ' days' : 'Unknown'; ?></td>
							<td><?php echo (int)$row['lastaccess'] > 0 ? s(userdate((int)$row['lastaccess'], '%d %b %Y')) : 'No recorded access'; ?></td>
							<td><?php echo $statusbadge($row['label'], $row['badgeclass']); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
local_admindashboard_render_footer();