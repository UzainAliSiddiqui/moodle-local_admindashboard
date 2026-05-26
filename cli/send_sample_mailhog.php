<?php
// Sends a sample email to MailHog for local testing.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params([
    'to' => '',
    'help' => false,
], [
    'h' => 'help',
]);

if (!empty($options['help'])) {
    $help = "Send a sample email to MailHog (SMTP test).\n\n"
        . "Options:\n"
        . "--to=EMAIL   Override recipient email (optional)\n"
        . "-h, --help   Print this help\n\n"
        . "Example:\n"
        . "php local/admindashboard/cli/send_sample_mailhog.php --to=teacher@example.test\n";
    cli_writeln($help);
    exit(0);
}

// Force SMTP to MailHog (independent of Site administration > Server > Outgoing mail configuration).
$CFG->smtphosts = 'mailhog:1025';
$CFG->smtpport = 1025;
$CFG->smtpsecure = '';
$CFG->smtpuser = '';
$CFG->smtppass = '';

// Pick a real Moodle user as recipient (required by email_to_user).
$admins = function_exists('get_admins') ? get_admins() : [];
$to = $admins[0] ?? null;

if (!$to) {
    $candidates = $DB->get_records_select(
        'user',
        "deleted = 0 AND suspended = 0 AND email <> ''",
        null,
        'id ASC',
        '*',
        0,
        1
    );
    $to = $candidates ? reset($candidates) : null;
}

if (!$to) {
    cli_error('No active user with an email address found to send to.');
}

$override = trim((string)($options['to'] ?? ''));
if ($override !== '') {
    $to->email = $override;
}

$from = \core_user::get_noreply_user();

$subject = 'Sample module report (MailHog test)';

// Sample data (dummy names) for previewing how the module report email will look.
$passednames = [
    'Ayesha Khan',
    'Bilal Ahmed',
    'Fatima Ali',
];
$failednames = [
    'Hassan Raza',
    'Iqra Noor',
];
$notattemptednames = [
    'Zain Malik',
    'Sana Tariq',
];

$participants = 20;
$attempted = 18;
$passed = 10;
$failed = 8;
$notattempted = max(0, $participants - $attempted);

$fmtlist = function(array $names): string {
    if (empty($names)) {
        return "-";
    }
    return "- " . implode("\n- ", $names);
};

$text = "Hello,\n\n"
    . "This is a sample module report email for MailHog testing.\n\n"
    . "Course: Demo Course\n"
    . "Module: 1\n"
    . "Participants: {$participants}\n"
    . "Attempted: {$attempted}\n"
    . "Passed: {$passed}\n"
    . "Failed: {$failed}\n"
    . "Not attempted: {$notattempted}\n\n"
    . "Passed students:\n" . $fmtlist($passednames) . "\n\n"
    . "Failed students:\n" . $fmtlist($failednames) . "\n\n"
    . "Not attempted students:\n" . $fmtlist($notattemptednames) . "\n\n"
    . "Regards,\nMoodle\n";

$htmllist = function(array $names): string {
    if (empty($names)) {
        return '<div>-</div>';
    }
    $items = '';
    foreach ($names as $n) {
        $items .= '<li>' . s($n) . '</li>';
    }
    return '<ul style="margin:0 0 0 18px">' . $items . '</ul>';
};

$html = '<p>Hello,</p>'
    . '<p>This is a <strong>sample module report</strong> email for MailHog testing.</p>'
    . '<table border="1" cellpadding="6" cellspacing="0">'
    . '<tr><th align="left">Course</th><td>Demo Course</td></tr>'
    . '<tr><th align="left">Module</th><td>1</td></tr>'
    . '<tr><th align="left">Participants</th><td>' . $participants . '</td></tr>'
    . '<tr><th align="left">Attempted</th><td>' . $attempted . '</td></tr>'
    . '<tr><th align="left">Passed</th><td>' . $passed . '</td></tr>'
    . '<tr><th align="left">Failed</th><td>' . $failed . '</td></tr>'
    . '<tr><th align="left">Not attempted</th><td>' . $notattempted . '</td></tr>'
    . '</table>'
    . '<h4 style="margin:16px 0 6px 0">Passed students</h4>'
    . $htmllist($passednames)
    . '<h4 style="margin:16px 0 6px 0">Failed students</h4>'
    . $htmllist($failednames)
    . '<h4 style="margin:16px 0 6px 0">Not attempted students</h4>'
    . $htmllist($notattemptednames)
    . '<p>Regards,<br>Moodle</p>';

$ok = email_to_user($to, $from, $subject, $text, $html);

if ($ok) {
    cli_writeln('OK: sent sample email to MailHog as ' . $to->email);
    exit(0);
}

cli_error('FAILED: email_to_user() returned false.');
