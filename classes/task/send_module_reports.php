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

use core_user;
use html_writer;
use moodle_url;

class send_module_reports extends \core\task\scheduled_task {
    // How long before the next module start we should send the previous module report.
    private const LEAD_SECONDS = 86400; // 24 hours.

    private function debug(string $message): void {
        if (PHP_SAPI !== 'cli') {
            return;
        }
        if (!getenv('LOCAL_ADMINDASHBOARD_DEBUG')) {
            return;
        }
        mtrace('[local_admindashboard] ' . $message);
    }

    public function get_name(): string {
        return get_string('task_send_module_reports', 'local_admindashboard');
    }

    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/admindashboard/metricslib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $now = time();
        $this->debug('send_module_reports starting at ' . date('c', $now));

        // Only consider visible courses.
        $courses = $DB->get_records_sql(
            "SELECT id, fullname
               FROM {course}
              WHERE id > 1 AND visible = 1
           ORDER BY id ASC"
        );

        foreach ($courses as $course) {
            $courseid = (int)$course->id;
            $this->debug("Course {$courseid}: {$course->fullname}");
            $coursectx = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$coursectx) {
                $this->debug("Course {$courseid}: missing course context, skipping");
                continue;
            }

            $teachers = $this->get_course_teacher_recipients($coursectx);
            if (empty($teachers)) {
                $this->debug("Course {$courseid}: no teacher recipients (grade:viewall), skipping");
                continue;
            }

            $this->debug('Course ' . $courseid . ': teacher recipients=' . count($teachers));

            $sections = $DB->get_records_sql(
                "SELECT id, section, name, availability
                   FROM {course_sections}
                  WHERE course = :courseid
                    AND section > 0
               ORDER BY section ASC",
                ['courseid' => $courseid]
            );

            if (empty($sections)) {
                $this->debug("Course {$courseid}: no sections found, skipping");
                continue;
            }

            $sectionsbynum = [];
            foreach ($sections as $s) {
                $sectionsbynum[(int)$s->section] = $s;
            }

