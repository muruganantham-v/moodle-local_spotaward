<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_spotaward\local\api;

require_login();

$id = required_param('id', PARAM_INT);
$nomination = api::get_nomination($id);
api::require_nomination_access($nomination, $USER->id);

$course = get_course($nomination->courseid);
$programmanager = core_user::get_user($nomination->programmanagerid);
$maacexecutive = !empty($nomination->maacexecutiveid) ? core_user::get_user($nomination->maacexecutiveid) : null;
$items = api::get_nomination_items($id);

if (empty($items)) {
    throw new moodle_exception('invalidparameter');
}

$filename = 'spot_awards_students_list_' . date('dmY') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility.
fwrite($out, "\xEF\xBB\xBF");

// Write header row.
fputcsv($out, [
    'Sl #',
    'Month',
    'Date',
    'Student',
    'Regn ID',
    'Award Category',
    'Approver',
    'Issued to',
    'Comments'
]);

$slno = 1;
$approvername = $programmanager ? fullname($programmanager) : '';
$issuedtoname = $maacexecutive ? fullname($maacexecutive) : '';
$dateval = date('Y-m-d H:i:s', $nomination->timecreated); // Format matching the user's file.

foreach ($items as $item) {
    if ($item->status === 'rejected') {
        continue;
    }
    fputcsv($out, [
        $slno,
        $item->email,
        $dateval,
        fullname($item),
        $item->username,
        $item->awardcategory ?? '',
        $approvername,
        $issuedtoname,
        $item->rejectionreason ?? ''
    ]);
    $slno++;
}

fclose($out);
exit;
