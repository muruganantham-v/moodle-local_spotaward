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
 * Download nomination details in PDF format.
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
    throw new moodle_exception('notauthorised', 'local_spotaward');
}
$course = get_course($nomination->courseid);
$nominator = core_user::get_user($nomination->nominatorid);
$programmanager = core_user::get_user($nomination->programmanagerid);
$maacexecutive = !empty($nomination->maacexecutiveid) ? core_user::get_user($nomination->maacexecutiveid) : null;
$items = api::get_nomination_items($id);
$items = array_values(array_filter($items, function($item) {
    return (string)$item->status !== 'rejected';
}));
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
    $summaryparts[] = s($cat) . ' (' . $count . ')';
}
$summarytext = implode(', ', $summaryparts);
$date = userdate($nomination->timecreated, '%d %B %Y');
$totalstudents = count($items);
$approvedby = $programmanager ? fullname($programmanager) : '';
$issuedby = $maacexecutive ? fullname($maacexecutive) : '';
// Batch Name: extract the part after the first ':'.
$coursefullname = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
$colonpos = strpos($coursefullname, ':');
$batchname = ($colonpos !== false) ? trim(substr($coursefullname, $colonpos + 1)) : trim($coursefullname);
// Course Type: map professional name to abbreviation.
$professionalmapping = [
    'Embedded Professional' => 'ECEP',
    'IoT Professional'      => 'ECIP',
];
$professional = trim($nomination->professional ?? '');
$coursetype   = $professionalmapping[$professional] ?? $professional;
// Module Name and nominator full name.
$modulename  = s($nomination->modulename ?? '');
$nominatedby = $nominator ? fullname($nominator) : '';
// Logo file path for mPDF (absolute path).
$logopath = __DIR__ . '/pix/emertxe_logo_cropped.png';
$titlepath = __DIR__ . '/pix/spot_awards_title.png';

// ══════════════════════════════════════════════════════════════════════════════
// TITLE
// "SPOT AWARDS" is rendered as plain HTML text split into two colored spans
// (#CC0066 pink/magenta → #663399 purple) to approximate the brand gradient.
// mPDF's SVG importer does not reliably apply gradient fills to <text>
// elements, so a real linear-gradient title isn't safe to rely on in mPDF —
// this two-tone split is the robust equivalent for print output.
// ══════════════════════════════════════════════════════════════════════════════

// ══════════════════════════════════════════════════════════════════════════════
// ICONS
// ══════════════════════════════════════════════════════════════════════════════
$ICON_BATCH = __DIR__ . '/icons/batch.svg';
$ICON_PERSON = __DIR__ . '/icons/nominatorby.svg';
$ICON_GRID = __DIR__ . '/icons/coursetype.svg';
$ICON_CHECK = __DIR__ . '/icons/apporvedby.svg';
$ICON_MONITOR = __DIR__ . '/icons/module.svg';
$ICON_ARROWS = __DIR__ . '/icons/issuedby.svg';
$ICON_CALENDAR = __DIR__ . '/icons/Date.svg';
$ICON_PEOPLE = __DIR__ . '/icons/studentcount.png';
// Rosette Star Badge for "Award Recipient Details"
$ICON_STAR = 'data:image/svg+xml;base64,' . base64_encode(
    '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28">'
    . '<path fill="#F0EDFA" stroke="#6741d9" stroke-width="1.5" d="M14 2l2.8 2.8 4-.8.8 4 2.8 2.8-2.8 2.8-.8 4-4-.8L14 26l-2.8-2.8-4 .8-.8-4-2.8-2.8 2.8-2.8.8-4 4 .8L14 2z"/>'
    . '<polygon fill="#6741d9" points="14 8 15.5 12 20 12 16.5 14.5 17.5 19 14 16.5 10.5 19 11.5 14.5 8 12 12.5 12"/>'
    . '</svg>'
);

