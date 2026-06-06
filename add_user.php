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

local_admindashboard_setup_page('/local/admindashboard/add_user.php', 'Add User', 'admintools.users.add');
local_admindashboard_render_header('admintools.users.add');

$meta = local_admindashboard_get_meta();
$tabs = local_admindashboard_get_manage_users_suite_tabs();
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

local_admindashboard_render_workspace_header(
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
		<div class="admindash-module-stat__label"><?php echo get_string('ui_add_user_departments_ready', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $deptcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_add_user_available_department_values_for_immediate_user_mapping', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_add_user_roles_available', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $totroles; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_add_user_assignable_operational_roles_currently_configured_in_the_lms', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_add_user_required_profile_fields', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $requiredcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_add_user_custom_fields_that_should_be_satisfied_during_onboarding', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_add_user_cohorts_available', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $totcohorts; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_add_user_system_cohorts_available_for_immediate_audience_placement', 'local_admindashboard'); ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_add_user_required_profile_fields', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo $requiredcount; ?> <?php echo get_string('ui_add_user_required', 'local_admindashboard'); ?></span>
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
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_add_user_no_required_custom_profile_fields_are_configured_right_now', 'local_admindashboard'); ?></p>
		<?php endif; ?>
	</div>

	<div class="admindash-admin-stack">
		<div class="admindash-card admindash-admin-panel">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0"><?php echo get_string('ui_add_user_role_defaults_to_review', 'local_admindashboard'); ?></h5>
				<span class="admindash-admin-note"><?php echo get_string('ui_add_user_top_assignments', 'local_admindashboard'); ?></span>
			</div>
			<?php if (!empty($roles)): ?>
				<ul class="admindash-admin-list">
					<?php foreach ($roles as $role): ?>
						<li>
							<span class="admindash-admin-list__label"><?php echo s(trim((string)($role->name ?: $role->shortname))); ?></span>
							<span class="admindash-admin-list__value"><?php echo (int)$role->assignmentcount; ?> <?php echo get_string('ui_add_user_assignments', 'local_admindashboard'); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else: ?>
				<p class="admindash-admin-note mb-0"><?php echo get_string('ui_add_user_no_assignable_roles_were_returned', 'local_admindashboard'); ?></p>
			<?php endif; ?>
		</div>

		<div class="admindash-card admindash-admin-panel mt-3">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0"><?php echo get_string('ui_add_user_placement_readiness', 'local_admindashboard'); ?></h5>
				<span class="admindash-admin-note"><?php echo get_string('ui_add_user_top_destinations', 'local_admindashboard'); ?></span>
			</div>
			<div class="admindash-admin-split-grid">
				<div>
					<div class="admindash-callout-panel__eyebrow"><?php echo get_string('ui_add_user_departments', 'local_admindashboard'); ?></div>
					<ul class="admindash-admin-list is-tight">
						<?php foreach ($departmentrows as $departmentrow): ?>
							<li>
								<span class="admindash-admin-list__label"><?php echo s($departmentrow->departmentlabel); ?></span>
								<span class="admindash-admin-list__value"><?php echo (int)$departmentrow->usercount; ?> <?php echo get_string('ui_add_user_users', 'local_admindashboard'); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div>
					<div class="admindash-callout-panel__eyebrow"><?php echo get_string('ui_add_user_cohorts', 'local_admindashboard'); ?></div>
					<ul class="admindash-admin-list is-tight">
						<?php foreach ($cohortrows as $cohortrow): ?>
							<li>
								<span class="admindash-admin-list__label"><?php echo s($cohortrow->name); ?></span>
								<span class="admindash-admin-list__value"><?php echo (int)$cohortrow->membercount; ?> <?php echo get_string('ui_add_user_members', 'local_admindashboard'); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
local_admindashboard_render_footer();