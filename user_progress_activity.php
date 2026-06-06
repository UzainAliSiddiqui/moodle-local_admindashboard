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

local_admindashboard_setup_page('/local/admindashboard/user_progress_activity.php', 'Recent Activity Report', 'reports.useractivity');
local_admindashboard_render_header('reports.useractivity');

$department = trim(optional_param('department', '', PARAM_TEXT));
$meta = local_admindashboard_get_meta();

$since = time() - (7 * 24 * 60 * 60);
[$userwhere, $userparams] = local_admindashboard_build_user_filter($department);
$where = "{$userwhere} AND u.lastaccess > :since";
$params = $userparams + ['since' => $since];

$sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.lastaccess
          FROM {user} u
         WHERE {$where}
      ORDER BY u.lastaccess DESC";

$rows = $DB->get_records_sql($sql, $params, 0, 50);

$table = new html_table();
$table->attributes['class'] = 'table table-striped table-hover admindash-card admindash-report-table';
$table->head = ['User', 'Email', 'Department', 'Last access'];
foreach ($rows as $r) {
    $table->data[] = [s(fullname($r)), s($r->email), s($r->department ?? ''), s(userdate($r->lastaccess))];
}
?>

<h2 class="mb-3"><?php echo get_string('ui_user_progress_activity_recent_activity_report', 'local_admindashboard'); ?></h2>

<form method="get" class="admindash-filters admindash-card">
    <div class="title"><?php echo get_string('ui_user_progress_activity_filters', 'local_admindashboard'); ?></div>
    <label class="mb-0" for="deptSelect"><?php echo get_string('ui_user_progress_activity_select_department', 'local_admindashboard'); ?></label>
    <select id="deptSelect" name="department" class="form-select" style="max-width:320px">
        <option value="" <?php echo $department === '' ? 'selected' : ''; ?>>All Departments</option>
        <?php foreach ($meta['departments'] as $dept): ?>
            <option value="<?php echo s($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                <?php echo s($dept); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary" style="margin-left:auto"><?php echo get_string('ui_user_progress_activity_apply', 'local_admindashboard'); ?></button>
</form>

<?php
echo html_writer::tag('div', html_writer::table($table), ['class' => 'mt-3']);

local_admindashboard_render_footer();
