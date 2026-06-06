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

local_admindashboard_setup_page('/local/admindashboard/create_course.php', 'Create New Course', 'admintools.courses.create');
local_admindashboard_render_header('admintools.courses.create');

$tabs = local_admindashboard_get_manage_courses_suite_tabs();
$totalcategories = (int)$DB->count_records('course_categories');
$totalcohorts = (int)$DB->count_records('cohort');

$formatrows = $DB->get_records_sql(
	"SELECT COALESCE(NULLIF(c.format, ''), 'topics') AS formatname, COUNT(1) AS coursecount
	   FROM {course} c
	  WHERE c.id > 1
   GROUP BY COALESCE(NULLIF(c.format, ''), 'topics')
   ORDER BY coursecount DESC, formatname ASC",
	[],
	0,
	6
);

$categoryrows = $DB->get_records_sql(
	"SELECT cc.id, cc.name, COUNT(c.id) AS coursecount
	   FROM {course_categories} cc
  LEFT JOIN {course} c ON c.category = cc.id AND c.id > 1
   GROUP BY cc.id, cc.name, cc.path
   ORDER BY coursecount DESC, cc.path ASC",
	[],
	0,
	6
);

$templatecandidates = $DB->get_records_sql(
	"SELECT c.id, c.fullname, COALESCE(cc.name, 'Uncategorised') AS categoryname,
			COALESCE(c.format, 'topics') AS formatname,
			COALESCE(modstats.modulecount, 0) AS modulecount
	   FROM {course} c
  LEFT JOIN {course_categories} cc ON cc.id = c.category
  LEFT JOIN (
			SELECT cm.course, COUNT(1) AS modulecount
			  FROM {course_modules} cm
			  JOIN {modules} m ON m.id = cm.module AND m.name <> 'label'
			 WHERE cm.deletioninprogress = 0
		  GROUP BY cm.course
	   ) modstats ON modstats.course = c.id
	  WHERE c.id > 1
		AND c.visible = 1
		AND COALESCE(modstats.modulecount, 0) >= 5
   ORDER BY modulecount DESC, c.timemodified DESC, c.fullname ASC",
	[],
	0,
	6
);

$requiredsignals = [
	'Course category and audience',
	'Visibility and naming standards',
	'Schedule window and ownership',
	'Completion model and assessment path',
];

local_admindashboard_render_workspace_header(
	'Admin Tools / Manage Courses',
	'Create New Course',
	'Preflight workspace for planning new courses before handing off into Moodle core creation screens.',
	'courses',
	'admintools.courses.create',
	$tabs,
	[
		['label' => 'Open core course admin', 'url' => new moodle_url('/course/management.php'), 'primary' => true],
		['label' => 'Course list', 'url' => new moodle_url('/local/admindashboard/course_list.php'), 'primary' => false],
		['label' => 'Course templates', 'url' => new moodle_url('/local/admindashboard/course_templates.php'), 'primary' => false],
	],
	['Preflight ready', 'Template aware', 'Category guided']
);
?>

<div class="admindash-kpis">
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_create_course_categories', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $totalcategories; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_create_course_available_course_categories_you_can_route_new_courses_into', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_create_course_formats', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo count($formatrows); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_create_course_most_common_course_formats_currently_in_use_across_the_platform', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_create_course_cohorts', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo $totalcohorts; ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_create_course_audience_groups_available_for_enrolment_and_rollout_planning', 'local_admindashboard'); ?></div>
	</div>
	<div class="admindash-card admindash-module-stat">
		<div class="admindash-module-stat__label"><?php echo get_string('ui_create_course_template_candidates', 'local_admindashboard'); ?></div>
		<div class="admindash-module-stat__value"><?php echo count($templatecandidates); ?></div>
		<div class="admindash-module-stat__meta"><?php echo get_string('ui_create_course_existing_structured_courses_that_can_guide_repeatable_builds', 'local_admindashboard'); ?></div>
	</div>
</div>

<div class="admindash-widget-grid mt-3">
	<div class="admindash-card admindash-admin-panel">
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
			<h5 class="mb-0"><?php echo get_string('ui_create_course_top_categories', 'local_admindashboard'); ?></h5>
			<span class="admindash-admin-note"><?php echo get_string('ui_create_course_where_most_builds_live', 'local_admindashboard'); ?></span>
		</div>
		<ul class="admindash-admin-list">
			<?php foreach ($categoryrows as $row): ?>
				<li>
					<span class="admindash-admin-list__label"><?php echo s($row->name); ?></span>
					<span class="admindash-admin-list__value"><?php echo (int)$row->coursecount; ?> <?php echo get_string('ui_create_course_courses', 'local_admindashboard'); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="admindash-admin-stack">
		<div class="admindash-card admindash-admin-panel">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0"><?php echo get_string('ui_create_course_popular_formats', 'local_admindashboard'); ?></h5>
				<span class="admindash-admin-note"><?php echo get_string('ui_create_course_current_platform_defaults', 'local_admindashboard'); ?></span>
			</div>
			<ul class="admindash-admin-list is-tight">
				<?php foreach ($formatrows as $row): ?>
					<li>
						<span class="admindash-admin-list__label"><?php echo s($row->formatname); ?></span>
						<span class="admindash-admin-list__value"><?php echo (int)$row->coursecount; ?> <?php echo get_string('ui_create_course_courses', 'local_admindashboard'); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="admindash-card admindash-admin-panel mt-3">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
				<h5 class="mb-0"><?php echo get_string('ui_create_course_reusable_course_candidates', 'local_admindashboard'); ?></h5>
				<span class="admindash-admin-note"><?php echo get_string('ui_create_course_structured_existing_builds', 'local_admindashboard'); ?></span>
			</div>
			<?php if (!empty($templatecandidates)): ?>
				<ul class="admindash-admin-list is-tight">
					<?php foreach ($templatecandidates as $row): ?>
						<li>
							<span class="admindash-admin-list__label"><?php echo s($row->fullname); ?></span>
							<span class="admindash-admin-list__value"><?php echo s($row->formatname); ?> · <?php echo (int)$row->modulecount; ?> <?php echo get_string('ui_create_course_modules', 'local_admindashboard'); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else: ?>
				<p class="admindash-admin-note mb-0"><?php echo get_string('ui_create_course_no_strong_template_candidates_were_found_yet', 'local_admindashboard'); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php
local_admindashboard_render_footer();