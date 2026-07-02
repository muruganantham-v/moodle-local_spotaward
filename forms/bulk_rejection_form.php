<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\forms;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Bulk rejection reason form.
 *
 * @package   local_spotaward
 */
final class bulk_rejection_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $selectedcount = (int)($this->_customdata['selectedcount'] ?? 0);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        $mform->addElement('hidden', 'selecteditemscsv');
        $mform->setType('selecteditemscsv', PARAM_TEXT);

        $mform->addElement('static', 'selectedcountlabel', '',
            get_string('bulkselectedstudentscount', 'local_spotaward', $selectedcount));

        $mform->addElement('textarea', 'rejectionreason', get_string('rejectionreason', 'local_spotaward'),
            ['rows' => 5, 'cols' => 60]);
        $mform->setType('rejectionreason', PARAM_TEXT);
        $mform->addRule('rejectionreason', null, 'required', null, 'server');

        $this->add_action_buttons(true, get_string('saverejection', 'local_spotaward'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty(trim((string)($data['rejectionreason'] ?? '')))) {
            $errors['rejectionreason'] = get_string('required');
        }
        if (empty(trim((string)($data['selecteditemscsv'] ?? '')))) {
            $errors['rejectionreason'] = get_string('selectstudentsforbulkreview', 'local_spotaward');
        }
        return $errors;
    }
}
