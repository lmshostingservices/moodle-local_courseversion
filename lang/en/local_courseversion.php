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

/**
 * Language strings for local_courseversion.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Course Version Control';
$string['courseversion:manage'] = 'Manage course versions';
$string['courseversion:create'] = 'Create course versions';
$string['courseversion:release'] = 'Release course versions';
$string['courseversion:archive'] = 'Archive course versions';
$string['courseversion:override'] = 'Override version locks';
$string['courseversion:viewaudit'] = 'View audit logs';
$string['courseversion:exportaudit'] = 'Export audit logs';

// Settings
$string['settings'] = 'Course Version Control Settings';
$string['apicredentials'] = 'API Credentials';
$string['apicredentials_desc'] = 'Enter your AI Grader credentials to enable plugin unlock verification. These credentials are available from your AI Grader dashboard at lms-labs.com.';
$string['siteid'] = 'Site ID';
$string['siteid_desc'] = 'Your unique Site ID from the AI Grader dashboard. This is checked first for credit verification.';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Your API Key from the AI Grader dashboard. This is checked first for credit verification.';
$string['centralconfig_fallback'] = '(If empty, falls back to Central Config plugin settings)';
$string['generalsettings'] = 'General Settings';
$string['enableasqaguidance'] = 'Enable ASQA Compliance Guidance';
$string['enableasqaguidance_desc'] = 'Show Australian RTO Standards 2025 compliance tips and guidance throughout the interface. Disable this for non-Australian organisations.';
$string['defaultreleaseyear'] = 'Default Release Year';
$string['defaultreleaseyear_desc'] = 'Default year for new course versions.';

// Navigation
$string['managecourses'] = 'Manage Courses';
$string['courseversions'] = 'Course Versions';
$string['auditlog'] = 'Audit Log';
$string['dashboard'] = 'Dashboard';

// Course fields
$string['coursecode'] = 'Course Code';
$string['coursecode_help'] = 'A unique identifier for this course (e.g., BSBOPS201, CERT-WHS-001). This should match your training product code if applicable.';
$string['coursename'] = 'Course Name';
$string['coursename_help'] = 'The full official name of the course as it appears on certificates and marketing materials.';
$string['category'] = 'Category';
$string['category_help'] = 'Group courses by category for easier management (e.g., Business, Safety, IT).';
$string['description'] = 'Description';
$string['description_help'] = 'A brief description of the course content and target audience.';
$string['moodlecourse'] = 'Linked Moodle Course';
$string['moodlecourse_help'] = 'Link this to an existing Moodle course to sync enrolment and attempt data for automatic version locking.';

// Version fields
$string['versionnumber'] = 'Version Number';
$string['versionnumber_help'] = 'Use semantic versioning (e.g., 25.0 for 2025 initial, 25.1 for first revision). Major number typically represents the year.';
$string['releaseyear'] = 'Release Year';
$string['releaseyear_help'] = 'The year this version was or will be released. Used for compliance reporting.';
$string['status'] = 'Status';
$string['status_help'] = 'Draft: Being developed. Active: Currently in use. Superseded: Replaced by newer version. Archived: Permanently stored.';
$string['changesummary'] = 'Change Summary';
$string['changesummary_help'] = 'Document what changed in this version. Required for release and compliance audits.';
$string['tasversion'] = 'TAS Document Version';
$string['tasversion_help'] = 'The Training and Assessment Strategy document version that applies to this course version.';
$string['validationdate'] = 'Last Validation Date';
$string['validationdate_help'] = 'Date when this version was last validated for quality and compliance.';
$string['validationnotes'] = 'Validation Notes';
$string['validationnotes_help'] = 'Notes from the last validation review, including any actions taken.';

// Status values
$string['status_draft'] = 'Draft';
$string['status_active'] = 'Active';
$string['status_superseded'] = 'Superseded';
$string['status_archived'] = 'Archived';

// Actions
$string['addcourse'] = 'Add Course';
$string['editcourse'] = 'Edit Course';
$string['createversion'] = 'Create New Version';
$string['editversion'] = 'Edit Version';
$string['releaseversion'] = 'Release Version';
$string['archiveversion'] = 'Archive Version';
$string['overridelock'] = 'Override Lock';
$string['viewtimeline'] = 'View Timeline';
$string['viewdetails'] = 'View Details';
$string['exportcsv'] = 'Export CSV';
$string['exportpdf'] = 'Export PDF';

// Lock states
$string['locked'] = 'Locked';
$string['unlocked'] = 'Unlocked';
$string['lockreason'] = 'Lock Reason';
$string['lockreason_help'] = 'Explain why this version is locked. This is recorded in the audit log.';
$string['autolockedmessage'] = 'This version is automatically locked because it has {$a->enrolments} enrolment(s) and {$a->attempts} assessment attempt(s).';
$string['overridereason'] = 'Override Reason';
$string['overridereason_help'] = 'Provide a detailed reason for overriding the lock. This action is audited and requires manager approval.';

// Confirmations
$string['confirmrelease'] = 'Confirm Release';
$string['confirmrelease_desc'] = 'Releasing this version will make it active and lock it for editing. The current active version will be superseded. This action cannot be undone.';
$string['confirmarchive'] = 'Confirm Archive';
$string['confirmarchive_desc'] = 'Archiving this version will permanently lock it and hide it from active views. Archived versions cannot be deleted.';
$string['confirmoverride'] = 'Confirm Lock Override';
$string['confirmoverride_desc'] = 'You are about to override a safety lock. This action is logged and may require justification during compliance audits.';

// Messages
$string['coursecreated'] = 'Course created successfully.';
$string['courseupdated'] = 'Course updated successfully.';
$string['versioncreated'] = 'Version {$a} created successfully.';
$string['versionupdated'] = 'Version updated successfully.';
$string['versionreleased'] = 'Version {$a} has been released and is now active.';
$string['versionarchived'] = 'Version {$a} has been archived.';
$string['lockoverridden'] = 'Lock has been overridden. This action has been logged.';
$string['cannoteditversionlocked'] = 'This version is locked and cannot be edited.';
$string['changesummaryrequired'] = 'A change summary is required to release a version.';

// Dashboard stats
$string['totalcourses'] = 'Total Courses';
$string['activeversions'] = 'Active Versions';
$string['draftversions'] = 'Draft Versions';
$string['lockedversions'] = 'Locked Versions';
$string['recentactivity'] = 'Recent Activity';

// Audit log
$string['auditaction'] = 'Action';
$string['audituser'] = 'User';
$string['audittime'] = 'Time';
$string['auditreason'] = 'Reason';
$string['auditdetails'] = 'Details';
$string['action_create'] = 'Created';
$string['action_edit'] = 'Edited';
$string['action_release'] = 'Released';
$string['action_archive'] = 'Archived';
$string['action_override'] = 'Lock Override';
$string['action_sync'] = 'Data Sync';

// ASQA Compliance Guidance (shown when enabled)
$string['asqa_header'] = 'RTO Standards 2025 Guidance';
$string['asqa_version_control'] = 'Standard 1.3 requires all assessment tools to be reviewed prior to use. Version control ensures you can demonstrate which materials were used for each student cohort.';
$string['asqa_change_summary'] = 'Document all changes to training materials. ASQA expects evidence that materials are "used, reviewed, and improved" - not static.';
$string['asqa_validation'] = 'The 2025 Standards require risk-based validation. Record validation dates and outcomes to demonstrate continuous improvement.';
$string['asqa_tas_link'] = 'Link each course version to your Training and Assessment Strategy (TAS) document version for full traceability.';
$string['asqa_lock_protection'] = 'Versions with enrolled students or assessment attempts are automatically locked to protect student results - a key compliance requirement.';
$string['asqa_audit_trail'] = 'All actions are logged with timestamps, users, and reasons. This immutable audit trail supports ASQA compliance reviews.';
$string['asqa_archive_retention'] = 'Archived versions are retained indefinitely. RTO regulations require 7+ year retention of training records.';
$string['asqa_release_checklist'] = 'Before releasing: Ensure TAS is updated, materials are validated, and change summary documents all modifications.';

// Errors
$string['error_coursecodeexists'] = 'A course with this code already exists.';
$string['error_versionexists'] = 'This version number already exists for this course.';
$string['error_cannotarchiveactive'] = 'Cannot archive the active version. Release a new version first.';
$string['error_cannotdeleteversion'] = 'Versions cannot be deleted. Archive them instead for compliance.';
$string['error_noreason'] = 'A reason is required for this action.';

// Course lock messages
$string['courselocked'] = 'This course is locked (Version {$a->version}). {$a->reason} Editing is disabled to protect student results. Contact an administrator with override permissions if changes are required.';
$string['action_blocked_edit'] = 'Blocked Edit Attempt';
$string['action_auto_lock'] = 'Auto-Locked';

// Unlock verification
$string['unlock_required'] = 'This plugin requires 1000 credits to unlock. Please visit lms-labs.com to purchase credits.';
