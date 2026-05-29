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

local_admindashboard_setup_page('/local/admindashboard/manage_users.php', 'Manage Users', 'admintools.users.list');
local_admindashboard_render_header('admintools.users.list');

$department = trim(optional_param('department', '', PARAM_TEXT));
$roleid = max(0, optional_param('roleid', 0, PARAM_INT));
$status = trim(optional_param('status', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 20;

$meta = local_admindashboard_get_meta();
$statusoptions = [
	'all' => 'All accounts',
	'active' => 'Enabled only',
	'suspended' => 'Suspended only',
	'neveraccess' => 'Never accessed',
	'missingdepartment' => 'Missing department',
];
if (!array_key_exists($status, $statusoptions)) {
	$status = 'all';
}

$roles = $DB->get_records_sql(
	"SELECT r.id, r.name, r.shortname
	   FROM {role} r
	  WHERE (r.archetype IS NULL OR (r.archetype <> :guestarch AND r.archetype <> :userarch))
   ORDER BY r.sortorder ASC, r.name ASC",
	[
		'guestarch' => 'guest',
		'userarch' => 'user',
	]
);

$conditions = [
	'u.deleted = 0',
	'u.confirmed = 1',
	"u.username <> 'guest'",
	'u.username NOT LIKE :testuser',
	'u.username NOT LIKE :demouser',
];
$params = [
	'testuser' => '%test%',
	'demouser' => '%demo%',
];

if ($department !== '') {
	$conditions[] = "COALESCE(u.department, '') = :department";
	$params['department'] = $department;
}

if ($roleid > 0) {
	$conditions[] = 'EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = :roleid)';
	$params['roleid'] = $roleid;
}

if ($q !== '') {
	$conditions[] = '(u.firstname LIKE :qfn OR u.lastname LIKE :qln OR u.email LIKE :qem OR u.username LIKE :qun)';
	$like = '%' . $q . '%';
	$params['qfn'] = $like;
	$params['qln'] = $like;
	$params['qem'] = $like;
	$params['qun'] = $like;
}

switch ($status) {
	case 'active':
		$conditions[] = 'u.suspended = 0';
		break;
	case 'suspended':
		$conditions[] = 'u.suspended = 1';
		break;
	case 'neveraccess':
		$conditions[] = 'u.suspended = 0';
		$conditions[] = 'COALESCE(u.lastaccess, 0) = 0';
		break;
	case 'missingdepartment':
		$conditions[] = "TRIM(COALESCE(u.department, '')) = ''";
		break;
}

$where = implode(' AND ', $conditions);

$summary = $DB->get_record_sql(
	"SELECT COUNT(1) AS totalusers,
			SUM(CASE WHEN u.suspended = 0 THEN 1 ELSE 0 END) AS activeusers,
			SUM(CASE WHEN u.suspended = 1 THEN 1 ELSE 0 END) AS suspendedusers,
			SUM(CASE WHEN TRIM(COALESCE(u.department, '')) = '' THEN 1 ELSE 0 END) AS missingdepartment,
			SUM(CASE WHEN COALESCE(u.lastaccess, 0) = 0 THEN 1 ELSE 0 END) AS neveraccess
	   FROM {user} u
	  WHERE {$where}",
	$params
);

$total = (int)($summary->totalusers ?? 0);

$rows = $DB->get_records_sql(
	"SELECT u.id, u.firstname, u.lastname, u.email, u.username,
			COALESCE(u.department, '') AS department,
			COALESCE(u.lastaccess, 0) AS lastaccess,
			u.suspended,
			COUNT(DISTINCT e.courseid) AS enrolledcourses
	   FROM {user} u
  LEFT JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
  LEFT JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
	  WHERE {$where}
   GROUP BY u.id, u.firstname, u.lastname, u.email, u.username, u.department, u.lastaccess, u.suspended
   ORDER BY u.suspended ASC, u.lastaccess DESC, u.lastname ASC, u.firstname ASC",
	$params,
	$page * $perpage,
	$perpage
);

$userids = array_map(static fn($row) => (int)$row->id, $rows);
$rolesbyuser = [];
if (!empty($userids)) {
	list($userinsql, $userinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
	$rolerows = $DB->get_records_sql(
		"SELECT ra.id AS assignmentid, ra.userid, r.name, r.shortname, r.sortorder
		   FROM {role_assignments} ra
		   JOIN {role} r ON r.id = ra.roleid
		  WHERE ra.userid {$userinsql}
			AND (r.archetype IS NULL OR (r.archetype <> :guestarch2 AND r.archetype <> :userarch2))
	   ORDER BY ra.userid ASC, r.sortorder ASC, r.name ASC",
		$userinparams + [
			'guestarch2' => 'guest',
			'userarch2' => 'user',
		]
	);
	foreach ($rolerows as $rolerow) {
		$userid = (int)$rolerow->userid;
		$label = trim((string)($rolerow->name ?: $rolerow->shortname));
		if ($label === '') {
			continue;
		}
		if (!isset($rolesbyuser[$userid])) {
			$rolesbyuser[$userid] = [];
		}
		$rolesbyuser[$userid][] = $label;
	}
}

$departmentrows = $DB->get_records_sql(
	"SELECT COALESCE(NULLIF(TRIM(u.department), ''), 'Unassigned') AS departmentlabel,
			COUNT(1) AS usercount
	   FROM {user} u
	  WHERE {$where}
   GROUP BY COALESCE(NULLIF(TRIM(u.department), ''), 'Unassigned')
   ORDER BY usercount DESC, departmentlabel ASC",
	$params,
	0,
	8
);

$baseurl = new moodle_url('/local/admindashboard/manage_users.php', [
	'department' => $department,
	'roleid' => $roleid,
	'status' => $status,
	'q' => $q,
]);
$tabs = local_admindashboard_get_manage_users_suite_tabs();
?>

<?php
local_admindashboard_render_workspace_header(
	'Admin Tools / Manage Users',
	'Manage Users',
	'Control room for account visibility, onboarding hygiene, and quick intervention across departments, roles, and account states.',
	'users',
	'admintools.users.list',
	$tabs,
	[
		['label' => 'Add user workspace', 'url' => new moodle_url('/local/admindashboard/add_user.php'), 'primary' => true],
		['label' => 'Define roles', 'url' => new moodle_url('/local/admindashboard/define_roles.php'), 'primary' => false],
		['label' => 'Core user admin', 'url' => new moodle_url('/admin/user.php'), 'primary' => false],
	],
	[
		'Live directory',
		'Filter-ready',
		'Operations focused',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Filters</div>

	<label class="mb-0" for="deptSelect">Department</label>
	<select id="deptSelect" name="department" class="form-select" style="max-width:280px">
		<option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
		<?php foreach ($meta['departments'] as $dept): ?>
			<option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
				<?php echo s($dept); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="roleSelect">Role</label>
	<select id="roleSelect" name="roleid" class="form-select" style="max-width:240px">
		<option value="0" <?php echo $roleid === 0 ? 'selected' : ''; ?>>All Roles</option>
		<?php foreach ($roles as $role): ?>
			<option value="<?php echo (int)$role->id; ?>" <?php echo $roleid === (int)$role->id ? 'selected' : ''; ?>>
				<?php echo s(trim((string)($role->name ?: $role->shortname))); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="statusSelect">Status</label>
	<select id="statusSelect" name="status" class="form-select" style="max-width:240px">
		<?php foreach ($statusoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="searchInput">Search</label>
	<input id="searchInput" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Name, username, or email" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/manage_users.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Accounts</div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->totalusers ?? 0); ?></div>
		<div class="admindash-module-stat__meta">Users matched by the current filters.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Enabled</div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->activeusers ?? 0); ?></div>
		<div class="admindash-module-stat__meta">Accounts that are not suspended.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Suspended</div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->suspendedusers ?? 0); ?></div>
		<div class="admindash-module-stat__meta">Accounts currently blocked from access.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Needs Cleanup</div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->missingdepartment ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo (int)($summary->neveraccess ?? 0); ?> never accessed and <?php echo (int)($summary->missingdepartment ?? 0); ?> missing department.</div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<h5 class="mb-3">Department Distribution</h5>
		<?php if (!empty($departmentrows)): ?>
			<ul class="admindash-admin-list">
				<?php foreach ($departmentrows as $deptrow): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($deptrow->departmentlabel); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$deptrow->usercount; ?> users</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0">No user records matched the current filters.</p>
		<?php endif; ?>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">User Directory</h5>
			<p class="admindash-admin-note mb-0">Showing <?php echo $total > 0 ? (($page * $perpage) + 1) : 0; ?>-<?php echo min((($page + 1) * $perpage), $total); ?> of <?php echo $total; ?> matching accounts.</p>
		</div>
	</div>

	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>User</th>
					<th>Department</th>
					<th>Roles</th>
					<th>Last access</th>
					<th>Enrolled courses</th>
					<th>Status</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($rows)): ?>
					<tr>
						<td colspan="7" class="text-center py-4">No users found for the current filters.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($rows as $row): ?>
						<?php
						$fullname = fullname($row);
						$lastaccess = !empty($row->lastaccess) ? userdate((int)$row->lastaccess) : 'Never';
						$userroles = $rolesbyuser[(int)$row->id] ?? [];
						$rolelabel = !empty($userroles) ? implode(', ', array_slice($userroles, 0, 3)) : 'No explicit roles';
						if (count($userroles) > 3) {
							$rolelabel .= ' +' . (count($userroles) - 3);
						}
						$badges = [];
						$badges[] = '<span class="admindash-admin-badge ' . (((int)$row->suspended) === 1 ? 'is-danger' : 'is-success') . '">' . (((int)$row->suspended) === 1 ? 'Suspended' : 'Enabled') . '</span>';
						if ((int)$row->lastaccess === 0) {
							$badges[] = '<span class="admindash-admin-badge is-warn">Never accessed</span>';
						}
						if (trim((string)$row->department) === '') {
							$badges[] = '<span class="admindash-admin-badge is-info">No department</span>';
						}
						?>
						<tr>
							<td>
								<div class="admindash-admin-user">
									<a href="<?php echo new moodle_url('/user/profile.php', ['id' => (int)$row->id]); ?>" class="admindash-admin-user__name"><?php echo s($fullname); ?></a>
									<div class="admindash-admin-note"><?php echo s($row->email); ?> · <?php echo s($row->username); ?></div>
								</div>
							</td>
							<td><?php echo s(trim((string)$row->department) !== '' ? $row->department : 'Unassigned'); ?></td>
							<td><?php echo s($rolelabel); ?></td>
							<td><?php echo s($lastaccess); ?></td>
							<td><?php echo (int)$row->enrolledcourses; ?></td>
							<td><div class="admindash-admin-badges"><?php echo implode('', $badges); ?></div></td>
							<td>
								<div class="admindash-admin-actions-inline">
									<a href="<?php echo new moodle_url('/user/editadvanced.php', ['id' => (int)$row->id]); ?>">Edit</a>
									<a href="<?php echo new moodle_url('/user/profile.php', ['id' => (int)$row->id]); ?>">Profile</a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl); ?>
</div>

<?php
local_admindashboard_render_footer();
