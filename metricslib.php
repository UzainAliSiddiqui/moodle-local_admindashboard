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
defined('MOODLE_INTERNAL') || die();

/**
 * Returns a cached list of user IDs to exclude from analytics.
 *
 * @return int[]
 */
function local_admindashboard_get_excluded_user_ids(): array {
    global $DB;

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $excluded = [];

    // Site admins.
    if (function_exists('get_admins')) {
        foreach (get_admins() as $admin) {
            $excluded[] = (int)$admin->id;
        }
    }

    // Staff roles. If a role doesn't exist, it simply won't match.
    $excludedroles = ['manager', 'editingteacher', 'teacher', 'coursecreator', 'nonteachingstaff'];
    list($roleinsql, $roleparams) = $DB->get_in_or_equal($excludedroles, SQL_PARAMS_NAMED, 'exrole');
    $rolesql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE r.shortname {$roleinsql}";
    $excluded = array_merge($excluded, $DB->get_fieldset_sql($rolesql, $roleparams));

    // Explicit test user: Firstname=ZMT and Lastname=Student.
    $testusersql = "SELECT u.id
                      FROM {user} u
                     WHERE u.deleted = 0
                       AND LOWER(u.firstname) = :zmtfn
                       AND LOWER(u.lastname) = :zmtln";
    $excluded = array_merge($excluded, $DB->get_fieldset_sql($testusersql, ['zmtfn' => 'zmt', 'zmtln' => 'student']));

    $excluded = array_values(array_unique(array_map('intval', $excluded)));
    $cache = $excluded;
    return $cache;
}

/**
 * True when the at-risk cache table is present (install/upgrade has been applied).
 *
 * @return bool
 */
function local_admindashboard_local_atrisk_table_exists(): bool {
    global $CFG, $DB;

    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    require_once($CFG->libdir . '/xmldb/xmldb_table.php');
    $exists = $DB->get_manager()->table_exists(new xmldb_table('local_admindashboard_atrisk'));
    return $exists;
}

/**
 * Builds a base user filter for analytics.
 *
 * @param string $department Optional department filter.
 * @param bool $includeexcludedusers When true, keeps staff/admin users in the result set.
 * @return array{0:string,1:array} tuple of (where sql, params)
 */
function local_admindashboard_build_user_filter(string $department = '', bool $includeexcludedusers = false): array {
    global $DB;

    $department = trim($department);

    $where = "u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0 AND u.username <> 'guest'";
    $params = [];

    if ($department !== '') {
        $where .= ' AND u.department = :department';
        $params['department'] = $department;
    }

    // Exclude common test/demo usernames.
    $where .= ' AND u.username NOT LIKE :testuser AND u.username NOT LIKE :demouser';
    $params['testuser'] = '%test%';
    $params['demouser'] = '%demo%';

    if (!$includeexcludedusers) {
        $excludeduserids = local_admindashboard_get_excluded_user_ids();
        if (!empty($excludeduserids)) {
            list($exsql, $exparams) = $DB->get_in_or_equal($excludeduserids, SQL_PARAMS_NAMED, 'exuid', false);
            $where .= " AND u.id {$exsql}";
            $params += $exparams;
        }
    }

    return [$where, $params];
}

/**
 * Normalises activity names so "Module 2 Pre-Test" and "Module 1 Pre-Test" can be compared.
 *
 * @param string $name Raw module name.
 * @return string
 */
function local_admindashboard_normalize_module_name_for_trend(string $name): string {
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $normalized = preg_replace('/module\s*\d+/i', '', $normalized);
    $normalized = preg_replace('/\bweek\s*\d+\b/i', '', $normalized);
    $normalized = preg_replace('/\s+/', ' ', (string)$normalized);
    $normalized = preg_replace('/[^\pL\pN\s-]+/u', '', (string)$normalized);
    return trim((string)$normalized);
}

/**
 * Builds a render-ready KPI trend view model.
 *
 * @param string $metrickey Logical metric key.
 * @param int $currentvalue Current KPI value.
 * @param int $previousvalue Baseline KPI value.
 * @param string $comparisonlabel Human-readable baseline label.
 * @return array{metric:string,supported:bool,current_period_count:int,previous_period_count:int,delta:int,change_percent:?float,direction:string,arrow:string,display_value:string,comparison_label:string,css_class:string,is_new:bool}
 */
function local_admindashboard_build_kpi_trend_view(string $metrickey, int $currentvalue, int $previousvalue, string $comparisonlabel): array {
    $delta = $currentvalue - $previousvalue;
    $changepercent = null;
    $direction = 'flat';
    $arrow = '→';
    $cssclass = 'is-flat';
    $displayvalue = '0%';
    $isnew = false;

    if ($previousvalue === 0) {
        if ($currentvalue > 0) {
            $direction = 'up';
            $arrow = '↑';
            $cssclass = 'is-up is-new';
            $displayvalue = 'New';
            $isnew = true;
        } else {
            $changepercent = 0.0;
        }
    } else if ($delta > 0) {
        $changepercent = round(($delta / $previousvalue) * 100, 1);
        $direction = 'up';
        $arrow = '↑';
        $cssclass = 'is-up';
        $displayvalue = rtrim(rtrim(number_format(abs($changepercent), 1, '.', ''), '0'), '.') . '%';
    } else if ($delta < 0) {
        $changepercent = round(($delta / $previousvalue) * 100, 1);
        $direction = 'down';
        $arrow = '↓';
        $cssclass = 'is-down';
        $displayvalue = rtrim(rtrim(number_format(abs($changepercent), 1, '.', ''), '0'), '.') . '%';
    } else {
        $changepercent = 0.0;
    }

    return [
        'metric' => $metrickey,
        'supported' => true,
        'current_period_count' => $currentvalue,
        'previous_period_count' => $previousvalue,
        'delta' => $delta,
        'change_percent' => $changepercent,
        'direction' => $direction,
        'arrow' => $arrow,
        'display_value' => $displayvalue,
        'comparison_label' => $comparisonlabel,
        'css_class' => $cssclass,
        'is_new' => $isnew,
    ];
}

/**
 * Builds a neutral KPI trend state when no comparable baseline exists.
 *
 * @param string $metrickey Logical metric key.
 * @param string $comparisonlabel Label to show under the badge.
 * @return array{metric:string,supported:bool,current_period_count:int,previous_period_count:int,delta:int,change_percent:?float,direction:string,arrow:string,display_value:string,comparison_label:string,css_class:string,is_new:bool}
 */
function local_admindashboard_build_kpi_no_previous_data_view(string $metrickey, string $comparisonlabel = 'No previous data'): array {
    return [
        'metric' => $metrickey,
        'supported' => true,
        'current_period_count' => 0,
        'previous_period_count' => 0,
        'delta' => 0,
        'change_percent' => null,
        'direction' => 'flat',
        'arrow' => '→',
        'display_value' => 'N/A',
        'comparison_label' => $comparisonlabel,
        'css_class' => 'is-flat is-empty',
        'is_new' => false,
    ];
}

/**
 * Returns the previous visible course in site sort order.
 *
 * @param int $courseid Current course ID.
 * @return int Previous course ID, or 0 when unavailable.
 */
function local_admindashboard_get_previous_course_id(int $courseid): int {
    global $DB;

    if ($courseid <= 0) {
        return 0;
    }

    $courses = $DB->get_records_select('course', 'id > 1 AND visible = 1', null, 'sortorder ASC, id ASC', 'id');
    $previd = 0;
    foreach ($courses as $course) {
        $thisid = (int)$course->id;
        if ($thisid === $courseid) {
            return $previd;
        }
        $previd = $thisid;
    }

    return 0;
}

/**
 * Finds the previous year's iteration of a course based on a trailing 4-digit year.
 *
 * Examples:
 * - "Basics of Pharmacology & Drug Dispensing 2026" -> "Basics of Pharmacology & Drug Dispensing 2025"
 *
 * @param int $courseid Current course ID.
 * @return array{found:bool,courseid:int,year:?int,fullname:string,comparison_label:string}
 */
function local_admindashboard_get_previous_year_course_match(int $courseid): array {
    global $DB;

    $empty = [
        'found' => false,
        'courseid' => 0,
        'year' => null,
        'fullname' => '',
        'comparison_label' => 'No previous data',
    ];

    if ($courseid <= 0) {
        return $empty;
    }

    $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', IGNORE_MISSING);
    if (!$course || empty($course->fullname)) {
        return $empty;
    }

    $fullname = trim((string)$course->fullname);
    if (!preg_match('/^(.*?)\s+(\d{4})$/', $fullname, $matches)) {
        return $empty;
    }

    $basename = trim((string)$matches[1]);
    $currentyear = (int)$matches[2];
    if ($basename === '' || $currentyear <= 0) {
        return $empty;
    }

    $previousyear = $currentyear - 1;
    $previousfullname = $basename . ' ' . $previousyear;
    $previouscourse = $DB->get_record_select(
        'course',
        'id > 1 AND visible = 1 AND fullname = :fullname',
        ['fullname' => $previousfullname],
        'id, fullname',
        IGNORE_MISSING
    );

    if (!$previouscourse) {
        $empty['comparison_label'] = 'No ' . $previousyear . ' course';
        return $empty;
    }

    return [
        'found' => true,
        'courseid' => (int)$previouscourse->id,
        'year' => $previousyear,
        'fullname' => (string)$previouscourse->fullname,
        'comparison_label' => 'vs ' . $previousyear . ' course',
    ];
}

/**
 * Finds the most relevant previous course module for trend comparison.
 *
 * Preference order:
 * 1. Same activity type in the previous section with a matching normalized name.
 * 2. Same activity type in the previous section.
 * 3. The immediately previous visible non-label activity in the course sequence.
 *
 * @param int $courseid Selected course ID.
 * @param int $moduleid Current course-module ID.
 * @return int Previous comparable course-module ID, or 0 when unavailable.
 */
function local_admindashboard_get_previous_module_id(int $courseid, int $moduleid): int {
    global $CFG, $DB;

    if ($courseid <= 0 || $moduleid <= 0) {
        return 0;
    }

    require_once($CFG->dirroot . '/course/lib.php');

    $modinfo = get_fast_modinfo($courseid);
    if (empty($modinfo->cms[$moduleid])) {
        return 0;
    }

    $currentcm = $modinfo->cms[$moduleid];
    $currentsection = (int)($currentcm->sectionnum ?? 0);
    $currentmodname = (string)($currentcm->modname ?? '');
    $currentname = local_admindashboard_normalize_module_name_for_trend((string)($currentcm->name ?? ''));

    $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC', 'id,section,sequence');
    $ordered = [];
    foreach ($sections as $section) {
        $sectionnum = (int)($section->section ?? 0);
        $sequence = trim((string)($section->sequence ?? ''));
        if ($sequence === '') {
            continue;
        }

        foreach (explode(',', $sequence) as $rawcmid) {
            $cmid = (int)$rawcmid;
            if ($cmid <= 0 || empty($modinfo->cms[$cmid])) {
                continue;
            }

            $cm = $modinfo->cms[$cmid];
            if (!empty($cm->deletioninprogress) || empty($cm->visible) || empty($cm->visibleoncoursepage)) {
                continue;
            }
            if ((string)$cm->modname === 'label') {
                continue;
            }

            $ordered[] = [
                'id' => $cmid,
                'sectionnum' => $sectionnum,
                'modname' => (string)$cm->modname,
                'name' => local_admindashboard_normalize_module_name_for_trend((string)($cm->name ?? '')),
            ];
        }
    }

    $currentindex = -1;
    foreach ($ordered as $index => $row) {
        if ((int)$row['id'] === $moduleid) {
            $currentindex = $index;
            break;
        }
    }

    if ($currentindex < 0) {
        return 0;
    }

    for ($section = $currentsection - 1; $section >= 0; $section--) {
        $sectionrows = array_values(array_filter($ordered, static function(array $row) use ($section): bool {
            return (int)$row['sectionnum'] === $section;
        }));
        if (empty($sectionrows)) {
            continue;
        }

        foreach ($sectionrows as $row) {
            if ((string)$row['modname'] === $currentmodname && $currentname !== '' && (string)$row['name'] === $currentname) {
                return (int)$row['id'];
            }
        }

        foreach ($sectionrows as $row) {
            if ((string)$row['modname'] === $currentmodname) {
                return (int)$row['id'];
            }
        }
    }

    if ($currentindex > 0) {
        return (int)$ordered[$currentindex - 1]['id'];
    }

    return 0;
}

/**
 * Extracts the numeric KPI value used by trend badges.
 *
 * @param array $metrics Metrics payload from local_admindashboard_get_metrics().
 * @param string $metrickey KPI key.
 * @return int
 */
function local_admindashboard_get_metric_value_for_trend(array $metrics, string $metrickey): int {
    switch ($metrickey) {
        case 'participants':
            return (int)($metrics['participants'] ?? 0);
        case 'attempted':
            return (int)($metrics['attempted'] ?? 0);
        case 'passed':
            return (int)($metrics['passed'] ?? 0);
        case 'certified':
            return (int)($metrics['certified'] ?? 0);
        case 'failed':
            return (int)($metrics['failed'] ?? 0);
        case 'dropped_midway':
            return (int)($metrics['dropped_midway'] ?? 0);
        case 'not_attempted':
            return (int)($metrics['not_attempted'] ?? $metrics['dropped_midway'] ?? 0);
    }

    return 0;
}

/**
 * Builds trend badges for all dashboard KPIs.
 *
 * When a specific module is selected, comparison uses the previous module in the course sequence.
 * Otherwise, a selected course compares against the previous visible course in sort order.
 * Site-wide fallback uses the previous 30-day participant trend and neutral badges for other KPIs.
 *
 * @param int $courseid Selected course ID.
 * @param string $department Optional department filter.
 * @param int $moduleid Selected course-module ID.
 * @param array $currentmetrics Current metrics payload.
 * @return array<string,array>
 */
function local_admindashboard_get_kpi_trends(int $courseid, string $department, int $moduleid, array $currentmetrics): array {
    $metrickeys = ['participants', 'attempted', 'passed', 'certified', 'failed', 'dropped_midway', 'not_attempted'];
    $comparisonlabel = 'vs previous 30 days';
    $baseline = [];
    $hasbaseline = false;

    if ($courseid > 0 && $moduleid > 0) {
        $previousmoduleid = local_admindashboard_get_previous_module_id($courseid, $moduleid);
        $comparisonlabel = 'vs previous module';
        if ($previousmoduleid > 0) {
            $baseline = local_admindashboard_get_metrics($courseid, $department, $previousmoduleid, false);
            $hasbaseline = true;
        }
    } else if ($courseid > 0) {
        $previousyearcourse = local_admindashboard_get_previous_year_course_match($courseid);
        $comparisonlabel = (string)$previousyearcourse['comparison_label'];
        if (!empty($previousyearcourse['found']) && !empty($previousyearcourse['courseid'])) {
            $baseline = local_admindashboard_get_metrics((int)$previousyearcourse['courseid'], $department, 0, false);
            $hasbaseline = true;
        }
    }

    $trends = [];
    foreach ($metrickeys as $metrickey) {
        $currentvalue = local_admindashboard_get_metric_value_for_trend($currentmetrics, $metrickey);
        $previousvalue = local_admindashboard_get_metric_value_for_trend($baseline, $metrickey);
        $label = $comparisonlabel;

        if ($courseid <= 0 && $moduleid <= 0) {
            $label = 'vs previous 30 days';
            if ($metrickey === 'participants') {
                $participanttrend = local_admindashboard_build_kpi_trend_view(
                    'participants',
                    local_admindashboard_get_metric_value_for_trend($currentmetrics, 'participants'),
                    0,
                    $label
                );
                $participanttrend['supported'] = true;
                $participanttrend['css_class'] = 'is-flat';
                $participanttrend['arrow'] = '→';
                $participanttrend['display_value'] = '0%';
                $participanttrend['comparison_label'] = $label;
                $participanttrend['previous_period_count'] = 0;
                $participanttrend['delta'] = 0;
                $participanttrend['change_percent'] = 0.0;
                $participanttrend['is_new'] = false;
                $trends[$metrickey] = $participanttrend;
                continue;
            }
            $trends[$metrickey] = local_admindashboard_build_kpi_trend_view($metrickey, $currentvalue, $currentvalue, $label);
            continue;
        }

        if (!$hasbaseline) {
            $trends[$metrickey] = local_admindashboard_build_kpi_no_previous_data_view($metrickey, $label);
            $trends[$metrickey]['current_period_count'] = $currentvalue;
            continue;
        }

        $trends[$metrickey] = local_admindashboard_build_kpi_trend_view($metrickey, $currentvalue, $previousvalue, $label);
    }

    return $trends;
}

/**
 * Returns dropdown metadata.
 *
 * @return array{courses: array<int, array{id:int, fullname:string}>, departments: array<int, string>}
 */
function local_admindashboard_get_meta(int $courseid = 0): array {
    global $CFG, $DB;

    $courses = $DB->get_records_select('course', 'id > 1 AND visible = 1', null, 'fullname ASC', 'id, fullname');
    $courselist = [];
    foreach ($courses as $course) {
        $courselist[] = ['id' => (int)$course->id, 'fullname' => $course->fullname];
    }

        $deptparams = [];
        $deptsql = "SELECT DISTINCT u.department
                                    FROM {user} u
                                 WHERE u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0 AND u.department <> ''
                                 ORDER BY u.department";

        if ($courseid > 0) {
                $deptsql = "SELECT DISTINCT u.department
                                            FROM {user} u
                                            JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                                            JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                                         WHERE u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0
                                             AND u.username <> 'guest'
                                             AND u.department <> ''
                                         ORDER BY u.department";
                $deptparams = ['courseid' => $courseid];
        }

        $departments = $DB->get_records_sql($deptsql, $deptparams);
    $deptlist = [];
    foreach ($departments as $row) {
        $deptlist[] = $row->department;
    }

    $modules = [];
    $modulegroups = [];
    if ($courseid > 0) {
        require_once($CFG->dirroot . '/course/lib.php');
        $modinfo = get_fast_modinfo($courseid);
        $sections = $modinfo->get_section_info_all();
        foreach ($sections as $sectionnum => $sectioninfo) {
            $cmids = $modinfo->sections[$sectionnum] ?? [];
            if (empty($cmids)) {
                continue;
            }

            $sectionlabel = ($sectionnum > 0) ? ('Module ' . $sectionnum) : 'General';
            $groupitems = [];

            foreach ($cmids as $cmid) {
                if (empty($modinfo->cms[$cmid])) {
                    continue;
                }

                $cm = $modinfo->cms[$cmid];
                if (!empty($cm->deletioninprogress)) {
                    continue;
                }
                if ($cm->modname === 'label') {
                    continue;
                }
                if (empty($cm->visibleoncoursepage)) {
                    continue;
                }
                if (empty($cm->visible)) {
                    continue;
                }

                $name = format_string($cm->name, true, ['context' => $cm->context]);
                $label = ucfirst($cm->modname) . ': ' . $name;
                $item = (object)[
                    'id' => (int)$cm->id,
                    'name' => $label,
                ];
                $groupitems[] = $item;
                $modules[] = $item;
            }

            if (!empty($groupitems)) {
                $modulegroups[] = (object)[
                    'label' => $sectionlabel,
                    'items' => $groupitems,
                ];
            }
        }
    } else {
        // Course-dependent list only.
        $modules = [];
        $modulegroups = [];
    }

    return [
        'courses' => $courselist,
        'departments' => $deptlist,
        'modules' => $modules,
        'modulegroups' => $modulegroups,
    ];
}

/**
 * Returns visible pre-test quiz grade items for a course.
 *
 * @param int $courseid Course ID.
 * @return array<int,stdClass>
 */
function local_admindashboard_get_at_risk_pretest_items(int $courseid): array {
    global $DB;

    if ($courseid <= 0) {
        return [];
    }

    $sql = "SELECT q.id AS quizid,
                   q.name AS quizname,
                   gi.id AS gradeitemid,
                   gi.grademax
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
              JOIN {quiz} q ON q.id = cm.instance
              JOIN {grade_items} gi
                   ON gi.courseid = cm.course
                  AND gi.itemtype = 'mod'
                  AND gi.itemmodule = 'quiz'
                  AND gi.iteminstance = q.id
                 AND gi.itemnumber = 0
             WHERE cm.course = :courseid
               AND cm.deletioninprogress = 0
               AND cm.visible = 1
               AND gi.grademax > 0
               AND (LOWER(q.name) LIKE :pretest1 OR LOWER(q.name) LIKE :pretest2)
          ORDER BY cm.id ASC";

    return $DB->get_records_sql($sql, [
        'courseid' => $courseid,
        'pretest1' => '%pre%test%',
        'pretest2' => '%pretest%',
    ]);
}

/**
 * Finds the clinic custom profile field ID used by reports.
 *
 * @return int
 */
function local_admindashboard_get_clinic_field_id(): int {
    global $DB;

    static $clinicfieldid = null;
    if ($clinicfieldid !== null) {
        return $clinicfieldid;
    }

    $clinicfieldid = 0;
    $clinicshortnames = ['clinicname', 'clinic_name', 'clinic', 'cn', 'profile_field_cn'];
    try {
        $clinicshortnameslower = array_values(array_unique(array_map('strtolower', $clinicshortnames)));
        $extra = [];
        foreach ($clinicshortnameslower as $sn) {
            if (str_starts_with($sn, 'profile_field_')) {
                $extra[] = substr($sn, strlen('profile_field_'));
            }
        }
        if (!empty($extra)) {
            $clinicshortnameslower = array_values(array_unique(array_merge($clinicshortnameslower, $extra)));
        }

        list($insql, $inparams) = $DB->get_in_or_equal($clinicshortnameslower, SQL_PARAMS_NAMED, 'clinicshort');
        $clinicfields = $DB->get_records_sql(
            "SELECT id, shortname
               FROM {user_info_field}
              WHERE LOWER(shortname) {$insql}",
            $inparams
        );

        if (!empty($clinicfields)) {
            $byshortname = [];
            foreach ($clinicfields as $field) {
                $byshortname[strtolower((string)$field->shortname)] = (int)$field->id;
            }
            foreach ($clinicshortnameslower as $sn) {
                if (!empty($byshortname[$sn])) {
                    $clinicfieldid = (int)$byshortname[$sn];
                    break;
                }
            }
        }
    } catch (Exception $e) {
        $clinicfieldid = 0;
    }

    return $clinicfieldid;
}

/**
 * Finds the gender custom profile field ID used by reports.
 *
 * @return int
 */
function local_admindashboard_get_gender_field_id(): int {
    global $DB;

    static $genderfieldid = null;
    if ($genderfieldid !== null) {
        return $genderfieldid;
    }

    $genderfieldid = 0;
    $gendershortnames = ['gender', 'sex', 'profile_field_gender'];
    try {
        $gendershortnameslower = array_values(array_unique(array_map('strtolower', $gendershortnames)));
        $extra = [];
        foreach ($gendershortnameslower as $sn) {
            if (str_starts_with($sn, 'profile_field_')) {
                $extra[] = substr($sn, strlen('profile_field_'));
            }
        }
        if (!empty($extra)) {
            $gendershortnameslower = array_values(array_unique(array_merge($gendershortnameslower, $extra)));
        }

        list($insql, $inparams) = $DB->get_in_or_equal($gendershortnameslower, SQL_PARAMS_NAMED, 'gendershort');
        $genderfields = $DB->get_records_sql(
            "SELECT id, shortname
               FROM {user_info_field}
              WHERE LOWER(shortname) {$insql}",
            $inparams
        );

        if (!empty($genderfields)) {
            $byshortname = [];
            foreach ($genderfields as $field) {
                $byshortname[strtolower((string)$field->shortname)] = (int)$field->id;
            }
            foreach ($gendershortnameslower as $sn) {
                if (!empty($byshortname[$sn])) {
                    $genderfieldid = (int)$byshortname[$sn];
                    break;
                }
            }
        }
    } catch (Exception $e) {
        $genderfieldid = 0;
    }

    return $genderfieldid;
}

/**
 * Parse a multichoicerated presentation definition into stored answer index => score map.
 *
 * @param string $presentation Presentation string from feedback_item.
 * @return array<int, array{score:float,label:string}>
 */
function local_admindashboard_parse_multichoicerated_presentation(string $presentation): array {
    global $CFG;

    static $loaded = false;
    if (!$loaded) {
        require_once($CFG->dirroot . '/mod/feedback/item/multichoicerated/lib.php');
        $loaded = true;
    }

    $items = [];
    $lines = explode(FEEDBACK_MULTICHOICERATED_LINE_SEP, trim($presentation));
    $index = 1;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            $index++;
            continue;
        }

        $parts = explode(FEEDBACK_MULTICHOICERATED_VALUE_SEP, $line, 2);
        if (count($parts) !== 2 || !is_numeric(trim($parts[0]))) {
            $index++;
            continue;
        }

        $items[$index] = [
            'score' => (float)trim($parts[0]),
            'label' => trim($parts[1]),
        ];
        $index++;
    }

    return $items;
}

/**
 * Infer whether a multichoice option label is positive or negative.
 *
 * @param string $label Option label.
 * @return int Positive values mean positive sentiment, negative values mean negative sentiment.
 */
function local_admindashboard_feedback_option_polarity(string $label): int {
    $normalized = trim(html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5));
    if ($normalized === '') {
        return 0;
    }

    $normalized = function_exists('mb_strtolower') ? mb_strtolower($normalized) : strtolower($normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    if (!is_string($normalized) || $normalized === '') {
        return 0;
    }

    $positivephrases = [
        'strongly agree',
        'agree',
        'excellent',
        'very satisfied',
        'satisfied',
        'always',
        'useful',
        'helpful',
        'easy',
        'clear',
        'relevant',
        'good',
        'very good',
    ];
    $negativephrases = [
        'strongly disagree',
        'disagree',
        'very dissatisfied',
        'dissatisfied',
        'poor',
        'very poor',
        'difficult',
        'unclear',
        'irrelevant',
        'bad',
        'never',
    ];

    foreach ($positivephrases as $phrase) {
        if (strpos($normalized, $phrase) !== false) {
            return 1;
        }
    }

    foreach ($negativephrases as $phrase) {
        if (strpos($normalized, $phrase) !== false) {
            return -1;
        }
    }

    return 0;
}

/**
 * Parse 5-point multichoice Likert options into a numeric scale.
 *
 * @param string $presentation Presentation string from feedback_item.
 * @return array<int, array{score:float,label:string}>
 */
function local_admindashboard_parse_multichoice_likert_presentation(string $presentation): array {
    global $CFG;

    static $loaded = false;
    if (!$loaded) {
        require_once($CFG->dirroot . '/mod/feedback/item/multichoice/lib.php');
        $loaded = true;
    }

    $parts = explode(FEEDBACK_MULTICHOICE_TYPE_SEP, $presentation, 2);
    $subtype = trim((string)($parts[0] ?? 'r'));
    $optionsblob = (string)($parts[1] ?? $presentation);
    if ($subtype !== 'd') {
        $adjustparts = explode(FEEDBACK_MULTICHOICE_ADJUST_SEP, $optionsblob, 2);
        $optionsblob = (string)($adjustparts[0] ?? '');
    }

    $rawoptions = array_values(array_filter(array_map('trim', explode(FEEDBACK_MULTICHOICE_LINE_SEP, $optionsblob)), static function(string $option): bool {
        return $option !== '';
    }));
    if (count($rawoptions) !== 5) {
        return [];
    }

    $firstpolarity = local_admindashboard_feedback_option_polarity($rawoptions[0]);
    $lastpolarity = local_admindashboard_feedback_option_polarity($rawoptions[4]);
    if ($firstpolarity === 0 && $lastpolarity === 0) {
        return [];
    }

    $descending = $firstpolarity > $lastpolarity;
    $options = [];
    foreach ($rawoptions as $index => $label) {
        $position = $index + 1;
        $options[$position] = [
            'score' => $descending ? (float)(6 - $position) : (float)$position,
            'label' => $label,
        ];
    }

    return $options;
}

/**
 * Parse supported Moodle feedback quantitative item types into a numeric scale.
 *
 * @param string $itemtype Feedback item type.
 * @param string $presentation Presentation string from feedback_item.
 * @return array<int, array{score:float,label:string}>
 */
function local_admindashboard_parse_feedback_scale_options(string $itemtype, string $presentation): array {
    if ($itemtype === 'multichoicerated') {
        return local_admindashboard_parse_multichoicerated_presentation($presentation);
    }

    if ($itemtype === 'multichoice') {
        return local_admindashboard_parse_multichoice_likert_presentation($presentation);
    }

    return [];
}

/**
 * Calculate average rated feedback scores for a specific course.
 *
 * @param int $courseid Course ID.
 * @return array{questions:array<string,array{itemid:int,question:string,avg_score:float,max_score:float,response_count:int}>,overall_average:?float,question_count:int}
 */
function local_admindashboard_get_quantitative_feedback_averages(int $courseid): array {
    global $DB;

    $result = [
        'questions' => [],
        'overall_average' => null,
        'question_count' => 0,
    ];

    if ($courseid <= 0) {
        return $result;
    }

    [$typesql, $typeparams] = $DB->get_in_or_equal(['multichoicerated', 'multichoice'], SQL_PARAMS_NAMED, 'fbqtype');

    $sql = "SELECT fi.id AS itemid,
                   fi.typ AS itemtype,
                   fi.name,
                   fi.label,
                   fi.presentation,
                   fi.position,
                   fv.value AS rawvalue
              FROM {feedback} f
              JOIN {feedback_item} fi ON fi.feedback = f.id AND fi.typ {$typesql}
              JOIN {feedback_completed} fc ON fc.feedback = f.id
              JOIN {feedback_value} fv ON fv.completed = fc.id AND fv.item = fi.id
             WHERE f.course = :courseid
               AND fv.value <> ''
          ORDER BY fi.position ASC, fv.id ASC";

    $totals = [];
    $recordset = $DB->get_recordset_sql($sql, $typeparams + ['courseid' => $courseid]);
    foreach ($recordset as $row) {
        $options = local_admindashboard_parse_feedback_scale_options((string)$row->itemtype, (string)$row->presentation);
        if (count($options) !== 5) {
            continue;
        }

        $selectedindex = (int)trim((string)$row->rawvalue);
        if (!isset($options[$selectedindex])) {
            continue;
        }

        if ((string)$row->itemtype === 'multichoice') {
            $correctedscore = (float)(6 - $selectedindex);
        } else {
            $correctedscore = (float)$options[$selectedindex]['score'];
        }

        $question = trim((string)$row->label) !== '' ? trim((string)$row->label) : trim((string)$row->name);
        if ($question === '') {
            $question = 'Question ' . (int)$row->itemid;
        }

        $key = 'item_' . (int)$row->itemid;
        if (!isset($totals[$key])) {
            $scores = array_map(function(array $option): float {
                return (float)$option['score'];
            }, $options);
            $totals[$key] = [
                'itemid' => (int)$row->itemid,
                'question' => $question,
                'score_sum' => 0.0,
                'response_count' => 0,
                'max_score' => ((string)$row->itemtype === 'multichoice') ? 5.0 : (!empty($scores) ? max($scores) : 5.0),
            ];
        }

        $totals[$key]['score_sum'] += $correctedscore;
        $totals[$key]['response_count']++;
    }
    $recordset->close();

    $overallsum = 0.0;
    $overallcount = 0;
    foreach ($totals as $key => $row) {
        if (empty($row['response_count'])) {
            continue;
        }

        $avgscore = round($row['score_sum'] / $row['response_count'], 2);
        $result['questions'][$key] = [
            'itemid' => (int)$row['itemid'],
            'question' => (string)$row['question'],
            'avg_score' => $avgscore,
            'max_score' => (float)$row['max_score'],
            'response_count' => (int)$row['response_count'],
        ];
        $overallsum += $avgscore;
        $overallcount++;
    }

    if ($overallcount > 0) {
        $result['overall_average'] = round($overallsum / $overallcount, 2);
        $result['question_count'] = $overallcount;
    }

    return $result;
}

/**
 * Retrieve free-text comments from Moodle feedback activities in a course.
 *
 * @param int $courseid Course ID.
 * @return array<int,string>
 */
function local_admindashboard_get_feedback_comments(int $courseid): array {
    global $DB;

    if ($courseid <= 0) {
        return [];
    }

    $sql = "SELECT fv.value
              FROM {feedback} f
              JOIN {feedback_item} fi ON fi.feedback = f.id AND fi.typ IN (:textfield, :textarea)
              JOIN {feedback_completed} fc ON fc.feedback = f.id
              JOIN {feedback_value} fv ON fv.completed = fc.id AND fv.item = fi.id
             WHERE f.course = :courseid
               AND fv.value <> ''";

    $recordset = $DB->get_recordset_sql($sql, [
        'textfield' => 'textfield',
        'textarea' => 'textarea',
        'courseid' => $courseid,
    ]);

    $comments = [];
    foreach ($recordset as $row) {
        $comment = trim((string)$row->value);
        if ($comment !== '') {
            $comments[] = $comment;
        }
    }
    $recordset->close();

    return array_values(array_unique($comments));
}

