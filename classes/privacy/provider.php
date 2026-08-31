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
 * Privacy Subsystem implementation for local_spotaward.
 *
 * @package    local_spotaward
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_spotaward\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use context_course;

/**
 * Privacy Subsystem implementation for local_spotaward.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'spotaward_nominations',
            [
                'nominatorid' => 'privacy:metadata:nominations:nominatorid',
                'programmanagerid' => 'privacy:metadata:nominations:programmanagerid',
                'maacexecutiveid' => 'privacy:metadata:nominations:maacexecutiveid',
                'adminsharedby' => 'privacy:metadata:nominations:adminsharedby',
                'adminsharedtime' => 'privacy:metadata:nominations:adminsharedtime',
                'admindownloadedby' => 'privacy:metadata:nominations:admindownloadedby',
                'admindownloadedtime' => 'privacy:metadata:nominations:admindownloadedtime',
                'modulename' => 'privacy:metadata:nominations:modulename',
                'awardcategory' => 'privacy:metadata:nominations:awardcategory',
                'professional' => 'privacy:metadata:nominations:professional',
                'awarddescription' => 'privacy:metadata:nominations:awarddescription',
                'status' => 'privacy:metadata:nominations:status',
                'timecreated' => 'privacy:metadata:nominations:timecreated',
            ],
            'privacy:metadata:nominations'
        );

        $collection->add_database_table(
            'spotaward_nomination_items',
            [
                'studentid' => 'privacy:metadata:items:studentid',
                'awardcategory' => 'privacy:metadata:items:awardcategory',
                'professional' => 'privacy:metadata:items:professional',
                'awarddescription' => 'privacy:metadata:items:awarddescription',
                'status' => 'privacy:metadata:items:status',
                'rejectionreason' => 'privacy:metadata:items:rejectionreason',
                'closuredate' => 'privacy:metadata:items:closuredate',
                'reviewedby' => 'privacy:metadata:items:reviewedby',
                'timereviewed' => 'privacy:metadata:items:timereviewed',
            ],
            'privacy:metadata:items'
        );

        $collection->add_database_table(
            'spotaward_status_track',
            [
                'actorid' => 'privacy:metadata:track:actorid',
                'reason' => 'privacy:metadata:track:reason',
                'timecreated' => 'privacy:metadata:track:timecreated',
            ],
            'privacy:metadata:track'
        );

        $collection->add_external_location_link(
            'zoho_cliq',
            [
                'recipientemail' => 'privacy:metadata:zohocliq:recipientemail',
                'recipientname' => 'privacy:metadata:zohocliq:recipientname',
                'nominationdetails' => 'privacy:metadata:zohocliq:nominationdetails',
            ],
            'privacy:metadata:zohocliq'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int         $userid     The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Local plugins generally store data at the system context.
        // If data is course-specific, it might be stored in course contexts, but often local plugins use system context for simplicity unless explicitly tied to a course module.
        // Since nominations are tied to courses, we could use course contexts, but to ensure all data is found and for safety, we'll map to system context or course contexts.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course} co ON co.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {spotaward_nominations} sn ON sn.courseid = co.id
                 WHERE sn.nominatorid = :userid1
                    OR sn.programmanagerid = :userid2
                    OR sn.maacexecutiveid = :userid3
                    OR sn.adminsharedby = :userid4
                    OR sn.admindownloadedby = :userid5
                 UNION
                SELECT c.id
                  FROM {context} c
                  JOIN {course} co ON co.id = c.instanceid AND c.contextlevel = :contextlevel2
                  JOIN {spotaward_nominations} sn ON sn.courseid = co.id
                  JOIN {spotaward_nomination_items} sni ON sni.nominationid = sn.id
                 WHERE sni.studentid = :userid6
                    OR sni.reviewedby = :userid7
                 UNION
                SELECT c.id
                  FROM {context} c
                  JOIN {course} co ON co.id = c.instanceid AND c.contextlevel = :contextlevel3
                  JOIN {spotaward_nominations} sn ON sn.courseid = co.id
                  JOIN {spotaward_status_track} sst ON sst.nominationid = sn.id
                 WHERE sst.actorid = :userid8";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'contextlevel2' => CONTEXT_COURSE,
            'contextlevel3' => CONTEXT_COURSE,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'userid6' => $userid,
            'userid7' => $userid,
            'userid8' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;

        $sql = "SELECT sn.nominatorid, sn.programmanagerid, sn.maacexecutiveid, sn.adminsharedby, sn.admindownloadedby
                  FROM {spotaward_nominations} sn
                 WHERE sn.courseid = :courseid";
        $userlist->add_from_sql('nominatorid', $sql, ['courseid' => $courseid]);
        $userlist->add_from_sql('programmanagerid', $sql, ['courseid' => $courseid]);
        $userlist->add_from_sql('maacexecutiveid', $sql, ['courseid' => $courseid]);
        $userlist->add_from_sql('adminsharedby', $sql, ['courseid' => $courseid]);
        $userlist->add_from_sql('admindownloadedby', $sql, ['courseid' => $courseid]);

        $sqlitems = "SELECT sni.studentid, sni.reviewedby
                       FROM {spotaward_nominations} sn
                       JOIN {spotaward_nomination_items} sni ON sni.nominationid = sn.id
                      WHERE sn.courseid = :courseid";
        $userlist->add_from_sql('studentid', $sqlitems, ['courseid' => $courseid]);
        $userlist->add_from_sql('reviewedby', $sqlitems, ['courseid' => $courseid]);

        $sqltracks = "SELECT sst.actorid
                        FROM {spotaward_nominations} sn
                        JOIN {spotaward_status_track} sst ON sst.nominationid = sn.id
                       WHERE sn.courseid = :courseid";
        $userlist->add_from_sql('actorid', $sqltracks, ['courseid' => $courseid]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            // Export Nominations where user is involved
            $sql = "SELECT sn.*
                      FROM {spotaward_nominations} sn
                     WHERE sn.courseid = ? AND (sn.nominatorid = ? OR sn.programmanagerid = ? OR sn.maacexecutiveid = ? OR sn.adminsharedby = ? OR sn.admindownloadedby = ?)";
            $nominations = $DB->get_records_sql($sql, [$courseid, $userid, $userid, $userid, $userid, $userid]);

            if (!empty($nominations)) {
                $exportdata = [];
                foreach ($nominations as $nom) {
                    $exportdata[] = (object)[
                        'modulename' => $nom->modulename,
                        'awardcategory' => $nom->awardcategory,
                        'professional' => $nom->professional,
                        'awarddescription' => $nom->awarddescription,
                        'status' => $nom->status,
                        'adminsharedtime' => $nom->adminsharedtime ? transform::datetime($nom->adminsharedtime) : null,
                        'admindownloadedtime' => $nom->admindownloadedtime
                            ? transform::datetime($nom->admindownloadedtime) : null,
                        'timecreated' => transform::datetime($nom->timecreated)
                    ];
                }
                writer::with_context($context)->export_data([get_string('pluginname', 'local_spotaward'), get_string('nominations', 'local_spotaward')], (object)['data' => $exportdata]);
            }

            // Export Items
            $sqlitems = "SELECT sni.*
                           FROM {spotaward_nominations} sn
                           JOIN {spotaward_nomination_items} sni ON sni.nominationid = sn.id
                          WHERE sn.courseid = ? AND (sni.studentid = ? OR sni.reviewedby = ?)";
            $items = $DB->get_records_sql($sqlitems, [$courseid, $userid, $userid]);

            if (!empty($items)) {
                $exportitems = [];
                foreach ($items as $item) {
                    $exportitems[] = (object)[
                        'awardcategory' => $item->awardcategory,
                        'professional' => $item->professional,
                        'awarddescription' => $item->awarddescription,
                        'status' => $item->status,
                        'rejectionreason' => $item->rejectionreason,
                        'closuredate' => $item->closuredate ? transform::datetime($item->closuredate) : null,
                        'timereviewed' => transform::datetime($item->timereviewed)
                    ];
                }
                writer::with_context($context)->export_data([get_string('pluginname', 'local_spotaward'), get_string('nominationitems', 'local_spotaward')], (object)['data' => $exportitems]);
            }

            // Export certificates which contain this student's personal data.
            $certificateitems = $DB->get_records_sql(
                "SELECT sni.id, sni.nominationid, sni.studentid
                   FROM {spotaward_nominations} sn
                   JOIN {spotaward_nomination_items} sni ON sni.nominationid = sn.id
                  WHERE sn.courseid = ? AND sni.studentid = ?",
                [$courseid, $userid]
            );
            $fs = get_file_storage();
            foreach ($certificateitems as $certificateitem) {
                $file = $fs->get_file(
                    $context->id,
                    'local_spotaward',
                    'certificates',
                    (int)$certificateitem->nominationid,
                    '/',
                    'certificate_item_' . (int)$certificateitem->id . '_user_' . $userid . '.pdf'
                );
                if (!$file) {
                    $file = $fs->get_file(
                        $context->id,
                        'local_spotaward',
                        'certificates',
                        (int)$certificateitem->nominationid,
                        '/',
                        'certificate_' . $userid . '.pdf'
                    );
                }
                if ($file) {
                    writer::with_context($context)->export_file([
                        get_string('pluginname', 'local_spotaward'),
                        get_string('privacy:certificatefiles', 'local_spotaward'),
                    ], $file);
                }
            }

            // Export Tracks
            $sqltracks = "SELECT sst.*
                            FROM {spotaward_nominations} sn
                            JOIN {spotaward_status_track} sst ON sst.nominationid = sn.id
                           WHERE sn.courseid = ? AND sst.actorid = ?";
            $tracks = $DB->get_records_sql($sqltracks, [$courseid, $userid]);

            if (!empty($tracks)) {
                $exporttracks = [];
                foreach ($tracks as $track) {
                    $exporttracks[] = (object)[
                        'fromstatus' => $track->fromstatus,
                        'tostatus' => $track->tostatus,
                        'reason' => $track->reason,
                        'timecreated' => transform::datetime($track->timecreated)
                    ];
                }
                writer::with_context($context)->export_data([get_string('pluginname', 'local_spotaward'), get_string('statustracking', 'local_spotaward')], (object)['data' => $exporttracks]);
            }
        }
    }

    /**
     * Delete all user data which matches the specified context.
     *
     * @param   \context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;

        $nominations = $DB->get_records('spotaward_nominations', ['courseid' => $courseid], '', 'id');
        if (empty($nominations)) {
            return;
        }

        $fs = get_file_storage();
        foreach (array_keys($nominations) as $nominationid) {
            $fs->delete_area_files(
                $context->id,
                'local_spotaward',
                'certificates',
                (int)$nominationid
            );
        }

        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($nominations));
        $DB->delete_records_select('spotaward_status_track', "nominationid $insql", $inparams);
        $DB->delete_records_select('spotaward_nomination_items', "nominationid $insql", $inparams);
        $DB->delete_records_select('spotaward_nominations', "id $insql", $inparams);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            // Anonymise student items.
            $sql = "SELECT sni.id, sni.nominationid, sni.studentid
                      FROM {spotaward_nominations} sn
                      JOIN {spotaward_nomination_items} sni ON sni.nominationid = sn.id
                     WHERE sn.courseid = ? AND sni.studentid = ?";
            $studentitems = $DB->get_records_sql($sql, [$courseid, $userid]);

            if (!empty($studentitems)) {
                self::delete_certificate_files_for_items($studentitems);
                $itemids = array_keys($studentitems);
                list($insql, $inparams) = $DB->get_in_or_equal($itemids);
                $DB->set_field_select('spotaward_nomination_items', 'studentid', 0, "id $insql", $inparams);
                $DB->set_field_select('spotaward_nomination_items', 'awarddescription', '', "id $insql", $inparams);
                $DB->set_field_select('spotaward_nomination_items', 'rejectionreason', '', "id $insql", $inparams);
            }

            $revieweditemids = $DB->get_fieldset_sql(
                "SELECT sni.id
                   FROM {spotaward_nominations} sn
                   JOIN {spotaward_nomination_items} sni ON sni.nominationid = sn.id
                  WHERE sn.courseid = ? AND sni.reviewedby = ?",
                [$courseid, $userid]
            );
            if (!empty($revieweditemids)) {
                list($reviewedsql, $reviewedparams) = $DB->get_in_or_equal($revieweditemids);
                $DB->set_field_select('spotaward_nomination_items', 'reviewedby', 0,
                    "id $reviewedsql", $reviewedparams);
                $DB->set_field_select('spotaward_nomination_items', 'rejectionreason', '',
                    "id $reviewedsql", $reviewedparams);
            }

            // Anonymise nomination references.
            $DB->set_field_select('spotaward_nominations', 'nominatorid', 0, "courseid = ? AND nominatorid = ?", [$courseid, $userid]);
            $DB->set_field_select('spotaward_nominations', 'programmanagerid', 0, "courseid = ? AND programmanagerid = ?", [$courseid, $userid]);
            $DB->set_field_select('spotaward_nominations', 'maacexecutiveid', 0, "courseid = ? AND maacexecutiveid = ?", [$courseid, $userid]);
            $DB->set_field_select('spotaward_nominations', 'adminsharedby', 0, "courseid = ? AND adminsharedby = ?", [$courseid, $userid]);
            $DB->set_field_select('spotaward_nominations', 'admindownloadedby', 0, "courseid = ? AND admindownloadedby = ?", [$courseid, $userid]);

            // Anonymise tracking logs
            $sqltracks = "SELECT sst.id
                            FROM {spotaward_nominations} sn
                            JOIN {spotaward_status_track} sst ON sst.nominationid = sn.id
                           WHERE sn.courseid = ? AND sst.actorid = ?";
            $trackids = $DB->get_fieldset_sql($sqltracks, [$courseid, $userid]);

            if (!empty($trackids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($trackids);
                $DB->set_field_select('spotaward_status_track', 'actorid', 0, "id $insql", $inparams);
                $DB->set_field_select('spotaward_status_track', 'reason', '', "id $insql", $inparams);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;
        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids);

        // Anonymise student items.
        $sql = "SELECT sni.id, sni.nominationid, sni.studentid
                  FROM {spotaward_nominations} sn
                  JOIN {spotaward_nomination_items} sni ON sni.nominationid = sn.id
                 WHERE sn.courseid = ? AND sni.studentid $insql";
        $studentitems = $DB->get_records_sql($sql, array_merge([$courseid], $inparams));

        if (!empty($studentitems)) {
            self::delete_certificate_files_for_items($studentitems);
            $itemids = array_keys($studentitems);
            list($iteminsql, $iteminparams) = $DB->get_in_or_equal($itemids);
            $DB->set_field_select('spotaward_nomination_items', 'studentid', 0, "id $iteminsql", $iteminparams);
            $DB->set_field_select('spotaward_nomination_items', 'awarddescription', '', "id $iteminsql", $iteminparams);
            $DB->set_field_select('spotaward_nomination_items', 'rejectionreason', '', "id $iteminsql", $iteminparams);
        }

        $revieweditemids = $DB->get_fieldset_sql(
            "SELECT sni.id
               FROM {spotaward_nominations} sn
               JOIN {spotaward_nomination_items} sni ON sni.nominationid = sn.id
              WHERE sn.courseid = ? AND sni.reviewedby $insql",
            array_merge([$courseid], $inparams)
        );
        if (!empty($revieweditemids)) {
            list($reviewedsql, $reviewedparams) = $DB->get_in_or_equal($revieweditemids);
            $DB->set_field_select('spotaward_nomination_items', 'reviewedby', 0,
                "id $reviewedsql", $reviewedparams);
            $DB->set_field_select('spotaward_nomination_items', 'rejectionreason', '',
                "id $reviewedsql", $reviewedparams);
        }

        // Anonymise nomination references.
        $DB->set_field_select('spotaward_nominations', 'nominatorid', 0, "courseid = ? AND nominatorid $insql", array_merge([$courseid], $inparams));
        $DB->set_field_select('spotaward_nominations', 'programmanagerid', 0, "courseid = ? AND programmanagerid $insql", array_merge([$courseid], $inparams));
        $DB->set_field_select('spotaward_nominations', 'maacexecutiveid', 0, "courseid = ? AND maacexecutiveid $insql", array_merge([$courseid], $inparams));
        $DB->set_field_select('spotaward_nominations', 'adminsharedby', 0, "courseid = ? AND adminsharedby $insql", array_merge([$courseid], $inparams));
        $DB->set_field_select('spotaward_nominations', 'admindownloadedby', 0, "courseid = ? AND admindownloadedby $insql", array_merge([$courseid], $inparams));

        // Anonymise tracking logs
        $sqltracks = "SELECT sst.id
                        FROM {spotaward_nominations} sn
                        JOIN {spotaward_status_track} sst ON sst.nominationid = sn.id
                       WHERE sn.courseid = ? AND sst.actorid $insql";
        $trackids = $DB->get_fieldset_sql($sqltracks, array_merge([$courseid], $inparams));

        if (!empty($trackids)) {
            list($trackinsql, $trackinparams) = $DB->get_in_or_equal($trackids);
            $DB->set_field_select('spotaward_status_track', 'actorid', 0, "id $trackinsql", $trackinparams);
            $DB->set_field_select('spotaward_status_track', 'reason', '', "id $trackinsql", $trackinparams);
        }
    }

    /**
     * Delete generated certificates which contain personal data for nominated users.
     *
     * @param array $items Nomination item records keyed by item id.
     * @return void
     */
    private static function delete_certificate_files_for_items(array $items): void {
        global $DB;

        $fs = get_file_storage();
        $contexts = [];

        foreach ($items as $item) {
            $nominationid = (int)$item->nominationid;
            if (!isset($contexts[$nominationid])) {
                $courseid = (int)$DB->get_field('spotaward_nominations', 'courseid', ['id' => $nominationid], MUST_EXIST);
                $contexts[$nominationid] = context_course::instance($courseid)->id;
            }
            $filenames = [
                'certificate_item_' . (int)$item->id . '_user_' . (int)$item->studentid . '.pdf',
                'certificate_' . (int)$item->studentid . '.pdf',
            ];
            foreach ($filenames as $filename) {
                $file = $fs->get_file(
                    $contexts[$nominationid],
                    'local_spotaward',
                    'certificates',
                    (int)$item->nominationid,
                    '/',
                    $filename
                );
                if ($file) {
                    $file->delete();
                }
            }
        }
    }
}
