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

local_admindashboard_setup_page('/local/admindashboard/account_settings.php', 'Settings', 'support.settings');
local_admindashboard_render_header('support.settings');

$tabs = local_admindashboard_get_support_account_suite_tabs();
$pluginmanager = \core_plugin_manager::instance();

$currentuser = $DB->get_record('user', ['id' => $USER->id],
	'id, username, firstname, lastname, email, department, city, country, lang, timezone, mailformat, emailstop, auth, lastaccess, confirmed, suspended',
	MUST_EXIST
);

$supportname = trim((string)get_config('moodle', 'supportname'));
$supportemail = trim((string)get_config('moodle', 'supportemail'));
$noreplyaddress = trim((string)get_config('moodle', 'noreplyaddress'));

$enabledmessageplugins = $pluginmanager->get_enabled_plugins('message');
$messagechannelcount = is_array($enabledmessageplugins) ? count($enabledmessageplugins) : 0;

$profilefields = [
	trim((string)$currentuser->email),
	trim((string)$currentuser->department),
	trim((string)$currentuser->city),
	trim((string)$currentuser->country),
];
$profilefilled = count(array_filter($profilefields, static function(string $value): bool {
	return $value !== '';
}));
$profilereadiness = (int)round(($profilefilled / max(count($profilefields), 1)) * 100);

$displaylang = trim((string)$currentuser->lang) !== '' ? trim((string)$currentuser->lang) : trim((string)($CFG->lang ?? current_language()));
$displaytimezone = trim((string)$currentuser->timezone) !== '' ? trim((string)$currentuser->timezone) : (trim((string)($CFG->timezone ?? '')) !== '' ? trim((string)$CFG->timezone) : 'server default');
$mailformatlabel = ((int)$currentuser->mailformat === 1) ? 'HTML email' : 'Plain text email';
$emaildeliverylabel = !empty($currentuser->emailstop) ? 'Email notifications paused' : 'Email notifications enabled';
$securitylabel = (!empty($currentuser->confirmed) && empty($currentuser->suspended) && (string)$currentuser->auth !== 'nologin') ? 'Account active' : 'Account needs review';

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

$settingsbadges = [];
$settingsbadges[] = $statusbadge($profilereadiness >= 75 ? 'Profile mostly complete' : 'Profile needs details', $profilereadiness >= 75 ? 'is-success' : 'is-warn');
$settingsbadges[] = $statusbadge(empty($currentuser->emailstop) ? 'Notifications active' : 'Notifications paused', empty($currentuser->emailstop) ? 'is-success' : 'is-warn');
$settingsbadges[] = $statusbadge($messagechannelcount > 0 ? 'Messaging channels live' : 'Messaging channels limited', $messagechannelcount > 0 ? 'is-info' : 'is-warn');
$settingsbadges[] = $statusbadge($supportemail !== '' ? 'Support route configured' : 'Support route missing', $supportemail !== '' ? 'is-success' : 'is-warn');

$domains = [
	[
		'area' => 'Profile details',
		'status' => $statusbadge($profilereadiness >= 75 ? 'Healthy' : 'Needs update', $profilereadiness >= 75 ? 'is-success' : 'is-warn'),
		'snapshot' => $profilereadiness . '% readiness · ' . ($currentuser->department !== '' ? trim((string)$currentuser->department) : 'No department') . ' · ' . ($currentuser->email !== '' ? trim((string)$currentuser->email) : 'No email'),
		'detail' => 'Profile completeness affects support routing, analytics, and how quickly other admins can act on your account context.',
		'url' => new moodle_url('/user/edit.php', ['id' => (int)$currentuser->id]),
		'action' => 'Edit profile',
	],
	[
		'area' => 'Notification preferences',
		'status' => $statusbadge(empty($currentuser->emailstop) ? 'Receiving email' : 'Paused', empty($currentuser->emailstop) ? 'is-success' : 'is-warn'),
		'snapshot' => $emaildeliverylabel . ' · ' . $mailformatlabel,
		'detail' => 'Email and notification posture controls whether operational reminders, support follow-ups, and alerts will reach you reliably.',
		'url' => new moodle_url('/message/notificationpreferences.php'),
		'action' => 'Open notifications',
	],
	[
		'area' => 'Messaging workspace',
		'status' => $statusbadge($messagechannelcount > 0 ? 'Available' : 'Limited', $messagechannelcount > 0 ? 'is-info' : 'is-warn'),
		'snapshot' => $messagechannelcount . ' enabled message processor plugins',
		'detail' => 'Direct messaging and processor availability determine how fast you can move from support diagnosis to communication.',
		'url' => new moodle_url('/message/index.php'),
		'action' => 'Open messages',
	],
	[
		'area' => 'Language and locale',
		'status' => $statusbadge(($displaylang !== '' && $displaytimezone !== '') ? 'Defined' : 'Using defaults', ($displaylang !== '' && $displaytimezone !== '') ? 'is-success' : 'is-warn'),
		'snapshot' => $displaylang . ' · ' . $displaytimezone,
		'detail' => 'Language and timezone choices shape date interpretation, notification timing, and interface consistency.',
		'url' => new moodle_url('/user/language.php'),
		'action' => 'Review locale',
	],
	[
		'area' => 'Password and access',
		'status' => $statusbadge($securitylabel, (!empty($currentuser->confirmed) && empty($currentuser->suspended) && (string)$currentuser->auth !== 'nologin') ? 'is-success' : 'is-danger'),
		'snapshot' => 'Auth: ' . trim((string)$currentuser->auth) . ' · ' . (!empty($currentuser->confirmed) ? 'confirmed' : 'unconfirmed') . ' · ' . (empty($currentuser->suspended) ? 'not suspended' : 'suspended'),
		'detail' => 'This is the identity and access layer for your personal admin account, including password maintenance and sign-in method awareness.',
		'url' => new moodle_url('/login/change_password.php'),
		'action' => 'Change password',
	],
	[
		'area' => 'Support and escalation',
		'status' => $statusbadge($supportemail !== '' ? 'Configured' : 'Needs setup', $supportemail !== '' ? 'is-success' : 'is-warn'),
		'snapshot' => ($supportname !== '' ? $supportname : 'No support name') . ' / ' . ($supportemail !== '' ? $supportemail : 'No support email'),
		'detail' => 'When personal settings do not resolve the issue, this is the next route for support escalation and platform contact.',
		'url' => new moodle_url('/user/contactsitesupport.php'),
		'action' => 'Contact support',
	],
];

