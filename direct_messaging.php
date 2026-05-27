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

local_admindashboard_setup_page('/local/admindashboard/direct_messaging.php', 'Direct Messaging', 'communication.messaging');
local_admindashboard_render_header('communication.messaging');

$courseid = optional_param('courseid', 0, PARAM_INT);
$convtype = trim(optional_param('convtype', 'all', PARAM_ALPHA));
$state = trim(optional_param('state', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));

$tabs = local_admindashboard_get_communication_suite_tabs();
$meta = local_admindashboard_get_meta($courseid);
$pluginmanager = \core_plugin_manager::instance();

$courseoptions = $meta['courses'] ?? [];
$resolvedcourseid = $courseid;
if ($resolvedcourseid <= 0 && !empty($courseoptions)) {
	$resolvedcourseid = (int)($courseoptions[0]['id'] ?? 0);
}

$typeoptions = [
	'all' => 'All conversation types',
	'individual' => 'Individual',
	'group' => 'Group',
	'self' => 'Self notes',
];
if (!array_key_exists($convtype, $typeoptions)) {
	$convtype = 'all';
}

$stateoptions = [
	'all' => 'All states',
	'unread' => 'Unread',
	'muted' => 'Muted',
];
if (!array_key_exists($state, $stateoptions)) {
	$state = 'all';
}

$typemap = [
	'individual' => \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL,
	'group' => \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP,
	'self' => \core_message\api::MESSAGE_CONVERSATION_TYPE_SELF,
];

$where = [
	'myself.userid = :memberuserid',
];
$params = [
	'memberuserid' => (int)$USER->id,
];

if ($convtype !== 'all') {
	$where[] = 'mc.type = :conversationtype';
	$params['conversationtype'] = (int)$typemap[$convtype];
}

if ($state === 'unread') {
	$where[] = 'EXISTS (
		SELECT 1
		  FROM {messages} mu
	 LEFT JOIN {message_user_actions} muaread
			ON muaread.messageid = mu.id
		   AND muaread.userid = :unreaduserid
		   AND muaread.action = :readaction
	 LEFT JOIN {message_user_actions} muadelete
			ON muadelete.messageid = mu.id
		   AND muadelete.userid = :unreaddeleteuserid
		   AND muadelete.action = :deleteaction
		 WHERE mu.conversationid = mc.id
		   AND mu.useridfrom <> :unreadfromuserid
		   AND muaread.id IS NULL
		   AND muadelete.id IS NULL
	)';
	$params['unreaduserid'] = (int)$USER->id;
	$params['readaction'] = \core_message\api::MESSAGE_ACTION_READ;
	$params['unreaddeleteuserid'] = (int)$USER->id;
	$params['deleteaction'] = \core_message\api::MESSAGE_ACTION_DELETED;
	$params['unreadfromuserid'] = (int)$USER->id;
} else if ($state === 'muted') {
	$where[] = 'EXISTS (
		SELECT 1
		  FROM {message_conversation_actions} mcax
		 WHERE mcax.conversationid = mc.id
		   AND mcax.userid = :muteduserid
		   AND mcax.action = :mutedaction
	)';
	$params['muteduserid'] = (int)$USER->id;
	$params['mutedaction'] = \core_message\api::CONVERSATION_ACTION_MUTED;
}

if ($q !== '') {
	$search = '%' . $DB->sql_like_escape($q) . '%';
	$where[] = '('
		. $DB->sql_like('mc.name', ':searchname', false)
		. ' OR EXISTS (
			SELECT 1
			  FROM {messages} ms
			 WHERE ms.conversationid = mc.id
			   AND (' . $DB->sql_like('ms.smallmessage', ':searchsmall', false)
		. ' OR ' . $DB->sql_like('ms.fullmessage', ':searchfull', false) . ')
		)
	)';
	$params['searchname'] = $search;
	$params['searchsmall'] = $search;
	$params['searchfull'] = $search;
}

