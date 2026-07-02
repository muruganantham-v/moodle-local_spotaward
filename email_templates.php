<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/forms/email_templates_form.php');

use local_spotaward\forms\email_templates_form;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/spotaward/email_templates.php');
$PAGE->set_title(get_string('emailtemplatesettings', 'local_spotaward'));
$PAGE->set_heading(get_string('emailtemplatesettings', 'local_spotaward'));
local_spotaward_require_stylesheet();
local_spotaward_require_action_success_overlay();
$PAGE->requires->js_init_code(
    "document.addEventListener('click', function(e) {
        var button = e.target.closest('.spotaward-template-reset');
        if (!button) {
            return;
        }

        e.preventDefault();

        var subject = document.getElementById(button.getAttribute('data-subject'));
        var body = document.getElementById(button.getAttribute('data-body'));

        if (subject) {
            subject.value = '';
            subject.dispatchEvent(new Event('change', {bubbles: true}));
        }
        if (body) {
            body.value = '';
            body.dispatchEvent(new Event('change', {bubbles: true}));
        }
    });"
);

$mform = new email_templates_form();

$templatefields = array_merge(email_templates_form::fields(), email_templates_form::cliq_fields());

$defaults = [];
foreach ($templatefields as $field) {
    foreach (['subject', 'body'] as $type) {
        $key = $field[$type];
        $value = get_config('local_spotaward', $key);
        if ($value === false || $value === null || $value === '') {
            $value = get_string($field[$type . '_default'], 'local_spotaward');
        }
        $defaults[$key] = $value;
    }
}
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/settings.php', ['section' => 'local_spotaward_settings']));
} else if ($data = $mform->get_data()) {
    foreach ($templatefields as $field) {
        set_config($field['subject'], $data->{$field['subject']} ?? '', 'local_spotaward');
        set_config($field['body'], $data->{$field['body']} ?? '', 'local_spotaward');
    }

    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/email_templates.php'),
        get_string('emailtemplatessaved', 'local_spotaward')
    );
}

echo $OUTPUT->header();
echo html_writer::start_div('local-spotaward-app');
echo html_writer::start_div('spotaward-shell');
echo html_writer::link(
    new moodle_url('/admin/settings.php', ['section' => 'local_spotaward_settings']),
    get_string('back'),
    ['class' => 'btn btn-secondary mb-3']
);
echo html_writer::tag('h3', get_string('emailtemplatesettings', 'local_spotaward'), ['class' => 'spotaward-section-title']);
$mform->display();
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