local_admindashboard_render_workspace_header(
	'Support & Account',
	'Settings',
	'Personal admin settings workspace for profile completeness, notification posture, locale, password hygiene, and support escalation paths.',
	'settings',
	'support.settings',
	$tabs,
	[
		['label' => 'User preferences', 'url' => new moodle_url('/user/preferences.php'), 'primary' => true],
		['label' => 'Notification preferences', 'url' => new moodle_url('/message/notificationpreferences.php'), 'primary' => false],
		['label' => 'Help Center', 'url' => new moodle_url('/local/admindashboard/help_center.php'), 'primary' => false],
	],
	[
		'Personal control room',
		empty($currentuser->emailstop) ? 'Notifications enabled' : 'Notifications paused',
		$supportemail !== '' ? 'Support route configured' : 'Support route missing',
	]
);
?>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_account_settings_profile_readiness', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $profilereadiness; ?>%</div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_account_settings_based_on_email_department_city_and_country_fields_for_your_account', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_account_settings_notification_posture', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo empty($currentuser->emailstop) ? 'Active' : 'Paused'; ?></div>
		<div class="admindash-module-stat__meta"><?php echo s($mailformatlabel); ?> <?php echo get_string('ui_account_settings_with', 'local_admindashboard'); ?> <?php echo s($emaildeliverylabel); ?>.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_account_settings_locale', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo s($displaylang); ?></div>
		<div class="admindash-module-stat__meta"><?php echo s($displaytimezone); ?> <?php echo get_string('ui_account_settings_is_currently_driving_date_and_time_rendering', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_account_settings_messaging_channels', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $messagechannelcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_account_settings_enabled_message_processor_plugins_available_to_support_notifica_79fadace', 'local_admindashboard'); ?></div>
	</div>
</div>

	<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_account_settings_current_account_state', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_account_settings_live_snapshot', 'local_admindashboard'); ?></span>
		</div>
		<div class="admindash-admin-badges mb-3"><?php echo implode('', $settingsbadges); ?></div>
		<ul class="admindash-admin-list">
			<li>
				<span class="admindash-admin-list__label"><?php echo get_string('ui_account_settings_account', 'local_admindashboard'); ?></span>
				<span class="admindash-admin-list__value"><?php echo s(trim(fullname($currentuser))); ?> / <?php echo s((string)$currentuser->username); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label"><?php echo get_string('ui_account_settings_department', 'local_admindashboard'); ?></span>
				<span class="admindash-admin-list__value"><?php echo s(trim((string)$currentuser->department) !== '' ? (string)$currentuser->department : 'Unassigned'); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label"><?php echo get_string('ui_account_settings_email', 'local_admindashboard'); ?></span>
				<span class="admindash-admin-list__value"><?php echo s(trim((string)$currentuser->email) !== '' ? (string)$currentuser->email : 'No email configured'); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label"><?php echo get_string('ui_account_settings_last_access', 'local_admindashboard'); ?></span>
				<span class="admindash-admin-list__value"><?php echo (int)$currentuser->lastaccess > 0 ? s(userdate((int)$currentuser->lastaccess)) : 'No recorded access'; ?></span>
			</li>
		</ul>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1"><?php echo get_string('ui_account_settings_settings_domains', 'local_admindashboard'); ?></h5>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_account_settings_each_row_shows_a_settings_area_its_live_status_and_the_best_rou_d486d9ba', 'local_admindashboard'); ?></p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th><?php echo get_string('ui_account_settings_domain', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_account_settings_status', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_account_settings_current_snapshot', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_account_settings_why_it_matters', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_account_settings_action', 'local_admindashboard'); ?></th>
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
<?php
local_admindashboard_render_footer();