/**
 * Normalize Groq sentiment output into a stable structure.
 *
 * @param array $payload Raw decoded JSON.
 * @return array{sentiment_split:array{positive_pct:int,neutral_pct:int,negative_pct:int},trending_keywords:array<int,string>,flagged_comments:array<int,array{text:string,sentiment:string}>,error:?string}
 */
function local_admindashboard_normalize_feedback_sentiment_payload(array $payload): array {
    $result = [
        'sentiment_split' => [
            'positive_pct' => 0,
            'neutral_pct' => 0,
            'negative_pct' => 0,
        ],
        'trending_keywords' => [],
        'flagged_comments' => [],
        'error' => null,
    ];

    if (!empty($payload['sentiment_split']) && is_array($payload['sentiment_split'])) {
        $result['sentiment_split']['positive_pct'] = max(0, min(100, (int)($payload['sentiment_split']['positive_pct'] ?? 0)));
        $result['sentiment_split']['neutral_pct'] = max(0, min(100, (int)($payload['sentiment_split']['neutral_pct'] ?? 0)));
        $result['sentiment_split']['negative_pct'] = max(0, min(100, (int)($payload['sentiment_split']['negative_pct'] ?? 0)));
    }

    if (!empty($payload['trending_keywords']) && is_array($payload['trending_keywords'])) {
        foreach (array_slice($payload['trending_keywords'], 0, 5) as $keyword) {
            $keyword = trim((string)$keyword);
            if ($keyword !== '') {
                $result['trending_keywords'][] = $keyword;
            }
        }
    }

    if (!empty($payload['flagged_comments']) && is_array($payload['flagged_comments'])) {
        $n = 0;
        foreach ($payload['flagged_comments'] as $comment) {
            if ($n >= 500) {
                break;
            }
            if (!is_array($comment)) {
                continue;
            }
            $text = trim((string)($comment['text'] ?? ''));
            $sentiment = strtolower(trim((string)($comment['sentiment'] ?? 'neutral')));
            if ($text === '') {
                continue;
            }
            if (!in_array($sentiment, ['positive', 'neutral', 'negative'], true)) {
                $sentiment = 'neutral';
            }
            $result['flagged_comments'][] = [
                'text' => $text,
                'sentiment' => $sentiment,
            ];
            $n++;
        }
    }

    return $result;
}

/**
 * Ensure every raw feedback comment appears in the highlighted list; map sentiment from Groq where text matches.
 *
 * @param array<int,string> $allcomments Full comment texts from the course (same source as sent to the model).
 * @param array<string,mixed> $sentiment Normalized sentiment payload (includes flagged_comments from Groq).
 * @return array<int,array{text:string,sentiment:string}>
 */
