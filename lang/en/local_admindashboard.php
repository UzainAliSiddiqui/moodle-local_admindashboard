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

$string['pluginname'] = 'Admin dashboard';
$string['admindashboard:view'] = 'View admin dashboard';
$string['admindashboard:editcourseschedulenotes'] = 'Edit course schedule sticky notes on the admin dashboard';
$string['settingsheading'] = 'Admin dashboard settings';

$string['theme_day_mode'] = 'Day mode';
$string['theme_dark_mode'] = 'Dark mode';
$string['sticky_schedule_show'] = 'Show';
$string['sticky_schedule_hide'] = 'Hide';
$string['table_search_placeholder'] = 'Search...';
$string['table_export_excel'] = 'Export Excel';
$string['table_export_pdf'] = 'Export PDF';
$string['table_report_title'] = 'Report';

$string['nav_heading_admintools'] = 'Admin tools';
$string['nav_heading_reportsanalytics'] = 'Reports and analytics';
$string['nav_heading_communication'] = 'Communication';
$string['nav_heading_platformsettings'] = 'Platform settings';
$string['nav_heading_supportaccount'] = 'Support and account';
$string['nav_admintools_users'] = 'Manage users';
$string['nav_admintools_users_list'] = 'Users list';
$string['nav_admintools_users_add'] = 'Add user';
$string['nav_admintools_users_roles'] = 'Define roles';
$string['nav_admintools_courses'] = 'Manage courses';
$string['nav_admintools_courses_list'] = 'Course list';
$string['nav_admintools_courses_create'] = 'Create new course';
$string['nav_admintools_courses_templates'] = 'Course templates';
$string['nav_admintools_groups'] = 'Group and department setup';
$string['nav_courseanalytics'] = 'Course analytics';
$string['nav_courseanalytics_overview'] = 'Course analytics';
$string['nav_courseanalytics_modules'] = 'Modules report';
$string['nav_courseanalytics_sentiment'] = 'Sentiment analyzer';
$string['nav_reports'] = 'Reports';
$string['nav_reports_passfail'] = 'Pass/fail report';
$string['nav_reports_ticks'] = 'Progress ticks report';
$string['nav_reports_departmentcompletion'] = 'Department completion report';
$string['nav_reports_departmentengagement'] = 'Department engagement report';
$string['nav_reports_userprogress'] = 'User progress report';
$string['nav_reports_useractivity'] = 'Recent activity report';
$string['nav_compliance'] = 'Compliance and expiry tracking';
$string['nav_compliance_expiry'] = 'License expiry';
$string['nav_compliance_mandatory'] = 'Mandatory training';
$string['nav_compliance_dashboard'] = 'Compliance dashboard';
$string['nav_skills'] = 'Skill gap and certifications';
$string['nav_skills_gap'] = 'Skill gap matrix';
$string['nav_skills_certificates'] = 'Certificate status';
$string['nav_skills_renewals'] = 'Renewal readiness';
$string['nav_exportcenter'] = 'Export center';
$string['nav_communication_announcements'] = 'Announcements';
$string['nav_communication_discussions'] = 'Forums and discussions';
$string['nav_communication_messaging'] = 'Direct messaging';
$string['nav_platform_integrations'] = 'Integrations';
$string['nav_platform_config'] = 'System config';
$string['nav_platform_branding'] = 'Platform branding';
$string['nav_platform_schedule_notes'] = 'Course schedule notes';
$string['nav_support_tickets'] = 'Support tickets';
$string['nav_support_help'] = 'Help center';
$string['nav_support_profile'] = 'My profile';
$string['nav_support_settings'] = 'Settings';

$string['page_dashboard'] = 'Admin dashboard';
$string['page_admintools_users_list'] = 'Manage users';
$string['page_admintools_users_add'] = 'Add user';
$string['page_admintools_users_roles'] = 'Define roles';
$string['page_admintools_courses_list'] = 'Course list';
$string['page_admintools_courses_create'] = 'Create new course';
$string['page_admintools_courses_templates'] = 'Course templates';
$string['page_admintools_groups'] = 'Group and department setup';
$string['page_courseanalytics_overview'] = 'Course analytics';
$string['page_courseanalytics_modules'] = 'Course analytics';
$string['page_courseanalytics_sentiment'] = 'Sentiment analyzer';
$string['page_reports_passfail'] = 'Pass/fail report';
$string['page_reports_ticks'] = 'Progress ticks report';
$string['page_reports_departmentcompletion'] = 'Department completion report';
$string['page_reports_departmentengagement'] = 'Department engagement report';
$string['page_reports_userprogress'] = 'User progress report';
$string['page_reports_useractivity'] = 'Recent activity report';
$string['page_compliance_expiry'] = 'License expiry';
$string['page_compliance_mandatory'] = 'Mandatory training';
$string['page_compliance_dashboard'] = 'Compliance dashboard';
$string['page_skills_gap'] = 'Skill gap matrix';
$string['page_skills_certificates'] = 'Certificate status';
$string['page_skills_renewals'] = 'Renewal readiness';
$string['page_exportcenter'] = 'Export center';
$string['page_communication_announcements'] = 'Announcements';
$string['page_communication_discussions'] = 'Forums and discussions';
$string['page_communication_messaging'] = 'Direct messaging';
$string['page_platform_integrations'] = 'Integrations';
$string['page_platform_config'] = 'System config';
$string['page_platform_branding'] = 'Platform branding';
$string['page_platform_schedule_notes'] = 'Course schedule notes';
$string['page_support_tickets'] = 'Support tickets';
$string['page_support_help'] = 'Help center';
$string['page_support_profile'] = 'My profile';
$string['page_support_settings'] = 'Settings';

