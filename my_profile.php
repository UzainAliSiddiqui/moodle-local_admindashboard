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

local_admindashboard_setup_page('/local/admindashboard/my_profile.php', 'My Profile', 'support.profile');
local_admindashboard_render_header('support.profile');

$tabs = local_admindashboard_get_support_account_suite_tabs();
$pluginmanager = \core_plugin_manager::instance();

$currentuser = $DB->get_record('user', ['id' => $USER->id],
	'id, username, firstname, lastname, email, department, city, country, lang, timezone, lastaccess, auth, confirmed, suspended, picture, imagealt, description, mailformat, emailstop',
	MUST_EXIST
);

$supportname = trim((string)get_config('moodle', 'supportname'));
$supportemail = trim((string)get_config('moodle', 'supportemail'));
$displaylang = trim((string)$currentuser->lang) !== '' ? trim((string)$currentuser->lang) : trim((string)($CFG->lang ?? current_language()));
$displaytimezone = trim((string)$currentuser->timezone) !== '' ? trim((string)$currentuser->timezone) : (trim((string)($CFG->timezone ?? '')) !== '' ? trim((string)$CFG->timezone) : 'server default');

$profilefields = [
	trim((string)$currentuser->email),
	trim((string)$currentuser->department),
	trim((string)$currentuser->city),
	trim((string)$currentuser->country),
	trim((string)$currentuser->lang),
];
$profilefilled = count(array_filter($profilefields, static function(string $value): bool {
	return $value !== '';
}));
$profilereadiness = (int)round(($profilefilled / max(count($profilefields), 1)) * 100);

$rolerows = $DB->get_records_sql(
	"SELECT DISTINCT r.id, r.name, r.shortname, r.sortorder
	   FROM {role_assignments} ra
	   JOIN {role} r ON r.id = ra.roleid
	  WHERE ra.userid = :userid
		AND (r.archetype IS NULL OR (r.archetype <> :guestarch AND r.archetype <> :userarch))
   ORDER BY r.sortorder ASC, r.name ASC",
	[
		'userid' => (int)$currentuser->id,
		'guestarch' => 'guest',
		'userarch' => 'user',
	]
);

$roles = [];
foreach ($rolerows as $role) {
	$label = trim((string)($role->name ?: $role->shortname));
	if ($label !== '') {
		$roles[] = $label;
	}
}

$enrolledcourses = (int)$DB->count_records_sql(
	"SELECT COUNT(DISTINCT e.courseid)
	   FROM {user_enrolments} ue
	   JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
	   JOIN {course} c ON c.id = e.courseid AND c.id > 1
	  WHERE ue.userid = :userid
		AND ue.status = 0",
	['userid' => (int)$currentuser->id]
);

$courserolecount = (int)$DB->count_records_sql(
	"SELECT COUNT(DISTINCT ctx.instanceid)
	   FROM {role_assignments} ra
	   JOIN {context} ctx ON ctx.id = ra.contextid
	  WHERE ra.userid = :userid
		AND ctx.contextlevel = :coursecontext",
	[
		'userid' => (int)$currentuser->id,
		'coursecontext' => CONTEXT_COURSE,
	]
);

$enabledmessageplugins = $pluginmanager->get_enabled_plugins('message');
$messagechannelcount = is_array($enabledmessageplugins) ? count($enabledmessageplugins) : 0;

$profiletext = trim(strip_tags((string)($currentuser->description ?? '')));
if ($profiletext !== '' && core_text::strlen($profiletext) > 180) {
	$profiletext = core_text::substr($profiletext, 0, 180) . '...';
}

$mailposture = !empty($currentuser->emailstop) ? 'Email notifications paused' : (((int)$currentuser->mailformat === 1) ? 'HTML notifications enabled' : 'Plain text notifications enabled');

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

$profilebadges = [];
$profilebadges[] = $statusbadge($profilereadiness >= 80 ? 'Profile complete' : 'Profile incomplete', $profilereadiness >= 80 ? 'is-success' : 'is-warn');
$profilebadges[] = $statusbadge(!empty($roles) ? 'Role mapped' : 'Role unclear', !empty($roles) ? 'is-success' : 'is-warn');
$profilebadges[] = $statusbadge($courserolecount > 0 ? 'Course footprint active' : 'Course footprint limited', $courserolecount > 0 ? 'is-info' : 'is-warn');
$profilebadges[] = $statusbadge(empty($currentuser->suspended) && !empty($currentuser->confirmed) ? 'Account active' : 'Account needs review', (empty($currentuser->suspended) && !empty($currentuser->confirmed)) ? 'is-success' : 'is-danger');