function local_admindashboard_merge_all_feedback_highlight_comments(array $allcomments, array $sentiment): array {
    $map = [];
    foreach (($sentiment['flagged_comments'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $text = trim((string)($row['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $s = strtolower(trim((string)($row['sentiment'] ?? 'neutral')));
        if (!in_array($s, ['positive', 'neutral', 'negative'], true)) {
            $s = 'neutral';
        }
        $map[core_text::strtolower($text)] = $s;
    }

    $out = [];
    foreach ($allcomments as $c) {
        $c = trim((string)$c);
        if ($c === '') {
            continue;
        }
        $key = core_text::strtolower($c);
        $s = $map[$key] ?? 'neutral';
        $out[] = [
            'text' => $c,
            'sentiment' => $s,
        ];
    }

    return $out;
}

/**
 * Analyse feedback comments using Groq chat completions.
 *
 * @param array<int,string> $comments_array Raw text comments.
 * @return array{sentiment_split:array{positive_pct:int,neutral_pct:int,negative_pct:int},trending_keywords:array<int,string>,flagged_comments:array<int,array{text:string,sentiment:string}>,error:?string}
 */
function local_admindashboard_analyze_feedback_sentiment_groq(array $comments_array): array {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');

    $default = [
        'sentiment_split' => [
            'positive_pct' => 0,
            'neutral_pct' => 0,
            'negative_pct' => 0,
        ],
        'trending_keywords' => [],
        'flagged_comments' => [],
        'error' => null,
    ];

    $comments = [];
    foreach ($comments_array as $comment) {
        $comment = trim((string)$comment);
        if ($comment === '') {
            continue;
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($comment) > 1000) {
            $comment = mb_substr($comment, 0, 1000);
        } else if (strlen($comment) > 1000) {
            $comment = substr($comment, 0, 1000);
        }
        $comments[] = $comment;
    }

    $comments = array_values(array_unique($comments));
    $comments = array_slice($comments, 0, 150);
    if (empty($comments)) {
        return $default;
    }

    $apikey = trim((string)get_config('local_admindashboard', 'groq_apikey'));
    $model = trim((string)get_config('local_admindashboard', 'groq_model'));
    $endpoint = trim((string)get_config('local_admindashboard', 'groq_endpoint'));

    if ($model === '') {
        $model = 'llama3-70b-8192';
    }
    if ($endpoint === '') {
        $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    }
    if ($apikey === '') {
        debugging('local_admindashboard: Groq API key missing for feedback sentiment analysis.', DEBUG_DEVELOPER);
        $default['error'] = 'Groq API key is not configured.';
        return $default;
    }

    $systemprompt = <<<PROMPT
You are a healthcare training feedback sentiment analyzer.

Return only one strict JSON object with this exact schema:
{
  "sentiment_split": {
    "positive_pct": 0,
    "neutral_pct": 0,
    "negative_pct": 0
  },
  "trending_keywords": [
    "keyword 1",
    "keyword 2",
    "keyword 3",
    "keyword 4",
    "keyword 5"
  ],
  "flagged_comments": [
    {"text": "exact text of first input comment", "sentiment": "positive"},
    {"text": "exact text of second input comment", "sentiment": "neutral"}
  ]
}

Rules:
- Percentages must be integers from 0 to 100.
- trending_keywords must contain exactly 5 concise themes if possible.
- The user message includes a JSON array "comments". flagged_comments MUST have exactly one entry per input comment, in the SAME ORDER as in that array.
- For each entry, "text" MUST match the corresponding input comment exactly (verbatim).
- Classify each comment's sentiment as positive, neutral, or negative.
- If there are many comments, still include every one — do not omit or summarize away rows.
- sentiment must be only positive, neutral, or negative.
- Do not include markdown.
- Do not include explanation outside JSON.
PROMPT;

    $payload = [
        'model' => $model,
        'temperature' => 0.2,
        'max_tokens' => 8192,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $systemprompt],
            [
                'role' => 'user',
                'content' => json_encode([
                    'task' => 'Analyze these learner comments for sentiment and recurring themes.',
                    'comments' => $comments,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ],
    ];

    $curl = new curl();
    $curl->setHeader([
        'Authorization: Bearer ' . $apikey,
        'Content-Type: application/json',
    ]);
    $rawresponse = $curl->post(
        $endpoint,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        [
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_TIMEOUT' => 90,
        ]
    );

    if ($curl->get_errno()) {
        debugging('local_admindashboard: Groq cURL error [' . $curl->get_errno() . '] ' . $curl->error, DEBUG_DEVELOPER);
        $default['error'] = 'Groq request failed.';
        return $default;
    }

    $info = $curl->get_info();
    $httpcode = (int)($info['http_code'] ?? 0);
    if ($httpcode < 200 || $httpcode >= 300 || $rawresponse === false || $rawresponse === '') {
        debugging('local_admindashboard: Groq HTTP error [' . $httpcode . ']', DEBUG_DEVELOPER);
        $default['error'] = 'Groq returned an unexpected HTTP response.';
        return $default;
    }

    $decoded = json_decode($rawresponse, true);
    if (!is_array($decoded)) {
        debugging('local_admindashboard: Groq top-level decode failed.', DEBUG_DEVELOPER);
        $default['error'] = 'Groq returned invalid JSON.';
        return $default;
    }

    $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
    if ($content === '') {
        debugging('local_admindashboard: Groq response missing assistant content.', DEBUG_DEVELOPER);
        $default['error'] = 'Groq returned an empty completion.';
        return $default;
    }

    $analysis = json_decode($content, true);
    if (!is_array($analysis)) {
        debugging('local_admindashboard: Groq analysis JSON decode failed.', DEBUG_DEVELOPER);
        $default['error'] = 'Groq returned invalid analysis JSON.';
        return $default;
    }

    return local_admindashboard_normalize_feedback_sentiment_payload($analysis);
}

/**
 * Build the dashboard feedback insights payload for one selected course.
 *
 * @param int $courseid Course ID.
 * @return array{quantitative:array{questions:array<string,array{itemid:int,question:string,avg_score:float,max_score:float,response_count:int}>,overall_average:?float,question_count:int},sentiment:array{sentiment_split:array{positive_pct:int,neutral_pct:int,negative_pct:int},trending_keywords:array<int,string>,flagged_comments:array<int,array{text:string,sentiment:string}>,error:?string},meta:array{courseid:int,comments_count:int,has_quantitative:bool,has_comments:bool}}
 */
function local_admindashboard_get_feedback_insights(int $courseid): array {
    $quantitative = local_admindashboard_get_quantitative_feedback_averages($courseid);
    $comments = local_admindashboard_get_feedback_comments($courseid);
    $sentiment = local_admindashboard_analyze_feedback_sentiment_groq($comments);
    if (!empty($comments)) {
        $sentiment['flagged_comments'] = local_admindashboard_merge_all_feedback_highlight_comments($comments, $sentiment);
    }

    return [
        'quantitative' => $quantitative,
        'sentiment' => $sentiment,
        'meta' => [
            'courseid' => $courseid,
            'comments_count' => count($comments),
            'has_quantitative' => !empty($quantitative['questions']),
            'has_comments' => !empty($comments),
        ],
    ];
}

/**
 * Builds department versus module compliance data for the dashboard heatmap.
 *
 * @param int $courseid Selected course.
 * @param string $department Optional department filter.
 * @param int $moduleid Optional module filter.
 * @return array{columns:array<int,array{id:int,name:string,modname:string}>,rows:array<int,array{department:string,cells:array<int,array{moduleid:int,value:int,status:string,total:int,compliant:int,label:string}>}>,summary:array{course_selected:bool,total_cells:int,red_cells:int,amber_cells:int,green_cells:int,label:string}}
 */
function local_admindashboard_get_compliance_heatmap(int $courseid, string $department = '', int $moduleid = 0): array {
    global $CFG, $DB;

    $supervideocompliancethreshold = 80;
    $videoextensions = ['mp4', 'm4v', 'mov', 'avi', 'mkv', 'webm'];

    $empty = [
        'columns' => [],
        'rows' => [],
        'summary' => [
            'course_selected' => false,
            'total_cells' => 0,
            'red_cells' => 0,
            'amber_cells' => 0,
            'green_cells' => 0,
            'label' => '',
        ],
    ];

    if ($courseid <= 0) {
        return $empty;
    }

    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->libdir . '/xmldb/xmldb_table.php');

    $department = trim($department);
    [$userwhere, $userparams] = local_admindashboard_build_user_filter($department);

    $manager = $DB->get_manager();
    $hassupervideo = $manager->table_exists(new xmldb_table('supervideo_view'))
        && $manager->table_exists(new xmldb_table('supervideo'));
    $hasslog = $manager->table_exists(new xmldb_table('logstore_standard_log'));

    $modinfo = get_fast_modinfo($courseid);
    $columns = [];
    $moduleinstanceids = [];
    $supervideocmids = [];
    $resourcecandidates = [];
    foreach ($modinfo->get_cms() as $cm) {
        if ((int)$cm->course !== $courseid || !empty($cm->deletioninprogress) || empty($cm->visible) || empty($cm->visibleoncoursepage)) {
            continue;
        }
        if ($cm->modname === 'label') {
            continue;
        }
        if ($moduleid > 0 && (int)$cm->id !== $moduleid) {
            continue;
        }

        if ($cm->modname === 'resource' && $hasslog) {
            $resourcecandidates[(int)$cm->id] = [
                'id' => (int)$cm->id,
                'contextid' => (int)$cm->context->id,
                'name' => trim(format_string($cm->name, true, ['context' => $cm->context])),
                'modname' => 'resource',
            ];
        }

        $allowsupervideo = ($cm->modname === 'supervideo' && $hassupervideo);
        if ((int)$cm->completion <= 0 && $cm->modname !== 'quiz' && !$allowsupervideo && $cm->modname !== 'resource') {
            continue;
        }

        if ($cm->modname === 'resource') {
            continue;
        }

        $name = trim(format_string($cm->name, true, ['context' => $cm->context]));
        if ($name === '') {
            $name = ucfirst((string)$cm->modname);
        }

        $columns[(int)$cm->id] = [
            'id' => (int)$cm->id,
            'name' => $name,
            'modname' => (string)$cm->modname,
        ];

        if ($cm->modname === 'quiz') {
            $moduleinstanceids[(int)$cm->id] = (int)$cm->instance;
        } else if ($allowsupervideo) {
            $supervideocmids[(int)$cm->id] = (int)$cm->id;
        }
    }

    $resourcecmids = [];
    if (!empty($resourcecandidates)) {
        [$contextinsql, $contextparams] = $DB->get_in_or_equal(array_column($resourcecandidates, 'contextid'), SQL_PARAMS_NAMED, 'heatctx');
        $resourcesql = "SELECT f.contextid, f.mimetype, f.filename
                          FROM {files} f
                         WHERE f.contextid {$contextinsql}
                           AND f.component = 'mod_resource'
                           AND f.filearea = 'content'
                           AND f.filename <> '.'";
        $resourcefiles = $DB->get_records_sql($resourcesql, $contextparams);

        $resourcekindbycontext = [];
        foreach ($resourcefiles as $file) {
            $contextid = (int)($file->contextid ?? 0);
            $mimetype = strtolower((string)($file->mimetype ?? ''));
            $filename = strtolower((string)($file->filename ?? ''));
            $isvideo = str_starts_with($mimetype, 'video/');
            if (!$isvideo) {
                foreach ($videoextensions as $extension) {
                    if (str_ends_with($filename, '.' . $extension)) {
                        $isvideo = true;
                        break;
                    }
                }
            }
            $ispdf = ($mimetype === 'application/pdf' || str_ends_with($filename, '.pdf'));

            if ($isvideo) {
                $resourcekindbycontext[$contextid] = 'video';
                continue;
            }
            if ($ispdf && !isset($resourcekindbycontext[$contextid])) {
                $resourcekindbycontext[$contextid] = 'pdf';
            }
        }

        foreach ($resourcecandidates as $cmid => $candidate) {
            $contextid = (int)$candidate['contextid'];
            if (!isset($resourcekindbycontext[$contextid])) {
                continue;
            }
            $resourcecmids[$cmid] = (int)$cmid;
            $columns[$cmid] = [
                'id' => (int)$cmid,
                'name' => $candidate['name'] !== '' ? $candidate['name'] : 'Resource',
                'modname' => 'resource',
                'resourcekind' => $resourcekindbycontext[$contextid],
            ];
        }
        ksort($columns);
    }

    if (empty($columns)) {
        $empty['summary']['course_selected'] = true;
        $empty['summary']['label'] = 'No completion-tracked modules or quizzes are available for the selected course.';
        return $empty;
    }

    $deptparams = ['courseid_heatmap' => $courseid] + $userparams;
    $departmentrows = $DB->get_records_sql(
        "SELECT DISTINCT u.department
           FROM {user} u
           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_heatmap
          WHERE {$userwhere}
            AND u.department <> ''
       ORDER BY u.department ASC",
        $deptparams
    );

    $departments = [];
    foreach ($departmentrows as $row) {
        $deptname = trim((string)($row->department ?? ''));
        if ($deptname !== '') {
            $departments[] = $deptname;
        }
    }

    if (empty($departments)) {
        $empty['summary']['course_selected'] = true;
        $empty['summary']['label'] = 'No enrolled departments were found for the selected course.';
        return $empty;
    }

    $gradeitembymodule = [];
    if (!empty($moduleinstanceids)) {
        [$instanceinsql, $instanceparams] = $DB->get_in_or_equal(array_values($moduleinstanceids), SQL_PARAMS_NAMED, 'heatquiz');
        $gradeitems = $DB->get_records_sql(
            "SELECT gi.iteminstance, gi.id, gi.gradepass
               FROM {grade_items} gi
              WHERE gi.courseid = :courseid_gradeitems
                AND gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND gi.itemnumber = 0
                AND gi.iteminstance {$instanceinsql}",
            ['courseid_gradeitems' => $courseid] + $instanceparams
        );
        foreach ($moduleinstanceids as $cmid => $instanceid) {
            if (isset($gradeitems[$instanceid])) {
                $gradeitembymodule[$cmid] = [
                    'id' => (int)$gradeitems[$instanceid]->id,
                    'gradepass' => (float)($gradeitems[$instanceid]->gradepass ?? 0),
                ];
            }
        }
    }

    $deptplaceholders = [];
    $deptqueryparams = [];
    foreach (array_values($departments) as $index => $deptname) {
        $key = 'heatdept' . $index;
        $deptplaceholders[] = ':' . $key;
        $deptqueryparams[$key] = $deptname;
    }
    $deptinsql = implode(',', $deptplaceholders);

    $enrolsql = "SELECT u.id AS userid, u.department
                   FROM {user} u
                   JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_users
                  WHERE {$userwhere}
                    AND u.department <> ''
                    AND u.department IN ({$deptinsql})";
    $baseparams = ['courseid_users' => $courseid] + $userparams + $deptqueryparams;
    $enrolledrows = $DB->get_records_sql($enrolsql, $baseparams);

    $totalsbydepartment = array_fill_keys($departments, 0);
    $userdepartment = [];
    foreach ($enrolledrows as $row) {
        $userid = (int)($row->userid ?? 0);
        $deptname = trim((string)($row->department ?? ''));
        if ($userid <= 0 || $deptname === '' || !isset($totalsbydepartment[$deptname])) {
            continue;
        }
        $totalsbydepartment[$deptname]++;
        $userdepartment[$userid] = $deptname;
    }

    if (empty($userdepartment)) {
        $empty['summary']['course_selected'] = true;
        $empty['summary']['label'] = 'No enrolled learners were found for the selected course filters.';
        return $empty;
    }

    $moduleplaceholders = [];
    $modulequeryparams = [];
    foreach (array_keys($columns) as $index => $cmid) {
        $key = 'heatcm' . $index;
        $moduleplaceholders[] = ':' . $key;
        $modulequeryparams[$key] = (int)$cmid;
    }
    $moduleinsql = implode(',', $moduleplaceholders);

    $completionrows = $DB->get_records_sql(
        "SELECT cmc.userid, cmc.coursemoduleid
           FROM {course_modules_completion} cmc
          WHERE cmc.coursemoduleid IN ({$moduleinsql})
            AND cmc.completionstate IS NOT NULL
            AND cmc.completionstate > 0",
        $modulequeryparams
    );

    $completedbydeptmodule = [];
    foreach ($completionrows as $row) {
        $userid = (int)($row->userid ?? 0);
        $cmid = (int)($row->coursemoduleid ?? 0);
        if ($userid <= 0 || $cmid <= 0 || !isset($userdepartment[$userid])) {
            continue;
        }
        $deptname = $userdepartment[$userid];
        if (!isset($completedbydeptmodule[$deptname])) {
            $completedbydeptmodule[$deptname] = [];
        }
        if (!isset($completedbydeptmodule[$deptname][$cmid])) {
            $completedbydeptmodule[$deptname][$cmid] = [];
        }
        $completedbydeptmodule[$deptname][$cmid][$userid] = true;
    }

    $watchedbydeptmodule = [];
    if (!empty($supervideocmids)) {
        [$supervideoinsql, $supervideoparams] = $DB->get_in_or_equal(array_values($supervideocmids), SQL_PARAMS_NAMED, 'heatsv');
        $supervideorows = $DB->get_records_sql(
            "SELECT sv.user_id AS userid,
                    sv.cm_id AS cmid,
                    MAX(COALESCE(sv.percent, 0)) AS maxpercent
               FROM {supervideo_view} sv
              WHERE sv.cm_id {$supervideoinsql}
           GROUP BY sv.user_id, sv.cm_id",
            $supervideoparams
        );

        foreach ($supervideorows as $row) {
            $userid = (int)($row->userid ?? 0);
            $cmid = (int)($row->cmid ?? 0);
            $maxpercent = (float)($row->maxpercent ?? 0);
            if ($userid <= 0 || $cmid <= 0 || $maxpercent < $supervideocompliancethreshold || !isset($userdepartment[$userid])) {
                continue;
            }

            $deptname = $userdepartment[$userid];
            if (!isset($watchedbydeptmodule[$deptname])) {
                $watchedbydeptmodule[$deptname] = [];
            }
            if (!isset($watchedbydeptmodule[$deptname][$cmid])) {
                $watchedbydeptmodule[$deptname][$cmid] = [];
            }
            $watchedbydeptmodule[$deptname][$cmid][$userid] = true;
        }
    }

    $viewedbydeptmodule = [];
    if (!empty($resourcecmids) && $hasslog) {
        [$resourceinsql, $resourceparams] = $DB->get_in_or_equal(array_values($resourcecmids), SQL_PARAMS_NAMED, 'heatresource');
        $resourceparams['heatresourcesince'] = time() - YEARSECS;
        $resourceviewrows = $DB->get_records_sql(
            "SELECT DISTINCT l.userid, ctx.instanceid AS cmid
               FROM {logstore_standard_log} l
               JOIN {context} ctx ON ctx.id = l.contextid AND ctx.contextlevel = 70
               JOIN {user} u ON u.id = l.userid
              WHERE ctx.instanceid {$resourceinsql}
                AND l.eventname = :resourceviewevent
                AND l.timecreated >= :heatresourcesince
                AND {$userwhere}",
            $resourceparams + ['resourceviewevent' => '\\mod_resource\\event\\course_module_viewed'] + $userparams
        );

        foreach ($resourceviewrows as $row) {
            $userid = (int)($row->userid ?? 0);
            $cmid = (int)($row->cmid ?? 0);
            if ($userid <= 0 || $cmid <= 0 || !isset($userdepartment[$userid])) {
                continue;
            }

            $deptname = $userdepartment[$userid];
            if (!isset($viewedbydeptmodule[$deptname])) {
                $viewedbydeptmodule[$deptname] = [];
            }
            if (!isset($viewedbydeptmodule[$deptname][$cmid])) {
                $viewedbydeptmodule[$deptname][$cmid] = [];
            }
            $viewedbydeptmodule[$deptname][$cmid][$userid] = true;
        }
    }

    $passedbydeptmodule = [];
    foreach ($gradeitembymodule as $cmid => $gradeitem) {
        $graderows = $DB->get_records_sql(
            "SELECT gg.userid, gg.finalgrade
               FROM {grade_grades} gg
              WHERE gg.itemid = :gradeitemid
                AND gg.finalgrade IS NOT NULL",
            ['gradeitemid' => (int)$gradeitem['id']]
        );
        foreach ($graderows as $row) {
            $userid = (int)($row->userid ?? 0);
            if ($userid <= 0 || !isset($userdepartment[$userid])) {
                continue;
            }
            $passes = ((float)$gradeitem['gradepass'] > 0)
                ? ((float)$row->finalgrade >= (float)$gradeitem['gradepass'])
                : true;
            if (!$passes) {
                continue;
            }
            $deptname = $userdepartment[$userid];
            if (!isset($passedbydeptmodule[$cmid])) {
                $passedbydeptmodule[$cmid] = [];
            }
            if (!isset($passedbydeptmodule[$cmid][$deptname])) {
                $passedbydeptmodule[$cmid][$deptname] = [];
            }
            $passedbydeptmodule[$cmid][$deptname][$userid] = true;
        }
    }

    $rows = [];
    $summary = [
        'course_selected' => true,
        'total_cells' => 0,
        'red_cells' => 0,
        'amber_cells' => 0,
        'green_cells' => 0,
        'label' => '',
    ];

    foreach ($departments as $deptname) {
        $cells = [];
        foreach ($columns as $cmid => $column) {
            $total = (int)($totalsbydepartment[$deptname] ?? 0);
            if ($column['modname'] === 'quiz' && isset($passedbydeptmodule[$cmid][$deptname])) {
                $compliant = count($passedbydeptmodule[$cmid][$deptname]);
            } else if ($column['modname'] === 'supervideo' && isset($watchedbydeptmodule[$deptname][$cmid])) {
                $compliant = count($watchedbydeptmodule[$deptname][$cmid]);
            } else if ($column['modname'] === 'resource' && isset($viewedbydeptmodule[$deptname][$cmid])) {
                $compliant = count($viewedbydeptmodule[$deptname][$cmid]);
            } else if (isset($completedbydeptmodule[$deptname][$cmid])) {
                $compliant = count($completedbydeptmodule[$deptname][$cmid]);
            } else {
                $compliant = 0;
            }

            $value = ($total > 0) ? (int)round(($compliant * 100) / $total) : 0;
            if ($total <= 0 || $value < 50) {
                $status = 'critical';
                $summary['red_cells']++;
            } else if ($value < 75) {
                $status = 'warning';
                $summary['amber_cells']++;
            } else {
                $status = 'healthy';
                $summary['green_cells']++;
            }

            $summary['total_cells']++;
            $cells[] = [
                'moduleid' => (int)$cmid,
                'value' => $value,
                'status' => $status,
                'total' => $total,
                'compliant' => $compliant,
                'label' => $compliant . '/' . $total,
            ];
        }

        $rows[] = [
            'department' => $deptname,
            'cells' => $cells,
        ];
    }

    $summary['label'] = $summary['red_cells'] . ' risk zones need attention';

    return [
        'columns' => array_values($columns),
        'rows' => $rows,
        'summary' => $summary,
    ];
}

/**
 * Returns whether a course is currently running based on course start/end dates.
 *
 * @param int $courseid Course ID.
 * @return bool
 */
function local_admindashboard_is_course_running(int $courseid): bool {
    global $DB;

    if ($courseid <= 0) {
        return false;
    }

    $course = $DB->get_record('course', ['id' => $courseid], 'id,visible,startdate,enddate', IGNORE_MISSING);
    if (!$course || empty($course->visible)) {
        return false;
    }

    $now = time();
    $startdate = (int)($course->startdate ?? 0);
    $enddate = (int)($course->enddate ?? 0);

    if ($startdate > 0 && $startdate > $now) {
        return false;
    }

    if ($enddate > 0 && $enddate < $now) {
        return false;
    }

    return true;
}

/**
 * Calculates schedule-based progress between two timestamps.
 *
 * @param int $starttimestamp Window start.
 * @param int $endtimestamp Window end.
 * @param int|null $now Current timestamp override.
 * @return int Progress percent from 0 to 100.
 */
function local_admindashboard_calculate_schedule_progress(int $starttimestamp, int $endtimestamp, ?int $now = null): int {
    $now = $now ?? time();

    if ($starttimestamp > 0 && $endtimestamp > 0 && $endtimestamp > $starttimestamp) {
        if ($now <= $starttimestamp) {
            return 0;
        }
        if ($now >= $endtimestamp) {
            return 100;
        }
        return max(0, min(100, (int)round(100 * (($now - $starttimestamp) / ($endtimestamp - $starttimestamp)))));
    }

    if ($endtimestamp > 0 && $now >= $endtimestamp) {
        return 100;
    }

    return 0;
}

/**
 * Returns resolved schedule windows for the sections of a course.
 *
 * @param int $courseid Course ID.
 * @return array{coursestart:int,courseend:int,sections:array<int,array{module:string,start:int,end:int}>}
 */
function local_admindashboard_get_course_schedule_sections(int $courseid): array {
    global $DB;

    $course = $DB->get_record('course', ['id' => $courseid], 'id,startdate,enddate', MUST_EXIST);
    $coursestart = (int)($course->startdate ?? 0);
    $courseend = (int)($course->enddate ?? 0);

    $sectionrecords = $DB->get_records_sql(
        "SELECT cs.id,
                cs.section AS sectionnum,
                COALESCE(NULLIF(cs.name, ''), '') AS sectionname,
                MIN(CASE
                        WHEN m.name = 'quiz' AND q.timeopen > 0 THEN q.timeopen
                        WHEN m.name = 'assign' AND a.allowsubmissionsfromdate > 0 THEN a.allowsubmissionsfromdate
                        WHEN m.name = 'feedback' AND f.timeopen > 0 THEN f.timeopen
                        ELSE NULL
                    END) AS sectionstart,
                MAX(CASE
                        WHEN m.name = 'quiz' AND q.timeclose > 0 THEN q.timeclose
                        WHEN m.name = 'assign' AND a.duedate > 0 THEN a.duedate
                        WHEN m.name = 'feedback' AND f.timeclose > 0 THEN f.timeclose
                        WHEN cm.completionexpected IS NOT NULL AND cm.completionexpected > 0 THEN cm.completionexpected
                        ELSE NULL
                    END) AS sectionend
           FROM {course_sections} cs
      LEFT JOIN {course_modules} cm
             ON cm.section = cs.id
            AND cm.course = cs.course
            AND cm.deletioninprogress = 0
            AND cm.visible = 1
      LEFT JOIN {modules} m ON m.id = cm.module
      LEFT JOIN {quiz} q ON q.id = cm.instance AND m.name = 'quiz'
      LEFT JOIN {assign} a ON a.id = cm.instance AND m.name = 'assign'
      LEFT JOIN {feedback} f ON f.id = cm.instance AND m.name = 'feedback'
          WHERE cs.course = :progresscourseid
            AND cs.section > 0
       GROUP BY cs.id, cs.section, cs.name
       ORDER BY cs.section ASC",
        ['progresscourseid' => $courseid]
    );

    if (empty($sectionrecords)) {
        return [
            'coursestart' => $coursestart,
            'courseend' => $courseend,
            'sections' => [],
        ];
    }

    $rawsections = array_values($sectionrecords);
    $sectioncount = count($rawsections);

    foreach ($rawsections as $index => $section) {
        $rawstart = (int)($section->sectionstart ?? 0);
        $rawend = (int)($section->sectionend ?? 0);

        if ($rawstart <= 0) {
            if ($index > 0) {
                $rawstart = (int)($rawsections[$index - 1]->resolvedend ?? 0);
            }
            if ($rawstart <= 0) {
                $rawstart = $coursestart;
            }
        }

        if ($rawend <= 0) {
            $nextanchor = 0;
            for ($lookahead = $index + 1; $lookahead < $sectioncount; $lookahead++) {
                $nextstart = (int)($rawsections[$lookahead]->sectionstart ?? 0);
                $nextend = (int)($rawsections[$lookahead]->sectionend ?? 0);
                if ($nextstart > 0) {
                    $nextanchor = $nextstart;
                    break;
                }
                if ($nextend > 0) {
                    $nextanchor = $nextend;
                    break;
                }
            }
            $rawend = $nextanchor > 0 ? $nextanchor : $courseend;
        }

        if ($rawend > 0 && $rawstart > 0 && $rawend < $rawstart) {
            $rawend = $rawstart;
        }

        $rawsections[$index]->resolvedstart = $rawstart;
        $rawsections[$index]->resolvedend = $rawend;
    }

    $sections = [];
    foreach ($rawsections as $index => $section) {
        $sectionnum = (int)($section->sectionnum ?? ($index + 1));
        $sectiontitle = trim((string)($section->sectionname ?? ''));
        $label = 'Module ' . $sectionnum;
        if ($sectiontitle !== '' && strcasecmp($sectiontitle, $label) !== 0) {
            $label .= ' - ' . $sectiontitle;
        }

        $sections[] = [
            'module' => $label,
            'start' => (int)($section->resolvedstart ?? 0),
            'end' => (int)($section->resolvedend ?? 0),
        ];
    }

    return [
        'coursestart' => $coursestart,
        'courseend' => $courseend,
        'sections' => $sections,
    ];
}

/**
 * Calculates overall course schedule progress from section windows.
 *
 * @param array<int,array{module:string,start:int,end:int}> $sections Section schedule rows.
 * @param int|null $now Current timestamp override.
 * @return int
 */
function local_admindashboard_calculate_course_schedule_progress(array $sections, ?int $now = null): int {
    $now = $now ?? time();
    if (empty($sections)) {
        return 0;
    }

    $progresssum = 0;
    foreach ($sections as $section) {
        $progresssum += local_admindashboard_calculate_schedule_progress((int)($section['start'] ?? 0), (int)($section['end'] ?? 0), $now);
    }

    return (int)round($progresssum / count($sections));
}

/**
 * Returns schedule-based progress rows for all courses or one selected course.
 *
 * @param int $courseid Selected course ID.
 * @param int $limit Max course rows when no course is selected; 0 means all.
 * @return array<int,array{module:string,completion:int}>
 */
function local_admindashboard_get_schedule_progress_rows(int $courseid, int $limit = 0): array {
    global $DB;

    $now = time();
    $rows = [];

    if ($courseid <= 0) {
        $courses = $DB->get_records_sql(
            "SELECT id, fullname, startdate, enddate
               FROM {course}
              WHERE visible = 1
                AND id > 1
           ORDER BY sortorder ASC, fullname ASC, id ASC"
        );

        foreach ($courses as $course) {
            $courseidlocal = (int)$course->id;
            $startdate = (int)($course->startdate ?? 0);
            $enddate = (int)($course->enddate ?? 0);
            $schedule = local_admindashboard_get_course_schedule_sections($courseidlocal);
            $sections = $schedule['sections'] ?? [];
            if (!empty($sections)) {
                $completion = local_admindashboard_calculate_course_schedule_progress($sections, $now);
            } else {
                $completion = local_admindashboard_calculate_schedule_progress($startdate, $enddate, $now);
            }

            $statuskey = 'running';
            $statuslabel = 'Running';
            $statusrank = 0;
            if ($startdate > 0 && $startdate > $now) {
                $statuskey = 'upcoming';
                $statuslabel = 'Upcoming';
                $statusrank = 1;
            } else if (($enddate > 0 && $enddate < $now) || $completion >= 100) {
                $statuskey = 'completed';
                $statuslabel = 'Completed';
                $statusrank = 2;
            }

            $rows[] = [
                'module' => (string)$course->fullname,
                'completion' => $completion,
                'status' => $statuslabel,
                'status_key' => $statuskey,
                'status_rank' => $statusrank,
                'startdate' => $startdate,
                'enddate' => $enddate,
            ];
        }

        usort($rows, static function(array $a, array $b): int {
            if ((int)($a['status_rank'] ?? 0) !== (int)($b['status_rank'] ?? 0)) {
                return (int)($a['status_rank'] ?? 0) <=> (int)($b['status_rank'] ?? 0);
            }
            if ((int)($a['completion'] ?? 0) !== (int)($b['completion'] ?? 0)) {
                return (int)($b['completion'] ?? 0) <=> (int)($a['completion'] ?? 0);
            }
            return strcasecmp((string)($a['module'] ?? ''), (string)($b['module'] ?? ''));
        });

        if ($limit > 0) {
            return array_slice($rows, 0, $limit);
        }

        return $rows;
    }

    $schedule = local_admindashboard_get_course_schedule_sections($courseid);
    $sections = $schedule['sections'] ?? [];
    if (empty($sections)) {
        return $rows;
    }

    foreach ($sections as $section) {
            $rows[] = [
                'module' => (string)$section['module'],
                'completion' => local_admindashboard_calculate_schedule_progress((int)($section['start'] ?? 0), (int)($section['end'] ?? 0), $now),
                'status' => 'Module',
                'status_key' => 'module',
                'startdate' => (int)($section['start'] ?? 0),
                'enddate' => (int)($section['end'] ?? 0),
            ];
        }

        return $rows;
}

/**
 * Calculates at-risk participants for a course using login, pacing, and pre-test signals.
 *
 * @param int $courseid Course ID.
 * @return array<int,array<string,mixed>>
 */
function local_admindashboard_calculate_at_risk_rows_for_course(int $courseid): array {
    global $DB;

    if ($courseid <= 0 || !local_admindashboard_is_course_running($courseid)) {
        return [];
    }

    [$userwhere, $userparams] = local_admindashboard_build_user_filter('');
    $now = time();

    $usersql = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, u.lastaccess
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
             LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
                 WHERE {$userwhere}
                   AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)
              ORDER BY u.lastname ASC, u.firstname ASC";
    $users = $DB->get_records_sql($usersql, ['courseid' => $courseid] + $userparams);
    if (empty($users)) {
        return [];
    }

    $trackablecmids = $DB->get_fieldset_sql(
        "SELECT cm.id
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.course = :courseid
            AND cm.deletioninprogress = 0
            AND cm.visible = 1
            AND cm.completion > 0
            AND m.name <> 'label'",
        ['courseid' => $courseid]
    );

    $trackablecmids = array_values(array_unique(array_map('intval', $trackablecmids)));
    $totaltrackable = count($trackablecmids);
    $completioncounts = [];
    $deadlineat = 0;

    if (!empty($trackablecmids)) {
        list($cminsql, $cminparams) = $DB->get_in_or_equal($trackablecmids, SQL_PARAMS_NAMED, 'riskcmid');
        $deadlineat = (int)$DB->get_field_sql(
            "SELECT MAX(completionexpected)
               FROM {course_modules}
              WHERE course = :courseid
                AND id {$cminsql}
                AND completionexpected IS NOT NULL
                AND completionexpected > 0",
            ['courseid' => $courseid] + $cminparams
        );

        $userids = array_map('intval', array_keys($users));
        list($uinq, $uinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'riskuid');
        $donebyuser = $DB->get_records_sql(
            "SELECT userid, COUNT(1) AS donecount
               FROM {course_modules_completion}
              WHERE userid {$uinq}
                AND coursemoduleid {$cminsql}
                AND completionstate > 0
           GROUP BY userid",
            $uinparams + $cminparams
        );
        foreach ($donebyuser as $row) {
            $completioncounts[(int)$row->userid] = (int)$row->donecount;
        }
    }

    $pretestitems = local_admindashboard_get_at_risk_pretest_items($courseid);
    $pretestavgbyuser = [];
    $coursepretestavg = null;

    if (!empty($pretestitems)) {
        $gradeitemids = array_map(static function($item): int {
            return (int)($item->gradeitemid ?? 0);
        }, $pretestitems);
        $gradeitemids = array_values(array_filter($gradeitemids));

        if (!empty($gradeitemids)) {
            list($giinsql, $ginparams) = $DB->get_in_or_equal($gradeitemids, SQL_PARAMS_NAMED, 'riskgi');
            $pretestparams = ['courseid' => $courseid] + $userparams + $ginparams;
            $pretestsql = "SELECT u.id,
                                  AVG((100.0 * gg.finalgrade) / gi.grademax) AS avgpct
                             FROM {user} u
                             JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                             JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                        LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
                             JOIN {grade_grades} gg ON gg.userid = u.id AND gg.finalgrade IS NOT NULL
                             JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.grademax > 0
                            WHERE {$userwhere}
                              AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)
                              AND gi.id {$giinsql}
                         GROUP BY u.id";
            $pretestrows = $DB->get_records_sql($pretestsql, $pretestparams);
            foreach ($pretestrows as $row) {
                $pretestavgbyuser[(int)$row->id] = round((float)$row->avgpct, 1);
            }

            $courseavgsql = "SELECT AVG((100.0 * gg.finalgrade) / gi.grademax)
                               FROM {user} u
                               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                          LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
                               JOIN {grade_grades} gg ON gg.userid = u.id AND gg.finalgrade IS NOT NULL
                               JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.grademax > 0
                              WHERE {$userwhere}
                                AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)
                                AND gi.id {$giinsql}";
            $courseavgvalue = $DB->get_field_sql($courseavgsql, $pretestparams);
            if ($courseavgvalue !== false && $courseavgvalue !== null) {
                $coursepretestavg = round((float)$courseavgvalue, 1);
            }
        }
    }

    $rows = [];
    foreach ($users as $user) {
        $userid = (int)$user->id;
        $name = trim((string)$user->firstname . ' ' . (string)$user->lastname);
        if ($name === '') {
            $name = 'User ' . $userid;
        }

        $lastaccess = (int)($user->lastaccess ?? 0);
        $dayssincelogin = ($lastaccess > 0) ? (int)floor(max(0, $now - $lastaccess) / DAYSECS) : 9999;
        $donecount = (int)($completioncounts[$userid] ?? 0);
        $completionpct = ($totaltrackable > 0) ? round((100 * $donecount) / $totaltrackable, 1) : 0.0;
        if ($completionpct >= 100.0) {
            continue;
        }
        $timeleft = ($deadlineat > 0) ? ($deadlineat - $now) : null;

        $loginrisk = ($lastaccess <= 0) || ($dayssincelogin >= 7);
        $pacingrisk = ($deadlineat > 0 && $timeleft !== null && $timeleft >= 0 && $timeleft <= (3 * DAYSECS) && $completionpct < 10);

        $pretestpct = $pretestavgbyuser[$userid] ?? null;
        $pretestrisk = false;
        if ($pretestpct !== null && $coursepretestavg !== null) {
            $pretestrisk = ($pretestpct <= ($coursepretestavg - 15.0));
        }

        $riskscore = ((int)$loginrisk) + ((int)$pacingrisk) + ((int)$pretestrisk);
        if (!$pacingrisk && $riskscore < 2) {
            continue;
        }

        $reasons = [];
        if ($loginrisk) {
            $reasons[] = ($lastaccess > 0)
                ? ('No login for ' . $dayssincelogin . ' days')
                : 'No login activity recorded';
        }
        if ($pacingrisk) {
            $daysleft = max(0, (int)ceil($timeleft / DAYSECS));
            $reasons[] = 'Deadline in ' . $daysleft . ' days, completion only ' . rtrim(rtrim(number_format($completionpct, 1, '.', ''), '0'), '.') . '%';
        }
        if ($pretestrisk && $pretestpct !== null && $coursepretestavg !== null) {
            $reasons[] = 'Pre-test ' . rtrim(rtrim(number_format($pretestpct, 1, '.', ''), '0'), '.') . '% vs cohort ' . rtrim(rtrim(number_format($coursepretestavg, 1, '.', ''), '0'), '.') . '%';
        }

        $rows[] = [
            'courseid' => $courseid,
            'userid' => $userid,
            'riskscore' => $riskscore,
            'loginrisk' => $loginrisk ? 1 : 0,
            'pacingrisk' => $pacingrisk ? 1 : 0,
            'pretestrisk' => $pretestrisk ? 1 : 0,
            'dayssincelogin' => $dayssincelogin,
            'completionpct' => $completionpct,
            'deadlineat' => $deadlineat,
            'pretestpct' => $pretestpct,
            'pretestavgpct' => $coursepretestavg,
            'reasonsjson' => json_encode($reasons, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
    }

    usort($rows, static function(array $a, array $b): int {
        if ((int)$a['pacingrisk'] !== (int)$b['pacingrisk']) {
            return (int)$b['pacingrisk'] <=> (int)$a['pacingrisk'];
        }
        if ((int)$a['riskscore'] !== (int)$b['riskscore']) {
            return (int)$b['riskscore'] <=> (int)$a['riskscore'];
        }
        if ((int)$a['dayssincelogin'] !== (int)$b['dayssincelogin']) {
            return (int)$b['dayssincelogin'] <=> (int)$a['dayssincelogin'];
        }

        $agap = (float)($a['pretestavgpct'] ?? 0) - (float)($a['pretestpct'] ?? 0);
        $bgap = (float)($b['pretestavgpct'] ?? 0) - (float)($b['pretestpct'] ?? 0);
        if ($agap !== $bgap) {
            return $bgap <=> $agap;
        }

        return (float)$a['completionpct'] <=> (float)$b['completionpct'];
    });

    return $rows;
}

/**
 * Refreshes the at-risk cache for one course or all visible courses.
 *
 * @param int $courseid Optional course ID.
 * @return void
 */
function local_admindashboard_refresh_at_risk_cache(int $courseid = 0): void {
    global $DB;

    if (!local_admindashboard_local_atrisk_table_exists()) {
        return;
    }

    $courseids = [];
    if ($courseid > 0) {
        $courseids[] = $courseid;
    } else {
        $courseids = $DB->get_fieldset_sql(
            "SELECT id
               FROM {course}
              WHERE id > 1 AND visible = 1
           ORDER BY id ASC"
        );
        $courseids = array_map('intval', $courseids);
    }

    foreach ($courseids as $thiscourseid) {
        if (!local_admindashboard_is_course_running((int)$thiscourseid)) {
            $DB->delete_records('local_admindashboard_atrisk', ['courseid' => (int)$thiscourseid]);
            continue;
        }

        $rows = local_admindashboard_calculate_at_risk_rows_for_course((int)$thiscourseid);
        $DB->delete_records('local_admindashboard_atrisk', ['courseid' => (int)$thiscourseid]);
        foreach ($rows as $row) {
            $DB->insert_record('local_admindashboard_atrisk', (object)$row);
        }
    }
}

/**
 * Returns current at-risk participants from cache, refreshing when needed.
 *
 * @param int $courseid Course filter; use 0 for network-wide (top at-risk across all running visible courses).
 * @param string $department Optional department filter.
 * @param int $limit Max rows.
 * @return array<int,array<string,mixed>>
 */
function local_admindashboard_get_at_risk_participants(int $courseid, string $department, int $limit = 10): array {
    global $DB;

    if (!local_admindashboard_local_atrisk_table_exists()) {
        return [];
    }

    $department = trim($department);

    if ($courseid > 0) {
        if (!local_admindashboard_is_course_running($courseid)) {
            return [];
        }
        try {
            local_admindashboard_refresh_at_risk_cache($courseid);
        } catch (\Throwable $e) {
            return [];
        }
    } else {
        // Rebuilding every course on each request can time out; refresh periodically or when cache is empty.
        $now = time();
        try {
            $cachehas = (int)$DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {local_admindashboard_atrisk} ar
                   JOIN {course} c ON c.id = ar.courseid
                  WHERE c.id > 1 AND c.visible = 1"
            );
        } catch (\Throwable $e) {
            return [];
        }
        $lastfull = (int)(get_config('local_admindashboard', 'atrisk_net_refresh_ts') ?: 0);
        $interval = 300;
        if ($cachehas === 0 || $lastfull <= 0 || ($now - $lastfull) >= $interval) {
            try {
                local_admindashboard_refresh_at_risk_cache(0);
            } catch (\Throwable $e) {
                // Large sites may hit limits during a full rebuild; still return any rows already in cache.
            }
            set_config('atrisk_net_refresh_ts', $now, 'local_admindashboard');
        }
    }

    $nowts = time();
    // Use distinct placeholder names: PostgreSQL counts each :name occurrence separately.
    $params = [
        'risknowts_start' => $nowts,
        'risknowts_end' => $nowts,
        'riskcompletepct' => 100,
    ];
    $where = 'c.id > 1 AND c.visible = 1
          AND (c.startdate = 0 OR c.startdate <= :risknowts_start)
          AND (c.enddate = 0 OR c.enddate >= :risknowts_end)
          AND ar.completionpct < :riskcompletepct
          AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)';
    if ($courseid > 0) {
        $where .= ' AND ar.courseid = :courseid';
        $params['courseid'] = $courseid;
    }
    if ($department !== '') {
        $where .= ' AND u.department = :riskdepartment';
        $params['riskdepartment'] = $department;
    }

    $sql = "SELECT CONCAT(ar.courseid, '-', ar.userid) AS rowkey,
                   ar.courseid,
                   ar.userid,
                   ar.riskscore,
                   ar.loginrisk,
                   ar.pacingrisk,
                   ar.pretestrisk,
                   ar.dayssincelogin,
                   ar.completionpct,
                   ar.deadlineat,
                   ar.pretestpct,
                   ar.pretestavgpct,
                   ar.reasonsjson,
                   c.fullname AS coursefullname,
                   u.firstname,
                   u.lastname,
                   COALESCE(u.department, '') AS department
              FROM {local_admindashboard_atrisk} ar
              JOIN {course} c ON c.id = ar.courseid
              JOIN {user} u ON u.id = ar.userid
         LEFT JOIN {course_completions} cc ON cc.userid = ar.userid AND cc.course = ar.courseid
             WHERE {$where}
          ORDER BY ar.pacingrisk DESC,
                   ar.riskscore DESC,
                   ar.dayssincelogin DESC,
                   ar.completionpct ASC,
                   (COALESCE(ar.pretestavgpct, 0) - COALESCE(ar.pretestpct, 0)) DESC,
                   u.lastname ASC,
                   u.firstname ASC";

    $records = $DB->get_records_sql($sql, $params, 0, max(0, $limit));
    $rows = [];
    foreach ($records as $record) {
        $name = trim((string)$record->firstname . ' ' . (string)$record->lastname);
        if ($name === '') {
            $name = 'User ' . (int)$record->userid;
        }

        $reasons = json_decode((string)($record->reasonsjson ?? '[]'), true);
        if (!is_array($reasons)) {
            $reasons = [];
        }

        $rows[] = [
            'courseid' => (int)$record->courseid,
            'userid' => (int)$record->userid,
            'name' => $name,
            'department' => (string)($record->department ?? ''),
            'coursefullname' => (string)($record->coursefullname ?? ''),
            'risk_score' => (int)($record->riskscore ?? 0),
            'login_risk' => !empty($record->loginrisk),
            'pacing_risk' => !empty($record->pacingrisk),
            'pretest_risk' => !empty($record->pretestrisk),
            'days_since_login' => (int)($record->dayssincelogin ?? 0),
            'completion_pct' => (float)($record->completionpct ?? 0),
            'pretest_pct' => ($record->pretestpct !== null) ? (float)$record->pretestpct : null,
            'pretest_avg_pct' => ($record->pretestavgpct !== null) ? (float)$record->pretestavgpct : null,
            'deadline_at' => (int)($record->deadlineat ?? 0),
            'warning_icon' => '⚠',
            'reasons' => array_values($reasons),
        ];
    }

    return $rows;
}

/**
 * Returns recent user activity for the dashboard live feed.
 *
 * @param int $courseid Optional course filter.
 * @param string $department Optional department filter.
 * @param int $limit Max rows.
 * @return array<int,array{name:string,action:string,course:string,timestamp:string,avatar:string}>
 */
function local_admindashboard_get_live_feed_rows(int $courseid, string $department, int $limit = 8): array {
    global $CFG, $DB;

    require_once($CFG->libdir . '/xmldb/xmldb_table.php');

    $manager = $DB->get_manager();
    if (!$manager->table_exists(new xmldb_table('logstore_standard_log'))) {
        return [];
    }

    [$userwhere, $userparams] = local_admindashboard_build_user_filter($department, true);
    $params = $userparams;
    $coursefilter = '';
    $params['livefeedsince'] = time() - (7 * DAYSECS);
    $genderselect = "'' AS genderdata";
    $genderjoin = '';
    $genderfieldid = local_admindashboard_get_gender_field_id();
    if ($genderfieldid > 0) {
        $genderselect = 'COALESCE(uig.data, "") AS genderdata';
        $genderjoin = ' LEFT JOIN {user_info_data} uig ON uig.userid = u.id AND uig.fieldid = :livefeedgenderfieldid';
        $params['livefeedgenderfieldid'] = $genderfieldid;
    }
    if ($courseid > 0) {
        $coursefilter = ' AND l.courseid = :livefeedcourseid';
        $params['livefeedcourseid'] = $courseid;
    }

    try {
        $rows = $DB->get_records_sql(
            "SELECT l.id,
                    l.action,
                    l.objecttable,
                    l.component,
                    l.eventname,
                    l.timecreated,
                    u.firstname,
                    u.lastname,
                    {$genderselect},
                    COALESCE(c.fullname, '') AS coursefullname
               FROM {logstore_standard_log} l
               JOIN {user} u ON u.id = l.userid
            {$genderjoin}
          LEFT JOIN {course} c ON c.id = l.courseid
              WHERE {$userwhere}
                AND u.deleted = 0
                AND u.suspended = 0
                AND u.username <> 'guest'
                AND l.courseid > 1
                AND l.timecreated >= :livefeedsince
                {$coursefilter}
                AND (
                    l.component LIKE 'mod_%'
                    OR l.eventname LIKE '\\\\core\\\\event\\\\course_module%'
                )
           ORDER BY l.timecreated DESC",
            $params,
            0,
            max(1, $limit)
        );
    } catch (Exception $e) {
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        $name = trim((string)$row->firstname . ' ' . (string)$row->lastname);
        if ($name === '') {
            $name = 'User';
        }

        $component = trim((string)($row->component ?? ''));
        $module = '';
        if (strpos($component, 'mod_') === 0) {
            $module = str_replace('_', ' ', substr($component, 4));
        }
        $object = strtolower(trim((string)($row->objecttable ?? '')));
        $actionverb = strtolower(trim((string)($row->action ?? '')));
        if ($actionverb === '') {
            $actionverb = 'updated';
        }

        if ($module !== '') {
            $actiontext = $actionverb . ' ' . $module;
        } else if ($object !== '') {
            $actiontext = $actionverb . ' ' . str_replace('_', ' ', $object);
        } else {
            $actiontext = $actionverb . ' activity';
        }

        $genderraw = strtolower(trim((string)($row->genderdata ?? '')));
        $avatar = 'neutral';
        if (in_array($genderraw, ['male', 'm', 'man', 'boy'], true)) {
            $avatar = 'male';
        } else if (in_array($genderraw, ['female', 'f', 'woman', 'girl'], true)) {
            $avatar = 'female';
        }

        $elapsed = max(0, time() - (int)$row->timecreated);
        if ($elapsed < HOURSECS) {
            $timestamp = max(1, (int)floor($elapsed / MINSECS)) . ' min ago';
        } else if ($elapsed < DAYSECS) {
            $timestamp = (int)floor($elapsed / HOURSECS) . ' hr ago';
        } else {
            $days = (int)floor($elapsed / DAYSECS);
            $timestamp = $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }

        $items[] = [
            'name' => $name,
            'action' => $actiontext,
            'course' => (string)($row->coursefullname ?? ''),
            'timestamp' => $timestamp,
            'avatar' => $avatar,
        ];
    }

    return $items;
}

/**
 * Picks a likely "final assessment" quiz grade item for a course.
 *
 * This is used when course completion tracking and/or course gradepass isn't configured,
 * but pass/fail outcomes can be inferred from an assessment quiz with gradepass.
 */
function local_admindashboard_pick_course_assessment_quiz(int $courseid, string $userwhere, array $userparams): ?stdClass {
        global $DB;

        if ($courseid <= 0) {
                return null;
        }

        // Pick the last module's last quiz/test in the course (highest section, then latest cmid).
        // Note: we only consider grade items with gradepass > 0.
        $params = ['courseid_assess' => $courseid];
        $sql = "SELECT
                                q.id AS quizid,
                                q.name AS quizname,
                                gi.id AS gradeitemid,
                                gi.gradepass AS gradepass,
                                COUNT(DISTINCT gg.userid) AS attempted,
                                MAX(cm.id) AS cmid,
                                MAX(cs.section) AS sectionnum
                            FROM {course_modules} cm
                            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                            JOIN {quiz} q ON q.id = cm.instance
                            JOIN {course_sections} cs ON cs.id = cm.section
                            JOIN {grade_items} gi
                                ON gi.courseid = cm.course
                             AND gi.itemtype = 'mod'
                             AND gi.itemmodule = 'quiz'
                             AND gi.iteminstance = q.id
                       LEFT JOIN {grade_grades} gg
                                ON gg.itemid = gi.id
                               AND gg.finalgrade IS NOT NULL
                         WHERE cm.course = :courseid_assess
                             AND cm.deletioninprogress = 0
                             AND gi.gradepass IS NOT NULL AND gi.gradepass > 0
                    GROUP BY q.id, q.name, gi.id, gi.gradepass
                    ORDER BY sectionnum DESC, cmid DESC, attempted DESC";

        $recs = $DB->get_records_sql($sql, $params, 0, 1);
        if (!$recs) {
                return null;
        }
        return reset($recs);
}

/**
 * Counts users who have (1) passed the given grade item and (2) have a certificate issued
 * from a certificate activity in the course.
 *
 * Supports common Moodle plugins:
 * - mod_customcert: {customcert} + {customcert_issues}
 * - mod_certificate: {certificate} + {certificate_issues}
 */
function local_admindashboard_count_certified_issued(int $courseid, string $userwhere, array $userparams, int $passgradeitemid, float $passgradepass): int {
    global $CFG, $DB;

    if ($courseid <= 0 || $passgradeitemid <= 0 || $passgradepass <= 0) {
        return 0;
    }

    // Avoid referencing plugin tables when the plugin isn't installed.
    require_once($CFG->libdir . '/xmldb/xmldb_table.php');
    require_once($CFG->libdir . '/xmldb/xmldb_field.php');
    $manager = $DB->get_manager();

    $hascustomcerttables = $manager->table_exists(new xmldb_table('customcert'))
        && $manager->table_exists(new xmldb_table('customcert_issues'));
    $hascertificatetables = $manager->table_exists(new xmldb_table('certificate'))
        && $manager->table_exists(new xmldb_table('certificate_issues'));

    $hastoolcerttables = $manager->table_exists(new xmldb_table('tool_certificate_templates'))
        && $manager->table_exists(new xmldb_table('tool_certificate_issues'));

    // Prefer customcert if present in the course; otherwise fall back to certificate.
    if ($hascustomcerttables) {
        $hascustomcertincourse = $DB->record_exists_sql(
            "SELECT 1
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'customcert'
              WHERE cm.course = :cc_course_cm
                AND cm.deletioninprogress = 0",
            ['cc_course_cm' => $courseid]
        );
        if ($hascustomcertincourse) {
            $params = $userparams + [
                'cc_course_enrol' => $courseid,
                'cc_course_mod' => $courseid,
                'cc_gradeitemid' => $passgradeitemid,
                'cc_gradepass' => $passgradepass,
            ];

            $sql = "SELECT COUNT(DISTINCT u.id)
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cc_course_enrol
                      JOIN {grade_grades} gg
                        ON gg.userid = u.id
                       AND gg.itemid = :cc_gradeitemid
                       AND gg.finalgrade IS NOT NULL
                       AND gg.finalgrade >= :cc_gradepass
                      JOIN {customcert_issues} ci ON ci.userid = u.id
                      JOIN {customcert} ccert ON ccert.id = ci.customcertid AND ccert.course = :cc_course_mod
                     WHERE {$userwhere}";

            return (int)$DB->count_records_sql($sql, $params);
        }
    }

    if ($hascertificatetables) {
        $hascertificateincourse = $DB->record_exists_sql(
            "SELECT 1
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'certificate'
              WHERE cm.course = :cert_course_cm
                AND cm.deletioninprogress = 0",
            ['cert_course_cm' => $courseid]
        );
        if ($hascertificateincourse) {
            $params = $userparams + [
                'cert_course_enrol' => $courseid,
                'cert_course_mod' => $courseid,
                'cert_gradeitemid' => $passgradeitemid,
                'cert_gradepass' => $passgradepass,
            ];

            $sql = "SELECT COUNT(DISTINCT u.id)
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cert_course_enrol
                      JOIN {grade_grades} gg
                        ON gg.userid = u.id
                       AND gg.itemid = :cert_gradeitemid
                       AND gg.finalgrade IS NOT NULL
                       AND gg.finalgrade >= :cert_gradepass
                      JOIN {certificate_issues} ci ON ci.userid = u.id
                      JOIN {certificate} cert ON cert.id = ci.certificateid AND cert.course = :cert_course_mod
                     WHERE {$userwhere}";

            return (int)$DB->count_records_sql($sql, $params);
        }
    }

    // Moodle certificate templates tool (often provides "Manage certificate templates").
    // Tables and fields can vary by version, so we only use fields if they exist.
    if ($hastoolcerttables) {
        $issues = new xmldb_table('tool_certificate_issues');
        $templates = new xmldb_table('tool_certificate_templates');

        $issueshascourseid = $manager->field_exists($issues, new xmldb_field('courseid'));
        $issueshascontextid = $manager->field_exists($issues, new xmldb_field('contextid'));
        $issueshastemplateid = $manager->field_exists($issues, new xmldb_field('templateid'));

        $templateshascourseid = $manager->field_exists($templates, new xmldb_field('courseid'));
        $templateshascontextid = $manager->field_exists($templates, new xmldb_field('contextid'));

        $coursecontextid = 0;
        if ($issueshascontextid || ($issueshastemplateid && $templateshascontextid)) {
            $ctx = $DB->get_record('context', ['contextlevel' => 50, 'instanceid' => $courseid], 'id', IGNORE_MISSING);
            $coursecontextid = (int)($ctx->id ?? 0);
        }

        $params = $userparams + [
            'tc_course_enrol' => $courseid,
            'tc_gradeitemid' => $passgradeitemid,
            'tc_gradepass' => $passgradepass,
        ];

        $joins = "";
        $where = "";

        if ($issueshascourseid) {
            $where = " AND tci.courseid = :tc_course_filter";
            $params['tc_course_filter'] = $courseid;
        } else if ($issueshascontextid && $coursecontextid > 0) {
            $where = " AND tci.contextid = :tc_context_filter";
            $params['tc_context_filter'] = $coursecontextid;
        } else if ($issueshastemplateid && ($templateshascourseid || ($templateshascontextid && $coursecontextid > 0))) {
            $joins = " JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid";
            if ($templateshascourseid) {
                $where = " AND tct.courseid = :tc_course_filter";
                $params['tc_course_filter'] = $courseid;
            } else {
                $where = " AND tct.contextid = :tc_context_filter";
                $params['tc_context_filter'] = $coursecontextid;
            }
        } else {
            // Can't safely scope issues to a course.
            return 0;
        }

        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :tc_course_enrol
                  JOIN {grade_grades} gg
                    ON gg.userid = u.id
                   AND gg.itemid = :tc_gradeitemid
                   AND gg.finalgrade IS NOT NULL
                   AND gg.finalgrade >= :tc_gradepass
                  JOIN {tool_certificate_issues} tci ON tci.userid = u.id
                  {$joins}
                 WHERE {$userwhere}{$where}";

        return (int)$DB->count_records_sql($sql, $params);
    }

    return 0;
}

/**
 * Counts issued certificate records in visible courses for the filtered learner cohort.
 *
 * Supports mod_customcert, mod_certificate, and Moodle tool_certificate when issue rows can be
 * scoped to a course.
 */
function local_admindashboard_count_certificate_issues(int $courseid, string $userwhere, array $userparams): int {
    global $CFG, $DB;

    require_once($CFG->libdir . '/xmldb/xmldb_table.php');
    require_once($CFG->libdir . '/xmldb/xmldb_field.php');
    $manager = $DB->get_manager();
    $total = 0;

    if ($manager->table_exists(new xmldb_table('customcert'))
            && $manager->table_exists(new xmldb_table('customcert_issues'))) {
        $params = $userparams;
        $coursefilter = "cc.course > 1";
        if ($courseid > 0) {
            $params['certissue_custom_courseid'] = $courseid;
            $coursefilter = "cc.course = :certissue_custom_courseid";
        }
        $sql = "SELECT COUNT(DISTINCT ci.id)
                  FROM {user} u
                  JOIN {customcert_issues} ci ON ci.userid = u.id
                  JOIN {customcert} cc ON cc.id = ci.customcertid
                  JOIN {course} c ON c.id = cc.course AND c.visible = 1
                 WHERE {$userwhere}
                   AND {$coursefilter}";
        $total += (int)$DB->count_records_sql($sql, $params);
    }

    if ($manager->table_exists(new xmldb_table('certificate'))
            && $manager->table_exists(new xmldb_table('certificate_issues'))) {
        $params = $userparams;
        $coursefilter = "cert.course > 1";
        if ($courseid > 0) {
            $params['certissue_mod_courseid'] = $courseid;
            $coursefilter = "cert.course = :certissue_mod_courseid";
        }
        $sql = "SELECT COUNT(DISTINCT ci.id)
                  FROM {user} u
                  JOIN {certificate_issues} ci ON ci.userid = u.id
                  JOIN {certificate} cert ON cert.id = ci.certificateid
                  JOIN {course} c ON c.id = cert.course AND c.visible = 1
                 WHERE {$userwhere}
                   AND {$coursefilter}";
        $total += (int)$DB->count_records_sql($sql, $params);
    }

    if ($manager->table_exists(new xmldb_table('tool_certificate_issues'))) {
        $issues = new xmldb_table('tool_certificate_issues');
        $templates = new xmldb_table('tool_certificate_templates');
        $issueshascourseid = $manager->field_exists($issues, new xmldb_field('courseid'));
        $issueshascontextid = $manager->field_exists($issues, new xmldb_field('contextid'));
        $issueshastemplateid = $manager->field_exists($issues, new xmldb_field('templateid'));
        $templatesexist = $manager->table_exists($templates);
        $templateshascourseid = $templatesexist && $manager->field_exists($templates, new xmldb_field('courseid'));
        $templateshascontextid = $templatesexist && $manager->field_exists($templates, new xmldb_field('contextid'));

        $joins = '';
        $where = '';
        $params = $userparams;
        if ($issueshascourseid) {
            $joins = "JOIN {course} c ON c.id = tci.courseid AND c.visible = 1";
            $where = $courseid > 0 ? " AND c.id = :certissue_tool_courseid" : " AND c.id > 1";
        } else if ($issueshascontextid) {
            $joins = "JOIN {context} ctx ON ctx.id = tci.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                      JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1";
            $where = $courseid > 0 ? " AND c.id = :certissue_tool_courseid" : " AND c.id > 1";
        } else if ($issueshastemplateid && $templateshascourseid) {
            $joins = "JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid
                      JOIN {course} c ON c.id = tct.courseid AND c.visible = 1";
            $where = $courseid > 0 ? " AND c.id = :certissue_tool_courseid" : " AND c.id > 1";
        } else if ($issueshastemplateid && $templateshascontextid) {
            $joins = "JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid
                      JOIN {context} ctx ON ctx.id = tct.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                      JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1";
            $where = $courseid > 0 ? " AND c.id = :certissue_tool_courseid" : " AND c.id > 1";
        }

        if ($joins !== '') {
            if ($courseid > 0) {
                $params['certissue_tool_courseid'] = $courseid;
            }
            $sql = "SELECT COUNT(DISTINCT tci.id)
                      FROM {user} u
                      JOIN {tool_certificate_issues} tci ON tci.userid = u.id
                      {$joins}
                     WHERE {$userwhere}{$where}";
            $total += (int)$DB->count_records_sql($sql, $params);
        }
    }

    return $total;
}

/**
 * Returns certificate issue rows for KPI drill-downs.
 *
 * @return array<int,array{id:int,name:string,department:string,clinicname:string,course_name:string}>
 */
function local_admindashboard_get_certificate_issue_user_rows(
    int $courseid,
    string $userwhere,
    array $userparams,
    string $clinicselect,
    string $clinicjoin,
    array $clinicparams
): array {
    global $CFG, $DB;

    require_once($CFG->libdir . '/xmldb/xmldb_table.php');
    require_once($CFG->libdir . '/xmldb/xmldb_field.php');
    $manager = $DB->get_manager();
    $rows = [];

    $append = static function(array $records, string $source) use (&$rows): void {
        foreach ($records as $record) {
            $rows[] = [
                'id' => (int)($record->issueid ?? $record->id ?? 0),
                'name' => trim(fullname($record)),
                'department' => (string)($record->department ?? ''),
                'clinicname' => (string)($record->clinicname ?? ''),
                'course_name' => strip_tags((string)($record->coursefullname ?? '')),
                'enrolment_label' => $source,
            ];
        }
    };

    if ($manager->table_exists(new xmldb_table('customcert'))
            && $manager->table_exists(new xmldb_table('customcert_issues'))) {
        $params = $userparams + $clinicparams;
        $coursefilter = "cc.course > 1";
        if ($courseid > 0) {
            $params['certrow_custom_courseid'] = $courseid;
            $coursefilter = "cc.course = :certrow_custom_courseid";
        }
        $sql = "SELECT ci.id AS issueid, u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                       {$clinicselect}, c.fullname AS coursefullname
                  FROM {user} u
                  {$clinicjoin}
                  JOIN {customcert_issues} ci ON ci.userid = u.id
                  JOIN {customcert} cc ON cc.id = ci.customcertid
                  JOIN {course} c ON c.id = cc.course AND c.visible = 1
                 WHERE {$userwhere}
                   AND {$coursefilter}
              ORDER BY c.fullname ASC, u.lastname ASC, u.firstname ASC";
        $append($DB->get_records_sql($sql, $params), 'Custom certificate');
    }

    if ($manager->table_exists(new xmldb_table('certificate'))
            && $manager->table_exists(new xmldb_table('certificate_issues'))) {
        $params = $userparams + $clinicparams;
        $coursefilter = "cert.course > 1";
        if ($courseid > 0) {
            $params['certrow_mod_courseid'] = $courseid;
            $coursefilter = "cert.course = :certrow_mod_courseid";
        }
        $sql = "SELECT ci.id AS issueid, u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                       {$clinicselect}, c.fullname AS coursefullname
                  FROM {user} u
                  {$clinicjoin}
                  JOIN {certificate_issues} ci ON ci.userid = u.id
                  JOIN {certificate} cert ON cert.id = ci.certificateid
                  JOIN {course} c ON c.id = cert.course AND c.visible = 1
                 WHERE {$userwhere}
                   AND {$coursefilter}
              ORDER BY c.fullname ASC, u.lastname ASC, u.firstname ASC";
        $append($DB->get_records_sql($sql, $params), 'Certificate');
    }

    if ($manager->table_exists(new xmldb_table('tool_certificate_issues'))) {
        $issues = new xmldb_table('tool_certificate_issues');
        $templates = new xmldb_table('tool_certificate_templates');
        $issueshascourseid = $manager->field_exists($issues, new xmldb_field('courseid'));
        $issueshascontextid = $manager->field_exists($issues, new xmldb_field('contextid'));
        $issueshastemplateid = $manager->field_exists($issues, new xmldb_field('templateid'));
        $templatesexist = $manager->table_exists($templates);
        $templateshascourseid = $templatesexist && $manager->field_exists($templates, new xmldb_field('courseid'));
        $templateshascontextid = $templatesexist && $manager->field_exists($templates, new xmldb_field('contextid'));

        $joins = '';
        $where = '';
        $params = $userparams + $clinicparams;
        if ($issueshascourseid) {
            $joins = "JOIN {course} c ON c.id = tci.courseid AND c.visible = 1";
            $where = $courseid > 0 ? " AND c.id = :certrow_tool_courseid" : " AND c.id > 1";
        } else if ($issueshascontextid) {
            $joins = "JOIN {context} ctx ON ctx.id = tci.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                      JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1";
            $where = $courseid > 0 ? " AND c.id = :certrow_tool_courseid" : " AND c.id > 1";
        } else if ($issueshastemplateid && $templateshascourseid) {
            $joins = "JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid
                      JOIN {course} c ON c.id = tct.courseid AND c.visible = 1";
            $where = $courseid > 0 ? " AND c.id = :certrow_tool_courseid" : " AND c.id > 1";
        } else if ($issueshastemplateid && $templateshascontextid) {
            $joins = "JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid
                      JOIN {context} ctx ON ctx.id = tct.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                      JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1";
            $where = $courseid > 0 ? " AND c.id = :certrow_tool_courseid" : " AND c.id > 1";
        }

        if ($joins !== '') {
            if ($courseid > 0) {
                $params['certrow_tool_courseid'] = $courseid;
            }
            $sql = "SELECT tci.id AS issueid, u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                           {$clinicselect}, c.fullname AS coursefullname
                      FROM {user} u
                      {$clinicjoin}
                      JOIN {tool_certificate_issues} tci ON tci.userid = u.id
                      {$joins}
                     WHERE {$userwhere}{$where}
                  ORDER BY c.fullname ASC, u.lastname ASC, u.firstname ASC";
            $append($DB->get_records_sql($sql, $params), 'Tool certificate');
        }
    }

    return $rows;
}

/**
 * Returns the grade-source configuration that drives KPI membership for a course/module filter.
 *
 * @param int $courseid Selected course ID.
 * @param int $moduleid Selected module ID.
 * @param string $userwhere Base user filter SQL.
 * @param array $userparams Base user filter params.
 * @return array{mode:string,gradeitemid:int,gradepass:float,selectedmodname:string,selectedinstance:int}
 */
function local_admindashboard_get_kpi_grade_source(int $courseid, int $moduleid, string $userwhere, array $userparams): array {
    global $DB;

    $source = [
        'mode' => 'none',
        'gradeitemid' => 0,
        'gradepass' => 0.0,
        'selectedmodname' => '',
        'selectedinstance' => 0,
    ];

    if ($courseid <= 0) {
        return $source;
    }

    $selectedmodname = '';
    $selectedinstance = 0;
    if ($moduleid > 0) {
        $cm = $DB->get_record_sql(
            "SELECT cm.id, cm.course, m.name AS modname, cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.id = :cmid",
            ['cmid' => $moduleid],
            IGNORE_MISSING
        );
        if ($cm && (int)$cm->course === $courseid) {
            $selectedmodname = (string)$cm->modname;
            $selectedinstance = (int)$cm->instance;
        }
    }

    $source['selectedmodname'] = $selectedmodname;
    $source['selectedinstance'] = $selectedinstance;

    if ($moduleid > 0 && $selectedmodname === 'quiz' && $selectedinstance > 0) {
        $girecs = $DB->get_records_sql(
            "SELECT gi.id, gi.gradepass
               FROM {grade_items} gi
              WHERE gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND gi.iteminstance = :quizid
           ORDER BY gi.id ASC",
            ['quizid' => $selectedinstance],
            0,
            1
        );
        $gi = $girecs ? reset($girecs) : null;
        if ($gi) {
            $source['mode'] = 'quiz';
            $source['gradeitemid'] = (int)($gi->id ?? 0);
            $source['gradepass'] = (float)($gi->gradepass ?? 0);
            return $source;
        }
    }

    if ($moduleid <= 0) {
        $assessment = local_admindashboard_pick_course_assessment_quiz($courseid, $userwhere, $userparams);
        if ($assessment && !empty($assessment->gradeitemid)) {
            $source['mode'] = 'course_assessment';
            $source['gradeitemid'] = (int)$assessment->gradeitemid;
            $source['gradepass'] = (float)($assessment->gradepass ?? 0);
            return $source;
        }
    }

    $coursegi = $DB->get_record_sql(
        "SELECT gi.id, gi.gradepass
           FROM {grade_items} gi
          WHERE gi.courseid = :courseid
            AND gi.itemtype = 'course'
       ORDER BY gi.id ASC",
        ['courseid' => $courseid],
        IGNORE_MULTIPLE
    );
    if ($coursegi) {
        $source['mode'] = 'course';
        $source['gradeitemid'] = (int)($coursegi->id ?? 0);
        $source['gradepass'] = (float)($coursegi->gradepass ?? 0);
    }

    return $source;
}

/**
 * Returns a list of certified users for a course and grade item.
 *
 * @param int $courseid Selected course ID.
 * @param string $userwhere Base user filter SQL.
 * @param array $userparams Base user filter params.
 * @param int $passgradeitemid Grade item ID.
 * @param float $passgradepass Grade pass threshold.
 * @return array<int,array{id:int,name:string,department:string}>
 */
function local_admindashboard_get_certified_user_rows(int $courseid, string $userwhere, array $userparams, int $passgradeitemid, float $passgradepass): array {
    global $CFG, $DB;

    if ($courseid <= 0 || $passgradeitemid <= 0 || $passgradepass <= 0) {
        return [];
    }

    require_once($CFG->libdir . '/xmldb/xmldb_table.php');
    require_once($CFG->libdir . '/xmldb/xmldb_field.php');
    $manager = $DB->get_manager();

    $select = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department";

    $buildrows = static function(array $records): array {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'id' => (int)$record->id,
                'name' => trim(fullname($record)),
                'department' => (string)($record->department ?? ''),
            ];
        }
        return $rows;
    };

    if ($manager->table_exists(new xmldb_table('customcert')) && $manager->table_exists(new xmldb_table('customcert_issues'))) {
        $hascustomcertincourse = $DB->record_exists_sql(
            "SELECT 1
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'customcert'
              WHERE cm.course = :courseid
                AND cm.deletioninprogress = 0",
            ['courseid' => $courseid]
        );
        if ($hascustomcertincourse) {
            $params = $userparams + [
                'courseid' => $courseid,
                'gradeitemid' => $passgradeitemid,
                'gradepass' => $passgradepass,
            ];
            $sql = $select . "
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                      JOIN {grade_grades} gg
                        ON gg.userid = u.id
                       AND gg.itemid = :gradeitemid
                       AND gg.finalgrade IS NOT NULL
                       AND gg.finalgrade >= :gradepass
                      JOIN {customcert_issues} ci ON ci.userid = u.id
                      JOIN {customcert} ccert ON ccert.id = ci.customcertid AND ccert.course = :courseid
                     WHERE {$userwhere}
                  ORDER BY u.lastname ASC, u.firstname ASC";
            return local_admindashboard_kpi_rows_add_course_enrolment_detail($courseid, $buildrows($DB->get_records_sql($sql, $params)));
        }
    }

    if ($manager->table_exists(new xmldb_table('certificate')) && $manager->table_exists(new xmldb_table('certificate_issues'))) {
        $hascertificateincourse = $DB->record_exists_sql(
            "SELECT 1
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'certificate'
              WHERE cm.course = :courseid
                AND cm.deletioninprogress = 0",
            ['courseid' => $courseid]
        );
        if ($hascertificateincourse) {
            $params = $userparams + [
                'courseid' => $courseid,
                'gradeitemid' => $passgradeitemid,
                'gradepass' => $passgradepass,
            ];
            $sql = $select . "
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                      JOIN {grade_grades} gg
                        ON gg.userid = u.id
                       AND gg.itemid = :gradeitemid
                       AND gg.finalgrade IS NOT NULL
                       AND gg.finalgrade >= :gradepass
                      JOIN {certificate_issues} ci ON ci.userid = u.id
                      JOIN {certificate} cert ON cert.id = ci.certificateid AND cert.course = :courseid
                     WHERE {$userwhere}
                  ORDER BY u.lastname ASC, u.firstname ASC";
            return local_admindashboard_kpi_rows_add_course_enrolment_detail($courseid, $buildrows($DB->get_records_sql($sql, $params)));
        }
    }

    if ($manager->table_exists(new xmldb_table('tool_certificate_templates'))
            && $manager->table_exists(new xmldb_table('tool_certificate_issues'))) {
        $issues = new xmldb_table('tool_certificate_issues');
        $templates = new xmldb_table('tool_certificate_templates');
        $issueshascourseid = $manager->field_exists($issues, new xmldb_field('courseid'));
        $issueshascontextid = $manager->field_exists($issues, new xmldb_field('contextid'));
        $issueshastemplateid = $manager->field_exists($issues, new xmldb_field('templateid'));
        $templateshascourseid = $manager->field_exists($templates, new xmldb_field('courseid'));
        $templateshascontextid = $manager->field_exists($templates, new xmldb_field('contextid'));

        $coursecontextid = 0;
        if ($issueshascontextid || ($issueshastemplateid && $templateshascontextid)) {
            $ctx = $DB->get_record('context', ['contextlevel' => CONTEXT_COURSE, 'instanceid' => $courseid], 'id', IGNORE_MISSING);
            $coursecontextid = (int)($ctx->id ?? 0);
        }

        $joins = '';
        $where = '';
        $params = $userparams + [
            'courseid' => $courseid,
            'gradeitemid' => $passgradeitemid,
            'gradepass' => $passgradepass,
        ];
        if ($issueshascourseid) {
            $where = ' AND tci.courseid = :coursefilter';
            $params['coursefilter'] = $courseid;
        } else if ($issueshascontextid && $coursecontextid > 0) {
            $where = ' AND tci.contextid = :contextfilter';
            $params['contextfilter'] = $coursecontextid;
        } else if ($issueshastemplateid && ($templateshascourseid || ($templateshascontextid && $coursecontextid > 0))) {
            $joins = ' JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid';
            if ($templateshascourseid) {
                $where = ' AND tct.courseid = :coursefilter';
                $params['coursefilter'] = $courseid;
            } else {
                $where = ' AND tct.contextid = :contextfilter';
                $params['contextfilter'] = $coursecontextid;
            }
        }

        if ($where !== '') {
            $sql = $select . "
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                      JOIN {grade_grades} gg
                        ON gg.userid = u.id
                       AND gg.itemid = :gradeitemid
                       AND gg.finalgrade IS NOT NULL
                       AND gg.finalgrade >= :gradepass
                      JOIN {tool_certificate_issues} tci ON tci.userid = u.id
                      {$joins}
                     WHERE {$userwhere}{$where}
                  ORDER BY u.lastname ASC, u.firstname ASC";
            return local_admindashboard_kpi_rows_add_course_enrolment_detail($courseid, $buildrows($DB->get_records_sql($sql, $params)));
        }
    }

    return [];
}

