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
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/xmldb/xmldb_table.php');

local_admindashboard_setup_page('/local/admindashboard/system_config.php', 'System Config', 'platform.config');
local_admindashboard_render_header('platform.config');

$tabs = local_admindashboard_get_platform_settings_suite_tabs();
$dbmanager = $DB->get_manager();

$coreconfigcount = (int)$DB->count_records('config');
$pluginoverridecount = (int)$DB->count_records('config_plugins');
$enabledauths = function_exists('get_enabled_auth_plugins') ? get_enabled_auth_plugins() : [];
$enabledenrols = function_exists('enrol_get_plugins') ? array_keys(enrol_get_plugins(true)) : [];
$authcount = count($enabledauths);
$enrolcount = count($enabledenrols);

$totalscheduledtasks = 0;
$disabledscheduledtasks = 0;
$customisedscheduledtasks = 0;
$pluginmanagercount = 0;
if ($dbmanager->table_exists(new xmldb_table('task_scheduled'))) {
	$totalscheduledtasks = (int)$DB->count_records('task_scheduled');
	$disabledscheduledtasks = (int)$DB->count_records('task_scheduled', ['disabled' => 1]);
	if ($dbmanager->field_exists(new xmldb_table('task_scheduled'), 'customised')) {
		$customisedscheduledtasks = (int)$DB->count_records('task_scheduled', ['customised' => 1]);
	}
}

try {
	$pluginmanager = \core_plugin_manager::instance();
	$pluginmanagercount = count($pluginmanager->get_plugins());
} catch (Throwable $e) {
	$pluginmanagercount = 0;
}

$externalservicescount = 0;
$enabledexternalservicescount = 0;
if ($dbmanager->table_exists(new xmldb_table('external_services'))) {
	$externalservicescount = (int)$DB->count_records('external_services');
	$enabledexternalservicescount = (int)$DB->count_records('external_services', ['enabled' => 1]);
}

$oauthissuercount = 0;
if ($dbmanager->table_exists(new xmldb_table('oauth2_issuer'))) {
	$oauthissuercount = (int)$DB->count_records('oauth2_issuer');
}

$supportemail = trim((string)get_config('moodle', 'supportemail'));
$noreplyaddress = trim((string)get_config('moodle', 'noreplyaddress'));
$themename = trim((string)get_config('moodle', 'theme'));
$defaultlang = trim((string)get_config('moodle', 'lang'));
$timezone = trim((string)get_config('moodle', 'timezone'));
$registerauth = trim((string)get_config('moodle', 'registerauth'));
$loginviaemail = !empty(get_config('moodle', 'loginviaemail'));
$forcelogin = !empty(get_config('moodle', 'forcelogin'));
$cronclionly = !empty(get_config('moodle', 'cronclionly'));
$enablewebservices = !empty(get_config('moodle', 'enablewebservices'));
$enablemobilewebservice = !empty(get_config('moodle', 'enablemobilewebservice'));
$debugvalue = (int)get_config('moodle', 'debug');
$debugdisplay = !empty(get_config('moodle', 'debugdisplay'));
$allowthemechangeonurl = !empty(get_config('moodle', 'allowthemechangeonurl'));
$securewwwroot = stripos($CFG->wwwroot, 'https://') === 0;

$sitetitle = trim((string)$SITE->fullname);
$siteshortname = trim((string)$SITE->shortname);
$themevalue = $themename !== '' ? $themename : 'theme resolved by current stack';
$langvalue = $defaultlang !== '' ? $defaultlang : current_language();
$timezonevalue = $timezone !== '' ? $timezone : 'server default';

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

$yesno = static function(bool $value, string $yes = 'Enabled', string $no = 'Disabled'): string {
	return $value ? $yes : $no;
};

$posturebadges = [];
$posturebadges[] = $statusbadge($securewwwroot ? 'HTTPS site URL' : 'HTTP site URL', $securewwwroot ? 'is-success' : 'is-danger');
$posturebadges[] = $statusbadge($debugvalue > 0 ? 'Debugging enabled' : 'Debugging restricted', $debugvalue > 0 ? 'is-warn' : 'is-success');
$posturebadges[] = $statusbadge($enablewebservices ? 'Web services on' : 'Web services off', $enablewebservices ? 'is-info' : 'is-success');
$posturebadges[] = $statusbadge($disabledscheduledtasks > 0 ? 'Disabled scheduled tasks present' : 'Scheduled tasks active', $disabledscheduledtasks > 0 ? 'is-warn' : 'is-success');

