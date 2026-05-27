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

local_admindashboard_setup_page('/local/admindashboard/support_tickets.php', 'Support Tickets', 'support.tickets');
local_admindashboard_render_header('support.tickets');

$courseid = optional_param('courseid', 0, PARAM_INT);
$issuetype = trim(optional_param('issuetype', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));

$meta = local_admindashboard_get_meta($courseid);
$tabs = local_admindashboard_get_support_account_suite_tabs();
$supportname = trim((string)get_config('moodle', 'supportname'));
$supportemail = trim((string)get_config('moodle', 'supportemail'));
$noreplyaddress = trim((string)get_config('moodle', 'noreplyaddress'));

$courseoptions = $meta['courses'] ?? [];
$resolvedcourseid = $courseid;
if ($resolvedcourseid <= 0 && !empty($courseoptions)) {
	$resolvedcourseid = (int)($courseoptions[0]['id'] ?? 0);
}

$statusoptions = [
	'all' => 'All issues',
	'access' => 'Access blockers',
	'profile' => 'Profile gaps',
	'engagement' => 'Learning blockers',
];
if (!array_key_exists($issuetype, $statusoptions)) {
	$issuetype = 'all';
}

$summary = $DB->get_record_sql(
	"SELECT COUNT(1) AS totalusers,
			SUM(CASE WHEN u.suspended = 1 THEN 1 ELSE 0 END) AS suspendedusers,
			SUM(CASE WHEN u.confirmed = 0 THEN 1 ELSE 0 END) AS unconfirmedusers,
			SUM(CASE WHEN u.auth = :nologinauth THEN 1 ELSE 0 END) AS nologinusers,
			SUM(CASE WHEN TRIM(COALESCE(u.department, '')) = '' THEN 1 ELSE 0 END) AS missingdepartment,
			SUM(CASE WHEN TRIM(COALESCE(u.email, '')) = '' THEN 1 ELSE 0 END) AS missingemail,
			SUM(CASE WHEN COALESCE(u.lastaccess, 0) = 0 THEN 1 ELSE 0 END) AS neveraccess
	   FROM {user} u
	  WHERE u.deleted = 0
		AND u.username <> 'guest'
		AND u.username NOT LIKE :testuser
		AND u.username NOT LIKE :demouser",
	[
		'nologinauth' => 'nologin',
		'testuser' => '%test%',
		'demouser' => '%demo%',
	]
);

$atriskrows = $resolvedcourseid > 0 ? local_admindashboard_get_at_risk_participants($resolvedcourseid, '', 8) : [];
$atriskcount = is_array($atriskrows) ? count($atriskrows) : 0;

$queuecards = [
	[
		'label' => 'Access blockers',
		'value' => (int)($summary->suspendedusers ?? 0) + (int)($summary->unconfirmedusers ?? 0) + (int)($summary->nologinusers ?? 0),
		'meta' => (int)($summary->suspendedusers ?? 0) . ' suspended, ' . (int)($summary->unconfirmedusers ?? 0) . ' unconfirmed, ' . (int)($summary->nologinusers ?? 0) . ' no-login accounts.',
	],
	[
		'label' => 'Profile gaps',
		'value' => (int)($summary->missingdepartment ?? 0) + (int)($summary->missingemail ?? 0),
		'meta' => (int)($summary->missingdepartment ?? 0) . ' missing department, ' . (int)($summary->missingemail ?? 0) . ' missing email.',
	],
	[
		'label' => 'Never launched',
		'value' => (int)($summary->neveraccess ?? 0),
		'meta' => 'Accounts that still have no recorded last access and may trigger onboarding requests.',
	],
	[
		'label' => 'Learning blockers',
		'value' => $atriskcount,
		'meta' => $resolvedcourseid > 0 ? 'At-risk learners pulled from the selected course as a support queue proxy.' : 'Select a course to load the learner-risk proxy queue.',
	],
];

$userconditions = [
	'u.deleted = 0',
	"u.username <> 'guest'",
	'u.username NOT LIKE :testuser',
	'u.username NOT LIKE :demouser',
];
$userparams = [
	'testuser' => '%test%',
	'demouser' => '%demo%',
];

switch ($issuetype) {
	case 'access':
		$userconditions[] = '(u.suspended = 1 OR u.confirmed = 0 OR u.auth = :ticketnologin)';
		$userparams['ticketnologin'] = 'nologin';
		break;
	case 'profile':
		$userconditions[] = "(TRIM(COALESCE(u.department, '')) = '' OR TRIM(COALESCE(u.email, '')) = '')";
		break;
	case 'engagement':
		$userconditions[] = 'COALESCE(u.lastaccess, 0) = 0';
		break;
}

