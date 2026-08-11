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
 * Event observers for local_courseversion.
 *
 * Prevents editing of locked courses.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseversion;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class.
 */
class observer {
    /**
     * Check if a course is locked before allowing updates.
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event) {
        global $DB;
        
        $courseid = $event->courseid;
        
        // Check if this course is linked to a locked version
        $locked = self::is_course_locked($courseid);
        
        if ($locked) {
            // Log the attempted edit
            $cvcourse = $DB->get_record('local_cv_courses', ['moodle_course_id' => $courseid]);
            if ($cvcourse) {
                require_once(__DIR__ . '/../lib.php');
                local_courseversion_log_action('blocked_edit', null, $cvcourse->id, 
                    'Attempted course settings edit while locked');
            }
        }
    }

    /**
     * Check if a course module is being updated on a locked course.
     *
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        global $DB;
        
        $courseid = $event->courseid;
        
        if (self::is_course_locked($courseid)) {
            $cvcourse = $DB->get_record('local_cv_courses', ['moodle_course_id' => $courseid]);
            if ($cvcourse) {
                require_once(__DIR__ . '/../lib.php');
                local_courseversion_log_action('blocked_edit', null, $cvcourse->id, 
                    'Attempted activity edit while locked');
            }
        }
    }

    /**
     * Check if a course module is being created on a locked course.
     *
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $DB;
        
        $courseid = $event->courseid;
        
        if (self::is_course_locked($courseid)) {
            $cvcourse = $DB->get_record('local_cv_courses', ['moodle_course_id' => $courseid]);
            if ($cvcourse) {
                require_once(__DIR__ . '/../lib.php');
                local_courseversion_log_action('blocked_edit', null, $cvcourse->id, 
                    'Attempted activity creation while locked');
            }
        }
    }

    /**
     * Check if a course module is being deleted from a locked course.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;
        
        $courseid = $event->courseid;
        
        if (self::is_course_locked($courseid)) {
            $cvcourse = $DB->get_record('local_cv_courses', ['moodle_course_id' => $courseid]);
            if ($cvcourse) {
                require_once(__DIR__ . '/../lib.php');
                local_courseversion_log_action('blocked_edit', null, $cvcourse->id, 
                    'Attempted activity deletion while locked');
            }
        }
    }

    /**
     * Check if a course is locked via version control.
     *
     * @param int $moodlecourseid The Moodle course ID
     * @return bool True if locked
     */
    public static function is_course_locked($moodlecourseid) {
        global $DB;
        
        // Find the course version control entry
        $cvcourse = $DB->get_record('local_cv_courses', ['moodle_course_id' => $moodlecourseid]);
        if (!$cvcourse) {
            return false;
        }
        
        // Check if active version is locked
        $activeversion = $DB->get_record('local_cv_versions', [
            'courseid' => $cvcourse->id,
            'status' => 'active',
            'locked' => 1
        ]);
        
        return !empty($activeversion);
    }

    /**
     * Get lock info for a course.
     *
     * @param int $moodlecourseid The Moodle course ID
     * @return object|false Lock info or false if not locked
     */
    public static function get_lock_info($moodlecourseid) {
        global $DB;
        
        $cvcourse = $DB->get_record('local_cv_courses', ['moodle_course_id' => $moodlecourseid]);
        if (!$cvcourse) {
            return false;
        }
        
        $activeversion = $DB->get_record('local_cv_versions', [
            'courseid' => $cvcourse->id,
            'status' => 'active',
            'locked' => 1
        ]);
        
        if (!$activeversion) {
            return false;
        }
        
        return (object)[
            'version' => $activeversion->version_number,
            'reason' => $activeversion->lock_reason,
            'cvcourse_id' => $cvcourse->id
        ];
    }
}