$wheresql = implode(' AND ', $where);

$summaryparams = $params + [
	'summaryindtype' => \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL,
	'summarygrouptype' => \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP,
	'summaryselftype' => \core_message\api::MESSAGE_CONVERSATION_TYPE_SELF,
	'summaryuseridfrom' => (int)$USER->id,
	'summaryreaduserid' => (int)$USER->id,
	'summaryreadaction' => \core_message\api::MESSAGE_ACTION_READ,
	'summarydeleteuserid' => (int)$USER->id,
	'summarydeleteaction' => \core_message\api::MESSAGE_ACTION_DELETED,
	'summarymuteduserid' => (int)$USER->id,
	'summarymutedaction' => \core_message\api::CONVERSATION_ACTION_MUTED,
];

$summary = $DB->get_record_sql(
	"SELECT COUNT(DISTINCT mc.id) AS conversationcount,
			COUNT(DISTINCT CASE WHEN mc.type = :summaryindtype THEN mc.id ELSE NULL END) AS individualcount,
			COUNT(DISTINCT CASE WHEN mc.type = :summarygrouptype THEN mc.id ELSE NULL END) AS groupcount,
			COUNT(DISTINCT CASE WHEN mc.type = :summaryselftype THEN mc.id ELSE NULL END) AS selfcount,
			COUNT(DISTINCT CASE WHEN mca.id IS NOT NULL THEN mc.id ELSE NULL END) AS mutedcount,
			COALESCE(SUM(CASE
				WHEN m.id IS NOT NULL
				 AND m.useridfrom <> :summaryuseridfrom
				 AND muaread.id IS NULL
				 AND muadelete.id IS NULL
				THEN 1 ELSE 0 END), 0) AS unreadmessages,
			COUNT(DISTINCT CASE WHEN mc.enabled = :enabledstate THEN mc.id ELSE NULL END) AS enabledcount
	   FROM {message_conversations} mc
	   JOIN {message_conversation_members} myself ON myself.conversationid = mc.id
  LEFT JOIN {messages} m ON m.conversationid = mc.id
  LEFT JOIN {message_user_actions} muaread
		 ON muaread.messageid = m.id
		AND muaread.userid = :summaryreaduserid
		AND muaread.action = :summaryreadaction
  LEFT JOIN {message_user_actions} muadelete
		 ON muadelete.messageid = m.id
		AND muadelete.userid = :summarydeleteuserid
		AND muadelete.action = :summarydeleteaction
  LEFT JOIN {message_conversation_actions} mca
		 ON mca.conversationid = mc.id
		AND mca.userid = :summarymuteduserid
		AND mca.action = :summarymutedaction
	  WHERE {$wheresql}",
	$summaryparams + ['enabledstate' => \core_message\api::MESSAGE_CONVERSATION_ENABLED]
);

