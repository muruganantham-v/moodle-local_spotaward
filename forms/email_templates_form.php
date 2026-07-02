<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\forms;

use html_writer;
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
                'group' => 'submission',
                'subject' => 'submission_pm_subject',
                'body' => 'submission_pm_body',
                'subject_default' => 'submission_pm_subject_default',
                'body_default' => 'submission_pm_body_default',
                'placeholders' => [
                    'recipient_name', 'program_manager_name', 'course', 'module', 'professional',
                    'mentor_name', 'maac_executive_name', 'nominator_name', 'total_students',
                    'description', 'award_summary', 'award_summary_html', 'moodle_link',
                ],
            ],
            [
                'group' => 'submission',
                'subject' => 'submission_ss_subject',
                'body' => 'submission_ss_body',
                'subject_default' => 'submission_ss_subject_default',
                'body_default' => 'submission_ss_body_default',
                'placeholders' => [
                    'recipient_name', 'course', 'module', 'professional', 'mentor_name',
                    'program_manager_name', 'maac_executive_name', 'nominator_name',
                    'total_students', 'award_summary', 'award_summary_html', 'moodle_link',
                ],
            ],
            [
                'group' => 'submission',
                'subject' => 'submission_mentor_subject',
                'body' => 'submission_mentor_body',
                'subject_default' => 'submission_mentor_subject_default',
                'body_default' => 'submission_mentor_body_default',
                'placeholders' => [
                    'recipient_name', 'mentor_name', 'course', 'module', 'professional',
                    'program_manager_name', 'maac_executive_name', 'total_students',
                    'award_summary', 'award_summary_html', 'moodle_link',
                ],
            ],
            [
                'group' => 'pmreview',
                'subject' => 'pm_to_ss_subject',
                'body' => 'pm_to_ss_body',
                'subject_default' => 'pm_to_ss_subject_default',
                'body_default' => 'pm_to_ss_body_default',
                'placeholders' => [
                    'recipient_name', 'course', 'module', 'professional', 'mentor_name',
                    'program_manager_name', 'total_students', 'description', 'moodle_link',
                ],
            ],
            [
                'group' => 'pmreview',
                'subject' => 'pm_to_mentor_subject',
                'body' => 'pm_to_mentor_body',
                'subject_default' => 'pm_to_mentor_subject_default',
                'body_default' => 'pm_to_mentor_body_default',
                'placeholders' => [
                    'recipient_name', 'mentor_name', 'course', 'module', 'professional',
                    'program_manager_name', 'maac_executive_name', 'total_students',
                    'status', 'decision', 'decision_message', 'pm_comments', 'moodle_link',
                ],
            ],
            [
                'group' => 'pmreview',
                'subject' => 'pm_to_pm_subject',
                'body' => 'pm_to_pm_body',
                'subject_default' => 'pm_to_pm_subject_default',
                'body_default' => 'pm_to_pm_body_default',
                'placeholders' => [
                    'recipient_name', 'mentor_name', 'course', 'module', 'professional',
                    'program_manager_name', 'maac_executive_name', 'total_students',
                    'status', 'decision', 'decision_message', 'pm_comments', 'moodle_link',
                ],
            ],
            [
                'group' => 'adminhandover',
                'subject' => 'ss_to_admin_subject',
                'body' => 'ss_to_admin_body',
                'subject_default' => 'ss_to_admin_subject_default',
                'body_default' => 'ss_to_admin_body_default',
                'placeholders' => [
                    'recipient_name', 'course', 'module', 'professional', 'mentor_name',
                    'total_students', 'certificate_mode', 'moodle_link',
                ],
            ],
            [
                'group' => 'closure',
                'subject' => 'record_closed_subject',
                'body' => 'record_closed_body',
                'subject_default' => 'record_closed_subject_default',
                'body_default' => 'record_closed_body_default',
                'placeholders' => [
                    'recipient_name', 'course', 'module', 'professional', 'mentor_name',
                    'program_manager_name', 'total_students', 'closure_date', 'closed_by',
                    'moodle_link',
                ],
            ],
            [
                'group' => 'certificate',
                'subject' => 'student_certificate_subject',
                'body' => 'student_certificate_body',
                'subject_default' => 'student_certificate_subject_default',
                'body_default' => 'student_certificate_body_default',
                'placeholders' => [
                    'student_name', 'student_firstname', 'student_lastname', 'student_email',
                    'student_username', 'course', 'module', 'professional', 'award_category',
                    'award_description', 'certificate_filename',
                ],
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
                'group' => $field['group'],
                'subject' => 'cliq_' . $field['subject'],
                'body' => 'cliq_' . $field['body'],
                'subject_default' => 'cliq_' . $field['subject_default'],
                'body_default' => 'cliq_' . $field['body_default'],
                'placeholders' => $field['placeholders'],
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

        $mform->addElement('static', 'templatedefaultnote', '',
            get_string('emailtemplatedefaultnote', 'local_spotaward'));

        $mform->addElement('header', 'email_templates_heading', get_string('emailtemplatesheading', 'local_spotaward'));
        self::add_template_fields($mform, self::fields(), 'email');

        $mform->addElement('header', 'cliq_templates_heading', get_string('cliqtemplatesheading', 'local_spotaward'));
        self::add_template_fields($mform, self::cliq_fields(), 'cliq');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Add grouped template fields to the form.
     *
     * @param \MoodleQuickForm $mform
     * @param array $fields
     * @param string $channel
     * @return void
     */
    private static function add_template_fields($mform, array $fields, string $channel): void {
        $currentgroup = null;
        foreach ($fields as $field) {
            if ($field['group'] !== $currentgroup) {
                $currentgroup = $field['group'];
                $mform->addElement('html', html_writer::tag(
                    'h4',
                    get_string('templategroup_' . $currentgroup, 'local_spotaward'),
                    ['class' => 'mt-4 mb-3']
                ));
            }

            $subject = $field['subject'];
            $body = $field['body'];

            $mform->addElement('header', $subject . '_heading', get_string($subject, 'local_spotaward'));

            if (get_string_manager()->string_exists($subject . '_desc', 'local_spotaward')) {
                $mform->addElement('static', $subject . '_desc_static', '',
                    get_string($subject . '_desc', 'local_spotaward'));
            }

            $mform->addElement('html', self::build_placeholder_reference_html($field['placeholders'] ?? []));

            $mform->addElement('text', $subject, get_string($subject, 'local_spotaward'), ['size' => 80]);
            $mform->setType($subject, PARAM_TEXT);

            $rows = $channel === 'email' ? 12 : 10;
            $mform->addElement('textarea', $body, get_string($body, 'local_spotaward'), ['rows' => $rows, 'cols' => 90]);
            $mform->setType($body, PARAM_RAW);

            $mform->addElement('html', self::build_reset_button_html($field));
        }
    }

    /**
     * Build placeholder reference panel HTML.
     *
     * @param array $placeholders
     * @return string
     */
    private static function build_placeholder_reference_html(array $placeholders): string {
        $items = array_map(static function(string $placeholder): string {
            return html_writer::tag(
                'span',
                '{{' . $placeholder . '}}',
                ['class' => 'badge badge-light border mr-2 mb-2 p-2']
            );
        }, $placeholders);

        return html_writer::div(
            html_writer::tag('strong', get_string('availablevariables', 'local_spotaward')) . '<br>' .
            implode('', $items),
            'alert alert-light mb-3'
        );
    }

    /**
     * Build reset button HTML for a template override.
     *
     * @param array $field
     * @return string
     */
    private static function build_reset_button_html(array $field): string {
        $button = html_writer::tag(
            'button',
            get_string('resettoplugindefault', 'local_spotaward'),
            [
                'type' => 'button',
                'class' => 'btn btn-outline-secondary btn-sm spotaward-template-reset',
                'data-subject' => 'id_' . $field['subject'],
                'data-body' => 'id_' . $field['body'],
            ]
        );

        $help = html_writer::tag('span', get_string('templateoverridehelp', 'local_spotaward'), ['class' => 'ml-2']);

        return html_writer::div($button . $help, 'mb-4');
    }
}
