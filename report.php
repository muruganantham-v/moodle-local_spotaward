<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_spotaward\local\api;

require_login();

$itemid = optional_param('itemid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$activitytype = optional_param('activitytype', '', PARAM_ALPHA);

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/spotaward/report.php', [
    'itemid' => $itemid,
    'courseid' => $courseid,
    'activitytype' => $activitytype,
]);
$PAGE->set_title(get_string('courseperformancereport', 'local_spotaward'));
$PAGE->set_heading(get_string('courseperformancereport', 'local_spotaward'));
local_spotaward_require_stylesheet();

$courses = api::get_report_courses_for_user($USER->id);

if ($itemid > 0) {
    $item = $DB->get_record('spotaward_nomination_items', ['id' => $itemid], '*', MUST_EXIST);
    $nomination = api::get_nomination((int)$item->nominationid);
    api::require_nomination_access($nomination, $USER->id);

    $student = core_user::get_user($item->studentid, '*', MUST_EXIST);
    $course = get_course($nomination->courseid);
    $report = api::get_student_report($student->id, $course->id, $activitytype);
    $backurl = new moodle_url('/local/spotaward/submission.php', ['id' => $item->nominationid]);
} else {
    if (empty($courses)) {
        throw new moodle_exception('notauthorised', 'local_spotaward');
    }

    if ($courseid <= 0) {
        $courseid = (int)array_key_first($courses);
    }

    if (!api::can_access_report_course($USER->id, $courseid)) {
        throw new moodle_exception('notauthorised', 'local_spotaward');
    }

    $course = get_course($courseid);
    $report = api::get_course_report($courseid, $activitytype);
    $backurl = new moodle_url('/local/spotaward/index.php');
}

echo $OUTPUT->header();
echo html_writer::start_div('local-spotaward-app');
echo html_writer::start_div('spotaward-shell');

echo html_writer::div(
    html_writer::link($backurl, '&larr; ' . get_string('back', 'local_spotaward'), ['class' => 'btn btn-secondary']),
    'spotaward-back-link'
);

echo html_writer::tag('h3', get_string('courseperformancereport', 'local_spotaward'), ['class' => 'spotaward-section-title']);

echo html_writer::start_div('spotaward-card');
echo html_writer::start_div('spotaward-card-header');
echo html_writer::tag('strong', get_string('courseperformancereport', 'local_spotaward'));
if ($itemid <= 0) {
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('viewcourselink', 'local_spotaward'),
        ['class' => 'spotaward-btn-view spotaward-btn-sm', 'target' => '_blank', 'style' => 'float: right;']
    );
}
echo html_writer::end_div();
echo html_writer::start_div('spotaward-card-body');
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-0 spotaward-filter-form']);
if ($itemid > 0) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'itemid', 'value' => $itemid]);
} else {
    echo html_writer::label(get_string('course', 'local_spotaward'), 'id_courseid', false, ['class' => 'mr-2']);
    echo html_writer::select($courses, 'courseid', $course->id, false, ['id' => 'id_courseid', 'class' => 'custom-select d-inline-block w-auto mr-3']);
}
echo html_writer::label(get_string('activitytype', 'local_spotaward'), 'id_activitytype', false, ['class' => 'mr-2']);
echo html_writer::select(api::get_report_activity_type_options(), 'activitytype', $activitytype, false,
    ['id' => 'id_activitytype', 'class' => 'custom-select d-inline-block w-auto mr-3']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('filter')]);
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

if ($itemid > 0) {
    echo local_spotaward_render_student_report_content($student, $course, $report);
} else {
    echo local_spotaward_render_course_report_content($course, $report);
}

echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
