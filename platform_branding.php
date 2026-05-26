<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

admindash_setup_page('/local/admindashboard/platform_branding.php', 'Platform Branding', 'platform.branding');
admindash_render_header('platform.branding');

$tabs = admindash_get_platform_settings_suite_tabs();

$sitename = trim((string)$SITE->fullname);
$siteshortname = trim((string)$SITE->shortname);
$sitesummary = trim(strip_tags((string)($SITE->summary ?? '')));

$themename = trim((string)get_config('moodle', 'theme'));
$themevalue = $themename !== '' ? $themename : 'theme resolved by current stack';
$defaultlang = trim((string)get_config('moodle', 'lang'));
$langvalue = $defaultlang !== '' ? $defaultlang : current_language();
$supportname = trim((string)get_config('moodle', 'supportname'));
$supportemail = trim((string)get_config('moodle', 'supportemail'));
$mobilecssurl = trim((string)($CFG->mobilecssurl ?? ''));
$custommenuitems = trim((string)($CFG->custommenuitems ?? ''));
$customusermenuitems = trim((string)($CFG->customusermenuitems ?? ''));

$custommenucount = $custommenuitems === '' ? 0 : count(preg_split('/\r\n|\r|\n/', $custommenuitems));
$customusermenucount = $customusermenuitems === '' ? 0 : count(preg_split('/\r\n|\r|\n/', $customusermenuitems));

$logourl = $OUTPUT->get_logo_url(null, 320);
if (empty($logourl)) {
	$logourl = $OUTPUT->get_compact_logo_url(320, 320);
}
$compactlogourl = $OUTPUT->get_compact_logo_url(160, 160);

$haslogo = !empty($logourl);
$hascompactlogo = !empty($compactlogourl);
$hasmobilecss = $mobilecssurl !== '';
$hascustommenu = $custommenucount > 0;
$hascustomusermenu = $customusermenucount > 0;

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

$brandingbadges = [];
$brandingbadges[] = $statusbadge($haslogo ? 'Logo configured' : 'Logo fallback only', $haslogo ? 'is-success' : 'is-warn');
$brandingbadges[] = $statusbadge($hascompactlogo ? 'Compact mark available' : 'No compact mark', $hascompactlogo ? 'is-success' : 'is-warn');
$brandingbadges[] = $statusbadge($hasmobilecss ? 'Mobile CSS set' : 'Mobile CSS not set', $hasmobilecss ? 'is-info' : 'is-warn');
$brandingbadges[] = $statusbadge($hascustommenu ? 'Navigation customized' : 'Default navigation', $hascustommenu ? 'is-info' : 'is-success');

