<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

admindash_setup_page('/local/admindashboard/add_user.php', 'Add User', 'admintools.users.add');
admindash_render_header('admintools.users.add');

$meta = admindash_get_meta();
$tabs = admindash_get_manage_users_suite_tabs();
$systemcontextid = context_system::instance()->id;

$roles = $DB->get_records_sql(
	"SELECT r.id, r.name, r.shortname,
			COUNT(DISTINCT ra.userid) AS assignmentcount
	   FROM {role} r
	LEFT JOIN {role_assignments} ra ON ra.roleid = r.id
	  WHERE (r.archetype IS NULL OR (r.archetype <> :guestarch AND r.archetype <> :userarch))
	GROUP BY r.id, r.name, r.shortname, r.sortorder
	ORDER BY assignmentcount DESC, r.sortorder ASC, r.name ASC",
	[
		'guestarch' => 'guest',
		'userarch' => 'user',
	],
	0,
	8
);

$requiredfields = $DB->get_records_sql(
	"SELECT f.id, f.name, f.shortname, f.datatype
	   FROM {user_info_field} f
	  WHERE f.required = 1
	ORDER BY f.sortorder ASC, f.name ASC",
	[],
	0,
	10
);

$departmentrows = $DB->get_records_sql(
	"SELECT u.department AS departmentlabel, COUNT(1) AS usercount
	   FROM {user} u
	  WHERE u.deleted = 0
		AND u.confirmed = 1
		AND u.suspended = 0
		AND u.username <> 'guest'
		AND u.department <> ''
	GROUP BY u.department
	ORDER BY usercount DESC, u.department ASC",
	[],
	0,
	6
);

$cohortrows = $DB->get_records_sql(
	"SELECT c.id, c.name, COUNT(cm.userid) AS membercount
	   FROM {cohort} c
	LEFT JOIN {cohort_members} cm ON cm.cohortid = c.id
	GROUP BY c.id, c.name
	ORDER BY membercount DESC, c.name ASC",
	[],
	0,
	6
);

$totroles = (int)$DB->count_records_select('role', "archetype IS NULL OR (archetype <> :guestarch AND archetype <> :userarch)", [
	'guestarch' => 'guest',
	'userarch' => 'user',
]);
$totcohorts = (int)$DB->count_records('cohort');
$requiredcount = (int)$DB->count_records('user_info_field', ['required' => 1]);
$deptcount = count($meta['departments']);

admindash_render_workspace_header(
	'Admin Tools / Manage Users',
	'Add User',
	'Guided onboarding workspace for preparing defaults, checking taxonomy readiness, and then handing off into Moodle core account creation.',
	'users',
	'admintools.users.add',
	$tabs,
	[
		['label' => 'Open core add-user form', 'url' => new moodle_url('/user/editadvanced.php', ['id' => -1]), 'primary' => true],
		['label' => 'Manage users', 'url' => new moodle_url('/local/admindashboard/manage_users.php'), 'primary' => false],
		['label' => 'Group setup', 'url' => new moodle_url('/local/admindashboard/group_department_setup.php'), 'primary' => false],
	],
	[
		'Onboarding ready',
		'Department aware',
		'Role guided',
	]
);
?>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Departments Ready</div>
		<div class="admindash-module-stat__value"><?php echo $deptcount; ?></div>
		<div class="admindash-module-stat__meta">Available department values for immediate user mapping.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Roles Available</div>
		<div class="admindash-module-stat__value"><?php echo $totroles; ?></div>
		<div class="admindash-module-stat__meta">Assignable operational roles currently configured in the LMS.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Required Profile Fields</div>
		<div class="admindash-module-stat__value"><?php echo $requiredcount; ?></div>
		<div class="admindash-module-stat__meta">Custom fields that should be satisfied during onboarding.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Cohorts Available</div>
		<div class="admindash-module-stat__value"><?php echo $totcohorts; ?></div>
		<div class="admindash-module-stat__meta">System cohorts available for immediate audience placement.</div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Required Profile Fields</h5>
			<span class="admindash-admin-note"><?php echo $requiredcount; ?> required</span>
		</div>
		<?php if (!empty($requiredfields)): ?>
			<div class="admindash-admin-list">
				<?php foreach ($requiredfields as $field): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($field->name); ?></span>
						<span class="admindash-admin-list__value"><?php echo s($field->shortname); ?> · <?php echo s($field->datatype); ?></span>
					</li>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<p class="admindash-admin-note mb-0">No required custom profile fields are configured right now.</p>
		<?php endif; ?>
	</div>

	<div class="admindash-admin-stack">
		<div class="admindash-card admindash-admin-panel">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0">Role Defaults to Review</h5>
				<span class="admindash-admin-note">Top assignments</span>
			</div>
			<?php if (!empty($roles)): ?>
				<ul class="admindash-admin-list">
					<?php foreach ($roles as $role): ?>
						<li>
							<span class="admindash-admin-list__label"><?php echo s(trim((string)($role->name ?: $role->shortname))); ?></span>
							<span class="admindash-admin-list__value"><?php echo (int)$role->assignmentcount; ?> assignments</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else: ?>
				<p class="admindash-admin-note mb-0">No assignable roles were returned.</p>
			<?php endif; ?>
		</div>

		<div class="admindash-card admindash-admin-panel mt-3">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0">Placement Readiness</h5>
				<span class="admindash-admin-note">Top destinations</span>
			</div>
			<div class="admindash-admin-split-grid">
				<div>
					<div class="admindash-callout-panel__eyebrow">Departments</div>
					<ul class="admindash-admin-list is-tight">
						<?php foreach ($departmentrows as $departmentrow): ?>
							<li>
								<span class="admindash-admin-list__label"><?php echo s($departmentrow->departmentlabel); ?></span>
								<span class="admindash-admin-list__value"><?php echo (int)$departmentrow->usercount; ?> users</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div>
					<div class="admindash-callout-panel__eyebrow">Cohorts</div>
					<ul class="admindash-admin-list is-tight">
						<?php foreach ($cohortrows as $cohortrow): ?>
							<li>
								<span class="admindash-admin-list__label"><?php echo s($cohortrow->name); ?></span>
								<span class="admindash-admin-list__value"><?php echo (int)$cohortrow->membercount; ?> members</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
admindash_render_footer();