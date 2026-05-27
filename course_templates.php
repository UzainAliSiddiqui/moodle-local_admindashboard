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

local_admindashboard_setup_page('/local/admindashboard/course_templates.php', 'Course Templates', 'admintools.courses.templates');
local_admindashboard_render_header('admintools.courses.templates');

$q = trim(optional_param('q', '', PARAM_TEXT));
$categoryid = max(0, optional_param('categoryid', 0, PARAM_INT));
$tabs = local_admindashboard_get_manage_courses_suite_tabs();

$categories = $DB->get_records_sql(
	"SELECT id, name
	   FROM {course_categories}
	  WHERE id > 0
   ORDER BY path ASC, name ASC",
	[]
);

$conditions = ['c.id > 1'];
$params = [];
if ($categoryid > 0) {
	$conditions[] = 'c.category = :categoryid';
	$params['categoryid'] = $categoryid;
}
if ($q !== '') {
	$conditions[] = '(c.fullname LIKE :qfull OR c.shortname LIKE :qshort OR c.format LIKE :qformat)';
	$like = '%' . $q . '%';
	$params['qfull'] = $like;
	$params['qshort'] = $like;
	$params['qformat'] = $like;
}
$where = implode(' AND ', $conditions);

$templatecourses = $DB->get_records_sql(
	"SELECT c.id, c.fullname, c.shortname, c.visible, COALESCE(c.format, 'topics') AS formatname,
			COALESCE(cc.name, 'Uncategorised') AS categoryname,
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
   ORDER BY trackablecount DESC, modulecount DESC, learnercount DESC, c.fullname ASC",
	$params,
	0,
	18
);

$templatecount = count($templatecourses);
$formatrows = $DB->get_records_sql(
	"SELECT COALESCE(NULLIF(c.format, ''), 'topics') AS formatname, COUNT(1) AS coursecount
	   FROM {course} c
	  WHERE {$where}
   GROUP BY COALESCE(NULLIF(c.format, ''), 'topics')
   ORDER BY coursecount DESC, formatname ASC",
	$params,
	0,
	8
);
$completionready = 0;
$reusable = 0;
foreach ($templatecourses as $course) {
	if ((int)$course->trackablecount > 0) {
		$completionready++;
	}
	if ((int)$course->modulecount >= 5 && (int)$course->sectioncount >= 3) {
		$reusable++;
	}
}

local_admindashboard_render_workspace_header(
	'Admin Tools / Manage Courses',
	'Course Templates',
	'Template-design workspace for spotting repeatable course structures and turning strong builds into reusable standards.',
	'courses',
	'admintools.courses.templates',
	$tabs,
	[
		['label' => 'Create new course', 'url' => new moodle_url('/local/admindashboard/create_course.php'), 'primary' => true],
		['label' => 'Course list', 'url' => new moodle_url('/local/admindashboard/course_list.php'), 'primary' => false],
		['label' => 'Modules report', 'url' => new moodle_url('/local/admindashboard/course_analytics_modules.php'), 'primary' => false],
	],
	['Pattern driven', 'Structure aware', 'Reuse focused']
);
?>

<form method="get" class="admindash-filters admindash-card">
	<div class="title">Template Search</div>

	<label class="mb-0" for="templateSearch">Search</label>
	<input id="templateSearch" name="q" class="form-control" style="max-width:280px" value="<?php echo s($q); ?>" placeholder="Course name, shortname, or format" />

	<label class="mb-0" for="templateCategorySelect">Category</label>
	<select id="templateCategorySelect" name="categoryid" class="form-select" style="max-width:260px">
		<option value="0" <?php echo $categoryid === 0 ? 'selected' : ''; ?>>All categories</option>
		<?php foreach ($categories as $category): ?>
			<option value="<?php echo (int)$category->id; ?>" <?php echo $categoryid === (int)$category->id ? 'selected' : ''; ?>>
				<?php echo s($category->name); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
	<a class="btn btn-outline-secondary" href="<?php echo new moodle_url('/local/admindashboard/course_templates.php'); ?>">Reset</a>
</form>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Candidates</div>
		<div class="admindash-module-stat__value"><?php echo $templatecount; ?></div>
		<div class="admindash-module-stat__meta">Courses currently surfaced as template candidates inside the selected scope.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Completion Ready</div>
		<div class="admindash-module-stat__value"><?php echo $completionready; ?></div>
		<div class="admindash-module-stat__meta">Courses with at least one tracked activity, making them easier to operationalize.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Reusable Structures</div>
		<div class="admindash-module-stat__value"><?php echo $reusable; ?></div>
		<div class="admindash-module-stat__meta">Courses with enough sections and modules to act as solid blueprint candidates.</div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label">Formats Seen</div>
		<div class="admindash-module-stat__value"><?php echo count($formatrows); ?></div>
		<div class="admindash-module-stat__meta">Course formats represented in the current template search results.</div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0">Format Mix</h5>
			<span class="admindash-admin-note">Patterns by build style</span>
		</div>
		<ul class="admindash-admin-list">
			<?php foreach ($formatrows as $row): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo s($row->formatname); ?></span>
					<span class="admindash-admin-list__value"><?php echo (int)$row->coursecount; ?> courses</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>

<div class="admindash-card admindash-admin-panel mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<div>
			<h5 class="mb-1">Template Candidate Board</h5>
			<p class="admindash-admin-note mb-0">Use this table to identify courses that can be standardized into reusable build patterns.</p>
		</div>
	</div>
	<div class="admindash-tablewrap">
		<table class="table table-striped table-hover admindash-admin-table">
			<thead>
				<tr>
					<th>Course</th>
					<th>Category</th>
					<th>Format</th>
					<th>Structure</th>
					<th>Learners</th>
					<th>Signals</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($templatecourses)): ?>
					<tr>
						<td colspan="7" class="text-center py-4">No course candidates matched the current template filters.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($templatecourses as $course): ?>
						<?php
						$signals = [];
						if ((int)$course->visible === 1) {
							$signals[] = '<span class="admindash-admin-badge is-success">Visible</span>';
						}
						if ((int)$course->trackablecount > 0) {
							$signals[] = '<span class="admindash-admin-badge is-success">Tracked</span>';
						}
						if ((int)$course->modulecount >= 5 && (int)$course->sectioncount >= 3) {
							$signals[] = '<span class="admindash-admin-badge is-info">Reusable</span>';
						}
						if ((int)$course->learnercount === 0) {
							$signals[] = '<span class="admindash-admin-badge is-warn">No learners</span>';
						}
						?>
						<tr>
							<td>
								<div class="admindash-admin-user">
									<a href="<?php echo new moodle_url('/course/view.php', ['id' => (int)$course->id]); ?>" class="admindash-admin-user__name"><?php echo s($course->fullname); ?></a>
									<div class="admindash-admin-note"><?php echo s($course->shortname); ?></div>
								</div>
							</td>
							<td><?php echo s($course->categoryname); ?></td>
							<td><?php echo s($course->formatname); ?></td>
							<td><?php echo (int)$course->sectioncount; ?> sections · <?php echo (int)$course->modulecount; ?> modules · <?php echo (int)$course->trackablecount; ?> tracked</td>
							<td><?php echo (int)$course->learnercount; ?></td>
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
</div>

<?php
local_admindashboard_render_footer();