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
 * Nomination rejection form.
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
 * Rejection reason form.
 *
 * @package   local_spotaward
 */
final class rejection_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('textarea', 'rejectionreason', get_string('rejectionreason', 'local_spotaward'), ['rows' => 5, 'cols' => 60]);
        $mform->setType('rejectionreason', PARAM_TEXT);
        $mform->addRule('rejectionreason', null, 'required', null, 'server');

        $this->add_action_buttons(true, get_string('saverejection', 'local_spotaward'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty(trim((string)($data['rejectionreason'] ?? '')))) {
            $errors['rejectionreason'] = get_string('required');
        }
        return $errors;
    }
}