$domains = [
	[
		'area' => 'Site identity',
		'signal' => $statusbadge(($supportemail !== '' && $securewwwroot) ? 'Operationally ready' : 'Needs review', ($supportemail !== '' && $securewwwroot) ? 'is-success' : 'is-warn'),
		'details' => 'Site: ' . ($sitetitle !== '' ? $sitetitle : 'Unnamed site') . ' / ' . ($siteshortname !== '' ? $siteshortname : 'No shortname') . ' . Support email: ' . ($supportemail !== '' ? $supportemail : 'missing') . '.',
		'impact' => 'These values affect user-facing communications, trust signals, and escalation paths.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'supportemail']),
		'action' => 'Review identity',
	],
	[
		'area' => 'Security and access',
		'signal' => $statusbadge(($debugvalue === 0 && !$debugdisplay && $forcelogin) ? 'Controlled' : 'Open settings to review', ($debugvalue === 0 && !$debugdisplay && $forcelogin) ? 'is-success' : 'is-warn'),
		'details' => 'Force login: ' . $yesno($forcelogin, 'Yes', 'No') . ' . Login via email: ' . $yesno($loginviaemail, 'Yes', 'No') . ' . Self registration: ' . ($registerauth !== '' ? $registerauth : 'disabled') . '.',
		'impact' => 'Authentication posture shapes who can enter the site and how identity is verified.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'login']),
		'action' => 'Review access',
	],
	[
		'area' => 'Automation and cron',
		'signal' => $statusbadge(($disabledscheduledtasks === 0) ? 'Healthy task grid' : 'Task exceptions found', ($disabledscheduledtasks === 0) ? 'is-success' : 'is-danger'),
		'details' => $totalscheduledtasks . ' scheduled tasks, ' . $customisedscheduledtasks . ' customised, ' . $disabledscheduledtasks . ' disabled. Cron CLI only: ' . $yesno($cronclionly, 'Yes', 'No') . '.',
		'impact' => 'Broken or disabled jobs quickly affect messaging, reports, clean-up, and integrations.',
		'url' => new moodle_url('/admin/tool/task/scheduledtasks.php'),
		'action' => 'Open tasks',
	],
	[
		'area' => 'API and integrations',
		'signal' => $statusbadge($enablewebservices ? 'API surface active' : 'API surface minimal', $enablewebservices ? 'is-info' : 'is-success'),
		'details' => 'Web services: ' . $yesno($enablewebservices, 'Enabled', 'Disabled') . ' . Mobile service: ' . $yesno($enablemobilewebservice, 'Enabled', 'Disabled') . ' . External services: ' . $enabledexternalservicescount . '/' . $externalservicescount . ' enabled. OAuth issuers: ' . $oauthissuercount . '.',
		'impact' => 'This area governs system-to-system access, mobile connectivity, and integration blast radius.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'web service']),
		'action' => 'Review APIs',
	],
	[
		'area' => 'Theme and localisation',
		'signal' => $statusbadge($allowthemechangeonurl ? 'Theme override exposed' : 'Theme locked down', $allowthemechangeonurl ? 'is-warn' : 'is-success'),
		'details' => 'Theme: ' . $themevalue . ' . Default language: ' . $langvalue . ' . Timezone: ' . $timezonevalue . '.',
		'impact' => 'These settings influence user experience consistency and support overhead.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'theme']),
		'action' => 'Review UX',
	],
];

$quickdestinations = [
	['label' => 'Site administration search', 'meta' => 'Fastest route to core settings by keyword.', 'url' => new moodle_url('/admin/search.php')],
	['label' => 'Plugins overview', 'meta' => 'Review installed and upgrade-sensitive plugins.', 'url' => new moodle_url('/admin/plugins.php')],
	['label' => 'Scheduled tasks', 'meta' => 'Check disabled jobs, custom timings, and failures.', 'url' => new moodle_url('/admin/tool/task/scheduledtasks.php')],
	['label' => 'Security search', 'meta' => 'Jump straight into access and login settings.', 'url' => new moodle_url('/admin/search.php', ['query' => 'security'])],
	['label' => 'Web service search', 'meta' => 'Inspect API exposure and token-related settings.', 'url' => new moodle_url('/admin/search.php', ['query' => 'web service'])],
	['label' => 'Language and theme search', 'meta' => 'Review branding, locale, and interface defaults.', 'url' => new moodle_url('/admin/search.php', ['query' => 'theme'])],
];

