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

local_admindashboard_setup_page('/local/admindashboard/course_list.php', 'Course List', 'admintools.courses.list');
local_admindashboard_render_header('admintools.courses.list');

$q = trim(optional_param('q', '', PARAM_TEXT));
$categoryid = max(0, optional_param('categoryid', 0, PARAM_INT));
$visibility = trim(optional_param('visibility', 'all', PARAM_ALPHA));
$lifecycle = trim(optional_param('lifecycle', 'all', PARAM_ALPHA));
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 18;
$now = time();

$tabs = local_admindashboard_get_manage_courses_suite_tabs();
$categories = $DB->get_records_sql(
	"SELECT id, name
	   FROM {course_categories}
	  WHERE id > 0
   ORDER BY path ASC, name ASC",
	[]
);

$visibilityoptions = [
	'all' => 'All visibility states',
	'visible' => 'Visible only',
	'hidden' => 'Hidden only',
];
$lifecycleoptions = [
	'all' => 'All course states',
	'running' => 'Running now',
	'upcoming' => 'Upcoming',
	'ended' => 'Ended',
	'nodates' => 'No schedule',
];
if (!array_key_exists($visibility, $visibilityoptions)) {
	$visibility = 'all';
}
if (!array_key_exists($lifecycle, $lifecycleoptions)) {
	$lifecycle = 'all';
}

$conditions = ['c.id > 1'];
$params = [];
if ($categoryid > 0) {
	$conditions[] = 'c.category = :categoryid';
	$params['categoryid'] = $categoryid;
}
if ($q !== '') {
	$conditions[] = '(c.fullname LIKE :qfull OR c.shortname LIKE :qshort)';
	$like = '%' . $q . '%';
	$params['qfull'] = $like;
	$params['qshort'] = $like;
}
if ($visibility === 'visible') {
	$conditions[] = 'c.visible = 1';
} else if ($visibility === 'hidden') {
	$conditions[] = 'c.visible = 0';
}
switch ($lifecycle) {
	case 'running':
		$conditions[] = 'c.startdate > 0';
		$conditions[] = 'c.startdate <= :nowrunning';
		$conditions[] = '(c.enddate = 0 OR c.enddate >= :nowrunningend)';
		$params['nowrunning'] = $now;
		$params['nowrunningend'] = $now;
		break;
	case 'upcoming':
		$conditions[] = 'c.startdate > :nowupcoming';
		$params['nowupcoming'] = $now;
		break;
	case 'ended':
		$conditions[] = 'c.enddate > 0';
		$conditions[] = 'c.enddate < :nowended';
		$params['nowended'] = $now;
		break;
	case 'nodates':
		$conditions[] = 'c.startdate = 0';
		$conditions[] = 'c.enddate = 0';
		break;
}

$where = implode(' AND ', $conditions);

$summary = $DB->get_record_sql(
	"SELECT COUNT(1) AS totalcourses,
			SUM(CASE WHEN c.visible = 1 THEN 1 ELSE 0 END) AS visiblecourses,
			SUM(CASE WHEN c.visible = 0 THEN 1 ELSE 0 END) AS hiddencourses,
			SUM(CASE WHEN c.startdate > 0 AND c.startdate <= :summarynow AND (c.enddate = 0 OR c.enddate >= :summarynowend) THEN 1 ELSE 0 END) AS runningcourses,
			AVG(COALESCE(modstats.modulecount, 0)) AS avgmodules
	   FROM {course} c
  LEFT JOIN (
			SELECT cm.course, COUNT(1) AS modulecount
			  FROM {course_modules} cm
			  JOIN {modules} m ON m.id = cm.module AND m.name <> 'label'
			 WHERE cm.deletioninprogress = 0
		  GROUP BY cm.course
	   ) modstats ON modstats.course = c.id
	  WHERE {$where}",
	['summarynow' => $now, 'summarynowend' => $now] + $params
);

$total = (int)($summary->totalcourses ?? 0);

