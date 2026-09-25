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
 * Course Version Control - Version Timeline
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/courseversion:manage', $context);

$course = $DB->get_record('local_cv_courses', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/versions.php', ['id' => $id]));
$PAGE->set_title($course->course_code . ' - ' . get_string('courseversions', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$asqaenabled = local_courseversion_asqa_enabled();

// Get all versions for this course
$versions = $DB->get_records_sql("
    SELECT v.*, s.enrolment_count, s.attempt_count, s.last_sync,
           u.firstname, u.lastname
    FROM {local_cv_versions} v
    LEFT JOIN {local_cv_assessment_state} s ON s.versionid = v.id
    LEFT JOIN {user} u ON u.id = v.releasedby
    WHERE v.courseid = ?
    ORDER BY v.timecreated DESC
", [$id]);

// Suggest next version number (simple incremental: 1.0, 1.1, 2.0, etc.)
$latestversion = $DB->get_field_sql("SELECT MAX(version_number) FROM {local_cv_versions} WHERE courseid = ?", [$id]);
if ($latestversion) {
    $parts = explode('.', $latestversion);
    $major = (int)($parts[0] ?? 1);
    $minor = (int)($parts[1] ?? 0);
    $nextversion = $major . '.' . ($minor + 1);
} else {
    $nextversion = '1.0';
}

echo $OUTPUT->header();
?>

<div class="cv-container">
    <a href="<?php echo new moodle_url('/local/courseversion/index.php'); ?>" class="cv-back-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to Dashboard
    </a>

    <div class="cv-header">
        <div class="cv-header-left">
            <div class="cv-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <h1><?php echo s($course->course_code); ?></h1>
                <p><?php echo s($course->course_name); ?></p>
            </div>
        </div>
        <div class="cv-actions-bar">
            <a href="<?php echo new moodle_url('/local/courseversion/edit_version.php', ['courseid' => $id]); ?>" class="cv-btn cv-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?php echo get_string('createversion', 'local_courseversion'); ?>
            </a>
            <a href="<?php echo new moodle_url('/local/courseversion/edit_course.php', ['id' => $id]); ?>" class="cv-btn cv-btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <?php echo get_string('editcourse', 'local_courseversion'); ?>
            </a>
        </div>
    </div>

    <?php if ($asqaenabled): ?>
    <div class="cv-asqa-guidance">
        <div class="cv-asqa-guidance-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <?php echo get_string('asqa_header', 'local_courseversion'); ?>
        </div>
        <p><?php echo get_string('asqa_lock_protection', 'local_courseversion'); ?></p>
    </div>
    <?php endif; ?>

    <?php if (empty($versions)): ?>
    <div class="cv-card">
        <div class="cv-empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            <h3>No versions yet</h3>
            <p>Create your first version to start tracking changes</p>
            <a href="<?php echo new moodle_url('/local/courseversion/edit_version.php', ['courseid' => $id]); ?>" class="cv-btn cv-btn-primary">
                Create Version <?php echo $nextversion; ?>
            </a>
        </div>
    </div>
    <?php else: ?>

    <div class="cv-card">
        <div class="cv-card-header">
            <h2 class="cv-card-title">Version Timeline</h2>
        </div>

        <div class="cv-timeline">
            <?php foreach ($versions as $v): ?>
            <div class="cv-timeline-item <?php echo $v->status; ?>">
                <div class="cv-timeline-dot"></div>
                <div class="cv-card" style="margin-bottom: 0; margin-left: 0;">
                    <div class="cv-card-header">
                        <div>
                            <span class="cv-timeline-version">v<?php echo s($v->version_number); ?></span>
                            <span class="cv-badge cv-badge-<?php echo $v->status; ?>" style="margin-left: 8px;">
                                <?php echo get_string('status_' . $v->status, 'local_courseversion'); ?>
                            </span>
                            <?php if ($v->locked): ?>
                            <span class="cv-badge cv-badge-locked" style="margin-left: 4px;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <?php echo get_string('locked', 'local_courseversion'); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="cv-actions-bar">
                            <?php if ($v->status === 'draft' && !$v->locked): ?>
                            <a href="<?php echo new moodle_url('/local/courseversion/edit_version.php', ['id' => $v->id]); ?>" class="cv-btn cv-btn-secondary cv-btn-sm">Edit</a>
                            <a href="<?php echo new moodle_url('/local/courseversion/release.php', ['id' => $v->id]); ?>" class="cv-btn cv-btn-primary cv-btn-sm">Release</a>
                            <?php elseif ($v->locked && has_capability('local/courseversion:override', $context)): ?>
                            <a href="<?php echo new moodle_url('/local/courseversion/override.php', ['id' => $v->id]); ?>" class="cv-btn cv-btn-secondary cv-btn-sm">Override Lock</a>
                            <?php endif; ?>
                            <?php if ($v->status !== 'archived' && $v->status !== 'active'): ?>
                            <a href="<?php echo new moodle_url('/local/courseversion/archive.php', ['id' => $v->id]); ?>" class="cv-btn cv-btn-secondary cv-btn-sm">Archive</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="cv-timeline-meta">
                        <?php if ($v->releasedat): ?>
                        Released <?php echo userdate($v->releasedat, get_string('strftimedateshort', 'langconfig')); ?>
                        by <?php echo s($v->firstname . ' ' . $v->lastname); ?>
                        <?php else: ?>
                        Created <?php echo userdate($v->timecreated, get_string('strftimedateshort', 'langconfig')); ?>
                        <?php endif; ?>
                        
                        <?php if ($v->enrolment_count || $v->attempt_count): ?>
                        <span style="margin-left: 16px; color: var(--cv-gray-500);">
                            <?php echo (int)$v->enrolment_count . ' enrolments • ' . (int)$v->attempt_count . ' attempts'; ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($v->change_summary): ?>
                    <p style="margin: 12px 0 0; font-size: 14px; color: var(--cv-gray-600);">
                        <?php echo nl2br(s($v->change_summary)); ?>
                    </p>
                    <?php endif; ?>

                    <?php if ($v->tas_version): ?>
                    <p style="margin: 8px 0 0; font-size: 13px; color: var(--cv-gray-500);">
                        <strong>TAS:</strong> <?php echo s($v->tas_version); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