$branddomains = [
	[
		'surface' => 'Site identity',
		'status' => $statusbadge(($sitename !== '' && $siteshortname !== '') ? 'Ready' : 'Needs review', ($sitename !== '' && $siteshortname !== '') ? 'is-success' : 'is-warn'),
		'snapshot' => ($sitename !== '' ? $sitename : 'Unnamed site') . ' / ' . ($siteshortname !== '' ? $siteshortname : 'No shortname'),
		'detail' => 'These values appear across headers, titles, notifications, and touchpoints where users decide whether the platform feels official.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'fullname']),
		'action' => 'Review identity',
	],
	[
		'surface' => 'Theme & visual baseline',
		'status' => $statusbadge($themevalue !== '' ? 'Active theme' : 'Unclear', $themevalue !== '' ? 'is-success' : 'is-warn'),
		'snapshot' => 'Theme: ' . $themevalue . ' / Language: ' . $langvalue,
		'detail' => 'Theme choice controls layout tone, core colors, login presentation, and whether branding assets are surfaced cleanly.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'theme']),
		'action' => 'Review theme',
	],
	[
		'surface' => 'Logo assets',
		'status' => $statusbadge($haslogo ? 'Primary logo live' : 'Using fallback mark', $haslogo ? 'is-success' : 'is-warn'),
		'snapshot' => $haslogo ? 'Primary logo can be rendered by the active theme.' : 'No primary logo exposed by the active theme renderer.',
		'detail' => 'A missing explicit logo usually means the site depends on theme defaults, which weakens brand control across device sizes.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'logo']),
		'action' => 'Review logos',
	],
	[
		'surface' => 'Navigation language',
		'status' => $statusbadge(($hascustommenu || $hascustomusermenu) ? 'Customized' : 'Default', ($hascustommenu || $hascustomusermenu) ? 'is-info' : 'is-success'),
		'snapshot' => $custommenucount . ' custom menu lines / ' . $customusermenucount . ' custom user-menu lines',
		'detail' => 'This affects the wording and structure users see in top-level navigation and user-menu actions.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'custommenu']),
		'action' => 'Review menus',
	],
	[
		'surface' => 'Mobile branding',
		'status' => $statusbadge($hasmobilecss ? 'Styled' : 'Default mobile shell', $hasmobilecss ? 'is-info' : 'is-warn'),
		'snapshot' => $hasmobilecss ? $mobilecssurl : 'No mobile CSS URL configured',
		'detail' => 'Mobile CSS and app launch settings determine whether the brand carries consistently into the Moodle app experience.',
		'url' => new moodle_url('/admin/tool/mobile/launch.php'),
		'action' => 'Open mobile tools',
	],
	[
		'surface' => 'Support voice',
		'status' => $statusbadge(($supportname !== '' || $supportemail !== '') ? 'Defined' : 'Sparse', ($supportname !== '' || $supportemail !== '') ? 'is-success' : 'is-warn'),
		'snapshot' => ($supportname !== '' ? $supportname : 'No support name') . ' / ' . ($supportemail !== '' ? $supportemail : 'No support email'),
		'detail' => 'Support contact language is part of brand trust, especially inside notifications, help, and account recovery flows.',
		'url' => new moodle_url('/admin/search.php', ['query' => 'supportemail']),
		'action' => 'Review support',
	],
];

$quickdestinations = [
	['label' => 'Theme search', 'meta' => 'Jump into theme-level branding and visual settings.', 'url' => new moodle_url('/admin/search.php', ['query' => 'theme'])],
	['label' => 'Logo search', 'meta' => 'Find theme and auth-logo related configuration points.', 'url' => new moodle_url('/admin/search.php', ['query' => 'logo'])],
	['label' => 'Mobile branding tools', 'meta' => 'Review app launch settings and mobile style hooks.', 'url' => new moodle_url('/admin/tool/mobile/launch.php')],
	['label' => 'Custom menu search', 'meta' => 'Inspect navigation labels and user-menu copy.', 'url' => new moodle_url('/admin/search.php', ['query' => 'custommenu'])],
	['label' => 'Announcements module', 'meta' => 'Keep outbound messaging visually and tonally aligned with the brand.', 'url' => new moodle_url('/local/admindashboard/announcements.php')],
	['label' => 'Create course', 'meta' => 'Carry branding standards into new course shells and templates.', 'url' => new moodle_url('/local/admindashboard/create_course.php')],
];