$pluginmix = [
	['label' => 'Enabled auth plugins', 'value' => $authcount . ' active', 'detail' => !empty($enabledauths) ? implode(', ', $enabledauths) : 'none'],
	['label' => 'Enabled enrol plugins', 'value' => $enrolcount . ' active', 'detail' => !empty($enabledenrols) ? implode(', ', $enabledenrols) : 'none'],
	['label' => 'Installed plugin types', 'value' => $pluginmanagercount . ' plugin types', 'detail' => $pluginmanagercount > 0 ? 'Directly reported by Moodle plugin manager' : 'Plugin manager details unavailable'],
	['label' => 'Plugin-scoped overrides', 'value' => $pluginoverridecount . ' keys', 'detail' => 'Configuration keys stored in config_plugins'],
];

local_admindashboard_render_workspace_header(
	'Platform Settings',
	'System Config',
	'Operations-first control room for high-value Moodle settings, policy-sensitive switches, and quick access to the admin areas that matter most.',
	'config',
	'platform.config',
	$tabs,
	[
		['label' => 'Admin search', 'url' => new moodle_url('/admin/search.php'), 'primary' => true],
		['label' => 'Scheduled tasks', 'url' => new moodle_url('/admin/tool/task/scheduledtasks.php'), 'primary' => false],
		['label' => 'Plugins overview', 'url' => new moodle_url('/admin/plugins.php'), 'primary' => false],
	],
	[
		$securewwwroot ? 'HTTPS root' : 'HTTP root',
		$enablewebservices ? 'Web services enabled' : 'Web services disabled',
		$disabledscheduledtasks > 0 ? 'Task exceptions present' : 'Task grid active',
	]
);
?>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Core Config Keys</div>
		<div class="admindash-module-stat__value"><?php echo $coreconfigcount; ?></div>
		<div class="admindash-module-stat__meta">Rows in Moodle's core config table, representing site-level stored settings.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Plugin Overrides</div>
		<div class="admindash-module-stat__value"><?php echo $pluginoverridecount; ?></div>
		<div class="admindash-module-stat__meta">Plugin-specific configuration keys currently stored in config_plugins.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Access Channels</div>
		<div class="admindash-module-stat__value"><?php echo $authcount + $enrolcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $authcount; ?> auth plugins and <?php echo $enrolcount; ?> enrol plugins are enabled right now.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Scheduled Tasks</div>
		<div class="admindash-module-stat__value"><?php echo $totalscheduledtasks; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $customisedscheduledtasks; ?> customised and <?php echo $disabledscheduledtasks; ?> disabled tasks currently detected.</div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Current Posture</h5>
			<span class="admindash-admin-note">Operational snapshot</span>
		</div>
		<div class="admindash-admin-badges mb-3"><?php echo implode('', $posturebadges); ?></div>
		<ul class="admindash-admin-list">
			<li>
				<span class="admindash-admin-list__label">Site URL</span>
				<span class="admindash-admin-list__value"><?php echo s($CFG->wwwroot); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">Theme / language</span>
				<span class="admindash-admin-list__value"><?php echo s($themevalue); ?> / <?php echo s($langvalue); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">Support contacts</span>
				<span class="admindash-admin-list__value"><?php echo s($supportemail !== '' ? $supportemail : 'Missing support email'); ?><?php echo $noreplyaddress !== '' ? ' / ' . s($noreplyaddress) : ''; ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">API surface</span>
				<span class="admindash-admin-list__value"><?php echo $enablewebservices ? 'Web services enabled' : 'Web services disabled'; ?><?php echo $enablemobilewebservice ? ' / mobile enabled' : ''; ?></span>
			</li>
		</ul>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Configuration Domains</h5>
			<p class="admindash-admin-note mb-0">Each row points to a controlled admin area and explains why it matters operationally.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Domain</th>
					<th>Signal</th>
					<th>Current Snapshot</th>
					<th>Why It Matters</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($domains as $domain): ?>
					<tr>
						<td><?php echo s($domain['area']); ?></td>
						<td><?php echo $domain['signal']; ?></td>
						<td><?php echo s($domain['details']); ?></td>
						<td><?php echo s($domain['impact']); ?></td>
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
			<h5 class="mb-0">Plugin Mix</h5>
			<span class="admindash-admin-note">Runtime footprint</span>
		</div>
		<ul class="admindash-admin-list">
			<?php foreach ($pluginmix as $row): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo s($row['label']); ?></span>
					<span class="admindash-admin-list__value"><?php echo s($row['value']); ?> · <?php echo s($row['detail']); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>

<?php
local_admindashboard_render_footer();