/**
 * Localised display name for an enrol instance row (enrolment method).
 */
function local_admindashboard_format_enrol_instance_label(\stdClass $enrol): string {
    global $CFG;
    if (empty($enrol->id) && empty($enrol->enrol)) {
        return '';
    }
    try {
        require_once($CFG->dirroot . '/lib/enrollib.php');
        $plugin = enrol_get_plugin($enrol->enrol);
        if ($plugin) {
            return (string)$plugin->get_instance_name($enrol);
        }
    } catch (\Throwable $e) {
        // Some enrol plugins or missing course contexts can throw; fall back to plugin id.
    }
    return (string)($enrol->enrol ?? '');
}

/**
 * Adds plain course title and active enrolment method labels for course-scoped KPI rows.
 *
 * @param int $courseid Course ID; returns $rows unchanged when <= 0.
 * @param array<int,array<string,mixed>> $rows Rows with integer 'id' (userid).
 * @return array<int,array<string,mixed>>
 */
function local_admindashboard_kpi_rows_add_course_enrolment_detail(int $courseid, array $rows): array {
    global $DB;

    if ($courseid <= 0 || $rows === []) {
        return $rows;
    }

    $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname', IGNORE_MISSING);
    $coursename = $course ? strip_tags((string)($course->fullname ?? '')) : '';

    $uids = [];
    foreach ($rows as $row) {
        if (!empty($row['id'])) {
            $uids[(int)$row['id']] = true;
        }
    }
    $uidlist = array_keys($uids);

    $byuser = [];
    $eids = [];
    if ($uidlist !== []) {
        list($insql, $inparams) = $DB->get_in_or_equal($uidlist, SQL_PARAMS_NAMED, 'kuen');
        $sql = "SELECT ue.userid AS uid, e.id AS eid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cid
                 WHERE ue.status = 0 AND ue.userid {$insql}";
        $params = ['cid' => $courseid] + $inparams;
        $pairs = $DB->get_records_sql($sql, $params);
        foreach ($pairs as $p) {
            $uid = (int)($p->uid ?? 0);
            $eid = (int)($p->eid ?? 0);
            if ($uid > 0 && $eid > 0) {
                if (!isset($byuser[$uid])) {
                    $byuser[$uid] = [];
                }
                $byuser[$uid][$eid] = true;
                $eids[$eid] = true;
            }
        }
    }

    $enrolbyid = [];
    if ($eids !== []) {
        list($einsql, $einparams) = $DB->get_in_or_equal(array_keys($eids), SQL_PARAMS_NAMED, 'kuenr');
        $enrolrows = $DB->get_records_sql("SELECT * FROM {enrol} WHERE id {$einsql}", $einparams);
        foreach ($enrolrows as $er) {
            $enrolbyid[(int)$er->id] = $er;
        }
    }

    foreach ($rows as $k => $row) {
        $uid = (int)($row['id'] ?? 0);
        $labels = [];
        if ($uid > 0 && !empty($byuser[$uid])) {
            foreach (array_keys($byuser[$uid]) as $eid) {
                $er = $enrolbyid[$eid] ?? null;
                if ($er) {
                    $lbl = local_admindashboard_format_enrol_instance_label($er);
                    if ($lbl !== '') {
                        $labels[] = $lbl;
                    }
                }
            }
        }
        $row['course_name'] = $coursename;
        $row['enrolment_label'] = implode(' · ', array_values(array_unique($labels)));
        $rows[$k] = $row;
    }

    return $rows;
}

/**
 * Fetches all SQL rows preserving duplicates (get_records_sql keys by first column only).
 *
 * @return array<int,\stdClass>
 */
function local_admindashboard_sql_fetch_all_rows(string $sql, array $params = []): array {
    global $DB;

    $out = [];
    try {
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $out[] = $row;
        }
        $rs->close();
    } catch (\Throwable $e) {
        return [];
    }
    return $out;
}

/**
 * Convert site overview records into KPI JSON rows.
 *
 * @param array<int,\stdClass> $records Rows with id, firstname, lastname, department, clinicname, coursefullname (optional).
 * @param bool $collapseusers When true, one user appears once with course names combined.
 * @return array<int,array{id:int,name:string,department:string,clinicname:string,course_name:string,enrolment_label:string}>
 */
function local_admindashboard_site_overview_records_to_kpi_rows(array $records, bool $collapseusers = true): array {
    if (!$collapseusers) {
        $out = [];
        foreach ($records as $r) {
            $out[] = [
                'id' => (int)($r->id ?? 0),
                'name' => trim(fullname($r)),
                'department' => (string)($r->department ?? ''),
                'clinicname' => (string)($r->clinicname ?? ''),
                'course_name' => strip_tags((string)($r->coursefullname ?? '')),
                'enrolment_label' => (string)($r->enrolmentlabel ?? ''),
            ];
        }
        return $out;
    }

    $byuser = [];
    foreach ($records as $r) {
        $uid = (int)($r->id ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $cfn = strip_tags((string)($r->coursefullname ?? ''));
        if (!isset($byuser[$uid])) {
            $byuser[$uid] = [
                'id' => $uid,
                'name' => trim(fullname($r)),
                'department' => (string)($r->department ?? ''),
                'clinicname' => (string)($r->clinicname ?? ''),
                'courses' => [],
            ];
        }
        if ($cfn !== '') {
            $byuser[$uid]['courses'][$cfn] = true;
        }
    }
    $out = [];
    foreach ($byuser as $u) {
        $names = array_keys($u['courses']);
        sort($names, SORT_STRING | SORT_FLAG_CASE);
        $display = $names;
        if (count($display) > 6) {
            $display = array_slice($display, 0, 6);
            $display[] = '…';
        }
        $out[] = [
            'id' => $u['id'],
            'name' => $u['name'],
            'department' => $u['department'],
            'clinicname' => $u['clinicname'],
            'course_name' => $names === [] ? '' : implode(' · ', $display),
            'enrolment_label' => '',
        ];
    }
    usort($out, static function(array $a, array $b): int {
        $cmp = strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        return $cmp !== 0 ? $cmp : ($a['id'] <=> $b['id']);
    });
    return $out;
}

/**
 * Site overview Dropped Midway: distinct user count matching {@see local_admindashboard_get_kpi_user_rows_site_overview}('dropped_midway').
 *
 * Each participant is counted at most once for the whole site, even if they match the resigned / withdrawn
 * pattern in several courses (same behaviour as the drill-down table after per-user merge).
 *
 * The legacy formula (participants − attempted) + {@see local_admindashboard_sum_resigned_midcourse_all_courses} mixed
 * distinct users with (user,course) pair counts and overstated the KPI vs the merged user table.
 *
 * @return int
 */
function local_admindashboard_count_site_overview_dropped_midway_distinct_users(string $userwhere, array $userparams): int {
    global $DB;

    $kpirow = $DB->get_record_sql(
        "SELECT COUNT(DISTINCT CASE
                    WHEN cc.timestarted > 0 OR cc.timecompleted > 0 THEN u.id
                    ELSE NULL
                END) AS attempted
           FROM {user} u
           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
           JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
      LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
          WHERE {$userwhere}",
        $userparams
    );
    $attemptedprimary = (int)($kpirow->attempted ?? 0);

    $clinicfieldid = local_admindashboard_get_clinic_field_id();
    $clinicselect = 'COALESCE(u.institution, \'\') AS clinicname';
    $clinicjoin = '';
    $clinicparams = [];
    if ($clinicfieldid > 0) {
        $clinicselect = "COALESCE(NULLIF(uic.data, ''), u.institution, '') AS clinicname";
        $clinicjoin = ' LEFT JOIN {user_info_data} uic ON uic.userid = u.id AND uic.fieldid = :clinicfieldid';
        $clinicparams['clinicfieldid'] = $clinicfieldid;
    }

    $merged = [];
    if ($attemptedprimary > 0) {
        foreach (local_admindashboard_site_overview_completion_not_attempted_records(
            $userwhere,
            $userparams,
            $clinicselect,
            $clinicjoin,
            $clinicparams
        ) as $r) {
            $merged[(int)$r->id] = true;
        }
    } else {
        foreach (local_admindashboard_site_overview_quiz_not_attempted_records(
            $userwhere,
            $userparams,
            $clinicselect,
            $clinicjoin,
            $clinicparams
        ) as $r) {
            $merged[(int)$r->id] = true;
        }
    }
    foreach (local_admindashboard_site_overview_resigned_user_records(
        $userwhere,
        $userparams,
        $clinicselect,
        $clinicjoin,
        $clinicparams
    ) as $uid => $rec) {
        $merged[(int)$uid] = true;
    }

    return count($merged);
}

/**
 * Site overview Resigned Midway: distinct participants only.
 *
 * A participant can qualify through more than one course, but the overview KPI
 * must count them once so it stays a people count, not an enrolment-row count.
 *
 * @return int
 */
function local_admindashboard_count_site_overview_resigned_midcourse_distinct_users(string $userwhere, array $userparams): int {
    $clinicfieldid = local_admindashboard_get_clinic_field_id();
    $clinicselect = 'COALESCE(u.institution, \'\') AS clinicname';
    $clinicjoin = '';
    $clinicparams = [];
    if ($clinicfieldid > 0) {
        $clinicselect = "COALESCE(NULLIF(uic.data, ''), u.institution, '') AS clinicname";
        $clinicjoin = ' LEFT JOIN {user_info_data} uic ON uic.userid = u.id AND uic.fieldid = :resoverviewclinicfieldid';
        $clinicparams['resoverviewclinicfieldid'] = $clinicfieldid;
    }

    return count(local_admindashboard_site_overview_resigned_user_records(
        $userwhere,
        $userparams,
        $clinicselect,
        $clinicjoin,
        $clinicparams
    ));
}

/**
 * Platform overview user lists for grade-style KPIs (mirrors {@see local_admindashboard_get_metrics} site rollups).
 *
 * @param string $metric attempted|passed|failed|dropped_midway|not_attempted
 * @return array<int,array<string,mixed>>
 */
