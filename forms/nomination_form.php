<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\forms;

use local_spotaward\local\constants;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Spot award nomination form.
 *
 * @package   local_spotaward
 */
final class nomination_form extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $mform->disable_form_change_checker();
        $courseoptions = $this->_customdata['courseoptions'] ?? [];
        $programmanageroptions = $this->_customdata['programmanageroptions'] ?? [];
        $maacexecutiveoptions = $this->_customdata['maacexecutiveoptions'] ?? [];
        $selectedprogrammanagerid = $this->_customdata['selectedprogrammanagerid'] ?? 0;
        $selectedmaacexecutiveid = $this->_customdata['selectedmaacexecutiveid'] ?? 0;
        $selectedcourseid = $this->_customdata['selectedcourseid'] ?? 0;
        $hasdraftentries = !empty($this->_customdata['hasdraftentries']);
        $draftcontext = $this->_customdata['draftcontext'] ?? [];
        $draftsavedat = (int)($this->_customdata['draftsavedat'] ?? 0);
        $fielderrors = $this->_customdata['fielderrors'] ?? [];

        $mform->addElement('html', '<div class="spotaward-form-section"><div class="spotaward-form-section-header">Course Details</div><div class="spotaward-form-section-body">');
        $mform->addElement('hidden', 'courseid', $selectedcourseid);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement(
            'select',
            'coursepicker',
            get_string('course', 'local_spotaward'),
            [0 => get_string('selectcourse', 'local_spotaward')] + $courseoptions
        );
        $mform->setType('coursepicker', PARAM_INT);
        $mform->setDefault('coursepicker', $selectedcourseid);

        $mform->addElement('hidden', 'modulename', $draftcontext['modulename'] ?? '');
        $mform->setType('modulename', PARAM_TEXT);

        $mform->addElement(
            'select',
            'professional',
            get_string('professional', 'local_spotaward'),
            [0 => get_string('selectprofessional', 'local_spotaward')] + constants::professionals()
        );
        $mform->setType('professional', PARAM_TEXT);
        $mform->setDefault('professional', $draftcontext['professional'] ?? 0);

        $programmanagerattributes = [];
        if (!empty($fielderrors['programmanagerid'])) {
            $programmanagerattributes['class'] = 'spotaward-field-error';
        }

        $mform->addElement(
            'select',
            'programmanagerid',
            get_string('programmanager', 'local_spotaward'),
            [0 => get_string('selectprogrammanager', 'local_spotaward')] + $programmanageroptions,
            $programmanagerattributes
        );
        $mform->setType('programmanagerid', PARAM_INT);
        if (!empty($draftcontext['programmanagerid'])) {
            $mform->setDefault('programmanagerid', (int)$draftcontext['programmanagerid']);
        } else if (!empty($selectedprogrammanagerid)) {
            $mform->setDefault('programmanagerid', (int)$selectedprogrammanagerid);
        }
        $mform->addHelpButton('programmanagerid', 'pmoverridehelp', 'local_spotaward');
        $mform->addElement('html',
            '<div id="spotaward-pm-noassign" class="alert alert-warning mt-1" style="display:none;">' .
            s(get_string('noprogrammanagerassigned', 'local_spotaward')) . '</div>');
        if (!empty($fielderrors['programmanagerid'])) {
            $mform->addElement('html',
                '<div class="spotaward-field-error-text">' . s($fielderrors['programmanagerid']) . '</div>');
        }

        $maacattributes = [];
        if (!empty($fielderrors['maacexecutiveid'])) {
            $maacattributes['class'] = 'spotaward-field-error';
        }

        $mform->addElement(
            'select',
            'maacexecutiveid',
            get_string('maacexecutive', 'local_spotaward'),
            [0 => get_string('selectmaacexecutive', 'local_spotaward')] + $maacexecutiveoptions,
            $maacattributes
        );
        $mform->setType('maacexecutiveid', PARAM_INT);
        if (!empty($draftcontext['maacexecutiveid'])) {
            $mform->setDefault('maacexecutiveid', (int)$draftcontext['maacexecutiveid']);
        } else if (!empty($selectedmaacexecutiveid)) {
            $mform->setDefault('maacexecutiveid', (int)$selectedmaacexecutiveid);
        }
        $mform->addHelpButton('maacexecutiveid', 'maacoverridehelp', 'local_spotaward');
        $mform->addElement('html',
            '<div id="spotaward-maac-noassign" class="alert alert-warning mt-1" style="display:none;">' .
            s(get_string('nomaacexecutiveassigned', 'local_spotaward')) . '</div>');
        if (!empty($fielderrors['maacexecutiveid'])) {
            $mform->addElement('html',
                '<div class="spotaward-field-error-text">' . s($fielderrors['maacexecutiveid']) . '</div>');
        }
        $mform->addElement('html', '</div></div>');

        $mform->addElement('html', '<div class="spotaward-form-section" style="display:none;" id="spotaward-award-section"><div class="spotaward-form-section-header">Award Categories</div><div class="spotaward-form-section-body">');

        $mform->addElement('hidden', 'awardfieldmap', '');
        $mform->setType('awardfieldmap', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'awardcategoriesui', '', '');
        $mform->addElement('html', '<div id="spotaward-award-fields"></div>');
        $mform->addElement('html', '</div></div>');

        $formattrs = [];
        if ($hasdraftentries) {
            $formattrs['data-has-draft-lock'] = '1';
            $formattrs['data-draft-courseid'] = (string)($draftcontext['courseid'] ?? 0);
            $formattrs['data-draft-modulename'] = (string)($draftcontext['modulename'] ?? '');
            $formattrs['data-draft-awardpayload'] = rawurlencode(json_encode($draftcontext['awardallocations'] ?? []));
            $formattrs['data-draft-professional'] = (string)($draftcontext['professional'] ?? '');
            $formattrs['data-draft-programmanagerid'] = (string)($draftcontext['programmanagerid'] ?? 0);
            $formattrs['data-draft-maacexecutiveid'] = (string)($draftcontext['maacexecutiveid'] ?? 0);
        }
        $formattrs['data-draft-saved-at'] = (string)$draftsavedat;
        $mform->updateAttributes($formattrs);

        $disableactions = !$hasdraftentries ? ' disabled="disabled"' : '';
        $buttonrow = '<div class="spotaward-action-buttons d-flex flex-wrap align-items-center">';
        $buttonrow .= '<span data-fieldtype="submit">';
        $buttonrow .= '<input type="submit" class="btn btn-primary" name="previewdraft" id="id_previewdraft" value="' .
            s(get_string('previewnominations', 'local_spotaward')) . '">';
        $buttonrow .= '</span>';
        $buttonrow .= '<span data-fieldtype="submit">';
        $buttonrow .= '<input type="submit" class="btn btn-primary" name="cleardraft" id="id_cleardraft" value="' .
            s(get_string('cleardraft', 'local_spotaward')) . '"' . $disableactions . '>';
        $buttonrow .= '</span>';
        $buttonrow .= '<span data-fieldtype="submit">';
        $buttonrow .= '<input type="submit" class="btn btn-primary" name="submitnominations" id="id_submitnominations" value="' .
            s(get_string('submitnominations', 'local_spotaward')) . '"' . $disableactions . '>';
        $buttonrow .= '</span>';
        $buttonrow .= '<span id="spotaward-draft-status" class="spotaward-draft-status" aria-live="polite"></span>';
        $buttonrow .= '</div>';
        $mform->addElement('html', $buttonrow);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['previewdraft'])) {
            if (empty($data['courseid'])) {
                $errors['courseid'] = get_string('coursefieldrequired', 'local_spotaward');
            }
            $awardfieldmap = json_decode((string)($data['awardfieldmap'] ?? ''), true);
            $hasstudents = false;
            if (is_array($awardfieldmap)) {
                foreach ($awardfieldmap as $fieldname => $category) {
                    if (!empty($data[$fieldname])) {
                        $hasstudents = true;
                        break;
                    }
                }
            }

            if (!$hasstudents) {
                $errors['awardcategoriesui'] = get_string('awardcategoryrequired', 'local_spotaward');
            }
            if (empty($data['professional'])) {
                $errors['professional'] = get_string('professionalrequired', 'local_spotaward');
            }
            if (empty($data['programmanagerid'])) {
                $errors['programmanagerid'] = get_string('programmanagerrequired', 'local_spotaward');
            }
            if (empty($data['maacexecutiveid'])) {
                $errors['maacexecutiveid'] = get_string('maacexecutiverequired', 'local_spotaward');
            }
        }

        return $errors;
    }
}
