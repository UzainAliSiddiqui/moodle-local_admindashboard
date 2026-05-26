<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

admindash_setup_page('/local/admindashboard/group_department_setup.php', 'Group & Department Setup', 'admintools.groups');
admindash_render_header('admintools.groups');

$q = trim(optional_param('q', '', PARAM_TEXT));
$cutoff = time() - (30 * DAYSECS);
$systemcontextid = context_system::instance()->id;

$baseconditions = [
	'u.deleted = 0',
	'u.confirmed = 1',
	"u.username <> 'guest'",
	'u.username NOT LIKE :testuser',
	'u.username NOT LIKE :demouser',
];
$baseparams = [
	'testuser' => '%test%',
	'demouser' => '%demo%',
];
$basewhere = implode(' AND ', $baseconditions);

$summary = $DB->get_record_sql(
	"SELECT COUNT(DISTINCT CASE WHEN TRIM(COALESCE(u.department, '')) <> '' THEN u.department ELSE NULL END) AS totaldepartments,
			SUM(CASE WHEN TRIM(COALESCE(u.department, '')) = '' THEN 1 ELSE 0 END) AS unassignedusers,
			SUM(CASE WHEN u.suspended = 0 THEN 1 ELSE 0 END) AS enabledusers,
			SUM(CASE WHEN COALESCE(u.lastaccess, 0) >= :cutoff THEN 1 ELSE 0 END) AS active30users
	   FROM {user} u
	  WHERE {$basewhere}",
	['cutoff' => $cutoff] + $baseparams
);

$totalcohorts = (int)$DB->count_records('cohort');
$profilefieldcount = (int)$DB->count_records('user_info_field');

$deptconditions = $baseconditions;
$deptparams = $baseparams + ['cutoff' => $cutoff];
if ($q !== '') {
	$deptconditions[] = "COALESCE(u.department, '') LIKE :deptq";
	$deptparams['deptq'] = '%' . $q . '%';
}
$deptwhere = implode(' AND ', $deptconditions);

$departmentrows = $DB->get_records_sql(
	"SELECT COALESCE(NULLIF(TRIM(u.department), ''), 'Unassigned') AS departmentlabel,
			COUNT(1) AS totalusers,
			SUM(CASE WHEN u.suspended = 0 THEN 1 ELSE 0 END) AS enabledusers,
			SUM(CASE WHEN COALESCE(u.lastaccess, 0) >= :cutoff THEN 1 ELSE 0 END) AS active30users
	   FROM {user} u
	  WHERE {$deptwhere}
   GROUP BY COALESCE(NULLIF(TRIM(u.department), ''), 'Unassigned')
   ORDER BY totalusers DESC, departmentlabel ASC",
	$deptparams,
	0,
	25
);

$cohortparams = [];
$cohortwhere = '';
if ($q !== '') {
	$cohortwhere = 'WHERE (c.name LIKE :cohortq OR c.idnumber LIKE :cohortidq)';
	$cohortparams['cohortq'] = '%' . $q . '%';
	$cohortparams['cohortidq'] = '%' . $q . '%';
}

$cohortrows = $DB->get_records_sql(
	"SELECT c.id, c.name, COALESCE(c.idnumber, '') AS idnumber,
			COUNT(cm.userid) AS membercount
	   FROM {cohort} c
  LEFT JOIN {cohort_members} cm ON cm.cohortid = c.id
			{$cohortwhere}
   GROUP BY c.id, c.name, c.idnumber
   ORDER BY membercount DESC, c.name ASC",
	$cohortparams,
	0,
	12
);

$fieldparams = [];
$fieldwhere = '';
if ($q !== '') {
	$fieldwhere = 'WHERE (f.name LIKE :fieldq OR f.shortname LIKE :fieldshortq)';
	$fieldparams['fieldq'] = '%' . $q . '%';
	$fieldparams['fieldshortq'] = '%' . $q . '%';
}

$profilefields = $DB->get_records_sql(
	"SELECT f.id, f.name, f.shortname, f.datatype, f.required
	   FROM {user_info_field} f
			{$fieldwhere}
   ORDER BY f.sortorder ASC, f.name ASC",
	$fieldparams,
	0,
	12
);
?>

