# Admin dashboard changes

## 0.1.5 - 2026-05-27

- Aligned overview KPI drill-down rows with KPI card counts.
- Restricted overview Unique Learners drill-down to learners enrolled in visible courses.
- Returned per-course records for overview Attempted, Passed, Failed, and Not Attempted drill-downs.
- Updated the KPI users external service return structure to include course and enrolment detail fields.

## 0.1.4 - 2026-05-27

- Fixed the KPI users external service class to use Moodle's `core_external` API namespace.

## 0.1.3 - 2026-05-27

- Restored department readiness bars to show raw completion/pass percentages instead of coverage-adjusted scores.

## 0.1.2 - 2026-05-27

- Tightened access checks by moving the dashboard view capability to the system context and using explicit capability validation in the external service.
- Renamed plugin helper functions to the Frankenstyle `local_admindashboard_*` prefix.
- Removed public debug/runtime helper scripts and ad-hoc push-token table creation from the submitted package.
- Replaced the KPI users legacy AJAX endpoint with the registered Moodle external service call.
- Replaced direct cURL usage and excessive `error_log()` calls in the Groq sentiment helper with Moodle APIs.
- Removed manual loading of the plugin `styles.css` file.
- Added Moodle boilerplate headers across PHP files.
- Limited standard-log resource view queries by time window.

## 0.1.1 - 2026-05-26

- Added Moodle Privacy API metadata and export/delete support for the at-risk participant cache.
- Documented the Groq external service integration in Privacy API metadata and README.
- Added the missing scheduled task class for refreshing the at-risk participant cache.
- Updated release metadata for marketplace/plugin-directory submission readiness.
