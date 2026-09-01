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
 * Download nominations list in CSV format.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_spotaward\local\api;

require_login();
require_sesskey();

$id = required_param('id', PARAM_INT);
$nomination = api::get_nomination($id);
api::require_submission_details_access($nomination, $USER->id);

if (!api::can_export_nomination_details($nomination, (int)$USER->id)) {
    throw new moodle_exception('csvdownloadnotallowed', 'local_spotaward');
}

$course = get_course($nomination->courseid);
$programmanager = core_user::get_user($nomination->programmanagerid);
$maacexecutive = !empty($nomination->maacexecutiveid) ? core_user::get_user($nomination->maacexecutiveid) : null;
$items = api::get_nomination_items($id);

if (empty($items)) {
    throw new moodle_exception('invalidparameter');
}

$filename = 'spot_awards_students_list_' . userdate(time(), '%d%m%Y') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility.
fwrite($out, "\xEF\xBB\xBF");

// Write header row.
fputcsv($out, [
    get_string('csv_slno', 'local_spotaward'),
    get_string('csv_email', 'local_spotaward'),
    get_string('csv_date', 'local_spotaward'),
    get_string('csv_student', 'local_spotaward'),
    get_string('csv_regnid', 'local_spotaward'),
    get_string('csv_awardcategory', 'local_spotaward'),
    get_string('status', 'local_spotaward'),
    get_string('csv_approver', 'local_spotaward'),
    get_string('csv_issuedto', 'local_spotaward'),
    get_string('csv_comments', 'local_spotaward')
]);

$slno = 1;
$approvername = $programmanager ? fullname($programmanager) : '';
$issuedtoname = $maacexecutive ? fullname($maacexecutive) : '';
$dateval = userdate($nomination->timecreated, get_string('strftimedatefullshort', 'langconfig'));

foreach ($items as $item) {
    $statuslabel = local_spotaward_get_status_label($item->status ?? '');
    $row = [
        $slno,
        $item->email,
        $dateval,
        fullname($item),
        $item->username,
        $item->awardcategory ?? '',
        $statuslabel,
        $approvername,
        $issuedtoname,
        $item->rejectionreason ?? ''
    ];
    fputcsv($out, array_map([api::class, 'prepare_csv_cell'], $row));
    $slno++;
}

fclose($out);
exit;
