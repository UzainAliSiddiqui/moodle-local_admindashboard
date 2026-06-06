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

local_admindashboard_setup_page('/local/admindashboard/announcements.php', 'Announcements', 'communication.announcements');
local_admindashboard_render_header('communication.announcements');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$status = trim(optional_param('status', 'all', PARAM_ALPHA));
$q = trim(optional_param('q', '', PARAM_TEXT));

$tabs = local_admindashboard_get_communication_suite_tabs();
$now = time();
$forumtype = 'news';

$statusoptions = [
	'all' => 'All announcement states',
	'live' => 'Live now',
	'scheduled' => 'Scheduled',
	'archived' => 'Archived',
];
if (!array_key_exists($status, $statusoptions)) {
	$status = 'all';
}

$categoryoptions = $DB->get_records_sql(
	"SELECT cc.id, cc.name,
			COUNT(DISTINCT f.id) AS forumcount,
			COUNT(DISTINCT c.id) AS coursecount
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course AND c.id > 1
	   JOIN {course_categories} cc ON cc.id = c.category
	  WHERE f.type = :forumtype
   GROUP BY cc.id, cc.name
   ORDER BY forumcount DESC, cc.name ASC",
	['forumtype' => $forumtype]
);

$where = [
	'f.type = :forumtype',
	'c.id > 1',
];
$params = [
	'forumtype' => $forumtype,
];

if ($categoryid > 0) {
	$where[] = 'cc.id = :categoryid';
	$params['categoryid'] = $categoryid;
}

if ($status === 'live') {
	$where[] = 'fd.id IS NOT NULL';
	$where[] = 'fd.timestart <= :livenowstart';
	$where[] = '(fd.timeend = 0 OR fd.timeend >= :livenowend)';
	$params['livenowstart'] = $now;
	$params['livenowend'] = $now;
} else if ($status === 'scheduled') {
	$where[] = 'fd.id IS NOT NULL';
	$where[] = 'fd.timestart > :schedulednow';
	$params['schedulednow'] = $now;
} else if ($status === 'archived') {
	$where[] = 'fd.id IS NOT NULL';
	$where[] = 'fd.timeend > 0';
	$where[] = 'fd.timeend < :archivednow';
	$params['archivednow'] = $now;
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
	'summarynow1' => $now,
	'summarynow2' => $now,
	'summarynow3' => $now,
	'summarynow4' => $now,
];

$summary = $DB->get_record_sql(
	"SELECT COUNT(DISTINCT f.id) AS forumcount,
			COUNT(DISTINCT c.id) AS coursecount,
			COUNT(DISTINCT cc.id) AS categorycount,
			COUNT(DISTINCT CASE
				WHEN fd.id IS NOT NULL AND fd.timestart <= :summarynow1 AND (fd.timeend = 0 OR fd.timeend >= :summarynow2)
				THEN fd.id ELSE NULL END) AS livethreads,
			COUNT(DISTINCT CASE
				WHEN fd.id IS NOT NULL AND fd.timestart > :summarynow3
				THEN fd.id ELSE NULL END) AS scheduledthreads,
			COUNT(DISTINCT CASE
				WHEN fd.id IS NOT NULL AND fd.timeend > 0 AND fd.timeend < :summarynow4
				THEN fd.id ELSE NULL END) AS archivedthreads,
			COUNT(DISTINCT CASE WHEN f.forcesubscribe <> 0 THEN f.id ELSE NULL END) AS broadcastchannels,
			COALESCE(SUM(CASE WHEN fpall.parent <> 0 AND fpall.deleted = 0 THEN 1 ELSE 0 END), 0) AS totalreplies
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course
	   JOIN {course_categories} cc ON cc.id = c.category
  LEFT JOIN {forum_discussions} fd ON fd.forum = f.id
  LEFT JOIN {forum_posts} fpall ON fpall.discussion = fd.id
	  WHERE {$wheresql}",
	$summaryparams
);

