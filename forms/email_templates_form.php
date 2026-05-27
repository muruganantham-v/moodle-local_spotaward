<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\forms;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Email template settings form.
 *
 * @package   local_spotaward
 */
final class email_templates_form extends moodleform {
    /**
     * Email template fields.
     *
     * @return array[]
     */
    public static function fields(): array {
        return [
            [
                'subject' => 'submission_pm_subject',
                'body' => 'submission_pm_body',
                'subject_default' => 'submission_pm_subject_default',
                'body_default' => 'submission_pm_body_default',
            ],
            [
                'subject' => 'submission_ss_subject',
                'body' => 'submission_ss_body',
                'subject_default' => 'submission_ss_subject_default',
                'body_default' => 'submission_ss_body_default',
            ],
            [
                'subject' => 'submission_mentor_subject',
                'body' => 'submission_mentor_body',
                'subject_default' => 'submission_mentor_subject_default',
                'body_default' => 'submission_mentor_body_default',
            ],
            [
                'subject' => 'pm_to_ss_subject',
                'body' => 'pm_to_ss_body',
                'subject_default' => 'pm_to_ss_subject_default',
                'body_default' => 'pm_to_ss_body_default',
            ],
            [
                'subject' => 'pm_to_mentor_subject',
                'body' => 'pm_to_mentor_body',
                'subject_default' => 'pm_to_mentor_subject_default',
                'body_default' => 'pm_to_mentor_body_default',
            ],
            [
                'subject' => 'pm_to_pm_subject',
                'body' => 'pm_to_pm_body',
                'subject_default' => 'pm_to_pm_subject_default',
                'body_default' => 'pm_to_pm_body_default',
            ],
            [
                'subject' => 'ss_to_admin_subject',
                'body' => 'ss_to_admin_body',
                'subject_default' => 'ss_to_admin_subject_default',
                'body_default' => 'ss_to_admin_body_default',
            ],
            [
                'subject' => 'record_closed_subject',
                'body' => 'record_closed_body',
                'subject_default' => 'record_closed_subject_default',
                'body_default' => 'record_closed_body_default',
            ],
            [
                'subject' => 'student_certificate_subject',
                'body' => 'student_certificate_body',
                'subject_default' => 'student_certificate_subject_default',
                'body_default' => 'student_certificate_body_default',
            ],
        ];
    }

    /**
     * Cliq template fields.
     *
     * @return array[]
     */
    public static function cliq_fields(): array {
        $fields = [];
        foreach (self::fields() as $field) {
            if ($field['subject'] === 'student_certificate_subject') {
                continue;
            }
            $fields[] = [
                'subject' => 'cliq_' . $field['subject'],
                'body' => 'cliq_' . $field['body'],
                'subject_default' => 'cliq_' . $field['subject_default'],
                'body_default' => 'cliq_' . $field['body_default'],
            ];
        }

        return $fields;
    }

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('static', 'placeholders', '', get_string('emailtemplateplaceholders', 'local_spotaward'));

        $mform->addElement('header', 'email_templates_heading', get_string('emailtemplatesheading', 'local_spotaward'));
        foreach (self::fields() as $field) {
            $subject = $field['subject'];
            $body = $field['body'];

            $mform->addElement('header', $subject . '_heading', get_string($subject, 'local_spotaward'));

            $mform->addElement('text', $subject, get_string($subject, 'local_spotaward'), ['size' => 80]);
            $mform->setType($subject, PARAM_TEXT);

            $mform->addElement('textarea', $body, get_string($body, 'local_spotaward'), ['rows' => 12, 'cols' => 90]);
            $mform->setType($body, PARAM_RAW);
        }

        $mform->addElement('header', 'cliq_templates_heading', get_string('cliqtemplatesheading', 'local_spotaward'));
        foreach (self::cliq_fields() as $field) {
            $subject = $field['subject'];
            $body = $field['body'];

            $mform->addElement('header', $subject . '_heading', get_string($subject, 'local_spotaward'));

            $mform->addElement('text', $subject, get_string($subject, 'local_spotaward'), ['size' => 80]);
            $mform->setType($subject, PARAM_TEXT);

            $mform->addElement('textarea', $body, get_string($body, 'local_spotaward'), ['rows' => 10, 'cols' => 90]);
            $mform->setType($body, PARAM_RAW);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
