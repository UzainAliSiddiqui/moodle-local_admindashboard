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

local_admindashboard_setup_page('/local/admindashboard/forums_discussions.php', 'Forums & Discussions', 'communication.discussions');
local_admindashboard_render_header('communication.discussions');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$forumtype = trim(optional_param('forumtype', 'all', PARAM_ALPHANUMEXT));
$health = trim(optional_param('health', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));

$tabs = local_admindashboard_get_communication_suite_tabs();
$now = time();
$activecutoff = $now - (7 * DAYSECS);
$stalecutoff = $now - (14 * DAYSECS);
$responsecutoff = $now - (2 * DAYSECS);
$announcementtype = 'news';

$healthoptions = [
	'all' => 'All discussion states',
	'active' => 'Active',
	'unanswered' => 'Needs response',
	'stale' => 'Stale',
	'locked' => 'Locked',
];
if (!array_key_exists($health, $healthoptions)) {
	$health = 'all';
}

$formatforumtype = static function(string $type): string {
	$labels = [
		'general' => 'General forum',
		'eachuser' => 'Each person posts one discussion',
		'qanda' => 'Q and A',
		'blog' => 'Blog style',
		'social' => 'Social forum',
	];
	return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
};

$categoryoptions = $DB->get_records_sql(
	"SELECT cc.id, cc.name,
			COUNT(DISTINCT f.id) AS forumcount,
			COUNT(DISTINCT fd.id) AS discussioncount
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course AND c.id > 1
	   JOIN {course_categories} cc ON cc.id = c.category
  LEFT JOIN {forum_discussions} fd ON fd.forum = f.id
	  WHERE f.type <> :announcementtype
   GROUP BY cc.id, cc.name
   ORDER BY discussioncount DESC, cc.name ASC",
	['announcementtype' => $announcementtype]
);

$typeoptions = ['all' => 'All forum types'];
$typerows = $DB->get_records_sql(
	"SELECT f.type,
			COUNT(DISTINCT f.id) AS forumcount,
			COUNT(DISTINCT fd.id) AS discussioncount
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course AND c.id > 1
  LEFT JOIN {forum_discussions} fd ON fd.forum = f.id
	  WHERE f.type <> :announcementtype
   GROUP BY f.type
   ORDER BY forumcount DESC, f.type ASC",
	['announcementtype' => $announcementtype]
);
foreach ($typerows as $typerow) {
	$typekey = trim((string)$typerow->type);
	if ($typekey !== '') {
		$typeoptions[$typekey] = $formatforumtype($typekey);
	}
}
if (!array_key_exists($forumtype, $typeoptions)) {
	$forumtype = 'all';
}

$where = [
	'f.type <> :announcementtype',
	'c.id > 1',
	'fd.id IS NOT NULL',
];
$params = [
	'announcementtype' => $announcementtype,
];

if ($categoryid > 0) {
	$where[] = 'cc.id = :categoryid';
	$params['categoryid'] = $categoryid;
}

if ($forumtype !== 'all') {
	$where[] = 'f.type = :forumtypefilter';
	$params['forumtypefilter'] = $forumtype;
}

if ($health === 'active') {
	$where[] = 'fd.timemodified >= :activecutoff';
	$params['activecutoff'] = $activecutoff;
} else if ($health === 'unanswered') {
	$where[] = 'fd.timemodified <= :responsecutoff';
	$where[] = 'NOT EXISTS (SELECT 1 FROM {forum_posts} fpr WHERE fpr.discussion = fd.id AND fpr.deleted = 0 AND fpr.parent <> 0)';
	$params['responsecutoff'] = $responsecutoff;
} else if ($health === 'stale') {
	$where[] = 'fd.timemodified < :stalecutoff';
	$params['stalecutoff'] = $stalecutoff;
} else if ($health === 'locked') {
	$where[] = 'fd.timelocked > 0';
	$where[] = 'fd.timelocked <= :lockednow';
	$params['lockednow'] = $now;
}

if ($q !== '') {
	$search = '%' . $DB->sql_like_escape($q) . '%';
	$where[] = '('
		. $DB->sql_like('fd.name', ':searchdiscussion', false)
		. ' OR ' . $DB->sql_like('f.name', ':searchforum', false)
		. ' OR ' . $DB->sql_like('c.fullname', ':searchcourse', false)
		. ' OR ' . $DB->sql_like('cc.name', ':searchcategory', false)
		. ')';
	$params['searchdiscussion'] = $search;
	$params['searchforum'] = $search;
	$params['searchcourse'] = $search;
	$params['searchcategory'] = $search;
}

$wheresql = implode(' AND ', $where);

