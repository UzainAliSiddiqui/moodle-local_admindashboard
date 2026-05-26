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

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

set_debugging(DEBUG_DEVELOPER, true);
putenv('LOCAL_ADMINDASHBOARD_DEBUG=1');

$logfile = __DIR__ . '/send_module_reports_run.log';
$started = time();

ob_start();

try {
    mtrace('=== run_send_module_reports_and_log.php ===');
    mtrace('Started: ' . date('c', $started));

    if (moodle_needs_upgrading()) {
        throw new runtime_exception('Moodle upgrade pending, cannot run task.');
    }

    $taskclassname = '\\local_admindashboard\\task\\send_module_reports';
    $task = \core\task\manager::get_scheduled_task($taskclassname);
    if (!$task) {
        throw new coding_exception("Task '{$taskclassname}' not found.");
    }

    $task->execute();

    mtrace('Task completed.');
} catch (Throwable $e) {
    mtrace('ERROR: ' . $e->getMessage());
    mtrace($e->getTraceAsString());
} finally {
    $ended = time();
    mtrace('Ended: ' . date('c', $ended));
    mtrace('Duration: ' . ($ended - $started) . 's');

    $output = ob_get_clean();
    file_put_contents($logfile, "\n\n" . $output, FILE_APPEND);
    echo $output;
}