$rows = $DB->get_records_sql(
	"SELECT fd.id,
			fd.name,
			fd.timestart,
			fd.timeend,
			fd.timemodified,
			fd.pinned,
			f.id AS forumid,
			f.name AS forumname,
			f.forcesubscribe,
			c.id AS courseid,
			c.fullname AS coursename,
			c.visible AS coursevisible,
			cc.name AS categoryname,
			u.firstname,
			u.lastname,
			COALESCE(fp.subject, fd.name) AS subject,
			COUNT(fpall.id) AS totalposts,
			SUM(CASE WHEN fpall.parent <> 0 AND fpall.deleted = 0 THEN 1 ELSE 0 END) AS replycount
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course
	   JOIN {course_categories} cc ON cc.id = c.category
	   JOIN {forum_discussions} fd ON fd.forum = f.id
  LEFT JOIN {forum_posts} fp ON fp.id = fd.firstpost
  LEFT JOIN {forum_posts} fpall ON fpall.discussion = fd.id AND fpall.deleted = 0
  LEFT JOIN {user} u ON u.id = fd.userid
	  WHERE {$wheresql}
   GROUP BY fd.id, fd.name, fd.timestart, fd.timeend, fd.timemodified, fd.pinned,
			f.id, f.name, f.forcesubscribe,
			c.id, c.fullname, c.visible,
			cc.name,
			u.firstname, u.lastname,
			fp.subject
   ORDER BY fd.pinned DESC, fd.timemodified DESC",
	$params,
	0,
	14
);

$breakdownparams = $params;
$categorybreakdown = $DB->get_records_sql(
	"SELECT cc.id,
			cc.name,
			COUNT(DISTINCT f.id) AS forumcount,
			COUNT(DISTINCT c.id) AS coursecount,
			COUNT(DISTINCT fd.id) AS discussioncount
	   FROM {forum} f
	   JOIN {course} c ON c.id = f.course
	   JOIN {course_categories} cc ON cc.id = c.category
  LEFT JOIN {forum_discussions} fd ON fd.forum = f.id
	  WHERE {$wheresql}
   GROUP BY cc.id, cc.name
   ORDER BY discussioncount DESC, forumcount DESC, cc.name ASC",
	$breakdownparams,
	0,
	6
);

$statusbadge = static function(string $label, string $class): string {
	return '<span class="admindash-admin-badge ' . $class . '">' . s($label) . '</span>';
};

$lanes = [
	['title' => 'Live now', 'count' => (int)($summary->livethreads ?? 0), 'note' => 'Announcements currently within their visible window.', 'class' => ((int)($summary->livethreads ?? 0) > 0) ? 'is-success' : 'is-warn'],
	['title' => 'Scheduled', 'count' => (int)($summary->scheduledthreads ?? 0), 'note' => 'Announcements queued for future release.', 'class' => ((int)($summary->scheduledthreads ?? 0) > 0) ? 'is-info' : 'is-warn'],
	['title' => 'Archived', 'count' => (int)($summary->archivedthreads ?? 0), 'note' => 'Posts whose visibility window has already closed.', 'class' => ((int)($summary->archivedthreads ?? 0) > 0) ? 'is-warn' : 'is-success'],
];

local_admindashboard_render_workspace_header(
	'Communication',
	'Announcements',
	'Announcement operations workspace for forum-backed broadcast channels, scheduled notice windows, and course-category coverage across the LMS.',
	'announcement',
	'communication.announcements',
	$tabs,
	[
		['label' => 'Forum index', 'url' => new moodle_url('/mod/forum/index.php'), 'primary' => true],
		['label' => 'Direct Messaging', 'url' => new moodle_url('/local/admindashboard/direct_messaging.php'), 'primary' => false],
		['label' => 'Notifications center', 'url' => new moodle_url('/message/output/popup/notifications.php'), 'primary' => false],
	],
	[
		(int)($summary->forumcount ?? 0) . ' announcement channels',
		(int)($summary->livethreads ?? 0) . ' live threads',
		(int)($summary->scheduledthreads ?? 0) . ' scheduled',
	]
);

$formatwindow = static function(int $timestart, int $timeend): string {
	if ($timestart <= 0 && $timeend <= 0) {
		return 'Always visible';
	}
	if ($timestart > 0 && $timeend <= 0) {
		return 'Starts ' . userdate($timestart);
	}
	if ($timestart <= 0 && $timeend > 0) {
		return 'Until ' . userdate($timeend);
	}
	return userdate($timestart) . ' to ' . userdate($timeend);
};

