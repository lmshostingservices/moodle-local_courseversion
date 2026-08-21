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
 * Settings for local_courseversion.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Check unlock status - only show notification when viewing THIS settings page
    if (class_exists('\local_courseversion\unlock_verifier')) {
        if (!\local_courseversion\unlock_verifier::is_unlocked()) {
            // Only show warning when user is actually viewing this plugin's settings page
            $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
            if ($currentsection === 'local_courseversion') {
                \core\notification::warning(get_string('unlock_required', 'local_courseversion'));
            }
        }
    }

    // Add main dashboard link under Site Administration > Courses
    $ADMIN->add('courses', new admin_externalpage(
        'local_courseversion_dashboard',
        get_string('pluginname', 'local_courseversion'),
        new moodle_url('/local/courseversion/index.php'),
        'local/courseversion:manage'
    ));
    
    // Settings page under Local Plugins
    $settings = new admin_settingpage('local_courseversion', get_string('settings', 'local_courseversion'));
    
    // Check if Central Config plugin is installed (provides site-wide credentials)
    $centralconfiginstalled = file_exists($CFG->dirroot . '/local/aiconfig/version.php');
    
    $settings->add(new admin_setting_heading(
        'local_courseversion/general',
        get_string('settings', 'local_courseversion'),
        'Configure Course Version Control settings.'
    ));
    
    // API Credentials heading
    $settings->add(new admin_setting_heading(
        'local_courseversion/apicredentials',
        get_string('apicredentials', 'local_courseversion'),
        get_string('apicredentials_desc', 'local_courseversion')
    ));
    
    // Site ID (fallback if Central Config not installed)
    $settings->add(new admin_setting_configtext(
        'local_courseversion/siteid',
        get_string('siteid', 'local_courseversion'),
        get_string('siteid_desc', 'local_courseversion') . ($centralconfiginstalled ? ' ' . get_string('centralconfig_fallback', 'local_courseversion') : ''),
        '',
        PARAM_TEXT
    ));
    
    // API Key (fallback if Central Config not installed)
    $settings->add(new admin_setting_configpasswordunmask(
        'local_courseversion/apikey',
        get_string('apikey', 'local_courseversion'),
        get_string('apikey_desc', 'local_courseversion') . ($centralconfiginstalled ? ' ' . get_string('centralconfig_fallback', 'local_courseversion') : ''),
        ''
    ));
    
    // General Settings heading
    $settings->add(new admin_setting_heading(
        'local_courseversion/generalsettings',
        get_string('generalsettings', 'local_courseversion'),
        ''
    ));
    
    // ASQA Compliance Guidance Toggle
    $settings->add(new admin_setting_configcheckbox(
        'local_courseversion/enableasqaguidance',
        get_string('enableasqaguidance', 'local_courseversion'),
        get_string('enableasqaguidance_desc', 'local_courseversion'),
        1  // Default: enabled for Australian RTOs
    ));
    
    // Default Release Year
    $currentyear = date('Y');
    $years = [];
    for ($y = $currentyear - 2; $y <= $currentyear + 5; $y++) {
        $years[$y] = $y;
    }
    $settings->add(new admin_setting_configselect(
        'local_courseversion/defaultreleaseyear',
        get_string('defaultreleaseyear', 'local_courseversion'),
        get_string('defaultreleaseyear_desc', 'local_courseversion'),
        $currentyear,
        $years
    ));
    
    $ADMIN->add('localplugins', $settings);
}
