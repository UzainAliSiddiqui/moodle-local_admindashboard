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

local_admindashboard_setup_page('/local/admindashboard/export_center.php', 'Export Center', 'exportcenter');
local_admindashboard_render_header('exportcenter');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));

$meta = local_admindashboard_get_meta($courseid);
$metrics = local_admindashboard_get_metrics($courseid, $department);

$baseparams = [
    'courseid' => $courseid,
    'department' => $department,
    'sesskey' => sesskey(),
];

$csvurl = new moodle_url('/local/admindashboard/export.php', $baseparams + ['format' => 'csv']);
$pdfurl = new moodle_url('/local/admindashboard/export.php', $baseparams + ['format' => 'pdf']);
?>

<h2 class="mb-3"><?php echo get_string('ui_export_center_export_center', 'local_admindashboard'); ?></h2>

<form method="get" class="admindash-filters admindash-card">
    <div class="title"><?php echo get_string('ui_export_center_filters', 'local_admindashboard'); ?></div>

    <label class="mb-0" for="courseSelect"><?php echo get_string('ui_export_center_select_course', 'local_admindashboard'); ?></label>
    <select id="courseSelect" name="courseid" class="form-select" style="max-width:320px">
        <option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
        <?php foreach ($meta['courses'] as $course): ?>
            <option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
                <?php echo s($course['fullname']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="mb-0" for="deptSelect" style="margin-left:12px"><?php echo get_string('ui_export_center_select_department', 'local_admindashboard'); ?></label>
    <select id="deptSelect" name="department" class="form-select" style="max-width:320px">
        <option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
        <?php foreach ($meta['departments'] as $dept): ?>
            <option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                <?php echo s($dept); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_export_center_apply', 'local_admindashboard'); ?></button>
</form>

<div class="admindash-card bg-white p-3 mt-3">
    <h5 class="mb-3"><?php echo get_string('ui_export_center_downloads', 'local_admindashboard'); ?></h5>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="<?php echo $csvurl; ?>">Download CSV</a>
        <a class="btn btn-outline-primary" href="<?php echo $pdfurl; ?>">Download PDF</a>
    </div>
</div>

<div class="admindash-card bg-white p-3 mt-3">
    <h5 class="mb-3"><?php echo get_string('ui_export_center_preview_current_filters', 'local_admindashboard'); ?></h5>
    <div class="row g-3">
        <div class="col-md-3"><b><?php echo get_string('ui_export_center_total_participants', 'local_admindashboard'); ?></b> <?php echo (int)$metrics['participants']; ?></div>
        <div class="col-md-3"><b><?php echo get_string('ui_export_center_passed', 'local_admindashboard'); ?></b> <?php echo (int)($metrics['passed'] ?? 0); ?></div>
        <div class="col-md-3"><b><?php echo get_string('ui_export_center_certified', 'local_admindashboard'); ?></b> <?php echo (int)($metrics['certified'] ?? 0); ?></div>
        <div class="col-md-3"><b><?php echo get_string('ui_export_center_failed', 'local_admindashboard'); ?></b> <?php echo (int)$metrics['failed']; ?></div>
        <div class="col-md-3"><b><?php echo get_string('ui_export_center_dropped_midway', 'local_admindashboard'); ?></b> <?php echo (int)$metrics['dropped_midway']; ?></div>
    </div>
</div>

<?php
local_admindashboard_render_footer();
