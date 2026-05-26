<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/xmldb/xmldb_table.php');

admindash_setup_page('/local/admindashboard/integrations.php', 'Integrations', 'platform.integrations');
admindash_render_header('platform.integrations');

$tabs = admindash_get_platform_settings_suite_tabs();
$pluginmanager = \core_plugin_manager::instance();
$dbmanager = $DB->get_manager();

$normalizeplugins = static function(?array $plugins): array {
	if (empty($plugins)) {
		return [];
	}

	$normalized = [];
	foreach ($plugins as $key => $value) {
		if (is_string($key) && !is_int($key)) {
			$normalized[] = trim($key);
		} else if (is_string($value)) {
			$normalized[] = trim($value);
		}
	}

	$normalized = array_values(array_filter(array_unique($normalized), static function(string $value): bool {
		return $value !== '';
	}));
	sort($normalized);
	return $normalized;
};

$enabledauth = $normalizeplugins($pluginmanager->get_enabled_plugins('auth'));
$enabledenrol = $normalizeplugins($pluginmanager->get_enabled_plugins('enrol'));
$enabledrepository = $normalizeplugins($pluginmanager->get_enabled_plugins('repository'));
$enabledmessage = $normalizeplugins($pluginmanager->get_enabled_plugins('message'));

$presentauth = $pluginmanager->get_present_plugins('auth');
$presentenrol = $pluginmanager->get_present_plugins('enrol');
$presentrepository = $pluginmanager->get_present_plugins('repository');
$presentmessage = $pluginmanager->get_present_plugins('message');

$authcount = count($enabledauth);
$enrolcount = count($enabledenrol);
$repositorycount = count($enabledrepository);
$messagecount = count($enabledmessage);

$authpresentcount = is_array($presentauth) ? count($presentauth) : 0;
$enrolpresentcount = is_array($presentenrol) ? count($presentenrol) : 0;
$repositorypresentcount = is_array($presentrepository) ? count($presentrepository) : 0;
$messagepresentcount = is_array($presentmessage) ? count($presentmessage) : 0;

$enablewebservices = !empty(get_config('moodle', 'enablewebservices'));
$enablemobilewebservice = !empty(get_config('moodle', 'enablemobilewebservice'));

$externalservicecount = 0;
$enabledexternalservicecount = 0;
$restrictedexternalservicecount = 0;
$serviceuserlinkcount = 0;
$uploadservicecount = 0;
$downloadservicecount = 0;
$externalservices = [];

if ($dbmanager->table_exists(new xmldb_table('external_services'))) {
	$externalservicecount = (int)$DB->count_records('external_services');
	$enabledexternalservicecount = (int)$DB->count_records('external_services', ['enabled' => 1]);
	$restrictedexternalservicecount = (int)$DB->count_records('external_services', ['enabled' => 1, 'restrictedusers' => 1]);
	$uploadservicecount = (int)$DB->count_records('external_services', ['enabled' => 1, 'uploadfiles' => 1]);
	$downloadservicecount = (int)$DB->count_records('external_services', ['enabled' => 1, 'downloadfiles' => 1]);
	$externalservices = $DB->get_records('external_services', null, 'enabled DESC, name ASC',
		'id, name, enabled, restrictedusers, uploadfiles, downloadfiles, component', 0, 8);
}

if ($dbmanager->table_exists(new xmldb_table('external_services_users'))) {
	$serviceuserlinkcount = (int)$DB->count_records('external_services_users');
}

$oauthissuercount = 0;
if ($dbmanager->table_exists(new xmldb_table('oauth2_issuer'))) {
	$oauthissuercount = (int)$DB->count_records('oauth2_issuer');
}

$repositoryinstancecount = 0;
if ($dbmanager->table_exists(new xmldb_table('repository_instances'))) {
	$repositoryinstancecount = (int)$DB->count_records('repository_instances');
}

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

$pluginpreview = static function(array $plugins): string {
	if (empty($plugins)) {
		return 'none';
	}

	$preview = array_slice($plugins, 0, 5);
	$label = implode(', ', $preview);
	if (count($plugins) > count($preview)) {
		$label .= ' +' . (count($plugins) - count($preview));
	}
	return $label;
};

$integrationbadges = [];
$integrationbadges[] = $statusbadge($authcount > 0 ? 'Identity providers active' : 'No auth providers detected', $authcount > 0 ? 'is-success' : 'is-danger');
$integrationbadges[] = $statusbadge($enablewebservices ? 'API surface enabled' : 'API surface disabled', $enablewebservices ? 'is-info' : 'is-success');
$integrationbadges[] = $statusbadge($oauthissuercount > 0 ? 'OAuth issuers configured' : 'OAuth issuers missing', $oauthissuercount > 0 ? 'is-info' : 'is-warn');
$integrationbadges[] = $statusbadge($repositoryinstancecount > 0 ? 'Repository instances live' : 'No repository instances', $repositoryinstancecount > 0 ? 'is-success' : 'is-warn');

