<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/forms/close_record_form.php');

use local_spotaward\forms\close_record_form;
use local_spotaward\local\api;

require_login();

$id = required_param('id', PARAM_INT);
$nomination = api::get_nomination($id);
api::require_nomination_access($nomination, $USER->id);

if (!api::is_ss_team($USER->id) && !is_siteadmin()) {
    throw new moodle_exception('notauthorised', 'local_spotaward');
}
if ($nomination->status !== 'ssteamprogress') {
    throw new moodle_exception('invalidparameter');
}

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/spotaward/close_record.php', ['id' => $id]);
$PAGE->set_title(get_string('closerecord', 'local_spotaward'));
$PAGE->set_heading(get_string('closerecord', 'local_spotaward'));
local_spotaward_require_stylesheet();
local_spotaward_require_action_success_overlay();

$mform = new close_record_form(null, [
    'returnurl' => (new moodle_url('/local/spotaward/index.php', ['view' => 'ssteam']))->out(false),
]);
$mform->set_data([
    'id' => $id,
    'closuredate' => time(),
]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/spotaward/index.php', ['view' => 'ssteam']));
} else if ($data = $mform->get_data()) {
    api::close_nomination_record($id, $USER->id, (int)$data->closuredate);
    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/index.php', ['view' => 'ssteam']),
        get_string('recordclosed', 'local_spotaward')
    );
}

echo $OUTPUT->header();
echo html_writer::start_div('local-spotaward-app');
echo html_writer::link(
    new moodle_url('/local/spotaward/index.php', ['view' => 'ssteam']),
    get_string('back'),
    ['class' => 'btn btn-secondary mb-3']
);
echo html_writer::tag('h3', get_string('closerecord', 'local_spotaward'), ['class' => 'mb-3']);
$mform->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
