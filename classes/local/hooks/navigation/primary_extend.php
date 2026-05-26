<?php

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
        local_admindashboard_extend_navigation_primary($hook->get_primaryview());
    }
}
