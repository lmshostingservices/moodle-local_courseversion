# Course Version Control (local_courseversion)

Version control for Moodle courses. Each course can have a series of numbered
versions (draft → released → archived). Once a version is released and has
active enrolments or assessment attempts, the course structure is locked, so
the content students are assessed against can't change under them. Every
action is written to an audit log.

Built for Australian RTOs working under the Standards for RTOs 2025, but usable
by any organisation that needs controlled course changes.

## Requirements

- Moodle 4.0 or later (supported range 4.0 to 5.x, per `version.php`)
- PHP version as required by your Moodle release

## Installation

1. Extract the ZIP into `local/courseversion` in your Moodle directory, or
   upload it via *Site administration → Plugins → Install plugins*.
2. Visit *Site administration → Notifications* to complete the install.
3. Configure the plugin at *Site administration → Plugins → Local plugins →
   Course Version Control Settings*.

## Configuration

| Setting | Purpose |
|---|---|
| Site ID / API Key | LMS Labs credentials used to verify the plugin unlock (see *External services*). Used as a fallback if the `local_aiconfig` Central Config plugin is installed. |
| Enable ASQA guidance | Shows RTO Standards 2025 compliance tips in the interface. Default on. Turn it off if you're not an Australian RTO. |
| Default release year | Default year for new course versions. |

## Features

- Logical courses with multiple numbered versions and status tracking
- Release, archive and override workflows, each with confirmation
- Structural edit blocking on locked courses (activities, sections, course
  settings), while learning activity (submissions, forums, quizzes,
  completion, grading) and the Moodle mobile app keep working
- Immutable audit log, with viewing and export

## Capabilities

| Capability | Purpose |
|---|---|
| `local/courseversion:manage` | Manage course versions |
| `local/courseversion:create` | Create course versions |
| `local/courseversion:release` | Release course versions |
| `local/courseversion:archive` | Archive course versions |
| `local/courseversion:override` | Override version locks (must be assigned explicitly; site admins are not granted it implicitly) |
| `local/courseversion:viewaudit` | View audit logs |
| `local/courseversion:exportaudit` | Export audit logs |

## External services

The plugin checks its unlock status by sending the configured Site ID, API Key
and plugin identifier to `https://lms-labs.com/api/plugin-unlock/verify`. No
student or user data is sent. No request is made until credentials are
entered.

## Privacy

Implements the Moodle Privacy API. Audit log entries record the acting user.

## Support

LMS Labs: https://lms-labs.com

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Licence

GNU GPL v3 or later. See [LICENSE](LICENSE).