admindash_render_workspace_header(
	'Platform Settings',
	'Platform Branding',
	'Brand and UX control room for site identity, logos, theme language, navigation copy, and mobile presentation across the admin experience.',
	'branding',
	'platform.branding',
	$tabs,
	[
		['label' => 'Theme search', 'url' => new moodle_url('/admin/search.php', ['query' => 'theme']), 'primary' => true],
		['label' => 'Mobile branding tools', 'url' => new moodle_url('/admin/tool/mobile/launch.php'), 'primary' => false],
		['label' => 'Announcements', 'url' => new moodle_url('/local/admindashboard/announcements.php'), 'primary' => false],
	],
	[
		$haslogo ? 'Logo live' : 'Logo fallback',
		$hasmobilecss ? 'Mobile styling configured' : 'Mobile styling pending',
		$hascustommenu ? 'Custom navigation' : 'Default navigation',
	]
);
?>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Brand Assets</div>
		<div class="admindash-module-stat__value"><?php echo ($haslogo ? 1 : 0) + ($hascompactlogo ? 1 : 0); ?></div>
		<div class="admindash-module-stat__meta">Detected primary and compact logo assets exposed by the active theme renderer.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Menu Customizations</div>
		<div class="admindash-module-stat__value"><?php echo $custommenucount + $customusermenucount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $custommenucount; ?> top-menu lines and <?php echo $customusermenucount; ?> user-menu lines are currently customized.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Language & Voice</div>
		<div class="admindash-module-stat__value"><?php echo s($langvalue); ?></div>
		<div class="admindash-module-stat__meta">Default site language currently setting the baseline tone for the UI and outbound communication.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Mobile Styling</div>
		<div class="admindash-module-stat__value"><?php echo $hasmobilecss ? 'Custom' : 'Default'; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $hasmobilecss ? 'A mobile CSS URL is configured for the Moodle app experience.' : 'No mobile CSS URL is configured, so the app relies on default styling.'; ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Brand Snapshot</h5>
			<span class="admindash-admin-note">Current state</span>
		</div>
		<div class="admindash-admin-badges mb-3"><?php echo implode('', $brandingbadges); ?></div>
		<ul class="admindash-admin-list">
			<li>
				<span class="admindash-admin-list__label">Site identity</span>
				<span class="admindash-admin-list__value"><?php echo s($sitename !== '' ? $sitename : 'Unnamed site'); ?> / <?php echo s($siteshortname !== '' ? $siteshortname : 'No shortname'); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">Theme</span>
				<span class="admindash-admin-list__value"><?php echo s($themevalue); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">Support voice</span>
				<span class="admindash-admin-list__value"><?php echo s($supportname !== '' ? $supportname : 'No support name'); ?><?php echo $supportemail !== '' ? ' / ' . s($supportemail) : ''; ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label">Site summary</span>
				<span class="admindash-admin-list__value"><?php echo s($sitesummary !== '' ? core_text::substr($sitesummary, 0, 120) . (core_text::strlen($sitesummary) > 120 ? '...' : '') : 'No summary configured'); ?></span>
			</li>
		</ul>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Brand Asset Preview</h5>
			<span class="admindash-admin-note">Theme-rendered assets</span>
		</div>
		<?php if ($haslogo || $hascompactlogo): ?>
			<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:flex-start;min-height:140px">
				<?php if ($haslogo): ?>
					<div class="admindash-card" style="padding:18px;min-width:220px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f8fafc,#e2e8f0)">
						<img src="<?php echo $logourl; ?>" alt="<?php echo s($sitename !== '' ? $sitename : 'Site logo'); ?>" style="max-width:220px;max-height:96px;height:auto;width:auto">
					</div>
				<?php endif; ?>
				<?php if ($hascompactlogo): ?>
					<div class="admindash-card" style="padding:18px;min-width:140px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#eef2ff,#dbeafe)">
						<img src="<?php echo $compactlogourl; ?>" alt="<?php echo s($siteshortname !== '' ? $siteshortname : 'Compact logo'); ?>" style="max-width:96px;max-height:96px;height:auto;width:auto">
					</div>
				<?php endif; ?>
			</div>
		<?php else: ?>
			<p class="admindash-admin-note mb-0">The active theme is not exposing a primary or compact logo through Moodle renderer APIs.</p>
		<?php endif; ?>
	</div>

</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Brand Control Matrix</h5>
			<p class="admindash-admin-note mb-0">Each row shows which branding surface exists, what signal it exposes today, and where to manage it.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Surface</th>
					<th>Status</th>
					<th>Current Snapshot</th>
					<th>Why It Matters</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($branddomains as $domain): ?>
					<tr>
						<td><?php echo s($domain['surface']); ?></td>
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
admindash_render_footer();