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
 * Course Version Control - Audit Log
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

require_login();
$context = context_system::instance();
require_capability('local/courseversion:viewaudit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/audit.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('auditlog', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$asqaenabled = local_courseversion_asqa_enabled();

// Build query
$where = [];
$params = [];
if ($courseid) {
    $where[] = 'a.courseid = :courseid';
    $params['courseid'] = $courseid;
}

$whereclause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $DB->count_records_sql("SELECT COUNT(*) FROM {local_cv_audit_log} a $whereclause", $params);

$auditlog = $DB->get_records_sql("
    SELECT a.*, u.firstname, u.lastname, u.email, 
           c.course_code, c.course_name, v.version_number
    FROM {local_cv_audit_log} a
    LEFT JOIN {user} u ON u.id = a.userid
    LEFT JOIN {local_cv_courses} c ON c.id = a.courseid
    LEFT JOIN {local_cv_versions} v ON v.id = a.versionid
    $whereclause
    ORDER BY a.timecreated DESC
", $params, $page * $perpage, $perpage);

// CSV Export
if ($export === 'csv' && has_capability('local/courseversion:exportaudit', $context)) {
    $allrecords = $DB->get_records_sql("
        SELECT a.*, u.firstname, u.lastname, u.email, 
               c.course_code, c.course_name, v.version_number
        FROM {local_cv_audit_log} a
        LEFT JOIN {user} u ON u.id = a.userid
        LEFT JOIN {local_cv_courses} c ON c.id = a.courseid
        LEFT JOIN {local_cv_versions} v ON v.id = a.versionid
        $whereclause
        ORDER BY a.timecreated DESC
    ", $params);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date/Time', 'Action', 'Course Code', 'Course Name', 'Version', 'User', 'Email', 'Reason', 'IP Address']);

    foreach ($allrecords as $row) {
        fputcsv($out, [
            userdate($row->timecreated, '%Y-%m-%d %H:%M:%S'),
            $row->action,
            $row->course_code,
            $row->course_name,
            $row->version_number,
            $row->firstname . ' ' . $row->lastname,
            $row->email,
            $row->reason,
            $row->ipaddress
        ]);
    }
    fclose($out);
    exit;
}

// Get courses for filter
$courses = $DB->get_records('local_cv_courses', null, 'course_code', 'id, course_code, course_name');

echo $OUTPUT->header();
?>

<div class="cv-container">
    <div class="cv-header">
        <div class="cv-header-left">
            <div class="cv-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                </svg>
            </div>
            <div>
                <h1><?php echo get_string('auditlog', 'local_courseversion'); ?></h1>
                <p>Complete audit trail of all version control actions</p>
            </div>
        </div>
        <?php if (has_capability('local/courseversion:exportaudit', $context)): ?>
        <div class="cv-actions-bar">
            <a href="<?php echo new moodle_url('/local/courseversion/audit.php', ['courseid' => $courseid, 'export' => 'csv']); ?>" class="cv-btn cv-btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?php echo get_string('exportcsv', 'local_courseversion'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="cv-tabs">
        <?php echo html_writer::link(new moodle_url('/local/courseversion/index.php'), get_string('dashboard', 'local_courseversion'), ['class' => 'cv-tab']); ?>
        <?php echo html_writer::link(new moodle_url('/local/courseversion/courses.php'), get_string('managecourses', 'local_courseversion'), ['class' => 'cv-tab']); ?>
        <?php echo html_writer::link(new moodle_url('/local/courseversion/audit.php'), get_string('auditlog', 'local_courseversion'), ['class' => 'cv-tab active']); ?>
    </div>

    <?php if ($asqaenabled): ?>
    <div class="cv-asqa-guidance">
        <div class="cv-asqa-guidance-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <?php echo get_string('asqa_header', 'local_courseversion'); ?>
        </div>
        <p><?php echo get_string('asqa_audit_trail', 'local_courseversion') . ' ' . get_string('asqa_archive_retention', 'local_courseversion'); ?></p>
    </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="cv-card" style="margin-bottom: 16px;">
        <form method="get" action="" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
            <div>
                <label style="font-size: 14px; font-weight: 500; color: var(--cv-gray-600); margin-right: 8px;">Filter by course:</label>
                <select name="courseid" class="cv-form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="0">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c->id . '"' . ($courseid == $c->id ? ' selected' : ''); ?>>
                        <?php echo s($c->course_code . ' - ' . $c->course_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($courseid): ?>
            <a href="<?php echo new moodle_url('/local/courseversion/audit.php'); ?>" class="cv-btn cv-btn-secondary cv-btn-sm">Clear Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="cv-card">
        <div class="cv-card-header">
            <h2 class="cv-card-title"><?php echo $total; ?> Audit Records</h2>
        </div>

        <?php if (empty($auditlog)): ?>
        <div class="cv-empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
            </svg>
            <h3>No audit records yet</h3>
            <p>Actions will be logged as courses and versions are managed</p>
        </div>
        <?php else: ?>
        <div class="cv-table-container">
            <table class="cv-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Action</th>
                        <th>Course</th>
                        <th>Version</th>
                        <th>User</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditlog as $log): ?>
                    <tr>
                        <td style="white-space: nowrap;"><?php echo userdate($log->timecreated, get_string('strftimedatetime', 'langconfig')); ?></td>
                        <td>
                            <span class="cv-badge cv-badge-<?php echo ($log->action === 'release' || $log->action === 'create') ? 'active' : (($log->action === 'override') ? 'locked' : 'draft'); ?>">
                                <?php
                                    $actionkey = 'action_' . $log->action;
                                    echo get_string_manager()->string_exists($actionkey, 'local_courseversion')
                                        ? get_string($actionkey, 'local_courseversion')
                                        : ucfirst($log->action);
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log->course_code): ?>
                            <a href="<?php echo new moodle_url('/local/courseversion/versions.php', ['id' => $log->courseid]); ?>" style="color: var(--cv-primary); text-decoration: none;">
                                <?php echo s($log->course_code); ?>
                            </a>
                            <?php else: ?>
                            <span style="color: var(--cv-gray-400);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: var(--cv-font-mono);"><?php echo $log->version_number ? 'v' . s($log->version_number) : '—'; ?></td>
                        <td><?php echo s($log->firstname . ' ' . $log->lastname); ?></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;"><?php echo s($log->reason ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > $perpage): ?>
        <div style="padding: 16px; border-top: 1px solid var(--cv-gray-200); text-align: center;">
            <?php
            $baseurl = new moodle_url('/local/courseversion/audit.php', ['courseid' => $courseid]);
            echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
            ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
