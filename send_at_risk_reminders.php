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
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/metricslib.php');

require_login();
require_sesskey();
local_admindashboard_require_view_access();
$PAGE->set_context(context_system::instance());

header('Content-Type: application/json; charset=utf-8');

$courseid = optional_param('courseid', 0, PARAM_INT);
$department = trim(optional_param('department', '', PARAM_TEXT));
$useridsraw = trim(optional_param('userids', '', PARAM_SEQUENCE));

if ($courseid <= 0 || !local_admindashboard_is_course_running($courseid)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'At-risk reminders are only available for a currently running course.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$requesteduserids = array_values(array_unique(array_map('intval', preg_split('/\s*,\s*/', $useridsraw, -1, PREG_SPLIT_NO_EMPTY) ?: [])));
$requesteduserids = array_values(array_filter($requesteduserids));
if (empty($requesteduserids)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'No at-risk participants were selected.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot . '/message/lib.php');

    $atriskrows = local_admindashboard_get_at_risk_participants($courseid, $department, 0);
    $eligible = [];
    foreach ($atriskrows as $row) {
        $userid = (int)($row['userid'] ?? 0);
        if ($userid > 0 && in_array($userid, $requesteduserids, true)) {
            $eligible[$userid] = $row;
        }
    }

    if (empty($eligible)) {
        echo json_encode([
            'sentcount' => 0,
            'recipientcount' => 0,
            'message' => 'No matching at-risk participants found.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $recipients = $DB->get_records_list('user', 'id', array_keys($eligible), '', 'id,firstname,lastname,email,suspended,deleted,lang');
    $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname', MUST_EXIST);
    $contexturl = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
    $contexturlname = format_string((string)$course->fullname, true, ['context' => context_course::instance($courseid)]);
    $departmentlabel = $department !== '' ? $department : 'All Departments';

    $userfrom = core_user::get_user($USER->id, 'id,firstname,lastname,email,maildisplay,mailformat,maildigest,lang,imagealt,lastaccess,lastip,auth,suspended,deleted,emailstop');
    if (!$userfrom) {
        $userfrom = core_user::get_support_user();
    }

    $sentcount = 0;
    foreach ($eligible as $userid => $riskrow) {
        if (empty($recipients[$userid])) {
            continue;
        }
        $recipient = $recipients[$userid];
        if (!empty($recipient->deleted) || !empty($recipient->suspended)) {
            continue;
        }

        $riskscore = (int)($riskrow['risk_score'] ?? 0);
        $reasons   = (array)($riskrow['reasons'] ?? []);

        // Plain-text body — shown in Moodle Messages chat + plain-text email fallback.
        $plainlines = [
            'Dear ' . $recipient->firstname . ',',
            '',
            'You have been flagged for proactive follow-up by your training administrator.',
            '',
            'Course:     ' . $contexturlname,
            'Department: ' . $departmentlabel,
            'Risk score: ' . $riskscore . ' / 3',
        ];
        foreach ($reasons as $reason) {
            $plainlines[] = '  - ' . $reason;
        }
        $plainlines[] = '';
        $plainlines[] = 'Please log in and continue your course progress as soon as possible.';
        $plainlines[] = 'Open the course: ' . $contexturl;
        $fullmessage = implode("\n", $plainlines);

        // HTML email template.
        $htmlreasons = '';
        foreach ($reasons as $reason) {
            $htmlreasons .= '<li style="margin:0 0 6px;font-size:13px;color:#374151;">' . s($reason) . '</li>';
        }
        $fullmessagehtml =
            '<!DOCTYPE html><html lang="en">'
            . '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . '<title>Reminder: immediate action needed on your course</title></head>'
            . '<body style="margin:0;padding:0;background:#eef1f6;font-family:Arial,Helvetica,sans-serif;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f6;padding:36px 16px;">'
            . '<tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.09);">'
            . '<tr><td style="background:#7f1d1d;padding:28px 40px 24px;">'
            . '<p style="margin:0 0 3px;color:rgba(255,255,255,.55);font-size:10px;letter-spacing:1.8px;text-transform:uppercase;font-weight:700;">ZMT LMS</p>'
            . '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700;letter-spacing:-.2px;">Action Required</h1>'
            . '</td></tr>'
            . '<tr><td style="padding:36px 40px 30px;">'
            . '<p style="margin:0 0 6px;color:#111827;font-size:16px;font-weight:600;">Dear ' . s($recipient->firstname) . ',</p>'
            . '<p style="margin:0 0 26px;color:#4b5563;font-size:14px;line-height:1.75;">You have been flagged for proactive follow-up by your training administrator. Please review the details below and take action immediately.</p>'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff5f5;border:1px solid #fecaca;border-left:4px solid #dc2626;border-radius:6px;margin-bottom:20px;">'
            . '<tr><td style="padding:16px 22px;">'
            . '<table cellpadding="0" cellspacing="0" border="0" width="100%">'
            . '<tr><td style="padding:5px 10px 5px 0;font-size:12px;font-weight:700;color:#7f1d1d;white-space:nowrap;vertical-align:top;">Course</td>'
            . '<td style="padding:5px 0;font-size:13px;color:#374151;">' . s($contexturlname) . '</td></tr>'
            . '<tr><td style="padding:5px 10px 5px 0;font-size:12px;font-weight:700;color:#7f1d1d;white-space:nowrap;vertical-align:top;">Department</td>'
            . '<td style="padding:5px 0;font-size:13px;color:#374151;">' . s($departmentlabel) . '</td></tr>'
            . '<tr><td style="padding:5px 10px 5px 0;font-size:12px;font-weight:700;color:#7f1d1d;white-space:nowrap;vertical-align:top;">Risk Score</td>'
            . '<td style="padding:5px 0;font-size:13px;font-weight:700;color:#374151;">' . $riskscore . ' / 3</td></tr>'
            . '</table></td></tr></table>'
            . ($htmlreasons !== ''
                ? '<p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#374151;">Risk factors:</p><ul style="margin:0 0 24px;padding-left:20px;">' . $htmlreasons . '</ul>'
                : '<p style="margin:0 0 24px;font-size:13px;color:#9ca3af;font-style:italic;">No specific risk factors listed.</p>')
            . '<table cellpadding="0" cellspacing="0" border="0">'
            . '<tr><td style="border-radius:6px;background:#7f1d1d;">'
            . '<a href="' . s($contexturl) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:13px 28px;color:#fff;font-size:14px;font-weight:700;text-decoration:none;">Open Course &#8594;</a>'
            . '</td></tr></table>'
            . '</td></tr>'
            . '<tr><td style="background:#f9fafb;padding:18px 40px;border-top:1px solid #e5e7eb;">'
            . '<p style="margin:0;color:#9ca3af;font-size:11px;line-height:1.6;">This is an automated reminder from ZMT LMS. Please do not reply to this email.<br>'
            . 'If you received this in error, contact your system administrator.</p>'
            . '</td></tr>'
            . '</table></td></tr></table>'
            . '</body></html>';

        try {
            // Pre-create (or retrieve existing) the 1-to-1 conversation.
            // Setting convid bypasses $CFG->messaging site gate and privacy checks.
            $conversationid = \core_message\api::get_conversation_between_users(
                [$userfrom->id, $userid]
            );
            if (!$conversationid) {
                $conv = \core_message\api::create_conversation(
                    \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL,
                    [$userfrom->id, $userid]
                );
                $conversationid = (int)$conv->id;
            }

            $message = new core\message\message();
            $message->courseid          = $courseid;
            $message->component         = 'moodle';
            $message->name              = 'instantmessage';
            $message->userfrom          = $userfrom;
            $message->userto            = $recipient;
            $message->convid            = $conversationid;
            $message->subject           = 'Reminder: immediate action needed on your course';
            $message->fullmessage       = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = $fullmessagehtml;
            $message->smallmessage      = $fullmessage;
            $message->notification      = 0;
            $message->contexturl        = $contexturl;
            $message->contexturlname    = $contexturlname;

            if (message_send($message)) {
                $sentcount++;
            }
        } catch (Throwable $ue) {
            // skip this user, continue with others
        }
    }

    echo json_encode([
        'sentcount'      => $sentcount,
        'recipientcount' => count($eligible),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to send at-risk reminders: ' . $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
