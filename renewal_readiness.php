<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

admindash_setup_page('/local/admindashboard/renewal_readiness.php', 'Renewal Readiness', 'skills.renewals');
admindash_render_header('skills.renewals');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$meta = admindash_get_meta($courseid);
$tabs = admindash_get_skill_certifications_suite_tabs();
$certunion = admindash_get_certificate_issue_union_sql();

[$userwhere, $userparams] = admindash_build_user_filter($department);
$extrawhere = '';
$extraparams = [];
if ($courseid > 0) {
	$extrawhere .= ' AND cert.courseid = :courseid';
	$extraparams['courseid'] = $courseid;
}

$rows = [];
$summary = [
	'ready' => 0,
	'watch' => 0,
	'risk' => 0,
	'datagap' => 0,
	'stable' => 0,
];

if ($certunion['available']) {
	$issuedrows = $DB->get_records_sql(
		"SELECT cert.userid, cert.courseid,
				MAX(cert.issuedat) AS latestissuedat,
				MAX(cert.source) AS source,
				u.firstname, u.lastname, COALESCE(u.department, '') AS department,
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
		120
	);

	$now = time();
	foreach ($issuedrows as $row) {
		$issuedat = (int)($row->latestissuedat ?? 0);
		$lastaccess = (int)($row->lastaccess ?? 0);
		$dayssinceissue = $issuedat > 0 ? (int)floor(($now - $issuedat) / DAYSECS) : null;
		$dayssinceaccess = $lastaccess > 0 ? (int)floor(($now - $lastaccess) / DAYSECS) : null;
		$status = 'stable';
		$label = 'Stable';
		$badgeclass = 'is-success';

		if ($issuedat <= 0) {
			$status = 'datagap';
			$label = 'Data gap';
			$badgeclass = 'is-info';
		} else if ($dayssinceissue >= 365 || ($dayssinceissue >= 300 && ($dayssinceaccess === null || $dayssinceaccess > 90))) {
			$status = 'risk';
			$label = 'At risk';
			$badgeclass = 'is-danger';
		} else if ($dayssinceissue >= 300 && $dayssinceaccess !== null && $dayssinceaccess <= 90) {
			$status = 'ready';
			$label = 'Ready now';
			$badgeclass = 'is-success';
		} else if ($dayssinceissue >= 240) {
			$status = 'watch';
			$label = 'Watch window';
			$badgeclass = 'is-warn';
		}

		$summary[$status]++;
		$rows[] = [
			'userid' => (int)$row->userid,
			'courseid' => (int)$row->courseid,
			'name' => trim(fullname($row)),
			'department' => (string)$row->department,
			'coursename' => (string)$row->coursename,
			'issuedat' => $issuedat,
			'dayssinceissue' => $dayssinceissue,
			'dayssinceaccess' => $dayssinceaccess,
			'source' => (string)$row->source,
			'status' => $status,
			'label' => $label,
			'badgeclass' => $badgeclass,
		];
	}

	usort($rows, static function(array $a, array $b): int {
		$priority = ['risk' => 0, 'ready' => 1, 'watch' => 2, 'datagap' => 3, 'stable' => 4];
		$left = $priority[$a['status']] ?? 99;
		$right = $priority[$b['status']] ?? 99;
		if ($left !== $right) {
			return $left <=> $right;
		}
		return ($b['dayssinceissue'] ?? -1) <=> ($a['dayssinceissue'] ?? -1);
	});
}

admindash_render_workspace_header(
	'Reports & Analytics / Skill Gap & Certifications',
	'Renewal Readiness',
	'Operational renewal board that converts certificate age and recent learner activity into readiness signals for follow-up.',
	'certification',
	'skills.renewals',
	$tabs,
	[
		['label' => 'Certificate status', 'url' => new moodle_url('/local/admindashboard/certificate_status.php'), 'primary' => true],
		['label' => 'Support tickets', 'url' => new moodle_url('/local/admindashboard/support_tickets.php'), 'primary' => false],
		['label' => 'Direct messaging', 'url' => new moodle_url('/local/admindashboard/direct_messaging.php'), 'primary' => false],
	],
	[
		'Operational proxy',
		$certunion['hastimestamps'] ? 'Issue age available' : 'Issue age limited',
		'Activity aware',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Filters</div>

	<label class="mb-0" for="courseSelect">Course</label>
	<select id="courseSelect" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
		<?php foreach ($meta['courses'] as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="deptSelect">Department</label>
	<select id="deptSelect" name="department" class="form-select" style="max-width:320px">
		<option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
		<?php foreach ($meta['departments'] as $dept): ?>
			<option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
				<?php echo s($dept); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/renewal_readiness.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Ready Now</div>
		<div class="admindash-module-stat__value"><?php echo $summary['ready']; ?></div>
		<div class="admindash-module-stat__meta">Certificates in the renewal-ready window with recent learner activity.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Watch Window</div>
		<div class="admindash-module-stat__value"><?php echo $summary['watch']; ?></div>
		<div class="admindash-module-stat__meta">Certificates approaching renewal but not yet urgent.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">At Risk</div>
		<div class="admindash-module-stat__value"><?php echo $summary['risk']; ?></div>
		<div class="admindash-module-stat__meta">Old certificates or low-activity learners likely to miss renewal without intervention.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Data Gaps</div>
		<div class="admindash-module-stat__value"><?php echo $summary['datagap']; ?></div>
		<div class="admindash-module-stat__meta">Certificates without usable issue dates, so readiness has to be reviewed manually.</div>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Renewal Candidate Queue</h5>
			<p class="admindash-admin-note mb-0">This queue is an operational proxy based on certificate age and recent activity, not a formal expiry engine.</p>
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
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($rows)): ?>
					<tr>
						<td colspan="7" class="text-center py-4">No renewal readiness rows were returned for the current filters.</td>
					</tr>
				<?php else: ?>
					<?php foreach (array_slice($rows, 0, 40) as $row): ?>
						<tr>
							<td><?php echo s($row['name']); ?></td>
							<td><?php echo s(trim($row['department']) !== '' ? $row['department'] : 'Unassigned'); ?></td>
							<td><?php echo s($row['coursename']); ?></td>
							<td><?php echo s($row['source']); ?></td>
							<td><?php echo $row['dayssinceissue'] !== null ? (int)$row['dayssinceissue'] . ' days' : 'Unknown'; ?></td>
							<td><?php echo $row['dayssinceaccess'] !== null ? (int)$row['dayssinceaccess'] . ' days ago' : 'No recent access'; ?></td>
							<td><span class="admindash-admin-badge <?php echo s($row['badgeclass']); ?>"><?php echo s($row['label']); ?></span></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
admindash_render_footer();