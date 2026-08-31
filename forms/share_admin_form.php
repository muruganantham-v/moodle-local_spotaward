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
 * Share nomination to administrators form.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
            'maxbytes' => 10 * 1024 * 1024,
            'accepted_types' => ['.pdf'],
        ]);
        $mform->addRule('prdocument', null, 'required', null, 'server');

        $mform->addElement('advcheckbox', 'attachcertificates', get_string('attachcertificatestoemail', 'local_spotaward'));
        $mform->setType('attachcertificates', PARAM_BOOL);
        $mform->setDefault('attachcertificates', 1);

        $buttons = '<div class="spotaward-action-buttons spotaward-secondary-actions d-flex flex-wrap align-items-center">';
        $buttons .= '<span data-fieldtype="submit">';
        $buttons .= '<input type="submit" class="btn btn-primary" name="submitbutton" id="id_submitbutton" value="' .
            s(get_string('sendtoadmin', 'local_spotaward')) . '"' .
            ' data-spotaward-progress-message="Sharing to admin..."' .
            ' data-spotaward-success-message="Successfully shared to admin"' .
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