// ══════════════════════════════════════════════════════════════════════════════
// FIELD ROW HELPER (4-Column Layout)
// Renders one full row of the field box:
// Label 1 (gray bg) | Value 1 (white bg) | Label 2 (gray bg) | Value 2 (white bg)
// ══════════════════════════════════════════════════════════════════════════════
function spotaward_field_row(
    string $icon1, string $label1, string $value1,
    string $icon2, string $label2, string $value2,
    bool $is_last
): string {
    $b_bot   = $is_last ? '' : 'border-bottom:1px solid #e3e3e3;';
    $b_right = 'border-right:1px solid #e3e3e3;';
    $bg_label = '#F9F8FC';
    $bg_value = '#FFFFFF';

    return '
    <tr>
      <!-- Column 1 (Label) -->
      <td style="width:20%; background-color:'.$bg_label.'; padding:12px 14px; '.$b_bot.' '.$b_right.'">
        <table style="border-collapse:collapse; width:100%; border:none;">
          <tr>
            <td style="width:40px; padding:0; border:none; vertical-align:middle;">
              <img src="'.$icon1.'" style="width:36px; height:36px;" />
            </td>
            <td style="padding:0; border:none; vertical-align:middle; font-family:poppins,sans-serif; font-size:12px; color:#1a1a1a;">
              '.$label1.'
            </td>
          </tr>
        </table>
      </td>

      <!-- Column 2 (Value) -->
      <td style="width:30%; background-color:'.$bg_value.'; padding:12px 14px; '.$b_bot.' '.$b_right.' font-family:poppins,sans-serif; font-size:12px; color:#1a1a1a; vertical-align:middle;">
        '.$value1.'
      </td>

      <!-- Column 3 (Label) -->
      <td style="width:20%; background-color:'.$bg_label.'; padding:12px 14px; '.$b_bot.' '.$b_right.'">
        <table style="border-collapse:collapse; width:100%; border:none;">
          <tr>
            <td style="width:40px; padding:0; border:none; vertical-align:middle;">
              <img src="'.$icon2.'" style="width:36px; height:36px;" />
            </td>
            <td style="padding:0; border:none; vertical-align:middle; font-family:poppins,sans-serif; font-size:12px; color:#1a1a1a;">
              '.$label2.'
            </td>
          </tr>
        </table>
      </td>

      <!-- Column 4 (Value) -->
      <td style="width:30%; background-color:'.$bg_value.'; padding:12px 14px; '.$b_bot.' font-family:poppins,sans-serif; font-size:12px; color:#1a1a1a; vertical-align:middle;">
        '.$value2.'
      </td>
    </tr>';
}

// ══════════════════════════════════════════════════════════════════════════════
// STUDENT ROWS
// First 10 rows on page 1; overflow on page 2.
// White backgrounds with light gray borders between all cells.
// ══════════════════════════════════════════════════════════════════════════════
$firstpagehtml  = '';
$otherpageshtml = '';
$rowcount = 1;
foreach ($items as $item) {
    $rowbg = ($rowcount % 2 == 0) ? '#FAF8FD' : '#FFF';
    $rowhtml = '<tr style="background:' . $rowbg . ';">
        <td style="padding:14px 6px; border-left:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; text-align:center; font-family:poppins,sans-serif; font-size:14px; color:#1a1a1a;">' . sprintf("%02d", $rowcount) . '</td>
        <td style="padding:14px 6px; border-left:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; text-align:center; font-family:poppins,sans-serif; font-size:14px; color:#1a1a1a;">' . s(fullname($item)) . '</td>
        <td style="padding:14px 6px; border-left:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; text-align:center; font-family:poppins,sans-serif; font-size:14px; color:#1a1a1a;">' . s($item->username) . '</td>
        <td style="padding:14px 6px; border-left:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; text-align:center; font-family:poppins,sans-serif; font-size:14px; color:#1a1a1a;">' . s($item->email) . '</td>
        <td style="padding:14px 6px; border-left:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; border-right:1px solid #E4E4E4; text-align:center; font-family:poppins,sans-serif; font-size:14px; color:#1a1a1a;">' . s($item->awardcategory ?? '') . '</td>
    </tr>';

    if ($rowcount <= 10) {
        $firstpagehtml .= $rowhtml;
    } else {
        $otherpageshtml .= $rowhtml;
    }
    $rowcount++;
}