function local_admindashboard_get_kpi_user_rows_site_overview(
    string $metric,
    string $userwhere,
    array $userparams,
    string $clinicselect,
    string $clinicjoin,
    array $clinicparams
): array {
    global $DB;

    $metric = strtolower(trim($metric));
    if ($metric === 'notattempted') {
        $metric = 'not_attempted';
    }

    $allowed = ['attempted', 'passed', 'failed', 'dropped_midway', 'not_attempted'];
    if (!in_array($metric, $allowed, true)) {
        return [];
    }

    // Same attempted signal as local_admindashboard_get_metrics (course_completions path).
    $kpirow = $DB->get_record_sql(
        "SELECT COUNT(DISTINCT CASE
                    WHEN cc.timestarted > 0 OR cc.timecompleted > 0 THEN u.id
                    ELSE NULL
                END) AS attempted
           FROM {user} u
           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
           JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
      LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
          WHERE {$userwhere}",
        $userparams
    );
    $attemptedprimary = (int)($kpirow->attempted ?? 0);

    if ($attemptedprimary > 0) {
        return local_admindashboard_get_kpi_user_rows_site_overview_completion($metric, $userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
    }

    return local_admindashboard_get_kpi_user_rows_site_overview_quiz_fallback($metric, $userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
}

/**
 * @param string $metric attempted|passed|failed|dropped_midway|not_attempted
 * @return array<int,array<string,mixed>>
 */
function local_admindashboard_get_kpi_user_rows_site_overview_completion(
    string $metric,
    string $userwhere,
    array $userparams,
    string $clinicselect,
    string $clinicjoin,
    array $clinicparams
): array {
    global $DB;

    $base = $userparams + $clinicparams;
    $fromcore = "FROM {user} u
                {$clinicjoin}
           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
           JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1";

    $records = [];

    if ($metric === 'attempted') {
        $sql = "SELECT CONCAT(u.id, '-', c.id) AS rowkey,
                       u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                       {$clinicselect}, c.fullname AS coursefullname,
                       'Attempted' AS enrolmentlabel
                  {$fromcore}
           JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                    AND (cc.timestarted > 0 OR cc.timecompleted > 0)
                 WHERE {$userwhere}
              ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC";
        $records = local_admindashboard_sql_fetch_all_rows($sql, $base);
        return local_admindashboard_site_overview_records_to_kpi_rows($records, false);
    }

    if ($metric === 'passed') {
        $sql = "SELECT CONCAT(u.id, '-', c.id) AS rowkey,
                       u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                       {$clinicselect}, c.fullname AS coursefullname,
                       'Completed' AS enrolmentlabel
                  {$fromcore}
           JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id AND cc.timecompleted > 0
                 WHERE {$userwhere}
              ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC";
        $records = local_admindashboard_sql_fetch_all_rows($sql, $base);
        return local_admindashboard_site_overview_records_to_kpi_rows($records, false);
    }

    if ($metric === 'failed') {
        $sql = "SELECT CONCAT(u.id, '-', c.id) AS rowkey,
                       u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                       {$clinicselect}, c.fullname AS coursefullname,
                       'In progress' AS enrolmentlabel
                  {$fromcore}
           JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                 WHERE {$userwhere}
                   AND cc.timestarted > 0
                   AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)
              ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC";
        $records = local_admindashboard_sql_fetch_all_rows($sql, $base);
        return local_admindashboard_site_overview_records_to_kpi_rows($records, false);
    }

    if ($metric === 'not_attempted') {
        $records = local_admindashboard_site_overview_completion_not_attempted_records($userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
        return local_admindashboard_site_overview_records_to_kpi_rows($records, false);
    }

    if ($metric === 'dropped_midway') {
        $records = local_admindashboard_site_overview_completion_not_attempted_records($userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
        $merged = [];
        foreach ($records as $r) {
            $merged[(int)$r->id] = $r;
        }
        foreach (local_admindashboard_site_overview_resigned_user_records($userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams) as $id => $rec) {
            if (!isset($merged[$id])) {
                $merged[$id] = $rec;
            }
        }
        return local_admindashboard_site_overview_records_to_kpi_rows(array_values($merged));
    }

    return [];
}

/**
 * @return array<int,\stdClass>
 */
function local_admindashboard_site_overview_completion_not_attempted_records(
    string $userwhere,
    array $userparams,
    string $clinicselect,
    string $clinicjoin,
    array $clinicparams
): array {
    global $DB;

    $base = $userparams + $clinicparams;
    $sql = "SELECT CONCAT(u.id, '-', c.id) AS rowkey,
                   u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                   {$clinicselect}, c.fullname AS coursefullname,
                   'Not attempted' AS enrolmentlabel
              FROM {user} u
              {$clinicjoin}
         JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
         JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
         JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
    LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
             WHERE {$userwhere}
               AND (cc.id IS NULL OR (COALESCE(cc.timestarted, 0) = 0 AND COALESCE(cc.timecompleted, 0) = 0))
          ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC";
    return local_admindashboard_sql_fetch_all_rows($sql, $base);
}

/**
 * Resigned / withdrawn site-overview users merged into Dropped Midway drill-down (union with not-attempted).
 * Rows are keyed by userid so the same person appears once even if they qualify in multiple courses.
 *
 * @return array<int,\stdClass> keyed by userid
 */
function local_admindashboard_site_overview_resigned_user_records(
    string $userwhere,
    array $userparams,
    string $clinicselect,
    string $clinicjoin,
    array $clinicparams
): array {
    global $DB;

    $base = $userparams + $clinicparams;
    $suspendeduserwhere = str_replace('u.suspended = 0', 'u.suspended = 1', $userwhere);
    $susbase = $userparams + $clinicparams;

    $merged = [];

    $sqla = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                    {$clinicselect}, c.fullname AS coursefullname
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod'
               JOIN {user} u ON u.id = gg.userid
               {$clinicjoin}
               JOIN {course} c ON c.id = gi.courseid AND c.visible = 1 AND c.id > 1
              WHERE gg.finalgrade IS NOT NULL
                AND {$userwhere}
                AND NOT EXISTS (
                    SELECT 1
                      FROM {user_enrolments} ue2
                      JOIN {enrol} e2 ON e2.id = ue2.enrolid AND e2.status = 0 AND e2.courseid = gi.courseid
                     WHERE ue2.userid = gg.userid AND ue2.status = 0
                )
           ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC";
    foreach (local_admindashboard_sql_fetch_all_rows($sqla, $base) as $r) {
        $merged[(int)$r->id] = $r;
    }

    $sqlb = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                    {$clinicselect}, c.fullname AS coursefullname
               FROM {user} u
               {$clinicjoin}
               JOIN {user_enrolments} ue ON ue.userid = u.id
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
               JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
              WHERE {$suspendeduserwhere}";
    foreach (local_admindashboard_sql_fetch_all_rows($sqlb, $susbase) as $r) {
        $merged[(int)$r->id] = $r;
    }

    $sqlc = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                    {$clinicselect}, c.fullname AS coursefullname
               FROM {user} u
               {$clinicjoin}
               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 1
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
               JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
              WHERE {$userwhere}
                AND NOT EXISTS (
                    SELECT 1
                      FROM {grade_grades} gg2
                      JOIN {grade_items} gi2 ON gi2.id = gg2.itemid
                           AND gi2.courseid = e.courseid
                           AND gi2.itemtype = 'mod'
                     WHERE gg2.userid = u.id AND gg2.finalgrade IS NOT NULL
                )";
    foreach (local_admindashboard_sql_fetch_all_rows($sqlc, $base) as $r) {
        $merged[(int)$r->id] = $r;
    }

    return $merged;
}

/**
 * @param string $metric attempted|passed|failed|dropped_midway|not_attempted
 * @return array<int,array<string,mixed>>
 */
function local_admindashboard_get_kpi_user_rows_site_overview_quiz_fallback(
    string $metric,
    string $userwhere,
    array $userparams,
    string $clinicselect,
    string $clinicjoin,
    array $clinicparams
): array {
    global $DB;

    $base = $userparams + $clinicparams;
    $fromcore = "FROM {user} u
                {$clinicjoin}
           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
           JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
           JOIN {course_modules} cm ON cm.course = c.id AND cm.deletioninprogress = 0
           JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
           JOIN {grade_items} gi ON gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                                AND gi.iteminstance = cm.instance AND gi.courseid = c.id
      LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id";

    $status = '';
    if ($metric === 'attempted') {
        $status = ' AND gg.finalgrade IS NOT NULL';
    } else if ($metric === 'passed') {
        $status = ' AND gi.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= gi.gradepass';
    } else if ($metric === 'failed') {
        $status = ' AND gi.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade < gi.gradepass';
    } else if ($metric === 'not_attempted') {
        $records = local_admindashboard_site_overview_quiz_not_attempted_records($userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
        return local_admindashboard_site_overview_records_to_kpi_rows($records, false);
    } else if ($metric === 'dropped_midway') {
        $records = local_admindashboard_site_overview_quiz_not_attempted_records($userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
        $merged = [];
        foreach ($records as $r) {
            $merged[(int)$r->id] = $r;
        }
        foreach (local_admindashboard_site_overview_resigned_user_records($userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams) as $id => $rec) {
            if (!isset($merged[$id])) {
                $merged[$id] = $rec;
            }
        }
        return local_admindashboard_site_overview_records_to_kpi_rows(array_values($merged));
    } else {
        return [];
    }

    $sql = "SELECT DISTINCT CONCAT(u.id, '-', c.id) AS rowkey,
                   u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                   {$clinicselect}, c.fullname AS coursefullname,
                   CASE
                       WHEN gg.finalgrade IS NULL THEN 'Not attempted'
                       WHEN gi.gradepass > 0 AND gg.finalgrade >= gi.gradepass THEN 'Passed'
                       WHEN gi.gradepass > 0 AND gg.finalgrade < gi.gradepass THEN 'Failed'
                       ELSE 'Attempted'
                   END AS enrolmentlabel
              {$fromcore}
             WHERE {$userwhere}{$status}
          ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC";
    $records = local_admindashboard_sql_fetch_all_rows($sql, $base);
    return local_admindashboard_site_overview_records_to_kpi_rows($records, false);
}

/**
 * Enrolled users with no quiz grade on any quiz-backed grade item (site overview fallback).
 *
 * @return array<int,\stdClass>
 */
function local_admindashboard_site_overview_quiz_not_attempted_records(
    string $userwhere,
    array $userparams,
    string $clinicselect,
    string $clinicjoin,
    array $clinicparams
): array {
    global $DB;

    $base = $userparams + $clinicparams;
    $sql = "SELECT DISTINCT CONCAT(u.id, '-', c.id) AS rowkey,
                   u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                   {$clinicselect}, c.fullname AS coursefullname,
                   'Not attempted' AS enrolmentlabel
              FROM {user} u
              {$clinicjoin}
         JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
         JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
         JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
             WHERE {$userwhere}
               AND NOT EXISTS (
                   SELECT 1
                     FROM {user_enrolments} ue2
                     JOIN {enrol} e2 ON e2.id = ue2.enrolid AND e2.status = 0
                     JOIN {course} c2 ON c2.id = e2.courseid AND c2.visible = 1 AND c2.id > 1
                     JOIN {course_modules} cm2 ON cm2.course = c2.id AND cm2.deletioninprogress = 0
                     JOIN {modules} m2 ON m2.id = cm2.module AND m2.name = 'quiz'
                     JOIN {grade_items} gi2 ON gi2.itemtype = 'mod' AND gi2.itemmodule = 'quiz'
                          AND gi2.iteminstance = cm2.instance AND gi2.courseid = c2.id
                LEFT JOIN {grade_grades} gg2 ON gg2.itemid = gi2.id AND gg2.userid = u.id
                    WHERE ue2.userid = u.id
                      AND ue2.status = 0
                      AND e2.courseid = e.courseid
                      AND gg2.finalgrade IS NOT NULL
               )
          ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC";
    return local_admindashboard_sql_fetch_all_rows($sql, $base);
}

/**
 * Returns the user list represented by a KPI card.
 *
 * @param int $courseid Selected course ID.
 * @param string $department Optional department filter.
 * @param int $moduleid Selected module ID.
 * @param string $metric KPI key.
 * @return array<int,array{id:int,name:string,department:string,clinicname:string}>
 */
function local_admindashboard_get_kpi_user_rows(int $courseid, string $department, int $moduleid, string $metric): array {
    global $DB;

    $metric = strtolower(trim($metric));
    if ($metric === 'notattempted') {
        $metric = 'not_attempted';
    }

    [$userwhere, $userparams] = local_admindashboard_build_user_filter($department);

    $rows = [];
    $buildrows = static function(array $records): array {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'id' => (int)$record->id,
                'name' => trim(fullname($record)),
                'department' => (string)($record->department ?? ''),
                'clinicname' => (string)($record->clinicname ?? ''),
            ];
        }
        return $rows;
    };

    $clinicfieldid = local_admindashboard_get_clinic_field_id();
    $clinicselect = 'COALESCE(u.institution, \'\') AS clinicname';
    $clinicjoin = '';
    $clinicparams = [];
    if ($clinicfieldid > 0) {
        $clinicselect = "COALESCE(NULLIF(uic.data, ''), u.institution, '') AS clinicname";
        $clinicjoin = ' LEFT JOIN {user_info_data} uic ON uic.userid = u.id AND uic.fieldid = :clinicfieldid';
        $clinicparams['clinicfieldid'] = $clinicfieldid;
    }

    if ($courseid <= 0) {
        if ($metric === 'total_enrollments') {
            $sql = "SELECT ue.id AS uenrolid, u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                           {$clinicselect}, c.fullname AS coursefullname, e.id AS enrolrecid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                      JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                      JOIN {user} u ON u.id = ue.userid
                 {$clinicjoin}
                     WHERE ue.status = 0 AND {$userwhere}
                  ORDER BY c.fullname ASC, u.lastname ASC, u.firstname ASC, ue.id ASC";
            $recs = $DB->get_records_sql($sql, $userparams + $clinicparams);
            $enrolids = [];
            foreach ($recs as $rec) {
                if (!empty($rec->enrolrecid)) {
                    $enrolids[(int)$rec->enrolrecid] = true;
                }
            }
            $enrolbyid = [];
            if (!empty($enrolids)) {
                list($insql, $inparams) = $DB->get_in_or_equal(array_keys($enrolids), SQL_PARAMS_NAMED, 'enrel');
                $enrolrows = $DB->get_records_sql("SELECT * FROM {enrol} WHERE id $insql", $inparams);
                foreach ($enrolrows as $er) {
                    $enrolbyid[(int)$er->id] = $er;
                }
            }
            $out = [];
            foreach ($recs as $rec) {
                $efull = $enrolbyid[(int)($rec->enrolrecid ?? 0)] ?? null;
                // Plain course title for JSON (avoid format_string without course context).
                $coursename = strip_tags((string)($rec->coursefullname ?? ''));
                $out[] = [
                    'id' => (int)$rec->id,
                    'name' => trim(fullname($rec)),
                    'department' => (string)($rec->department ?? ''),
                    'clinicname' => (string)($rec->clinicname ?? ''),
                    'course_name' => $coursename,
                    'enrolment_label' => $efull ? local_admindashboard_format_enrol_instance_label($efull) : '',
                ];
            }
            return $out;
        }
        if ($metric === 'participants') {
            $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, {$clinicselect}
                      FROM {user} u
                 {$clinicjoin}
                      JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                      JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                     WHERE {$userwhere}
                  ORDER BY u.lastname ASC, u.firstname ASC";
            return $buildrows($DB->get_records_sql($sql, $userparams + $clinicparams));
        }
        if ($metric === 'certified') {
            return local_admindashboard_get_certificate_issue_user_rows($courseid, $userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
        }
        if ($metric === 'resigned_midcourse') {
            $records = local_admindashboard_site_overview_resigned_user_records(
                $userwhere,
                $userparams,
                $clinicselect,
                $clinicjoin,
                $clinicparams
            );
            return local_admindashboard_site_overview_records_to_kpi_rows(array_values($records));
        }
        if ($moduleid > 0) {
            return [];
        }
        $sitemetrics = ['attempted', 'passed', 'failed', 'dropped_midway', 'not_attempted'];
        if (in_array($metric, $sitemetrics, true)) {
            return local_admindashboard_get_kpi_user_rows_site_overview($metric, $userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
        }
        return [];
    }

    $baseparams = $userparams + ['courseid_enrol' => $courseid];
    $enrolleduserssql = "SELECT DISTINCT u.id
                           FROM {user} u
                           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_enrol
                          WHERE {$userwhere}";

    if ($metric === 'total_enrollments') {
        $sql = "SELECT ue.id AS uenrolid, u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department,
                       {$clinicselect}, e.id AS enrolrecid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_te_rows
                  JOIN {user} u ON u.id = ue.userid
             {$clinicjoin}
                 WHERE ue.status = 0 AND {$userwhere}
              ORDER BY u.lastname ASC, u.firstname ASC, ue.id ASC";
        $tparams = $userparams + $clinicparams + ['courseid_te_rows' => $courseid];
        $recs = $DB->get_records_sql($sql, $tparams);
        $enrolids = [];
        foreach ($recs as $rec) {
            if (!empty($rec->enrolrecid)) {
                $enrolids[(int)$rec->enrolrecid] = true;
            }
        }
        $enrolbyid = [];
        if (!empty($enrolids)) {
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($enrolids), SQL_PARAMS_NAMED, 'enrcr');
            $enrolrows = $DB->get_records_sql("SELECT * FROM {enrol} WHERE id $insql", $inparams);
            foreach ($enrolrows as $er) {
                $enrolbyid[(int)$er->id] = $er;
            }
        }
        $out = [];
        foreach ($recs as $rec) {
            $efull = $enrolbyid[(int)($rec->enrolrecid ?? 0)] ?? null;
            $out[] = [
                'id' => (int)$rec->id,
                'name' => trim(fullname($rec)),
                'department' => (string)($rec->department ?? ''),
                'clinicname' => (string)($rec->clinicname ?? ''),
                'course_name' => '',
                'enrolment_label' => $efull ? local_admindashboard_format_enrol_instance_label($efull) : '',
            ];
        }
        return $out;
    }

    if ($metric === 'participants') {
        // Active (non-suspended) enrolled users.
        $sql = "SELECT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, {$clinicselect}
                  FROM ({$enrolleduserssql}) eu
                  JOIN {user} u ON u.id = eu.id
             {$clinicjoin}
              ORDER BY u.lastname ASC, u.firstname ASC";
        $activerows = $DB->get_records_sql($sql, $baseparams + $clinicparams);

        // Also include account-suspended users who are still enrolled in this course.
        $suspendeduserwhere = str_replace('u.suspended = 0', 'u.suspended = 1', $userwhere);
        $susparams = $userparams + $clinicparams + ['sus_part_rows_cid' => $courseid];
        $sussql = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, {$clinicselect}
                     FROM {user} u
                     {$clinicjoin}
                     JOIN {user_enrolments} ue ON ue.userid = u.id
                     JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :sus_part_rows_cid
                    WHERE {$suspendeduserwhere}";
        try {
            $susrows = $DB->get_records_sql($sussql, $susparams);
        } catch (\Throwable $e) {
            $susrows = [];
        }
        foreach ($susrows as $susid => $susrow) {
            if (!isset($activerows[$susid])) {
                $activerows[$susid] = $susrow;
            }
        }

        // Also include enrolment-suspended users (ue.status=1, active account).
        $susenrolrowsparams = $userparams + $clinicparams + ['sus_enrol_rows_cid' => $courseid];
        $susenrolrowssql = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, {$clinicselect}
                              FROM {user} u
                              {$clinicjoin}
                              JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 1
                              JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :sus_enrol_rows_cid
                             WHERE {$userwhere}";
        try {
            $susenrolrows = $DB->get_records_sql($susenrolrowssql, $susenrolrowsparams);
        } catch (\Throwable $e) {
            $susenrolrows = [];
        }
        foreach ($susenrolrows as $susid => $susrow) {
            if (!isset($activerows[$susid])) {
                $activerows[$susid] = $susrow;
            }
        }
        uasort($activerows, static function($a, $b): int {
            $cmp = strcasecmp((string)($a->lastname ?? ''), (string)($b->lastname ?? ''));
            return $cmp !== 0 ? $cmp : strcasecmp((string)($a->firstname ?? ''), (string)($b->firstname ?? ''));
        });
        return local_admindashboard_kpi_rows_add_course_enrolment_detail($courseid, $buildrows($activerows));
    }

    $source = local_admindashboard_get_kpi_grade_source($courseid, $moduleid, $userwhere, $userparams);
    $gradeitemid = (int)($source['gradeitemid'] ?? 0);
    $gradepass = (float)($source['gradepass'] ?? 0);
    $ismodulequiz = ((string)($source['selectedmodname'] ?? '') === 'quiz');

    if ($metric === 'certified') {
        return local_admindashboard_get_certificate_issue_user_rows($courseid, $userwhere, $userparams, $clinicselect, $clinicjoin, $clinicparams);
    }

    if ($gradeitemid <= 0) {
        return [];
    }

    $params = $baseparams + [
        'gradeitemid_list' => $gradeitemid,
        'gradepass_list' => $gradepass,
    ] + $clinicparams;
    $statuswhere = '';

    switch ($metric) {
        case 'attempted':
            $statuswhere = ' AND gg.finalgrade IS NOT NULL';
            break;

        case 'passed':
            if ($gradepass > 0) {
                $statuswhere = ' AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= gp.gradepass';
            } else if ($ismodulequiz) {
                $statuswhere = ' AND gg.finalgrade IS NOT NULL';
            } else {
                return [];
            }
            break;

        case 'failed':
            if ($gradepass <= 0) {
                return [];
            }
            $statuswhere = ' AND gg.finalgrade IS NOT NULL AND gg.finalgrade < gp.gradepass';
            break;

        case 'dropped_midway':
        case 'not_attempted':
            $statuswhere = ' AND gg.finalgrade IS NULL';
            break;

        case 'resigned_midcourse':
            // Users who had grade activity in the course but no longer have an active enrolment
            // (covers both suspended enrolments and fully-removed enrolment records).
            if ($courseid <= 0) {
                return [];
            }
            $resparams = $userparams + $clinicparams + ['res_cid' => $courseid, 'res_cid2' => $courseid];
            $ressql = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, {$clinicselect}
                         FROM {user} u
                         {$clinicjoin}
                         JOIN {grade_grades} gg ON gg.userid = u.id AND gg.finalgrade IS NOT NULL
                         JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.courseid = :res_cid AND gi.itemtype = 'mod'
                        WHERE {$userwhere}
                          AND NOT EXISTS (
                              SELECT 1 FROM {user_enrolments} ue2
                              JOIN {enrol} e2 ON e2.id = ue2.enrolid AND e2.status = 0 AND e2.courseid = :res_cid2
                              WHERE ue2.userid = u.id AND ue2.status = 0
                          )
                     ORDER BY u.lastname ASC, u.firstname ASC";
            $resigned = $DB->get_records_sql($ressql, $resparams);

            // Also include users whose Moodle account is suspended (u.suspended = 1) and enrolled in the course.
            $suspendeduserwhere = str_replace('u.suspended = 0', 'u.suspended = 1', $userwhere);
            $susparams = $userparams + $clinicparams + ['sus_cid' => $courseid];
            $sussql = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, {$clinicselect}
                         FROM {user} u
                         {$clinicjoin}
                         JOIN {user_enrolments} ue ON ue.userid = u.id
                         JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :sus_cid
                        WHERE {$suspendeduserwhere}
                     ORDER BY u.lastname ASC, u.firstname ASC";
            try {
                $suspended = $DB->get_records_sql($sussql, $susparams);
            } catch (\Throwable $e) {
                $suspended = [];
            }

            // Also include users with suspended enrolment (ue.status=1, active account).
            $susenrolresrowsparams = $userparams + $clinicparams + ['sus_enrol_res_rows_cid' => $courseid];
            $susenrolressql = "SELECT DISTINCT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, {$clinicselect}
                                 FROM {user} u
                                 {$clinicjoin}
                                 JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 1
                                 JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :sus_enrol_res_rows_cid
                                WHERE {$userwhere}";
            try {
                $susenrolresrows = $DB->get_records_sql($susenrolressql, $susenrolresrowsparams);
            } catch (\Throwable $e) {
                $susenrolresrows = [];
            }

            // Merge all three sources by user ID (no duplicates) then sort.
            foreach ($suspended as $susid => $susrow) {
                if (!isset($resigned[$susid])) {
                    $resigned[$susid] = $susrow;
                }
            }
            foreach ($susenrolresrows as $susid => $susrow) {
                if (!isset($resigned[$susid])) {
                    $resigned[$susid] = $susrow;
                }
            }
            uasort($resigned, static function($a, $b): int {
                $cmp = strcasecmp((string)($a->lastname ?? ''), (string)($b->lastname ?? ''));
                return $cmp !== 0 ? $cmp : strcasecmp((string)($a->firstname ?? ''), (string)($b->firstname ?? ''));
            });
            return local_admindashboard_kpi_rows_add_course_enrolment_detail($courseid, $buildrows($resigned));

        default:
            return [];
    }

    $sql = "SELECT u.id, u.firstname, u.lastname, COALESCE(u.department, '') AS department, {$clinicselect}
              FROM ({$enrolleduserssql}) eu
              JOIN {user} u ON u.id = eu.id
         CROSS JOIN (SELECT :gradepass_list AS gradepass) gp
         {$clinicjoin}
         LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :gradeitemid_list
             WHERE 1=1{$statuswhere}
          ORDER BY u.lastname ASC, u.firstname ASC";
    return local_admindashboard_kpi_rows_add_course_enrolment_detail($courseid, $buildrows($DB->get_records_sql($sql, $params)));
}

/**
 * Sum of per-course "Resigned Mid-Course" counts across all visible courses.
 *
 * Mirrors the three components of the course-scoped {@see local_admindashboard_get_metrics} resigned_midcourse
 * logic (grade-but-unenrolled, account-suspended enrolments, suspended-enrolment-without-grade),
 * counting distinct (course, user) pairs so totals match summing each course's KPI.
 *
 * @param string $userwhere SQL fragment for alias u (active-account cohort)
 * @param array $userparams Bound parameters for $userwhere
 * @return int
 */
function local_admindashboard_sum_resigned_midcourse_all_courses(string $userwhere, array $userparams): int {
    global $DB;

    $total = 0;
    $suspendeduserwhere = str_replace('u.suspended = 0', 'u.suspended = 1', $userwhere);

    // Part A: user has a module grade in a course but no active enrolment in that course.
    try {
        $total += (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM (
                    SELECT DISTINCT gg.userid, gi.courseid
                      FROM {grade_grades} gg
                      JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'mod'
                      JOIN {user} u ON u.id = gg.userid
                      JOIN {course} c ON c.id = gi.courseid AND c.visible = 1 AND c.id > 1
                     WHERE gg.finalgrade IS NOT NULL
                       AND {$userwhere}
                       AND NOT EXISTS (
                             SELECT 1
                               FROM {user_enrolments} ue2
                               JOIN {enrol} e2 ON e2.id = ue2.enrolid AND e2.status = 0 AND e2.courseid = gi.courseid
                              WHERE ue2.userid = gg.userid AND ue2.status = 0
                         )
                ) rsite_a",
            $userparams
        );
    } catch (\Throwable $e) {
        $total += 0;
    }

    // Part B: account-suspended users with an enrolment row in each visible course (count per course).
    try {
        $total += (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM (
                    SELECT DISTINCT e.courseid, u.id
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                      JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                     WHERE {$suspendeduserwhere}
                ) rsite_b",
            $userparams
        );
    } catch (\Throwable $e) {
        $total += 0;
    }

    // Part C: enrolment suspended (ue.status=1), active account, no module grade in that course.
    try {
        $total += (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM (
                    SELECT DISTINCT e.courseid, u.id
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 1
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                      JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                     WHERE {$userwhere}
                       AND NOT EXISTS (
                             SELECT 1
                               FROM {grade_grades} gg2
                               JOIN {grade_items} gi2 ON gi2.id = gg2.itemid
                                    AND gi2.courseid = e.courseid
                                    AND gi2.itemtype = 'mod'
                              WHERE gg2.userid = u.id AND gg2.finalgrade IS NOT NULL
                         )
                ) rsite_c",
            $userparams
        );
    } catch (\Throwable $e) {
        $total += 0;
    }

    return $total;
}

/**
 * Computes dashboard metrics and chart data.
 *
 * Notes on KPI meanings (course-scoped when courseid > 0):
 * - participants: enrolled users
 * - attempted: users with a non-null assessment grade (or module grade when a quiz module is selected)
 * - passed: users who met gradepass on the selected assessment (course gradepass if configured, otherwise an assessment quiz)
 * - certified: users who passed the assessment AND have a certificate issued (certificate activity)
 * - failed: users below gradepass on the selected assessment
 * - dropped_midway: participants - attempted (single course). Platform overview (courseid=0): one count per
 *   participant only — union of never-attempted and resigned-style buckets, deduped across all courses.
 * - total_enrollments: count of user_enrolments rows (active enrolment instances) in scope; KPI % denominators
 *
 * @return array{total_students:int, active_courses:int, completion_rate:int, pending_modules:int, participants:int, total_enrollments:int, attempted:int, passed:int, certified:int, failed:int, dropped_midway:int, selected_modname:string, bar_data:array<int, array{department:string, completion:int}>, bar_data_completion:array<int, array{department:string, completion:int}>, bar_data_pass:array<int, array{department:string, completion:int}>, bar_data_fail:array<int, array{department:string, completion:int}>, engagement:array{Active:int, Inactive:int, Pending:int}}
 */
/**
 * Computes dashboard metrics and chart data, excluding staff/admin/teacher/test/demo users.
 *
 * Excludes users with roles: manager, editingteacher, teacher, coursecreator, admin, and users with username or name like 'ZMT Student' or 'test'.
 */
function local_admindashboard_get_metrics(int $courseid, string $department, int $moduleid = 0, bool $includetrends = true): array {
    global $CFG, $DB;

    $department = trim($department);

    [$userwhere, $userparams] = local_admindashboard_build_user_filter($department);

    // Total Learners: count the same enrolled learner cohort used by the outcome KPIs.
    if ($courseid > 0) {
        $participantparams = $userparams + ['courseid' => $courseid];
        $totalstudents = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id)
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
              WHERE e.courseid = :courseid
                AND {$userwhere}",
            $participantparams
        );
    } else {
        $totalstudents = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id)
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
               JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
              WHERE {$userwhere}",
            $userparams
        );
    }

    if ($courseid > 0) {
        $activecourses = (int)$DB->count_records_select('course', 'id = :id AND visible = 1', ['id' => $courseid]);
    } else {
        $activecourses = (int)$DB->count_records_select('course', 'id > 1 AND visible = 1');
    }

    // If a specific module is selected, prefer module-level pass/fail metrics when possible.
    $selectedmodname = null;
    $selectedinstance = null;
    if ($courseid > 0 && $moduleid > 0) {
        $cm = $DB->get_record_sql(
            "SELECT cm.id, cm.course, m.name AS modname, cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.id = :cmid",
            ['cmid' => $moduleid]
        );
        if ($cm && (int)$cm->course === (int)$courseid) {
            $selectedmodname = $cm->modname;
            $selectedinstance = (int)$cm->instance;
        }
    }

    // Default KPI rollups (course-level: participants / passed / failed / dropped).
    $participants = 0;
    $attempted = 0;
    $passed = 0;
    $certified = 0;
    $failed = 0;
    $droppedmidway = 0;

    // When the course doesn't have a course-gradepass configured, we may fall back to an assessment quiz.
    $assessmentgradeitemid = 0;
    $assessmentgradepass = 0.0;

    // Participant performance leaderboard (overall performance) for a selected course.
    $performance = [
        'items' => [],
        'participants' => [],
        'clinics' => [],
        'totaltrackable' => 0,
        'totalexpected' => 0,
        'gradeitem' => null,
    ];

    if ($courseid > 0 && $moduleid > 0 && $selectedmodname === 'quiz' && !empty($selectedinstance)) {
        // Quiz pass/fail based on gradepass for that quiz (like the quiz overview report).
        $girecs = $DB->get_records_sql(
            "SELECT gi.id, gi.gradepass, gi.grademax
               FROM {grade_items} gi
              WHERE gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND gi.iteminstance = :quizid
           ORDER BY gi.id ASC",
            ['quizid' => $selectedinstance],
            0,
            1
        );
        $gi = $girecs ? reset($girecs) : null;

        $gradeitemid = (int)($gi->id ?? 0);
        $gradepass = (float)($gi->gradepass ?? 0);

        $qparams = $userparams + ['courseid' => $courseid, 'gradeitemid' => $gradeitemid, 'gradepass' => $gradepass];

        $qsql = "SELECT
                    COUNT(DISTINCT u.id) AS enrolled,
                    COUNT(DISTINCT CASE WHEN gg.finalgrade IS NOT NULL THEN u.id ELSE NULL END) AS attempted,
                    COUNT(DISTINCT CASE
                        WHEN gp.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= gp.gradepass THEN u.id
                        WHEN gp.gradepass <= 0 AND gg.finalgrade IS NOT NULL THEN u.id
                        ELSE NULL
                    END) AS passed,
                    COUNT(DISTINCT CASE
                        WHEN gp.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade < gp.gradepass THEN u.id
                        ELSE NULL
                    END) AS failed
                  FROM {user} u
            CROSS JOIN (SELECT :gradepass AS gradepass) gp
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
             LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :gradeitemid
                 WHERE {$userwhere}";

        $qrow = $DB->get_record_sql($qsql, $qparams);
        $participants = (int)($qrow->enrolled ?? 0);
        $attempted = (int)($qrow->attempted ?? 0);
        $passed = (int)($qrow->passed ?? 0);
        $failed = (int)($qrow->failed ?? 0);
        if ($gradeitemid > 0 && $gradepass > 0) {
            $certified = local_admindashboard_count_certified_issued($courseid, $userwhere, $userparams, $gradeitemid, $gradepass);
        }
        // For quizzes, treat "dropped" as enrolled but not attempted (no grade yet).
        $droppedmidway = max(0, $participants - $attempted);
    } else {
        // Course-level KPIs.
        if ($courseid > 0) {
            // Participants first.
            $pparams = $userparams + ['courseid_part' => $courseid];
            $participants = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT u.id)
                   FROM {user} u
                   JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_part
                  WHERE {$userwhere}",
                $pparams
            );

            // Strict rule: use the last module's last quiz/test as the course outcome assessment.
            // Only apply this when no specific module is selected; otherwise keep module selection semantics.
            $usedassessmentkpi = false;
            if ($moduleid <= 0) {
                $assessment = ($participants > 0) ? local_admindashboard_pick_course_assessment_quiz($courseid, $userwhere, $userparams) : null;
                if ($assessment && !empty($assessment->gradeitemid) && !empty($assessment->gradepass)) {
                    $assessmentgradeitemid = (int)$assessment->gradeitemid;
                    $assessmentgradepass = (float)$assessment->gradepass;

                    $fparams = $userparams + [
                        'courseid_assess_kpi' => $courseid,
                        'gradeitemid_assess_kpi' => $assessmentgradeitemid,
                        'gradepass_assess_kpi' => $assessmentgradepass,
                    ];

                    $fsql = "SELECT
                                COUNT(DISTINCT u.id) AS enrolled,
                                COUNT(DISTINCT CASE WHEN gg.finalgrade IS NOT NULL THEN u.id ELSE NULL END) AS attempted,
                                COUNT(DISTINCT CASE WHEN gg.finalgrade IS NOT NULL AND gg.finalgrade >= gp.gradepass THEN u.id ELSE NULL END) AS passed,
                                COUNT(DISTINCT CASE WHEN gg.finalgrade IS NOT NULL AND gg.finalgrade < gp.gradepass THEN u.id ELSE NULL END) AS failed
                              FROM {user} u
                         CROSS JOIN (SELECT :gradepass_assess_kpi AS gradepass) gp
                              JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                              JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_assess_kpi
                         LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :gradeitemid_assess_kpi
                             WHERE {$userwhere}";

                    $frow = $DB->get_record_sql($fsql, $fparams);
                    $participants = (int)($frow->enrolled ?? $participants);
                    $attempted = (int)($frow->attempted ?? 0);
                    $passed = (int)($frow->passed ?? 0);
                    $failed = (int)($frow->failed ?? 0);
                    $droppedmidway = max(0, $participants - $attempted);

                    $certified = local_admindashboard_count_certified_issued($courseid, $userwhere, $userparams, $assessmentgradeitemid, $assessmentgradepass);

                    // Signal dashboard to treat this as assessment-quiz mode.
                    $selectedmodname = 'quiz';
                    $selectedinstance = (int)($assessment->quizid ?? 0);
                    $usedassessmentkpi = true;
                }
            }

            if (!$usedassessmentkpi) {
                // Fallback: if no suitable quiz exists (or module is explicitly selected), fall back to course gradepass.
                $kpiparams = $userparams + ['courseid' => $courseid];
                $kpisql = "SELECT
                                COUNT(DISTINCT u.id) AS participants,
                                COUNT(DISTINCT CASE
                                    WHEN gg.finalgrade IS NOT NULL THEN u.id
                                    ELSE NULL
                                END) AS attempted,
                                COUNT(DISTINCT CASE
                                    WHEN (gi.gradepass IS NOT NULL AND gi.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= gi.gradepass)
                                    THEN u.id END) AS passed,
                                COUNT(DISTINCT CASE
                                    WHEN (gi.gradepass IS NOT NULL AND gi.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade < gi.gradepass)
                                    THEN u.id END) AS failed,
                                MAX(COALESCE(gi.gradepass, 0)) AS coursegradepass
                              FROM {user} u
                              JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                              JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                              JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                         LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
                         LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
                             WHERE {$userwhere} AND c.id = :courseid";

                $kpirow = $DB->get_record_sql($kpisql, $kpiparams);
                $participants = (int)($kpirow->participants ?? $participants);
                $failed = (int)($kpirow->failed ?? 0);
                $attempted = (int)($kpirow->attempted ?? 0);
                $passed = (int)($kpirow->passed ?? 0);
                $coursegradepass = (float)($kpirow->coursegradepass ?? 0);
                $droppedmidway = max(0, $participants - $attempted);

                // If course gradepass is 0 (not configured), fall back to course_completions
                // as the pass signal: timecompleted > 0 = user finished the course.
                if ($passed === 0 && $attempted === 0) {
                    $ccparams = $userparams + ['courseid_cc_fallback' => $courseid];
                    $ccsql = "SELECT
                                  COUNT(DISTINCT u.id) AS participants,
                                  COUNT(DISTINCT CASE WHEN cc.timestarted > 0 OR cc.timecompleted > 0 THEN u.id ELSE NULL END) AS attempted,
                                  COUNT(DISTINCT CASE WHEN cc.timecompleted > 0 THEN u.id ELSE NULL END) AS passed,
                                  COUNT(DISTINCT CASE WHEN cc.timestarted > 0 AND (cc.timecompleted IS NULL OR cc.timecompleted = 0) THEN u.id ELSE NULL END) AS failed
                                FROM {user} u
                                JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                                JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_cc_fallback
                           LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
                               WHERE {$userwhere}";
                    $ccrow = $DB->get_record_sql($ccsql, $ccparams);
                    if ($ccrow && ((int)($ccrow->attempted ?? 0) > 0 || (int)($ccrow->passed ?? 0) > 0)) {
                        $participants = (int)($ccrow->participants ?? $participants);
                        $attempted    = (int)($ccrow->attempted ?? 0);
                        $passed       = (int)($ccrow->passed ?? 0);
                        $failed       = (int)($ccrow->failed ?? 0);
                        $droppedmidway = max(0, $participants - $attempted);
                    }
                }

                if ($coursegradepass > 0) {
                    $coursegi = $DB->get_record_sql(
                        "SELECT gi.id, gi.gradepass
                           FROM {grade_items} gi
                          WHERE gi.courseid = :c_coursegi
                            AND gi.itemtype = 'course'
                            AND gi.gradepass IS NOT NULL
                       ORDER BY gi.id ASC",
                        ['c_coursegi' => $courseid],
                        IGNORE_MULTIPLE
                    );
                    if ($coursegi && !empty($coursegi->id) && !empty($coursegi->gradepass)) {
                        $certified = local_admindashboard_count_certified_issued($courseid, $userwhere, $userparams, (int)$coursegi->id, (float)$coursegi->gradepass);
                    }
                }
            }
        } else {
            // Site-wide rollup (all courses): use course_completions as the primary pass signal,
            // because course-level grade items have gradepass=0 on most Moodle installations.
            // course_completions.timecompleted > 0 means the user finished all required activities.
            $kpiparams = $userparams;
            $kpisql = "SELECT COUNT(DISTINCT x.userid) AS participants,
                              SUM(GREATEST(x.attempted, x.passed)) AS attempted,
                              SUM(x.passed) AS passed,
                              SUM(GREATEST(0, GREATEST(x.attempted, x.passed) - x.passed)) AS failed
                         FROM (
                              SELECT u.id AS userid,
                                     c.id AS courseid,
                                     MAX(CASE WHEN cc.timestarted > 0 OR cc.timecompleted > 0 THEN 1 ELSE 0 END) AS attempted,
                                     MAX(CASE WHEN cc.timecompleted > 0 THEN 1 ELSE 0 END) AS passed
                                FROM {user} u
                                JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                                JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                                JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                           LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                               WHERE {$userwhere}
                            GROUP BY u.id, c.id
                         ) x";
            $kpirow = $DB->get_record_sql($kpisql, $kpiparams);
            $participants = (int)($kpirow->participants ?? 0);
            $passed = (int)($kpirow->passed ?? 0);
            $attempted = (int)($kpirow->attempted ?? 0);
            $failed = max(0, $attempted - $passed);

            // If completions give no data (completion tracking not configured), fall
            // back to any graded quiz attempt across all courses as "attempted" signal.
            if ($attempted === 0) {
                $fallbackparams = $userparams;
                $fallbacksql = "SELECT
                                    COUNT(DISTINCT u.id) AS participants,
                                    COUNT(DISTINCT CASE WHEN gg.finalgrade IS NOT NULL THEN CONCAT(u.id, '-', c.id) ELSE NULL END) AS attempted,
                                    COUNT(DISTINCT CASE
                                        WHEN gi.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= gi.gradepass THEN CONCAT(u.id, '-', c.id)
                                        ELSE NULL
                                    END) AS passed,
                                    COUNT(DISTINCT CASE
                                        WHEN gi.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade < gi.gradepass THEN CONCAT(u.id, '-', c.id)
                                        ELSE NULL
                                    END) AS failed
                                  FROM {user} u
                                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                                  JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                                  JOIN {course_modules} cm ON cm.course = c.id AND cm.deletioninprogress = 0
                                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                                  JOIN {grade_items} gi ON gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                                                        AND gi.iteminstance = cm.instance AND gi.courseid = c.id
                             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
                                 WHERE {$userwhere}";
                $fbrow = $DB->get_record_sql($fallbacksql, $fallbackparams);
                $participants = (int)($fbrow->participants ?? $participants);
                $attempted    = (int)($fbrow->attempted ?? 0);
                $passed       = (int)($fbrow->passed ?? 0);
                $failed       = (int)($fbrow->failed ?? 0);
            }

            $droppedmidway = local_admindashboard_count_site_overview_dropped_midway_distinct_users($userwhere, $userparams);
        }
    }

    // Certificates issued are counted directly from certificate issue records.
    $certified = local_admindashboard_count_certificate_issues($courseid, $userwhere, $userparams);

    // ── Suspended participants (account-suspended OR enrolment-suspended) ──
    // Case A: User account suspended (u.suspended = 1) — still enrolled in course.
    // Case B: Enrolment suspended (ue.status = 1) — the most common "suspend participant"
    //         action admins take via Course > Participants.
    // Both must be counted in the Participants KPI total but must NOT appear in
    // any other KPI (passed / failed / dropped / attempted / certified).
    // They are surfaced exclusively under the "Resigned Mid-Course" KPI.
    $suspendedEnrolled = 0;        // u.suspended = 1 users enrolled
    $suspendedEnrolmentUsers = 0;  // ue.status = 1 (active account) users enrolled
    if ($courseid > 0) {
        // Case A: account-suspended users enrolled.
        try {
            $suspendeduserwhere = str_replace('u.suspended = 0', 'u.suspended = 1', $userwhere);
            $susparams = $userparams + ['sus_part_cid' => $courseid];
            $suspendedEnrolled = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT u.id)
                   FROM {user} u
                   JOIN {user_enrolments} ue ON ue.userid = u.id
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :sus_part_cid
                  WHERE {$suspendeduserwhere}",
                $susparams
            );
        } catch (\Throwable $e) {
            $suspendedEnrolled = 0;
        }

        // Case B: active-account users whose enrolment is suspended (ue.status = 1).
        // This is what happens when an admin clicks "Suspend" on a participant in the
        // course participants list.
        try {
            $susenrolparams = $userparams + ['sus_enrol_part_cid' => $courseid];
            $suspendedEnrolmentUsers = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT u.id)
                   FROM {user} u
                   JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 1
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :sus_enrol_part_cid
                  WHERE {$userwhere}",
                $susenrolparams
            );
        } catch (\Throwable $e) {
            $suspendedEnrolmentUsers = 0;
        }

        $participants += $suspendedEnrolled + $suspendedEnrolmentUsers;
    }

    // Build performance leaderboard (Top 10 by overall performance) when a course is selected.
    if ($courseid > 0) {
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $completioninfo = new completion_info($course);
        if ($completioninfo->is_enabled()) {
            $modinfo = get_fast_modinfo($courseid);

            $trackablecmids = [];
            foreach ($completioninfo->get_activities() as $cmid => $cm) {
                if (empty($modinfo->cms[$cmid])) {
                    continue;
                }
                $cms = $modinfo->cms[$cmid];
                if (!empty($cms->deletioninprogress)) {
                    continue;
                }
                $trackablecmids[] = (int)$cmid;
            }

            // If a module is selected and it is completion-tracked, narrow to that activity.
            if ($moduleid > 0 && in_array((int)$moduleid, $trackablecmids, true)) {
                $trackablecmids = [(int)$moduleid];
            }

            $totaltrackable = count($trackablecmids);
            $performance['totaltrackable'] = $totaltrackable;

            if ($totaltrackable > 0) {
                $coursecontext = context_course::instance($courseid);
                $progressusers = $completioninfo->get_progress_all(
                    $userwhere,
                    $userparams,
                    0,
                    'u.lastname ASC, u.firstname ASC',
                    0,
                    0,
                    $coursecontext
                );

                $userids = array_map('intval', array_keys($progressusers));
                $departmentbyuser = [];
                $clinicbyuser = [];
                if (!empty($userids)) {
                    $clinicfieldid = local_admindashboard_get_clinic_field_id();
                    $clinicselect = 'COALESCE(u.institution, \'\') AS clinicname';
                    $clinicjoin = '';
                    $clinicparams = [];
                    if ($clinicfieldid > 0) {
                        $clinicselect = "COALESCE(NULLIF(uic.data, ''), u.institution, '') AS clinicname";
                        $clinicjoin = ' LEFT JOIN {user_info_data} uic ON uic.userid = u.id AND uic.fieldid = :perfclinicfieldid';
                        $clinicparams['perfclinicfieldid'] = $clinicfieldid;
                    }

                    list($uinq, $uinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'pdeptuid');
                    $userrecords = $DB->get_records_sql(
                        "SELECT u.id, COALESCE(u.department, '') AS department, {$clinicselect}
                           FROM {user} u
                      {$clinicjoin}
                          WHERE u.id {$uinq}",
                        $uinparams + $clinicparams
                    );
                    foreach ($userrecords as $userrecord) {
                        $departmentbyuser[(int)$userrecord->id] = trim((string)($userrecord->department ?? ''));
                        $clinicbyuser[(int)$userrecord->id] = trim((string)($userrecord->clinicname ?? ''));
                    }
                }

                // Expected completion dates for on-time metric.
                $duebycmid = [];
                $expectedcmids = [];
                if (!empty($trackablecmids)) {
                    list($cminsql, $cminparams) = $DB->get_in_or_equal($trackablecmids, SQL_PARAMS_NAMED, 'pcmid');
                    $duerecs = $DB->get_records_sql(
                        "SELECT id, completionexpected
                           FROM {course_modules}
                          WHERE course = :p_courseid
                            AND id {$cminsql}
                            AND completionexpected IS NOT NULL
                            AND completionexpected > 0",
                        ['p_courseid' => $courseid] + $cminparams
                    );
                    foreach ($duerecs as $r) {
                        $cmid = (int)$r->id;
                        $due = (int)$r->completionexpected;
                        if ($due > 0) {
                            $duebycmid[$cmid] = $due;
                            $expectedcmids[] = $cmid;
                        }
                    }
                }
                $expectedcmids = array_values(array_unique($expectedcmids));
                $performance['totalexpected'] = count($expectedcmids);

                // Completion timestamps (for on-time completion).
                $cmc = []; // [userid][cmid] => (object){state,timemodified}
                if (!empty($userids) && !empty($expectedcmids)) {
                    list($uinq, $uinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'puid');
                    list($cminsql, $cminparams) = $DB->get_in_or_equal($expectedcmids, SQL_PARAMS_NAMED, 'ecmid');
                    $cmcrecs = $DB->get_records_sql(
                        "SELECT userid, coursemoduleid, completionstate, timemodified
                           FROM {course_modules_completion}
                          WHERE userid {$uinq}
                            AND coursemoduleid {$cminsql}",
                        $uinparams + $cminparams
                    );
                    foreach ($cmcrecs as $row) {
                        $uid = (int)$row->userid;
                        $cmid = (int)$row->coursemoduleid;
                        if (!isset($cmc[$uid])) {
                            $cmc[$uid] = [];
                        }
                        $cmc[$uid][$cmid] = (object)[
                            'state' => (int)($row->completionstate ?? 0),
                            'time' => (int)($row->timemodified ?? 0),
                        ];
                    }
                }

                // Grade item for grade metric: prefer selected quiz grade item, else course total.
                $gradeitemid = 0;
                $grademax = 0.0;
                if ($moduleid > 0 && $selectedmodname === 'quiz' && !empty($selectedinstance)) {
                    $girecs = $DB->get_records_sql(
                        "SELECT gi.id, gi.grademax
                           FROM {grade_items} gi
                          WHERE gi.itemtype = 'mod'
                            AND gi.itemmodule = 'quiz'
                            AND gi.iteminstance = :p_quizid
                       ORDER BY gi.id ASC",
                        ['p_quizid' => $selectedinstance],
                        0,
                        1
                    );
                    $gi = $girecs ? reset($girecs) : null;
                    $gradeitemid = (int)($gi->id ?? 0);
                    $grademax = (float)($gi->grademax ?? 0);
                } else {
                    $girecs = $DB->get_records_sql(
                        "SELECT gi.id, gi.grademax
                           FROM {grade_items} gi
                          WHERE gi.courseid = :p_courseid
                            AND gi.itemtype = 'course'
                       ORDER BY gi.id ASC",
                        ['p_courseid' => $courseid],
                        0,
                        1
                    );
                    $gi = $girecs ? reset($girecs) : null;
                    $gradeitemid = (int)($gi->id ?? 0);
                    $grademax = (float)($gi->grademax ?? 0);
                }

                $gradebyuser = []; // [userid] => finalgrade
                if ($gradeitemid > 0 && $grademax > 0 && !empty($userids)) {
                    list($uinq, $uinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'guid');
                    $graderecs = $DB->get_records_sql(
                        "SELECT userid, finalgrade
                           FROM {grade_grades}
                          WHERE itemid = :p_itemid
                            AND userid {$uinq}",
                        ['p_itemid' => $gradeitemid] + $uinparams
                    );
                    foreach ($graderecs as $gr) {
                        $gradebyuser[(int)$gr->userid] = (float)($gr->finalgrade ?? 0);
                    }
                    $performance['gradeitem'] = [
                        'id' => $gradeitemid,
                        'grademax' => $grademax,
                    ];
                }

                $quizids = [];
                foreach ($trackablecmids as $trackablecmid) {
                    if (empty($modinfo->cms[$trackablecmid])) {
                        continue;
                    }
                    $trackablecm = $modinfo->cms[$trackablecmid];
                    if ((string)($trackablecm->modname ?? '') !== 'quiz' || empty($trackablecm->instance)) {
                        continue;
                    }
                    $quizids[] = (int)$trackablecm->instance;
                }
                $quizids = array_values(array_unique(array_filter($quizids)));

                $quizgradesbyuser = [];
                $quizattemptsbyuser = [];
                if (!empty($quizids) && !empty($userids)) {
                    list($quizinsql, $quizinparams) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'perfquizid');
                    list($quizuinq, $quizuinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'perfquizuid');

                    $quizgraderecs = $DB->get_records_sql(
                        "SELECT qg.userid,
                                MAX(CASE WHEN q.grade > 0 THEN (100 * qg.grade / q.grade) ELSE NULL END) AS bestquizpct,
                                AVG(CASE WHEN q.grade > 0 THEN (100 * qg.grade / q.grade) ELSE NULL END) AS avgquizpct,
                                COUNT(DISTINCT qg.quiz) AS gradedquizcount
                           FROM {quiz_grades} qg
                           JOIN {quiz} q ON q.id = qg.quiz
                          WHERE qg.quiz {$quizinsql}
                            AND qg.userid {$quizuinq}
                       GROUP BY qg.userid",
                        $quizinparams + $quizuinparams
                    );
                    foreach ($quizgraderecs as $quizgraderec) {
                        $quizgradesbyuser[(int)$quizgraderec->userid] = [
                            'bestquizpct' => ($quizgraderec->bestquizpct !== null) ? max(0, min(100, (int)round((float)$quizgraderec->bestquizpct))) : null,
                            'avgquizpct' => ($quizgraderec->avgquizpct !== null) ? max(0, min(100, (int)round((float)$quizgraderec->avgquizpct))) : null,
                            'gradedquizcount' => (int)($quizgraderec->gradedquizcount ?? 0),
                        ];
                    }

                    $quizattemptrecs = $DB->get_records_sql(
                        "SELECT qa.userid,
                                COUNT(qa.id) AS totalattempts,
                                COUNT(DISTINCT qa.quiz) AS attemptedquizcount
                           FROM {quiz_attempts} qa
                          WHERE qa.quiz {$quizinsql}
                            AND qa.userid {$quizuinq}
                            AND qa.preview = 0
                       GROUP BY qa.userid",
                        $quizinparams + $quizuinparams
                    );
                    foreach ($quizattemptrecs as $quizattemptrec) {
                        $quizattemptsbyuser[(int)$quizattemptrec->userid] = [
                            'totalattempts' => (int)($quizattemptrec->totalattempts ?? 0),
                            'attemptedquizcount' => (int)($quizattemptrec->attemptedquizcount ?? 0),
                        ];
                    }
                }

                $items = [];
                foreach ($progressusers as $uid => $pu) {
                    $done = 0;
                    foreach ($trackablecmids as $cmid) {
                        $state = (int)($pu->progress[$cmid]->completionstate ?? COMPLETION_INCOMPLETE);
                        if ($state > 0) {
                            $done++;
                        }
                    }

                    $completionpct = (int)round(100 * ($done / $totaltrackable));
                    $completionpct = max(0, min(100, $completionpct));

                    $ontimedone = 0;
                    $ontimepct = null;
                    if (!empty($expectedcmids)) {
                        foreach ($expectedcmids as $ecmid) {
                            $due = (int)($duebycmid[$ecmid] ?? 0);
                            if ($due <= 0) {
                                continue;
                            }
                            $rec = $cmc[(int)$uid][(int)$ecmid] ?? null;
                            $state = (int)($rec->state ?? 0);
                            $time = (int)($rec->time ?? 0);
                            if ($state > 0 && $time > 0 && $time <= $due) {
                                $ontimedone++;
                            }
                        }

                        $den = count($expectedcmids);
                        $ontimepct = ($den > 0) ? (int)round(100 * ($ontimedone / $den)) : null;
                        if ($ontimepct !== null) {
                            $ontimepct = max(0, min(100, $ontimepct));
                        }
                    }

                    $gradepct = null;
                    if ($gradeitemid > 0 && $grademax > 0 && array_key_exists((int)$uid, $gradebyuser)) {
                        $final = (float)$gradebyuser[(int)$uid];
                        $gradepct = (int)round(100 * ($final / $grademax));
                        $gradepct = max(0, min(100, $gradepct));
                    }

                    $quizgrades = $quizgradesbyuser[(int)$uid] ?? [];
                    $avgquizpct = array_key_exists('avgquizpct', $quizgrades) ? $quizgrades['avgquizpct'] : null;
                    $bestquizpct = array_key_exists('bestquizpct', $quizgrades) ? $quizgrades['bestquizpct'] : null;
                    $gradedquizcount = (int)($quizgrades['gradedquizcount'] ?? 0);

                    $quizattempts = $quizattemptsbyuser[(int)$uid] ?? [];
                    $totalattempts = (int)($quizattempts['totalattempts'] ?? 0);
                    $attemptedquizcount = (int)($quizattempts['attemptedquizcount'] ?? 0);
                    $attemptsefficiencypct = ($totalattempts > 0)
                        ? max(0, min(100, (int)round(100 * ($attemptedquizcount / $totalattempts))))
                        : null;

                    $goodgradecomponents = [];
                    if ($avgquizpct !== null) {
                        $goodgradecomponents[] = (int)$avgquizpct;
                    }
                    if ($gradepct !== null) {
                        $goodgradecomponents[] = (int)$gradepct;
                    }
                    $goodgradepct = !empty($goodgradecomponents)
                        ? (int)round(array_sum($goodgradecomponents) / count($goodgradecomponents))
                        : null;

                    $highestgradecandidates = [];
                    if ($bestquizpct !== null) {
                        $highestgradecandidates[] = (int)$bestquizpct;
                    }
                    if ($gradepct !== null) {
                        $highestgradecandidates[] = (int)$gradepct;
                    }
                    $highestgradepct = !empty($highestgradecandidates) ? max($highestgradecandidates) : null;

                    // Overall performance blends timeliness, completion, attempt efficiency, and strong grades.
                    $components = [];
                    $components[] = ($ontimepct !== null) ? $ontimepct : $completionpct;
                    $components[] = $completionpct;
                    if ($attemptsefficiencypct !== null) {
                        $components[] = $attemptsefficiencypct;
                    }
                    if ($goodgradepct !== null) {
                        $components[] = $goodgradepct;
                    }
                    if ($highestgradepct !== null) {
                        $components[] = $highestgradepct;
                    }
                    $overall = (int)round(array_sum($components) / max(1, count($components)));
                    $overall = max(0, min(100, $overall));

                    $name = trim((string)($pu->firstname ?? '') . ' ' . (string)($pu->lastname ?? ''));
                    if ($name === '') {
                        $name = 'User ' . (int)$uid;
                    }

                    $items[] = [
                        'userid' => (int)$uid,
                        'name' => $name,
                        'department' => (string)($departmentbyuser[(int)$uid] ?? ''),
                        'clinicname' => (string)($clinicbyuser[(int)$uid] ?? ''),
                        'overall' => $overall,
                        'completionpct' => $completionpct,
                        'ontimepct' => $ontimepct,
                        'ontimedone' => $ontimedone,
                        'ontimetotal' => count($expectedcmids),
                        'gradepct' => $gradepct,
                        'goodgradepct' => $goodgradepct,
                        'highestgradepct' => $highestgradepct,
                        'avgquizpct' => $avgquizpct,
                        'bestquizpct' => $bestquizpct,
                        'gradedquizcount' => $gradedquizcount,
                        'attemptsefficiencypct' => $attemptsefficiencypct,
                        'attemptedquizcount' => $attemptedquizcount,
                        'totalattempts' => $totalattempts,
                        'done' => $done,
                        'total' => $totaltrackable,
                    ];
                }

                usort($items, static function(array $a, array $b): int {
                    if ((int)$a['overall'] !== (int)$b['overall']) {
                        return (int)$b['overall'] <=> (int)$a['overall'];
                    }
                    $bg = (int)($b['gradepct'] ?? -1);
                    $ag = (int)($a['gradepct'] ?? -1);
                    if ($ag !== $bg) {
                        return $bg <=> $ag;
                    }
                    $bo = (int)($b['ontimepct'] ?? -1);
                    $ao = (int)($a['ontimepct'] ?? -1);
                    if ($ao !== $bo) {
                        return $bo <=> $ao;
                    }
                    return strcasecmp((string)$a['name'], (string)$b['name']);
                });

                $performance['items'] = array_slice($items, 0, 10);
                $performance['participants'] = $performance['items'];

                $clinicrollups = [];
                foreach ($items as $item) {
                    $clinicname = trim((string)($item['clinicname'] ?? ''));
                    if ($clinicname === '') {
                        $clinicname = 'Unassigned Clinic';
                    }

                    if (!isset($clinicrollups[$clinicname])) {
                        $clinicrollups[$clinicname] = [
                            'name' => $clinicname,
                            'participants' => 0,
                            'overallsum' => 0,
                            'completionsum' => 0,
                            'ontimesum' => 0,
                            'ontimecount' => 0,
                            'gradesum' => 0,
                            'gradecount' => 0,
                            'goodgradesum' => 0,
                            'goodgradecount' => 0,
                            'highestgradesum' => 0,
                            'highestgradecount' => 0,
                            'attemptssum' => 0,
                            'attemptscount' => 0,
                            'attemptedquizcount' => 0,
                            'totalattempts' => 0,
                            'done' => 0,
                            'total' => 0,
                        ];
                    }

                    $clinicrollups[$clinicname]['participants']++;
                    $clinicrollups[$clinicname]['overallsum'] += (int)($item['overall'] ?? 0);
                    $clinicrollups[$clinicname]['completionsum'] += (int)($item['completionpct'] ?? 0);
                    if ($item['ontimepct'] !== null) {
                        $clinicrollups[$clinicname]['ontimesum'] += (int)$item['ontimepct'];
                        $clinicrollups[$clinicname]['ontimecount']++;
                    }
                    if ($item['gradepct'] !== null) {
                        $clinicrollups[$clinicname]['gradesum'] += (int)$item['gradepct'];
                        $clinicrollups[$clinicname]['gradecount']++;
                    }
                    if ($item['goodgradepct'] !== null) {
                        $clinicrollups[$clinicname]['goodgradesum'] += (int)$item['goodgradepct'];
                        $clinicrollups[$clinicname]['goodgradecount']++;
                    }
                    if ($item['highestgradepct'] !== null) {
                        $clinicrollups[$clinicname]['highestgradesum'] += (int)$item['highestgradepct'];
                        $clinicrollups[$clinicname]['highestgradecount']++;
                    }
                    if ($item['attemptsefficiencypct'] !== null) {
                        $clinicrollups[$clinicname]['attemptssum'] += (int)$item['attemptsefficiencypct'];
                        $clinicrollups[$clinicname]['attemptscount']++;
                    }
                    $clinicrollups[$clinicname]['attemptedquizcount'] += (int)($item['attemptedquizcount'] ?? 0);
                    $clinicrollups[$clinicname]['totalattempts'] += (int)($item['totalattempts'] ?? 0);
                    $clinicrollups[$clinicname]['done'] += (int)($item['done'] ?? 0);
                    $clinicrollups[$clinicname]['total'] += (int)($item['total'] ?? 0);
                }

                $clinicitems = [];
                foreach ($clinicrollups as $clinicrollup) {
                    $participantcount = max(1, (int)$clinicrollup['participants']);
                    $clinicitems[] = [
                        'name' => (string)$clinicrollup['name'],
                        'participantcount' => (int)$clinicrollup['participants'],
                        'overall' => (int)round($clinicrollup['overallsum'] / $participantcount),
                        'completionpct' => (int)round($clinicrollup['completionsum'] / $participantcount),
                        'ontimepct' => ($clinicrollup['ontimecount'] > 0) ? (int)round($clinicrollup['ontimesum'] / $clinicrollup['ontimecount']) : null,
                        'gradepct' => ($clinicrollup['gradecount'] > 0) ? (int)round($clinicrollup['gradesum'] / $clinicrollup['gradecount']) : null,
                        'goodgradepct' => ($clinicrollup['goodgradecount'] > 0) ? (int)round($clinicrollup['goodgradesum'] / $clinicrollup['goodgradecount']) : null,
                        'highestgradepct' => ($clinicrollup['highestgradecount'] > 0) ? (int)round($clinicrollup['highestgradesum'] / $clinicrollup['highestgradecount']) : null,
                        'attemptsefficiencypct' => ($clinicrollup['attemptscount'] > 0) ? (int)round($clinicrollup['attemptssum'] / $clinicrollup['attemptscount']) : null,
                        'attemptedquizcount' => (int)$clinicrollup['attemptedquizcount'],
                        'totalattempts' => (int)$clinicrollup['totalattempts'],
                        'done' => (int)$clinicrollup['done'],
                        'total' => (int)$clinicrollup['total'],
                    ];
                }

                usort($clinicitems, static function(array $a, array $b): int {
                    if ((int)$a['overall'] !== (int)$b['overall']) {
                        return (int)$b['overall'] <=> (int)$a['overall'];
                    }
                    if ((int)$a['participantcount'] !== (int)$b['participantcount']) {
                        return (int)$b['participantcount'] <=> (int)$a['participantcount'];
                    }
                    return strcasecmp((string)$a['name'], (string)$b['name']);
                });

                $performance['clinics'] = array_slice($clinicitems, 0, 10);
            }
        }
    }

    $enrolparams = $userparams;
    $coursefilter = '';
    if ($courseid > 0) {
        $coursefilter = ' AND c.id = :courseid';
        $enrolparams['courseid'] = $courseid;
    }

    $totalsql = "SELECT COUNT(DISTINCT CONCAT(u.id, '-', c.id))
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                  JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                 WHERE {$userwhere}{$coursefilter}";
    $totalenrolments = (int)$DB->count_records_sql($totalsql, $enrolparams);

    $completedenrolments = 0;
    $completionacts = 0;
    $completionfallbackmode = '';

    $completedsql = "SELECT COUNT(DISTINCT CONCAT(u.id, '-', c.id))
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                      JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                      JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                      JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                     WHERE {$userwhere}{$coursefilter}
                       AND cc.timecompleted IS NOT NULL AND cc.timecompleted > 0";
        $completedenrolments = (int)$DB->count_records_sql($completedsql, $enrolparams);
    $completionrate = $totalenrolments > 0 ? (int)round(($completedenrolments / $totalenrolments) * 100) : 0;

    // Fallback for course-scoped completion rate when course completion isn't configured.
    // Prefer activity-completion (cm.completion) across the course; if none, fall back to assessment attempted%.
    if ($courseid > 0 && $completionrate === 0 && $participants > 0) {
                $completionacts = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT cm.id)
               FROM {course_modules} cm
              WHERE cm.course = :courseid
                AND cm.deletioninprogress = 0
                AND cm.visible = 1
                AND cm.completion > 0",
            ['courseid' => $courseid]
        );

        if ($completionacts > 0) {
            $cmparams = $userparams + ['courseid_cm' => $courseid];
            $completedall = (int)$DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM (
                         SELECT u.id
                           FROM {user} u
                           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_cm
                           JOIN {course_modules} cm
                             ON cm.course = e.courseid
                            AND cm.deletioninprogress = 0
                            AND cm.visible = 1
                            AND cm.completion > 0
                      LEFT JOIN {course_modules_completion} cmc
                             ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id
                          WHERE {$userwhere}
                       GROUP BY u.id
                         HAVING SUM(CASE WHEN cmc.completionstate IS NOT NULL AND cmc.completionstate > 0 THEN 1 ELSE 0 END) = COUNT(DISTINCT cm.id)
                        ) t",
                $cmparams
            );

            $completionrate = (int)round(100 * ($completedall / $participants));
            $completionfallbackmode = 'activities';
        } else if ($attempted > 0) {
            // No completion tracking: treat "completion" as attempted the assessment.
            $completionrate = (int)round(100 * ($attempted / $participants));
            $completionfallbackmode = 'attempted';
        }
    }

        $pendingparams = $userparams;
    $pendingcoursefilter = '';
    if ($courseid > 0) {
        $pendingcoursefilter = ' AND cm.course = :courseid';
        $pendingparams['courseid'] = $courseid;
    }

        $pendingmodulefilter = '';
        if ($moduleid > 0) {
            $pendingmodulefilter = ' AND cm.id = :moduleid';
                $pendingparams['moduleid'] = $moduleid;
        }

        // Pending Modules: if a course is selected, count modules where not all participants have completed.
        if ($courseid > 0) {
                $pendingsql = "SELECT COUNT(1)
                                                FROM (
                                                            SELECT cm.id,
                                                                         COUNT(DISTINCT u.id) AS participants,
                                         COUNT(DISTINCT CASE WHEN cmc.completionstate IS NOT NULL AND cmc.completionstate > 0 THEN u.id END) AS completed
                                                                FROM {course_modules} cm
                                                                JOIN {course} c ON c.id = cm.course AND c.visible = 1 AND c.id > 1
                                                                JOIN {enrol} e ON e.courseid = cm.course AND e.status = 0
                                                                JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                                                                JOIN {user} u ON u.id = ue.userid
                                                     LEFT JOIN {course_modules_completion} cmc
                                                                    ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id
                                                             WHERE cm.deletioninprogress = 0
                                                                 AND cm.visible = 1
                                                                 AND cm.completion > 0
                                                                 {$pendingcoursefilter}
                                                                 {$pendingmodulefilter}
                                                                 AND {$userwhere}
                                                        GROUP BY cm.id
                                                            HAVING completed < participants
                                                 ) t";
                $pendingmodules = (int)$DB->count_records_sql($pendingsql, $pendingparams);
        } else {
                $pendingsql = "SELECT COUNT(1)
                                  FROM (
                                        SELECT cm.id,
                                               COUNT(DISTINCT u.id) AS participants,
                                               COUNT(DISTINCT CASE WHEN cmc.completionstate IS NOT NULL AND cmc.completionstate > 0 THEN u.id END) AS completed
                                          FROM {course_modules} cm
                                          JOIN {course} c ON c.id = cm.course AND c.visible = 1 AND c.id > 1
                                          JOIN {enrol} e ON e.courseid = cm.course AND e.status = 0
                                          JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                                          JOIN {user} u ON u.id = ue.userid
                                     LEFT JOIN {course_modules_completion} cmc
                                            ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id
                                         WHERE cm.deletioninprogress = 0
                                           AND cm.visible = 1
                                           AND cm.completion > 0
                                           {$pendingmodulefilter}
                                           AND {$userwhere}
                                      GROUP BY cm.id
                                        HAVING completed < participants
                                       ) t";
                $pendingmodules = (int)$DB->count_records_sql($pendingsql, $pendingparams);
        }

        $bardepartmentparams = [];
        $barcoursefilter = '';
        if ($courseid > 0) {
                $barcoursefilter = ' AND c.id = :courseid';
                $bardepartmentparams['courseid'] = $courseid;
        }
        if ($department !== '') {
            // Note: department condition already exists in $userwhere; we only provide the parameter here.
            $bardepartmentparams['department'] = $department;
        }

        if ($courseid > 0 && $moduleid > 0 && $selectedmodname === 'quiz' && !empty($selectedinstance)) {
            $girecs = $DB->get_records_sql(
                "SELECT gi.id, gi.gradepass
                   FROM {grade_items} gi
                  WHERE gi.itemtype = 'mod'
                    AND gi.itemmodule = 'quiz'
                    AND gi.iteminstance = :quizid
               ORDER BY gi.id ASC",
                ['quizid' => $selectedinstance],
                0,
                1
            );
            $gi = $girecs ? reset($girecs) : null;
            $gradeitemid = (int)($gi->id ?? 0);
            $gradepass = (float)($gi->gradepass ?? 0);

            $bardepartmentparams['gradeitemid'] = $gradeitemid;
            $bardepartmentparams['gradepass'] = $gradepass;

                $barsql = "SELECT dept.department AS department,
                                                    ROUND(100 * SUM(
                                                            CASE
                                                                WHEN gp.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= gp.gradepass THEN 1
                                                                WHEN gp.gradepass <= 0 AND gg.finalgrade IS NOT NULL THEN 1
                                                                ELSE 0
                                                            END
                                                    ) / COUNT(1)) AS passpct,
                                                    ROUND(100 * SUM(
                                                            CASE
                                                                WHEN gg.finalgrade IS NOT NULL THEN 1
                                                                ELSE 0
                                                            END
                                                    ) / COUNT(1)) AS completionpct,
                                                    ROUND(100 * SUM(
                                                            CASE
                                                                WHEN gp.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade < gp.gradepass THEN 1
                                                                ELSE 0
                                                            END
                                                    ) / COUNT(1)) AS failpct
                                                    ,ROUND(100 * SUM(CASE WHEN gg.finalgrade IS NULL THEN 1 ELSE 0 END) / COUNT(1)) AS notattemptedpct
                                         FROM (
                                                     SELECT u.id AS userid, u.department AS department
                                                         FROM {user} u
                                                         JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                                                         JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                                                         JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                                                        WHERE {$userwhere}
                                                            AND u.department <> ''
                                                                {$barcoursefilter}
                                                    ) dept
                                   CROSS JOIN (SELECT :gradepass AS gradepass) gp
                                LEFT JOIN {grade_grades} gg ON gg.userid = dept.userid AND gg.itemid = :gradeitemid
                                 GROUP BY dept.department
                                 ORDER BY COUNT(1) DESC";
        } else {
                $useassessmentbars = ($courseid > 0 && $moduleid === 0 && $assessmentgradeitemid > 0);
                if ($useassessmentbars) {
                    $bardepartmentparams['gradeitemid'] = $assessmentgradeitemid;
                    $bardepartmentparams['gradepass'] = $assessmentgradepass;

                    $barsql = "SELECT dept.department AS department,
                                        ROUND(100 * SUM(
                                            CASE
                                                WHEN gp.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= gp.gradepass THEN 1
                                                WHEN gp.gradepass <= 0 AND gg.finalgrade IS NOT NULL THEN 1
                                                ELSE 0
                                            END
                                        ) / COUNT(1)) AS passpct,
                                        ROUND(100 * SUM(
                                            CASE
                                                WHEN gg.finalgrade IS NOT NULL THEN 1
                                                ELSE 0
                                            END
                                        ) / COUNT(1)) AS completionpct,
                                        ROUND(100 * SUM(
                                            CASE
                                                WHEN gp.gradepass > 0 AND gg.finalgrade IS NOT NULL AND gg.finalgrade < gp.gradepass THEN 1
                                                ELSE 0
                                            END
                                    ) / COUNT(1)) AS failpct,
                                    ROUND(100 * SUM(CASE WHEN gg.finalgrade IS NULL THEN 1 ELSE 0 END) / COUNT(1)) AS notattemptedpct
                                 FROM (
                                        SELECT u.id AS userid, u.department AS department, c.id AS courseid
                                          FROM {user} u
                                          JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                                          JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                                          JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                                         WHERE {$userwhere}
                                           AND u.department <> ''
                                           {$barcoursefilter}
                                      ) dept
                            CROSS JOIN (SELECT :gradepass AS gradepass) gp
                       LEFT JOIN {grade_grades} gg ON gg.userid = dept.userid AND gg.itemid = :gradeitemid
                             GROUP BY dept.department
                             ORDER BY COUNT(1) DESC";
                } else {
                    // Overview / fallback: use the latest available quiz per course as the
                    // completion signal (highest course_section * 1e6 + cm.id, timeopen <= now).
                    $bardepartmentparams['bar_now']  = time();
                    $bardepartmentparams['bar_now2'] = time();
                    $barsql = "SELECT dept.department AS department,
                                        COUNT(DISTINCT dept.courseid) AS coursecount,
                                        COUNT(1) AS enrollmentcount,
                                        ROUND(100 * SUM(
                                            CASE
                                                WHEN lq.gradepass > 0 AND lqg.finalgrade IS NOT NULL AND lqg.finalgrade >= lq.gradepass THEN 1
                                                WHEN (lq.gradepass IS NULL OR lq.gradepass <= 0) AND lqg.finalgrade IS NOT NULL THEN 1
                                                ELSE 0
                                            END
                                        ) / COUNT(1)) AS passpct,
                                        ROUND(100 * SUM(
                                            CASE WHEN lqg.finalgrade IS NOT NULL THEN 1 ELSE 0 END
                                        ) / COUNT(1)) AS completionpct,
                                        ROUND(100 * SUM(
                                            CASE
                                                WHEN lq.gradepass > 0 AND lqg.finalgrade IS NOT NULL AND lqg.finalgrade < lq.gradepass THEN 1
                                                ELSE 0
                                            END
                                        ) / COUNT(1)) AS failpct,
                                        ROUND(100 * SUM(CASE WHEN lqg.finalgrade IS NULL THEN 1 ELSE 0 END) / COUNT(1)) AS notattemptedpct
                                 FROM (
                                        SELECT u.id AS userid, u.department AS department, c.id AS courseid
                                          FROM {user} u
                                          JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                                          JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                                          JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                                         WHERE {$userwhere}
                                           AND u.department <> ''
                                           {$barcoursefilter}
                                      ) dept
                       LEFT JOIN (
                                    SELECT ranked.courseid, ranked.gradeitemid, ranked.gradepass
                                      FROM (
                                               SELECT gi.courseid, gi.id AS gradeitemid, gi.gradepass,
                                                      cs.section * 1000000 + cm.id AS rank_val
                                                 FROM {grade_items} gi
                                                 JOIN {quiz} qz ON qz.id = gi.iteminstance
                                                 JOIN {course_modules} cm
                                                      ON cm.instance = qz.id
                                                     AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                                                     AND cm.deletioninprogress = 0
                                                 JOIN {course_sections} cs ON cs.id = cm.section AND cs.course = gi.courseid
                                                WHERE gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                                                  AND gi.gradepass > 0
                                                  AND (qz.timeopen = 0 OR qz.timeopen <= :bar_now)
                                           ) ranked
                                      JOIN (
                                               SELECT gi2.courseid, MAX(cs2.section * 1000000 + cm2.id) AS max_rank
                                                 FROM {grade_items} gi2
                                                 JOIN {quiz} qz2 ON qz2.id = gi2.iteminstance
                                                 JOIN {course_modules} cm2
                                                      ON cm2.instance = qz2.id
                                                     AND cm2.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                                                     AND cm2.deletioninprogress = 0
                                                 JOIN {course_sections} cs2 ON cs2.id = cm2.section AND cs2.course = gi2.courseid
                                                WHERE gi2.itemtype = 'mod' AND gi2.itemmodule = 'quiz'
                                                  AND gi2.gradepass > 0
                                                  AND (qz2.timeopen = 0 OR qz2.timeopen <= :bar_now2)
                                             GROUP BY gi2.courseid
                                           ) best ON best.courseid = ranked.courseid AND ranked.rank_val = best.max_rank
                                 ) lq ON lq.courseid = dept.courseid
                       LEFT JOIN {grade_grades} lqg ON lqg.itemid = lq.gradeitemid AND lqg.userid = dept.userid
                             GROUP BY dept.department
                             ORDER BY COUNT(1) DESC";
                }
        }

    // Important: $barsql includes {$userwhere}, so it must receive all $userparams (e.g., excluded role userids).
    $barrows = $DB->get_records_sql($barsql, $userparams + $bardepartmentparams, 0, 8);
    $bardatacompletion = [];
    $bardatapass = [];
    $bardatafail = [];
    $bardatanotattempted = [];
    $maxdepartmentcourses = 1;
    foreach ($barrows as $row) {
        $maxdepartmentcourses = max($maxdepartmentcourses, (int)($row->coursecount ?? 1));
    }
    foreach ($barrows as $row) {
        $passpct = (int)($row->passpct ?? 0);
        $failpct = (int)($row->failpct ?? 0);
        $completionpct = (int)($row->completionpct ?? $passpct);
        $notattemptedpct = (int)($row->notattemptedpct ?? max(0, 100 - $passpct - $failpct));
        $coursecoverage = (int)($row->coursecount ?? 1);
        $enrollmentcount = (int)($row->enrollmentcount ?? 0);
        $coveragefactor = 1.0;
        $readinesscompletion = max(0, min(100, $completionpct));
        $readinesspass = max(0, min(100, $passpct));
        $common = [
            'coursecount' => $coursecoverage,
            'enrollmentcount' => $enrollmentcount,
            'coveragefactor' => round($coveragefactor, 3),
        ];
        $bardatapass[] = $common + ['department' => $row->department, 'completion' => $readinesspass, 'rawpct' => $passpct];
        $bardatafail[] = $common + ['department' => $row->department, 'completion' => $failpct, 'rawpct' => $failpct];
        $bardatacompletion[] = $common + ['department' => $row->department, 'completion' => $readinesscompletion, 'rawpct' => $completionpct];
        $bardatanotattempted[] = $common + ['department' => $row->department, 'completion' => $notattemptedpct, 'rawpct' => $notattemptedpct];
    }

    if ($courseid > 0 && $moduleid === 0 && $completedenrolments === 0 && $participants > 0) {
        if ($completionfallbackmode === 'activities' && $completionacts > 0) {
            $fallbackbarparams = $userparams + ['courseid_bar_completion' => $courseid];
            $fallbackcompletionrows = $DB->get_records_sql(
                "SELECT dept.department,
                        ROUND(100 * SUM(
                            CASE
                                WHEN dept.totalmodules > 0 AND dept.completedmodules = dept.totalmodules THEN 1
                                ELSE 0
                            END
                        ) / COUNT(1)) AS completionpct
                   FROM (
                        SELECT u.id AS userid,
                               u.department AS department,
                               COUNT(DISTINCT cm.id) AS totalmodules,
                               COUNT(DISTINCT CASE
                                   WHEN cmc.completionstate IS NOT NULL AND cmc.completionstate > 0 THEN cm.id
                                   ELSE NULL
                               END) AS completedmodules
                          FROM {user} u
                          JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                          JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_bar_completion
                          JOIN {course_modules} cm
                            ON cm.course = e.courseid
                           AND cm.deletioninprogress = 0
                           AND cm.visible = 1
                           AND cm.completion > 0
                     LEFT JOIN {course_modules_completion} cmc
                            ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id
                         WHERE {$userwhere}
                           AND u.department <> ''
                      GROUP BY u.id, u.department
                   ) dept
               GROUP BY dept.department
               ORDER BY COUNT(1) DESC",
                $fallbackbarparams,
                0,
                8
            );

            $bardatacompletion = [];
            foreach ($fallbackcompletionrows as $row) {
                $bardatacompletion[] = [
                    'department' => $row->department,
                    'completion' => (int)($row->completionpct ?? 0),
                ];
            }
        } else if ($completionfallbackmode === 'attempted') {
            $bardatacompletion = [];
            foreach ($barrows as $row) {
                $notattemptedpct = (int)($row->notattemptedpct ?? 0);
                $bardatacompletion[] = [
                    'department' => $row->department,
                    'completion' => max(0, 100 - $notattemptedpct),
                ];
            }
        }
    }

    // Optional: names behind the bar chart values (admin-only UI uses this for hover panels).
    $barnames = ['completion' => [], 'pass' => [], 'fail' => []];
    if ($courseid > 0 && !empty($barrows)) {
        $deptplaceholders = [];
        $deptparams = [];
        $i = 0;
        foreach ($barrows as $row) {
            $key = 'dept' . $i;
            $deptplaceholders[] = ':' . $key;
            $deptparams[$key] = (string)$row->department;
            $i++;
        }
        $deptin = implode(',', $deptplaceholders);

        // Build a distinct enrolled-user list for the course to avoid duplicates.
        $nameparams = $userparams + $deptparams;
        $nameparams['courseid_names_enrol'] = $courseid;
        $nameparams['courseid_names_cc'] = $courseid;
        $nameparams['courseid_names_gi'] = $courseid;

        $enrolleduserssql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.department
                               FROM {user} u
                               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_names_enrol
                              WHERE {$userwhere}
                                AND u.department <> ''
                                AND u.department IN ({$deptin})";

        $useassessmentnames = ($courseid > 0 && $moduleid === 0 && $assessmentgradeitemid > 0);

        if (($courseid > 0 && $moduleid > 0 && $selectedmodname === 'quiz' && !empty($selectedinstance)) || $useassessmentnames) {
            $girecs = $DB->get_records_sql(
                "SELECT gi.id, gi.gradepass
                   FROM {grade_items} gi
                  WHERE gi.itemtype = 'mod'
                    AND gi.itemmodule = 'quiz'
                    AND gi.iteminstance = :quizid
               ORDER BY gi.id ASC",
                ['quizid' => $selectedinstance],
                0,
                1
            );
            if ($useassessmentnames) {
                $gradeitemid = $assessmentgradeitemid;
                $gradepass = $assessmentgradepass;
            } else {
                $gi = $girecs ? reset($girecs) : null;
                $gradeitemid = (int)($gi->id ?? 0);
                $gradepass = (float)($gi->gradepass ?? 0);
            }

            $nameparams['gradeitemid_names'] = $gradeitemid;
            $nameparams['gradepass_names'] = $gradepass;

            $namesql = "SELECT eu.department, eu.id, eu.firstname, eu.lastname, gg.finalgrade
                          FROM ({$enrolleduserssql}) eu
                     LEFT JOIN {grade_grades} gg ON gg.userid = eu.id AND gg.itemid = :gradeitemid_names";

            $namerows = $DB->get_records_sql($namesql, $nameparams);
            foreach ($namerows as $nr) {
                $dept = (string)($nr->department ?? '');
                $name = trim((string)$nr->firstname . ' ' . (string)$nr->lastname);
                if ($dept === '' || $name === '') {
                    continue;
                }

                $final = $nr->finalgrade;
                $isattempted = ($final !== null);
                $ispassed = $isattempted && ($gradepass <= 0 || (float)$final >= $gradepass);
                $isfailed = $isattempted && ($gradepass > 0 && (float)$final < $gradepass);

                if ($isattempted) {
                    $barnames['completion'][$dept][] = $name;
                }
                if ($ispassed) {
                    $barnames['pass'][$dept][] = $name;
                }
                if ($isfailed) {
                    $barnames['fail'][$dept][] = $name;
                }
            }
        } else {
            $namesql = "SELECT eu.department, eu.id, eu.firstname, eu.lastname,
                               cc.timecompleted, gi.gradepass, gg.finalgrade
                          FROM ({$enrolleduserssql}) eu
                     LEFT JOIN {course_completions} cc ON cc.userid = eu.id AND cc.course = :courseid_names_cc
                     LEFT JOIN {grade_items} gi ON gi.courseid = :courseid_names_gi AND gi.itemtype = 'course'
                     LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = eu.id";

            $namerows = $DB->get_records_sql($namesql, $nameparams);
            foreach ($namerows as $nr) {
                $dept = (string)($nr->department ?? '');
                $name = trim((string)$nr->firstname . ' ' . (string)$nr->lastname);
                if ($dept === '' || $name === '') {
                    continue;
                }

                $gradepass = (float)($nr->gradepass ?? 0);
                $final = $nr->finalgrade;
                $hasfinal = ($final !== null);

                $completed = (!empty($nr->timecompleted) && (int)$nr->timecompleted > 0);
                $ispassed = ($gradepass > 0 && $hasfinal && (float)$final >= $gradepass);
                $isfailed = ($gradepass > 0 && $hasfinal && (float)$final < $gradepass);

                if ($completed) {
                    $barnames['completion'][$dept][] = $name;
                }
                if ($ispassed) {
                    $barnames['pass'][$dept][] = $name;
                }
                if ($isfailed) {
                    $barnames['fail'][$dept][] = $name;
                }
            }
        }

        // Keep names in a stable order.
        foreach ($barnames as $metric => $map) {
            foreach ($map as $dept => $names) {
                sort($names, SORT_NATURAL | SORT_FLAG_CASE);
                $barnames[$metric][$dept] = array_values(array_unique($names));
            }
        }
    }

    // Chart: Module completion breakdown for selected course/module/department.
        $modulecompletion = ['Completed' => 0, 'Incomplete' => 0];
    if ($courseid > 0) {
        $mcparams = $userparams + ['courseid' => $courseid];
        $mcmodfilter = '';
        if ($moduleid > 0) {
            $mcmodfilter = ' AND cm.id = :moduleid';
            $mcparams['moduleid'] = $moduleid;
        }

        $mcsql = "SELECT
                                        SUM(CASE WHEN cmc.completionstate IS NOT NULL AND cmc.completionstate > 0 THEN 1 ELSE 0 END) AS completed,
                                        SUM(CASE WHEN cmc.completionstate IS NOT NULL AND cmc.completionstate > 0 THEN 0 ELSE 1 END) AS incomplete
                  FROM {course_modules} cm
                  JOIN {course} c ON c.id = cm.course AND c.visible = 1 AND c.id > 1
                  JOIN {enrol} e ON e.courseid = cm.course AND e.status = 0
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                  JOIN {user} u ON u.id = ue.userid
             LEFT JOIN {course_modules_completion} cmc
                                        ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id
                 WHERE cm.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND cm.visible = 1
                   AND cm.completion > 0
                   {$mcmodfilter}
                   AND {$userwhere}";
        $row = $DB->get_record_sql($mcsql, $mcparams);
        if ($row) {
            $modulecompletion['Completed'] = (int)$row->completed;
            $modulecompletion['Incomplete'] = (int)$row->incomplete;
        }
    }

    // Chart: Enrolments over time (last 14 days) for selected course/department.
    // If no course is selected (courseid = 0), show site-wide enrolments across visible courses.
    $enrolseries = [];
    $since = time() - (14 * 24 * 60 * 60);

    $escoursefilter = '';
    $esparams = $userparams + ['since' => $since];
    if ($courseid > 0) {
        $escoursefilter = ' AND e.courseid = :courseid';
        $esparams['courseid'] = $courseid;
    }

    $essql = "SELECT FROM_UNIXTIME(ue.timecreated, '%Y-%m-%d') AS day,
                     COUNT(DISTINCT ue.userid) AS cnt
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                JOIN {user} u ON u.id = ue.userid
               WHERE ue.status = 0
                 AND ue.timecreated >= :since
                 {$escoursefilter}
                 AND {$userwhere}
            GROUP BY day
            ORDER BY day ASC";
    $rows = $DB->get_records_sql($essql, $esparams);

    $daily = [];
    foreach ($rows as $r) {
        $daily[$r->day] = (int)$r->cnt;
    }

    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' days'));
        $enrolseries[] = ['day' => $day, 'count' => $daily[$day] ?? 0];
    }

    // Content engagement: SuperVideo watch time + PDF views over the last 30 days.
    // These are teacher-friendly proxies for content consumption.
    $engagementdays = 30;
    $since = time() - ($engagementdays * 24 * 60 * 60);

    $supervideowatchseries = [];
    $pdfviewseries = [];

    // Only attempt SuperVideo metrics if the plugin table exists.
    require_once($CFG->libdir . '/xmldb/xmldb_table.php');
    $manager = $DB->get_manager();
    $hassupervideo = $manager->table_exists(new xmldb_table('supervideo_view'))
        && $manager->table_exists(new xmldb_table('supervideo'));

    if ($hassupervideo) {
        $svparams = $userparams + ['since_sv' => $since];
        $svcoursefilter = '';
        if ($courseid > 0) {
            $svcoursefilter = ' AND cm.course = :sv_courseid';
            $svparams['sv_courseid'] = $courseid;
        }
        if ($moduleid > 0 && ($selectedmodname ?? '') === 'supervideo') {
            $svcoursefilter .= ' AND cm.id = :sv_cmid';
            $svparams['sv_cmid'] = $moduleid;
        }

        $svsql = "SELECT FROM_UNIXTIME(sv.timemodified, '%Y-%m-%d') AS day,
                         SUM(COALESCE(sv.currenttime, 0)) AS seconds
                    FROM {supervideo_view} sv
                    JOIN {course_modules} cm ON cm.id = sv.cm_id
                    JOIN {modules} m ON m.id = cm.module AND m.name = 'supervideo'
                    JOIN {course} c ON c.id = cm.course AND c.visible = 1 AND c.id > 1
                    JOIN {user} u ON u.id = sv.user_id
                   WHERE sv.timemodified >= :since_sv
                     AND sv.percent IS NOT NULL AND sv.percent > 0
                     {$svcoursefilter}
                     AND {$userwhere}
                GROUP BY day
                ORDER BY day ASC";

        $svrows = $DB->get_records_sql($svsql, $svparams);
        $svdaily = [];
        foreach ($svrows as $r) {
            $svdaily[$r->day] = (int)($r->seconds ?? 0);
        }
        for ($i = $engagementdays - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $supervideowatchseries[] = ['day' => $day, 'seconds' => $svdaily[$day] ?? 0];
        }
    } else {
        for ($i = $engagementdays - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $supervideowatchseries[] = ['day' => $day, 'seconds' => 0];
        }
    }

    // PDF views from standard logs (resource module views where the resource's file is a PDF).
    $hasslog = $manager->table_exists(new xmldb_table('logstore_standard_log'));
    if ($hasslog) {
        $pvparams = $userparams + ['since_pv' => $since, 'event_pv' => '\\mod_resource\\event\\course_module_viewed'];
        $pvcoursefilter = '';
        $pvjoins = "";
        if ($courseid > 0) {
            $pvcoursefilter = ' AND l.courseid = :pv_courseid';
            $pvparams['pv_courseid'] = $courseid;
        } else {
            $pvjoins .= " JOIN {course} c2 ON c2.id = l.courseid AND c2.visible = 1 AND c2.id > 1";
        }

        // If a specific module is selected and it is a resource, only count that one.
        if ($moduleid > 0 && ($selectedmodname ?? '') === 'resource') {
            $pvcoursefilter .= ' AND ctx.instanceid = :pv_cmid';
            $pvparams['pv_cmid'] = $moduleid;
        }

                $pvsql = "SELECT FROM_UNIXTIME(l.timecreated, '%Y-%m-%d') AS day,
                                                 COUNT(1) AS views
                                        FROM {logstore_standard_log} l
                                        JOIN {context} ctx ON ctx.id = l.contextid AND ctx.contextlevel = 70
                                        JOIN {course_modules} cm ON cm.id = ctx.instanceid
                                        JOIN {modules} m ON m.id = cm.module AND m.name = 'resource'
                                        JOIN {resource} r ON r.id = cm.instance
                                        JOIN {user} u ON u.id = l.userid
                                        {$pvjoins}
                                     WHERE l.timecreated >= :since_pv
                                         AND l.eventname = :event_pv
                                         {$pvcoursefilter}
                                         AND {$userwhere}
                                         AND EXISTS (
                                                 SELECT 1
                                                     FROM {files} f
                                                    WHERE f.contextid = ctx.id
                                                        AND f.component = 'mod_resource'
                                                        AND f.filearea = 'content'
                                                        AND f.filename <> '.'
                                                        AND (f.mimetype = 'application/pdf' OR LOWER(f.filename) LIKE '%.pdf')
                                         )
                                GROUP BY day
                                ORDER BY day ASC";

        $pvrows = $DB->get_records_sql($pvsql, $pvparams);
        $pvdaily = [];
        foreach ($pvrows as $r) {
            $pvdaily[$r->day] = (int)($r->views ?? 0);
        }
        for ($i = $engagementdays - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $pdfviewseries[] = ['day' => $day, 'views' => $pvdaily[$day] ?? 0];
        }
    } else {
        for ($i = $engagementdays - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $pdfviewseries[] = ['day' => $day, 'views' => 0];
        }
    }

    // Schedule-based course progress.
    // - No course selected: all visible courses, sorted with running courses first.
    // - Course selected: section/module progress based on schedule windows.
    $moduleprogress = local_admindashboard_get_schedule_progress_rows($courseid, 0);
    $moduleprogressnames = [];

    $now = time();
    $activesince = $now - (7 * 24 * 60 * 60);
    $engparams = $userparams;
    $engparams['activesince'] = $activesince;

        $engcoursefilter = '';
        if ($courseid > 0) {
            $engcoursefilter = " AND u.id IN (
                SELECT DISTINCT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                 WHERE ue.status = 0
            )";
            $engparams['courseid'] = $courseid;
        } else {
            $engcoursefilter = " AND u.id IN (
                SELECT DISTINCT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                  JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
                 WHERE ue.status = 0
            )";
        }

    $engsql = "SELECT status, COUNT(1) AS total
                 FROM (
                       SELECT CASE
                                WHEN u.lastaccess > :activesince THEN 'Active'
                                WHEN u.lastaccess = 0 THEN 'Pending'
                                ELSE 'Inactive'
                              END AS status
                         FROM {user} u
                                                WHERE {$userwhere}{$engcoursefilter}
                      ) x
             GROUP BY status";

    $engagement = ['Active' => 0, 'Inactive' => 0, 'Pending' => 0];
    $engrows = $DB->get_records_sql($engsql, $engparams);
    foreach ($engrows as $row) {
        $engagement[$row->status] = (int)$row->total;
    }

    // Skill gap radar chart data.
    // Treat every visible quiz in the selected course as a skill axis.
    $skillgap = [
        'labels' => [],
        'required' => [],
        'current' => [],
    ];

    if ($courseid > 0) {
        $quizitems = [];

        // If a specific quiz module is selected, keep it first.
        if ($moduleid > 0 && ($selectedmodname ?? '') === 'quiz') {
            $selectedquiz = $DB->get_record_sql(
                "SELECT q.id AS quizid, q.name AS quizname, gi.id AS gradeitemid, gi.grademax, gi.gradepass
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                   JOIN {quiz} q ON q.id = cm.instance
                   JOIN {grade_items} gi
                        ON gi.courseid = cm.course
                       AND gi.itemtype = 'mod'
                       AND gi.itemmodule = 'quiz'
                       AND gi.iteminstance = q.id
                       AND gi.itemnumber = 0
                  WHERE cm.id = :cmid
                    AND cm.course = :courseid
                    AND cm.deletioninprogress = 0",
                ['cmid' => $moduleid, 'courseid' => $courseid]
            );
            if ($selectedquiz) {
                $quizitems[(int)$selectedquiz->quizid] = $selectedquiz;
            }
        }

        $sql = "SELECT q.id AS quizid, q.name AS quizname, gi.id AS gradeitemid, gi.grademax, gi.gradepass
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz} q ON q.id = cm.instance
                  JOIN {grade_items} gi
                       ON gi.courseid = cm.course
                      AND gi.itemtype = 'mod'
                      AND gi.itemmodule = 'quiz'
                      AND gi.iteminstance = q.id
                     AND gi.itemnumber = 0
                 WHERE cm.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND cm.visible = 1
              ORDER BY cm.id ASC";

        $allquizitems = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        foreach ($allquizitems as $row) {
            $quizid = (int)($row->quizid ?? 0);
            if ($quizid > 0 && !isset($quizitems[$quizid])) {
                $quizitems[$quizid] = $row;
            }
        }

        foreach (array_values($quizitems) as $qi) {
            $quizname = (string)($qi->quizname ?? 'Quiz');
            $gradeitemid = (int)($qi->gradeitemid ?? 0);
            $grademax = (float)($qi->grademax ?? 0);
            $gradepass = (float)($qi->gradepass ?? 0);

            if ($gradeitemid <= 0 || $grademax <= 0) {
                continue;
            }

            $requiredpct = ($gradepass > 0) ? (int)round(100 * ($gradepass / $grademax)) : 70;
            $requiredpct = max(0, min(100, $requiredpct));

            $avgparams = $userparams + [
                'courseid' => $courseid,
                'gradeitemid' => $gradeitemid,
            ];

            $avgsql = "SELECT AVG(gg.finalgrade) AS avggrade
                         FROM {user} u
                         JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                         JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                         JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :gradeitemid AND gg.finalgrade IS NOT NULL
                        WHERE {$userwhere}";
            $avgrow = $DB->get_record_sql($avgsql, $avgparams);
            $avggrade = (float)($avgrow->avggrade ?? 0);

            $currentpct = (int)round(100 * ($avggrade / $grademax));
            $currentpct = max(0, min(100, $currentpct));

            $skillgap['labels'][] = $quizname;
            $skillgap['required'][] = $requiredpct;
            $skillgap['current'][] = $currentpct;
        }
    }

    // In overview mode (no course selected), build department-level skill axes
    // from bar completion data so the radar shows cross-department readiness.
    if ($courseid <= 0 && !empty($bardatacompletion)) {
        foreach ($bardatacompletion as $deptrow) {
            $skillgap['labels'][]   = (string)($deptrow['department'] ?? '');
            $skillgap['required'][] = 80; // Network-wide completion target.
            $skillgap['current'][]  = (int)($deptrow['completion'] ?? 0);
        }
    }

    // Resigned mid-course (course filter only): users whose enrolment is now suspended
    // (ue.status=1) but who had already attempted at least one graded module activity.
    // These are participants who enrolled, started, then withdrew / were unenrolled.
    // Also includes account-suspended enrolled users (computed above as $suspendedEnrolled).
    $resignedmidcourse = 0;
    if ($courseid > 0) {
        try {
            $resparams = $userparams + ['res_cid' => $courseid, 'res_cid2' => $courseid];
            // Use NOT EXISTS to catch both suspended (ue.status=1) and fully-removed enrolment records.
            $resignedmidcourse = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT gg.userid)
                   FROM {grade_grades} gg
                   JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.courseid = :res_cid AND gi.itemtype = 'mod'
                   JOIN {user} u ON u.id = gg.userid
                  WHERE gg.finalgrade IS NOT NULL
                    AND {$userwhere}
                    AND NOT EXISTS (
                        SELECT 1 FROM {user_enrolments} ue2
                        JOIN {enrol} e2 ON e2.id = ue2.enrolid AND e2.status = 0 AND e2.courseid = :res_cid2
                        WHERE ue2.userid = gg.userid AND ue2.status = 0
                    )",
                $resparams
            );
        } catch (\Throwable $e) {
            $resignedmidcourse = 0;
        }

        // Add account-suspended users who are still enrolled (already computed above).
        // These are distinct from the NOT EXISTS block (which requires u.suspended=0 via $userwhere).
        $resignedmidcourse += $suspendedEnrolled;

        // Add enrolment-suspended users (ue.status=1, active account) who have NO grade activity.
        // Those WITH grade activity are already counted by the NOT EXISTS block above.
        // Adding only the "no grade" subset avoids double-counting.
        try {
            $susenrolresparams = $userparams + ['sus_enrol_res_cid' => $courseid, 'sus_enrol_res_cid2' => $courseid];
            $resignedmidcourse += (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT u.id)
                   FROM {user} u
                   JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 1
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :sus_enrol_res_cid
                  WHERE {$userwhere}
                    AND NOT EXISTS (
                        SELECT 1 FROM {grade_grades} gg2
                        JOIN {grade_items} gi2 ON gi2.id = gg2.itemid AND gi2.courseid = :sus_enrol_res_cid2
                                              AND gi2.itemtype = 'mod'
                        WHERE gg2.userid = u.id AND gg2.finalgrade IS NOT NULL
                    )",
                $susenrolresparams
            );
        } catch (\Throwable $e) {
            // silent
        }
    } else {
        $resignedmidcourse = local_admindashboard_count_site_overview_resigned_midcourse_distinct_users($userwhere, $userparams);
    }

    // Total user–course enrollment rows (denominator for outcome KPI percentages).
    // Matches active enrolments (ue.status = 0, e.status = 0) and the same user cohort as pass/fail/dropped.
    $totalenrollments = 0;
    if ($courseid > 0) {
        $teparams = $userparams + ['te_cid' => $courseid];
        $totalenrollments = (int)$DB->count_records_sql(
            "SELECT COUNT(ue.id)
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :te_cid
              WHERE {$userwhere}",
            $teparams
        );
    } else {
        $totalenrollments = (int)$DB->count_records_sql(
            "SELECT COUNT(ue.id)
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
               JOIN {course} c ON c.id = e.courseid AND c.visible = 1 AND c.id > 1
              WHERE {$userwhere}",
            $userparams
        );
    }

    $notattempted = max(0, $totalenrollments - $attempted);

    if ($courseid <= 0) {
        $totalstudents = $participants;
        $completionrate = $totalenrollments > 0 ? (int)round(100 * ($passed / $totalenrollments)) : 0;
    }

    $heatmap = local_admindashboard_get_compliance_heatmap($courseid, $department, $moduleid);
    $atriskcourserunning = ($courseid > 0) ? local_admindashboard_is_course_running($courseid) : false;
    $atriskparticipants = local_admindashboard_get_at_risk_participants($courseid, $department);
    $livefeed = local_admindashboard_get_live_feed_rows($courseid, $department, 8);

    $result = [
        'total_students' => $totalstudents,
        'active_courses' => $activecourses,
        'completion_rate' => $completionrate,
        'pending_modules' => $pendingmodules,
        'participants' => $participants,
        'total_enrollments' => $totalenrollments,
        'attempted' => $attempted,
        'passed' => $passed,
        'certified' => $certified,
        'failed' => ($courseid <= 0) ? max(0, $attempted - $passed) : $failed,
        'dropped_midway' => $droppedmidway,
        'not_attempted' => $notattempted,
        'resigned_midcourse' => $resignedmidcourse,
        'selected_modname' => $selectedmodname ?? '',
        'bar_data' => $bardatapass,
        'bar_data_completion' => $bardatacompletion,
        'bar_data_pass' => $bardatapass,
        'bar_data_fail' => $bardatafail,
        'bar_data_notattempted' => $bardatanotattempted,
        'engagement' => $engagement,
        'module_completion' => $modulecompletion,
        'enrol_series' => $enrolseries,
        'supervideo_watch_series' => $supervideowatchseries,
        'pdf_view_series' => $pdfviewseries,
        'live_feed' => $livefeed,
        'module_progress' => $moduleprogress,
        'module_progress_names' => $moduleprogressnames,
        'bar_names' => $barnames,
        'compliance_heatmap' => $heatmap,
        'skill_gap' => $skillgap,
        'performance_leaderboard' => $performance,
        'at_risk_participants' => $atriskparticipants,
        'at_risk_course_running' => $atriskcourserunning,
    ];

    try {
        $result['trends'] = $includetrends ? local_admindashboard_get_kpi_trends($courseid, $department, $moduleid, $result) : [];
    } catch (\Throwable $e) {
        $result['trends'] = [];
    }

    return $result;
}

