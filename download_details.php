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
$nominator = core_user::get_user($nomination->nominatorid);
$programmanager = core_user::get_user($nomination->programmanagerid);
$maacexecutive = !empty($nomination->maacexecutiveid) ? core_user::get_user($nomination->maacexecutiveid) : null;
$items = api::get_nomination_items($id);

if (empty($items)) {
    throw new moodle_exception('invalidparameter');
}

// Compile award category summary counts.
$categorycounts = [];
foreach ($items as $item) {
    $cat = trim($item->awardcategory ?? '');
    if ($cat !== '') {
        $categorycounts[$cat] = ($categorycounts[$cat] ?? 0) + 1;
    }
}
$summaryparts = [];
foreach ($categorycounts as $cat => $count) {
    $summaryparts[] = s($cat) . ': ' . $count;
}
$summarytext = implode(' | ', $summaryparts);

$date = userdate($nomination->timecreated, '%d %B %Y');
$totalstudents = count($items);
$approvedby = $programmanager ? fullname($programmanager) : '-';
$issuedby = $maacexecutive ? fullname($maacexecutive) : '-';

// Logo path for mPDF embedding.
$logopath = __DIR__ . '/pix/emertxe_logo.png';

// Build student rows HTML.
$rowshtml = '';
$slno = 1;
foreach ($items as $item) {
    $bgcolor = ($slno % 2 === 0) ? '#f9f9f9' : '#ffffff';
    $studentname = fullname($item);
    $rowshtml .= '
    <tr style="background-color: ' . $bgcolor . ';">
      <td style="padding: 12px 10px; border: 1px solid #dcdcdc; font-size: 11px; color: #222222; text-align: center; font-weight: bold;">' . sprintf('%02d', $slno) . '</td>
      <td style="padding: 12px 10px; border: 1px solid #dcdcdc; font-size: 11px; color: #222222; font-weight: bold;">' . s($studentname) . '</td>
      <td style="padding: 12px 10px; border: 1px solid #dcdcdc; font-size: 11px; color: #333333;">' . s($item->username) . '</td>
      <td style="padding: 12px 10px; border: 1px solid #dcdcdc; font-size: 11px; color: #333333;">' . s($item->email) . '</td>
      <td style="padding: 12px 10px; border: 1px solid #dcdcdc; font-size: 11px; color: #333333;">' . s($item->awardcategory ?? '') . '</td>
    </tr>';
    $slno++;
}

// Build the full HTML document.
$html = '
<!-- Logo top-right -->
<div style="text-align: right; margin-bottom: 10px;">
  <img src="' . $logopath . '" style="height: 50px;" />
</div>

<!-- Title block -->
<div style="margin-bottom: 5px;">
  <div style="font-size: 28px; font-weight: bold; color: #111111; letter-spacing: 0.5px;">SPOT AWARDS</div>
  <div style="font-size: 14px; color: #333333; margin-top: 2px;">Student Recognition Details</div>
</div>

<!-- Divider -->
<div style="border-bottom: 2px solid #333333; margin-bottom: 22px;"></div>

<!-- Info table 2x2 -->
<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; border: 1px solid #cccccc;">
  <tr>
    <td style="width: 50%; padding: 16px 20px; border: 1px solid #cccccc; background-color: #ffffff; vertical-align: top;">
      <div style="font-size: 11px; color: #555555; margin-bottom: 8px;">Recognition Date</div>
      <div style="font-size: 18px; font-weight: bold; color: #111111;">' . s($date) . '</div>
    </td>
    <td style="width: 50%; padding: 16px 20px; border: 1px solid #cccccc; background-color: #ffffff; vertical-align: top;">
      <div style="font-size: 11px; color: #555555; margin-bottom: 8px;">Total Students</div>
      <div style="font-size: 18px; font-weight: bold; color: #111111;">' . s($totalstudents) . '</div>
    </td>
  </tr>
  <tr>
    <td style="width: 50%; padding: 16px 20px; border: 1px solid #cccccc; background-color: #ffffff; vertical-align: top;">
      <div style="font-size: 11px; color: #555555; margin-bottom: 8px;">Approved By</div>
      <div style="font-size: 18px; font-weight: bold; color: #111111;">' . s($approvedby) . '</div>
    </td>
    <td style="width: 50%; padding: 16px 20px; border: 1px solid #cccccc; background-color: #ffffff; vertical-align: top;">
      <div style="font-size: 11px; color: #555555; margin-bottom: 8px;">Issued By</div>
      <div style="font-size: 18px; font-weight: bold; color: #111111;">' . s($issuedby) . '</div>
    </td>
  </tr>
