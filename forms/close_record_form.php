<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\forms;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Close nomination record form.
 *
 * @package   local_spotaward
 */
final class close_record_form extends moodleform {
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

        $mform->addElement('date_selector', 'closuredate', get_string('closuredate', 'local_spotaward'));
        $mform->addRule('closuredate', null, 'required', null, 'server');

        $buttons = '<div class="spotaward-action-buttons spotaward-secondary-actions d-flex flex-wrap align-items-center">';
        $buttons .= '<span data-fieldtype="submit">';
        $buttons .= '<input type="submit" class="btn btn-primary" name="submitbutton" id="id_submitbutton" value="' .
            s(get_string('closerecord', 'local_spotaward')) . '"' .
            ' data-spotaward-progress-message="Closing nomination..."' .
            ' data-spotaward-success-message="Nomination closed successfully"' .
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