// ══════════════════════════════════════════════════════════════════════════════
// TABLE HEADER
// ══════════════════════════════════════════════════════════════════════════════
$tableheader = '<thead>
    <tr style="background:#663399;">
      <th style="padding:14px 6px; border-top:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; border-left:1px solid #E4E4E4; border-top-left-radius:8px; color:#FFF; text-align:center; font-family:poppins,sans-serif; font-size:14px; font-weight:600; width:8%;">SI.NO.</th>
      <th style="padding:14px 6px; border-top:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; border-left:1px solid #E4E4E4; color:#FFF; text-align:center; font-family:poppins,sans-serif; font-size:14px; font-weight:600; width:23%;">Student Name</th>
      <th style="padding:14px 6px; border-top:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; border-left:1px solid #E4E4E4; color:#FFF; text-align:center; font-family:poppins,sans-serif; font-size:14px; font-weight:600; width:21%;">Registration ID</th>
      <th style="padding:14px 6px; border-top:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; border-left:1px solid #E4E4E4; color:#FFF; text-align:center; font-family:poppins,sans-serif; font-size:14px; font-weight:600; width:27%;">Email ID</th>
      <th style="padding:14px 6px; border-top:1px solid #E4E4E4; border-bottom:1px solid #E4E4E4; border-left:1px solid #E4E4E4; border-right:1px solid #E4E4E4; border-top-right-radius:8px; color:#FFF; text-align:center; font-family:poppins,sans-serif; font-size:14px; font-weight:600; width:21%;">Award Category</th>
    </tr>
  </thead>';

// ══════════════════════════════════════════════════════════════════════════════
// BUILD HTML DOCUMENT
// ══════════════════════════════════════════════════════════════════════════════
$page_break_html = '';
if ($otherpageshtml !== '') {
    $page_break_html = '
    <pagebreak />
    <table style="width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed;margin-bottom:18px;">
      ' . $tableheader . '
      <tbody>
        ' . $otherpageshtml . '
      </tbody>
    </table>';
}

$ICON_STAR_PATH = __DIR__ . '/icons/award_recipient.svg';
if (file_exists($ICON_STAR_PATH)) {
    $ICON_STAR = $ICON_STAR_PATH;
}

$default_template = '<!-- HEADER -->
<table style="width:100%;border-collapse:collapse;border-bottom:1px solid #1a1a1a;padding-bottom:14px;margin-bottom:20px;">
  <tr>
    <td style="text-align:left;padding:0 0 14px 0;vertical-align:bottom;">
      <img src="[[TITLE_URI]]" style="height:28px;margin-bottom:4px;" alt="SPOT AWARDS" />
      <div style="color:#000;font-family:poppins,sans-serif;font-size:18px;font-style:normal;font-weight:400;line-height:26px;margin-top:4px;">Student Recognition Details</div>
    </td>
    <td style="text-align:right;padding:0 0 14px 0;vertical-align:top;">
      <img src="[[LOGO_PATH]]" style="width:144px;height:32px;" />
    </td>
  </tr>
</table>