$summaryparams = $params + [
	'summaryactivecutoff' => $activecutoff,
	'summarystalecutoff' => $stalecutoff,
	'summaryresponsecutoff' => $responsecutoff,
	'summarylockednow' => $now,
];

$summary = $DB->get_record_sql(
	"SELECT COUNT(DISTINCT f.id) AS forumcount,
			COUNT(DISTINCT c.id) AS coursecount,
			COUNT(DISTINCT fd.id) AS discussioncount,
			COUNT(DISTINCT CASE WHEN fd.timemodified >= :summaryactivecutoff THEN fd.id ELSE NULL END) AS activecount,
			COUNT(DISTINCT CASE WHEN fd.timemodified < :summarystalecutoff THEN fd.id ELSE NULL END) AS stalecount,
			COUNT(DISTINCT CASE WHEN fd.timelocked > 0 AND fd.timelocked <= :summarylockednow THEN fd.id ELSE NULL END) AS lockedcount,
			COUNT(DISTINCT CASE
				WHEN fd.timemodified <= :summaryresponsecutoff
				 AND NOT EXISTS (
					SELECT 1
					  FROM {forum_posts} fpr
					 WHERE fpr.discussion = fd.id
					   AND fpr.deleted = 0
					   AND fpr.parent <> 0
				 )
				THEN fd.id ELSE NULL END) AS unansweredcount,
			COALESCE(SUM(CASE WHEN fpall.parent <> 0 AND fpall.deleted = 0 THEN 1 ELSE 0 END), 0) AS totalreplies
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course
	   JOIN {course_categories} cc ON cc.id = c.category
	   JOIN {forum_discussions} fd ON fd.forum = f.id
  LEFT JOIN {forum_posts} fpall ON fpall.discussion = fd.id
	  WHERE {$wheresql}",
	$summaryparams
);

$rows = $DB->get_records_sql(
	"SELECT fd.id,
			fd.name,
			fd.timemodified,
			fd.timestart,
			fd.timeend,
			fd.pinned,
			fd.timelocked,
			f.id AS forumid,
			f.name AS forumname,
			f.type,
			f.forcesubscribe,
			f.trackingtype,
			c.id AS courseid,
			c.fullname AS coursename,
			c.visible AS coursevisible,
			cc.name AS categoryname,
			starter.firstname AS starterfirstname,
			starter.lastname AS starterlastname,
			COALESCE(fp.subject, fd.name) AS subject,
			COUNT(fpall.id) AS totalposts,
			SUM(CASE WHEN fpall.parent <> 0 AND fpall.deleted = 0 THEN 1 ELSE 0 END) AS replycount,
			MAX(fpall.created) AS lastpostcreated
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course
	   JOIN {course_categories} cc ON cc.id = c.category
	   JOIN {forum_discussions} fd ON fd.forum = f.id
  LEFT JOIN {forum_posts} fp ON fp.id = fd.firstpost
  LEFT JOIN {forum_posts} fpall ON fpall.discussion = fd.id AND fpall.deleted = 0
  LEFT JOIN {user} starter ON starter.id = fd.userid
	  WHERE {$wheresql}
   GROUP BY fd.id, fd.name, fd.timemodified, fd.timestart, fd.timeend, fd.pinned, fd.timelocked,
			f.id, f.name, f.type, f.forcesubscribe, f.trackingtype,
			c.id, c.fullname, c.visible,
			cc.name,
			starter.firstname, starter.lastname,
			fp.subject
   ORDER BY fd.pinned DESC, fd.timemodified DESC",
	$params,
	0,
	16
);

$forumhealth = $DB->get_records_sql(
	"SELECT f.id,
			f.name,
			f.type,
			COUNT(DISTINCT fd.id) AS discussioncount,
			COUNT(DISTINCT CASE
				WHEN fd.timemodified <= :forumresponsecutoff
				 AND NOT EXISTS (
					SELECT 1
					  FROM {forum_posts} fpr
					 WHERE fpr.discussion = fd.id
					   AND fpr.deleted = 0
					   AND fpr.parent <> 0
				 )
				THEN fd.id ELSE NULL END) AS unansweredcount,
			COUNT(DISTINCT CASE WHEN fd.timemodified < :forumstalecutoff THEN fd.id ELSE NULL END) AS stalecount,
			MAX(fd.timemodified) AS lastactivity
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course
	   JOIN {course_categories} cc ON cc.id = c.category
	   JOIN {forum_discussions} fd ON fd.forum = f.id
	  WHERE {$wheresql}
   GROUP BY f.id, f.name, f.type
   ORDER BY discussioncount DESC, unansweredcount DESC, lastactivity DESC",
	$params + [
		'forumresponsecutoff' => $responsecutoff,
		'forumstalecutoff' => $stalecutoff,
	],
	0,
	6
);

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

