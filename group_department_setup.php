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

local_admindashboard_setup_page('/local/admindashboard/group_department_setup.php', 'Group & Department Setup', 'admintools.groups');
local_admindashboard_render_header('admintools.groups');

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

<h2 class="mb-3"><?php echo get_string('ui_group_department_setup_group_department_setup', 'local_admindashboard'); ?></h2>

<form method="get" class="admindash-filters admindash-card">
	<div class="title"><?php echo get_string('ui_group_department_setup_directory_search', 'local_admindashboard'); ?></div>
	<label class="mb-0" for="groupSearch"><?php echo get_string('ui_group_department_setup_find_group_department_or_profile_field', 'local_admindashboard'); ?></label>
	<input id="groupSearch" name="q" class="form-control" style="max-width:420px" value="<?php echo s($q); ?>" placeholder="Search department names, cohorts, or profile fields" />
	<button type="submit" class="btn btn-primary"><?php echo get_string('ui_group_department_setup_apply', 'local_admindashboard'); ?></button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/group_department_setup.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_group_department_setup_departments', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->totaldepartments ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_group_department_setup_unique_department_values_currently_used_on_real_user_accounts', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_group_department_setup_unassigned_users', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->unassignedusers ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_group_department_setup_accounts_missing_a_department_tag_and_likely_affecting_reporting', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_group_department_setup_cohorts', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $totalcohorts; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_group_department_setup_system_cohorts_available_for_enrolment_and_audience_grouping', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_group_department_setup_profile_fields', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $profilefieldcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo (int)($summary->active30users ?? 0); ?> <?php echo get_string('ui_group_department_setup_active_users_in_the_last_30_days_across_the_current_directory', 'local_admindashboard'); ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<h5 class="mb-3"><?php echo get_string('ui_group_department_setup_configuration_actions', 'local_admindashboard'); ?></h5>
		<div class="admindash-module-actions">
			<a class="btn btn-primary" href="<?php echo new moodle_url('/user/profile/index.php'); ?>">Profile fields</a>
			<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/cohort/index.php', ['contextid' => $systemcontextid]); ?>">Manage cohorts</a>
			<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/manage_users.php'); ?>">Manage users</a>
			<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/department_reports.php'); ?>">Department reports</a>
		</div>
		<p class="admindash-admin-note mb-0"><?php echo get_string('ui_group_department_setup_this_page_gives_you_the_reporting_layer_around_taxonomy_setup_w_a18ed082', 'local_admindashboard'); ?></p>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<h5 class="mb-3"><?php echo get_string('ui_group_department_setup_department_signals', 'local_admindashboard'); ?></h5>
		<?php if (!empty($departmentrows)): ?>
			<ul class="admindash-admin-list">
				<?php foreach (array_slice(array_values($departmentrows), 0, 8) as $departmentrow): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($departmentrow->departmentlabel); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$departmentrow->enabledusers; ?> <?php echo get_string('ui_group_department_setup_enabled', 'local_admindashboard'); ?> <?php echo (int)$departmentrow->active30users; ?> <?php echo get_string('ui_group_department_setup_active_30d', 'local_admindashboard'); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_group_department_setup_no_departments_matched_the_current_search', 'local_admindashboard'); ?></p>
		<?php endif; ?>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_group_department_setup_department_directory', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo count($departmentrows); ?> <?php echo get_string('ui_group_department_setup_rows', 'local_admindashboard'); ?></span>
		</div>
		<div class="admindash-tablewrap">
			<table class="table table-striped table-hover admindash-admin-table">
				<thead>
					<tr>
						<th><?php echo get_string('ui_group_department_setup_department', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_group_department_setup_total_users', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_group_department_setup_enabled', 'local_admindashboard'); ?></th>
						<th><?php echo get_string('ui_group_department_setup_active_in_30d', 'local_admindashboard'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($departmentrows)): ?>
						<tr>
							<td colspan="4" class="text-center py-4"><?php echo get_string('ui_group_department_setup_no_department_records_found', 'local_admindashboard'); ?></td>
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
				<h5 class="mb-0"><?php echo get_string('ui_group_department_setup_cohorts', 'local_admindashboard'); ?></h5>
				<span class="admindash-admin-note"><?php echo $totalcohorts; ?> <?php echo get_string('ui_group_department_setup_total', 'local_admindashboard'); ?></span>
			</div>
			<div class="admindash-tablewrap">
				<table class="table table-striped table-hover admindash-admin-table">
					<thead>
						<tr>
							<th><?php echo get_string('ui_group_department_setup_name', 'local_admindashboard'); ?></th>
							<th><?php echo get_string('ui_group_department_setup_id_number', 'local_admindashboard'); ?></th>
							<th><?php echo get_string('ui_group_department_setup_members', 'local_admindashboard'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($cohortrows)): ?>
							<tr>
								<td colspan="3" class="text-center py-4"><?php echo get_string('ui_group_department_setup_no_cohorts_matched_the_current_search', 'local_admindashboard'); ?></td>
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
				<h5 class="mb-0"><?php echo get_string('ui_group_department_setup_custom_profile_fields', 'local_admindashboard'); ?></h5>
				<span class="admindash-admin-note"><?php echo $profilefieldcount; ?> <?php echo get_string('ui_group_department_setup_configured', 'local_admindashboard'); ?></span>
			</div>
			<div class="admindash-tablewrap">
				<table class="table table-striped table-hover admindash-admin-table">
					<thead>
						<tr>
							<th><?php echo get_string('ui_group_department_setup_name', 'local_admindashboard'); ?></th>
							<th><?php echo get_string('ui_group_department_setup_shortname', 'local_admindashboard'); ?></th>
							<th><?php echo get_string('ui_group_department_setup_type', 'local_admindashboard'); ?></th>
							<th><?php echo get_string('ui_group_department_setup_required', 'local_admindashboard'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($profilefields)): ?>
							<tr>
								<td colspan="4" class="text-center py-4"><?php echo get_string('ui_group_department_setup_no_custom_profile_fields_are_configured', 'local_admindashboard'); ?></td>
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
local_admindashboard_render_footer();