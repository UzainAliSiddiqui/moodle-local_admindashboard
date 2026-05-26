<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_admindashboard.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_admindashboard_upgrade(int $oldversion): bool {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026030102) {
        $table = new xmldb_table('local_admindashboard_modulereport');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('course_section_uix', XMLDB_INDEX_UNIQUE, ['courseid', 'sectionid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026030102, 'local', 'admindashboard');
    }

    if ($oldversion < 2026030200) {
        // Ensure new/changed capabilities from db/access.php are applied.
        require_once($CFG->libdir . '/accesslib.php');
        update_capabilities('local_admindashboard');

        upgrade_plugin_savepoint(true, 2026030200, 'local', 'admindashboard');
    }

    if ($oldversion < 2026031901) {
        $table = new xmldb_table('local_admindashboard_atrisk');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('riskscore', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('loginrisk', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('pacingrisk', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('pretestrisk', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('dayssincelogin', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('completionpct', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('deadlineat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('pretestpct', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
        $table->add_field('pretestavgpct', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
        $table->add_field('reasonsjson', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('course_user_uix', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']);
        $table->add_index('course_score_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'riskscore']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026031901, 'local', 'admindashboard');
    }

    if ($oldversion < 2026050200) {
        require_once($CFG->libdir . '/accesslib.php');
        update_capabilities('local_admindashboard');
        upgrade_plugin_savepoint(true, 2026050200, 'local', 'admindashboard');
    }

    return true;
}