$domains = [
	[
		'area' => 'Identity and contact',
		'status' => $statusbadge($profilereadiness >= 80 ? 'Strong' : 'Needs attention', $profilereadiness >= 80 ? 'is-success' : 'is-warn'),
		'snapshot' => ($currentuser->email !== '' ? trim((string)$currentuser->email) : 'No email') . ' · ' . ($currentuser->department !== '' ? trim((string)$currentuser->department) : 'No department'),
		'detail' => 'These are the fields other admins rely on when routing work, contacting you, or placing you in department views.',
		'url' => new moodle_url('/user/edit.php', ['id' => (int)$currentuser->id]),
		'action' => 'Edit identity',
	],
	[
		'area' => 'Role footprint',
		'status' => $statusbadge(!empty($roles) ? 'Mapped' : 'Minimal', !empty($roles) ? 'is-success' : 'is-warn'),
		'snapshot' => !empty($roles) ? implode(', ', array_slice($roles, 0, 3)) . (count($roles) > 3 ? ' +' . (count($roles) - 3) : '') : 'No non-default roles detected',
		'detail' => 'Your assigned roles explain why certain screens, actions, and dashboards are available to you.',
		'url' => new moodle_url('/user/profile.php', ['id' => (int)$currentuser->id]),
		'action' => 'Open profile',
	],
	[
		'area' => 'Course footprint',
		'status' => $statusbadge($enrolledcourses > 0 || $courserolecount > 0 ? 'Active' : 'Light', $enrolledcourses > 0 || $courserolecount > 0 ? 'is-info' : 'is-warn'),
		'snapshot' => $enrolledcourses . ' enrolled courses · ' . $courserolecount . ' course-role contexts',
		'detail' => 'This shows how broadly your identity is operating across live course spaces, which matters for support and governance.',
		'url' => new moodle_url('/my/courses.php'),
		'action' => 'Open courses',
	],
	[
		'area' => 'Notification posture',
		'status' => $statusbadge(empty($currentuser->emailstop) ? 'Active' : 'Paused', empty($currentuser->emailstop) ? 'is-success' : 'is-warn'),
		'snapshot' => $mailposture . ' · ' . $messagechannelcount . ' message channels available',
		'detail' => 'This determines whether system notices, support replies, and direct messages will reach you through the expected channels.',
		'url' => new moodle_url('/message/notificationpreferences.php'),
		'action' => 'Review notifications',
	],
	[
		'area' => 'Locale and working context',
		'status' => $statusbadge(($displaylang !== '' && $displaytimezone !== '') ? 'Defined' : 'Defaulted', ($displaylang !== '' && $displaytimezone !== '') ? 'is-success' : 'is-warn'),
		'snapshot' => $displaylang . ' · ' . $displaytimezone,
		'detail' => 'Language and timezone shape how dates, reminders, and workflow timing appear throughout the LMS.',
		'url' => new moodle_url('/user/language.php'),
		'action' => 'Review locale',
	],
	[
		'area' => 'Support route',
		'status' => $statusbadge($supportemail !== '' ? 'Ready' : 'Missing', $supportemail !== '' ? 'is-success' : 'is-warn'),
		'snapshot' => ($supportname !== '' ? $supportname : 'No support name') . ' / ' . ($supportemail !== '' ? $supportemail : 'No support email'),
		'detail' => 'This is the escalation path when the issue is broader than your own profile or settings changes can resolve.',
		'url' => new moodle_url('/local/admindashboard/help_center.php'),
		'action' => 'Open help center',
	],
];