function local_admindashboard_get_upcoming_event(int $courseid = 0): array {
    global $DB;

    $now = time();

    if ($courseid > 0) {
        // Upcoming quiz (timeopen in future) for the selected course.
        $sql = "SELECT q.id, q.name, q.timeopen, q.timeclose
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id
                                          AND cm.deletioninprogress = 0
                                          AND cm.visible = 1
                  JOIN {modules} md ON md.id = cm.module AND md.name = 'quiz'
                 WHERE cm.course = :courseid
                   AND q.timeopen > :now
                 ORDER BY q.timeopen ASC";
        try {
            $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'now' => $now], 0, 1);
        } catch (\Throwable $e) {
            $rows = [];
        }
        if (!empty($rows)) {
            $quiz = reset($rows);
            $courserec = $DB->get_record('course', ['id' => $courseid], 'fullname', IGNORE_MISSING);
            $coursename = $courserec ? strip_tags((string)($courserec->fullname ?? '')) : '';
            return [
                'type'           => 'quiz',
                'label'          => format_string((string)($quiz->name ?? ''), true),
                'meta'           => userdate((int)$quiz->timeopen, get_string('strftimedatetimeshort', 'langconfig')),
                'target_ts'      => (int)$quiz->timeopen,
                'date_formatted' => userdate((int)$quiz->timeopen, get_string('strftimedatetimeshort', 'langconfig')),
                'course_name'    => $coursename,
            ];
        }
        return [
            'type' => 'none', 'label' => 'No upcoming tests',
            'meta' => 'No scheduled quizzes found for this course.',
            'target_ts' => 0, 'date_formatted' => '', 'course_name' => '',
        ];
    }

    // No course selected — first try next upcoming quiz across all visible courses,
    // then fall back to next course whose startdate is in the future.
    $sql = "SELECT q.id, q.name, q.timeopen, cm.course AS courseid, c.fullname AS coursefullname
              FROM {quiz} q
              JOIN {course_modules} cm ON cm.instance = q.id
                                      AND cm.deletioninprogress = 0
                                      AND cm.visible = 1
              JOIN {modules} md ON md.id = cm.module AND md.name = 'quiz'
              JOIN {course} c ON c.id = cm.course AND c.id > 1 AND c.visible = 1
             WHERE q.timeopen > :now
             ORDER BY q.timeopen ASC";
    try {
        $rows = $DB->get_records_sql($sql, ['now' => $now], 0, 1);
    } catch (\Throwable $e) {
        $rows = [];
    }
    if (!empty($rows)) {
        $quiz = reset($rows);
        $coursename = strip_tags((string)($quiz->coursefullname ?? ''));
        return [
            'type'           => 'quiz',
            'label'          => format_string((string)($quiz->name ?? ''), true),
            'meta'           => userdate((int)$quiz->timeopen, get_string('strftimedatetimeshort', 'langconfig')),
            'target_ts'      => (int)$quiz->timeopen,
            'date_formatted' => userdate((int)$quiz->timeopen, get_string('strftimedatetimeshort', 'langconfig')),
            'course_name'    => $coursename,
        ];
    }

    // Fall back to next course whose startdate is in the future.
    $sql = "SELECT id, fullname, startdate
              FROM {course}
             WHERE id > 1 AND visible = 1 AND startdate > :now
             ORDER BY startdate ASC";
    try {
        $rows = $DB->get_records_sql($sql, ['now' => $now], 0, 1);
    } catch (\Throwable $e) {
        $rows = [];
    }
    if (!empty($rows)) {
        $course = reset($rows);
        return [
            'type'           => 'course',
            'label'          => format_string((string)($course->fullname ?? ''), true),
            'meta'           => userdate((int)$course->startdate, get_string('strftimedate', 'langconfig')),
            'target_ts'      => (int)$course->startdate,
            'date_formatted' => userdate((int)$course->startdate, get_string('strftimedate', 'langconfig')),
            'course_name'    => '',
        ];
    }
    return [
        'type' => 'none', 'label' => 'No upcoming events',
        'meta' => 'No quizzes or courses scheduled in the future.',
        'target_ts' => 0, 'date_formatted' => '', 'course_name' => '',
    ];
}

