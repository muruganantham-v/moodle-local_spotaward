<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\forms;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Upload PR document before sharing to Admin.
 *
 * @package   local_spotaward
 */
final class share_admin_form extends moodleform {
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

        $mform->addElement('filepicker', 'prdocument', get_string('uploadprdocument', 'local_spotaward'), null, [
            'maxbytes' => 0,
            'accepted_types' => ['.pdf', '.doc', '.docx'],
        ]);
        $mform->addRule('prdocument', null, 'required', null, 'server');

        $mform->addElement('advcheckbox', 'attachcertificates', get_string('attachcertificatestoemail', 'local_spotaward'));
        $mform->setType('attachcertificates', PARAM_BOOL);
        $mform->setDefault('attachcertificates', 1);

        $buttons = '<div class="spotaward-action-buttons spotaward-secondary-actions d-flex flex-wrap align-items-center">';
        $buttons .= '<span data-fieldtype="submit">';
        $buttons .= '<input type="submit" class="btn btn-primary" name="submitbutton" id="id_submitbutton" value="' .
            s(get_string('sendtoadmin', 'local_spotaward')) . '">';
        $buttons .= '</span>';
        $buttons .= '<span data-fieldtype="button">';
        $buttons .= '<a class="btn btn-secondary" id="id_cancel" href="' . s($returnurl) . '">' .
            s(get_string('cancel')) . '</a>';
        $buttons .= '</span>';
        $buttons .= '</div>';
        $mform->addElement('html', $buttons);
    }
}