local_admindashboard_render_workspace_header(
	'Support & Account',
	'My Profile',
	'Personal identity workspace for account ownership, role footprint, course reach, and the fastest self-service routes around your admin presence.',
	'profile',
	'support.profile',
	$tabs,
	[
		['label' => 'Edit profile', 'url' => new moodle_url('/user/edit.php', ['id' => (int)$currentuser->id]), 'primary' => true],
		['label' => 'Settings module', 'url' => new moodle_url('/local/admindashboard/account_settings.php'), 'primary' => false],
		['label' => 'Core profile', 'url' => new moodle_url('/user/profile.php', ['id' => (int)$currentuser->id]), 'primary' => false],
	],
	[
		'Identity control room',
		!empty($roles) ? 'Role mapped' : 'Role minimal',
		$enrolledcourses > 0 ? 'Course footprint active' : 'Course footprint light',
	]
);
?>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_my_profile_profile_readiness', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $profilereadiness; ?>%</div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_my_profile_based_on_key_identity_and_routing_fields_for_this_account', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_my_profile_assigned_roles', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo count($roles); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_my_profile_non_default_roles_currently_shaping_your_access_footprint', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_my_profile_course_reach', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $enrolledcourses; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $courserolecount; ?> <?php echo get_string('ui_my_profile_distinct_course_role_contexts_currently_tied_to_this_user', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_my_profile_notification_channels', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $messagechannelcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_my_profile_enabled_message_processor_plugins_available_to_deliver_notices__ea2d7c86', 'local_admindashboard'); ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_my_profile_identity_snapshot', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_my_profile_live_account_view', 'local_admindashboard'); ?></span>
		</div>
		<div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
			<div><?php echo $OUTPUT->user_picture($currentuser, ['size' => 120, 'link' => false]); ?></div>
			<div style="flex:1 1 280px;min-width:240px">
				<div class="admindash-admin-user__name" style="font-size:1.2rem;margin-bottom:6px"><?php echo s(trim(fullname($currentuser))); ?></div>
				<div class="admindash-admin-note" style="margin-bottom:10px"><?php echo s((string)$currentuser->username); ?><?php echo trim((string)$currentuser->email) !== '' ? ' · ' . s((string)$currentuser->email) : ''; ?></div>
				<div class="admindash-admin-badges mb-3"><?php echo implode('', $profilebadges); ?></div>
				<ul class="admindash-admin-list">
					<li>
						<span class="admindash-admin-list__label"><?php echo get_string('ui_my_profile_department', 'local_admindashboard'); ?></span>
						<span class="admindash-admin-list__value"><?php echo s(trim((string)$currentuser->department) !== '' ? (string)$currentuser->department : 'Unassigned'); ?></span>
					</li>
					<li>
						<span class="admindash-admin-list__label"><?php echo get_string('ui_my_profile_city_country', 'local_admindashboard'); ?></span>
						<span class="admindash-admin-list__value"><?php echo s(trim((string)$currentuser->city) !== '' ? (string)$currentuser->city : 'No city'); ?> / <?php echo s(trim((string)$currentuser->country) !== '' ? (string)$currentuser->country : 'No country'); ?></span>
					</li>
					<li>
						<span class="admindash-admin-list__label"><?php echo get_string('ui_my_profile_last_access', 'local_admindashboard'); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$currentuser->lastaccess > 0 ? s(userdate((int)$currentuser->lastaccess)) : 'No recorded access'; ?></span>
					</li>
				</ul>
			</div>
		</div>
	</div>

</div>

<?php if ($profiletext !== ''): ?>
	<div class="admindash-card admindash-admin-panel mt-3">
		<h5 class="mb-2"><?php echo get_string('ui_my_profile_profile_note', 'local_admindashboard'); ?></h5>
		<p class="admindash-admin-note mb-0"><?php echo s($profiletext); ?></p>
	</div>
<?php endif; ?>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1"><?php echo get_string('ui_my_profile_profile_domains', 'local_admindashboard'); ?></h5>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_my_profile_each_domain_explains_one_part_of_your_account_footprint_and_poi_d76c22ce', 'local_admindashboard'); ?></p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th><?php echo get_string('ui_my_profile_domain', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_my_profile_status', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_my_profile_current_snapshot', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_my_profile_why_it_matters', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_my_profile_action', 'local_admindashboard'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($domains as $domain): ?>
					<tr>
						<td><?php echo s($domain['area']); ?></td>
						<td><?php echo $domain['status']; ?></td>
						<td><?php echo s($domain['snapshot']); ?></td>
						<td><?php echo s($domain['detail']); ?></td>
						<td>
							<div class="admindash-admin-actions-inline">
								<a href="<?php echo $domain['url']; ?>"><?php echo s($domain['action']); ?></a>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_my_profile_role_footprint', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_my_profile_access_context', 'local_admindashboard'); ?></span>
		</div>
		<?php if (!empty($roles)): ?>
			<div class="admindash-admin-badges">
				<?php foreach ($roles as $role): ?>
					<span class="admindash-admin-badge is-info"><?php echo s($role); ?></span>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_my_profile_no_non_default_role_assignments_were_detected_for_this_user', 'local_admindashboard'); ?></p>
		<?php endif; ?>
	</div>

</div>

<?php
local_admindashboard_render_footer();
