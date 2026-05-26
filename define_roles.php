<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

admindash_setup_page('/local/admindashboard/define_roles.php', 'Define Roles', 'admintools.users.roles');
admindash_render_header('admintools.users.roles');

$tabs = admindash_get_manage_users_suite_tabs();
$rolequery = $DB->get_records_sql(
	"SELECT r.id, r.name, r.shortname, COALESCE(r.archetype, '') AS archetype,
			COUNT(DISTINCT ra.userid) AS assignmentcount,
			COUNT(DISTINCT rcl.contextlevel) AS contextcount,
			r.sortorder
	   FROM {role} r
	LEFT JOIN {role_assignments} ra ON ra.roleid = r.id
	LEFT JOIN {role_context_levels} rcl ON rcl.roleid = r.id
	  WHERE (r.archetype IS NULL OR r.archetype <> :guestarch)
	GROUP BY r.id, r.name, r.shortname, r.archetype, r.sortorder
	ORDER BY assignmentcount DESC, r.sortorder ASC, r.name ASC",
	['guestarch' => 'guest']
);

$roles = array_values($rolequery);
$totalroles = count($roles);
$customroles = 0;
$privilegedroles = 0;
$totalassignments = 0;
$contextmap = [
	CONTEXT_SYSTEM => 'System',
	CONTEXT_COURSECAT => 'Category',
	CONTEXT_COURSE => 'Course',
	CONTEXT_MODULE => 'Activity',
	CONTEXT_USER => 'User',
];

foreach ($roles as $role) {
	$totalassignments += (int)$role->assignmentcount;
	$archetype = trim((string)$role->archetype);
	$shortname = trim((string)$role->shortname);
	if ($archetype === '') {
		$customroles++;
	}
	if (in_array($archetype, ['manager', 'coursecreator', 'teacher'], true)
			|| in_array($shortname, ['manager', 'coursecreator', 'editingteacher'], true)) {
		$privilegedroles++;
	}
}

$contextsummary = $DB->get_records_sql(
	"SELECT rcl.contextlevel, COUNT(DISTINCT rcl.roleid) AS rolecount
	   FROM {role_context_levels} rcl
	GROUP BY rcl.contextlevel
	ORDER BY rolecount DESC, rcl.contextlevel ASC",
	[],
	0,
	8
);

$toproles = array_slice($roles, 0, 8);

admindash_render_workspace_header(
	'Admin Tools / Manage Users',
	'Define Roles',
	'Governance view for role design, assignment volume, and privileged-access review before you step into Moodle capability management.',
	'users',
	'admintools.users.roles',
	$tabs,
	[
		['label' => 'Open role manager', 'url' => new moodle_url('/admin/roles/manage.php'), 'primary' => true],
		['label' => 'Manage users', 'url' => new moodle_url('/local/admindashboard/manage_users.php'), 'primary' => false],
		['label' => 'System config', 'url' => new moodle_url('/local/admindashboard/system_config.php'), 'primary' => false],
	],
	[
		'Governance ready',
		'Assignment aware',
		'Privilege focused',
	]
);
?>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Roles</div>
		<div class="admindash-module-stat__value"><?php echo $totalroles; ?></div>
		<div class="admindash-module-stat__meta">Roles visible in this governance view excluding the guest archetype.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Custom Roles</div>
		<div class="admindash-module-stat__value"><?php echo $customroles; ?></div>
		<div class="admindash-module-stat__meta">Roles without a built-in archetype and most likely to need documentation.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Privileged Roles</div>
		<div class="admindash-module-stat__value"><?php echo $privilegedroles; ?></div>
		<div class="admindash-module-stat__meta">Manager, course-creator, or teaching-style roles that deserve periodic review.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Assignments</div>
		<div class="admindash-module-stat__value"><?php echo $totalassignments; ?></div>
		<div class="admindash-module-stat__meta">Total visible role assignments across all returned roles.</div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Context Coverage</h5>
			<span class="admindash-admin-note">Where roles can be assigned</span>
		</div>
		<?php if (!empty($contextsummary)): ?>
			<ul class="admindash-admin-list">
				<?php foreach ($contextsummary as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($contextmap[(int)$row->contextlevel] ?? ('Context ' . (int)$row->contextlevel)); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->rolecount; ?> roles</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0">No role context level mappings were returned.</p>
		<?php endif; ?>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Role Inventory</h5>
			<p class="admindash-admin-note mb-0">Prioritized by assignment volume so operationally important roles surface first.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Role</th>
					<th>Archetype</th>
					<th>Assignments</th>
					<th>Contexts</th>
					<th>Signals</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($toproles) && empty($roles)): ?>
					<tr>
						<td colspan="6" class="text-center py-4">No roles were returned.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($roles as $role): ?>
						<?php
						$archetype = trim((string)$role->archetype) !== '' ? trim((string)$role->archetype) : 'custom';
						$signals = [];
						if ($archetype === 'custom') {
							$signals[] = '<span class="admindash-admin-badge is-info">Custom</span>';
						}
						if (in_array($archetype, ['manager', 'coursecreator', 'teacher'], true)
								|| in_array((string)$role->shortname, ['manager', 'coursecreator', 'editingteacher'], true)) {
							$signals[] = '<span class="admindash-admin-badge is-danger">Privileged</span>';
						}
						if ((int)$role->assignmentcount === 0) {
							$signals[] = '<span class="admindash-admin-badge is-warn">Unused</span>';
						}
						?>
						<tr>
							<td>
								<div class="admindash-admin-user">
									<div class="admindash-admin-user__name"><?php echo s(trim((string)($role->name ?: $role->shortname))); ?></div>
									<div class="admindash-admin-note"><?php echo s((string)$role->shortname); ?></div>
								</div>
							</td>
							<td><?php echo s($archetype); ?></td>
							<td><?php echo (int)$role->assignmentcount; ?></td>
							<td><?php echo (int)$role->contextcount; ?> levels</td>
							<td><div class="admindash-admin-badges"><?php echo !empty($signals) ? implode('', $signals) : '<span class="admindash-admin-badge is-success">Standard</span>'; ?></div></td>
							<td>
								<div class="admindash-admin-actions-inline">
									<a href="<?php echo new moodle_url('/admin/roles/define.php', ['action' => 'view', 'roleid' => (int)$role->id]); ?>">View</a>
									<a href="<?php echo new moodle_url('/admin/roles/manage.php'); ?>">Manage</a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
admindash_render_footer();