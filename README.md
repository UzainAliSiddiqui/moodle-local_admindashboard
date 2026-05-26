# Admin dashboard

Admin dashboard is a Moodle local plugin that provides a full-window dashboard for staff users who need operational analytics, reporting, course progress views, reminders, and compliance signals in one place.

## Features

- Dashboard analytics for courses, learners, departments, completion, engagement, compliance, certificates, and pass/fail progress.
- Staff-facing navigation for managers, course creators, teachers, and editing teachers.
- Course schedule sticky notes with a dedicated edit capability.
- Scheduled module pass/fail reports for teachers before a new module starts.
- Cached at-risk participant analytics for dashboard warning lists.
- Optional Groq-backed Ask the Data and feedback sentiment analysis features.

## Requirements

- Moodle 4.0 or later.
- PHP and database versions supported by the target Moodle release.
- A Groq API key only if Groq-backed assistant or sentiment analysis features are enabled.

## Installation

1. Copy the plugin folder to `local/admindashboard`.
2. Log in as a site administrator.
3. Visit **Site administration > Notifications** to complete installation.
4. Purge Moodle caches if the navigation link does not appear immediately.

The upload ZIP for Moodle must contain a single folder named `admindashboard` with `version.php` directly inside it.

## Permissions

Access is controlled by `local/admindashboard:view`.

By default, the capability is allowed for:

- Manager
- Course creator
- Teacher
- Editing teacher

Course schedule note editing is controlled separately by `local/admindashboard:editcourseschedulenotes`, which is granted to managers by default.

## Configuration

Settings are available under **Site administration > Plugins > Local plugins > Admin dashboard**.

Groq-backed features require:

- Groq API key
- Groq model
- Groq endpoint

If no Groq API key is configured, the dashboard still works, but Groq-backed assistant and sentiment analysis responses are unavailable.

## Privacy

The plugin processes Moodle user profile, enrolment, completion, grade, activity, certificate, and feedback data to display dashboard analytics to authorised staff.

The plugin stores a cached at-risk participant table containing course ID, user ID, risk scores, progress percentages, deadline timestamps, and JSON-encoded risk reasons. This cache is covered by the plugin Privacy API provider.

When Groq-backed features are enabled, selected prompts, dashboard context, and feedback comments may be sent to the configured Groq endpoint for analysis. Site administrators should ensure their Groq use complies with their organisation's privacy policy and data-processing requirements.

## Scheduled Tasks

- `\local_admindashboard\task\send_module_reports`
- `\local_admindashboard\task\refresh_at_risk_cache`

## Support

Report bugs and feature requests through the plugin's public issue tracker. Include your Moodle version, database type, PHP version, debugging output, and the page or scheduled task where the issue occurs.
