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

namespace local_admindashboard\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that refreshes cached at-risk participant analytics.
 *
 * @package    local_admindashboard
 * @copyright  2026 Uzain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_at_risk_cache extends \core\task\scheduled_task {

    /**
     * Returns the task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_refresh_at_risk_cache', 'local_admindashboard');
    }

    /**
     * Refreshes the network-wide at-risk participant cache.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/admindashboard/metricslib.php');
        admindash_refresh_at_risk_cache(0);
        set_config('atrisk_net_refresh_ts', time(), 'local_admindashboard');
    }
}