$rows = $DB->get_records_sql(
	"SELECT mc.id,
			mc.type,
			mc.name,
			mc.enabled,
			mc.timecreated,
			mc.timemodified,
			COUNT(DISTINCT members.userid) AS membercount,
			COUNT(DISTINCT m.id) AS messagecount,
			MAX(m.timecreated) AS latestmessage,
			SUM(CASE
				WHEN m.id IS NOT NULL
				 AND m.useridfrom <> :rowuseridfrom
				 AND muaread.id IS NULL
				 AND muadelete.id IS NULL
				THEN 1 ELSE 0 END) AS unreadcount,
			MAX(CASE WHEN mca.id IS NOT NULL THEN 1 ELSE 0 END) AS muted
	   FROM {message_conversations} mc
	   JOIN {message_conversation_members} myself ON myself.conversationid = mc.id
	   JOIN {message_conversation_members} members ON members.conversationid = mc.id
  LEFT JOIN {messages} m ON m.conversationid = mc.id
  LEFT JOIN {message_user_actions} muaread
		 ON muaread.messageid = m.id
		AND muaread.userid = :rowreaduserid
		AND muaread.action = :rowreadaction
  LEFT JOIN {message_user_actions} muadelete
		 ON muadelete.messageid = m.id
		AND muadelete.userid = :rowdeleteuserid
		AND muadelete.action = :rowdeleteaction
  LEFT JOIN {message_conversation_actions} mca
		 ON mca.conversationid = mc.id
		AND mca.userid = :rowmuteduserid
		AND mca.action = :rowmutedaction
	  WHERE {$wheresql}
   GROUP BY mc.id, mc.type, mc.name, mc.enabled, mc.timecreated, mc.timemodified
   ORDER BY unreadcount DESC, latestmessage DESC, mc.timemodified DESC",
	$params + [
		'rowuseridfrom' => (int)$USER->id,
		'rowreaduserid' => (int)$USER->id,
		'rowreadaction' => \core_message\api::MESSAGE_ACTION_READ,
		'rowdeleteuserid' => (int)$USER->id,
		'rowdeleteaction' => \core_message\api::MESSAGE_ACTION_DELETED,
		'rowmuteduserid' => (int)$USER->id,
		'rowmutedaction' => \core_message\api::CONVERSATION_ACTION_MUTED,
	],
	0,
	14
);

$memberlabels = [];
if (!empty($rows)) {
	list($insql, $inparams) = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED, 'conv');
	$recordset = $DB->get_recordset_sql(
		"SELECT mcm.conversationid, u.id, u.firstname, u.lastname
		   FROM {message_conversation_members} mcm
		   JOIN {user} u ON u.id = mcm.userid
		  WHERE mcm.conversationid {$insql}
			AND u.id <> :memberlabeluserid
			AND u.deleted = 0
	   ORDER BY u.lastname ASC, u.firstname ASC",
		$inparams + ['memberlabeluserid' => (int)$USER->id]
	);
	foreach ($recordset as $record) {
		$conversationid = (int)$record->conversationid;
		if (!isset($memberlabels[$conversationid])) {
			$memberlabels[$conversationid] = [];
		}
		$memberlabels[$conversationid][] = trim((string)$record->firstname . ' ' . (string)$record->lastname);
	}
	$recordset->close();
}

$incomingrequests = $DB->get_records_sql(
	"SELECT mcr.id, mcr.timecreated, u.id AS userid, u.firstname, u.lastname
	   FROM {message_contact_requests} mcr
	   JOIN {user} u ON u.id = mcr.userid
	  WHERE mcr.requesteduserid = :requestuserid
		AND u.deleted = 0
   ORDER BY mcr.timecreated DESC",
	['requestuserid' => (int)$USER->id],
	0,
	5
);

$blockedusers = $DB->get_records_sql(
	"SELECT mub.id, mub.timecreated, u.id AS userid, u.firstname, u.lastname
	   FROM {message_users_blocked} mub
	   JOIN {user} u ON u.id = mub.blockeduserid
	  WHERE mub.userid = :blockeduserid
		AND u.deleted = 0
   ORDER BY mub.timecreated DESC",
	['blockeduserid' => (int)$USER->id],
	0,
	5
);

$contactscount = (int)$DB->count_records('message_contacts', ['userid' => (int)$USER->id]);
$pendingrequestcount = (int)$DB->count_records('message_contact_requests', ['requesteduserid' => (int)$USER->id]);
$blockedcount = (int)$DB->count_records('message_users_blocked', ['userid' => (int)$USER->id]);
$unreadnotifications = (int)$DB->count_records_select('notifications', 'useridto = :userid AND timeread IS NULL', ['userid' => (int)$USER->id]);

$enabledmessageplugins = $pluginmanager->get_enabled_plugins('message');
$messagechannelcount = is_array($enabledmessageplugins) ? count($enabledmessageplugins) : 0;

$atriskrows = $resolvedcourseid > 0 ? local_admindashboard_get_at_risk_participants($resolvedcourseid, '', 6) : [];
$atriskcount = is_array($atriskrows) ? count($atriskrows) : 0;

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

