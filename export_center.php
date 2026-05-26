<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

admindash_setup_page('/local/admindashboard/export_center.php', 'Export Center', 'exportcenter');
admindash_render_header('exportcenter');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));

$meta = admindash_get_meta($courseid);
$metrics = admindash_get_metrics($courseid, $department);

$baseparams = [
    'courseid' => $courseid,
    'department' => $department,
    'sesskey' => sesskey(),
];

$csvurl = new moodle_url('/local/admindashboard/export.php', $baseparams + ['format' => 'csv']);
$pdfurl = new moodle_url('/local/admindashboard/export.php', $baseparams + ['format' => 'pdf']);
?>

<h2 class="mb-3">Export Center</h2>

<form method="get" class="admindash-filters admindash-card">
    <div class="title">Filters</div>

    <label class="mb-0" for="courseSelect">Select Course</label>
    <select id="courseSelect" name="courseid" class="form-select" style="max-width:320px">
        <option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>All Courses</option>
        <?php foreach ($meta['courses'] as $course): ?>
            <option value="<?php echo (int)$course['id']; ?>" <?php echo $courseid === (int)$course['id'] ? 'selected' : ''; ?>>
                <?php echo s($course['fullname']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="mb-0" for="deptSelect" style="margin-left:12px">Select Department</label>
    <select id="deptSelect" name="department" class="form-select" style="max-width:320px">
        <option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
        <?php foreach ($meta['departments'] as $dept): ?>
            <option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                <?php echo s($dept); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
</form>

<div class="admindash-card bg-white p-3 mt-3">
    <h5 class="mb-3">Downloads</h5>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="<?php echo $csvurl; ?>">Download CSV</a>
        <a class="btn btn-outline-primary" href="<?php echo $pdfurl; ?>">Download PDF</a>
    </div>
</div>

<div class="admindash-card bg-white p-3 mt-3">
    <h5 class="mb-3">Preview (Current Filters)</h5>
    <div class="row g-3">
        <div class="col-md-3"><b>Total Participants:</b> <?php echo (int)$metrics['participants']; ?></div>
        <div class="col-md-3"><b>Passed:</b> <?php echo (int)($metrics['passed'] ?? 0); ?></div>
        <div class="col-md-3"><b>Certified:</b> <?php echo (int)($metrics['certified'] ?? 0); ?></div>
        <div class="col-md-3"><b>Failed:</b> <?php echo (int)$metrics['failed']; ?></div>
        <div class="col-md-3"><b>Dropped Midway:</b> <?php echo (int)$metrics['dropped_midway']; ?></div>
    </div>
</div>

<?php
admindash_render_footer();
