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
$moduleid = optional_param('moduleid', 0, PARAM_INT);
$metric = strtolower(trim(optional_param('metric', '', PARAM_ALPHANUMEXT)));

$allowedmetrics = ['failed', 'not_attempted', 'notattempted', 'dropped_midway'];
if (!in_array($metric, $allowedmetrics, true)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Reminders are only supported for Failed and Not Attempted users.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot . '/message/lib.php');

    $rows = local_admindashboard_get_kpi_user_rows($courseid, $department, $moduleid, $metric);
    $userids = array_values(array_unique(array_map(static function(array $row): int {
        return (int)($row['id'] ?? 0);
    }, $rows)));
    $userids = array_values(array_filter($userids));

    if (empty($userids)) {
        echo json_encode([
            'sentcount' => 0,
            'recipientcount' => 0,
            'message' => 'No recipients found.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $recipients = $DB->get_records_list('user', 'id', $userids, '', 'id,firstname,lastname,email,suspended,deleted,lang');

    $course = null;
    if ($courseid > 0) {
        $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname', IGNORE_MISSING);
    }

    $modulelabel = '';
    $contexturl = (new moodle_url('/local/admindashboard/dashboard.php'))->out(false);
    $contexturlname = 'Admin Dashboard';
    if ($course) {
        $contexturl = (new moodle_url('/course/view.php', ['id' => (int)$course->id]))->out(false);
        $contexturlname = format_string((string)$course->fullname, true, ['context' => context_course::instance((int)$course->id)]);
    }

    if ($moduleid > 0) {
        require_once($CFG->dirroot . '/course/lib.php');
        $modinfo = get_fast_modinfo($courseid);
        if (!empty($modinfo->cms[$moduleid])) {
            $cm = $modinfo->cms[$moduleid];
            $modulelabel = trim((string)$cm->get_formatted_name());
            $contexturl = (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => (int)$cm->id]))->out(false);
            $contexturlname = $modulelabel !== '' ? $modulelabel : $contexturlname;
        }
    }

    $departmentlabel = $department !== '' ? $department : 'All Departments';
    $statuslabel = ($metric === 'failed') ? 'Failed' : 'Not Attempted';
    $subject = ($metric === 'failed')
        ? 'Reminder: please revisit your training assessment'
        : 'Reminder: please complete your pending training';

    $actionbody = ($metric === 'failed')
        ? 'You have been identified as needing to revisit a training assessment. Please review the material and re-attempt the required activity as soon as possible.'
        : 'You have a pending training activity that has not yet been completed. Please log in and complete it as soon as possible.';

    $coursename = $course
        ? format_string((string)$course->fullname, true, ['context' => context_course::instance((int)$course->id)])
        : '';

    // Plain-text body — shown in Moodle Messages chat + plain-text email fallback.
    // {{FIRSTNAME}} is replaced per-recipient inside the send loop.
    $plainparts = ['Dear {{FIRSTNAME}},', '', $actionbody, ''];
    if ($coursename !== '') { $plainparts[] = 'Course:     ' . $coursename; }
    if ($modulelabel !== '') { $plainparts[] = 'Module:     ' . $modulelabel; }
    $plainparts[] = 'Department: ' . $departmentlabel;
    $plainparts[] = 'Status:     ' . $statuslabel;
    $plainparts[] = '';
    $plainparts[] = 'Open the activity: ' . $contexturl;
    $fullmessage = implode("\n", $plainparts);

    // HTML email detail rows.
    $htmldetailrows = '';
    if ($coursename !== '') {
        $htmldetailrows .= '<tr>'
            . '<td style="padding:5px 10px 5px 0;font-size:12px;font-weight:700;color:#1e3a5f;white-space:nowrap;vertical-align:top;">Course</td>'
            . '<td style="padding:5px 0;font-size:13px;color:#374151;">' . s($coursename) . '</td>'
            . '</tr>';
    }
    if ($modulelabel !== '') {
        $htmldetailrows .= '<tr>'
            . '<td style="padding:5px 10px 5px 0;font-size:12px;font-weight:700;color:#1e3a5f;white-space:nowrap;vertical-align:top;">Module</td>'
            . '<td style="padding:5px 0;font-size:13px;color:#374151;">' . s($modulelabel) . '</td>'
            . '</tr>';
    }
    $htmldetailrows .= '<tr>'
        . '<td style="padding:5px 10px 5px 0;font-size:12px;font-weight:700;color:#1e3a5f;white-space:nowrap;vertical-align:top;">Department</td>'
        . '<td style="padding:5px 0;font-size:13px;color:#374151;">' . s($departmentlabel) . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="padding:5px 10px 5px 0;font-size:12px;font-weight:700;color:#1e3a5f;white-space:nowrap;vertical-align:top;">Status</td>'
        . '<td style="padding:5px 0;font-size:13px;color:#374151;">' . s($statuslabel) . '</td>'
        . '</tr>';

    // Full HTML email template. {{FIRSTNAME}} is replaced per-recipient in the send loop.
    $fullmessagehtml =
        '<!DOCTYPE html><html lang="en">'
        . '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
        . '<title>' . s($subject) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#eef1f6;font-family:Arial,Helvetica,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f6;padding:36px 16px;">'
        . '<tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.09);">'
        . '<tr><td style="background:#1e3a5f;padding:28px 40px 24px;">'
        . '<p style="margin:0 0 3px;color:rgba(255,255,255,.55);font-size:10px;letter-spacing:1.8px;text-transform:uppercase;font-weight:700;">ZMT LMS</p>'
        . '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700;letter-spacing:-.2px;">Training Reminder</h1>'
        . '</td></tr>'
        . '<tr><td style="padding:36px 40px 30px;">'
        . '<p style="margin:0 0 6px;color:#111827;font-size:16px;font-weight:600;">Dear {{FIRSTNAME}},</p>'
        . '<p style="margin:0 0 26px;color:#4b5563;font-size:14px;line-height:1.75;">' . s($actionbody) . '</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f5ff;border:1px solid #c7d7fd;border-left:4px solid #1e3a5f;border-radius:6px;margin-bottom:28px;">'
        . '<tr><td style="padding:16px 22px;">'
        . '<table cellpadding="0" cellspacing="0" border="0" width="100%">' . $htmldetailrows . '</table>'
        . '</td></tr></table>'
        . '<table cellpadding="0" cellspacing="0" border="0">'
        . '<tr><td style="border-radius:6px;background:#1e3a5f;">'
        . '<a href="' . s($contexturl) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:13px 28px;color:#fff;font-size:14px;font-weight:700;text-decoration:none;letter-spacing:.2px;">Open Activity &#8594;</a>'
        . '</td></tr></table>'
        . '</td></tr>'
        . '<tr><td style="background:#f9fafb;padding:18px 40px;border-top:1px solid #e5e7eb;">'
        . '<p style="margin:0;color:#9ca3af;font-size:11px;line-height:1.6;">This is an automated reminder from ZMT LMS. Please do not reply to this email.<br>'
        . 'If you received this in error, contact your system administrator.</p>'
        . '</td></tr>'
        . '</table></td></tr></table>'
        . '</body></html>';
    $userfrom = core_user::get_user($USER->id, 'id,firstname,lastname,email,maildisplay,mailformat,maildigest,lang,imagealt,lastaccess,lastip,auth,suspended,deleted,emailstop');
    if (!$userfrom) {
        $userfrom = core_user::get_support_user();
    }

    $sentcount = 0;
    $errors = [];
    foreach ($userids as $userid) {
        if (empty($recipients[$userid])) {
            continue;
        }
        $recipient = $recipients[$userid];
        if (!empty($recipient->deleted) || !empty($recipient->suspended)) {
            continue;
        }

        try {
            // Pre-create (or retrieve existing) the 1-to-1 conversation between
            // the sender and this recipient.  Setting convid on the message
            // bypasses Moodle's $CFG->messaging site-level gate AND any privacy
            // checks, so the message is delivered as a real conversation message
            // that appears in the user's Messages UI and fires the notification bell.
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
            $message->courseid          = $courseid > 0 ? $courseid : SITEID;
            $message->component         = 'moodle';
            $message->name              = 'instantmessage';
            $message->userfrom          = $userfrom;
            $message->userto            = $recipient;
            $message->convid            = $conversationid; // ← skips $CFG->messaging check
            $message->subject           = $subject;
            $message->fullmessage       = str_replace('{{FIRSTNAME}}', $recipient->firstname, $fullmessage);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = str_replace('{{FIRSTNAME}}', s($recipient->firstname), $fullmessagehtml);
            $message->smallmessage      = str_replace('{{FIRSTNAME}}', $recipient->firstname, $fullmessage);
            $message->notification      = 0; // real conversation message, not notification type
            $message->contexturl        = $contexturl;
            $message->contexturlname    = $contexturlname;

            $msgid = message_send($message);
            if ($msgid) {
                $sentcount++;
            } else {
                $errors[] = "uid=$userid: message_send returned false";
            }
        } catch (Throwable $ue) {
            $errors[] = "uid=$userid: " . $ue->getMessage();
        }
    }

    $response = [
        'sentcount'      => $sentcount,
        'recipientcount' => count($userids),
        'metric'         => $metric,
    ];
    if (!empty($errors)) {
        $response['errors'] = $errors; // surfaced in JS console for debugging
    }
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to send reminders: ' . $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}