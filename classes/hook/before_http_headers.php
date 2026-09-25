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
 * Hook callback for before_http_headers.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_http_headers {
    /**
     * Callback to block course editing if version is locked.
     * Uses shared function from lib.php for consistency.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function callback(\core\hook\output\before_http_headers $hook): void {
        global $CFG;
        // Match core: legacy lib.php callbacks are never run during install/upgrade,
        // and our tables may not exist yet.
        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }
        // Hook callbacks are autoloaded classes. Moodle does not guarantee this
        // plugin's lib.php is loaded before they run, so load it explicitly.
        require_once(__DIR__ . '/../../lib.php');
        \local_courseversion_check_and_block_edit();
    }
}
