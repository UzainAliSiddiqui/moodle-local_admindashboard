<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Marker file so we can confirm this script actually ran (even if stdout is not visible).
$marker = __DIR__ . '/_debug_send_module_reports_ran_' . date('Ymd_His') . '.txt';
@file_put_contents($marker, "ran at " . date('c') . "\n");

[$options, $unrecognized] = cli_get_params(
    [
        'courseid' => 0,
        'coursename' => '',
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($unrecognized)) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}

if (!empty($options['help'])) {
    echo "Debug why local_admindashboard module report emails are not sending\n\n";
    echo "Options:\n";
    echo "--courseid=ID\n";
    echo "--coursename=substring\n";
    exit(0);
}

$courseid = (int)($options['courseid'] ?? 0);
$coursename = trim((string)($options['coursename'] ?? ''));

if ($courseid <= 0 && $coursename === '') {
    $coursename = 'Key skills for Effective Assessment in Primary Care 2026';
}

// Force MailHog.
$CFG->smtphosts = 'mailhog:1025';
$CFG->smtpsecure = '';

set_debugging(DEBUG_DEVELOPER, true);

$now = time();

// Send a quick "started" email so we know execution reached this point.
$starterrecipient = $DB->get_record('user', ['username' => 'admin'], '*', IGNORE_MISSING);
if ($starterrecipient) {
    $starterrecipient->email = 'debug-module-reports@example.test';
    $from = \core_user::get_noreply_user();
    @email_to_user(
        $starterrecipient,
        $from,
        'DEBUG: send_module_reports START ' . date('Y-m-d H:i:s', $now),
        "debug script started at " . date('c', $now) . "\nmarker: {$marker}\n",
        '<pre>' . s("debug script started at " . date('c', $now) . "\nmarker: {$marker}\n") . '</pre>'
    );
}

