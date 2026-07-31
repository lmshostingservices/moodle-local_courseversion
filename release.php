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
 * Course Version Control - Release Version
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
require_capability('local/courseversion:release', $context);

$version = $DB->get_record('local_cv_versions', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('local_cv_courses', ['id' => $version->courseid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/release.php', ['id' => $id]));
$PAGE->set_title(get_string('releaseversion', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$asqaenabled = local_courseversion_asqa_enabled();

// Check if can release
if ($version->status !== 'draft') {
    throw new moodle_exception('cannotrelease', 'local_courseversion');
}

if (empty($version->change_summary)) {
    redirect(new moodle_url('/local/courseversion/edit_version.php', ['id' => $id]),
        get_string('changesummaryrequired', 'local_courseversion'), null, \core\output\notification::NOTIFY_ERROR);
}

if ($confirm && confirm_sesskey()) {
    local_courseversion_release_version($id);
    redirect(new moodle_url('/local/courseversion/versions.php', ['id' => $version->courseid]),
        get_string('versionreleased', 'local_courseversion', $version->version_number));
}

echo $OUTPUT->header();
?>

<div class="cv-container">
    <a href="<?php echo new moodle_url('/local/courseversion/versions.php', ['id' => $version->courseid]); ?>" class="cv-back-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to <?php echo s($course->course_code); ?>
    </a>

    <div class="cv-header">
        <div class="cv-header-left">
            <div class="cv-header-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div>
                <h1><?php echo get_string('confirmrelease', 'local_courseversion'); ?></h1>
                <p><?php echo s($course->course_code); ?> v<?php echo s($version->version_number); ?></p>
            </div>
        </div>
    </div>

    <?php if ($asqaenabled): ?>
    <div class="cv-asqa-guidance">
        <div class="cv-asqa-guidance-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <?php echo get_string('asqa_header', 'local_courseversion'); ?>
        </div>
        <p><?php echo get_string('asqa_release_checklist', 'local_courseversion'); ?></p>
    </div>
    <?php endif; ?>

    <div class="cv-card">
        <h2 class="cv-card-title" style="margin-bottom: 16px;">Release Version <?php echo s($version->version_number); ?>?</h2>
        
        <p style="color: var(--cv-gray-600); margin-bottom: 20px;">
            <?php echo get_string('confirmrelease_desc', 'local_courseversion'); ?>
        </p>

        <div style="background: var(--cv-gray-50); border-radius: var(--cv-radius); padding: 16px; margin-bottom: 24px;">
            <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 600;">Change Summary</h4>
            <p style="margin: 0; color: var(--cv-gray-700);"><?php echo nl2br(s($version->change_summary)); ?></p>
        </div>

        <?php if ($version->tas_version): ?>
        <p style="font-size: 14px; color: var(--cv-gray-600); margin-bottom: 24px;">
            <strong>TAS Version:</strong> <?php echo s($version->tas_version); ?>
        </p>
        <?php endif; ?>

        <div class="cv-actions-bar">
            <a href="<?php echo new moodle_url('/local/courseversion/release.php', ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]); ?>" class="cv-btn cv-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Release Version
            </a>
            <a href="<?php echo new moodle_url('/local/courseversion/versions.php', ['id' => $version->courseid]); ?>" class="cv-btn cv-btn-secondary">
                Cancel
            </a>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
