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
        $studentoptions = $this->_customdata['studentoptions'] ?? [];
        $programmanageroptions = $this->_customdata['programmanageroptions'] ?? [];
        $maacexecutiveoptions = $this->_customdata['maacexecutiveoptions'] ?? [];
        $selectedprogrammanagerid = $this->_customdata['selectedprogrammanagerid'] ?? 0;
        $selectedmaacexecutiveid = $this->_customdata['selectedmaacexecutiveid'] ?? 0;
        $selectedcourseid = $this->_customdata['selectedcourseid'] ?? 0;
        $selectedcoursename = $this->_customdata['selectedcoursename'] ?? '';
        $selectedcourseshortname = $this->_customdata['selectedcourseshortname'] ?? '';
        $hasdraftentries = !empty($this->_customdata['hasdraftentries']);
        $draftcontext = $this->_customdata['draftcontext'] ?? [];
        $coursedataset = $this->_customdata['coursedataset'] ?? [];
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
        if (!empty($fielderrors['programmanagerid'])) {
            $mform->addElement('html',
                '<div class="spotaward-field-error-text">' . s($fielderrors['programmanagerid']) . '</div>');
        } else if (!empty($selectedcoursename) && empty($programmanageroptions)) {
            $mform->addElement('html',
                '<div class="spotaward-field-error-text">' .
                s(get_string('programmanagercoursewarning', 'local_spotaward', $selectedcoursename)) .
                '</div>');
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
        if (!empty($fielderrors['maacexecutiveid'])) {
            $mform->addElement('html',
                '<div class="spotaward-field-error-text">' . s($fielderrors['maacexecutiveid']) . '</div>');
        } else if (!empty($selectedcoursename) && empty($maacexecutiveoptions)) {
            $mform->addElement('html',
                '<div class="spotaward-field-error-text">' .
                s(get_string('maacexecutivecoursewarning', 'local_spotaward', $selectedcoursename)) .
                '</div>');
        }
        $mform->addElement('html', '</div></div>');

        $hidesection = empty($selectedcoursename) ? ' style="display:none;"' : '';
        $mform->addElement('html', '<div class="spotaward-form-section"' . $hidesection . ' id="spotaward-award-section"><div class="spotaward-form-section-header">Award Categories</div><div class="spotaward-form-section-body">');

        $awardfieldmap = [];
        $mform->addElement('hidden', 'awardfieldmap', '');
        $mform->setType('awardfieldmap', PARAM_RAW_TRIMMED);
        $awardcategories = [];
        if (!empty($selectedcoursename)) {
            $awardcategories = array_values(constants::award_categories_for_course($selectedcourseshortname, $selectedcoursename));
        }

        if (!empty($awardcategories)) {
            foreach ($awardcategories as $index => $category) {
                $fieldname = 'awardstudents_' . $index;
                $awardfieldmap[$fieldname] = $category;
                $attributes = [
                    'multiple' => 'multiple',
                    'size' => 8,
                    'class' => 'spotaward-award-students',
                ];
                $mform->addElement('select', $fieldname, $category, $studentoptions, $attributes);
                $mform->setType($fieldname, PARAM_INT);
                if (!empty($draftcontext['awardallocations'][$category])) {
                    $mform->setDefault($fieldname, array_map('intval', (array)$draftcontext['awardallocations'][$category]));
                }
            }
        } else {
            $mform->addElement('static', 'awardcategoriesui', '', '');
            $mform->addElement('html', '<div id="spotaward-award-fields"></div>');
        }
        $mform->setDefault('awardfieldmap', json_encode($awardfieldmap));
        $mform->addElement('html', '</div></div>');

        $formattrs = [
            'data-course-dataset' => rawurlencode(json_encode($coursedataset)),
        ];
        if ($hasdraftentries) {
            $formattrs['data-has-draft-lock'] = '1';
            $formattrs['data-draft-courseid'] = (string)($draftcontext['courseid'] ?? 0);
            $formattrs['data-draft-modulename'] = (string)($draftcontext['modulename'] ?? '');
            $formattrs['data-draft-awardpayload'] = rawurlencode(json_encode($draftcontext['awardallocations'] ?? []));
            $formattrs['data-draft-professional'] = (string)($draftcontext['professional'] ?? '');
            $formattrs['data-draft-programmanagerid'] = (string)($draftcontext['programmanagerid'] ?? 0);
            $formattrs['data-draft-maacexecutiveid'] = (string)($draftcontext['maacexecutiveid'] ?? 0);
        }
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
