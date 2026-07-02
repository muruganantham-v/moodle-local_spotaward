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
 * View certificate PDF
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $PAGE, $CFG, $USER, $DB;

$nominationid = required_param('nominationid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$action = optional_param('action', 'view', PARAM_TEXT);

require_login();
require_sesskey();

$nomination = $DB->get_record('spotaward_nominations', ['id' => $nominationid], '*', MUST_EXIST);
if (!in_array($nomination->status, ['ssteamprogress', 'closed'], true)) {
    throw new moodle_exception('invalidparameter');
}

require_once(__DIR__ . '/classes/local/api.php');
local_spotaward\local\api::require_nomination_access($nomination, $USER->id);

$cancertificateaccess = is_siteadmin() || local_spotaward\local\api::is_manager($USER->id) ||
    local_spotaward\local\api::is_assigned_maac_executive($nomination, (int)$USER->id);
if (!$cancertificateaccess) {
    throw new moodle_exception('notauthorised', 'local_spotaward');
}

local_spotaward\local\api::ensure_nomination_certificates_generated($nominationid);

if ($userid > 0) {
    $student = core_user::get_user($userid);
    if (!$student) {
        throw new moodle_exception('invalidparameter');
    }

    if ($itemid > 0) {
        $item = $DB->get_record('spotaward_nomination_items', ['id' => $itemid, 'nominationid' => $nominationid], '*', MUST_EXIST);
        if ((int)$item->studentid !== $userid) {
            throw new moodle_exception('invalidparameter');
        }
    }

    $studentname = fullname($student);
    $filename = "Spot_Award_Certificate_{$studentname}.pdf";

    $file = local_spotaward\local\api::get_certificate_file($nominationid, $userid, $itemid);
    if (!$file) {
        throw new moodle_exception('certificatenotfound', 'local_spotaward');
    }

    $content = $file->get_content();
} else {
    $filename = "Spot_Award_Certificates_{$nominationid}.pdf";

    $files = local_spotaward\local\api::get_all_certificate_files($nominationid);

    if (empty($files)) {
        throw new moodle_exception('nocertificates', 'local_spotaward');
    }

    $content = local_spotaward\local\api::merge_stored_pdf_files($files, $filename);
}

if ($action === 'download') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Content-Description: File Transfer');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . strlen($content));
} else {
    header('Content-Type: application/pdf');
    header('Content-disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Content-Length: ' . strlen($content));
}

ob_clean();
echo $content;
die();
