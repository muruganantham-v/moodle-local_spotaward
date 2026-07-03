<?php
// This file is part of Moodle - http://moodle.org/

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_spotaward\local\api;
use local_spotaward\local\constants;

require_login();

$CFG->debugdisplay = 0;
$CFG->debug        = 0;

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/spotaward/ajax.php');

header('Content-Type: application/json; charset=utf-8');

$action = optional_param('action', 'courseoptions', PARAM_ALPHA);

if ($action === 'studentreport') {
    require_sesskey();

    $itemid = required_param('itemid', PARAM_INT);
    $item = $DB->get_record('spotaward_nomination_items', ['id' => $itemid], '*', MUST_EXIST);
    $nomination = api::get_nomination((int)$item->nominationid);
    api::require_nomination_access($nomination, $USER->id);

    $student = core_user::get_user($item->studentid, '*', MUST_EXIST);
    $course = get_course($nomination->courseid);
    $report = api::get_student_report($student->id, $nomination->courseid);

    echo json_encode([
        'html' => local_spotaward_render_student_report_content($student, $course, $report),
    ]);
    die();
}

if ($action === 'autosavedraft') {
    require_sesskey();

    if (!api::user_can_access($USER->id)) {
        http_response_code(403);
        echo json_encode([
            'saved' => false,
            'message' => 'Access denied',
        ]);
        die();
    }

    try {
        $data = (object)[
            'courseid' => optional_param('courseid', 0, PARAM_INT),
            'modulename' => optional_param('modulename', '', PARAM_TEXT),
            'awardpayload' => optional_param('awardpayload', '', PARAM_RAW_TRIMMED),
            'professional' => optional_param('professional', '', PARAM_TEXT),
            'programmanagerid' => optional_param('programmanagerid', 0, PARAM_INT),
            'maacexecutiveid' => optional_param('maacexecutiveid', 0, PARAM_INT),
        ];

        $state = api::save_draft_form_state($data, $USER->id);
        echo json_encode([
            'saved' => !empty($state),
            'cleared' => empty($state),
            'timesaved' => (int)($state['timesaved'] ?? 0),
        ]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'saved' => false,
            'message' => $e->getMessage(),
        ]);
    }
    die();
}

$courseid = required_param('courseid', PARAM_INT);

if ($courseid <= 0) {
    echo json_encode(['error' => 'Invalid course ID', 'students' => [], 'programmanagers' => [], 'categories' => []]);
    die();
}

if (!api::user_can_access($USER->id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied', 'students' => [], 'programmanagers' => [], 'categories' => []]);
    die();
}

if (!is_siteadmin() && !api::is_manager($USER->id) && !api::is_ss_team($USER->id)) {
    $coursecontext = context_course::instance($courseid);
    $canaccess = api::can_nominate_in_course($USER->id, $courseid) ||
                 has_capability('local/spotaward:nominate', $coursecontext) ||
                 has_capability('local/spotaward:review', $coursecontext);

    if (!$canaccess) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Access denied to this course context',
            'students' => [],
            'programmanagers' => [],
            'maacexecutives' => [],
            'categories' => []
        ]);
        die();
    }
}

$students = [];
foreach (api::get_course_students($courseid, $USER->id) as $student) {
    $name = trim(($student->firstname ?? '') . ' ' . ($student->lastname ?? ''));
    $students[] = [
        'id'       => (int) $student->id,
        'name'     => $name,
        'email'    => $student->email    ?? '',
        'username' => $student->username ?? '',
    ];
}

$programmanagers = [];
foreach (api::get_program_managers_for_course($courseid) as $pm) {
    $name = trim(($pm->firstname ?? '') . ' ' . ($pm->lastname ?? ''));
    $programmanagers[] = [
        'id'    => (int) $pm->id,
        'name'  => $name,
        'email' => $pm->email ?? '',
    ];
}

$maacexecutives = [];
foreach (api::get_maac_executives_for_course($courseid) as $maac) {
    $name = trim(($maac->firstname ?? '') . ' ' . ($maac->lastname ?? ''));
    $maacexecutives[] = [
        'id' => (int)$maac->id,
        'name' => $name,
        'email' => $maac->email ?? '',
    ];
}

$course = get_course($courseid);
$categories = array_values(constants::award_categories_for_course($course->shortname, $course->fullname));
$suggestions = api::get_nomination_suggestions($courseid, $USER->id);

echo json_encode([
    'students'        => $students,
    'programmanagers' => $programmanagers,
    'maacexecutives'  => $maacexecutives,
    'categories'      => $categories,
    'suggestions'     => $suggestions,
]);
die();
