<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\forms;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Reassign nomination Program Manager / MAAC Executive form.
 *
 * @package   local_spotaward
 */
final class reassign_nomination_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $programmanageroptions = $this->_customdata['programmanageroptions'] ?? [];
        $maacexecutiveoptions = $this->_customdata['maacexecutiveoptions'] ?? [];
        $currentprogrammanagerid = (int)($this->_customdata['currentprogrammanagerid'] ?? 0);
        $currentmaacexecutiveid = (int)($this->_customdata['currentmaacexecutiveid'] ?? 0);
        $userrole = $this->_customdata['userrole'] ?? 'admin';

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'reassign');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'currentprogrammanagerid', $currentprogrammanagerid);
        $mform->setType('currentprogrammanagerid', PARAM_INT);
        $mform->addElement('hidden', 'currentmaacexecutiveid', $currentmaacexecutiveid);
        $mform->setType('currentmaacexecutiveid', PARAM_INT);

        if ($userrole === 'pm') {
            $mform->addElement(
                'select',
                'programmanagerid',
                get_string('programmanager', 'local_spotaward'),
                $programmanageroptions
            );
            $mform->setType('programmanagerid', PARAM_INT);
            $mform->setDefault('programmanagerid', $currentprogrammanagerid);
            $mform->addRule('programmanagerid', null, 'required', null, 'server');

            $mform->addElement('hidden', 'maacexecutiveid', $currentmaacexecutiveid);
            $mform->setType('maacexecutiveid', PARAM_INT);

            $maacexecutivename = $maacexecutiveoptions[$currentmaacexecutiveid] ?? get_string('notassigned', 'local_spotaward');
            $mform->addElement('static', 'maacexecutive_static', get_string('maacexecutive', 'local_spotaward'), $maacexecutivename);
        } else if ($userrole === 'maac') {
            $mform->addElement('hidden', 'programmanagerid', $currentprogrammanagerid);
            $mform->setType('programmanagerid', PARAM_INT);

            $pmname = $programmanageroptions[$currentprogrammanagerid] ?? get_string('notassigned', 'local_spotaward');
            $mform->addElement('static', 'programmanager_static', get_string('programmanager', 'local_spotaward'), $pmname);

            $mform->addElement(
                'select',
                'maacexecutiveid',
                get_string('maacexecutive', 'local_spotaward'),
                $maacexecutiveoptions
            );
            $mform->setType('maacexecutiveid', PARAM_INT);
            $mform->setDefault('maacexecutiveid', $currentmaacexecutiveid);
            $mform->addRule('maacexecutiveid', null, 'required', null, 'server');
        } else {
            $mform->addElement(
                'select',
                'programmanagerid',
                get_string('programmanager', 'local_spotaward'),
                $programmanageroptions
            );
            $mform->setType('programmanagerid', PARAM_INT);
            $mform->setDefault('programmanagerid', $currentprogrammanagerid);
            $mform->addRule('programmanagerid', null, 'required', null, 'server');

            $mform->addElement(
                'select',
                'maacexecutiveid',
                get_string('maacexecutive', 'local_spotaward'),
                $maacexecutiveoptions
            );
            $mform->setType('maacexecutiveid', PARAM_INT);
            $mform->setDefault('maacexecutiveid', $currentmaacexecutiveid);
            $mform->addRule('maacexecutiveid', null, 'required', null, 'server');
        }

        $this->add_action_buttons(true, get_string('savereassignment', 'local_spotaward'));
    }

    /**
     * Validate the reassignment request.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $userrole = $this->_customdata['userrole'] ?? 'admin';

        $currentprogrammanagerid = (int)($data['currentprogrammanagerid'] ?? 0);
        $currentmaacexecutiveid = (int)($data['currentmaacexecutiveid'] ?? 0);
        $newprogrammanagerid = isset($data['programmanagerid']) ? (int)$data['programmanagerid'] : $currentprogrammanagerid;
        $newmaacexecutiveid = isset($data['maacexecutiveid']) ? (int)$data['maacexecutiveid'] : $currentmaacexecutiveid;

        if ($userrole === 'pm') {
            if (empty($newprogrammanagerid)) {
                $errors['programmanagerid'] = get_string('required');
            }
            if ($currentprogrammanagerid === $newprogrammanagerid) {
                $errors['programmanagerid'] = get_string('reassignnominationnochange', 'local_spotaward');
            }
        } else if ($userrole === 'maac') {
            if (empty($newmaacexecutiveid)) {
                $errors['maacexecutiveid'] = get_string('required');
            }
            if ($currentmaacexecutiveid === $newmaacexecutiveid) {
                $errors['maacexecutiveid'] = get_string('reassignnominationnochange', 'local_spotaward');
            }
        } else {
            if (empty($newprogrammanagerid)) {
                $errors['programmanagerid'] = get_string('required');
            }
            if (empty($newmaacexecutiveid)) {
                $errors['maacexecutiveid'] = get_string('required');
            }
            if ($currentprogrammanagerid === $newprogrammanagerid && $currentmaacexecutiveid === $newmaacexecutiveid) {
                $errors['programmanagerid'] = get_string('reassignnominationnochange', 'local_spotaward');
            }
        }

        return $errors;
    }
}