$registryrows = [
	[
		'surface' => 'Authentication & SSO',
		'status' => $statusbadge($authcount > 0 ? 'Connected' : 'Missing', $authcount > 0 ? 'is-success' : 'is-danger'),
		'active' => $authcount . ' enabled / ' . $authpresentcount . ' available',
		'owner' => 'Identity lane',
		'detail' => 'Enabled auth plugins: ' . $pluginpreview($enabledauth) . '.',
		'risk' => $authcount > 0 ? 'Multiple providers should be reviewed for ownership and sign-in policy overlap.' : 'Users depend on a missing or misread auth surface.',
		'url' => new moodle_url('/admin/auth.php'),
		'action' => 'Open auth',
	],
	[
		'surface' => 'Enrolment & roster flows',
		'status' => $statusbadge($enrolcount > 0 ? 'Active' : 'Missing', $enrolcount > 0 ? 'is-success' : 'is-danger'),
		'active' => $enrolcount . ' enabled / ' . $enrolpresentcount . ' available',
		'owner' => 'Learning ops lane',
		'detail' => 'Enabled enrol plugins: ' . $pluginpreview($enabledenrol) . '.',
		'risk' => 'Changes here affect how learners enter courses and how downstream analytics stay in sync.',
		'url' => new moodle_url('/admin/enrol.php'),
		'action' => 'Open enrol',
	],
	[
		'surface' => 'Web services & API consumers',
		'status' => $statusbadge($enablewebservices ? ($enabledexternalservicecount > 0 ? 'Live' : 'Enabled without services') : 'Closed', $enablewebservices ? ($enabledexternalservicecount > 0 ? 'is-info' : 'is-warn') : 'is-success'),
		'active' => $enabledexternalservicecount . ' enabled services / ' . $serviceuserlinkcount . ' user links',
		'owner' => 'API & reporting lane',
		'detail' => 'Uploads enabled on ' . $uploadservicecount . ' services, downloads enabled on ' . $downloadservicecount . ' services.',
		'risk' => $enablewebservices ? 'Review token governance, restricted-user mappings, and exposed file capabilities.' : 'API surface is currently minimized, which reduces integration reach but also removes external automation.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'web service']),
		'action' => 'Review APIs',
	],
	[
		'surface' => 'OAuth2 issuers',
		'status' => $statusbadge($oauthissuercount > 0 ? 'Configured' : 'Not configured', $oauthissuercount > 0 ? 'is-info' : 'is-warn'),
		'active' => $oauthissuercount . ' issuer records',
		'owner' => 'Identity lane',
		'detail' => 'Used for delegated sign-in and provider trust relationships when enabled.',
		'risk' => 'Issuer drift or stale client credentials can break external sign-in and sync flows.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'oauth2']),
		'action' => 'Review OAuth2',
	],
	[
		'surface' => 'Repositories & content sources',
		'status' => $statusbadge(($repositorycount > 0 || $repositoryinstancecount > 0) ? 'Connected' : 'Minimal', ($repositorycount > 0 || $repositoryinstancecount > 0) ? 'is-success' : 'is-warn'),
		'active' => $repositorycount . ' enabled plugins / ' . $repositoryinstancecount . ' instances',
		'owner' => 'Content ops lane',
		'detail' => 'Enabled repository plugins: ' . $pluginpreview($enabledrepository) . '.',
		'risk' => 'Repository availability affects file ingestion, shared content access, and author workflows.',
		'url' => new moodle_url('/admin/repository.php'),
		'action' => 'Open repositories',
	],
	[
		'surface' => 'Messaging outputs',
		'status' => $statusbadge($messagecount > 0 ? 'Available' : 'Limited', $messagecount > 0 ? 'is-success' : 'is-warn'),
		'active' => $messagecount . ' enabled / ' . $messagepresentcount . ' available',
		'owner' => 'Communications lane',
		'detail' => 'Enabled message processors: ' . $pluginpreview($enabledmessage) . '.',
		'risk' => 'Delivery gaps here affect reminders, support escalation, and compliance outreach.',
		'url' => new moodle_url('/admin/message.php'),
		'action' => 'Open messaging',
	],
	[
		'surface' => 'Mobile app channel',
		'status' => $statusbadge($enablemobilewebservice ? 'Available' : 'Disabled', $enablemobilewebservice ? 'is-success' : 'is-warn'),
		'active' => $enablemobilewebservice ? 'Moodle app services available' : 'Mobile web service disabled',
		'owner' => 'Platform lane',
		'detail' => 'This controls whether the official mobile app channel is available on top of web services.',
		'risk' => 'If the mobile channel is required, disablement becomes a learner-experience issue rather than just a technical setting.',
		'url' => new moodle_url('/admin/tool/mobile/launch.php'),
		'action' => 'Open mobile',
	],
];