$lanes = [
	['title' => 'Active', 'count' => (int)($summary->activecount ?? 0), 'note' => 'Discussions updated within the last 7 days.', 'class' => ((int)($summary->activecount ?? 0) > 0) ? 'is-success' : 'is-warn'],
	['title' => 'Needs response', 'count' => (int)($summary->unansweredcount ?? 0), 'note' => 'Threads older than 48 hours with no replies.', 'class' => ((int)($summary->unansweredcount ?? 0) > 0) ? 'is-danger' : 'is-success'],
	['title' => 'Stale', 'count' => (int)($summary->stalecount ?? 0), 'note' => 'Discussions with no recent movement in the last 14 days.', 'class' => ((int)($summary->stalecount ?? 0) > 0) ? 'is-warn' : 'is-success'],
	['title' => 'Locked', 'count' => (int)($summary->lockedcount ?? 0), 'note' => 'Threads that have already crossed their lock date.', 'class' => ((int)($summary->lockedcount ?? 0) > 0) ? 'is-info' : 'is-success'],
];

local_admindashboard_render_workspace_header(
	'Communication',
	'Forums & Discussions',
	'Discussion-health workspace for collaborative forums, response backlog, stale threads, and the course spaces that need moderator attention first.',
	'discussion',
	'communication.discussions',
	$tabs,
	[
		['label' => 'Forum index', 'url' => new moodle_url('/mod/forum/index.php'), 'primary' => true],
		['label' => 'Sentiment Analyzer', 'url' => new moodle_url('/local/admindashboard/sentiment_analyzer.php'), 'primary' => false],
		['label' => 'Direct Messaging', 'url' => new moodle_url('/local/admindashboard/direct_messaging.php'), 'primary' => false],
	],
	[
		(int)($summary->forumcount ?? 0) . ' collaborative forums',
		(int)($summary->discussioncount ?? 0) . ' discussions',
		(int)($summary->unansweredcount ?? 0) . ' need response',
	]
);