$courses = $DB->get_records_sql(
	"SELECT c.id, c.fullname, c.shortname, c.visible, c.startdate, c.enddate, c.timecreated, c.timemodified,
			COALESCE(cc.name, 'Uncategorised') AS categoryname,
			COALESCE(c.format, 'topics') AS formatname,
			COALESCE(modstats.modulecount, 0) AS modulecount,
			COALESCE(modstats.trackablecount, 0) AS trackablecount,
			COALESCE(sectionstats.sectioncount, 0) AS sectioncount,
			COALESCE(enrolstats.learnercount, 0) AS learnercount
	   FROM {course} c
  LEFT JOIN {course_categories} cc ON cc.id = c.category
  LEFT JOIN (
			SELECT cm.course,
				   COUNT(1) AS modulecount,
				   SUM(CASE WHEN cm.completion > 0 THEN 1 ELSE 0 END) AS trackablecount
			  FROM {course_modules} cm
			  JOIN {modules} m ON m.id = cm.module AND m.name <> 'label'
			 WHERE cm.deletioninprogress = 0
		  GROUP BY cm.course
	   ) modstats ON modstats.course = c.id
  LEFT JOIN (
			SELECT cs.course, COUNT(1) AS sectioncount
			  FROM {course_sections} cs
			 WHERE cs.section > 0
		  GROUP BY cs.course
	   ) sectionstats ON sectionstats.course = c.id
  LEFT JOIN (
			SELECT e.courseid, COUNT(DISTINCT ue.userid) AS learnercount
			  FROM {enrol} e
			  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
			 WHERE e.status = 0
		  GROUP BY e.courseid
	   ) enrolstats ON enrolstats.courseid = c.id
	  WHERE {$where}
   ORDER BY c.visible DESC, learnercount DESC, c.timemodified DESC, c.fullname ASC",
	$params,
	$page * $perpage,
	$perpage
);

$categoryrows = $DB->get_records_sql(
	"SELECT COALESCE(cc.name, 'Uncategorised') AS categoryname, COUNT(1) AS coursecount
	   FROM {course} c
  LEFT JOIN {course_categories} cc ON cc.id = c.category
	  WHERE {$where}
   GROUP BY COALESCE(cc.name, 'Uncategorised')
   ORDER BY coursecount DESC, categoryname ASC",
	$params,
	0,
	8
);

$baseurl = new moodle_url('/local/admindashboard/course_list.php', [
	'q' => $q,
	'categoryid' => $categoryid,
	'visibility' => $visibility,
	'lifecycle' => $lifecycle,
]);