$quickdestinations = [
	['label' => 'Plugins overview', 'meta' => 'See installed plugins, upgrades, and platform-wide dependency signals.', 'url' => new moodle_url('/admin/plugins.php')],
	['label' => 'Authentication', 'meta' => 'Manage SSO and username/password providers.', 'url' => new moodle_url('/admin/auth.php')],
	['label' => 'Enrolments', 'meta' => 'Review enrolment connectors and access provisioning paths.', 'url' => new moodle_url('/admin/enrol.php')],
	['label' => 'Repositories', 'meta' => 'Check file-source connectors and shared content entry points.', 'url' => new moodle_url('/admin/repository.php')],
	['label' => 'Messaging', 'meta' => 'Inspect output channels used for learner and staff delivery.', 'url' => new moodle_url('/admin/message.php')],
	['label' => 'Web service search', 'meta' => 'Jump to token, service, and API-related configuration.', 'url' => new moodle_url('/admin/search.php', ['query' => 'web service'])],
];

admindash_render_workspace_header(
	'Platform Settings',
	'Integrations',
	'Operational registry for identity, messaging, repositories, mobile services, and external API touchpoints around the LMS.',
	'integration',
	'platform.integrations',
	$tabs,
	[
		['label' => 'Plugins overview', 'url' => new moodle_url('/admin/plugins.php'), 'primary' => true],
		['label' => 'System config', 'url' => new moodle_url('/local/admindashboard/system_config.php'), 'primary' => false],
		['label' => 'Export center', 'url' => new moodle_url('/local/admindashboard/export_center.php'), 'primary' => false],
	],
	[
		$enablewebservices ? 'Web services on' : 'Web services off',
		$oauthissuercount > 0 ? 'OAuth configured' : 'OAuth pending',
		$repositoryinstancecount > 0 ? 'Repository sources live' : 'Repository sources limited',
	]
);
?>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Identity Surfaces</div>
		<div class="admindash-module-stat__value"><?php echo $authcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $authpresentcount; ?> auth plugins are present on disk and <?php echo $authcount; ?> are enabled for sign-in.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Service Endpoints</div>
		<div class="admindash-module-stat__value"><?php echo $enabledexternalservicecount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $externalservicecount; ?> total web services with <?php echo $restrictedexternalservicecount; ?> restricted-user services.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Repository Instances</div>
		<div class="admindash-module-stat__value"><?php echo $repositoryinstancecount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $repositorycount; ?> repository plugins are enabled to back file-source connections.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Message Channels</div>
		<div class="admindash-module-stat__value"><?php echo $messagecount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $messagepresentcount; ?> message processor plugins are available in this Moodle build.</div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Integration Posture</h5>
			<span class="admindash-admin-note">Current state</span>
		</div>
		<div class="admindash-admin-badges mb-3"><?php echo implode('', $integrationbadges); ?></div>
		<ul class="admindash-admin-list">
			<li>
				<span class="admindash-admin-list__label">Enabled auth providers</span>
				<span class="admindash-admin-list__value"><?php echo s($pluginpreview($enabledauth)); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">Enabled enrol connectors</span>
				<span class="admindash-admin-list__value"><?php echo s($pluginpreview($enabledenrol)); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">API exposure</span>
				<span class="admindash-admin-list__value"><?php echo $enablewebservices ? 'Web services enabled' : 'Web services disabled'; ?><?php echo $enablemobilewebservice ? ' / mobile channel enabled' : ''; ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">Service user mappings</span>
				<span class="admindash-admin-list__value"><?php echo $serviceuserlinkcount; ?> explicit external service user links</span>
			</li>
		</ul>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Integration Registry</h5>
			<p class="admindash-admin-note mb-0">Each row summarizes a major integration surface, its current signal, and the most relevant admin destination.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Surface</th>
					<th>Status</th>
					<th>Active Footprint</th>
					<th>Owner Lane</th>
					<th>Current Signal</th>
					<th>Risk Note</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($registryrows as $row): ?>
					<tr>
						<td><?php echo s($row['surface']); ?></td>
						<td><?php echo $row['status']; ?></td>
						<td><?php echo s($row['active']); ?></td>
						<td><?php echo s($row['owner']); ?></td>
						<td><?php echo s($row['detail']); ?></td>
						<td><?php echo s($row['risk']); ?></td>
						<td>
							<div class="admindash-admin-actions-inline">
								<a href="<?php echo $row['url']; ?>"><?php echo s($row['action']); ?></a>
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
			<h5 class="mb-0">External Service Feed</h5>
			<span class="admindash-admin-note">Top configured services</span>
		</div>
		<?php if (!empty($externalservices)): ?>
			<ul class="admindash-admin-list">
				<?php foreach ($externalservices as $service): ?>
					<?php
					$parts = [];
					$parts[] = !empty($service->enabled) ? 'enabled' : 'disabled';
					$parts[] = !empty($service->restrictedusers) ? 'restricted users' : 'open access model';
					if (!empty($service->uploadfiles)) {
						$parts[] = 'uploads';
					}
					if (!empty($service->downloadfiles)) {
						$parts[] = 'downloads';
					}
					if (!empty($service->component)) {
						$parts[] = trim((string)$service->component);
					}
					?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s((string)$service->name !== '' ? (string)$service->name : 'Unnamed service #' . (int)$service->id); ?></span>
						<span class="admindash-admin-list__value"><?php echo s(implode(' · ', $parts)); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0">No external service records were found in this environment.</p>
		<?php endif; ?>
	</div>

</div>

<?php
admindash_render_footer();