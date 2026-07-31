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
 * Privacy provider for local_courseversion.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseversion\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

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
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        // Users in system context who have audit log entries
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        // Export audit log entries for the user
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        // Audit logs should not be deleted for compliance
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // Audit logs should not be deleted for compliance
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        // Audit logs should not be deleted for compliance
    }
}