<!-- FIELD BOX -->
<div style="border:1px solid #E3E3E3;border-radius:12px;background:#FFF;margin-bottom:28px;overflow:hidden;">
<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
  <tr>
    <td style="width:29%;background:#FAF8FD;padding:14px 14px;vertical-align:middle;border-bottom:1px solid #EEEAF5;">
      <table style="border-collapse:collapse;width:100%;">
        <tr>
          <td style="width:56px;vertical-align:middle;padding:0;">
            <span style="display:inline-block;width:44px;height:44px;border-radius:50%;background:#FFF;border:1px solid #EFE9F7;text-align:center;line-height:44px;"><img src="[[ICON_BATCH]]" style="width:24px;height:24px;vertical-align:middle;margin-bottom:3px;"/></span>
          </td>
          <td style="padding:0 0 0 4px;color:#000;font-family:poppins,sans-serif;font-size:14px;font-style:normal;font-weight:500;line-height:18px;">Batch Name</td>
        </tr>
      </table>
    </td>
    <td style="width:21%;background:#FFF;padding:14px 14px;font-family:poppins,sans-serif;font-size:14px;color:#1a1a1a;font-weight:500;vertical-align:middle;border-bottom:1px solid #EEEAF5;">[[BATCH_NAME]]</td>
    <td style="width:1px;background:#E3E3E3;padding:0;"></td>
    <td style="width:29%;background:#FAF8FD;padding:14px 14px;vertical-align:middle;border-bottom:1px solid #EEEAF5;">
      <table style="border-collapse:collapse;width:100%;">
        <tr>
          <td style="width:56px;vertical-align:middle;padding:0;">
            <span style="display:inline-block;width:44px;height:44px;border-radius:50%;background:#FFF;border:1px solid #EFE9F7;text-align:center;line-height:44px;"><img src="[[ICON_PERSON]]" style="width:24px;height:24px;vertical-align:middle;margin-bottom:3px;"/></span>
          </td>
          <td style="padding:0 0 0 4px;color:#000;font-family:poppins,sans-serif;font-size:14px;font-style:normal;font-weight:500;line-height:18px;">Nominated By</td>
        </tr>
      </table>
    </td>
    <td style="width:21%;background:#FFF;padding:14px 14px;font-family:poppins,sans-serif;font-size:14px;color:#1a1a1a;font-weight:500;vertical-align:middle;border-bottom:1px solid #EEEAF5;">[[NOMINATED_BY]]</td>
  </tr>
  <tr>
    <td style="background:#FAF8FD;padding:14px 14px;vertical-align:middle;border-bottom:1px solid #EEEAF5;">
      <table style="border-collapse:collapse;width:100%;">
        <tr>
          <td style="width:56px;vertical-align:middle;padding:0;">
            <span style="display:inline-block;width:44px;height:44px;border-radius:50%;background:#FFF;border:1px solid #EFE9F7;text-align:center;line-height:44px;"><img src="[[ICON_GRID]]" style="width:24px;height:24px;vertical-align:middle;margin-bottom:3px;"/></span>
          </td>
          <td style="padding:0 0 0 4px;color:#000;font-family:poppins,sans-serif;font-size:14px;font-style:normal;font-weight:500;line-height:18px;">Course Type</td>
        </tr>
      </table>
    </td>
    <td style="background:#FFF;padding:14px 14px;font-family:poppins,sans-serif;font-size:14px;color:#1a1a1a;font-weight:500;vertical-align:middle;border-bottom:1px solid #EEEAF5;">[[COURSE_TYPE]]</td>
    <td style="background:#E3E3E3;padding:0;width:1px;"></td>
    <td style="background:#FAF8FD;padding:14px 14px;vertical-align:middle;border-bottom:1px solid #EEEAF5;">
      <table style="border-collapse:collapse;width:100%;">
        <tr>
          <td style="width:56px;vertical-align:middle;padding:0;">
            <span style="display:inline-block;width:44px;height:44px;border-radius:50%;background:#FFF;border:1px solid #EFE9F7;text-align:center;line-height:44px;"><img src="[[ICON_CHECK]]" style="width:24px;height:24px;vertical-align:middle;margin-bottom:3px;"/></span>
          </td>
          <td style="padding:0 0 0 4px;color:#000;font-family:poppins,sans-serif;font-size:14px;font-style:normal;font-weight:500;line-height:18px;">Approved By</td>
        </tr>
      </table>
    </td>
    <td style="background:#FFF;padding:14px 14px;font-family:poppins,sans-serif;font-size:14px;color:#1a1a1a;font-weight:500;vertical-align:middle;border-bottom:1px solid #EEEAF5;">[[APPROVED_BY]]</td>
  </tr>
  <tr>
    <td style="background:#FAF8FD;padding:14px 14px;vertical-align:middle;border-bottom:1px solid #EEEAF5;">
      <table style="border-collapse:collapse;width:100%;">
        <tr>
          <td style="width:56px;vertical-align:middle;padding:0;">
            <span style="display:inline-block;width:44px;height:44px;border-radius:50%;background:#FFF;border:1px solid #EFE9F7;text-align:center;line-height:44px;"><img src="[[ICON_MONITOR]]" style="width:24px;height:24px;vertical-align:middle;margin-bottom:3px;"/></span>
          </td>
          <td style="padding:0 0 0 4px;color:#000;font-family:poppins,sans-serif;font-size:14px;font-style:normal;font-weight:500;line-height:18px;">Module Name</td>
        </tr>
      </table>
    </td>
    <td style="background:#FFF;padding:14px 14px;font-family:poppins,sans-serif;font-size:14px;color:#1a1a1a;font-weight:500;vertical-align:middle;border-bottom:1px solid #EEEAF5;">[[MODULE_NAME]]</td>
    <td style="background:#E3E3E3;padding:0;width:1px;"></td>
    <td style="background:#FAF8FD;padding:14px 14px;vertical-align:middle;border-bottom:1px solid #EEEAF5;">
      <table style="border-collapse:collapse;width:100%;">
        <tr>
          <td style="width:56px;vertical-align:middle;padding:0;">
            <span style="display:inline-block;width:44px;height:44px;border-radius:50%;background:#FFF;border:1px solid #EFE9F7;text-align:center;line-height:44px;"><img src="[[ICON_ARROWS]]" style="width:24px;height:24px;vertical-align:middle;margin-bottom:3px;"/></span>
          </td>
          <td style="padding:0 0 0 4px;color:#000;font-family:poppins,sans-serif;font-size:14px;font-style:normal;font-weight:500;line-height:18px;">Issued By</td>
        </tr>
      </table>
    </td>
    <td style="background:#FFF;padding:14px 14px;font-family:poppins,sans-serif;font-size:14px;color:#1a1a1a;font-weight:500;vertical-align:middle;border-bottom:1px solid #EEEAF5;">[[ISSUED_BY]]</td>
  </tr>
  <tr>
    <td style="background:#FAF8FD;padding:14px 14px;vertical-align:middle;">
      <table style="border-collapse:collapse;width:100%;">
        <tr>
          <td style="width:56px;vertical-align:middle;padding:0;">
            <span style="display:inline-block;width:44px;height:44px;border-radius:50%;background:#FFF;border:1px solid #EFE9F7;text-align:center;line-height:44px;"><img src="[[ICON_CALENDAR]]" style="width:24px;height:24px;vertical-align:middle;margin-bottom:3px;"/></span>
          </td>
          <td style="padding:0 0 0 4px;color:#000;font-family:poppins,sans-serif;font-size:14px;font-style:normal;font-weight:500;line-height:18px;">Recognition Date</td>
        </tr>
      </table>
    </td>
    <td style="background:#FFF;padding:14px 14px;font-family:poppins,sans-serif;font-size:14px;color:#1a1a1a;font-weight:500;vertical-align:middle;">[[DATE]]</td>
    <td style="background:#E3E3E3;padding:0;width:1px;"></td>
    <td style="background:#FAF8FD;padding:14px 14px;vertical-align:middle;">
      <table style="border-collapse:collapse;width:100%;">
        <tr>
          <td style="width:56px;vertical-align:middle;padding:0;">
            <span style="display:inline-block;width:44px;height:44px;border-radius:50%;background:#FFF;border:1px solid #EFE9F7;text-align:center;line-height:44px;"><img src="[[ICON_PEOPLE]]" style="width:24px;height:24px;vertical-align:middle;margin-bottom:3px;"/></span>
          </td>
          <td style="padding:0 0 0 4px;color:#000;font-family:poppins,sans-serif;font-size:14px;font-style:normal;font-weight:500;line-height:18px;">Total Students</td>
        </tr>
      </table>
    </td>
    <td style="background:#FFF;padding:14px 14px;font-family:poppins,sans-serif;font-size:14px;color:#1a1a1a;font-weight:500;vertical-align:middle;">[[TOTAL_STUDENTS]]</td>
  </tr>
