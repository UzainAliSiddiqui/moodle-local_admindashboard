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

local_admindashboard_setup_page('/local/admindashboard/platform_branding.php', 'Platform Branding', 'platform.branding');
local_admindashboard_render_header('platform.branding');

$tabs = local_admindashboard_get_platform_settings_suite_tabs();

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

local_admindashboard_render_workspace_header(
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
		<div class="admindash-module-stat__label"><?php echo get_string('ui_platform_branding_brand_assets', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo ($haslogo ? 1 : 0) + ($hascompactlogo ? 1 : 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_platform_branding_detected_primary_and_compact_logo_assets_exposed_by_the_active__e1a94a47', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_platform_branding_menu_customizations', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $custommenucount + $customusermenucount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $custommenucount; ?> <?php echo get_string('ui_platform_branding_top_menu_lines_and', 'local_admindashboard'); ?> <?php echo $customusermenucount; ?> <?php echo get_string('ui_platform_branding_user_menu_lines_are_currently_customized', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_platform_branding_language_voice', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo s($langvalue); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_platform_branding_default_site_language_currently_setting_the_baseline_tone_for_t_41b0b2ff', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_platform_branding_mobile_styling', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $hasmobilecss ? 'Custom' : 'Default'; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $hasmobilecss ? 'A mobile CSS URL is configured for the Moodle app experience.' : 'No mobile CSS URL is configured, so the app relies on default styling.'; ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_platform_branding_brand_snapshot', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_platform_branding_current_state', 'local_admindashboard'); ?></span>
		</div>
		<div class="admindash-admin-badges mb-3"><?php echo implode('', $brandingbadges); ?></div>
		<ul class="admindash-admin-list">
			<li>
				<span class="admindash-admin-list__label"><?php echo get_string('ui_platform_branding_site_identity', 'local_admindashboard'); ?></span>
				<span class="admindash-admin-list__value"><?php echo s($sitename !== '' ? $sitename : 'Unnamed site'); ?> / <?php echo s($siteshortname !== '' ? $siteshortname : 'No shortname'); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label"><?php echo get_string('ui_platform_branding_theme', 'local_admindashboard'); ?></span>
				<span class="admindash-admin-list__value"><?php echo s($themevalue); ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label"><?php echo get_string('ui_platform_branding_support_voice', 'local_admindashboard'); ?></span>
				<span class="admindash-admin-list__value"><?php echo s($supportname !== '' ? $supportname : 'No support name'); ?><?php echo $supportemail !== '' ? ' / ' . s($supportemail) : ''; ?></span>
			</li>
			<li>
				<span class="admindash-admin-list__label"><?php echo get_string('ui_platform_branding_site_summary', 'local_admindashboard'); ?></span>
				<span class="admindash-admin-list__value"><?php echo s($sitesummary !== '' ? core_text::substr($sitesummary, 0, 120) . (core_text::strlen($sitesummary) > 120 ? '...' : '') : 'No summary configured'); ?></span>
			</li>
		</ul>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_platform_branding_brand_asset_preview', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_platform_branding_theme_rendered_assets', 'local_admindashboard'); ?></span>
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
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_platform_branding_the_active_theme_is_not_exposing_a_primary_or_compact_logo_thro_02b44bf8', 'local_admindashboard'); ?></p>
		<?php endif; ?>
	</div>

</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1"><?php echo get_string('ui_platform_branding_brand_control_matrix', 'local_admindashboard'); ?></h5>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_platform_branding_each_row_shows_which_branding_surface_exists_what_signal_it_exp_e635b27f', 'local_admindashboard'); ?></p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th><?php echo get_string('ui_platform_branding_surface', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_platform_branding_status', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_platform_branding_current_snapshot', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_platform_branding_why_it_matters', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_platform_branding_action', 'local_admindashboard'); ?></th>
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
local_admindashboard_render_footer();