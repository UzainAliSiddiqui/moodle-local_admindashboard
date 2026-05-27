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
declare(strict_types=1);

namespace local_admindashboard\local\hooks\navigation;

/**
 * Hook callbacks for local_admindashboard.
 */
class primary_extend {
    /**
     * Add nodes into site primary navigation.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function callback(\core\hook\navigation\primary_extend $hook): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/admindashboard/lib.php');

        // Reuse the existing callback implementation (and its permission checks).
        \local_admindashboard_extend_navigation_primary($hook->get_primaryview());
    }
}