</table>
</div>

<!-- SECTION HEADING -->
<table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
  <tr>
    <td style="padding:0;border:none;vertical-align:middle;">
      <img src="[[ICON_STAR]]" style="width:26px;height:26px;vertical-align:middle;" />
      <span style="color:#000;font-family:poppins,sans-serif;font-size:16px;font-style:normal;font-weight:700;line-height:22px;vertical-align:middle;margin-left:6px;">Award Recipient Details</span>
    </td>
  </tr>
</table>

<!-- STUDENT TABLE -->
<table style="width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed;margin-bottom:18px;">
  [[TABLE_HEADER]]
  <tbody>
    [[STUDENT_ROWS_PAGE1]]
  </tbody>
</table>

[[PAGE_BREAK_IF_NEEDED]]

<!-- SUMMARY BOX -->
<div style="border:1px solid #e3e3e3;border-radius:4px;background:#f4f3f3;margin-bottom:24px;padding:16px;">
  <div style="font-family:poppins,sans-serif;font-size:14px;font-weight:500;color:#4d4d4d;">Award Category Summary :</div>
  <div style="font-family:poppins,sans-serif;margin-top:8px;font-size:13px;color:#1a1a1a;">[[SUMMARY_TEXT]]</div>
</div>';

$template = $default_template;

