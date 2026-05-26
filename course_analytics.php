<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

admindash_setup_page('/local/admindashboard/course_analytics.php', 'Course Analytics', 'courseanalytics.overview');
admindash_render_header('courseanalytics.overview');
?>

<h2 class="mb-3">Course Analytics</h2>

<?php
$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));

$meta = admindash_get_meta($courseid);
$metrics = admindash_get_metrics($courseid, $department);
?>

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

<div class="admindash-kpis">
    <div class="admindash-card admindash-kpi k1">
        <div class="label">Total Participants</div>
        <div class="value"><?php echo (int)$metrics['participants']; ?></div>
        <?php echo $OUTPUT->render_from_template('local_admindashboard/kpi_trend', $metrics['trends']['participants'] ?? []); ?>
    </div>
    <div class="admindash-card admindash-kpi k5">
        <div class="label">Completion Rate</div>
        <div class="value"><?php echo (int)$metrics['completion_rate']; ?>%</div>
    </div>
    <div class="admindash-card admindash-kpi k5">
        <div class="label">Attempted</div>
        <div class="value"><?php echo (int)($metrics['attempted'] ?? 0); ?></div>
        <?php echo $OUTPUT->render_from_template('local_admindashboard/kpi_trend', $metrics['trends']['attempted'] ?? []); ?>
    </div>
    <div class="admindash-card admindash-kpi k2">
        <div class="label">Passed</div>
        <div class="value"><?php echo (int)($metrics['passed'] ?? 0); ?></div>
        <?php echo $OUTPUT->render_from_template('local_admindashboard/kpi_trend', $metrics['trends']['passed'] ?? []); ?>
    </div>
    <div class="admindash-card admindash-kpi k6">
        <div class="label">Certified</div>
        <div class="value"><?php echo (int)($metrics['certified'] ?? 0); ?></div>
        <?php echo $OUTPUT->render_from_template('local_admindashboard/kpi_trend', $metrics['trends']['certified'] ?? []); ?>
    </div>
    <div class="admindash-card admindash-kpi k3">
        <div class="label">Failed</div>
        <div class="value"><?php echo (int)$metrics['failed']; ?></div>
        <?php echo $OUTPUT->render_from_template('local_admindashboard/kpi_trend', $metrics['trends']['failed'] ?? []); ?>
    </div>
    <div class="admindash-card admindash-kpi k4">
        <div class="label">Dropped Midway</div>
        <div class="value"><?php echo (int)$metrics['dropped_midway']; ?></div>
        <?php echo $OUTPUT->render_from_template('local_admindashboard/kpi_trend', $metrics['trends']['dropped_midway'] ?? []); ?>
    </div>
    <div class="admindash-card admindash-kpi k7">
        <div class="label">Pending Modules</div>
        <div class="value"><?php echo (int)$metrics['pending_modules']; ?></div>
    </div>
</div>

<?php if ($courseid > 0): ?>
<?php $heatmap = $metrics['compliance_heatmap'] ?? ['columns' => [], 'rows' => [], 'summary' => []]; ?>
<div class="admindash-card p-3 mt-3">
    <div class="admindash-heatmap-card__header">
        <div>
            <h5 class="mb-1">Compliance Risk Heatmap</h5>
            <div class="text-muted small">
                <?php
                $summary = $heatmap['summary'] ?? [];
                $redcells = (int)($summary['red_cells'] ?? 0);
                $ambercells = (int)($summary['amber_cells'] ?? 0);
                echo s($redcells . ' danger zones and ' . $ambercells . ' watch zones across the selected course.');
                ?>
            </div>
        </div>
        <div class="admindash-heatmap-legend" aria-label="Compliance risk legend">
            <span class="admindash-heatmap-legend__item is-healthy">Safe</span>
            <span class="admindash-heatmap-legend__item is-warning">Watch</span>
            <span class="admindash-heatmap-legend__item is-critical">Danger</span>
        </div>
    </div>

    <?php if (empty($heatmap['columns']) || empty($heatmap['rows'])): ?>
        <div class="text-muted small mt-2"><?php echo s((string)($summary['label'] ?? 'No compliance heatmap data is available for the selected filters.')); ?></div>
    <?php else: ?>
        <?php $heatmapcolumncount = max(1, count($heatmap['columns'] ?? [])); ?>
        <div class="admindash-heatmap" aria-live="polite">
            <div class="admindash-heatmap__grid" style="--admindash-heatmap-col-count:<?php echo (int)$heatmapcolumncount; ?>;">
                <div class="admindash-heatmap__corner">Department</div>
                <div class="admindash-heatmap__columns">
                    <?php foreach (($heatmap['columns'] ?? []) as $column): ?>
                        <div class="admindash-heatmap__columnhead" title="<?php echo s((string)($column['name'] ?? '')); ?>">
                            <span class="admindash-heatmap__columnhead-inner">
                                <span class="admindash-heatmap__columnhead-text"><?php echo s((string)($column['name'] ?? '')); ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="admindash-heatmap__body">
                    <?php foreach (($heatmap['rows'] ?? []) as $row): ?>
                        <div class="admindash-heatmap__row">
                            <div class="admindash-heatmap__rowlabel"><?php echo s((string)($row['department'] ?? '-')); ?></div>
                            <div class="admindash-heatmap__rowcells">
                                <?php foreach (($row['cells'] ?? []) as $index => $cell): ?>
                                    <?php $column = $heatmap['columns'][$index] ?? ['name' => 'Module']; ?>
                                    <div class="admindash-heatmap__cell is-<?php echo s((string)($cell['status'] ?? 'critical')); ?>"
                                         title="<?php echo s((string)($row['department'] ?? '-') . ' • ' . (string)($column['name'] ?? 'Module') . ': ' . (int)($cell['value'] ?? 0) . '% compliant (' . (int)($cell['compliant'] ?? 0) . '/' . (int)($cell['total'] ?? 0) . ')'); ?>">
                                        <div class="admindash-heatmap__value"><?php echo (int)($cell['value'] ?? 0); ?>%</div>
                                        <div class="admindash-heatmap__subvalue"><?php echo s((string)($cell['label'] ?? '0/0')); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<?php echo html_writer::div('Select a course to view the compliance risk heatmap.', 'alert alert-info mt-3'); ?>
<?php endif; ?>

<div class="admindash-card bg-white p-3 mt-3">
    <h5 class="mb-2">Reports</h5>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="<?php echo (new moodle_url('/local/admindashboard/course_analytics.php')); ?>">Overview</a>
        <a class="btn btn-outline-primary" href="<?php echo (new moodle_url('/local/admindashboard/course_analytics_modules.php')); ?>">Modules Report</a>
        <a class="btn btn-outline-primary" href="<?php echo (new moodle_url('/local/admindashboard/sentiment_analyzer.php')); ?>">Sentiment Analyzer</a>
    </div>
</div>

<?php
admindash_render_footer();