$string['groqapikey'] = 'Groq API key';
$string['groqapikey_desc'] = 'API key used by the Ask the Data assistant.';
$string['groqmodel'] = 'Groq model';
$string['groqmodel_desc'] = 'Model name for Ask the Data requests.';
$string['groqendpoint'] = 'Groq API endpoint';
$string['groqendpoint_desc'] = 'OpenAI-compatible Groq chat completions endpoint.';

$string['courseschedulenotes_pagetitle'] = 'Course schedule notes';
$string['courseschedulenotes_intro'] = 'Edit the sticky notes shown on the main admin dashboard (course cadence, intakes, and reminders). You can save up to {$a} notes.';
$string['courseschedulenotes_nocap'] = 'You can view the schedule below. Only users with the edit permission (typically site managers) can change these notes.';
$string['courseschedulenotes_backdash'] = 'Back to dashboard';
$string['courseschedulenotes_formtitle'] = 'Sticky note content';
$string['courseschedulenotes_formhelp'] = 'Plain text only; use line breaks for paragraphs. Colours are cosmetic on the dashboard. Rows with both title and body empty are skipped. One extra blank row appears so you can add another note (up to {$a} total).';
$string['courseschedulenotes_untitled'] = 'Untitled note';
$string['courseschedulenotes_maxreached'] = 'You are at the maximum of {$a} notes. Save, then remove or shorten an existing note to add a different one.';
$string['courseschedulenotes_notelabel'] = 'Note {$a}';
$string['courseschedulenotes_field_title'] = 'Title';
$string['courseschedulenotes_field_body'] = 'Body';
$string['courseschedulenotes_field_variant'] = 'Card colour';
$string['courseschedulenotes_variant_lemon'] = 'Lemon';
$string['courseschedulenotes_variant_mint'] = 'Mint';
$string['courseschedulenotes_variant_lavender'] = 'Lavender';
$string['courseschedulenotes_variant_peach'] = 'Peach';
$string['courseschedulenotes_preview'] = 'Live preview';
$string['courseschedulenotes_board_eyebrow'] = 'Course cadence';
$string['courseschedulenotes_board_title'] = 'Schedule at a glance';
$string['courseschedulenotes_board_edit'] = 'Edit notes';
$string['courseschedulenotes_board_toggle_hide'] = 'Hide schedule notes';
$string['courseschedulenotes_board_toggle_show'] = 'Show schedule notes';

$string['task_send_module_reports'] = 'Send module reports to teachers';
$string['task_refresh_at_risk_cache'] = 'Refresh at-risk participant cache';
$string['email_modulereport_title'] = 'Module Pass/Fail Report';
$string['email_modulereport_subject'] = 'Module report: {$a->course} - {$a->module}';

$string['privacy:export:atrisk'] = 'At-risk participant cache';
$string['privacy:metadata:groq'] = 'The plugin can send dashboard prompts and selected dashboard data to Groq when Groq-backed features are enabled.';
$string['privacy:metadata:groq:dashboarddata'] = 'Aggregated dashboard metrics and selected contextual data used to answer dashboard questions.';
$string['privacy:metadata:groq:feedbackcomments'] = 'Feedback comments submitted in Moodle activities when sentiment analysis is requested.';
$string['privacy:metadata:groq:prompt'] = 'The question or analysis prompt entered by the user or generated by the dashboard.';
$string['privacy:metadata:local_admindashboard_atrisk'] = 'Cached at-risk participant analytics used to render dashboard warning lists efficiently.';
$string['privacy:metadata:local_admindashboard_atrisk:completionpct'] = 'The participant completion percentage calculated for the course.';
$string['privacy:metadata:local_admindashboard_atrisk:courseid'] = 'The course associated with the cached analytics row.';
$string['privacy:metadata:local_admindashboard_atrisk:dayssincelogin'] = 'The number of days since the participant last accessed Moodle.';
$string['privacy:metadata:local_admindashboard_atrisk:deadlineat'] = 'The relevant course deadline timestamp used by the risk calculation.';
$string['privacy:metadata:local_admindashboard_atrisk:loginrisk'] = 'Whether the participant is considered at risk because of login activity.';
$string['privacy:metadata:local_admindashboard_atrisk:pacingrisk'] = 'Whether the participant is considered at risk because of course pacing.';
$string['privacy:metadata:local_admindashboard_atrisk:pretestavgpct'] = 'The comparison pre-test average percentage used by the risk calculation.';
$string['privacy:metadata:local_admindashboard_atrisk:pretestpct'] = 'The participant pre-test percentage used by the risk calculation.';
$string['privacy:metadata:local_admindashboard_atrisk:pretestrisk'] = 'Whether the participant is considered at risk because of pre-test performance.';
$string['privacy:metadata:local_admindashboard_atrisk:reasonsjson'] = 'JSON-encoded reasons explaining why the participant appears in the at-risk list.';
$string['privacy:metadata:local_admindashboard_atrisk:riskscore'] = 'The calculated at-risk score for the participant.';
$string['privacy:metadata:local_admindashboard_atrisk:timecreated'] = 'The time the cached analytics row was created.';
$string['privacy:metadata:local_admindashboard_atrisk:timemodified'] = 'The time the cached analytics row was last updated.';
$string['privacy:metadata:local_admindashboard_atrisk:userid'] = 'The participant represented by the cached analytics row.';
