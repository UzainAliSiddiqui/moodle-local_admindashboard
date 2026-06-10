# Admin Dashboard Privacy Information

Effective date: 10 June 2026

This document explains how the Admin Dashboard Moodle plugin
(`local_admindashboard`) processes information. It is intended to help Moodle
site administrators prepare their own privacy notices and configure the
plugin appropriately.

The organisation operating the Moodle site normally determines why and how
personal data is processed and is therefore the data controller (or
equivalent role under applicable law). Uzain Ali Siddiqui, the plugin
developer, does not automatically receive data from installations of the
plugin.

## 1. Information read from Moodle

To provide authorised dashboard views, reports, reminders, and analytics, the
plugin may read information already held in the Moodle site, including:

- User identifiers, names, email addresses, profile fields, departments, and
  account status.
- Course, category, group, cohort, role, and enrolment information.
- Course and activity access, participation, completion, and progress data.
- Grades, attempts, pass/fail results, and certificate information where
  supported certificate plugins are installed.
- Feedback comments and activity data used for engagement or sentiment
  analysis.
- Dates and timestamps associated with enrolment, access, completion,
  deadlines, and reporting.

Most of this information remains in Moodle's existing tables and is displayed
or aggregated when an authorised user opens a report.

## 2. Information stored by the plugin

The plugin stores a cache of at-risk participant analytics in the
`local_admindashboard_atrisk` database table. A cached record may contain:

- Moodle course ID and user ID.
- Overall risk score and login, pacing, and pre-test risk indicators.
- Days since login and course completion percentage.
- Relevant deadline timestamp.
- Pre-test percentages and comparison averages.
- JSON-encoded reasons used to explain the calculated risk.
- Record creation and modification timestamps.

The plugin also stores operational records identifying module reports that
have already been sent. Plugin configuration may include a Groq API key,
model name, and endpoint. The API key is a site-level administrative setting
and should be treated as a secret.

## 3. Purpose and lawful basis

The plugin processes information to:

- Present course, learner, completion, engagement, compliance, certificate,
  and pass/fail dashboards to authorised staff.
- Identify participants who may require learning or administrative support.
- Produce reports, exports, reminders, and operational status views.
- Cache calculated risk results so network-wide analytics can be delivered
  efficiently.
- Provide optional AI-assisted data questions and feedback sentiment analysis.

The Moodle site operator is responsible for selecting an appropriate lawful
basis, providing required notices, and ensuring that access and use comply
with local law and organisational policy.

## 4. Access and permissions

Dashboard access is controlled by Moodle authentication, roles, contexts, and
the `local/admindashboard:view` capability. Course schedule note editing uses
the separate `local/admindashboard:editcourseschedulenotes` capability.

Site administrators must assign capabilities only to staff who have a
legitimate need to view the relevant information. Moodle administrators and
database administrators may also be able to access stored data as part of
their normal system responsibilities.

## 5. Optional Groq data transfer

The Groq integration is optional and is inactive unless a site administrator
configures an API key. When enabled, the plugin may send the following to the
configured Groq-compatible endpoint:

- Prompts entered into Ask the Data.
- Selected or aggregated dashboard context needed to answer a prompt.
- Feedback comments submitted for sentiment analysis.

Depending on the feature and selected context, this information may contain
personal data or confidential institutional information. The site operator
must review the configured provider's terms, privacy policy, security
controls, data locations, retention practices, and legal suitability before
enabling the integration.

Administrators should minimise the data sent, avoid entering unnecessary
personal or special-category data into prompts, protect the API key, and use
an approved endpoint. Third-party API usage may incur separate charges.

## 6. Sharing with the plugin developer

The plugin does not contain telemetry that automatically sends Moodle data to
the developer.

If a customer contacts the developer for support, the developer receives only
the information the customer chooses to provide. Customers must remove or
anonymise personal data, credentials, API keys, database dumps, and other
sensitive information before posting to the public issue tracker or sending
diagnostic material.

Purchases through Moodle Marketplace are also subject to the Marketplace's
privacy notice and the privacy practices of its payment providers. The
developer does not directly store complete payment-card information.

## 7. Retention and deletion

The at-risk cache is refreshed by a Moodle scheduled task. Records for a
course are replaced when that course's cache is rebuilt. The exact retention
period therefore depends on the site's task schedule, course lifecycle, and
administrative practices.

The plugin implements Moodle's Privacy API for the at-risk cache. This allows
the site privacy workflow to:

- Identify course contexts containing cached data for a user.
- Export that user's cached at-risk records.
- Delete a user's cached records in approved course contexts.
- Delete all cached plugin records for a course context.

Uninstalling the plugin through Moodle's standard plugin management process
removes plugin-owned database tables, subject to Moodle's behaviour and the
site's backup arrangements. Site backups, logs, and exports may retain copies
according to the site operator's retention policy.

The site operator should configure scheduled tasks, retention periods,
backups, and privacy requests in accordance with its own legal and
organisational requirements.

## 8. Security

The plugin uses Moodle authentication and capability controls for protected
functionality. Site operators remain responsible for:

- Maintaining supported Moodle, PHP, database, web server, and plugin
  versions.
- Applying security updates promptly.
- Using HTTPS and appropriate server, database, session, and backup controls.
- Restricting administrative capabilities and protecting API credentials.
- Reviewing logs and testing changes before production deployment.

No system can guarantee absolute security.

## 9. Data subject rights

Individuals may have rights to access, correct, export, restrict, object to,
or delete their personal data. Requests should normally be directed to the
organisation operating the Moodle site, because that organisation controls
the installation and its data.

Moodle's privacy tools and this plugin's Privacy API implementation can assist
the site operator with applicable requests.

## 10. Children's data

The plugin does not independently determine the age of Moodle users. If a
site is used by children or other protected groups, the site operator is
responsible for obtaining any required consent or authorisation and applying
appropriate access, transparency, and safeguarding measures.

## 11. Changes to this information

This document may be updated when plugin functionality or legal requirements
change. Site operators should review the version supplied with each plugin
release and update their own notices where necessary.

## 12. Contact

Questions about data in a particular Moodle site should first be sent to that
site's administrator or privacy contact.

Questions about the plugin's privacy implementation can be sent to:

Uzain Ali Siddiqui  
uzainali95@gmail.com

Repository:
https://github.com/UzainAliSiddiqui/moodle-local_admindashboard
