<?php
// CLI debug utility for admin_dashboard completion/pass detection.
// Usage: php admin_dashboard/tools/debug_course_completion.php <courseid> [department]

define('CLI_SCRIPT', true);

require_once(dirname(__DIR__, 2) . '/config.php');

global $DB;

$courseid = (int)($argv[1] ?? 0);
$department = (string)($argv[2] ?? '');

if ($courseid <= 0) {
    fwrite(STDERR, "Usage: php admin_dashboard/tools/debug_course_completion.php <courseid> [department]\n");
    exit(1);
}

$course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);

echo "Course: {$course->fullname} (id={$courseid})\n";
if ($department !== '') {
    echo "Department filter: {$department}\n";
}

echo "\n";

$baseuserwhere = "u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0 AND u.username <> :guest";
$baseuserparams = ['guest' => 'guest'];

$deptsql = '';
$deptparams = [];
if ($department !== '') {
    $deptsql = ' AND u.department = :dept';
    $deptparams = ['dept' => $department];
}

$enrolled = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT u.id)
       FROM {user} u
       JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
       JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cid
      WHERE {$baseuserwhere}{$deptsql}",
    array_merge(['cid' => $courseid], $baseuserparams, $deptparams)
);

echo "Enrolled users: {$enrolled}\n";

$coursecompleted = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT u.id)
       FROM {user} u
       JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
       JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cid
       JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = :cid_cc
      WHERE {$baseuserwhere}{$deptsql}
        AND cc.timecompleted IS NOT NULL AND cc.timecompleted > 0",
    array_merge(['cid' => $courseid, 'cid_cc' => $courseid], $baseuserparams, $deptparams)
);

echo "Course completion (course_completions.timecompleted>0): {$coursecompleted}\n";

echo "\n";

$coursegi = $DB->get_record_sql(
    "SELECT id, gradepass
       FROM {grade_items}
      WHERE courseid = :cid AND itemtype = 'course'",
    ['cid' => $courseid],
    IGNORE_MISSING
);

if (!$coursegi) {
    echo "Course grade item: NONE\n";
} else {
    $gp = (float)$coursegi->gradepass;
    echo "Course grade item: id={$coursegi->id} gradepass={$gp}\n";
    if ($gp > 0) {
        $passed = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id)
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cid
               JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :giid
              WHERE {$baseuserwhere}{$deptsql}
                AND gg.finalgrade IS NOT NULL
                AND gg.finalgrade >= :gp",
            array_merge(
                ['cid' => $courseid, 'giid' => $coursegi->id, 'gp' => $gp],
                $baseuserparams,
                $deptparams
            )
        );
        echo "Course gradepass passed (finalgrade>=gradepass): {$passed}\n";
    }
}

echo "\n";

$quizitems = $DB->get_records_sql(
    "SELECT cm.id AS id,
            cs.section AS sectionnum,
            q.id AS quizid,
            q.name AS quizname,
            gi.id AS gradeitemid,
            gi.gradepass AS gradepass
       FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
       JOIN {quiz} q ON q.id = cm.instance
       JOIN {course_sections} cs ON cs.id = cm.section
       JOIN {grade_items} gi ON gi.courseid = cm.course
                           AND gi.itemtype = 'mod'
                           AND gi.itemmodule = 'quiz'
                           AND gi.iteminstance = q.id
      WHERE cm.course = :cid
        AND cm.deletioninprogress = 0
        AND gi.gradepass IS NOT NULL AND gi.gradepass > 0
   ORDER BY cs.section DESC, cm.id DESC",
    ['cid' => $courseid]
);

echo 'Quiz gradepass candidates (gradepass>0): ' . count($quizitems) . "\n";

$shown = 0;
foreach ($quizitems as $item) {
    $gp = (float)$item->gradepass;

    $attempted = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT u.id)
           FROM {user} u
           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cid
           JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :giid
          WHERE {$baseuserwhere}{$deptsql}
            AND gg.finalgrade IS NOT NULL",
        array_merge(
            ['cid' => $courseid, 'giid' => $item->gradeitemid],
            $baseuserparams,
            $deptparams
        )
    );

    $passed = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT u.id)
           FROM {user} u
           JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
           JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :cid
           JOIN {grade_grades} gg ON gg.userid = u.id AND gg.itemid = :giid
          WHERE {$baseuserwhere}{$deptsql}
            AND gg.finalgrade IS NOT NULL
            AND gg.finalgrade >= :gp",
        array_merge(
            ['cid' => $courseid, 'giid' => $item->gradeitemid, 'gp' => $gp],
            $baseuserparams,
            $deptparams
        )
    );

    echo "- sec={$item->sectionnum} cmid={$item->id} gradeitemid={$item->gradeitemid} gp={$gp} attempted={$attempted} passed={$passed} name={$item->quizname}\n";

    $shown++;
    if ($shown >= 30) {
        break;
    }
}

