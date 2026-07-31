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
 * Course Version Control - Main Dashboard
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

// Check unlock status
if (class_exists('\local_courseversion\unlock_verifier')) {
    if (!\local_courseversion\unlock_verifier::check_and_notify()) {
        $PAGE->set_context($context);
        $PAGE->set_url(new moodle_url('/local/courseversion/index.php'));
        $PAGE->set_title(get_string('pluginname', 'local_courseversion'));
        $PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
        $PAGE->set_pagelayout('admin');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('pluginname', 'local_courseversion'));
        echo $OUTPUT->notification(get_string('unlock_required', 'local_courseversion'), 'warning');
        echo $OUTPUT->footer();
        die();
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$asqaenabled = local_courseversion_asqa_enabled();

// Get stats
$totalcourses = $DB->count_records('local_cv_courses');
$activeversions = $DB->count_records('local_cv_versions', ['status' => 'active']);
$draftversions = $DB->count_records('local_cv_versions', ['status' => 'draft']);
$lockedversions = $DB->count_records('local_cv_versions', ['locked' => 1]);

// Get recent courses with their active version
$courses = $DB->get_records_sql("
    SELECT c.*, 
           v.version_number as active_version,
           v.status as version_status,
           v.locked as version_locked
    FROM {local_cv_courses} c
    LEFT JOIN {local_cv_versions} v ON v.courseid = c.id AND v.status = 'active'
    ORDER BY c.timemodified DESC
    LIMIT 20
");

// Get recent audit log
$auditlog = $DB->get_records_sql("
    SELECT a.*, u.firstname, u.lastname, c.course_code, v.version_number
    FROM {local_cv_audit_log} a
    LEFT JOIN {user} u ON u.id = a.userid
    LEFT JOIN {local_cv_courses} c ON c.id = a.courseid
    LEFT JOIN {local_cv_versions} v ON v.id = a.versionid
    ORDER BY a.timecreated DESC
    LIMIT 10
");

echo $OUTPUT->header();
?>

<div class="cv-container">
    <div class="cv-header">
        <div class="cv-header-left">
            <div class="cv-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <path d="M12 18v-6"/>
                    <path d="M9 15l3-3 3 3"/>
                </svg>
            </div>
            <div>
                <h1><?php echo get_string('pluginname', 'local_courseversion'); ?></h1>
                <p>Manage course versions, protect student results, maintain compliance</p>
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
        <a href="<?php echo new moodle_url('/local/courseversion/index.php'); ?>" class="cv-tab active"><?php echo get_string('dashboard', 'local_courseversion'); ?></a>
        <a href="<?php echo new moodle_url('/local/courseversion/courses.php'); ?>" class="cv-tab"><?php echo get_string('managecourses', 'local_courseversion'); ?></a>
        <a href="<?php echo new moodle_url('/local/courseversion/audit.php'); ?>" class="cv-tab"><?php echo get_string('auditlog', 'local_courseversion'); ?></a>
    </div>

    <?php if ($asqaenabled): ?>
    <div class="cv-asqa-guidance">
        <div class="cv-asqa-guidance-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <?php echo get_string('asqa_header', 'local_courseversion'); ?>
        </div>
        <p><?php echo get_string('asqa_version_control', 'local_courseversion'); ?></p>
    </div>
    <?php endif; ?>

    <div class="cv-stats-grid">
        <div class="cv-stat-card">
            <div class="cv-stat-label"><?php echo get_string('totalcourses', 'local_courseversion'); ?></div>
            <div class="cv-stat-value"><?php echo $totalcourses; ?></div>
        </div>
        <div class="cv-stat-card">
            <div class="cv-stat-label"><?php echo get_string('activeversions', 'local_courseversion'); ?></div>
            <div class="cv-stat-value success"><?php echo $activeversions; ?></div>
        </div>
        <div class="cv-stat-card">
            <div class="cv-stat-label"><?php echo get_string('draftversions', 'local_courseversion'); ?></div>
            <div class="cv-stat-value primary"><?php echo $draftversions; ?></div>
        </div>
        <div class="cv-stat-card">
            <div class="cv-stat-label"><?php echo get_string('lockedversions', 'local_courseversion'); ?></div>
            <div class="cv-stat-value warning"><?php echo $lockedversions; ?></div>
        </div>
    </div>

    <div class="cv-card">
        <div class="cv-card-header">
            <div>
                <h2 class="cv-card-title"><?php echo get_string('managecourses', 'local_courseversion'); ?></h2>
                <p class="cv-card-subtitle">All courses with version control</p>
            </div>
            <a href="<?php echo new moodle_url('/local/courseversion/courses.php'); ?>" class="cv-btn cv-btn-secondary cv-btn-sm">View All</a>
        </div>

        <?php if (empty($courses)): ?>
        <div class="cv-empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
            <h3>No courses yet</h3>
            <p>Get started by adding your first course</p>
            <a href="<?php echo new moodle_url('/local/courseversion/edit_course.php'); ?>" class="cv-btn cv-btn-primary"><?php echo get_string('addcourse', 'local_courseversion'); ?></a>
        </div>
        <?php else: ?>
        <div class="cv-table-container">
            <table class="cv-table">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Active Version</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><strong><?php echo s($course->course_code); ?></strong></td>
                        <td><?php echo s($course->course_name); ?></td>
                        <td>
                            <?php if ($course->active_version): ?>
                                <span style="font-family: var(--cv-font-mono);">v<?php echo s($course->active_version); ?></span>
                            <?php else: ?>
                                <span style="color: var(--cv-gray-400);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($course->version_locked): ?>
                                <span class="cv-badge cv-badge-locked">Locked</span>
                            <?php elseif ($course->version_status): ?>
                                <span class="cv-badge cv-badge-<?php echo $course->version_status; ?>"><?php echo ucfirst($course->version_status); ?></span>
                            <?php else: ?>
                                <span class="cv-badge cv-badge-draft">No Version</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="cv-actions-bar">
                                <a href="<?php echo new moodle_url('/local/courseversion/versions.php', ['id' => $course->id]); ?>" class="cv-btn cv-btn-secondary cv-btn-sm"><?php echo get_string('viewtimeline', 'local_courseversion'); ?></a>
                                <a href="<?php echo new moodle_url('/local/courseversion/edit_course.php', ['id' => $course->id]); ?>" class="cv-btn cv-btn-secondary cv-btn-sm"><?php echo get_string('editcourse', 'local_courseversion'); ?></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="cv-card">
        <div class="cv-card-header">
            <div>
                <h2 class="cv-card-title"><?php echo get_string('recentactivity', 'local_courseversion'); ?></h2>
                <p class="cv-card-subtitle">Audit trail of recent actions</p>
            </div>
            <a href="<?php echo new moodle_url('/local/courseversion/audit.php'); ?>" class="cv-btn cv-btn-secondary cv-btn-sm">View All</a>
        </div>

        <?php if (empty($auditlog)): ?>
        <div class="cv-empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
            </svg>
            <h3>No activity yet</h3>
            <p>Actions will appear here as you manage courses and versions</p>
        </div>
        <?php else: ?>
        <div class="cv-table-container">
            <table class="cv-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Course</th>
                        <th>Version</th>
                        <th>User</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditlog as $log): ?>
                    <tr>
                        <td>
                            <span class="cv-badge cv-badge-<?php echo ($log->action === 'release' || $log->action === 'create') ? 'active' : (($log->action === 'override') ? 'locked' : 'draft'); ?>">
                                <?php echo get_string('action_' . $log->action, 'local_courseversion'); ?>
                            </span>
                        </td>
                        <td><?php echo s($log->course_code ?: '—'); ?></td>
                        <td style="font-family: var(--cv-font-mono);"><?php echo $log->version_number ? 'v' . s($log->version_number) : '—'; ?></td>
                        <td><?php echo s($log->firstname . ' ' . $log->lastname); ?></td>
                        <td><?php echo userdate($log->timecreated, get_string('strftimedatetime', 'langconfig')); ?></td>
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
