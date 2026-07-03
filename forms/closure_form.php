<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\forms;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Close rejected ticket form.
 *
 * @package   local_spotaward
 */
final class closure_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $returnurl = (string)($this->_customdata['returnurl'] ?? '');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'closeticket');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('textarea', 'rejectionreason', get_string('rejectionreason', 'local_spotaward'), ['rows' => 5, 'cols' => 60]);
        $mform->setType('rejectionreason', PARAM_TEXT);
        $mform->addRule('rejectionreason', null, 'required', null, 'server');

        $mform->addElement('date_selector', 'closuredate', get_string('closuredate', 'local_spotaward'));
        $mform->addRule('closuredate', null, 'required', null, 'server');

        $buttons = '<div class="spotaward-action-buttons spotaward-secondary-actions d-flex flex-wrap align-items-center">';
        $buttons .= '<span data-fieldtype="submit">';
        $buttons .= '<input type="submit" class="btn btn-primary" name="submitbutton" id="id_submitbutton" value="' .
            s(get_string('closeticket', 'local_spotaward')) . '"' .
            ' data-spotaward-progress-message="Closing ticket..."' .
            ' data-spotaward-success-message="Ticket closed successfully"' .
            ' data-spotaward-success-submit="1">';
        $buttons .= '</span>';
        $buttons .= '<span data-fieldtype="button">';
        $buttons .= '<a class="btn btn-secondary" id="id_cancel" href="' . s($returnurl) . '">' .
            s(get_string('cancel')) . '</a>';
        $buttons .= '</span>';
        $buttons .= '</div>';
        $mform->addElement('html', $buttons);
    }
}