$typebadge = static function(int $type) use ($statusbadge): string {
	if ($type === \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP) {
		return $statusbadge('Group', 'is-info');
	}
	if ($type === \core_message\api::MESSAGE_CONVERSATION_TYPE_SELF) {
		return $statusbadge('Self', 'is-warn');
	}
	return $statusbadge('Individual', 'is-success');
};

local_admindashboard_render_workspace_header(
	'Communication',
	'Direct Messaging',
	'Operator-facing messaging workspace for inbox posture, unread backlog, muted conversations, contact readiness, and course-aware outreach candidates.',
	'message',
	'communication.messaging',
	$tabs,
	[
		['label' => 'Core messaging', 'url' => new moodle_url('/message/index.php'), 'primary' => true],
		['label' => 'Notification preferences', 'url' => new moodle_url('/message/notificationpreferences.php', ['userid' => (int)$USER->id]), 'primary' => false],
		['label' => 'Pending requests', 'url' => new moodle_url('/message/pendingcontactrequests.php'), 'primary' => false],
	],
	[
		(int)($summary->conversationcount ?? 0) . ' conversations',
		(int)($summary->unreadmessages ?? 0) . ' unread messages',
		$resolvedcourseid > 0 ? $atriskcount . ' outreach candidates' : 'No course selected',
	]
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Filters</div>

	<label class="mb-0" for="messagingCourse">Course</label>
	<select id="messagingCourse" name="courseid" class="form-select" style="max-width:320px">
		<option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>Auto-select course</option>
		<?php foreach ($courseoptions as $course): ?>
			<option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
				<?php echo s($course['fullname']); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="messagingType">Conversation Type</label>
	<select id="messagingType" name="convtype" class="form-select" style="max-width:240px">
		<?php foreach ($typeoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $convtype === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="messagingState">State</label>
	<select id="messagingState" name="state" class="form-select" style="max-width:220px">
		<?php foreach ($stateoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $state === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="messagingSearch">Search</label>
	<input id="messagingSearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Conversation name or message text" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/direct_messaging.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Conversations</div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->conversationcount ?? 0); ?></div>
		<div class="admindash-module-stat__meta">Individual, group, and self conversations currently in the active view.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Unread Messages</div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->unreadmessages ?? 0); ?></div>
		<div class="admindash-module-stat__meta">Messages from other participants that still have no read or delete action for this user.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Unread Notifications</div>
		<div class="admindash-module-stat__value"><?php echo $unreadnotifications; ?></div>
		<div class="admindash-module-stat__meta">Event notifications still waiting in the notification stream.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Outreach Candidates</div>
		<div class="admindash-module-stat__value"><?php echo $atriskcount; ?></div>
		<div class="admindash-module-stat__meta"><?php echo $resolvedcourseid > 0 ? 'At-risk learners from the selected course ready for direct follow-up.' : 'Select a course to surface at-risk outreach candidates.'; ?></div>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Conversation Queue</h5>
			<p class="admindash-admin-note mb-0">Conversation posture for the current operator, ordered by unread work and recent activity.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Conversation</th>
					<th>Type</th>
					<th>Participants</th>
					<th>Unread</th>
					<th>Muted</th>
					<th>Messages</th>
					<th>Last Activity</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($rows)): ?>
					<tr>
						<td colspan="8" class="text-center py-4">No conversations matched the current filters.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($rows as $row): ?>
						<?php
						$conversationid = (int)$row->id;
						$labels = $memberlabels[$conversationid] ?? [];
						if ((int)$row->type === \core_message\api::MESSAGE_CONVERSATION_TYPE_SELF) {
							$title = trim((string)$row->name) !== '' ? trim((string)$row->name) : 'Notes to self';
							$participantlabel = 'Just you';
						} else if ((int)$row->type === \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP) {
							$title = trim((string)$row->name) !== '' ? trim((string)$row->name) : 'Unnamed group conversation';
							$participantlabel = !empty($labels) ? implode(', ', array_slice($labels, 0, 3)) . (count($labels) > 3 ? ' +' . (count($labels) - 3) : '') : 'No member names available';
						} else {
							$participantlabel = !empty($labels) ? implode(', ', array_slice($labels, 0, 2)) : 'No recipient visible';
							$title = $participantlabel;
						}
						$lastactivity = (int)($row->latestmessage ?? 0);
						if ($lastactivity <= 0) {
							$lastactivity = (int)($row->timemodified ?? 0);
						}
						?>
						<tr>
							<td>
								<div style="font-weight:600"><?php echo s($title); ?></div>
								<div class="admindash-admin-note"><?php echo !empty($row->enabled) ? 'Conversation enabled' : 'Conversation disabled'; ?></div>
							</td>
							<td><?php echo $typebadge((int)$row->type); ?></td>
							<td><?php echo s($participantlabel); ?></td>
							<td><?php echo (int)($row->unreadcount ?? 0); ?></td>
							<td><?php echo $statusbadge(!empty($row->muted) ? 'Muted' : 'Live', !empty($row->muted) ? 'is-warn' : 'is-success'); ?></td>
							<td><?php echo (int)($row->messagecount ?? 0); ?></td>
							<td><?php echo $lastactivity > 0 ? s(userdate($lastactivity)) : 'No message activity'; ?></td>
							<td>
								<div class="admindash-admin-actions-inline">
									<a href="<?php echo new moodle_url('/message/index.php', ['id' => $conversationid]); ?>">Open inbox</a>
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
			<h5 class="mb-0">Pending Requests</h5>
			<span class="admindash-admin-note">Contact posture</span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($incomingrequests)): ?>
				<li>
					<span class="admindash-admin-list__label">No pending requests</span>
					<span class="admindash-admin-list__value">There are no incoming contact requests waiting for review.</span>
				</li>
			<?php else: ?>
				<?php foreach ($incomingrequests as $request): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s(trim((string)$request->firstname . ' ' . (string)$request->lastname)); ?></span>
						<span class="admindash-admin-list__value">Requested <?php echo s(userdate((int)$request->timecreated)); ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Blocked Users</h5>
			<span class="admindash-admin-note">Visibility constraints</span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($blockedusers)): ?>
				<li>
					<span class="admindash-admin-list__label">No blocked users</span>
					<span class="admindash-admin-list__value">No users are currently blocked in this messaging account.</span>
				</li>
			<?php else: ?>
				<?php foreach ($blockedusers as $blocked): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s(trim((string)$blocked->firstname . ' ' . (string)$blocked->lastname)); ?></span>
						<span class="admindash-admin-list__value">Blocked <?php echo !empty($blocked->timecreated) ? s(userdate((int)$blocked->timecreated)) : 'previously'; ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Outreach Candidates</h5>
			<span class="admindash-admin-note">Course-aware follow-up</span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($atriskrows)): ?>
				<li>
					<span class="admindash-admin-list__label">No at-risk users</span>
					<span class="admindash-admin-list__value"><?php echo $resolvedcourseid > 0 ? 'No at-risk participants were returned for the selected course.' : 'Select a course to load at-risk participants.'; ?></span>
				</li>
			<?php else: ?>
				<?php foreach ($atriskrows as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><a href="<?php echo new moodle_url('/user/profile.php', ['id' => (int)($row['userid'] ?? 0)]); ?>"><?php echo s((string)($row['name'] ?? 'Unknown user')); ?></a></span>
						<span class="admindash-admin-list__value">Risk <?php echo (int)($row['risk_score'] ?? 0); ?>/3 · <?php echo s((string)($row['department'] ?? 'Unassigned')); ?><?php echo !empty($row['reasons'][0]) ? ' · ' . s((string)$row['reasons'][0]) : ''; ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>
</div>

<?php
local_admindashboard_render_footer();