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
 * Course Version Control - Archive Version
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
require_capability('local/courseversion:archive', $context);

$version = $DB->get_record('local_cv_versions', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('local_cv_courses', ['id' => $version->courseid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/archive.php', ['id' => $id]));
$PAGE->set_title(get_string('archiveversion', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$asqaenabled = local_courseversion_asqa_enabled();

// Cannot archive active version
if ($version->status === 'active') {
    throw new moodle_exception('error_cannotarchiveactive', 'local_courseversion');
}

class archive_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $version = $this->_customdata['version'];

        $mform->addElement('hidden', 'id', $version->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'confirm', 1);
        $mform->setType('confirm', PARAM_BOOL);

        $mform->addElement('textarea', 'reason', 'Archive Reason (Optional)', ['class' => 'cv-form-textarea', 'rows' => 3, 'cols' => 60]);
        $mform->setType('reason', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('archiveversion', 'local_courseversion'));
    }
}

$form = new archive_form(null, ['version' => $version]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/courseversion/versions.php', ['id' => $version->courseid]));
}

if ($data = $form->get_data()) {
    local_courseversion_archive_version($id, $data->reason);
    redirect(new moodle_url('/local/courseversion/versions.php', ['id' => $version->courseid]),
        get_string('versionarchived', 'local_courseversion', $version->version_number));
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
            <div class="cv-header-icon" style="background: linear-gradient(135deg, #6b7280, #4b5563);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>
                </svg>
            </div>
            <div>
                <h1><?php echo get_string('confirmarchive', 'local_courseversion'); ?></h1>
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
        <p><?php echo get_string('asqa_archive_retention', 'local_courseversion'); ?></p>
    </div>
    <?php endif; ?>

    <div class="cv-card">
        <h2 class="cv-card-title" style="margin-bottom: 16px;">Archive Version <?php echo s($version->version_number); ?>?</h2>
        
        <p style="color: var(--cv-gray-600); margin-bottom: 20px;">
            <?php echo get_string('confirmarchive_desc', 'local_courseversion'); ?>
        </p>

        <div style="background: var(--cv-gray-50); border-radius: var(--cv-radius); padding: 16px; margin-bottom: 24px;">
            <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 600;">Version Details</h4>
            <p style="margin: 0; color: var(--cv-gray-700);">
                <strong>Status:</strong> <?php echo ucfirst($version->status); ?><br>
                <strong>Created:</strong> <?php echo userdate($version->timecreated, get_string('strftimedatetime', 'langconfig')); ?>
                <?php if ($version->change_summary): ?>
                <br><strong>Changes:</strong> <?php echo s($version->change_summary); ?>
                <?php endif; ?>
            </p>
        </div>

        <?php $form->display(); ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
