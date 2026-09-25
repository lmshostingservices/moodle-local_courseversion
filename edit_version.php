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
 * Course Version Control - Add/Edit Version
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/courseversion:create', $context);

$existing = $id ? $DB->get_record('local_cv_versions', ['id' => $id], '*', MUST_EXIST) : null;

if ($existing) {
    $courseid = $existing->courseid;
    if ($existing->locked) {
        throw new moodle_exception('cannoteditversionlocked', 'local_courseversion');
    }
}

$course = $DB->get_record('local_cv_courses', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/edit_version.php', ['id' => $id, 'courseid' => $courseid]));
$PAGE->set_title(get_string($id ? 'editversion' : 'createversion', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$asqaenabled = local_courseversion_asqa_enabled();

// Suggest next version number
$year = date('y');
$latestversion = $DB->get_field_sql("SELECT MAX(version_number) FROM {local_cv_versions} WHERE courseid = ?", [$courseid]);
if ($latestversion) {
    $parts = explode('.', $latestversion);
    if (count($parts) >= 2 && $parts[0] == $year) {
        $suggestedversion = $year . '.' . ($parts[1] + 1);
    } else {
        $suggestedversion = $year . '.0';
    }
} else {
    $suggestedversion = $year . '.0';
}

class version_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $existing = $this->_customdata['existing'];
        $courseid = $this->_customdata['courseid'];
        $suggestedversion = $this->_customdata['suggestedversion'];
        $asqaenabled = $this->_customdata['asqaenabled'];

        $mform->addElement('hidden', 'id', $existing ? $existing->id : 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Version Number
        $mform->addElement('text', 'version_number', get_string('versionnumber', 'local_courseversion'), ['class' => 'cv-form-input', 'size' => 20]);
        $mform->setType('version_number', PARAM_TEXT);
        $mform->setDefault('version_number', $existing ? $existing->version_number : $suggestedversion);
        $mform->addRule('version_number', null, 'required', null, 'client');
        $mform->addHelpButton('version_number', 'versionnumber', 'local_courseversion');

        // Release Year
        $currentyear = date('Y');
        $years = [];
        for ($y = $currentyear - 5; $y <= $currentyear + 5; $y++) {
            $years[$y] = $y;
        }
        $mform->addElement('select', 'release_year', get_string('releaseyear', 'local_courseversion'), $years, ['class' => 'cv-form-select']);
        $mform->setDefault('release_year', $existing ? $existing->release_year : $currentyear);
        $mform->addHelpButton('release_year', 'releaseyear', 'local_courseversion');

        // TAS Version
        $mform->addElement('text', 'tas_version', get_string('tasversion', 'local_courseversion'), ['class' => 'cv-form-input', 'size' => 30]);
        $mform->setType('tas_version', PARAM_TEXT);
        $mform->addHelpButton('tas_version', 'tasversion', 'local_courseversion');

        // Validation Date
        $mform->addElement('date_selector', 'validation_date', get_string('validationdate', 'local_courseversion'), ['optional' => true]);
        $mform->addHelpButton('validation_date', 'validationdate', 'local_courseversion');

        // Change Summary
        $mform->addElement('textarea', 'change_summary', get_string('changesummary', 'local_courseversion'), ['class' => 'cv-form-textarea', 'rows' => 5, 'cols' => 60]);
        $mform->setType('change_summary', PARAM_TEXT);
        $mform->addHelpButton('change_summary', 'changesummary', 'local_courseversion');

        // Validation Notes
        $mform->addElement('textarea', 'validation_notes', get_string('validationnotes', 'local_courseversion'), ['class' => 'cv-form-textarea', 'rows' => 4, 'cols' => 60]);
        $mform->setType('validation_notes', PARAM_TEXT);
        $mform->addHelpButton('validation_notes', 'validationnotes', 'local_courseversion');

        $this->add_action_buttons(true, $existing ? get_string('savechanges') : get_string('createversion', 'local_courseversion'));
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        // Check unique version number for this course
        $existing = $DB->get_record_sql(
            "SELECT id FROM {local_cv_versions} WHERE courseid = ? AND version_number = ? AND id != ?",
            [$data['courseid'], $data['version_number'], $data['id']]
        );
        if ($existing) {
            $errors['version_number'] = get_string('error_versionexists', 'local_courseversion');
        }

        return $errors;
    }
}

$form = new version_form(null, [
    'existing' => $existing,
    'courseid' => $courseid,
    'suggestedversion' => $suggestedversion,
    'asqaenabled' => $asqaenabled
]);

if ($existing) {
    $formdata = clone $existing;
    $formdata->validation_date = $existing->validation_date ?: 0;
    $form->set_data($formdata);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/courseversion/versions.php', ['id' => $courseid]));
}

if ($data = $form->get_data()) {
    $now = time();
    global $USER;

    if ($id) {
        $record = $DB->get_record('local_cv_versions', ['id' => $id], '*', MUST_EXIST);
        $record->version_number = $data->version_number;
        $record->release_year = $data->release_year;
        $record->tas_version = $data->tas_version ?: null;
        $record->validation_date = $data->validation_date ?: null;
        $record->change_summary = $data->change_summary ?: null;
        $record->validation_notes = $data->validation_notes ?: null;
        $record->timemodified = $now;
        $DB->update_record('local_cv_versions', $record);

        local_courseversion_log_action('edit', $id, $courseid, 'Version updated');

        redirect(
            new moodle_url('/local/courseversion/versions.php', ['id' => $courseid]),
            get_string('versionupdated', 'local_courseversion')
        );
    } else {
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->version_number = $data->version_number;
        $record->release_year = $data->release_year;
        $record->status = 'draft';
        $record->tas_version = $data->tas_version ?: null;
        $record->validation_date = $data->validation_date ?: null;
        $record->change_summary = $data->change_summary ?: null;
        $record->validation_notes = $data->validation_notes ?: null;
        $record->locked = 0;
        $record->lock_reason = null;
        $record->supersedes_version_id = null;
        $record->releasedby = null;
        $record->releasedat = null;
        $record->createdby = $USER->id;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $newid = $DB->insert_record('local_cv_versions', $record);

        // Create assessment state record
        $state = new stdClass();
        $state->versionid = $newid;
        $state->has_enrolments = 0;
        $state->has_attempts = 0;
        $state->enrolment_count = 0;
        $state->attempt_count = 0;
        $state->last_attempt_date = null;
        $state->last_sync = null;
        $state->timecreated = $now;
        $state->timemodified = $now;
        $DB->insert_record('local_cv_assessment_state', $state);

        local_courseversion_log_action('create', $newid, $courseid, 'Version created');

        redirect(
            new moodle_url('/local/courseversion/versions.php', ['id' => $courseid]),
            get_string('versioncreated', 'local_courseversion', $data->version_number)
        );
    }
}

echo $OUTPUT->header();
?>

<div class="cv-container">
    <a href="<?php echo new moodle_url('/local/courseversion/versions.php', ['id' => $courseid]); ?>" class="cv-back-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to <?php echo s($course->course_code); ?>
    </a>

    <div class="cv-header">
        <div class="cv-header-left">
            <div class="cv-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?php if ($id): ?>
                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                    <?php else: ?>
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    <?php endif; ?>
                </svg>
            </div>
            <div>
                <h1><?php echo get_string($id ? 'editversion' : 'createversion', 'local_courseversion'); ?></h1>
                <p><?php echo s($course->course_code . ' - ' . $course->course_name); ?></p>
            </div>
        </div>
    </div>

    <?php if ($asqaenabled): ?>
    <div class="cv-asqa-guidance">
        <div class="cv-asqa-guidance-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <?php echo get_string('asqa_header', 'local_courseversion'); ?>
        </div>
        <p><?php echo get_string('asqa_change_summary', 'local_courseversion') . ' ' . get_string('asqa_tas_link', 'local_courseversion'); ?></p>
    </div>
    <?php endif; ?>

    <div class="cv-card">
        <?php $form->display(); ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