if ($q !== '') {
	$like = '%' . $q . '%';
	$userconditions[] = '(u.firstname LIKE :qfn OR u.lastname LIKE :qln OR u.email LIKE :qem OR u.username LIKE :qun)';
	$userparams['qfn'] = $like;
	$userparams['qln'] = $like;
	$userparams['qem'] = $like;
	$userparams['qun'] = $like;
}

$userwhere = implode(' AND ', $userconditions);
$userrows = $DB->get_records_sql(
	"SELECT u.id, u.firstname, u.lastname, u.email, u.username,
			COALESCE(u.department, '') AS department,
			COALESCE(u.lastaccess, 0) AS lastaccess,
			u.suspended, u.confirmed, u.auth
	   FROM {user} u
	  WHERE {$userwhere}
   ORDER BY u.suspended DESC, u.confirmed ASC, u.lastaccess ASC, u.lastname ASC, u.firstname ASC",
	$userparams,
	0,
	20
);

local_admindashboard_render_workspace_header(
	'Support & Account',
	'Support Tickets',
	'Operational support queue built from Moodle account blockers, profile data gaps, and learner-risk signals so teams can act before issues pile up.',
	'support',
	'support.tickets',
	$tabs,
	[
		['label' => 'Help Center', 'url' => new moodle_url('/local/admindashboard/help_center.php'), 'primary' => true],
		['label' => 'Direct messaging', 'url' => new moodle_url('/local/admindashboard/direct_messaging.php'), 'primary' => false],
		['label' => 'Inbound message settings', 'url' => new moodle_url('/admin/tool/messageinbound/index.php'), 'primary' => false],
	],
	[
		'Operational proxy queue',
		$supportemail !== '' ? 'Support channel configured' : 'Support channel missing',
		$resolvedcourseid > 0 ? 'Course-aware risk signals' : 'No course selected',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Filters</div>

	<label class="mb-0" for="courseSelect">Course</label>
	<select id="courseSelect" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>Auto-select course</option>
		<?php foreach ($courseoptions as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="issueTypeSelect">Issue Type</label>
	<select id="issueTypeSelect" name="issuetype" class="form-select" style="max-width:240px">
		<?php foreach ($statusoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $issuetype === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="ticketSearch">Search</label>
	<input id="ticketSearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Name, email, or username" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/support_tickets.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<?php foreach ($queuecards as $card): ?>
		<div class="admindash-card admindash-module-stat">
			<div class="admindash-module-stat__label"><?php echo s($card['label']); ?></div>
			<div class="admindash-module-stat__value"><?php echo (int)$card['value']; ?></div>
			<div class="admindash-module-stat__meta"><?php echo s($card['meta']); ?></div>
		</div>
	<?php endforeach; ?>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Attention Queue</h5>
			<p class="admindash-admin-note mb-0">This queue lists accounts likely to generate or already represent support workload in the current filter scope.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>User</th>
					<th>Department</th>
					<th>Signals</th>
					<th>Last Access</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($userrows)): ?>
					<tr>
						<td colspan="5" class="text-center py-4">No support-queue rows were returned for the current filters.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($userrows as $row): ?>
						<?php
						$signals = [];
						if ((int)$row->suspended === 1) {
							$signals[] = '<span class="admindash-admin-badge is-danger">Suspended</span>';
						}
						if ((int)$row->confirmed === 0) {
							$signals[] = '<span class="admindash-admin-badge is-warn">Unconfirmed</span>';
						}
						if ((string)$row->auth === 'nologin') {
							$signals[] = '<span class="admindash-admin-badge is-warn">No login</span>';
						}
						if (trim((string)$row->email) === '') {
							$signals[] = '<span class="admindash-admin-badge is-info">Missing email</span>';
						}
						if (trim((string)$row->department) === '') {
							$signals[] = '<span class="admindash-admin-badge is-info">Missing department</span>';
						}
						if ((int)$row->lastaccess === 0) {
							$signals[] = '<span class="admindash-admin-badge is-warn">Never accessed</span>';
						}
						?>
						<tr>
							<td>
								<div class="admindash-admin-user">
									<a href="<?php echo new moodle_url('/user/profile.php', ['id' => (int)$row->id]); ?>" class="admindash-admin-user__name"><?php echo s(trim(fullname($row))); ?></a>
									<div class="admindash-admin-note"><?php echo s((string)$row->username); ?><?php echo trim((string)$row->email) !== '' ? ' · ' . s((string)$row->email) : ''; ?></div>
								</div>
							</td>
							<td><?php echo s(trim((string)$row->department) !== '' ? (string)$row->department : 'Unassigned'); ?></td>
							<td><div class="admindash-admin-badges"><?php echo implode('', $signals); ?></div></td>
							<td><?php echo (int)$row->lastaccess > 0 ? s(userdate((int)$row->lastaccess)) : 'Never'; ?></td>
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
</div>

<?php
local_admindashboard_render_footer();