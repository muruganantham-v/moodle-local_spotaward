<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/forms/share_admin_form.php');

use local_spotaward\forms\share_admin_form;
use local_spotaward\local\api;

require_login();

$id = required_param('id', PARAM_INT);
$nomination = api::get_nomination($id);
api::require_nomination_access($nomination, $USER->id);

if (!api::is_assigned_maac_executive($nomination, (int)$USER->id) && !is_siteadmin()) {
    throw new moodle_exception('notauthorised', 'local_spotaward');
}
if ($nomination->status !== 'ssteamprogress') {
    throw new moodle_exception('invalidparameter');
}

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/spotaward/share_admin.php', ['id' => $id]);
$PAGE->set_title(get_string('sharetoadmin', 'local_spotaward'));
$PAGE->set_heading(get_string('sharetoadmin', 'local_spotaward'));
local_spotaward_require_stylesheet();
local_spotaward_require_action_success_overlay();

$mform = new share_admin_form(null, [
    'id' => $id,
    'returnurl' => (new moodle_url('/local/spotaward/submission.php', ['id' => $id]))->out(false),
]);
$mform->set_data(['id' => $id]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/spotaward/submission.php', ['id' => $id]));
} else if ($data = $mform->get_data()) {
    $filename = $mform->get_new_filename('prdocument');
    $tempdir = make_temp_directory('local_spotaward');
    $temppath = tempnam($tempdir, 'spotawardpr');
    if ($filename === false || $temppath === false || !$mform->save_file('prdocument', $temppath, true)) {
        throw new moodle_exception('invalidparameter');
    }

    try {
        api::send_pr_document_to_admin($id, $USER->id, $temppath, clean_filename($filename),
            !empty($data->attachcertificates));
    } finally {
        if (is_file($temppath)) {
            @unlink($temppath);
        }
    }

    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
        get_string('sharedtoadminsuccess', 'local_spotaward')
    );
}

echo $OUTPUT->header();
echo html_writer::start_div('local-spotaward-app');
echo html_writer::start_div('spotaward-shell');

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
        '&larr; ' . get_string('back', 'local_spotaward'),
        ['class' => 'btn btn-secondary']
    ),
    'spotaward-back-link'
);

echo html_writer::tag('h3', get_string('sharetoadmin', 'local_spotaward'), ['class' => 'spotaward-section-title']);

echo html_writer::start_div('spotaward-card mb-4');
echo html_writer::start_div('spotaward-card-header');
echo html_writer::tag('strong', get_string('submissiondetail', 'local_spotaward'));
echo html_writer::end_div();
echo html_writer::start_div('spotaward-card-body');
echo html_writer::start_div('spotaward-meta');
$course = get_course($nomination->courseid);
$nominator = \core_user::get_user($nomination->nominatorid);
$programmanager = \core_user::get_user($nomination->programmanagerid);
$metafields = [
    get_string('mentor', 'local_spotaward') => fullname($nominator),
    get_string('programmanager', 'local_spotaward') => fullname($programmanager),
    get_string('course', 'local_spotaward') => format_string($course->fullname),
    get_string('module', 'local_spotaward') => s($nomination->modulename),
    get_string('professional', 'local_spotaward') => s($nomination->professional ?? ''),
];
foreach ($metafields as $label => $value) {
    echo html_writer::div(
        html_writer::tag('span', $label, ['class' => 'spotaward-meta-label']) .
        html_writer::div($value, 'spotaward-meta-value'),
        'spotaward-meta-item'
    );
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('spotaward-card');
echo html_writer::start_div('spotaward-card-header');
echo html_writer::tag('strong', get_string('uploadprdocument', 'local_spotaward'));
echo html_writer::end_div();
echo html_writer::start_div('spotaward-card-body');
$mform->display();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
