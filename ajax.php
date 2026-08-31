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
 * AJAX endpoints for local_spotaward.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    api::require_submission_details_access($nomination, $USER->id);

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
            'message' => get_string('accessdenied', 'local_spotaward'),
        ]);
        die();
    }

    try {
        $awardpayload = optional_param('awardpayload', '', PARAM_RAW_TRIMMED);
        if ($awardpayload !== '') {
            $decoded = json_decode($awardpayload, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new moodle_exception('invalidparameter');
            }
        }

        $data = (object)[
            'courseid' => optional_param('courseid', 0, PARAM_INT),
            'modulename' => optional_param('modulename', '', PARAM_TEXT),
            'awardpayload' => $awardpayload,
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
        // Bug #8 fix: Never expose raw PHP exception messages to the client.
        // Log the full detail server-side for debugging.
        debugging('local_spotaward autosave error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        http_response_code(400);
        echo json_encode([
            'saved'   => false,
            'message' => get_string('autosaveerror', 'local_spotaward'),
        ]);
    }
    die();
}

// Bug #1 fix: Protect the courseoptions endpoint with a sesskey so it cannot be
// triggered cross-site. Student/staff PII must not be readable via CSRF.
require_sesskey();

$courseid = required_param('courseid', PARAM_INT);

if ($courseid <= 0) {
    echo json_encode(['error' => get_string('invalidcourseid', 'local_spotaward'), 'students' => [], 'programmanagers' => [], 'categories' => []]);
    die();
}

if (!api::user_can_access($USER->id)) {
    http_response_code(403);
    echo json_encode(['error' => get_string('accessdenied', 'local_spotaward'), 'students' => [], 'programmanagers' => [], 'categories' => []]);
    die();
}

// This endpoint supplies the nomination form and exposes student PII. Manager or
// SS-Team membership elsewhere on the site must never grant access to this course.
if (!api::can_nominate_in_course((int)$USER->id, $courseid)) {
    http_response_code(403);
    echo json_encode([
        'error' => get_string('accessdeniedcoursecontext', 'local_spotaward'),
        'students' => [],
        'programmanagers' => [],
        'maacexecutives' => [],
        'categories' => [],
    ]);
    die();
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
    // Bug #4 fix: Only id+name is needed client-side for the dropdown.
    // Staff email addresses must not be sent to arbitrary authenticated users.
    $programmanagers[] = [
        'id'   => (int) $pm->id,
        'name' => $name,
    ];
}

$maacexecutives = [];
foreach (api::get_maac_executives_for_course($courseid) as $maac) {
    $name = trim(($maac->firstname ?? '') . ' ' . ($maac->lastname ?? ''));
    // Bug #4 fix: Strip email — id+name is sufficient for the nomination form.
    $maacexecutives[] = [
        'id'   => (int)$maac->id,
        'name' => $name,
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