$resolvedcategoryid = array_key_exists($categoryid, $categoryoptions) ? $categoryid : 0;
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title"><?php echo get_string('ui_announcements_filters', 'local_admindashboard'); ?></div>

	<label class="mb-0" for="announcementCategory"><?php echo get_string('ui_announcements_category', 'local_admindashboard'); ?></label>
	<select id="announcementCategory" name="categoryid" class="form-select" style="max-width:300px">
		<option value="0" <?php echo $resolvedcategoryid === 0 ? 'selected' : ''; ?>>All categories</option>
		<?php foreach ($categoryoptions as $option): ?>
			<option value="<?php echo (int)$option->id; ?>" <?php echo $resolvedcategoryid === (int)$option->id ? 'selected' : ''; ?>>
				<?php echo s($option->name); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="announcementStatus"><?php echo get_string('ui_announcements_status', 'local_admindashboard'); ?></label>
	<select id="announcementStatus" name="status" class="form-select" style="max-width:240px">
		<?php foreach ($statusoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="announcementSearch"><?php echo get_string('ui_announcements_search', 'local_admindashboard'); ?></label>
	<input id="announcementSearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Discussion, course, or category" />

	<button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_announcements_apply', 'local_admindashboard'); ?></button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/announcements.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_announcements_announcement_channels', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->forumcount ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo (int)($summary->coursecount ?? 0); ?> <?php echo get_string('ui_announcements_courses_currently_exposing_forum_based_announcement_spaces', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_announcements_live_threads', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->livethreads ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_announcements_announcements_that_are_currently_within_their_visible_publishing_window', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_announcements_scheduled_queue', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->scheduledthreads ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_announcements_announcement_discussions_configured_to_appear_later', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_announcements_reply_volume', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->totalreplies ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_announcements_replies_inside_the_currently_filtered_announcement_discussion_set', 'local_admindashboard'); ?></div>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1"><?php echo get_string('ui_announcements_announcement_queue', 'local_admindashboard'); ?></h5>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_announcements_recent_announcement_discussions_ordered_by_pin_state_and_latest_activity', 'local_admindashboard'); ?></p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th><?php echo get_string('ui_announcements_announcement', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_announcements_course', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_announcements_category', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_announcements_status', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_announcements_visibility_window', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_announcements_replies', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_announcements_owner', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_announcements_action', 'local_admindashboard'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($rows)): ?>
					<tr>
						<td colspan="8" class="text-center py-4"><?php echo get_string('ui_announcements_no_announcement_discussions_matched_the_current_filters', 'local_admindashboard'); ?></td>
					</tr>
				<?php else: ?>
					<?php foreach ($rows as $row): ?>
						<?php
						$rowstatus = 'Live now';
						$rowclass = 'is-success';
						if ((int)$row->timestart > $now) {
							$rowstatus = 'Scheduled';
							$rowclass = 'is-info';
						} else if ((int)$row->timeend > 0 && (int)$row->timeend < $now) {
							$rowstatus = 'Archived';
							$rowclass = 'is-warn';
						}
						$author = trim(fullname((object)[
							'firstname' => (string)$row->firstname,
							'lastname' => (string)$row->lastname,
						]));
						?>
						<tr>
							<td>
								<div style="font-weight:600"><?php echo s($row->subject); ?></div>
								<div class="admindash-admin-note"><?php echo s($row->forumname); ?><?php echo !empty($row->pinned) ? ' · pinned' : ''; ?></div>
							</td>
							<td>
								<?php echo s($row->coursename); ?>
								<div class="admindash-admin-note"><?php echo !empty($row->coursevisible) ? 'Visible course' : 'Hidden course'; ?></div>
							</td>
							<td><?php echo s($row->categoryname); ?></td>
							<td><?php echo $statusbadge($rowstatus, $rowclass); ?></td>
							<td><?php echo s($formatwindow((int)$row->timestart, (int)$row->timeend)); ?></td>
							<td><?php echo (int)($row->replycount ?? 0); ?></td>
							<td><?php echo s($author !== '' ? $author : 'System user'); ?></td>
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
			<h5 class="mb-0"><?php echo get_string('ui_announcements_announcement_lanes', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_announcements_operational_split', 'local_admindashboard'); ?></span>
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
			<h5 class="mb-0"><?php echo get_string('ui_announcements_category_coverage', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_announcements_top_segments', 'local_admindashboard'); ?></span>
		</div>
		<ul class="admindash-admin-list">
			<?php if (empty($categorybreakdown)): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo get_string('ui_announcements_no_categories', 'local_admindashboard'); ?></span>
					<span class="admindash-admin-list__value"><?php echo get_string('ui_announcements_there_are_no_announcement_categories_in_the_current_filter_scope', 'local_admindashboard'); ?></span>
				</li>
			<?php else: ?>
				<?php foreach ($categorybreakdown as $segment): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($segment->name); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$segment->coursecount; ?> <?php echo get_string('ui_announcements_courses', 'local_admindashboard'); ?> <?php echo (int)$segment->forumcount; ?> <?php echo get_string('ui_announcements_channels', 'local_admindashboard'); ?> <?php echo (int)$segment->discussioncount; ?> <?php echo get_string('ui_announcements_discussions', 'local_admindashboard'); ?></span>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>

</div>

<?php
local_admindashboard_render_footer();