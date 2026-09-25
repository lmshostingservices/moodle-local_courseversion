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
 * Course Version Control - Override Lock
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

require_login();
$context = context_system::instance();
require_capability('local/courseversion:override', $context);

$version = $DB->get_record('local_cv_versions', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('local_cv_courses', ['id' => $version->courseid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/override.php', ['id' => $id]));
$PAGE->set_title(get_string('overridelock', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$asqaenabled = local_courseversion_asqa_enabled();

class override_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $version = $this->_customdata['version'];

        $mform->addElement('hidden', 'id', $version->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('textarea', 'reason', get_string('overridereason', 'local_courseversion'), ['class' => 'cv-form-textarea', 'rows' => 4, 'cols' => 60]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', get_string('error_noreason', 'local_courseversion'), 'required', null, 'client');
        $mform->addHelpButton('reason', 'overridereason', 'local_courseversion');

        $this->add_action_buttons(true, get_string('overridelock', 'local_courseversion'));
    }
}

$form = new override_form(null, ['version' => $version]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/courseversion/versions.php', ['id' => $version->courseid]));
}

if ($data = $form->get_data()) {
    local_courseversion_override_lock($id, $data->reason);
    redirect(
        new moodle_url('/local/courseversion/versions.php', ['id' => $version->courseid]),
        get_string('lockoverridden', 'local_courseversion')
    );
}

// Get assessment state
$state = $DB->get_record('local_cv_assessment_state', ['versionid' => $id]);

echo $OUTPUT->header();
?>

<div class="cv-container">
    <a href="<?php echo new moodle_url('/local/courseversion/versions.php', ['id' => $version->courseid]); ?>" class="cv-back-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to <?php echo s($course->course_code); ?>
    </a>

    <div class="cv-header">
        <div class="cv-header-left">
            <div class="cv-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div>
                <h1><?php echo get_string('confirmoverride', 'local_courseversion'); ?></h1>
                <p><?php echo s($course->course_code) . ' v' . s($version->version_number); ?></p>
            </div>
        </div>
    </div>

    <div class="cv-lock-warning">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div class="cv-lock-warning-content">
            <div class="cv-lock-warning-title">Warning: Overriding Version Lock</div>
            <div class="cv-lock-warning-text">
                <?php echo get_string('confirmoverride_desc', 'local_courseversion'); ?>
            </div>
        </div>
    </div>

    <?php if ($state && ($state->enrolment_count > 0 || $state->attempt_count > 0)): ?>
    <div class="cv-card" style="margin-bottom: 16px; background: var(--cv-warning-light); border-color: #fcd34d;">
        <h3 style="margin: 0 0 12px; color: #92400e;">Assessment Data Detected</h3>
        <p style="margin: 0; color: #78350f;">
            This version has <strong><?php echo $state->enrolment_count; ?> student enrolment(s)</strong> and 
            <strong><?php echo $state->attempt_count; ?> assessment attempt(s)</strong>. 
            Modifying this version may affect student results and records.
        </p>
    </div>
    <?php endif; ?>

    <?php if ($asqaenabled): ?>
    <div class="cv-asqa-guidance" style="background: linear-gradient(135deg, #fef3c7 0%, #fee2e2 100%); border-color: #fcd34d; border-left-color: #f59e0b;">
        <div class="cv-asqa-guidance-header" style="color: #92400e;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            RTO Standards 2025 Warning
        </div>
        <p style="color: #78350f;">
            Overriding a version lock may affect compliance. The 2025 Standards require RTOs to maintain accurate records of assessment materials used for each student cohort. 
            This action will be recorded in the audit trail. Ensure you can justify this decision during compliance reviews.
        </p>
    </div>
    <?php endif; ?>

    <div class="cv-card">
        <h2 class="cv-card-title" style="margin-bottom: 16px;">Provide Override Reason</h2>
        
        <?php if ($version->lock_reason): ?>
        <div style="background: var(--cv-gray-50); border-radius: var(--cv-radius); padding: 16px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 600;">Current Lock Reason</h4>
            <p style="margin: 0; color: var(--cv-gray-700);"><?php echo nl2br(s($version->lock_reason)); ?></p>
        </div>
        <?php endif; ?>

        <?php $form->display(); ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
