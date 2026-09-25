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


namespace local_courseversion\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_courseversion.
 *
 * The plugin stores personal data only in the audit log (local_cv_audit_log),
 * which records the user who performed each version-control action. Entries are
 * held at system context.
 *
 * Audit log entries are retained on deletion requests. The log is the evidence
 * trail for course-change compliance (Standards for RTOs 2025), so deleting or
 * altering entries would destroy records the organisation is required to keep.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_cv_audit_log',
            [
                'userid' => 'privacy:metadata:audit_log:userid',
                'action' => 'privacy:metadata:audit_log:action',
                'reason' => 'privacy:metadata:audit_log:reason',
                'ipaddress' => 'privacy:metadata:audit_log:ipaddress',
                'timecreated' => 'privacy:metadata:audit_log:timecreated',
            ],
            'privacy:metadata:audit_log'
        );
        $collection->add_external_location_link(
            'lms_labs_unlock',
            [
                'siteid' => 'privacy:metadata:lms_labs_unlock:siteid',
            ],
            'privacy:metadata:lms_labs_unlock'
        );
        return $collection;
    }

    /**
     * Get the contexts holding data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_cv_audit_log', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Get the users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_cv_audit_log}', []);
    }

    /**
     * Export the user's audit log entries.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $records = $DB->get_records('local_cv_audit_log', ['userid' => $userid], 'timecreated ASC');
            if (!$records) {
                continue;
            }
            $entries = [];
            foreach ($records as $record) {
                $entries[] = (object) [
                    'action' => $record->action,
                    'versionid' => $record->versionid,
                    'courseid' => $record->courseid,
                    'reason' => $record->reason,
                    'details' => $record->details,
                    'ipaddress' => $record->ipaddress,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:auditlog', 'local_courseversion')],
                (object) ['entries' => $entries]
            );
        }
    }

    /**
     * Audit log entries are retained for compliance; nothing is deleted.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // Intentionally empty: see class docblock.
    }

    /**
     * Audit log entries are retained for compliance; nothing is deleted.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // Intentionally empty: see class docblock.
    }

    /**
     * Audit log entries are retained for compliance; nothing is deleted.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // Intentionally empty: see class docblock.
    }
}