<h2 class="mb-3">Group &amp; Department Setup</h2>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Directory Search</div>
	<label class="mb-0" for="groupSearch">Find group, department, or profile field</label>
	<input id="groupSearch" name="q" class="form-control" style="max-width:420px" value="<?php echo s($q); ?>" placeholder="Search department names, cohorts, or profile fields" />
	<button type="submit" class="btn btn-primary">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/group_department_setup.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Departments</div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->totaldepartments ?? 0); ?></div>
		<div class="admindash-module-stat__meta">Unique department values currently used on real user accounts.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Unassigned Users</div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->unassignedusers ?? 0); ?></div>
		<div class="admindash-module-stat__meta">Accounts missing a department tag and likely affecting reporting.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Cohorts</div>
		<div class="admindash-module-stat__value"><?php echo $totalcohorts; ?></div>
		<div class="admindash-module-stat__meta">System cohorts available for enrolment and audience grouping.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Profile Fields</div>
		<div class="admindash-module-stat__value"><?php echo $profilefieldcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo (int)($summary->active30users ?? 0); ?> active users in the last 30 days across the current directory.</div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<h5 class="mb-3">Configuration Actions</h5>
		<div class="admindash-module-actions">
			<a class="btn btn-primary" href="<?php echo new moodle_url('/user/profile/index.php'); ?>">Profile fields</a>
			<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/cohort/index.php', ['contextid' => $systemcontextid]); ?>">Manage cohorts</a>
			<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/manage_users.php'); ?>">Manage users</a>
			<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/department_reports.php'); ?>">Department reports</a>
		</div>
		<p class="admindash-admin-note mb-0">This page gives you the reporting layer around taxonomy setup, while the linked core screens still handle the actual edit workflows.</p>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<h5 class="mb-3">Department Signals</h5>
		<?php if (!empty($departmentrows)): ?>
			<ul class="admindash-admin-list">
				<?php foreach (array_slice(array_values($departmentrows), 0, 8) as $departmentrow): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($departmentrow->departmentlabel); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$departmentrow->enabledusers; ?> enabled / <?php echo (int)$departmentrow->active30users; ?> active 30d</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0">No departments matched the current search.</p>
		<?php endif; ?>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Department Directory</h5>
			<span class="admindash-admin-note"><?php echo count($departmentrows); ?> rows</span>
		</div>
		<div class="admindash-tablewrap">
			<table class="table table-striped table-hover admindash-admin-table">
				<thead>
					<tr>
						<th>Department</th>
						<th>Total users</th>
						<th>Enabled</th>
						<th>Active in 30d</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($departmentrows)): ?>
						<tr>
							<td colspan="4" class="text-center py-4">No department records found.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($departmentrows as $departmentrow): ?>
							<tr>
								<td><?php echo s($departmentrow->departmentlabel); ?></td>
								<td><?php echo (int)$departmentrow->totalusers; ?></td>
								<td><?php echo (int)$departmentrow->enabledusers; ?></td>
								<td><?php echo (int)$departmentrow->active30users; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="admindash-admin-stack">
		<div class="admindash-card admindash-admin-panel">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0">Cohorts</h5>
				<span class="admindash-admin-note"><?php echo $totalcohorts; ?> total</span>
			</div>
			<div class="admindash-tablewrap">
				<table class="table table-striped table-hover admindash-admin-table">
					<thead>
						<tr>
							<th>Name</th>
							<th>ID Number</th>
							<th>Members</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($cohortrows)): ?>
							<tr>
								<td colspan="3" class="text-center py-4">No cohorts matched the current search.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($cohortrows as $cohortrow): ?>
								<tr>
									<td><?php echo s($cohortrow->name); ?></td>
									<td><?php echo s($cohortrow->idnumber !== '' ? $cohortrow->idnumber : '-'); ?></td>
									<td><?php echo (int)$cohortrow->membercount; ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="admindash-card admindash-admin-panel mt-3">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0">Custom Profile Fields</h5>
				<span class="admindash-admin-note"><?php echo $profilefieldcount; ?> configured</span>
			</div>
			<div class="admindash-tablewrap">
				<table class="table table-striped table-hover admindash-admin-table">
					<thead>
						<tr>
							<th>Name</th>
							<th>Shortname</th>
							<th>Type</th>
							<th>Required</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($profilefields)): ?>
							<tr>
								<td colspan="4" class="text-center py-4">No custom profile fields are configured.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($profilefields as $field): ?>
								<tr>
									<td><?php echo s($field->name); ?></td>
									<td><?php echo s($field->shortname); ?></td>
									<td><?php echo s($field->datatype); ?></td>
									<td><?php echo !empty($field->required) ? 'Yes' : 'No'; ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<?php
admindash_render_footer();