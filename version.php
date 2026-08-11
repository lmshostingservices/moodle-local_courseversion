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
 * Plugin version and other meta-data.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_courseversion';
$plugin->version   = 2026072300215;
$plugin->requires  = 2022041900;   // Moodle 4.0+
$plugin->supported = [400, 500];   // Moodle 4.0 to 5.x supported
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.10'; // HOOK-MIGRATION: Migrated local_courseversion_after_config() legacy callback to Moodle 4.3+ hook system (core\hook\after_config). Added db/hooks.php entry and classes/hook/after_config.php. Legacy lib.php function now returns early on Moodle 4.3+ to prevent double-execution.