local_admindashboard_render_workspace_header(
	'Admin Tools / Manage Courses',
	'Course List',
	'Operational view of your course catalog with lifecycle, visibility, and readiness signals in one place.',
	'courses',
	'admintools.courses.list',
	$tabs,
	[
		['label' => 'Create new course', 'url' => new moodle_url('/local/admindashboard/create_course.php'), 'primary' => true],
		['label' => 'Course templates', 'url' => new moodle_url('/local/admindashboard/course_templates.php'), 'primary' => false],
		['label' => 'Core course admin', 'url' => new moodle_url('/course/management.php'), 'primary' => false],
	],
	['Catalog live', 'Lifecycle aware', 'Readiness focused']
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title"><?php echo get_string('ui_course_list_filters', 'local_admindashboard'); ?></div>

	<label class="mb-0" for="courseSearch"><?php echo get_string('ui_course_list_search', 'local_admindashboard'); ?></label>
	<input id="courseSearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Course name or shortname" />

	<label class="mb-0" for="categorySelect"><?php echo get_string('ui_course_list_category', 'local_admindashboard'); ?></label>
	<select id="categorySelect" name="categoryid" class="form-select" style="max-width:260px">
		<option value="0" <?php echo $categoryid === 0 ? 'selected' : ''; ?>>All categories</option>
		<?php foreach ($categories as $category): ?>
			<option value="<?php echo (int)$category->id; ?>" <?php echo $categoryid === (int)$category->id ? 'selected' : ''; ?>>
				<?php echo s($category->name); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="visibilitySelect"><?php echo get_string('ui_course_list_visibility', 'local_admindashboard'); ?></label>
	<select id="visibilitySelect" name="visibility" class="form-select" style="max-width:220px">
		<?php foreach ($visibilityoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $visibility === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<label class="mb-0" for="lifecycleSelect"><?php echo get_string('ui_course_list_lifecycle', 'local_admindashboard'); ?></label>
	<select id="lifecycleSelect" name="lifecycle" class="form-select" style="max-width:220px">
		<?php foreach ($lifecycleoptions as $value => $label): ?>
			<option value="<?php echo s($value); ?>" <?php echo $lifecycle === $value ? 'selected' : ''; ?>>
				<?php echo s($label); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_course_list_apply', 'local_admindashboard'); ?></button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/course_list.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_course_list_courses', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->totalcourses ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_course_list_catalog_rows_matched_by_the_current_course_filters', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_course_list_visible', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->visiblecourses ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo (int)($summary->hiddencourses ?? 0); ?> <?php echo get_string('ui_course_list_hidden_courses_remain_in_the_filtered_set', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_course_list_running_now', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)($summary->runningcourses ?? 0); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_course_list_courses_currently_inside_their_configured_schedule_window', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_course_list_avg_modules', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo (int)round((float)($summary->avgmodules ?? 0)); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_course_list_average_non_label_activity_count_across_the_filtered_catalog', 'local_admindashboard'); ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_course_list_category_distribution', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_course_list_top_groups', 'local_admindashboard'); ?></span>
		</div>
		<?php if (!empty($categoryrows)): ?>
			<ul class="admindash-admin-list">
				<?php foreach ($categoryrows as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($row->categoryname); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->coursecount; ?> <?php echo get_string('ui_course_list_courses', 'local_admindashboard'); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_course_list_no_categories_were_returned_for_the_current_filters', 'local_admindashboard'); ?></p>
		<?php endif; ?>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1"><?php echo get_string('ui_course_list_course_inventory', 'local_admindashboard'); ?></h5>
			<p class="admindash-admin-note mb-0"><?php echo get_string('ui_course_list_showing', 'local_admindashboard'); ?> <?php echo $total > 0 ? (($page * $perpage) + 1) : 0; ?>-<?php echo min((($page + 1) * $perpage), $total); ?> <?php echo get_string('ui_course_list_of', 'local_admindashboard'); ?> <?php echo $total; ?> <?php echo get_string('ui_course_list_matching_courses', 'local_admindashboard'); ?></p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th><?php echo get_string('ui_course_list_course', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_course_list_category', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_course_list_format', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_course_list_modules', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_course_list_learners', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_course_list_schedule', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_course_list_signals', 'local_admindashboard'); ?></th>
					<th><?php echo get_string('ui_course_list_actions', 'local_admindashboard'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($courses)): ?>
					<tr>
						<td colspan="8" class="text-center py-4"><?php echo get_string('ui_course_list_no_courses_found_for_the_current_filters', 'local_admindashboard'); ?></td>
					</tr>
				<?php else: ?>
					<?php foreach ($courses as $course): ?>
						<?php
						$signals = [];
						if ((int)$course->visible === 1) {
							$signals[] = '<span class="admindash-admin-badge is-success">Visible</span>';
						} else {
							$signals[] = '<span class="admindash-admin-badge is-danger">Hidden</span>';
						}
						if ((int)$course->startdate === 0 && (int)$course->enddate === 0) {
							$signals[] = '<span class="admindash-admin-badge is-warn">No dates</span>';
						} else if ((int)$course->startdate > 0 && (int)$course->startdate > $now) {
							$signals[] = '<span class="admindash-admin-badge is-info">Upcoming</span>';
						} else if ((int)$course->enddate > 0 && (int)$course->enddate < $now) {
							$signals[] = '<span class="admindash-admin-badge is-warn">Ended</span>';
						} else {
							$signals[] = '<span class="admindash-admin-badge is-info">Running</span>';
						}
						if ((int)$course->trackablecount > 0) {
							$signals[] = '<span class="admindash-admin-badge is-success">Completion ready</span>';
						}
						if ((int)$course->modulecount === 0) {
							$signals[] = '<span class="admindash-admin-badge is-danger">Empty shell</span>';
						}
						if ((int)$course->learnercount === 0) {
							$signals[] = '<span class="admindash-admin-badge is-warn">No learners</span>';
						}
						$schedule = 'No schedule';
						if ((int)$course->startdate > 0 || (int)$course->enddate > 0) {
							$schedule = ((int)$course->startdate > 0 ? userdate((int)$course->startdate, '%d %b %Y') : 'No start')
								. ' to '
								. ((int)$course->enddate > 0 ? userdate((int)$course->enddate, '%d %b %Y') : 'Open end');
						}
						?>
						<tr>
							<td>
								<div class="admindash-admin-user">
									<a href="<?php echo new moodle_url('/course/view.php', ['id' => (int)$course->id]); ?>" class="admindash-admin-user__name"><?php echo s($course->fullname); ?></a>
									<div class="admindash-admin-note"><?php echo s($course->shortname); ?> · <?php echo (int)$course->sectioncount; ?> <?php echo get_string('ui_course_list_sections', 'local_admindashboard'); ?></div>
								</div>
							</td>
							<td><?php echo s($course->categoryname); ?></td>
							<td><?php echo s($course->formatname); ?></td>
							<td><?php echo (int)$course->modulecount; ?> <?php echo get_string('ui_course_list_total', 'local_admindashboard'); ?> <?php echo (int)$course->trackablecount; ?> <?php echo get_string('ui_course_list_tracked', 'local_admindashboard'); ?></td>
							<td><?php echo (int)$course->learnercount; ?></td>
							<td><?php echo s($schedule); ?></td>
							<td><div class="admindash-admin-badges"><?php echo implode('', $signals); ?></div></td>
							<td>
								<div class="admindash-admin-actions-inline">
									<a href="<?php echo new moodle_url('/course/view.php', ['id' => (int)$course->id]); ?>">Open</a>
									<a href="<?php echo new moodle_url('/course/edit.php', ['id' => (int)$course->id]); ?>">Edit</a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl); ?>
</div>

<?php
local_admindashboard_render_footer();