function local_admindashboard_get_courses_overview(string $department = ''): array {
    global $DB;

    $department = trim($department);
    [$userwhere, $userparams] = local_admindashboard_build_user_filter($department);

    // Get all visible courses with enrollments first.
    $enrollsql = "SELECT c.id, c.fullname, c.shortname,
                         COUNT(DISTINCT u.id) AS enrolled
                    FROM {course} c
                    JOIN {enrol}            e  ON  e.courseid  = c.id  AND e.status  = 0
                    JOIN {user_enrolments} ue  ON  ue.enrolid  = e.id  AND ue.status = 0
                    JOIN {user}             u  ON  u.id        = ue.userid AND {$userwhere}
                   WHERE c.id > 1 AND c.visible = 1
                GROUP BY c.id, c.fullname, c.shortname
                  HAVING COUNT(DISTINCT u.id) > 0
                ORDER BY c.sortorder ASC, c.id ASC";

    try {
        $rows = $DB->get_records_sql($enrollsql, $userparams, 0, 20);
    } catch (\Throwable $e) {
        debugging('local_admindashboard_get_courses_overview SQL error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return ['courses' => []];
    }

    if (empty($rows)) {
        return ['courses' => []];
    }

    // For each course, count passed users using the assessment quiz (same method as KPIs).
    $items = [];
    foreach ($rows as $row) {
        $cid      = (int)($row->id ?? 0);
        $enrolled = (int)($row->enrolled ?? 0);

        // Try assessment quiz first (the last/final quiz in the course).
        $assessment = local_admindashboard_pick_course_assessment_quiz($cid, $userwhere, $userparams);
        if ($assessment && !empty($assessment->gradeitemid) && !empty($assessment->gradepass)) {
            $pparams = $userparams + [
                'cov_courseid' => $cid,
                'cov_giid'     => (int)$assessment->gradeitemid,
                'cov_gpass'    => (float)$assessment->gradepass,
            ];
            $psql = "SELECT COUNT(DISTINCT u.id) AS cnt
                       FROM {user} u
                       JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                       JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cov_courseid
                       JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :cov_giid
                                              AND gg.finalgrade IS NOT NULL AND gg.finalgrade >= :cov_gpass
                      WHERE {$userwhere}";
            try {
                $completed = (int)($DB->get_field_sql($psql, $pparams) ?? 0);
            } catch (\Throwable $e) {
                $completed = 0;
            }
        } else {
            // Fall back to course_completions.timecompleted > 0.
            $cparams = $userparams + ['cov_cc_courseid' => $cid];
            $csql = "SELECT COUNT(DISTINCT u.id) AS cnt
                       FROM {user} u
                       JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                       JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cov_cc_courseid
                       JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = :cov_cc_courseid2
                                                    AND cc.timecompleted > 0
                      WHERE {$userwhere}";
            try {
                $completed = (int)($DB->get_field_sql($csql,
                    $cparams + ['cov_cc_courseid2' => $cid]) ?? 0);
            } catch (\Throwable $e) {
                $completed = 0;
            }
        }

        $items[] = [
            'id'            => $cid,
            'shortname'     => (string)($row->shortname ?? ''),
            'fullname'      => (string)($row->fullname  ?? ''),
            'enrolled'      => $enrolled,
            'completed'     => $completed,
            'completionpct' => $enrolled > 0 ? round(($completed / $enrolled) * 100, 1) : 0.0,
        ];
    }

    usort($items, static fn($a, $b) => $b['completionpct'] <=> $a['completionpct']);

    return ['courses' => array_values($items)];
}

/**
 * Top participants and clinics across multiple selected courses.
 *
 * @param array<int,int> $courseids
 * @return array{selected_courseids:array<int,int>,participants:array<int,array>,clinics:array<int,array>}
 */
function local_admindashboard_get_multi_course_leaders(array $courseids, string $department = '', int $limit = 10): array {
    global $DB, $CFG;

    $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids), static function(int $id): bool {
        return $id > 1;
    })));

    if (empty($courseids)) {
        $activecourses = $DB->get_records_select('course', 'id > 1 AND visible = 1', null, 'sortorder ASC, id ASC', 'id');
        $courseids = array_map('intval', array_keys($activecourses));
    }
    if (empty($courseids)) {
        return ['selected_courseids' => [], 'participants' => [], 'clinics' => []];
    }

    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->libdir . '/completionlib.php');

    [$userwhere, $userparams] = local_admindashboard_build_user_filter(trim($department));

    [$coursesql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'mclvisiblecid');
    $visiblecourses = $DB->get_records_select(
        'course',
        "id {$coursesql} AND visible = 1",
        $courseparams,
        'sortorder ASC, id ASC',
        'id, fullname'
    );
    $courseids = array_map('intval', array_keys($visiblecourses));
    if (empty($courseids)) {
        return ['selected_courseids' => [], 'participants' => [], 'clinics' => []];
    }

    $byuser = [];

    foreach ($courseids as $courseid) {
        $course = $visiblecourses[$courseid] ?? null;
        if (!$course) {
            continue;
        }

        $completioninfo = new completion_info($course);
        if (!$completioninfo->is_enabled()) {
            continue;
        }

        $modinfo = get_fast_modinfo($courseid);
        $trackablecmids = [];
        foreach ($completioninfo->get_activities() as $cmid => $cm) {
            if (empty($modinfo->cms[$cmid])) {
                continue;
            }
            $cms = $modinfo->cms[$cmid];
            if (!empty($cms->deletioninprogress)) {
                continue;
            }
            $trackablecmids[] = (int)$cmid;
        }

        $trackablecmids = array_values(array_unique($trackablecmids));
        $totaltrackable = count($trackablecmids);
        if ($totaltrackable < 1) {
            continue;
        }

        $coursecontext = context_course::instance($courseid);
        $progressusers = $completioninfo->get_progress_all(
            $userwhere,
            $userparams,
            0,
            'u.lastname ASC, u.firstname ASC',
            0,
            0,
            $coursecontext
        );

        $userids = array_map('intval', array_keys($progressusers));
        if (empty($userids)) {
            continue;
        }

        $departmentbyuser = [];
        $clinicbyuser = [];
        $clinicfieldid = local_admindashboard_get_clinic_field_id();
        $clinicselect = 'COALESCE(u.institution, \'\') AS clinicname';
        $clinicjoin = '';
        $clinicparams = [];
        if ($clinicfieldid > 0) {
            $clinicselect = "COALESCE(NULLIF(uic.data, ''), u.institution, '') AS clinicname";
            $clinicjoin = ' LEFT JOIN {user_info_data} uic ON uic.userid = u.id AND uic.fieldid = :mclclinicfieldid';
            $clinicparams['mclclinicfieldid'] = $clinicfieldid;
        }

        [$uinq, $uinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mcldeptuid');
        $userrecords = $DB->get_records_sql(
            "SELECT u.id, COALESCE(u.department, '') AS department, {$clinicselect}
               FROM {user} u
          {$clinicjoin}
              WHERE u.id {$uinq}",
            $uinparams + $clinicparams
        );
        foreach ($userrecords as $userrecord) {
            $departmentbyuser[(int)$userrecord->id] = trim((string)($userrecord->department ?? ''));
            $clinicbyuser[(int)$userrecord->id] = trim((string)($userrecord->clinicname ?? ''));
        }

        $duebycmid = [];
        $expectedcmids = [];
        [$cminsql, $cminparams] = $DB->get_in_or_equal($trackablecmids, SQL_PARAMS_NAMED, 'mclpcmid');
        $duerecs = $DB->get_records_sql(
            "SELECT id, completionexpected
               FROM {course_modules}
              WHERE course = :mcl_courseid
                AND id {$cminsql}
                AND completionexpected IS NOT NULL
                AND completionexpected > 0",
            ['mcl_courseid' => $courseid] + $cminparams
        );
        foreach ($duerecs as $r) {
            $cmid = (int)$r->id;
            $due = (int)$r->completionexpected;
            if ($due > 0) {
                $duebycmid[$cmid] = $due;
                $expectedcmids[] = $cmid;
            }
        }
        $expectedcmids = array_values(array_unique($expectedcmids));

        $cmc = [];
        if (!empty($expectedcmids)) {
            [$uinq, $uinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mclpuid');
            [$cminsql, $cminparams] = $DB->get_in_or_equal($expectedcmids, SQL_PARAMS_NAMED, 'mclecmid');
            $cmcrecs = $DB->get_records_sql(
                "SELECT CONCAT(userid, '-', coursemoduleid) AS rowkey,
                        userid,
                        coursemoduleid,
                        completionstate,
                        timemodified
                   FROM {course_modules_completion}
                  WHERE userid {$uinq}
                    AND coursemoduleid {$cminsql}",
                $uinparams + $cminparams
            );
            foreach ($cmcrecs as $row) {
                $uid = (int)$row->userid;
                $cmid = (int)$row->coursemoduleid;
                if (!isset($cmc[$uid])) {
                    $cmc[$uid] = [];
                }
                $cmc[$uid][$cmid] = (object)[
                    'state' => (int)($row->completionstate ?? 0),
                    'time' => (int)($row->timemodified ?? 0),
                ];
            }
        }

        $gradeitemid = 0;
        $grademax = 0.0;
        $gradepass = 0.0;
        $girecs = $DB->get_records_sql(
            "SELECT gi.id, gi.grademax, gi.gradepass
               FROM {grade_items} gi
              WHERE gi.courseid = :mcl_grade_courseid
                AND gi.itemtype = 'course'
           ORDER BY gi.id ASC",
            ['mcl_grade_courseid' => $courseid],
            0,
            1
        );
        $gi = $girecs ? reset($girecs) : null;
        $gradeitemid = (int)($gi->id ?? 0);
        $grademax = (float)($gi->grademax ?? 0);
        $gradepass = (float)($gi->gradepass ?? 0);

        $gradebyuser = [];
        if ($gradeitemid > 0 && $grademax > 0) {
            [$uinq, $uinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mclguid');
            $graderecs = $DB->get_records_sql(
                "SELECT userid, finalgrade
                   FROM {grade_grades}
                  WHERE itemid = :mcl_itemid
                    AND userid {$uinq}",
                ['mcl_itemid' => $gradeitemid] + $uinparams
            );
            foreach ($graderecs as $gr) {
                $gradebyuser[(int)$gr->userid] = (float)($gr->finalgrade ?? 0);
            }
        }

        $quizids = [];
        foreach ($trackablecmids as $trackablecmid) {
            if (empty($modinfo->cms[$trackablecmid])) {
                continue;
            }
            $trackablecm = $modinfo->cms[$trackablecmid];
            if ((string)($trackablecm->modname ?? '') !== 'quiz' || empty($trackablecm->instance)) {
                continue;
            }
            $quizids[] = (int)$trackablecm->instance;
        }
        $quizids = array_values(array_unique(array_filter($quizids)));

        $quizgradesbyuser = [];
        $quizattemptsbyuser = [];
        if (!empty($quizids)) {
            [$quizinsql, $quizinparams] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'mclquizid');
            [$quizuinq, $quizuinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mclquizuid');

            $quizgraderecs = $DB->get_records_sql(
                "SELECT qg.userid,
                        MAX(CASE WHEN q.grade > 0 THEN (100 * qg.grade / q.grade) ELSE NULL END) AS bestquizpct,
                        AVG(CASE WHEN q.grade > 0 THEN (100 * qg.grade / q.grade) ELSE NULL END) AS avgquizpct,
                        COUNT(DISTINCT qg.quiz) AS gradedquizcount
                   FROM {quiz_grades} qg
                   JOIN {quiz} q ON q.id = qg.quiz
                  WHERE qg.quiz {$quizinsql}
                    AND qg.userid {$quizuinq}
               GROUP BY qg.userid",
                $quizinparams + $quizuinparams
            );
            foreach ($quizgraderecs as $quizgraderec) {
                $quizgradesbyuser[(int)$quizgraderec->userid] = [
                    'bestquizpct' => ($quizgraderec->bestquizpct !== null) ? max(0, min(100, (int)round((float)$quizgraderec->bestquizpct))) : null,
                    'avgquizpct' => ($quizgraderec->avgquizpct !== null) ? max(0, min(100, (int)round((float)$quizgraderec->avgquizpct))) : null,
                    'gradedquizcount' => (int)($quizgraderec->gradedquizcount ?? 0),
                ];
            }

            $quizattemptrecs = $DB->get_records_sql(
                "SELECT qa.userid,
                        COUNT(qa.id) AS totalattempts,
                        COUNT(DISTINCT qa.quiz) AS attemptedquizcount
                   FROM {quiz_attempts} qa
                  WHERE qa.quiz {$quizinsql}
                    AND qa.userid {$quizuinq}
                    AND qa.preview = 0
               GROUP BY qa.userid",
                $quizinparams + $quizuinparams
            );
            foreach ($quizattemptrecs as $quizattemptrec) {
                $quizattemptsbyuser[(int)$quizattemptrec->userid] = [
                    'totalattempts' => (int)($quizattemptrec->totalattempts ?? 0),
                    'attemptedquizcount' => (int)($quizattemptrec->attemptedquizcount ?? 0),
                ];
            }
        }

        foreach ($progressusers as $uid => $pu) {
            $uid = (int)$uid;
            $done = 0;
            foreach ($trackablecmids as $cmid) {
                $state = (int)($pu->progress[$cmid]->completionstate ?? COMPLETION_INCOMPLETE);
                if ($state > 0) {
                    $done++;
                }
            }

            $completionpct = max(0, min(100, (int)round(100 * ($done / $totaltrackable))));

            $ontimedone = 0;
            $ontimepct = null;
            if (!empty($expectedcmids)) {
                foreach ($expectedcmids as $ecmid) {
                    $due = (int)($duebycmid[$ecmid] ?? 0);
                    if ($due <= 0) {
                        continue;
                    }
                    $rec = $cmc[$uid][(int)$ecmid] ?? null;
                    $state = (int)($rec->state ?? 0);
                    $time = (int)($rec->time ?? 0);
                    if ($state > 0 && $time > 0 && $time <= $due) {
                        $ontimedone++;
                    }
                }
                $ontimetotal = count($expectedcmids);
                $ontimepct = ($ontimetotal > 0) ? max(0, min(100, (int)round(100 * ($ontimedone / $ontimetotal)))) : null;
            }

            $gradepct = null;
            $passed = 0;
            if ($gradeitemid > 0 && $grademax > 0 && array_key_exists($uid, $gradebyuser)) {
                $final = (float)$gradebyuser[$uid];
                $gradepct = max(0, min(100, (int)round(100 * ($final / $grademax))));
                if ($gradepass > 0 && $final >= $gradepass) {
                    $passed = 1;
                } else if ($gradepass <= 0 && $done >= $totaltrackable) {
                    $passed = 1;
                }
            } else if ($done >= $totaltrackable) {
                $passed = 1;
            }

            $quizgrades = $quizgradesbyuser[$uid] ?? [];
            $avgquizpct = array_key_exists('avgquizpct', $quizgrades) ? $quizgrades['avgquizpct'] : null;
            $bestquizpct = array_key_exists('bestquizpct', $quizgrades) ? $quizgrades['bestquizpct'] : null;
            $gradedquizcount = (int)($quizgrades['gradedquizcount'] ?? 0);

            $quizattempts = $quizattemptsbyuser[$uid] ?? [];
            $totalattempts = (int)($quizattempts['totalattempts'] ?? 0);
            $attemptedquizcount = (int)($quizattempts['attemptedquizcount'] ?? 0);
            $attemptsefficiencypct = ($totalattempts > 0)
                ? max(0, min(100, (int)round(100 * ($attemptedquizcount / $totalattempts))))
                : null;

            $goodgradecomponents = [];
            if ($avgquizpct !== null) {
                $goodgradecomponents[] = (int)$avgquizpct;
            }
            if ($gradepct !== null) {
                $goodgradecomponents[] = (int)$gradepct;
            }
            $goodgradepct = !empty($goodgradecomponents)
                ? (int)round(array_sum($goodgradecomponents) / count($goodgradecomponents))
                : null;

            $highestgradecandidates = [];
            if ($bestquizpct !== null) {
                $highestgradecandidates[] = (int)$bestquizpct;
            }
            if ($gradepct !== null) {
                $highestgradecandidates[] = (int)$gradepct;
            }
            $highestgradepct = !empty($highestgradecandidates) ? max($highestgradecandidates) : null;

            $components = [];
            $components[] = ($ontimepct !== null) ? $ontimepct : $completionpct;
            $components[] = $completionpct;
            if ($attemptsefficiencypct !== null) {
                $components[] = $attemptsefficiencypct;
            }
            if ($goodgradepct !== null) {
                $components[] = $goodgradepct;
            }
            if ($highestgradepct !== null) {
                $components[] = $highestgradepct;
            }
            $overall = max(0, min(100, (int)round(array_sum($components) / max(1, count($components)))));

            $name = trim((string)($pu->firstname ?? '') . ' ' . (string)($pu->lastname ?? ''));
            if ($name === '') {
                $name = 'User ' . $uid;
            }

            if (!isset($byuser[$uid])) {
                $byuser[$uid] = [
                    'userid' => $uid,
                    'name' => $name,
                    'department' => (string)($departmentbyuser[$uid] ?? ''),
                    'clinicname' => trim((string)($clinicbyuser[$uid] ?? '')) ?: 'Unassigned Clinic',
                    'courses' => 0,
                    'overall_sum' => 0,
                    'completion_sum' => 0,
                    'ontime_sum' => 0,
                    'ontime_count' => 0,
                    'grade_sum' => 0,
                    'grade_count' => 0,
                    'goodgrade_sum' => 0,
                    'goodgrade_count' => 0,
                    'highestgrade_sum' => 0,
                    'highestgrade_count' => 0,
                    'attempts_sum' => 0,
                    'attempts_count' => 0,
                    'attemptedquizcount' => 0,
                    'totalattempts' => 0,
                    'gradedquizcount' => 0,
                    'done' => 0,
                    'total' => 0,
                    'ontimedone' => 0,
                    'ontimetotal' => 0,
                    'completed' => 0,
                    'passed' => 0,
                    'course_names' => [],
                ];
            }

            $byuser[$uid]['courses']++;
            $byuser[$uid]['overall_sum'] += $overall;
            $byuser[$uid]['completion_sum'] += $completionpct;
            if ($ontimepct !== null) {
                $byuser[$uid]['ontime_sum'] += $ontimepct;
                $byuser[$uid]['ontime_count']++;
            }
            if ($gradepct !== null) {
                $byuser[$uid]['grade_sum'] += $gradepct;
                $byuser[$uid]['grade_count']++;
            }
            if ($goodgradepct !== null) {
                $byuser[$uid]['goodgrade_sum'] += $goodgradepct;
                $byuser[$uid]['goodgrade_count']++;
            }
            if ($highestgradepct !== null) {
                $byuser[$uid]['highestgrade_sum'] += $highestgradepct;
                $byuser[$uid]['highestgrade_count']++;
            }
            if ($attemptsefficiencypct !== null) {
                $byuser[$uid]['attempts_sum'] += $attemptsefficiencypct;
                $byuser[$uid]['attempts_count']++;
            }
            $byuser[$uid]['attemptedquizcount'] += $attemptedquizcount;
            $byuser[$uid]['totalattempts'] += $totalattempts;
            $byuser[$uid]['gradedquizcount'] += $gradedquizcount;
            $byuser[$uid]['done'] += $done;
            $byuser[$uid]['total'] += $totaltrackable;
            $byuser[$uid]['ontimedone'] += $ontimedone;
            $byuser[$uid]['ontimetotal'] += count($expectedcmids);
            $byuser[$uid]['completed'] += ($done >= $totaltrackable) ? 1 : 0;
            $byuser[$uid]['passed'] += $passed;
            $byuser[$uid]['course_names'][] = strip_tags((string)($course->fullname ?? ''));
        }
    }

    $participants = [];
    foreach ($byuser as $row) {
        $coursecount = max(1, (int)$row['courses']);
        $participants[] = [
            'userid' => (int)$row['userid'],
            'name' => $row['name'],
            'department' => $row['department'],
            'clinicname' => $row['clinicname'],
            'overall' => max(0, min(100, (int)round($row['overall_sum'] / $coursecount))),
            'completionpct' => max(0, min(100, (int)round($row['completion_sum'] / $coursecount))),
            'ontimepct' => ((int)$row['ontime_count'] > 0) ? (int)round($row['ontime_sum'] / (int)$row['ontime_count']) : null,
            'passpct' => (int)round(100 * ((int)$row['passed'] / $coursecount)),
            'gradepct' => ((int)$row['grade_count'] > 0) ? (int)round($row['grade_sum'] / (int)$row['grade_count']) : null,
            'goodgradepct' => ((int)$row['goodgrade_count'] > 0) ? (int)round($row['goodgrade_sum'] / (int)$row['goodgrade_count']) : null,
            'highestgradepct' => ((int)$row['highestgrade_count'] > 0) ? (int)round($row['highestgrade_sum'] / (int)$row['highestgrade_count']) : null,
            'attemptsefficiencypct' => ((int)$row['attempts_count'] > 0) ? (int)round($row['attempts_sum'] / (int)$row['attempts_count']) : null,
            'attemptedquizcount' => (int)$row['attemptedquizcount'],
            'totalattempts' => (int)$row['totalattempts'],
            'gradedquizcount' => (int)$row['gradedquizcount'],
            'done' => (int)$row['done'],
            'total' => (int)$row['total'],
            'ontimedone' => (int)$row['ontimedone'],
            'ontimetotal' => (int)$row['ontimetotal'],
            'completedcourses' => (int)$row['completed'],
            'passedcourses' => (int)$row['passed'],
            'coursecount' => $coursecount,
            'course_names' => array_values(array_unique($row['course_names'])),
        ];
    }

    usort($participants, static function(array $a, array $b): int {
        if ((int)$a['overall'] !== (int)$b['overall']) {
            return (int)$b['overall'] <=> (int)$a['overall'];
        }
        $bg = (int)($b['gradepct'] ?? -1);
        $ag = (int)($a['gradepct'] ?? -1);
        if ($ag !== $bg) {
            return $bg <=> $ag;
        }
        $bo = (int)($b['ontimepct'] ?? -1);
        $ao = (int)($a['ontimepct'] ?? -1);
        if ($ao !== $bo) {
            return $bo <=> $ao;
        }
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });

    $clinicrollups = [];
    foreach ($participants as $participant) {
        $clinic = trim((string)($participant['clinicname'] ?? '')) ?: 'Unassigned';
        if (!isset($clinicrollups[$clinic])) {
            $clinicrollups[$clinic] = [
                'name' => $clinic,
                'participants' => 0,
                'overall_sum' => 0,
                'completion_sum' => 0,
                'ontime_sum' => 0,
                'ontime_count' => 0,
                'grade_sum' => 0,
                'grade_count' => 0,
                'goodgrade_sum' => 0,
                'goodgrade_count' => 0,
                'highestgrade_sum' => 0,
                'highestgrade_count' => 0,
                'attempts_sum' => 0,
                'attempts_count' => 0,
                'pass_sum' => 0,
                'course_slots' => 0,
                'completed_slots' => 0,
                'passed_slots' => 0,
                'attemptedquizcount' => 0,
                'totalattempts' => 0,
                'done' => 0,
                'total' => 0,
                'ontimedone' => 0,
                'ontimetotal' => 0,
            ];
        }
        $clinicrollups[$clinic]['participants']++;
        $clinicrollups[$clinic]['overall_sum'] += (int)$participant['overall'];
        $clinicrollups[$clinic]['completion_sum'] += (int)$participant['completionpct'];
        if ($participant['ontimepct'] !== null) {
            $clinicrollups[$clinic]['ontime_sum'] += (int)$participant['ontimepct'];
            $clinicrollups[$clinic]['ontime_count']++;
        }
        $clinicrollups[$clinic]['course_slots'] += (int)$participant['coursecount'];
        $clinicrollups[$clinic]['completed_slots'] += (int)$participant['completedcourses'];
        $clinicrollups[$clinic]['passed_slots'] += (int)$participant['passedcourses'];
        $clinicrollups[$clinic]['pass_sum'] += (int)$participant['passpct'];
        if ($participant['gradepct'] !== null) {
            $clinicrollups[$clinic]['grade_sum'] += (int)$participant['gradepct'];
            $clinicrollups[$clinic]['grade_count']++;
        }
        if ($participant['goodgradepct'] !== null) {
            $clinicrollups[$clinic]['goodgrade_sum'] += (int)$participant['goodgradepct'];
            $clinicrollups[$clinic]['goodgrade_count']++;
        }
        if ($participant['highestgradepct'] !== null) {
            $clinicrollups[$clinic]['highestgrade_sum'] += (int)$participant['highestgradepct'];
            $clinicrollups[$clinic]['highestgrade_count']++;
        }
        if ($participant['attemptsefficiencypct'] !== null) {
            $clinicrollups[$clinic]['attempts_sum'] += (int)$participant['attemptsefficiencypct'];
            $clinicrollups[$clinic]['attempts_count']++;
        }
        $clinicrollups[$clinic]['attemptedquizcount'] += (int)($participant['attemptedquizcount'] ?? 0);
        $clinicrollups[$clinic]['totalattempts'] += (int)($participant['totalattempts'] ?? 0);
        $clinicrollups[$clinic]['done'] += (int)($participant['done'] ?? 0);
        $clinicrollups[$clinic]['total'] += (int)($participant['total'] ?? 0);
        $clinicrollups[$clinic]['ontimedone'] += (int)($participant['ontimedone'] ?? 0);
        $clinicrollups[$clinic]['ontimetotal'] += (int)($participant['ontimetotal'] ?? 0);
    }

    $clinics = [];
    foreach ($clinicrollups as $clinic) {
        $participantcount = max(1, (int)$clinic['participants']);
        $clinics[] = [
            'name' => $clinic['name'],
            'overall' => (int)round($clinic['overall_sum'] / $participantcount),
            'participantcount' => (int)$clinic['participants'],
            'completionpct' => (int)round($clinic['completion_sum'] / $participantcount),
            'ontimepct' => ((int)$clinic['ontime_count'] > 0) ? (int)round($clinic['ontime_sum'] / (int)$clinic['ontime_count']) : null,
            'passpct' => (int)round($clinic['pass_sum'] / $participantcount),
            'gradepct' => ((int)$clinic['grade_count'] > 0) ? (int)round($clinic['grade_sum'] / (int)$clinic['grade_count']) : null,
            'goodgradepct' => ((int)$clinic['goodgrade_count'] > 0) ? (int)round($clinic['goodgrade_sum'] / (int)$clinic['goodgrade_count']) : null,
            'highestgradepct' => ((int)$clinic['highestgrade_count'] > 0) ? (int)round($clinic['highestgrade_sum'] / (int)$clinic['highestgrade_count']) : null,
            'attemptsefficiencypct' => ((int)$clinic['attempts_count'] > 0) ? (int)round($clinic['attempts_sum'] / (int)$clinic['attempts_count']) : null,
            'attemptedquizcount' => (int)$clinic['attemptedquizcount'],
            'totalattempts' => (int)$clinic['totalattempts'],
            'done' => (int)$clinic['done'],
            'total' => (int)$clinic['total'],
            'ontimedone' => (int)$clinic['ontimedone'],
            'ontimetotal' => (int)$clinic['ontimetotal'],
            'completedcourses' => (int)$clinic['completed_slots'],
            'passedcourses' => (int)$clinic['passed_slots'],
            'coursecount' => (int)$clinic['course_slots'],
        ];
    }
    usort($clinics, static function(array $a, array $b): int {
        return ($b['overall'] <=> $a['overall']) ?: strcasecmp($a['name'], $b['name']);
    });

    return [
        'selected_courseids' => $courseids,
        'participants' => array_slice($participants, 0, max(1, $limit)),
        'clinics' => array_slice($clinics, 0, max(1, $limit)),
    ];
}