$html = str_replace(
    [
        '[[LOGO_PATH]]',
        '[[TITLE_URI]]',
        '[[BATCH_NAME]]',
        '[[NOMINATED_BY]]',
        '[[COURSE_TYPE]]',
        '[[APPROVED_BY]]',
        '[[MODULE_NAME]]',
        '[[ISSUED_BY]]',
        '[[DATE]]',
        '[[TOTAL_STUDENTS]]',
        '[[ICON_BATCH]]',
        '[[ICON_PERSON]]',
        '[[ICON_GRID]]',
        '[[ICON_CHECK]]',
        '[[ICON_MONITOR]]',
        '[[ICON_ARROWS]]',
        '[[ICON_CALENDAR]]',
        '[[ICON_PEOPLE]]',
        '[[ICON_STAR]]',
        '[[TABLE_HEADER]]',
        '[[STUDENT_ROWS_PAGE1]]',
        '[[PAGE_BREAK_IF_NEEDED]]',
        '[[SUMMARY_TEXT]]'
    ],
    [
        $logopath,
        $titlepath,
        s($batchname),
        s($nominatedby),
        s($coursetype),
        s($approvedby),
        $modulename,
        s($issuedby),
        s($date),
        s($totalstudents),
        $ICON_BATCH,
        $ICON_PERSON,
        $ICON_GRID,
        $ICON_CHECK,
        $ICON_MONITOR,
        $ICON_ARROWS,
        $ICON_CALENDAR,
        $ICON_PEOPLE,
        $ICON_STAR,
        $tableheader,
        $firstpagehtml,
        $page_break_html,
        $summarytext
    ],
    $template
);

// ══════════════════════════════════════════════════════════════════════════════
// mPDF SETUP
// Margins match the template: 16mm L/R/T, 12mm B.
// Poppins font is registered from the plugin's /fonts directory.
// ══════════════════════════════════════════════════════════════════════════════
require_once($CFG->dirroot . '/mod/certificatebeautiful/classes/pdf/vendor/autoload.php');
$defaultfontdirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
$defaultfontdata = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'orientation'   => 'P',
    'tempDir'       => "{$CFG->dataroot}/temp/mpdf",
    'margin_left'   => 16,
    'margin_right'  => 16,
    'margin_top'    => 16,
    'margin_bottom' => 12,
    'default_font'  => 'poppins',
    'fontDir'       => array_merge($defaultfontdirs, [__DIR__ . '/fonts']),
    'fontdata'      => $defaultfontdata + [
        'poppins' => [
            'R'  => 'Poppins-Regular.ttf',
            'B'  => 'Poppins-Bold.ttf',
            'I'  => 'Poppins-Italic.ttf',
            'BI' => 'Poppins-BoldItalic.ttf',
        ],
    ],
]);
$mpdf->SetAuthor('Spot Awards');
$mpdf->SetTitle('Student Recognition Details');
$mpdf->SetCreator('Spot Awards Plugin');

// Page footer.
$mpdf->SetHTMLFooter('
<table style="width:100%;border-top:1px solid #e3e3e3;padding-top:10px;margin-top:10px;">
  <tr>
    <td style="font-family:poppins,sans-serif;font-size:9.5px;color:#9a9a9a;padding:0;">Spot Awards - Student Recognition Details</td>
    <td style="text-align:right;font-family:poppins,sans-serif;font-size:9.5px;color:#9a9a9a;padding:0;">Page {PAGENO}</td>
  </tr>
</table>
');

$mpdf->WriteHTML($html);
$safename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $course->shortname);
$filename = 'Student_Recognition_Details_' . $safename . '_' . $id . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