</table>

<!-- Section title -->
<div style="font-size: 17px; font-weight: bold; color: #111111; margin-bottom: 14px;">Award Recipient Details</div>

<!-- Student table -->
<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px;">
  <thead>
    <tr>
      <th style="background-color: #1a5276; color: #ffffff; font-weight: bold; font-size: 11px; padding: 12px 10px; border: 1px solid #1a5276; text-align: center; width: 8%;">Sl. No.</th>
      <th style="background-color: #1a5276; color: #ffffff; font-weight: bold; font-size: 11px; padding: 12px 10px; border: 1px solid #1a5276; text-align: left; width: 22%;">Student Name</th>
      <th style="background-color: #1a5276; color: #ffffff; font-weight: bold; font-size: 11px; padding: 12px 10px; border: 1px solid #1a5276; text-align: left; width: 18%;">Registration ID</th>
      <th style="background-color: #1a5276; color: #ffffff; font-weight: bold; font-size: 11px; padding: 12px 10px; border: 1px solid #1a5276; text-align: left; width: 30%;">Email ID</th>
      <th style="background-color: #1a5276; color: #ffffff; font-weight: bold; font-size: 11px; padding: 12px 10px; border: 1px solid #1a5276; text-align: left; width: 22%;">Award Category</th>
    </tr>
  </thead>
  <tbody>
    ' . $rowshtml . '
  </tbody>
</table>

<!-- Award Category Summary box -->
<div style="border: 1px solid #d0d0d0; background-color: #f5f5f5; padding: 14px 18px; margin-top: 8px;">
  <div style="font-size: 12px; font-weight: bold; color: #111111; margin-bottom: 6px;">Award Category Summary</div>
  <div style="font-size: 10px; color: #333333; line-height: 1.6;">' . $summarytext . '</div>
</div>
';

// Load mPDF library.
require_once($CFG->dirroot . '/mod/certificatebeautiful/classes/pdf/vendor/autoload.php');

// Register Poppins font.
$defaultfontdirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
$defaultfontdata = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'P',
    'tempDir' => "{$CFG->dataroot}/temp/mpdf",
    'margin_left' => 20,
    'margin_right' => 20,
    'margin_top' => 20,
    'margin_bottom' => 25,
    'default_font' => 'poppins',
    'fontDir' => array_merge($defaultfontdirs, [
        __DIR__ . '/fonts',
    ]),
    'fontdata' => $defaultfontdata + [
        'poppins' => [
            'R' => 'Poppins-Regular.ttf',
            'B' => 'Poppins-Bold.ttf',
            'I' => 'Poppins-Italic.ttf',
            'BI' => 'Poppins-BoldItalic.ttf',
        ],
    ],
]);

$mpdf->SetAuthor('Spot Awards');
$mpdf->SetTitle('Student Recognition Details');
$mpdf->SetCreator('Spot Awards Plugin');

// Page footer.
$mpdf->SetHTMLFooter('
<table style="width: 100%; border-top: 1px solid #d0d0d0; padding-top: 6px;">
  <tr>
    <td style="font-size: 8px; color: #999999; font-family: poppins, sans-serif;">Spot Awards &mdash; Student Recognition Details</td>
    <td style="text-align: center; font-size: 8px; color: #999999; font-family: poppins, sans-serif;">Course: ' . s($course->shortname) . '</td>
    <td style="text-align: right; font-size: 8px; color: #999999; font-family: poppins, sans-serif;">Page {PAGENO} of {nbpg}</td>
  </tr>
</table>
');

$mpdf->WriteHTML($html);

$safename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $course->shortname);
$filename = 'Student_Recognition_Details_' . $safename . '_' . $id . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
