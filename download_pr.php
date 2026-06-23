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
 * Download PR PDF.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(__DIR__ . '/classes/local/api.php');

$nominationid = required_param('nominationid', PARAM_INT);

require_login();
require_sesskey();

$nomination = local_spotaward\local\api::get_nomination($nominationid);
local_spotaward\local\api::require_nomination_access($nomination, $USER->id);

if (!local_spotaward\local\api::is_assigned_maac_executive($nomination, (int)$USER->id) && !is_siteadmin()) {
    throw new moodle_exception('notauthorised', 'local_spotaward');
}

if ($nomination->status !== 'ssteamprogress') {
    throw new moodle_exception('prdocumentnotavailable', 'local_spotaward');
}

[$filename, $content] = local_spotaward\local\api::build_pr_document_download($nominationid);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: public, must-revalidate, max-age=0');
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Content-Description: File Transfer');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . strlen($content));

while (ob_get_level()) {
    ob_end_clean();
}

echo $content;
die();
