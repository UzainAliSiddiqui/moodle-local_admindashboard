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

local_admindashboard_setup_page('/local/admindashboard/course_analytics_modules.php', 'Course Analytics', 'courseanalytics.modules');
local_admindashboard_render_header('courseanalytics.modules');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$moduleid = optional_param('moduleid', 0, PARAM_INT);

$meta = local_admindashboard_get_meta($courseid);
?>

<h2 class="mb-3">Course Analytics</h2>

<form method="get" class="admindash-filters admindash-card">
    <div class="title">Filters</div>

    <label class="mb-0" for="courseSelect">Select Course</label>
    <select id="courseSelect" name="courseid" class="form-select" style="max-width:360px">
        <option value="0" <?php echo $courseid === 0 ? 'selected' : ''; ?>>Select a course…</option>
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

    <label class="mb-0" for="moduleSelect" style="margin-left:12px">Select Module</label>
    <select id="moduleSelect" name="moduleid" class="form-select" style="max-width:260px">
        <option value="0" <?php echo $moduleid === 0 ? 'selected' : ''; ?>>All Modules</option>
        <?php if (!empty($meta['modulegroups'])): ?>
            <?php foreach ($meta['modulegroups'] as $group): ?>
                <optgroup label="<?php echo s($group->label ?? 'Module'); ?>">
                    <?php foreach (($group->items ?? []) as $m): ?>
                        <option value="<?php echo (int)$m->id; ?>" <?php echo $moduleid === (int)$m->id ? 'selected' : ''; ?>>
                            <?php echo s($m->name); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($meta['modules'] as $m): ?>
                <option value="<?php echo (int)$m->id; ?>" <?php echo $moduleid === (int)$m->id ? 'selected' : ''; ?>>
                    <?php echo s($m->name); ?>
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>

    <button type="submit" class="btn btn-primary" style="margin-left:auto">Apply</button>
</form>

<?php
if ($courseid <= 0) {
    echo html_writer::div('Select a course to view module-level reports.', 'alert alert-info mt-3');
} else {
    [$userwhere, $params] = local_admindashboard_build_user_filter($department);
    $params['courseid'] = $courseid;

    $selectedmodulelabel = 'All Modules';
    if ($moduleid > 0) {
        $cmname = $DB->get_record_sql(
            "SELECT m.name AS modname, cm.id, cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.id = :cmid",
            ['cmid' => $moduleid]
        );
        if ($cmname && (int)$cmname->course === (int)$courseid) {
            $selectedmodulelabel = ucfirst($cmname->modname) . ' (cmid ' . (int)$moduleid . ')';
        } else {
            $selectedmodulelabel = 'Selected module (cmid ' . (int)$moduleid . ')';
        }
    }

    $modwhere = '';
    if ($moduleid > 0) {
        $modwhere = ' AND cm.id = :moduleid';
        $params['moduleid'] = $moduleid;
    }

        // Important: use a DISTINCT enrolled-user list to avoid double-counting users who have multiple enrolments.
        // Moodle's SQL param handling does not allow reusing the same named placeholder multiple times.
          $params['courseid_enrol'] = $courseid;
        $enrolleduserssql = "SELECT DISTINCT u.id
                          FROM {user} u
                          JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_enrol
                         WHERE {$userwhere}";

        $sql = "SELECT m.name AS modname,
                    COUNT(DISTINCT cm.id) AS activities,
                    COUNT(DISTINCT eu.id) AS participants,
                    SUM(CASE WHEN cmc.completionstate IS NOT NULL AND cmc.completionstate > 0 THEN 1 ELSE 0 END) AS completedcompletions,
                    (COUNT(DISTINCT cm.id) * COUNT(DISTINCT eu.id)) AS expectedcompletions
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module AND m.visible = 1
                JOIN {course} c ON c.id = cm.course AND c.id = :courseid AND c.visible = 1
                JOIN ({$enrolleduserssql}) eu ON 1=1
            LEFT JOIN {course_modules_completion} cmc
                              ON cmc.coursemoduleid = cm.id AND cmc.userid = eu.id
               WHERE cm.deletioninprogress = 0
                AND cm.visible = 1
                AND cm.completion > 0
                {$modwhere}
            GROUP BY m.name
            ORDER BY activities DESC";

    $rows = $DB->get_records_sql($sql, $params);

    $table = new html_table();
    $table->attributes['class'] = 'table table-striped table-hover admindash-card admindash-report-table';
    $table->head = ['Module type', 'Activities', 'Participants', 'Completed (activity completions)', 'Expected (participants × activities)'];

    $filtersummary = 'Applied filters: '
        . 'Course ID ' . (int)$courseid
        . ' · ' . ($department !== '' ? ('Department: ' . s($department)) : 'All Departments')
        . ' · ' . s($selectedmodulelabel);
    echo html_writer::div($filtersummary, 'text-muted small mt-3');

    echo html_writer::div(
        'Note: Completed/Expected are counts of completion checks across all activities (so they can be higher than Participants).',
        'text-muted small'
    );

    foreach ($rows as $r) {
        $typelabel = $r->modname;
        // Prefer a human-friendly module name (from language strings) when available.
        try {
            $typelabel = get_string('modulename', $r->modname);
        } catch (Throwable $e) {
            $typelabel = $r->modname;
        }

        // Moodle returns [[identifier]] when the string is missing (no exception), so detect and fall back.
        if (is_string($typelabel) && preg_match('/^\[\[.+\]\]$/', $typelabel)) {
            $fallback = '';
            try {
                $fallback = get_string('pluginname', 'mod_' . $r->modname);
            } catch (Throwable $e) {
                $fallback = '';
            }
            if (is_string($fallback) && $fallback !== '' && !preg_match('/^\[\[.+\]\]$/', $fallback)) {
                $typelabel = $fallback;
            } else {
                $typelabel = ucwords(str_replace('_', ' ', (string)$r->modname));
            }
        }
        $table->data[] = [
            s($typelabel),
            (int)$r->activities,
            (int)$r->participants,
            (int)$r->completedcompletions,
            (int)$r->expectedcompletions,
        ];
    }

    echo html_writer::tag('div', html_writer::table($table), ['class' => 'mt-3']);
}
?>

<?php
local_admindashboard_render_footer();
