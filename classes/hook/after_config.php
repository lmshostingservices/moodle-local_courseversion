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

namespace local_courseversion\hook;

/**
 * Hook callback for after_config.
 *
 * Replaces the legacy local_courseversion_after_config() lib.php callback
 * for Moodle 4.3+ sites that use the new hook dispatcher system.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_config {

    /**
     * Callback to block course editing if version is locked.
     * Fires early in the Moodle bootstrap (after config is loaded).
     *
     * @param \core\hook\after_config $hook
     */
    public static function callback(\core\hook\after_config $hook): void {
        local_courseversion_check_and_block_edit();
    }
}
