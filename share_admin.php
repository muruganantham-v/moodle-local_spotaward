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
 * Share nomination to admin page.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/forms/share_admin_form.php');

use local_spotaward\forms\share_admin_form;
use local_spotaward\local\api;

require_login();

$id = required_param('id', PARAM_INT);
$nomination = api::get_nomination($id);
api::require_nomination_access($nomination, $USER->id);

$isss = has_capability('local/spotaward:sstask', context_system::instance()) || api::is_ss_team((int)$USER->id);
if (!$isss && !is_siteadmin() && !api::is_assigned_maac_executive($nomination, (int)$USER->id)) {
    throw new moodle_exception('notauthorised', 'local_spotaward');
}
if ($nomination->status !== 'ssteamprogress') {
    throw new moodle_exception('invalidparameter');
}
if (!empty($nomination->adminsharedtime)) {
    throw new moodle_exception('alreadysharedtoadmin', 'local_spotaward');
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
        $filename = api::validate_admin_pr_document_upload($temppath, $filename);
        api::send_pr_document_to_admin($id, $USER->id, $temppath, $filename,
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
