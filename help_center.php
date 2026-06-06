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

local_admindashboard_setup_page('/local/admindashboard/help_center.php', 'Help Center', 'support.help');
local_admindashboard_render_header('support.help');

$courseid = optional_param('courseid', 0, PARAM_INT);
$topic = trim(optional_param('topic', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));

$meta = local_admindashboard_get_meta($courseid);
$tabs = local_admindashboard_get_support_account_suite_tabs();
$pluginmanager = \core_plugin_manager::instance();

$supportname = trim((string)get_config('moodle', 'supportname'));
$supportemail = trim((string)get_config('moodle', 'supportemail'));
$noreplyaddress = trim((string)get_config('moodle', 'noreplyaddress'));

$courseoptions = $meta['courses'] ?? [];
$resolvedcourseid = $courseid;
if ($resolvedcourseid <= 0 && !empty($courseoptions)) {
	$resolvedcourseid = (int)($courseoptions[0]['id'] ?? 0);
}

$topicoptions = [
	'all' => 'All Topics',
	'access' => 'Access & login',
	'profile' => 'Profile hygiene',
	'learning' => 'Learning blockers',
	'messaging' => 'Messaging routes',
	'platform' => 'Platform settings',
];
if (!array_key_exists($topic, $topicoptions)) {
	$topic = 'all';
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

$accessblockers = (int)($summary->suspendedusers ?? 0) + (int)($summary->unconfirmedusers ?? 0) + (int)($summary->nologinusers ?? 0);
$profilegaps = (int)($summary->missingdepartment ?? 0) + (int)($summary->missingemail ?? 0);
$neveraccess = (int)($summary->neveraccess ?? 0);

$atriskrows = $resolvedcourseid > 0 ? local_admindashboard_get_at_risk_participants($resolvedcourseid, '', 8) : [];
$atriskcount = is_array($atriskrows) ? count($atriskrows) : 0;

$enabledmessageplugins = $pluginmanager->get_enabled_plugins('message');
$messagechannelcount = is_array($enabledmessageplugins) ? count($enabledmessageplugins) : 0;

$topics = [
	[
		'key' => 'access',
		'title' => 'Access & Login Recovery',
		'signal' => $accessblockers,
		'badge' => $accessblockers > 0 ? 'is-danger' : 'is-success',
		'summary' => 'Resolve suspended, unconfirmed, and no-login account issues before they become manual support escalations.',
		'audience' => 'Admins and support operators',
		'route' => new moodle_url('/local/admindashboard/support_tickets.php', ['issuetype' => 'access']),
		'action' => 'Open access queue',
		'context' => (int)($summary->suspendedusers ?? 0) . ' suspended, ' . (int)($summary->unconfirmedusers ?? 0) . ' unconfirmed, ' . (int)($summary->nologinusers ?? 0) . ' no-login users.',
	],
	[
		'key' => 'profile',
		'title' => 'Profile Data Hygiene',
		'signal' => $profilegaps,
		'badge' => $profilegaps > 0 ? 'is-warn' : 'is-success',
		'summary' => 'Fix missing email and department values so routing, messaging, and reporting remain reliable.',
		'audience' => 'User management and reporting teams',
		'route' => new moodle_url('/local/admindashboard/manage_users.php', ['status' => 'missingdepartment']),
		'action' => 'Open profile gaps',
		'context' => (int)($summary->missingdepartment ?? 0) . ' missing department and ' . (int)($summary->missingemail ?? 0) . ' missing email records.',
	],
	[
		'key' => 'learning',
		'title' => 'Learning Blockers & Outreach',
		'signal' => $atriskcount,
		'badge' => $atriskcount > 0 ? 'is-danger' : 'is-success',
		'summary' => 'Use course-aware learner risk signals to trigger support outreach before participation drops further.',
		'audience' => 'Learning ops and success teams',
		'route' => new moodle_url('/local/admindashboard/support_tickets.php', ['courseid' => $resolvedcourseid, 'issuetype' => 'engagement']),
		'action' => 'Open learner blockers',
		'context' => $resolvedcourseid > 0 ? $atriskcount . ' at-risk learners detected in the selected course.' : 'Select a course to load learner-risk support context.',
	],
	[
		'key' => 'messaging',
		'title' => 'Messaging & Escalation Paths',
		'signal' => $messagechannelcount,
		'badge' => $messagechannelcount > 0 ? 'is-info' : 'is-warn',
		'summary' => 'Check the channels used for support follow-up, notifications, and direct learner intervention.',
		'audience' => 'Communications and support teams',
		'route' => new moodle_url('/admin/tool/messageinbound/index.php'),
		'action' => 'Open message routes',
		'context' => $messagechannelcount . ' enabled message processor plugins plus direct messaging routes inside the dashboard.',
	],
	[
		'key' => 'platform',
		'title' => 'Platform & Settings Troubleshooting',
		'signal' => ($supportemail !== '' ? 1 : 0) + ($noreplyaddress !== '' ? 1 : 0) + ($neveraccess > 0 ? 1 : 0),
		'badge' => ($supportemail !== '' && $noreplyaddress !== '') ? 'is-success' : 'is-warn',
		'summary' => 'Use this route when a support issue is really a system configuration, sender, or environment problem.',
		'audience' => 'Platform admins',
		'route' => new moodle_url('/local/admindashboard/system_config.php'),
		'action' => 'Open system config',
		'context' => 'Support email: ' . ($supportemail !== '' ? $supportemail : 'missing') . ' / No-reply: ' . ($noreplyaddress !== '' ? $noreplyaddress : 'missing') . ' / Never accessed users: ' . $neveraccess . '.',
	],
];

$filteredtopics = array_values(array_filter($topics, static function(array $item) use ($topic, $q): bool {
	if ($topic !== 'all' && $item['key'] !== $topic) {
		return false;
	}
	if ($q === '') {
		return true;
	}
	$needle = core_text::strtolower($q);
	return strpos(core_text::strtolower($item['title']), $needle) !== false
		|| strpos(core_text::strtolower($item['summary']), $needle) !== false
		|| strpos(core_text::strtolower($item['context']), $needle) !== false;
}));

local_admindashboard_render_workspace_header(
	'Support & Account',
	'Help Center',
	'Operational knowledge hub that turns live account blockers, learner-risk signals, and platform routes into clear support playbooks.',
	'help',
	'support.help',
	$tabs,
	[
		['label' => 'Support Tickets', 'url' => new moodle_url('/local/admindashboard/support_tickets.php'), 'primary' => true],
		['label' => 'System Config', 'url' => new moodle_url('/local/admindashboard/system_config.php'), 'primary' => false],
		['label' => 'Moodle Help', 'url' => new moodle_url('/help.php'), 'primary' => false],
	],
	[
		'Live support playbooks',
		$resolvedcourseid > 0 ? 'Course-aware learning help' : 'No course selected',
		$supportemail !== '' ? 'Support contact configured' : 'Support contact missing',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title"><?php echo get_string('ui_help_center_filters', 'local_admindashboard'); ?></div>

	<label class="mb-0" for="courseSelect"><?php echo get_string('ui_help_center_course', 'local_admindashboard'); ?></label>
	<select id="courseSelect" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>Auto-select course</option>
		<?php foreach ($courseoptions as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="topicSelect"><?php echo get_string('ui_help_center_topic', 'local_admindashboard'); ?></label>
	<select id="topicSelect" name="topic" class="form-select" style="max-width:240px">
		<?php foreach ($topicoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $topic === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="helpSearch"><?php echo get_string('ui_help_center_search', 'local_admindashboard'); ?></label>
	<input id="helpSearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Topic or issue keyword" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_help_center_apply', 'local_admindashboard'); ?></button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/help_center.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_help_center_knowledge_topics', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo count($filteredtopics); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_help_center_support_playbooks_currently_matching_the_active_filters', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_help_center_access_issues', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $accessblockers; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_help_center_suspended_unconfirmed_and_no_login_accounts_that_commonly_trigg_4ccf5f3d', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_help_center_profile_gaps', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $profilegaps; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_help_center_records_missing_routing_data_such_as_department_or_email', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_help_center_learning_help_signals', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $atriskcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_help_center_at_risk_learners_from_the_selected_course_used_as_a_proactive_s_b17aecff', 'local_admindashboard'); ?></div>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1"><?php echo get_string('ui_help_center_help_topics', 'local_admindashboard'); ?></h5>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_help_center_each_topic_combines_a_real_live_signal_a_short_support_playbook_4b3405a8', 'local_admindashboard'); ?></p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th><?php echo get_string('ui_help_center_topic', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_help_center_signal', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_help_center_audience', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_help_center_playbook_summary', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_help_center_live_context', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_help_center_action', 'local_admindashboard'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($filteredtopics)): ?>
					<tr>
						<td colspan="6" class="text-center py-4"><?php echo get_string('ui_help_center_no_help_topics_matched_the_current_filters', 'local_admindashboard'); ?></td>
					</tr>
				<?php else: ?>
					<?php foreach ($filteredtopics as $item): ?>
						<tr>
							<td><?php echo s($item['title']); ?></td>
							<td><span class="admindash-admin-badge <?php echo s($item['badge']); ?>"><?php echo (int)$item['signal']; ?></span></td>
							<td><?php echo s($item['audience']); ?></td>
							<td><?php echo s($item['summary']); ?></td>
							<td><?php echo s($item['context']); ?></td>
							<td>
								<div class="admindash-admin-actions-inline">
									<a href="<?php echo $item['route']; ?>"><?php echo s($item['action']); ?></a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>


<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_help_center_learning_blocker_snapshot', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_help_center_selected_course', 'local_admindashboard'); ?></span>
		</div>
		<?php if (!empty($atriskrows)): ?>
			<ul class="admindash-admin-list">
				<?php foreach ($atriskrows as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s((string)($row['name'] ?? 'Learner')); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)($row['risk_score'] ?? 0); ?> <?php echo get_string('ui_help_center_risk', 'local_admindashboard'); ?> <?php echo s(trim((string)($row['department'] ?? '')) !== '' ? (string)$row['department'] : 'Unassigned'); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_help_center_no_at_risk_learner_rows_were_returned_for_the_current_course_scope', 'local_admindashboard'); ?></p>
		<?php endif; ?>
	</div>
</div>

<?php
local_admindashboard_render_footer();