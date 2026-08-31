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
 * Close rejected ticket form.
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