$resolvedcategoryid = array_key_exists($categoryid, $categoryoptions) ? $categoryid : 0;
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title"><?php echo get_string('ui_forums_discussions_filters', 'local_admindashboard'); ?></div>

	<label class="mb-0" for="discussionCategory"><?php echo get_string('ui_forums_discussions_category', 'local_admindashboard'); ?></label>
	<select id="discussionCategory" name="categoryid" class="form-select" style="max-width:300px">
		<option value="0" <?php echo $resolvedcategoryid === 0 ? 'selected' : ''; ?>>All categories</option>
		<?php foreach ($categoryoptions as $option): ?>
			<option value="<?php echo (int)$option->id; ?>" <?php echo $resolvedcategoryid === (int)$option->id ? 'selected' : ''; ?>>
				<?php echo s($option->name); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="discussionType"><?php echo get_string('ui_forums_discussions_forum_type', 'local_admindashboard'); ?></label>
	<select id="discussionType" name="forumtype" class="form-select" style="max-width:260px">
		<?php foreach ($typeoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $forumtype === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="discussionHealth"><?php echo get_string('ui_forums_discussions_health', 'local_admindashboard'); ?></label>
	<select id="discussionHealth" name="health" class="form-select" style="max-width:240px">
		<?php foreach ($healthoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $health === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="discussionSearch"><?php echo get_string('ui_forums_discussions_search', 'local_admindashboard'); ?></label>
	<input id="discussionSearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Discussion, forum, or course" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_forums_discussions_apply', 'local_admindashboard'); ?></button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/forums_discussions.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_forums_discussions_forum_spaces', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->forumcount ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo (int)($summary->coursecount ?? 0); ?> <?php echo get_string('ui_forums_discussions_courses_currently_contribute_discussion_spaces_in_this_scope', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_forums_discussions_open_discussions', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->discussioncount ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_forums_discussions_discussion_threads_currently_matching_the_active_filters', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_forums_discussions_needs_response', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->unansweredcount ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_forums_discussions_threads_older_than_48_hours_where_nobody_has_replied_yet', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_forums_discussions_stale_threads', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->stalecount ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_forums_discussions_threads_with_no_recent_activity_over_the_last_14_days', 'local_admindashboard'); ?></div>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1"><?php echo get_string('ui_forums_discussions_discussion_queue', 'local_admindashboard'); ?></h5>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_forums_discussions_recent_discussions_ordered_by_pin_state_and_latest_modification_5890bf1f', 'local_admindashboard'); ?></p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th><?php echo get_string('ui_forums_discussions_discussion', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_forums_discussions_forum', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_forums_discussions_course', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_forums_discussions_status', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_forums_discussions_replies', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_forums_discussions_last_activity', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_forums_discussions_started_by', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_forums_discussions_action', 'local_admindashboard'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($rows)): ?>
					<tr>
						<td colspan="8" class="text-center py-4"><?php echo get_string('ui_forums_discussions_no_discussions_matched_the_current_filters', 'local_admindashboard'); ?></td>
					</tr>
				<?php else: ?>
					<?php foreach ($rows as $row): ?>
						<?php
						$replycount = (int)($row->replycount ?? 0);
						$rowstatus = 'Monitoring';
						$rowclass = 'is-info';
						if ((int)$row->timelocked > 0 && (int)$row->timelocked <= $now) {
							$rowstatus = 'Locked';
							$rowclass = 'is-warn';
						} else if ((int)$row->timemodified < $stalecutoff) {
							$rowstatus = 'Stale';
							$rowclass = 'is-warn';
						} else if ($replycount === 0 && (int)$row->timemodified <= $responsecutoff) {
							$rowstatus = 'Needs response';
							$rowclass = 'is-danger';
						} else if ((int)$row->timemodified >= $activecutoff) {
							$rowstatus = 'Active';
							$rowclass = 'is-success';
						}
						$starter = trim(fullname((object)[
							'firstname' => (string)$row->starterfirstname,
							'lastname' => (string)$row->starterlastname,
						]));
						$forumlabel = $formatforumtype((string)$row->type);
						?>
						<tr>
							<td>
								<div style="font-weight:600"><?php echo s($row->subject); ?></div>
								<div class="admindash-admin-note"><?php echo !empty($row->pinned) ? 'Pinned discussion' : 'Standard discussion'; ?></div>
							</td>
							<td>
								<?php echo s($row->forumname); ?>
								<div class="admindash-admin-note"><?php echo s($forumlabel); ?></div>
							</td>
							<td>
								<?php echo s($row->coursename); ?>
								<div class="admindash-admin-note"><?php echo s($row->categoryname); ?><?php echo !empty($row->coursevisible) ? ' · visible' : ' · hidden'; ?></div>
							</td>
							<td><?php echo $statusbadge($rowstatus, $rowclass); ?></td>
							<td><?php echo $replycount; ?></td>
							<td>
								<?php echo s(userdate((int)$row->timemodified)); ?>
								<?php if ((int)$row->timelocked > 0): ?>
									<div class="admindash-admin-note"><?php echo get_string('ui_forums_discussions_locks', 'local_admindashboard'); ?> <?php echo s(userdate((int)$row->timelocked)); ?></div>
								<?php endif; ?>
							</td>
							<td><?php echo s($starter !== '' ? $starter : 'Unknown user'); ?></td>
							<td>
								<div class="admindash-admin-actions-inline">
									<a href="<?php echo new moodle_url('/mod/forum/discuss.php', ['d' => (int)$row->id]); ?>">Open thread</a>
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
			<h5 class="mb-0"><?php echo get_string('ui_forums_discussions_health_lanes', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_forums_discussions_operational_split', 'local_admindashboard'); ?></span>
		</div>
		<ul class="admindash-admin-list">
			<?php foreach ($lanes as $lane): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo s($lane['title']); ?></span>
					<span class="admindash-admin-list__value"><span class="admindash-admin-badge <?php echo s($lane['class']); ?>"><?php echo (int)$lane['count']; ?></span> <?php echo s($lane['note']); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_forums_discussions_forum_health', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_forums_discussions_top_spaces', 'local_admindashboard'); ?></span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($forumhealth)): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo get_string('ui_forums_discussions_no_forums', 'local_admindashboard'); ?></span>
					<span class="admindash-admin-list__value"><?php echo get_string('ui_forums_discussions_there_are_no_collaborative_forums_in_the_current_filter_scope', 'local_admindashboard'); ?></span>
				</li>
			<?php else: ?>
				<?php foreach ($forumhealth as $space): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($space->name); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$space->discussioncount; ?> <?php echo get_string('ui_forums_discussions_discussions', 'local_admindashboard'); ?> <?php echo (int)$space->unansweredcount; ?> <?php echo get_string('ui_forums_discussions_unanswered', 'local_admindashboard'); ?> <?php echo (int)$space->stalecount; ?> <?php echo get_string('ui_forums_discussions_stale_last_active', 'local_admindashboard'); ?> <?php echo s(userdate((int)$space->lastactivity)); ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>

</div>

<?php
local_admindashboard_render_footer();