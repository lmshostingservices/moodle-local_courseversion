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
 * Course Version Control - Add/Edit Course
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

require_login();
$context = context_system::instance();
require_capability('local/courseversion:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseversion/edit_course.php', ['id' => $id]));
$PAGE->set_title(get_string($id ? 'editcourse' : 'addcourse', 'local_courseversion'));
$PAGE->set_heading(get_string('pluginname', 'local_courseversion'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/courseversion/styles.css');

$existing = $id ? $DB->get_record('local_cv_courses', ['id' => $id], '*', MUST_EXIST) : null;
$asqaenabled = local_courseversion_asqa_enabled();

// Get Moodle courses for linking
$moodlecourses = $DB->get_records_sql("SELECT id, fullname, shortname FROM {course} WHERE id > 1 ORDER BY fullname");

class course_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $existing = $this->_customdata['existing'];
        $moodlecourses = $this->_customdata['moodlecourses'];
        $asqaenabled = $this->_customdata['asqaenabled'];

        $mform->addElement('hidden', 'id', $existing ? $existing->id : 0);
        $mform->setType('id', PARAM_INT);

        // Course Code
        $mform->addElement('text', 'course_code', get_string('coursecode', 'local_courseversion'), ['class' => 'cv-form-input', 'size' => 30]);
        $mform->setType('course_code', PARAM_ALPHANUMEXT);
        $mform->addRule('course_code', null, 'required', null, 'client');
        $mform->addHelpButton('course_code', 'coursecode', 'local_courseversion');

        // Course Name
        $mform->addElement('text', 'course_name', get_string('coursename', 'local_courseversion'), ['class' => 'cv-form-input', 'size' => 60]);
        $mform->setType('course_name', PARAM_TEXT);
        $mform->addRule('course_name', null, 'required', null, 'client');
        $mform->addHelpButton('course_name', 'coursename', 'local_courseversion');

        // Category
        $mform->addElement('text', 'category', get_string('category', 'local_courseversion'), ['class' => 'cv-form-input', 'size' => 40]);
        $mform->setType('category', PARAM_TEXT);
        $mform->addHelpButton('category', 'category', 'local_courseversion');

        // Description
        $mform->addElement('textarea', 'description', get_string('description', 'local_courseversion'), ['class' => 'cv-form-textarea', 'rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'description', 'local_courseversion');

        // Link to Moodle Course
        $courseoptions = [0 => '-- None --'];
        foreach ($moodlecourses as $c) {
            $courseoptions[$c->id] = $c->fullname . ' (' . $c->shortname . ')';
        }
        $mform->addElement('select', 'moodle_course_id', get_string('moodlecourse', 'local_courseversion'), $courseoptions, ['class' => 'cv-form-select']);
        $mform->addHelpButton('moodle_course_id', 'moodlecourse', 'local_courseversion');

        $this->add_action_buttons(true, $existing ? get_string('savechanges') : get_string('addcourse', 'local_courseversion'));
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        // Check unique course code
        $existing = $DB->get_record('local_cv_courses', ['course_code' => $data['course_code']]);
        if ($existing && $existing->id != $data['id']) {
            $errors['course_code'] = get_string('error_coursecodeexists', 'local_courseversion');
        }

        return $errors;
    }
}

$form = new course_form(null, ['existing' => $existing, 'moodlecourses' => $moodlecourses, 'asqaenabled' => $asqaenabled]);

if ($existing) {
    $form->set_data($existing);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/courseversion/index.php'));
}

if ($data = $form->get_data()) {
    $now = time();
    global $USER;

    if ($id) {
        $record = $DB->get_record('local_cv_courses', ['id' => $id], '*', MUST_EXIST);
        $record->course_code = $data->course_code;
        $record->course_name = $data->course_name;
        $record->category = $data->category ?: null;
        $record->description = $data->description ?: null;
        $record->moodle_course_id = $data->moodle_course_id ?: null;
        $record->timemodified = $now;
        $DB->update_record('local_cv_courses', $record);

        local_courseversion_log_action('edit', null, $id, 'Course updated');

        redirect(new moodle_url('/local/courseversion/versions.php', ['id' => $id]),
            get_string('courseupdated', 'local_courseversion'));
    } else {
        $record = new stdClass();
        $record->course_code = $data->course_code;
        $record->course_name = $data->course_name;
        $record->category = $data->category ?: null;
        $record->description = $data->description ?: null;
        $record->moodle_course_id = $data->moodle_course_id ?: null;
        $record->createdby = $USER->id;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $newid = $DB->insert_record('local_cv_courses', $record);

        local_courseversion_log_action('create', null, $newid, 'Course created');

        redirect(new moodle_url('/local/courseversion/versions.php', ['id' => $newid]),
            get_string('coursecreated', 'local_courseversion'));
    }
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
                    <?php if ($id): ?>
                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                    <?php else: ?>
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    <?php endif; ?>
                </svg>
            </div>
            <div>
                <h1><?php echo get_string($id ? 'editcourse' : 'addcourse', 'local_courseversion'); ?></h1>
                <p><?php echo $id ? 'Update course details' : 'Create a new course for version control'; ?></p>
            </div>
        </div>
    </div>

    <?php if ($asqaenabled): ?>
    <div class="cv-asqa-guidance">
        <div class="cv-asqa-guidance-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <?php echo get_string('asqa_header', 'local_courseversion'); ?>
        </div>
        <p>Use training product codes from training.gov.au for nationally recognised qualifications (e.g., BSBOPS201). Link to Moodle courses to automatically track enrolments and assessment attempts.</p>
    </div>
    <?php endif; ?>

    <div class="cv-card">
        <?php $form->display(); ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