            foreach ($sections as $nextsection) {
                $nextsectionnum = (int)$nextsection->section;

                $starttime = $this->get_section_start_time($courseid, $nextsection);
                if ($starttime <= 0) {
                    $this->debug("Course {$courseid}: section {$nextsectionnum} starttime=0, skipping");
                    continue;
                }

                $sendafter = $starttime - self::LEAD_SECONDS;
                if ($now < $sendafter || $now >= $starttime) {
                    $this->debug(
                        "Course {$courseid}: section {$nextsectionnum} window not active (now=" . date('c', $now) .
                        ", sendafter=" . date('c', $sendafter) . ', starttime=' . date('c', $starttime) . '), skipping'
                    );
                    continue;
                }

                $prevsectionnum = $nextsectionnum - 1;
                if ($prevsectionnum <= 0 || empty($sectionsbynum[$prevsectionnum])) {
                    $this->debug("Course {$courseid}: section {$nextsectionnum} has no previous section, skipping");
                    continue;
                }

                $prevsection = $sectionsbynum[$prevsectionnum];

                // Don't resend.
                if ($DB->record_exists('local_admindashboard_modulereport', [
                    'courseid' => $courseid,
                    'sectionid' => (int)$prevsection->id,
                ])) {
                    $this->debug(
                        "Course {$courseid}: prev section {$prevsectionnum} already sent (sectionid=" . (int)$prevsection->id . '), skipping'
                    );
                    continue;
                }

                $report = $this->build_section_passfail_report($courseid, $course, $prevsectionnum);
                if ($report === null) {
                    $this->debug("Course {$courseid}: prev section {$prevsectionnum} report is null, skipping");
                    continue;
                }

                $this->debug("Course {$courseid}: sending report for prev section {$prevsectionnum} to teachers");

                $from = core_user::get_noreply_user();
                $sentany = false;

                foreach ($teachers as $teacher) {
                    if (empty($teacher->email) || !empty($teacher->suspended) || !empty($teacher->deleted)) {
                        $this->debug('Skipping teacher id=' . ($teacher->id ?? '?') . ' (no email/suspended/deleted)');
                        continue;
                    }
                    $ok = email_to_user($teacher, $from, $report['subject'], $report['text'], $report['html']);
                    if ($ok) {
                        $sentany = true;
                        $this->debug('Sent to teacher id=' . (int)$teacher->id . ' email=' . $teacher->email);
                    } else {
                        $this->debug('FAILED sending to teacher id=' . (int)$teacher->id . ' email=' . $teacher->email);
                    }
                }

                if ($sentany) {
                    $record = (object)[
                        'courseid' => $courseid,
                        'sectionid' => (int)$prevsection->id,
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ];
                    $DB->insert_record('local_admindashboard_modulereport', $record);
                    $this->debug("Course {$courseid}: recorded as sent for prev section {$prevsectionnum}");
                } else {
                    $this->debug("Course {$courseid}: did not send to any teachers for prev section {$prevsectionnum}");
                }
            }
        }

        $this->debug('send_module_reports finished');
    }

    /**
     * Returns course users who should receive module reports.
     *
     * We use grade:viewall as a practical "teacher" proxy.
     *
     * @param \context_course $coursectx
     * @return array
     */
    private function get_course_teacher_recipients(\context_course $coursectx): array {
        $users = get_enrolled_users(
            $coursectx,
            'moodle/grade:viewall',
            0,
            'u.id, u.firstname, u.lastname, u.email, u.lang, u.mailformat, u.suspended, u.deleted'
        );

        $users = $users ?: [];
        if (!empty($users)) {
            return array_values($users);
        }

        // Fallback: some sites use custom teacher roles without moodle/grade:viewall.
        // Try standard teacher archetypes.
        require_once(__DIR__ . '/../../../../lib/accesslib.php');
        $roleids = [];
        foreach (['editingteacher', 'teacher'] as $archetype) {
            $roles = get_archetype_roles($archetype);
            foreach ($roles as $role) {
                $roleids[] = (int)$role->id;
            }
        }
        $roleids = array_values(array_unique(array_filter($roleids)));
        if (empty($roleids)) {
            return [];
        }

        $merged = [];
        foreach ($roleids as $roleid) {
            $roleusers = get_role_users(
                $roleid,
                $coursectx,
                false,
                'u.id, u.firstname, u.lastname, u.email, u.lang, u.mailformat, u.suspended, u.deleted'
            );
            foreach (($roleusers ?: []) as $u) {
                $merged[(int)$u->id] = $u;
            }
        }

        return array_values($merged);
    }

    /**
     * Determine when a module (course section) starts.
     *
     * Strategy:
     * - Prefer the earliest date restriction in section availability JSON ("Available from") as the module start.
     * - Otherwise fall back to quiz open time(s) inside the section.
     */
    private function get_section_start_time(int $courseid, $section): int {
        global $DB;

        // Prefer section availability restriction date ("Available from").
        $availtime = $this->extract_earliest_availability_time($section->availability ?? null);
        if ($availtime > 0) {
            return $availtime;
        }

        // Otherwise, prefer the "pre test" quiz open time if present.
        // This matches common naming like "Module N Pre test" and prevents an earlier "Post test"
        // from accidentally becoming the module start.
        $params = ['courseid' => $courseid, 'sectionid' => (int)$section->id];
        $quiztime = (int)$DB->get_field_sql(
            "SELECT MIN(q.timeopen)
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
               JOIN {quiz} q ON q.id = cm.instance
              WHERE cm.course = :courseid
                AND cm.section = :sectionid
                AND cm.deletioninprogress = 0
                AND cm.visible = 1
                AND q.timeopen > 0
                AND (LOWER(q.name) LIKE :pretest1 OR LOWER(q.name) LIKE :pretest2)",
            $params + ['pretest1' => '%pre%test%', 'pretest2' => '%pretest%']
        );
        if ($quiztime > 0) {
            return $quiztime;
        }

        // Otherwise use the earliest quiz open time in the section.
        $quiztime = (int)$DB->get_field_sql(
            "SELECT MIN(q.timeopen)
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
               JOIN {quiz} q ON q.id = cm.instance
              WHERE cm.course = :courseid
                AND cm.section = :sectionid
                AND cm.deletioninprogress = 0
                AND cm.visible = 1
                AND q.timeopen > 0",
            $params
        );
        if ($quiztime > 0) {
            return $quiztime;
        }

        return 0;
    }

    private function extract_earliest_availability_time(?string $availabilityjson): int {
        if (empty($availabilityjson)) {
            return 0;
        }

        $decoded = json_decode($availabilityjson, true);
        if (!is_array($decoded)) {
            return 0;
        }

        $times = [];
        $this->walk_availability_tree($decoded, $times);
        if (empty($times)) {
            return 0;
        }

        return (int)min($times);
    }

    private function walk_availability_tree($node, array &$times): void {
        if (is_array($node)) {
            if (($node['type'] ?? '') === 'date' && isset($node['t'])) {
                $t = (int)$node['t'];
                if ($t > 0) {
                    $times[] = $t;
                }
            }
            foreach ($node as $v) {
                $this->walk_availability_tree($v, $times);
            }
        }
    }

    /**
     * Builds the report for a specific section (module) based on the last quiz in that section with gradepass.
     *
     * @return array|null
     */
    private function build_section_passfail_report(int $courseid, $course, int $sectionnum): ?array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $modinfo = get_fast_modinfo($courseid);
        $cmids = $modinfo->sections[$sectionnum] ?? [];
        if (empty($cmids)) {
            return null;
        }

        $pickedcm = null;
        for ($i = count($cmids) - 1; $i >= 0; $i--) {
            $cmid = $cmids[$i];
            $cm = $modinfo->cms[$cmid] ?? null;
            if (!$cm) {
                continue;
            }
            if (!empty($cm->deletioninprogress)) {
                continue;
            }
            if ($cm->modname !== 'quiz') {
                continue;
            }
            $pickedcm = $cm;
            break;
        }

        if (!$pickedcm) {
            return null;
        }

        $gi = $DB->get_record_sql(
            "SELECT gi.id, gi.gradepass, gi.grademax
               FROM {grade_items} gi
              WHERE gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND gi.iteminstance = :quizid
           ORDER BY gi.id ASC",
            ['quizid' => (int)$pickedcm->instance],
            IGNORE_MULTIPLE
        );

        $gradeitemid = (int)($gi->id ?? 0);
        $gradepass = (float)($gi->gradepass ?? 0);
        $grademax = (float)($gi->grademax ?? 0);

        // If Grade to pass is not configured, default to 70% (course requirement).
        if ($gradepass <= 0) {
            if ($grademax <= 0) {
                $grademax = (float)$DB->get_field('quiz', 'grade', ['id' => (int)$pickedcm->instance]);
            }
            if ($grademax > 0) {
                $gradepass = $grademax * 0.7;
            }
        }

        if ($gradepass <= 0) {
            return null;
        }

        $quizname = format_string($pickedcm->name, true, ['context' => $pickedcm->context]);
        $modulename = $this->get_module_label($course, $sectionnum);

        [$userwhere, $userparams] = \local_admindashboard_build_user_filter('');

        $params = $userparams;
        $params['courseid_enrol'] = $courseid;
        $params['gradeitemid'] = $gradeitemid;
        $params['quizid'] = (int)$pickedcm->instance;

        $enrolleduserssql = "SELECT DISTINCT u.id
                               FROM {user} u
                               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid_enrol
                              WHERE {$userwhere}";

           if ($gradeitemid > 0) {
              $rowsql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, gg.finalgrade
                        FROM ({$enrolleduserssql}) eu
                        JOIN {user} u ON u.id = eu.id
                    LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :gradeitemid";
           } else {
              // Fallback if the grade item is missing for any reason: use quiz_grades.
              $rowsql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, qg.grade AS finalgrade
                        FROM ({$enrolleduserssql}) eu
                        JOIN {user} u ON u.id = eu.id
                    LEFT JOIN {quiz_grades} qg ON qg.userid = u.id AND qg.quiz = :quizid";
           }

        $rows = $DB->get_records_sql($rowsql, $params);

        $participants = 0;
        $attempted = 0;
        $passed = 0;
        $failed = 0;
        $notattempted = 0;

        $passedlist = [];
        $failedlist = [];
        $notattemptedlist = [];

        foreach ($rows as $r) {
            $participants++;
            $final = $r->finalgrade;
            if ($final === null) {
                $notattempted++;
                $notattemptedlist[] = $r;
                continue;
            }

            $attempted++;
            if ((float)$final >= $gradepass) {
                $passed++;
                $passedlist[] = $r;
            } else {
                $failed++;
                $failedlist[] = $r;
            }
        }

        $url = new moodle_url('/local/admindashboard/passfail_report.php', [
            'courseid' => $courseid,
            'moduleid' => (int)$pickedcm->id,
        ]);

        $subject = get_string('email_modulereport_subject', 'local_admindashboard', (object)[
            'course' => $course->fullname,
            'module' => $modulename,
        ]);

        $kpitext = "Participants: {$participants}\nAttempted: {$attempted}\nPassed: {$passed}\nFailed: {$failed}\nNot Attempted: {$notattempted}\n";

        $text = "{$subject}\n\nCourse: {$course->fullname}\nModule: {$modulename}\nQuiz: {$quizname}\n\n" .
            $kpitext .
            "\nOpen full report: {$url->out(false)}\n";

        $html = $this->render_html_report(
            $course,
            $modulename,
            $quizname,
            $participants,
            $attempted,
            $passed,
            $failed,
            $notattempted,
            $passedlist,
            $failedlist,
            $notattemptedlist,
            $url
        );

        return [
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ];
    }

    private function get_module_label($course, int $sectionnum): string {
        $name = '';
        try {
            $name = get_section_name($course, $sectionnum);
        } catch (\Throwable $e) {
            $name = '';
        }

        $name = trim((string)$name);
        if ($name !== '') {
            return $name;
        }

        return 'Module ' . $sectionnum;
    }

    private function render_html_report(
        $course,
        string $modulename,
        string $quizname,
        int $participants,
        int $attempted,
        int $passed,
        int $failed,
        int $notattempted,
        array $passedlist,
        array $failedlist,
        array $notattemptedlist,
        moodle_url $reporturl
    ): string {
        $kpitables = html_writer::tag('table',
            html_writer::tag('tr',
                html_writer::tag('th', 'Participants') . html_writer::tag('td', (string)$participants)
            ) .
            html_writer::tag('tr',
                html_writer::tag('th', 'Attempted') . html_writer::tag('td', (string)$attempted)
            ) .
            html_writer::tag('tr',
                html_writer::tag('th', 'Passed') . html_writer::tag('td', (string)$passed)
            ) .
            html_writer::tag('tr',
                html_writer::tag('th', 'Failed') . html_writer::tag('td', (string)$failed)
            ) .
            html_writer::tag('tr',
                html_writer::tag('th', 'Not Attempted') . html_writer::tag('td', (string)$notattempted)
            ),
            ['border' => '1', 'cellpadding' => '6', 'cellspacing' => '0', 'style' => 'border-collapse:collapse;width:100%']
        );

        $html = '';
        $html .= html_writer::tag('h2', s(get_string('email_modulereport_title', 'local_admindashboard')));
        $html .= html_writer::tag('p', '<b>Course:</b> ' . s($course->fullname) . '<br><b>Module:</b> ' . s($modulename) . '<br><b>Quiz:</b> ' . s($quizname));
        $html .= html_writer::tag('h3', 'KPIs');
        $html .= $kpitables;
        $html .= html_writer::tag('p', html_writer::link($reporturl, 'Open full report'));

        $html .= $this->render_user_table('Failed', $failedlist);
        $html .= $this->render_user_table('Passed', $passedlist);
        $html .= $this->render_user_table('Not Attempted', $notattemptedlist);

        return $html;
    }

    private function render_user_table(string $title, array $rows): string {
        $html = html_writer::tag('h3', s($title) . ' candidates');

        if (empty($rows)) {
            return $html . html_writer::tag('p', 'None');
        }

        // Limit table size to keep emails reasonable.
        $max = 200;
        $sliced = array_slice($rows, 0, $max);

        $thead = html_writer::tag('tr',
            html_writer::tag('th', 'Name') .
            html_writer::tag('th', 'Email') .
            html_writer::tag('th', 'Department') .
            html_writer::tag('th', 'Grade')
        );

        $tbody = '';
        foreach ($sliced as $r) {
            $name = fullname($r);
            $grade = ($r->finalgrade === null) ? '-' : format_float((float)$r->finalgrade, 2);
            $tbody .= html_writer::tag('tr',
                html_writer::tag('td', s($name)) .
                html_writer::tag('td', s($r->email ?? '')) .
                html_writer::tag('td', s($r->department ?? '')) .
                html_writer::tag('td', s($grade))
            );
        }

        $table = html_writer::tag('table',
            html_writer::tag('thead', $thead) . html_writer::tag('tbody', $tbody),
            ['border' => '1', 'cellpadding' => '6', 'cellspacing' => '0', 'style' => 'border-collapse:collapse;width:100%']
        );

        $html .= $table;

        if (count($rows) > $max) {
            $html .= html_writer::tag('p', 'Showing first ' . $max . ' rows. Open the full report for the complete list.');
        }

        return $html;
    }
}