try {
    if ($courseid > 0) {
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    } else {
        $course = $DB->get_record_sql(
            "SELECT * FROM {course} WHERE id > 1 AND fullname LIKE :name ORDER BY id ASC",
            ['name' => '%' . $coursename . '%'],
            IGNORE_MULTIPLE
        );
        if (!$course) {
            throw new \moodle_exception("Course not found by name substring: {$coursename}");
        }
        $courseid = (int)$course->id;
    }

$leadseconds = 86400;

// Load the task class (so we reuse its helper methods).
require_once($CFG->dirroot . '/local/admindashboard/classes/task/send_module_reports.php');
$task = new \local_admindashboard\task\send_module_reports();

$coursectx = \context_course::instance($courseid, IGNORE_MISSING);

$lines = [];
$lines[] = '=== local_admindashboard send_module_reports DEBUG ===';
$lines[] = 'Now: ' . date('c', $now) . " (unix {$now})";
$lines[] = 'Course: ' . $course->fullname . " (id={$courseid})";
$lines[] = 'Timezone (PHP): ' . (date_default_timezone_get() ?: '(none)');

if (!$coursectx) {
    $lines[] = 'Course context: MISSING (skip)';
} else {
    // Teacher recipients.
    $teachers = get_enrolled_users(
        $coursectx,
        'moodle/grade:viewall',
        0,
        'u.id, u.firstname, u.lastname, u.email, u.lang, u.mailformat, u.suspended, u.deleted'
    );
    $teachercount = is_array($teachers) ? count($teachers) : 0;
    $lines[] = "Teacher recipients (grade:viewall): {$teachercount}";

    // Sections.
    $sections = $DB->get_records_sql(
        "SELECT id, section, name, availability
           FROM {course_sections}
          WHERE course = :courseid
            AND section > 0
       ORDER BY section ASC",
        ['courseid' => $courseid]
    );

    $sectionsbynum = [];
    foreach ($sections as $s) {
        $sectionsbynum[(int)$s->section] = $s;
    }

    $lines[] = 'Sections: ' . count($sections);

    foreach ($sections as $nextsection) {
        $nextnum = (int)$nextsection->section;

        // Use reflection to call the private get_section_start_time.
        $ref = new \ReflectionClass($task);
        $m = $ref->getMethod('get_section_start_time');
        $m->setAccessible(true);
        $starttime = (int)$m->invoke($task, $courseid, $nextsection);

        $sendafter = $starttime > 0 ? ($starttime - $leadseconds) : 0;
        $inwindow = ($starttime > 0) && ($now >= $sendafter) && ($now < $starttime);

        $lines[] = '';
        $lines[] = "Next section {$nextnum} (sectionid=" . (int)$nextsection->id . ')';
        $lines[] = '  starttime: ' . ($starttime > 0 ? date('c', $starttime) : '0');
        $lines[] = '  sendafter: ' . ($sendafter > 0 ? date('c', $sendafter) : '0');
        $lines[] = '  in 24h window: ' . ($inwindow ? 'YES' : 'no');

        if (!$inwindow) {
            continue;
        }

        $prevnum = $nextnum - 1;
        if ($prevnum <= 0 || empty($sectionsbynum[$prevnum])) {
            $lines[] = "  prev section {$prevnum}: missing (skip)";
            continue;
        }

        $prev = $sectionsbynum[$prevnum];
        $already = $DB->record_exists('local_admindashboard_modulereport', [
            'courseid' => $courseid,
            'sectionid' => (int)$prev->id,
        ]);
        $lines[] = "  prev section {$prevnum} (sectionid=" . (int)$prev->id . '):';
        $lines[] = '    already sent: ' . ($already ? 'YES (skip)' : 'no');

        // Try building report and indicate the likely fail reason.
        try {
            $ref2 = new \ReflectionClass($task);
            $m2 = $ref2->getMethod('build_section_passfail_report');
            $m2->setAccessible(true);
            $report = $m2->invoke($task, $courseid, $course, $prevnum);
            if ($report === null) {
                $lines[] = '    report: NULL (skip)';

                // Quick hints: was there a quiz? was there a grade item? gradepass/grademax?
                require_once($CFG->dirroot . '/course/lib.php');
                $modinfo = get_fast_modinfo($courseid);
                $cmids = $modinfo->sections[$prevnum] ?? [];
                $quizcms = [];
                foreach ($cmids as $cmid) {
                    $cm = $modinfo->cms[$cmid] ?? null;
                    if ($cm && empty($cm->deletioninprogress) && $cm->modname === 'quiz') {
                        $quizcms[] = $cm;
                    }
                }
                $lines[] = '    quizzes in prev section: ' . count($quizcms);
                if (!empty($quizcms)) {
                    $pickedcm = $quizcms[count($quizcms) - 1];
                    $lines[] = '    last quiz picked: ' . $pickedcm->name . ' (cmid=' . (int)$pickedcm->id . ', instance=' . (int)$pickedcm->instance . ')';
                    $gi = $DB->get_record_sql(
                        "SELECT gi.id, gi.gradepass, gi.grademax
                           FROM {grade_items} gi
                          WHERE gi.itemtype='mod' AND gi.itemmodule='quiz' AND gi.iteminstance=:quizid",
                        ['quizid' => (int)$pickedcm->instance],
                        IGNORE_MULTIPLE
                    );
                    if ($gi) {
                        $lines[] = '    grade_item: id=' . (int)$gi->id . ' gradepass=' . (float)$gi->gradepass . ' grademax=' . (float)$gi->grademax;
                    } else {
                        $lines[] = '    grade_item: NOT FOUND';
                    }
                }
            } else {
                $lines[] = '    report: OK';
                $lines[] = '    subject: ' . $report['subject'];
            }
        } catch (\Throwable $e) {
            $lines[] = '    report build ERROR: ' . $e->getMessage();
        }
    }
}

$bodytext = implode("\n", $lines);
$bodyhtml = '<pre>' . s($bodytext) . '</pre>';

// Send to a guaranteed-existing user, but override the email to a MailHog inbox.
$recipient = $DB->get_record('user', ['username' => 'admin'], '*', IGNORE_MISSING);
if (!$recipient) {
    $recipient = $DB->get_record_sql("SELECT * FROM {user} WHERE deleted=0 ORDER BY id ASC", [], IGNORE_MULTIPLE);
}
if (!$recipient) {
    cli_error('Could not find any user record to use as recipient');
}
$recipient->email = 'debug-module-reports@example.test';

$from = \core_user::get_noreply_user();
$subject = 'DEBUG: send_module_reports ' . date('Y-m-d H:i:s', $now);

email_to_user($recipient, $from, $subject, $bodytext, $bodyhtml);

mtrace('Debug email sent to MailHog inbox: ' . $recipient->email);

} catch (\Throwable $e) {
    // Always try to send the error to MailHog.
    $fallback = $DB->get_record('user', ['username' => 'admin'], '*', IGNORE_MISSING);
    if ($fallback) {
        $fallback->email = 'debug-module-reports@example.test';
        $from = \core_user::get_noreply_user();
        $msg = "DEBUG SCRIPT ERROR\n\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "\n\nmarker: {$marker}\n";
        @email_to_user($fallback, $from, 'DEBUG: send_module_reports ERROR', $msg, '<pre>' . s($msg) . '</pre>');
    }
    throw $e;
}
