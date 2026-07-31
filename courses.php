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
 * Course Version Control - All Courses
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/courseversion:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/courses.php'));
$PAGE->set_title(get_string('managecourses', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$asqaenabled = local_courseversion_asqa_enabled();

// Get all courses with version stats
$courses = $DB->get_records_sql("
    SELECT c.*, 
           (SELECT COUNT(*) FROM {local_cv_versions} v WHERE v.courseid = c.id) as version_count,
           (SELECT version_number FROM {local_cv_versions} v WHERE v.courseid = c.id AND v.status = 'active' LIMIT 1) as active_version,
           (SELECT COUNT(*) FROM {local_cv_versions} v WHERE v.courseid = c.id AND v.status = 'draft') as draft_count,
           (SELECT COUNT(*) FROM {local_cv_versions} v WHERE v.courseid = c.id AND v.locked = 1) as locked_count
    FROM {local_cv_courses} c
    ORDER BY c.course_code
");

echo $OUTPUT->header();
?>

<div class="cv-container">
    <div class="cv-header">
        <div class="cv-header-left">
            <div class="cv-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
            </div>
            <div>
                <h1><?php echo get_string('managecourses', 'local_courseversion'); ?></h1>
                <p>All courses with version control</p>
            </div>
        </div>
        <div class="cv-actions-bar">
            <a href="<?php echo new moodle_url('/local/courseversion/edit_course.php'); ?>" class="cv-btn cv-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?php echo get_string('addcourse', 'local_courseversion'); ?>
            </a>
        </div>
    </div>

    <div class="cv-tabs">
        <a href="<?php echo new moodle_url('/local/courseversion/index.php'); ?>" class="cv-tab"><?php echo get_string('dashboard', 'local_courseversion'); ?></a>
        <a href="<?php echo new moodle_url('/local/courseversion/courses.php'); ?>" class="cv-tab active"><?php echo get_string('managecourses', 'local_courseversion'); ?></a>
        <a href="<?php echo new moodle_url('/local/courseversion/audit.php'); ?>" class="cv-tab"><?php echo get_string('auditlog', 'local_courseversion'); ?></a>
    </div>

    <div class="cv-card">
        <?php if (empty($courses)): ?>
        <div class="cv-empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
            <h3>No courses yet</h3>
            <p>Create your first course to start version control</p>
            <a href="<?php echo new moodle_url('/local/courseversion/edit_course.php'); ?>" class="cv-btn cv-btn-primary"><?php echo get_string('addcourse', 'local_courseversion'); ?></a>
        </div>
        <?php else: ?>
        <div class="cv-table-container">
            <table class="cv-table">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Category</th>
                        <th>Active Version</th>
                        <th>Versions</th>
                        <th>Drafts</th>
                        <th>Locked</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><strong><?php echo s($course->course_code); ?></strong></td>
                        <td><?php echo s($course->course_name); ?></td>
                        <td><?php echo s($course->category ?: '—'); ?></td>
                        <td>
                            <?php if ($course->active_version): ?>
                                <span style="font-family: var(--cv-font-mono); background: var(--cv-success-light); color: #065f46; padding: 2px 8px; border-radius: 4px;">v<?php echo s($course->active_version); ?></span>
                            <?php else: ?>
                                <span style="color: var(--cv-gray-400);">None</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: var(--cv-font-mono);"><?php echo $course->version_count; ?></td>
                        <td style="font-family: var(--cv-font-mono);"><?php echo $course->draft_count; ?></td>
                        <td style="font-family: var(--cv-font-mono);"><?php echo $course->locked_count; ?></td>
                        <td>
                            <div class="cv-actions-bar">
                                <a href="<?php echo new moodle_url('/local/courseversion/versions.php', ['id' => $course->id]); ?>" class="cv-btn cv-btn-primary cv-btn-sm">Versions</a>
                                <a href="<?php echo new moodle_url('/local/courseversion/edit_course.php', ['id' => $course->id]); ?>" class="cv-btn cv-btn-secondary cv-btn-sm">Edit</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
