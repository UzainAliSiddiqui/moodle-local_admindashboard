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

namespace local_admindashboard\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for the Admin dashboard local plugin.
 *
 * @package    local_admindashboard
 * @copyright  2026 Uzain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describes personal data stored or sent by the plugin.
     *
     * @param collection $collection The metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_admindashboard_atrisk', [
            'courseid' => 'privacy:metadata:local_admindashboard_atrisk:courseid',
            'userid' => 'privacy:metadata:local_admindashboard_atrisk:userid',
            'riskscore' => 'privacy:metadata:local_admindashboard_atrisk:riskscore',
            'loginrisk' => 'privacy:metadata:local_admindashboard_atrisk:loginrisk',
            'pacingrisk' => 'privacy:metadata:local_admindashboard_atrisk:pacingrisk',
            'pretestrisk' => 'privacy:metadata:local_admindashboard_atrisk:pretestrisk',
            'dayssincelogin' => 'privacy:metadata:local_admindashboard_atrisk:dayssincelogin',
            'completionpct' => 'privacy:metadata:local_admindashboard_atrisk:completionpct',
            'deadlineat' => 'privacy:metadata:local_admindashboard_atrisk:deadlineat',
            'pretestpct' => 'privacy:metadata:local_admindashboard_atrisk:pretestpct',
            'pretestavgpct' => 'privacy:metadata:local_admindashboard_atrisk:pretestavgpct',
            'reasonsjson' => 'privacy:metadata:local_admindashboard_atrisk:reasonsjson',
            'timecreated' => 'privacy:metadata:local_admindashboard_atrisk:timecreated',
            'timemodified' => 'privacy:metadata:local_admindashboard_atrisk:timemodified',
        ], 'privacy:metadata:local_admindashboard_atrisk');

        $collection->add_external_location_link('groq', [
            'prompt' => 'privacy:metadata:groq:prompt',
            'dashboarddata' => 'privacy:metadata:groq:dashboarddata',
            'feedbackcomments' => 'privacy:metadata:groq:feedbackcomments',
        ], 'privacy:metadata:groq');

        return $collection;
    }

    /**
     * Gets course contexts where the user has cached at-risk analytics.
     *
     * @param int $userid The Moodle user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_admindashboard_atrisk} ar ON ar.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextcourse
                   AND ar.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Exports cached at-risk analytics for approved course contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        if (empty($user)) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }

            $records = $DB->get_records('local_admindashboard_atrisk', [
                'courseid' => $context->instanceid,
                'userid' => $user->id,
            ], 'timemodified ASC');

            if (empty($records)) {
                continue;
            }

            $data = [];
            foreach ($records as $record) {
                unset($record->id);
                $data[] = $record;
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_admindashboard'), get_string('privacy:export:atrisk', 'local_admindashboard')],
                (object)['records' => $data]
            );
        }
    }

    /**
     * Deletes all plugin data for a course context.
     *
     * @param context $context The Moodle context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context instanceof context_course) {
            $DB->delete_records('local_admindashboard_atrisk', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Deletes plugin data for one approved user across approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        if (empty($user)) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                $DB->delete_records('local_admindashboard_atrisk', [
                    'courseid' => $context->instanceid,
                    'userid' => $user->id,
                ]);
            }
        }
    }
}
