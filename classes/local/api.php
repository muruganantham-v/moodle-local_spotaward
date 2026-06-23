<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\local;

use context_course;
use context_system;
use core_user;
use moodle_exception;
use moodle_url;
use stdClass;

require_once(__DIR__ . '/cert_field_map.php');
require_once(__DIR__ . '/pr_field_map.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Shared API for Spot Award System.
 *
 * @package   local_spotaward
 */
final class api {
    /**
     * Maximum allowed admin-share PDF attachment size.
     */
    private const ADMIN_SHARE_MAX_BYTES = 10485760;

    /**
     * Session key for draft entries.
     */
    private const DRAFTSESSIONKEY = 'local_spotaward_draft_entries';

    /** @var array Request-level cache for get_nomination(). */
    private static $nominationcache = [];

    /** @var array Request-level cache for get_nomination_items(). */
    private static $nominationitemscache = [];

    /**
     * Whether user can see plugin entry point.
     *
     * @param int $userid
     * @return bool
     */
    public static function user_can_access(int $userid): bool {
        return self::is_nominator($userid) || self::is_program_manager($userid) ||
            self::is_ss_team($userid) || self::is_manager($userid);
    }

    /**
     * Whether user should see the Spot Award navigation menu.
     *
     * Only system-level Spot Award role/capability assignments should expose the menu.
     * Course-only enrolments or course-level role assignments should not.
     *
     * @param int $userid
     * @return bool
     */
    public static function user_can_see_menu(int $userid): bool {
        global $DB;

        if (is_siteadmin($userid)) {
            return true;
        }

        $systemcontext = context_system::instance();
        if (has_capability('local/spotaward:nominate', $systemcontext, $userid) ||
                has_capability('local/spotaward:review', $systemcontext, $userid) ||
                has_capability('local/spotaward:sstask', $systemcontext, $userid) ||
                has_capability('local/spotaward:viewreports', $systemcontext, $userid)) {
            return true;
        }

        $roleids = array_filter([
            constants::nominator_roleid(),
            constants::program_manager_roleid(),
            constants::ss_team_roleid(),
            self::get_manager_roleid(),
        ]);
        if (empty($roleids)) {
            return false;
        }

        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['systemcontextid'] = $systemcontext->id;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments}
              WHERE userid = :userid
                AND contextid = :systemcontextid
                AND roleid $insql",
            $params
        );
    }

    /**
     * Whether user is a nominator.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_nominator(int $userid): bool {
        global $DB;
        static $cache = [];
        if (isset($cache[$userid])) {
            return $cache[$userid];
        }

        if (is_siteadmin($userid)) {
            return $cache[$userid] = true;
        }

        $systemcontext = context_system::instance();
        if (has_capability('local/spotaward:nominate', $systemcontext, $userid)) {
            return $cache[$userid] = true;
        }

        return $cache[$userid] = $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = :userid
                AND ra.roleid = :roleid
                AND ctx.contextlevel IN (:systemlevel, :courselevel)",
            [
                'userid' => $userid,
                'roleid' => constants::nominator_roleid(),
                'systemlevel' => CONTEXT_SYSTEM,
                'courselevel' => CONTEXT_COURSE,
            ]
        );
    }

    /**
     * Whether user is a program manager.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_program_manager(int $userid): bool {
        global $DB;
        static $cache = [];
        if (isset($cache[$userid])) {
            return $cache[$userid];
        }

        if (is_siteadmin($userid)) {
            return $cache[$userid] = true;
        }

        $systemcontext = context_system::instance();
        if (has_capability('local/spotaward:review', $systemcontext, $userid)) {
            return $cache[$userid] = true;
        }

        return $cache[$userid] = $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = :userid
                AND ra.roleid = :roleid
                AND ctx.contextlevel IN (:systemlevel, :courselevel)",
            [
                'userid' => $userid,
                'roleid' => constants::program_manager_roleid(),
                'systemlevel' => CONTEXT_SYSTEM,
                'courselevel' => CONTEXT_COURSE,
            ]
        );
    }

    /**
     * Whether user is part of the SS Team.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_ss_team(int $userid): bool {
        global $DB;
        static $cache = [];
        if (isset($cache[$userid])) {
            return $cache[$userid];
        }

        if (is_siteadmin($userid)) {
            return $cache[$userid] = true;
        }

        $systemcontext = context_system::instance();
        if (has_capability('local/spotaward:sstask', $systemcontext, $userid)) {
            return $cache[$userid] = true;
        }

        return $cache[$userid] = $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = :userid
                AND ra.roleid = :roleid
                AND ctx.contextlevel IN (:systemlevel, :courselevel)",
            [
                'userid' => $userid,
                'roleid' => constants::ss_team_roleid(),
                'systemlevel' => CONTEXT_SYSTEM,
                'courselevel' => CONTEXT_COURSE,
            ]
        );
    }

    /**
     * Whether user can see reporting dashboard.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_manager(int $userid): bool {
        global $DB;
        static $cache = [];
        if (isset($cache[$userid])) {
            return $cache[$userid];
        }

        if (is_siteadmin($userid)) {
            return $cache[$userid] = true;
        }

        $systemcontext = context_system::instance();

        if (has_capability('local/spotaward:viewreports', $systemcontext, $userid)) {
            return $cache[$userid] = true;
        }

        $managerroleshort = get_config('local_spotaward', 'manager_role');
        if (empty($managerroleshort)) {
            $managerroleshort = 'manager';
        }

        $managerrole = $DB->get_record('role', ['shortname' => $managerroleshort]);
        if ($managerrole) {
            $ismanager = $DB->record_exists_sql(
                "SELECT 1 FROM {role_assignments}
                 WHERE userid = ? AND roleid = ?
                 AND contextid IN (SELECT id FROM {context} WHERE contextlevel = ? AND instanceid = 0)",
                [$userid, $managerrole->id, CONTEXT_SYSTEM]
            );
            if ($ismanager) {
                return $cache[$userid] = true;
            }
        }

        return $cache[$userid] = false;
    }

    /**
     * Get nominatable courses for current user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_nominator_courses(int $userid): array {
        global $DB;
        static $cache = [];
        if (isset($cache[$userid])) {
            return $cache[$userid];
        }

        if (is_siteadmin($userid)) {
            $records = $DB->get_records_select('course', 'id <> :sitecourse', ['sitecourse' => SITEID], 'fullname ASC',
                'id, shortname, fullname');
            $options = [];
            foreach ($records as $record) {
                if (!constants::is_allowed_nomination_course_shortname((string)($record->shortname ?? ''))) {
                    continue;
                }
                $options[$record->id] = format_string($record->fullname, true, ['context' => context_course::instance($record->id)]);
            }
            return $cache[$userid] = $options;
        }

        $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname
                  FROM {course} c
                  JOIN {context} ctx
                    ON ctx.instanceid = c.id
                   AND ctx.contextlevel = :courselevel
                  JOIN {role_assignments} ra
                    ON ra.contextid = ctx.id
                   AND ra.userid = :userid
                  JOIN {role} r
                    ON r.id = ra.roleid
                 WHERE c.id <> :sitecourse
                   AND r.shortname IN ('teacher', 'editingteacher')
              ORDER BY c.fullname ASC";

        $records = $DB->get_records_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid' => $userid,
            'sitecourse' => SITEID,
        ]);

        $options = [];
        foreach ($records as $record) {
            if (!constants::is_allowed_nomination_course_shortname((string)($record->shortname ?? ''))) {
                continue;
            }
            $options[$record->id] = format_string($record->fullname, true, ['context' => context_course::instance($record->id)]);
        }

        return $cache[$userid] = $options;
    }

    /**
     * Whether user can nominate in selected course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function can_nominate_in_course(int $userid, int $courseid): bool {
        if (is_siteadmin($userid)) {
            return true;
        }

        $courses = self::get_nominator_courses($userid);
        return isset($courses[$courseid]);
    }

    /**
     * Load students for a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_course_students(int $courseid, int $userid): array {
        global $DB;

        $studentroleid = constants::student_roleid();

        if ($studentroleid <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename,
                       u.email, u.username
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE u.deleted   = 0
                   AND u.suspended = 0
                   AND ue.status   = 0
                   AND u.id       <> :userid
                   AND ctx.instanceid   = :courseid2
                   AND ctx.contextlevel = :courselevel
                   AND ra.roleid = :studentroleid
              ORDER BY u.firstname ASC, u.lastname ASC";

        return array_values($DB->get_records_sql($sql, [
            'courseid'    => $courseid,
            'courseid2'   => $courseid,
            'userid'      => $userid,
            'courselevel' => CONTEXT_COURSE,
            'studentroleid' => $studentroleid,
        ]));
    }

    /**
     * Load program managers for a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_program_managers_for_course(int $courseid): array {
        $roleid = constants::program_manager_roleid();
        if ($roleid <= 0) {
            return [];
        }

        return self::get_course_role_users_by_roleid($courseid, $roleid);
    }

    /**
     * Load MAAC Executives for a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_maac_executives_for_course(int $courseid): array {
        $roleid = constants::ss_team_roleid();
        if ($roleid <= 0) {
            return [];
        }

        return self::get_course_role_users_by_roleid($courseid, $roleid);
    }

    /**
     * Load course users for a specific role.
     *
     * @param int $courseid
     * @param int $roleid
     * @return array
     */
    private static function get_course_role_users_by_roleid(int $courseid, int $roleid): array {
        global $DB;

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND u.email <> ''
                   AND ra.roleid = :roleid
                   AND ctx.contextlevel = :courselevel
                   AND ctx.instanceid = :courseid
              ORDER BY u.firstname ASC, u.lastname ASC";

        return array_values($DB->get_records_sql($sql, [
            'roleid' => $roleid,
            'courselevel' => CONTEXT_COURSE,
            'courseid' => $courseid,
        ]));
    }

    /**
     * Get draft entries from session.
     *
     * @return array
     */
    public static function get_draft_entries(): array {
        global $SESSION;

        if (empty($SESSION->{self::DRAFTSESSIONKEY}) || !is_array($SESSION->{self::DRAFTSESSIONKEY})) {
            $SESSION->{self::DRAFTSESSIONKEY} = [];
        }

        return $SESSION->{self::DRAFTSESSIONKEY};
    }

    /**
     * Clear session draft entries.
     *
     * @return void
     */
    public static function clear_draft_entries(): void {
        global $SESSION;
        $SESSION->{self::DRAFTSESSIONKEY} = [];
    }

    /**
     * Add a draft entry from submitted data.
     *
     * @param stdClass $data
     * @param int $userid
     * @return void
     */
    public static function replace_draft_entries(stdClass $data, int $userid): void {
        global $SESSION;

        if (empty($data->courseid) ||
                empty($data->professional) || empty($data->programmanagerid) || empty($data->maacexecutiveid)) {
            throw new moodle_exception('draftvalidationerror', 'local_spotaward');
        }

        $courseid = (int)$data->courseid;

        if (!self::can_nominate_in_course($userid, $courseid)) {
            throw new moodle_exception('cannotnominatecourse', 'local_spotaward');
        }

        $course = get_course($courseid);
        $awardallocations = json_decode((string)($data->awardpayload ?? ''), true);
        if (!is_array($awardallocations) || empty($awardallocations)) {
            throw new moodle_exception('awardcategoryrequired', 'local_spotaward');
        }

        if (empty($data->modulename)) {
            $data->modulename = constants::module_for_course((string)$course->shortname, (string)$course->fullname);
        }
        if (empty($data->modulename)) {
            throw new moodle_exception('draftvalidationerror', 'local_spotaward');
        }

        $programmanager = core_user::get_user((int)$data->programmanagerid, '*', MUST_EXIST);
        $maacexecutive = core_user::get_user((int)$data->maacexecutiveid, '*', MUST_EXIST);
        $professional = self::normalize_professional_for_course($courseid, clean_param($data->professional, PARAM_TEXT));
        $modulename = clean_param($data->modulename, PARAM_TEXT);

        $allowedcategories = constants::award_categories_for_course((string)$course->shortname, (string)$course->fullname);
        $allowedcategorykeys = array_keys($allowedcategories);
        $validstudents = [];
        foreach (self::get_course_students($courseid, $userid) as $student) {
            $validstudents[(int)$student->id] = true;
        }

        $validprogrammanagers = [];
        foreach (self::get_program_managers_for_course($courseid) as $user) {
            $validprogrammanagers[(int)$user->id] = true;
        }
        if (empty($validprogrammanagers[(int)$data->programmanagerid])) {
            throw new moodle_exception('invalidprogrammanager', 'local_spotaward');
        }

        $validmaacexecutives = [];
        foreach (self::get_maac_executives_for_course($courseid) as $user) {
            $validmaacexecutives[(int)$user->id] = true;
        }
        if (empty($validmaacexecutives[(int)$data->maacexecutiveid])) {
            throw new moodle_exception('invalidmaacexecutive', 'local_spotaward');
        }

        $entries = [];
        foreach ($awardallocations as $awardcategory => $studentids) {
            $awardcategory = clean_param((string)$awardcategory, PARAM_TEXT);
            if ($awardcategory === '' || !in_array($awardcategory, $allowedcategorykeys, true)) {
                continue;
            }

            $studentids = array_values(array_unique(array_filter(array_map('intval', (array)$studentids))));
            if (empty($studentids)) {
                continue;
            }

            foreach ($studentids as $studentid) {
                if (empty($validstudents[$studentid])) {
                    throw new moodle_exception('invalidstudent', 'local_spotaward');
                }
            }

            $awarddescription = constants::generated_award_description($awardcategory, $modulename);
            if ($awarddescription === '') {
                $awarddescription = '';
            }

            $entries[] = [
                'draftid' => uniqid('spotaward_', true),
                'courseid' => $courseid,
                'coursename' => format_string($course->fullname, true, ['context' => context_course::instance($courseid)]),
                'modulename' => $modulename,
                'awardcategory' => $awardcategory,
                'professional' => $professional,
                'awarddescription' => $awarddescription,
                'programmanagerid' => (int)$data->programmanagerid,
                'programmanagername' => fullname($programmanager),
                'maacexecutiveid' => (int)$data->maacexecutiveid,
                'maacexecutivename' => fullname($maacexecutive),
                'studentids' => $studentids,
            ];
        }

        if (empty($entries)) {
            throw new moodle_exception('awardcategoryrequired', 'local_spotaward');
        }

        $SESSION->{self::DRAFTSESSIONKEY} = $entries;
    }

    /**
     * Flatten draft entries for preview table.
     *
     * @param int $userid
     * @return array
     */
    public static function get_draft_preview_rows(int $userid): array {
        global $DB;

        $entries = self::get_draft_entries();
        if (empty($entries)) {
            return [];
        }

        $studentids = [];
        foreach ($entries as $entry) {
            foreach ($entry['studentids'] as $sid) {
                $studentids[(int)$sid] = true;
            }
        }

        $students = $DB->get_records_list('user', 'id', array_keys($studentids),
            '', 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email, username');
        $mentor = core_user::get_user($userid);

        $rows = [];
        foreach ($entries as $entry) {
            foreach ($entry['studentids'] as $studentid) {
                $student = $students[(int)$studentid] ?? null;
                if (!$student) {
                    continue;
                }
                $rows[] = [
                    'studentname' => fullname($student),
                    'studentemail' => s($student->email),
                    'admissionid' => s($student->username),
                    'batchname' => s($entry['coursename']),
                    'module' => s($entry['modulename']),
                    'mentorname' => fullname($mentor),
                    'awardcategory' => s($entry['awardcategory']),
                    'professional' => s($entry['professional'] ?? ''),
                    'awarddescription' => $entry['awarddescription'],  // Allow HTML formatting tags to render in preview
                    'programmanagername' => s($entry['programmanagername']),
                    'maacexecutivename' => s($entry['maacexecutivename'] ?? ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * Persist all draft entries and email program managers.
     *
     * @param int $userid
     * @return array
     */
    public static function submit_draft_entries(int $userid): array {
        global $DB;

        $entries = self::get_draft_entries();
        if (empty($entries)) {
            throw new moodle_exception('nodraftentries', 'local_spotaward');
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();

        $firstEntry = reset($entries);
        $nomination = (object)[
            'nominatorid' => $userid,
            'programmanagerid' => (int)$firstEntry['programmanagerid'],
            'maacexecutiveid' => (int)($firstEntry['maacexecutiveid'] ?? 0),
            'courseid' => (int)$firstEntry['courseid'],
            'modulename' => $firstEntry['modulename'],
            'awardcategory' => $firstEntry['awardcategory'],
            'professional' => $firstEntry['professional'] ?? '',
            'awarddescription' => $firstEntry['awarddescription'],
            'studentcount' => 0,
            'status' => 'pending',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $nominationid = $DB->insert_record('spotaward_nominations', $nomination);

        $descriptions = [];
        $studentCount = 0;

        foreach ($entries as $entry) {
            $descriptions[] = $entry['awardcategory'] . ': ' . $entry['awarddescription'];
            foreach ($entry['studentids'] as $studentid) {
                $studentCount++;

                $itemid = $DB->insert_record('spotaward_nomination_items', (object)[
                    'nominationid' => $nominationid,
                    'studentid' => (int)$studentid,
                    'awardcategory' => $entry['awardcategory'],
                    'professional' => $entry['professional'] ?? '',
                    'awarddescription' => $entry['awarddescription'],
                    'status' => 'pending',
                    'rejectionreason' => null,
                    'reviewedby' => 0,
                    'timereviewed' => 0,
                ]);

                $DB->insert_record('spotaward_status_track', (object)[
                    'nominationid' => $nominationid,
                    'nominationitemid' => $itemid,
                    'actorid' => $userid,
                    'fromstatus' => '',
                    'tostatus' => 'pending',
                    'reason' => null,
                    'timecreated' => $now,
                ]);
            }
        }

        $DB->set_field('spotaward_nominations', 'awarddescription', implode("\n\n", $descriptions), ['id' => $nominationid]);
        $DB->set_field('spotaward_nominations', 'studentcount', $studentCount, ['id' => $nominationid]);

        $transaction->allow_commit();

        self::clear_draft_entries();

        self::send_program_manager_notification($nominationid);
        self::send_submission_notification_to_ss_team($nominationid);
        self::send_submission_notification_to_mentor($nominationid);

        return [$nominationid];
    }

    /**
     * Send email notification to assigned program manager.
     *
     * @param int $nominationid
     * @return void
     */
    public static function send_program_manager_notification(int $nominationid): void {
        $nomination = self::get_nomination($nominationid);
        $programmanager = core_user::get_user($nomination->programmanagerid);
        if (!$programmanager || empty($programmanager->email)) {
            return;
        }

        self::send_configured_notification(
            [$programmanager],
            'submission_pm_subject',
            'submission_pm_body',
            'submission_pm_subject_default',
            'submission_pm_body_default',
            self::build_nomination_email_data($nominationid)
        );
    }

    /**
     * Send submission notification to assigned SS Team member.
     *
     * @param int $nominationid
     * @return void
     */
    public static function send_submission_notification_to_ss_team(int $nominationid): void {
        $nomination = self::get_nomination($nominationid);
        if (empty($nomination->maacexecutiveid)) {
            return;
        }

        $recipient = core_user::get_user((int)$nomination->maacexecutiveid);
        if (!$recipient || empty($recipient->email)) {
            return;
        }

        self::send_configured_notification(
            [$recipient],
            'submission_ss_subject',
            'submission_ss_body',
            'submission_ss_subject_default',
            'submission_ss_body_default',
            self::build_nomination_email_data($nominationid)
        );
    }

    /**
     * Send submission confirmation to the nominator/mentor.
     *
     * @param int $nominationid
     * @return void
     */
    public static function send_submission_notification_to_mentor(int $nominationid): void {
        $nomination = self::get_nomination($nominationid);
        $nominator = core_user::get_user($nomination->nominatorid);

        if (!$nominator || empty($nominator->email)) {
            return;
        }

        self::send_configured_notification(
            [$nominator],
            'submission_mentor_subject',
            'submission_mentor_body',
            'submission_mentor_subject_default',
            'submission_mentor_body_default',
            self::build_nomination_email_data($nominationid)
        );
    }

    /**
     * Build email data for a nomination.
     *
     * @param int $nominationid
     * @param array $extra
     * @return stdClass
     */
    private static function build_nomination_email_data(int $nominationid, array $extra = []): stdClass {
        $nomination = self::get_nomination($nominationid);
        $course = get_course($nomination->courseid);
        $nominator = core_user::get_user($nomination->nominatorid);
        $programmanager = core_user::get_user($nomination->programmanagerid);
        $maacexecutive = !empty($nomination->maacexecutiveid)
            ? core_user::get_user($nomination->maacexecutiveid)
            : null;
        [$awardsummary, $awardsummaryhtml] = self::get_nomination_award_summary($nominationid);
        $rejectionreasons = self::get_nomination_rejection_reasons($nominationid);

        $data = [
            'course' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
            'module' => $nomination->modulename ?? '',
            'categories' => $nomination->awarddescription ?? '',
            'students' => (string)($nomination->studentcount ?? 0),
            'mentor' => $nominator ? fullname($nominator) : '',
            'programmanager' => $programmanager ? fullname($programmanager) : '',
            'maacexecutive' => $maacexecutive ? fullname($maacexecutive) : '',
            'mentor_name' => $nominator ? fullname($nominator) : '',
            'program_manager_name' => $programmanager ? fullname($programmanager) : '',
            'maac_executive_name' => $maacexecutive ? fullname($maacexecutive) : '',
            'nominator_name' => $nominator ? fullname($nominator) : '',
            'professional' => $nomination->professional ?? '',
            'status' => get_string($nomination->status, 'local_spotaward'),
            'decision' => '',
            'reason' => $rejectionreasons,
            'pm_comments' => $rejectionreasons,
            'description' => $nomination->awarddescription ?? '',
            'award_summary' => $awardsummary,
            'award_summary_html' => $awardsummaryhtml,
            'total_students' => (string)($nomination->studentcount ?? 0),
            'certificate_mode' => 'PR Raised for Printing (Offline)',
            'url' => (new moodle_url('/local/spotaward/submission.php', ['id' => $nominationid]))->out(false),
            'moodle_link' => (new moodle_url('/local/spotaward/submission.php', ['id' => $nominationid]))->out(false),
            'recipient_name' => '',
        ];

        foreach ($extra as $key => $value) {
            $data[$key] = (string)$value;
        }

        return (object)$data;
    }

    /**
     * Build award-wise nomination summary for notifications.
     *
     * @param int $nominationid
     * @return array
     */
    private static function get_nomination_award_summary(int $nominationid): array {
        global $DB;
        static $cache = [];
        if (isset($cache[$nominationid])) {
            return $cache[$nominationid];
        }

        $sql = "SELECT awardcategory, COUNT(1) AS studentcount
                  FROM {spotaward_nomination_items}
                 WHERE nominationid = :nominationid
              GROUP BY awardcategory
              ORDER BY awardcategory ASC";
        $records = $DB->get_records_sql($sql, ['nominationid' => $nominationid]);

        if (empty($records)) {
            return $cache[$nominationid] = ['', ''];
        }

        $textlines = [];
        $htmlrows = [];
        foreach ($records as $record) {
            $category = (string)$record->awardcategory;
            $count = (int)$record->studentcount;
            $textlines[] = $category . ': ' . $count . ' student' . ($count === 1 ? '' : 's');
            $htmlrows[] = '<tr><td>' . s($category) . '</td><td>' . $count . '</td></tr>';
        }

        $htmltable = '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;">'
            . '<thead><tr><th align="left">Award Category</th><th align="left">Students</th></tr></thead>'
            . '<tbody>' . implode('', $htmlrows) . '</tbody></table>';

        return $cache[$nominationid] = [implode("\n", $textlines), $htmltable];
    }

    /**
     * Get configured users from a multiselect config field.
     *
     * @param string $configkey
     * @return array
     */
    private static function get_configured_users(string $configkey): array {
        global $DB;

        $value = get_config('local_spotaward', $configkey);
        if (empty($value)) {
            return [];
        }

        $emails = preg_split('/[\s,;]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
        $users = [];

        foreach ($emails as $email) {
            $email = trim(strtolower($email));
            if ($email === '' || !validate_email($email)) {
                continue;
            }

            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0, 'suspended' => 0],
                'id, firstname, lastname, email');
            if ($user) {
                $users[$user->id] = $user;
            }
        }

        return array_values($users);
    }

    /**
     * Get active SS Team users from the configured SS Team role.
     *
     * @return array
     */
    private static function get_ss_team_users(): array {
        global $DB;

        $roleid = constants::ss_team_roleid();
        if ($roleid <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND u.email <> ''
                   AND ra.roleid = :roleid
                   AND ctx.contextlevel IN (:systemlevel, :courselevel)
              ORDER BY u.firstname ASC, u.lastname ASC";

        return array_values($DB->get_records_sql($sql, [
            'roleid' => $roleid,
            'systemlevel' => CONTEXT_SYSTEM,
            'courselevel' => CONTEXT_COURSE,
        ]));
    }

    /**
     * Send an uploaded PR document to the configured admin user.
     *
     * @param int $nominationid
     * @param int $actorid
     * @param string $filepath
     * @param string $filename
     * @param bool $attachcertificates
     * @return void
     */
    public static function send_pr_document_to_admin(int $nominationid, int $actorid, string $filepath, string $filename,
            bool $attachcertificates = true): void {
        $nomination = self::get_nomination($nominationid);
        self::require_nomination_access($nomination, $actorid);

        if (!self::is_ss_team($actorid) && !is_siteadmin($actorid)) {
            throw new moodle_exception('notauthorised', 'local_spotaward');
        }

        if (!in_array($nomination->status, ['ssteamprogress', 'closed'], true)) {
            throw new moodle_exception('invalidparameter');
        }

        $recipients = self::get_configured_users('admin_team_members');
        if (empty($recipients)) {
            throw new moodle_exception('noadminconfigured', 'local_spotaward');
        }
        $nominator = core_user::get_user($nomination->nominatorid);
        if ($nominator && !empty($nominator->email)) {
            $recipients[] = $nominator;
        }
        $programmanager = core_user::get_user($nomination->programmanagerid);
        if ($programmanager && !empty($programmanager->email)) {
            $recipients[] = $programmanager;
        }

        $attachment = [
            'path' => $filepath,
            'name' => $filename,
        ];
        $temporaryfiles = [];

        try {
            if ($attachcertificates) {
                $certificatepdf = self::build_combined_certificate_pdf_attachment($nominationid);
                $temporaryfiles[] = $certificatepdf['path'];

                $attachment = self::build_admin_documents_bundle($nominationid, $filepath, $filename, $certificatepdf);
                $temporaryfiles[] = $attachment['path'];
            }

            self::send_configured_notification(
                $recipients,
                'ss_to_admin_subject',
                'ss_to_admin_body',
                'ss_to_admin_subject_default',
                'ss_to_admin_body_default',
                self::build_nomination_email_data($nominationid),
                $attachment
            );

            global $DB;
            $DB->update_record('spotaward_nominations', (object)[
                'id' => $nominationid,
                'adminsharedtime' => time(),
                'adminsharedby' => $actorid,
                'timemodified' => time(),
            ]);
        } finally {
            foreach ($temporaryfiles as $temporaryfile) {
                if (is_file($temporaryfile)) {
                    @unlink($temporaryfile);
                }
            }
        }
    }

    /**
     * Send notification when a record is closed.
     *
     * @param int $nominationid
     * @param int $actorid
     * @param int $closuredate
     * @return void
     */
    private static function send_record_closed_notification(int $nominationid, int $actorid, int $closuredate): void {
        $nomination = self::get_nomination($nominationid);
        $recipients = [];

        $nominator = core_user::get_user($nomination->nominatorid);
        if ($nominator && !empty($nominator->email)) {
            $recipients[] = $nominator;
        }

        $programmanager = core_user::get_user($nomination->programmanagerid);
        if ($programmanager && !empty($programmanager->email)) {
            $recipients[] = $programmanager;
        }

        if (!empty($nomination->maacexecutiveid)) {
            $maacexecutive = core_user::get_user((int)$nomination->maacexecutiveid);
            if ($maacexecutive && !empty($maacexecutive->email)) {
                $recipients[] = $maacexecutive;
            }
        }

        if (empty($recipients)) {
            return;
        }

        $actor = core_user::get_user($actorid);
        self::send_configured_notification(
            $recipients,
            'record_closed_subject',
            'record_closed_body',
            'record_closed_subject_default',
            'record_closed_body_default',
            self::build_nomination_email_data($nominationid, [
                'closure_date' => userdate($closuredate, get_string('strftimedate')),
                'closed_by' => $actor ? fullname($actor) : '',
            ])
        );
    }

    /**
     * Send notification from configured template fields.
     *
     * @param array $recipients
     * @param string $subjectkey
     * @param string $bodykey
     * @param string $defaultsubjectkey
     * @param string $defaultbodykey
     * @param stdClass $data
     * @param array|null $attachment
     * @return void
     */
    private static function send_configured_notification(array $recipients, string $subjectkey, string $bodykey,
            string $defaultsubjectkey, string $defaultbodykey, stdClass $data, ?array $attachment = null,
            bool $sendcliq = true): void {
        $subjecttemplate = (string)get_config('local_spotaward', $subjectkey);
        $bodytemplate = (string)get_config('local_spotaward', $bodykey);

        if ($subjecttemplate === '') {
            $subjecttemplate = get_string($defaultsubjectkey, 'local_spotaward');
        }
        if ($bodytemplate === '') {
            $bodytemplate = get_string($defaultbodykey, 'local_spotaward');
        }

        $deduped = [];
        foreach ($recipients as $recipient) {
            if (empty($recipient->email)) {
                continue;
            }
            $deduped[$recipient->email] = $recipient;
        }

        foreach ($deduped as $recipient) {
            $recipientdata = clone $data;
            $recipientname = fullname($recipient);
            $recipientdata->recipient_name = $recipientname;
            $renderedsubject = self::render_notification_template($subjecttemplate, $recipientdata);
            $renderedbody = self::render_notification_template($bodytemplate, $recipientdata);
            if (self::notification_body_is_html($renderedbody)) {
                $plaintextbody = function_exists('html_to_text') ? html_to_text($renderedbody) : trim(strip_tags($renderedbody));
                $htmlbody = $renderedbody;
            } else {
                $plaintextbody = $renderedbody;
                $htmlbody = text_to_html($renderedbody, false, false, true);
            }
            email_to_user(
                $recipient,
                core_user::get_support_user(),
                $renderedsubject,
                $plaintextbody,
                $htmlbody,
                $attachment['path'] ?? '',
                $attachment['name'] ?? ''
            );
        }

        if (!$sendcliq) {
            return;
        }

        $cliqsubjecttemplate = (string)get_config('local_spotaward', 'cliq_' . $subjectkey);
        $cliqbodytemplate = (string)get_config('local_spotaward', 'cliq_' . $bodykey);
        if ($cliqsubjecttemplate === '') {
            $cliqsubjecttemplate = get_string('cliq_' . $defaultsubjectkey, 'local_spotaward');
        }
        if ($cliqbodytemplate === '') {
            $cliqbodytemplate = get_string('cliq_' . $defaultbodykey, 'local_spotaward');
        }

        self::send_zoho_cliq_notification($deduped, $cliqsubjecttemplate, $cliqbodytemplate, $data);
    }

    /**
     * Whether a notification body contains HTML intended for email rendering.
     *
     * @param string $body
     * @return bool
     */
    private static function notification_body_is_html(string $body): bool {
        return (bool)preg_match('/<\s*(table|thead|tbody|tr|th|td|p|br|div|strong|ul|ol|li)\b/i', $body);
    }

    /**
     * Send rendered notification text to Zoho Cliq bot users.
     *
     * @param array $recipients
     * @param string $subjecttemplate
     * @param string $bodytemplate
     * @param stdClass $data
     * @return void
     */
    private static function send_zoho_cliq_notification(array $recipients, string $subjecttemplate, string $bodytemplate,
            stdClass $data): void {
        global $CFG;

        if (empty($recipients)) {
            return;
        }

        $boturl = trim((string)get_config('local_spotaward', 'zohocliq_bot_url'));
        $apikey = trim((string)get_config('local_spotaward', 'zohocliq_api_key'));
        if ($boturl === '' || $apikey === '') {
            return;
        }

        $emails = [];
        foreach ($recipients as $recipient) {
            if (!empty($recipient->email)) {
                $emails[] = $recipient->email;
            }
        }
        $emails = array_values(array_unique($emails));
        if (empty($emails)) {
            return;
        }

        $messagedata = clone $data;
        if (count($recipients) === 1) {
            $recipient = reset($recipients);
            $messagedata->recipient_name = fullname($recipient);
        } else if (empty($messagedata->recipient_name)) {
            $messagedata->recipient_name = 'Team';
        }

        $subject = self::render_notification_template($subjecttemplate, $messagedata);
        $body = self::render_notification_template($bodytemplate, $messagedata);
        $message = '*' . $subject . "*\n\n" . $body;
        $url = self::build_zoho_cliq_bot_url($boturl, $apikey);

        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');

        $payload = [
            'text' => $message,
            'userids' => implode(',', $emails),
        ];
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FAILONERROR' => false,
        ];

        try {
            $response = $curl->post($url, json_encode($payload), $options);
            $info = $curl->get_info();
            $httpcode = (int)($info['http_code'] ?? 0);
            if ($httpcode >= 400) {
                debugging('Zoho Cliq notification failed with HTTP ' . $httpcode . ': ' . $response, DEBUG_DEVELOPER);
            }
        } catch (\Throwable $e) {
            debugging('Zoho Cliq notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Build Zoho Cliq bot API URL with zapikey query parameter.
     *
     * @param string $boturl
     * @param string $apikey
     * @return string
     */
    private static function build_zoho_cliq_bot_url(string $boturl, string $apikey): string {
        if (strpos($boturl, '{zapikey}') !== false) {
            return str_replace('{zapikey}', rawurlencode($apikey), $boturl);
        }

        if (strpos($boturl, 'zapikey=') !== false) {
            return $boturl;
        }

        $separator = strpos($boturl, '?') === false ? '?' : '&';
        return $boturl . $separator . 'zapikey=' . rawurlencode($apikey);
    }

    /**
     * Render placeholders in notification template content.
     *
     * @param string $template
     * @param stdClass $data
     * @return string
     */
    private static function render_notification_template(string $template, stdClass $data): string {
        $replacements = [];
        foreach (get_object_vars($data) as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string)$value;
        }

        return strtr($template, $replacements);
    }

    /**
     * Get aggregated rejection reasons for a nomination.
     *
     * @param int $nominationid
     * @return string
     */
    private static function get_nomination_rejection_reasons(int $nominationid): string {
        $reasons = [];
        foreach (self::get_nomination_items($nominationid) as $item) {
            $reason = trim((string)($item->rejectionreason ?? ''));
            if ($reason !== '') {
                $reasons[$reason] = $reason;
            }
        }

        return implode('; ', $reasons);
    }

    /**
     * Send notification when PM finishes approval.
     *
     * @param int $nominationid
     * @return void
     */
    private static function send_program_manager_approval_to_ss_notification(int $nominationid): void {
        $nomination = self::get_nomination($nominationid);
        if (empty($nomination->maacexecutiveid)) {
            return;
        }

        $recipient = core_user::get_user((int)$nomination->maacexecutiveid);
        if (!$recipient || empty($recipient->email)) {
            return;
        }

        self::send_configured_notification(
            [$recipient],
            'pm_to_ss_subject',
            'pm_to_ss_body',
            'pm_to_ss_subject_default',
            'pm_to_ss_body_default',
            self::build_nomination_email_data($nominationid, ['decision' => 'approved'])
        );
    }

    /**
     * Send notification to mentor when PM completes decision.
     *
     * @param int $nominationid
     * @param string $decision
     * @return void
     */
    private static function send_program_manager_decision_to_mentor_notification(int $nominationid, string $decision): void {
        $nomination = self::get_nomination($nominationid);
        $nominator = core_user::get_user($nomination->nominatorid);
        if (!$nominator || empty($nominator->email)) {
            return;
        }

        self::send_configured_notification(
            [$nominator],
            'pm_to_mentor_subject',
            'pm_to_mentor_body',
            'pm_to_mentor_subject_default',
            'pm_to_mentor_body_default',
            self::build_nomination_email_data($nominationid, [
                'decision' => $decision,
                'decision_message' => self::get_program_manager_decision_message($decision),
            ])
        );
    }

    /**
     * Send notification to Program Manager when review outcome changes the workflow.
     *
     * @param int $nominationid
     * @param string $decision
     * @return void
     */
    private static function send_program_manager_decision_to_program_manager_notification(int $nominationid, string $decision): void {
        $nomination = self::get_nomination($nominationid);
        $programmanager = core_user::get_user($nomination->programmanagerid);
        if (!$programmanager || empty($programmanager->email)) {
            return;
        }

        self::send_configured_notification(
            [$programmanager],
            'pm_to_pm_subject',
            'pm_to_pm_body',
            'pm_to_pm_subject_default',
            'pm_to_pm_body_default',
            self::build_nomination_email_data($nominationid, [
                'decision' => $decision,
                'decision_message' => self::get_program_manager_decision_message($decision),
            ])
        );
    }

    /**
     * Get human-readable workflow message for PM decision notifications.
     *
     * @param string $decision
     * @return string
     */
    private static function get_program_manager_decision_message(string $decision): string {
        if ($decision === 'approved') {
            return 'The nomination has been approved and moved to the SS Team process.';
        }

        if ($decision === 'rejected') {
            return 'The nomination has been rejected by the Program Manager.';
        }

        return 'The nomination has been updated.';
    }

    /**
     * Share each generated certificate with its student by email.
     *
     * @param int $nominationid
     * @return int Number of student emails sent.
     */
    public static function share_certificates_to_students(int $nominationid): int {
        self::ensure_nomination_certificates_generated($nominationid);

        $sentcount = 0;
        $items = self::get_nomination_items($nominationid);
        $tempdir = make_temp_directory('local_spotaward');

        $allfiles = self::get_all_certificate_files($nominationid);
        $filemap = [];
        foreach ($allfiles as $f) {
            $filemap[$f->get_filename()] = $f;
        }

        foreach ($items as $item) {
            if (!in_array($item->status, ['ssteamprogress', 'closed'], true)) {
                continue;
            }

            $student = core_user::get_user($item->studentid);
            if (!$student || empty($student->email)) {
                continue;
            }

            $certfilename = self::get_certificate_filename((int)$item->id, (int)$student->id);
            $certificatefile = $filemap[$certfilename] ?? null;
            if (!$certificatefile) {
                continue;
            }

            $studentname = fullname($student);
            $filename = clean_filename('Spot_Award_Certificate_' . $studentname . '.pdf');
            if ($filename === '') {
                $filename = 'Spot_Award_Certificate.pdf';
            }

            $temppath = tempnam($tempdir, 'spotawardcert');
            if ($temppath === false) {
                continue;
            }

            try {
                if (file_put_contents($temppath, $certificatefile->get_content()) === false) {
                    continue;
                }
                self::send_configured_notification(
                    [$student],
                    'student_certificate_subject',
                    'student_certificate_body',
                    'student_certificate_subject_default',
                    'student_certificate_body_default',
                    self::build_nomination_email_data($nominationid, [
                        'student_name' => $studentname,
                        'student_firstname' => $student->firstname ?? '',
                        'student_lastname' => $student->lastname ?? '',
                        'student_email' => $student->email ?? '',
                        'student_username' => $student->username ?? '',
                        'award_category' => $item->awardcategory ?? '',
                        'award_description' => $item->awarddescription ?? '',
                        'certificate_filename' => $filename,
                    ]),
                    [
                        'path' => $temppath,
                        'name' => $filename,
                    ],
                    false
                );
                $sentcount++;
            } finally {
                if (is_file($temppath)) {
                    @unlink($temppath);
                }
            }
        }

        return $sentcount;
    }

    /**
     * Share selected generated certificates with their students by email.
     *
     * @param int $nominationid
     * @param array $itemids
     * @return int Number of student emails sent.
     */
    public static function share_selected_certificates_to_students(int $nominationid, array $itemids): int {
        $itemids = self::normalise_nomination_item_ids($itemids);
        if (empty($itemids)) {
            throw new moodle_exception('selectstudentsforbulkcert', 'local_spotaward');
        }

        self::ensure_selected_nomination_certificates_generated($nominationid, $itemids);

        $sentcount = 0;
        $items = self::get_selected_certificate_items($nominationid, $itemids);
        $tempdir = make_temp_directory('local_spotaward');

        $allfiles = self::get_all_certificate_files($nominationid);
        $filemap = [];
        foreach ($allfiles as $f) {
            $filemap[$f->get_filename()] = $f;
        }

        foreach ($items as $item) {
            $student = core_user::get_user($item->studentid);
            if (!$student || empty($student->email)) {
                continue;
            }

            $certfilename = self::get_certificate_filename((int)$item->id, (int)$student->id);
            $certificatefile = $filemap[$certfilename] ?? null;
            if (!$certificatefile) {
                continue;
            }

            $studentname = fullname($student);
            $filename = clean_filename('Spot_Award_Certificate_' . $studentname . '.pdf');
            if ($filename === '') {
                $filename = 'Spot_Award_Certificate.pdf';
            }

            $temppath = tempnam($tempdir, 'spotawardcert');
            if ($temppath === false) {
                continue;
            }

            try {
                if (file_put_contents($temppath, $certificatefile->get_content()) === false) {
                    continue;
                }
                self::send_configured_notification(
                    [$student],
                    'student_certificate_subject',
                    'student_certificate_body',
                    'student_certificate_subject_default',
                    'student_certificate_body_default',
                    self::build_nomination_email_data($nominationid, [
                        'student_name' => $studentname,
                        'student_firstname' => $student->firstname ?? '',
                        'student_lastname' => $student->lastname ?? '',
                        'student_email' => $student->email ?? '',
                        'student_username' => $student->username ?? '',
                        'award_category' => $item->awardcategory ?? '',
                        'award_description' => $item->awarddescription ?? '',
                        'certificate_filename' => $filename,
                    ]),
                    [
                        'path' => $temppath,
                        'name' => $filename,
                    ],
                    false
                );
                $sentcount++;
            } finally {
                if (is_file($temppath)) {
                    @unlink($temppath);
                }
            }
        }

        return $sentcount;
    }

    /**
     * Get nomination record.
     *
     * @param int $nominationid
     * @return stdClass
     */
    public static function get_nomination(int $nominationid): stdClass {
        global $DB;
        if (!isset(self::$nominationcache[$nominationid])) {
            self::$nominationcache[$nominationid] = $DB->get_record('spotaward_nominations', ['id' => $nominationid], '*', MUST_EXIST);
        }
        return self::$nominationcache[$nominationid];
    }

    /**
     * Get nomination items with student data.
     *
     * @param int $nominationid
     * @return array
     */
    public static function get_nomination_items(int $nominationid): array {
        global $DB;
        if (!isset(self::$nominationitemscache[$nominationid])) {
            $sql = "SELECT ni.*,
                             u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                             u.middlename, u.alternatename, u.email, u.username
                      FROM {spotaward_nomination_items} ni
                      JOIN {user} u ON u.id = ni.studentid
                     WHERE ni.nominationid = :nominationid
                  ORDER BY u.firstname ASC, u.lastname ASC";
            self::$nominationitemscache[$nominationid] = array_values($DB->get_records_sql($sql, ['nominationid' => $nominationid]));
        }
        return self::$nominationitemscache[$nominationid];
    }


    /**
     * Normalise selected nomination item IDs.
     *
     * @param array $itemids
     * @return array
     */
    private static function normalise_nomination_item_ids(array $itemids): array {
        return array_values(array_unique(array_filter(array_map('intval', $itemids))));
    }

    /**
     * Get selected certificate-eligible nomination items.
     *
     * @param int $nominationid
     * @param array $itemids
     * @return array
     */
    private static function get_selected_certificate_items(int $nominationid, array $itemids): array {
        global $DB;

        $itemids = self::normalise_nomination_item_ids($itemids);
        if (empty($itemids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'itemid');
        $params['nominationid'] = $nominationid;

        $sql = "SELECT ni.*, u.firstname, u.lastname, u.email, u.username
                  FROM {spotaward_nomination_items} ni
                  JOIN {user} u ON u.id = ni.studentid
                 WHERE ni.nominationid = :nominationid
                   AND ni.id $insql
                   AND ni.status IN ('ssteamprogress', 'closed')
              ORDER BY u.firstname ASC, u.lastname ASC";

        $items = array_values($DB->get_records_sql($sql, $params));
        if (count($items) !== count($itemids)) {
            throw new moodle_exception('invalidparameter');
        }

        return $items;
    }

    /**
     * Whether the selected course only allows Embedded Professional.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_requires_embedded_professional(int $courseid): bool {
        if ($courseid <= 0) {
            return false;
        }

        $course = get_course($courseid);
        $haystack = strtoupper(($course->shortname ?? '') . ' ' . ($course->fullname ?? ''));
        return strpos($haystack, '24024C') !== false;
    }

    /**
     * Normalize professional value for a course.
     *
     * @param int $courseid
     * @param string $professional
     * @return string
     */
    public static function normalize_professional_for_course(int $courseid, string $professional): string {
        $professional = trim($professional);

        if (self::course_requires_embedded_professional($courseid)) {
            return 'Embedded Professional';
        }

        $options = constants::professionals();
        return array_key_exists($professional, $options) ? $professional : '';
    }

    /**
     * Get status history.
     *
     * @param int $nominationid
     * @return array
     */
    public static function get_status_history(int $nominationid): array {
        global $DB;

        $sql = "SELECT st.*, u.firstname, u.lastname, u2.firstname AS studentfirstname, u2.lastname AS studentlastname
                  FROM {spotaward_status_track} st
             LEFT JOIN {user} u ON u.id = st.actorid
             LEFT JOIN {spotaward_nomination_items} ni ON ni.id = st.nominationitemid
             LEFT JOIN {user} u2 ON u2.id = ni.studentid
                  WHERE st.nominationid = :nominationid
                    AND st.tostatus != 'pending'
               ORDER BY st.nominationitemid ASC, st.timecreated DESC";

        return array_values($DB->get_records_sql($sql, ['nominationid' => $nominationid]));
    }

/**
     * Render Mustache placeholders for spotaward data.
     *
     * @param string $html
     * @param stdClass $spotaward
     * @return string
     */
    private static function render_mustache_placeholders(string $html, stdClass $spotaward): string {
        global $CFG;

        require_once($CFG->dirroot . '/lib/mustache/src/Mustache/Autoloader.php');
        \Mustache\Autoloader::register();

        $mustache = new \Mustache\Mustache(['entity_encoding' => 'raw']);

        $spotawardarray = [
            'student_name' => $spotaward->student_name,
            'roll_no' => $spotaward->roll_no,
            'student_firstname' => $spotaward->student_firstname,
            'student_lastname' => $spotaward->student_lastname,
            'student_email' => $spotaward->student_email,
            'student_idnumber' => $spotaward->student_idnumber,
            'course_name' => $spotaward->course_name,
            'course_shortname' => $spotaward->course_shortname,
            'module_name' => $spotaward->module_name,
            'award_category' => $spotaward->award_category,
            'award_description' => $spotaward->award_description,
            'nomination_date' => $spotaward->nomination_date,
            'issued_date' => $spotaward->issued_date,
            'nominated_by' => $spotaward->nominated_by,
            'presented_by' => $spotaward->presented_by,
            'nominator_email' => $spotaward->nominator_email,
            'program_manager_email' => $spotaward->program_manager_email,
            'student_institution' => $spotaward->student_institution,
            'student_department' => $spotaward->student_department,
        ];

        $context = ['spotaward' => (object) $spotawardarray];
        return $mustache->render($html, $context);
    }

    /**
     * Process Beautiful Certificate language strings {#s}...{/s} or {#S}...{/S}.
     *
     * @param string $html
     * @return string
     */
    private static function process_language_strings(string $html): string {
        if (empty($html)) {
            return $html;
        }
        
        // Match {#s}...{/s} or {#S}...{/S} patterns
        // The key is captured: {#S}CERTTITLE{/S} -> key = "CERTTITLE"
        $html = preg_replace_callback(
            '/\{#[sS]([a-zA-Z0-9_\-]+)\{\/[sS]\}/u',
            function($matches) {
                $key = strtolower(trim($matches[1]));
                
                if (empty($key)) {
                    return $matches[0];  // Return original if key is empty
                }
                
                // Try different language packs
                $langPacks = [
                    'mod_certificatebeautiful',
                    'certificatebeautiful',
                ];
                
                foreach ($langPacks as $component) {
                    try {
                        $string = get_string($key, $component);
                        // get_string returns the string or "[[langkey]]" if not found
                        // Check if it looks like a missing key error
                        if ($string && $string !== '[[' . $key . ',' . $component . ']]' && 
                            $string !== '[[' . $key . ']]' &&
                            strpos($string, '[[') !== 0) {
                            return $string;
                        }
                    } catch (\Exception $e) {
                        // Continue to next pack
                    }
                }
                
                // If key not found in any pack, return original placeholder
                return $matches[0];
            },
            $html
        );
        
        return $html;
    }

    /**
     * Process CSS for background images and other mPDF-incompatible URL references.
     * Converts base64 data URIs to temp files, and relative/pluginfile URLs to absolute paths.
     * Specially handles Beautiful Certificate format: [data-gjs-type=wrapper]{background-image:url(data:...)}
     *
     * @param string $css
     * @return string
     */
    private static function process_certificate_css(string $css): string {
        global $CFG;
        
        if (empty($css)) {
            return $css;
        }
        
        // CRITICAL FIRST PASS: Convert base64 data URIs to temporary files
        // This regex captures the FULL base64 string by matching everything up to the closing parenthesis
        // Handles: url(data:image/jpeg;base64,...) and image:url(data:image/jpeg;base64,...)
        $css = preg_replace_callback(
            '/url\s*\(\s*data:(image\/(?:jpeg|png|gif|webp));base64,([A-Za-z0-9+\/=\r\n]+?)\s*\)/i',
            function($matches) use ($CFG) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
                
                // Remove any whitespace (URLs can span multiple lines)
                $base64Data = preg_replace('/\s+/', '', $base64Data);
                
                try {
                    // Decode the base64 data
                    $imageData = base64_decode($base64Data, true);
                    
                    if ($imageData === false) {
                        return $matches[0];
                    }
                    
                    // Determine file extension from MIME type
                    $ext = 'jpg';
                    if (stripos($mimeType, 'png') !== false) {
                        $ext = 'png';
                    } elseif (stripos($mimeType, 'gif') !== false) {
                        $ext = 'gif';
                    } elseif (stripos($mimeType, 'webp') !== false) {
                        $ext = 'webp';
                    }
                    
                    $imagehash = md5($base64Data);
                    $filename = 'img_' . $imagehash . '.' . $ext;

                    $tempDir = $CFG->tempdir . '/spotaward_cert_images';
                    if (!is_dir($tempDir)) {
                        @mkdir($tempDir, 0755, true);
                    }
                    $tempFile = $tempDir . '/' . $filename;

                    if (!file_exists($tempFile)) {
                        $fs = get_file_storage();
                        $syscontext = \context_system::instance();
                        $storedfile = $fs->get_file($syscontext->id, 'local_spotaward', 'cert_images', 0, '/', $filename);
                        if ($storedfile) {
                            $storedfile->copy_content_to($tempFile);
                        } else {
                            file_put_contents($tempFile, $imageData);
                            @chmod($tempFile, 0644);
                            try {
                                $fs->create_file_from_pathname([
                                    'contextid' => $syscontext->id,
                                    'component' => 'local_spotaward',
                                    'filearea'  => 'cert_images',
                                    'itemid'    => 0,
                                    'filepath'  => '/',
                                    'filename'  => $filename,
                                ], $tempFile);
                            } catch (\Exception $storeerr) {
                                // Concurrent request already created it — safe to ignore.
                            }
                        }
                    }

                    return "url('" . $tempFile . "')";
                    
                } catch (\Exception $e) {
                    debugging('Base64 image decode error: ' . $e->getMessage());
                    return $matches[0];
                }
            },
            $css
        );
        
        // SECOND PASS: Process remaining URL references (non-base64)
        // Handles relative paths and external URLs
        $css = preg_replace_callback(
            '/url\s*\(\s*["\']?([^)"\'\s]+)["\']?\s*\)/i',
            function($matches) use ($CFG) {
                $url = trim($matches[1]);
                
                if (empty($url)) {
                    return $matches[0];
                }
                
                // Skip remaining data URLs
                if (strpos($url, 'data:') === 0) {
                    return $matches[0];
                }

                $resolved = self::resolve_certificate_asset_url_to_file($url);
                if (!empty($resolved) && is_file($resolved)) {
                    return "url('" . $resolved . "')";
                }
                
                // Handle external URLs
                if (preg_match('~^https?://~i', $url)) {
                    return "url('" . $url . "')";
                }
                
                // Handle absolute filesystem paths that already exist
                if (strpos($url, '/') === 0 && is_file($url)) {
                    return "url('" . $url . "')";
                }
                
                // Handle relative paths - resolve them
                if (strpos($url, '/') !== 0) {
                    $tryPaths = [
                        $CFG->dirroot . '/' . ltrim($url, '/'),
                        $CFG->dirroot . '/mod/certificatebeautiful/' . ltrim($url, '/'),
                        $CFG->dirroot . '/mod/certificatebeautiful/_editor/' . ltrim($url, '/'),
                        $CFG->dirroot . '/theme/' . $CFG->theme . '/pix/' . ltrim($url, '/'),
                        $CFG->dirroot . '/pix/' . ltrim($url, '/'),
                    ];
                    
                    foreach ($tryPaths as $tryPath) {
                        if (is_file($tryPath)) {
                            return "url('" . $tryPath . "')";
                        }
                    }
                }
                
                if (strpos($url, '/') === 0) {
                    $fullPath = $CFG->dirroot . $url;
                    if (is_file($fullPath)) {
                        return "url('" . $fullPath . "')";
                    }
                }
                
                return $matches[0];
            },
            $css
        );
        
        // THIRD PASS: Fix @font-face src declarations for mPDF compatibility.
        // mPDF only supports .ttf and .otf font files.
        // When a @font-face block has multiple src entries (local(), woff2, woff, ttf),
        // mPDF ignores the format() hint and tries to load whatever url() it finds first.
        // Strategy: inside each @font-face block, remove woff2/woff sources and keep only ttf/otf.
        $css = preg_replace_callback(
            '/@font-face\s*\{([^}]+)\}/is',
            function($matches) use ($CFG) {
                $block = $matches[1];

                // Extract the src: ... ; declaration from the block
                if (!preg_match('/\bsrc\s*:\s*((?:[^;{]|\{[^}]*\})+)/is', $block, $srcmatch)) {
                    return $matches[0]; // No src found - leave untouched
                }

                $srcdecl = $srcmatch[1];

                // Split src value by commas (each comma-separated entry is one font source)
                $entries = preg_split('/,\s*(?=(?:local|url)\s*\()/i', $srcdecl);

                $ttfentries  = [];
                $otfentries  = [];
                $otherentries = [];

                foreach ($entries as $entry) {
                    $entry = trim($entry);
                    if (empty($entry)) {
                        continue;
                    }

                    // Determine format: check explicit format() hint first, then file extension
                    $format = '';
                    if (preg_match('/format\s*\(\s*[\'"]?([^\)\'"]+)[\'"]?\s*\)/i', $entry, $fm)) {
                        $format = strtolower(trim($fm[1]));
                    } elseif (preg_match('/url\s*\(\s*[\'"]?([^\)\'"\s]+)[\'"]?\s*\)/i', $entry, $um)) {
                        $ext = strtolower(pathinfo($um[1], PATHINFO_EXTENSION));
                        $format = $ext;
                    }

                    // Skip woff/woff2 - mPDF cannot render these
                    if ($format === 'woff2' || $format === 'woff') {
                        continue;
                    }

                    // Resolve the url() inside this entry to an absolute local path
                    $resolvedentry = preg_replace_callback(
                        '/url\s*\(\s*[\'"]?([^)\'"]+)[\'"]?\s*\)/i',
                        function($um) use ($CFG) {
                            $url = trim($um[1]);
                            if (strpos($url, 'data:') === 0) {
                                return $um[0];
                            }
                            $resolved = self::resolve_certificate_asset_url_to_file($url);
                            if (!empty($resolved) && is_file($resolved)) {
                                return "url('" . $resolved . "')";
                            }
                            return $um[0];
                        },
                        $entry
                    );

                    if ($format === 'truetype' || $format === 'ttf') {
                        $ttfentries[] = $resolvedentry;
                    } elseif ($format === 'opentype' || $format === 'otf') {
                        $otfentries[] = $resolvedentry;
                    } else {
                        // local() or unknown - keep as-is
                        $otherentries[] = $resolvedentry;
                    }
                }

                // Prefer ttf > otf > other (local())
                $kept = array_merge($ttfentries, $otfentries, $otherentries);

                if (empty($kept)) {
                    // All sources were woff/woff2 and got stripped - keep original so mPDF at least tries
                    return $matches[0];
                }

                $newsrc = implode(",\n    ", $kept);
                $newblock = preg_replace(
                    '/\bsrc\s*:\s*(?:[^;{]|\{[^}]*\})+/is',
                    'src: ' . $newsrc,
                    $block
                );

                return '@font-face {' . $newblock . '}';
            },
            $css
        );

        return $css;
    }

    /**
     * Resolve a certificate asset URL into a local file path for mPDF.
     *
     * @param string $url
     * @return string|null
     */
    private static function resolve_certificate_asset_url_to_file(string $url): ?string {
        global $CFG;

        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || strpos($url, 'data:') === 0) {
            return null;
        }

        $url = trim($url, "\"'");

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $url) && is_file($url)) {
            return $url;
        }

        $parsedpath = parse_url($url, PHP_URL_PATH);
        if ($parsedpath === false || $parsedpath === null || $parsedpath === '') {
            $parsedpath = $url;
        }

        $marker = null;
        if (strpos($parsedpath, '/pluginfile.php/') !== false) {
            $marker = '/pluginfile.php/';
        } else if (strpos($parsedpath, '/draftfile.php/') !== false) {
            $marker = '/draftfile.php/';
        }

        if (!empty($marker)) {
            $markerpos = strpos($parsedpath, $marker);
            $args = trim(substr($parsedpath, $markerpos + strlen($marker)), '/');

            $parts = explode('/', $args);
            if (count($parts) >= 5) {
                $contextid = (int)array_shift($parts);
                $component = (string)array_shift($parts);
                $filearea = (string)array_shift($parts);
                $itemid = (int)array_shift($parts);
                $filename = (string)array_pop($parts);
                $filepath = '/' . (empty($parts) ? '' : implode('/', $parts) . '/');

                $fs = get_file_storage();
                $storedfile = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

                if ($storedfile) {
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $ext = $ext ? ('.' . strtolower($ext)) : '';
                    $tempdir = $CFG->tempdir . '/spotaward_cert_images';
                    if (!is_dir($tempdir)) {
                        @mkdir($tempdir, 0755, true);
                    }

                    $temppath = $tempdir . '/pluginfile_' . $storedfile->get_contenthash() . $ext;
                    if (!is_file($temppath)) {
                        file_put_contents($temppath, $storedfile->get_content());
                        @chmod($temppath, 0644);
                    }

                    if (is_file($temppath)) {
                        return $temppath;
                    }
                }
            }
        }

        if (strpos($url, '/') === 0 && is_file($url)) {
            return $url;
        }

        if (strpos($url, '/') === 0) {
            $fullpath = $CFG->dirroot . $url;
            if (is_file($fullpath)) {
                return $fullpath;
            }
        }

        $path = ltrim($parsedpath, '/');
        $trypaths = [
            $CFG->dirroot . '/' . $path,
            $CFG->dirroot . '/mod/certificatebeautiful/' . $path,
            $CFG->dirroot . '/mod/certificatebeautiful/_editor/' . $path,
            $CFG->dirroot . '/theme/' . $CFG->theme . '/pix/' . $path,
            $CFG->dirroot . '/pix/' . $path,
        ];

        foreach ($trypaths as $trypath) {
            if (is_file($trypath)) {
                return $trypath;
            }
        }

        return null;
    }

    /**
     * Process date functions {{userdate(...)}}.
     *
     * @param string $html
     * @return string
     */
    private static function process_date_functions(string $html): string {
        // Match {{...}} patterns - handles userdate() and other functions
        $html = preg_replace_callback(
            '/\{\{([^}]+)\}\}/u',
            function($matches) {
                $expression = trim($matches[1]);
                
                try {
                    // Handle userdate(time(), ...) patterns
                    if (strpos($expression, 'userdate') === 0) {
                        // Pattern: userdate(time(), 'FORMAT')
                        if (preg_match('/userdate\s*\(\s*time\s*\(\s*\)\s*,\s*["\']?([^"\')]+)["\']?\s*\)/i', $expression, $m)) {
                            $format = trim($m[1]);
                            // Convert Moodle strftime formats
                            if ($format === 'strftimedate') {
                                return userdate(time(), '%d-%m-%Y');
                            } elseif ($format === 'strftimedatefullshort') {
                                return userdate(time(), '%d %B %Y');
                            } else {
                                return userdate(time(), $format);
                            }
                        }
                        // Pattern: userdate(time()) - no format specified
                        elseif (preg_match('/userdate\s*\(\s*time\s*\(\s*\)\s*\)/i', $expression)) {
                            return userdate(time());
                        }
                    }
                    
                    // Handle time() function
                    if (preg_match('/^time\s*\(\s*\)$/i', $expression)) {
                        return time();
                    }
                    
                    // Default: return current date in standard format
                    return userdate(time(), '%d-%m-%Y');
                } catch (\Exception $e) {
                    // On error, return original (will appear as {{...}})
                    return $matches[0];
                }
            },
            $html
        );
        return $html;
    }

    /**
     * Convert base64 data URIs to temporary files for mPDF compatibility.
     * Handles Beautiful Certificate format and standard URL formats.
     * Uses improved regex to capture FULL base64 strings (can be thousands of chars).
     *
     * @param string $html
     * @return string
     */
    private static function process_base64_images(string $html): string {
        global $CFG;
        
        if (empty($html)) {
            return $html;
        }
        
        // Match base64 data URIs with improved regex that captures full base64 strings
        // Handles: url(data:image/jpeg;base64,...) and style attributes with inline background
        $html = preg_replace_callback(
            '/url\s*\(\s*data:(image\/(?:jpeg|png|gif|webp));base64,([A-Za-z0-9+\/=\r\n]+?)\s*\)/i',
            function($matches) use ($CFG) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
                
                // Clean whitespace from base64 (URLs can span multiple lines)
                $base64Data = preg_replace('/\s+/', '', $base64Data);
                
                try {
                    // Decode the base64 data
                    $imageData = base64_decode($base64Data, true);
                    
                    if ($imageData === false) {
                        return $matches[0];
                    }
                    
                    // Determine file extension
                    $ext = 'jpg';
                    if (stripos($mimeType, 'png') !== false) {
                        $ext = 'png';
                    } elseif (stripos($mimeType, 'gif') !== false) {
                        $ext = 'gif';
                    } elseif (stripos($mimeType, 'webp') !== false) {
                        $ext = 'webp';
                    }
                    
                    // Create temp directory
                    $tempDir = $CFG->tempdir . '/spotaward_cert_images';
                    if (!is_dir($tempDir)) {
                        @mkdir($tempDir, 0755, true);
                    }
                    
                    $tempFile = $tempDir . '/' . 'img_' . md5($base64Data) . '.' . $ext;
                    
                    if (!file_exists($tempFile)) {
                        file_put_contents($tempFile, $imageData);
                        @chmod($tempFile, 0644);
                    }
                    
                    // Return as url() with absolute path
                    return "url('" . $tempFile . "')";
                    
                } catch (\Exception $e) {
                    debugging('Error processing base64 image: ' . $e->getMessage());
                    return $matches[0];
                }
            },
            $html
        );
        
        return $html;
    }

    /**
     * Process inline background-image styles in HTML style attributes.
     * Converts base64 data URIs to files and relative URLs to absolute filesystem paths for mPDF.
     *
     * @param string $html
     * @return string
     */
    private static function process_inline_background_images(string $html): string {
        global $CFG;
        
        if (empty($html)) {
            return $html;
        }
        
        // First convert base64 data URIs to temp files
        $html = self::process_base64_images($html);
        
        // FIRST: Handle background shorthand in style attributes
        // Convert: background: url(...) no-repeat; to background-image: url(...);
        $html = preg_replace_callback(
            '/style\s*=\s*["\']([^"\']*background\s*:\s*[^"\']*url\s*\([^)]+\)[^"\']*)["\']/',
            function($matches) {
                $style = $matches[1];
                
                // Extract url() and convert to background-image
                if (preg_match('/(url\s*\(\s*["\']?([^)"\'\'\s]+)["\']?\s*\))/i', $style, $urlMatch)) {
                    $urlPart = $urlMatch[1];
                    // Remove the background: property entirely and use background-image
                    $style = preg_replace('/background\s*:\s*[^;]*;?/i', 'background-image: ' . $urlPart . ';', $style);
                }
                
                return 'style="' . $style . '"';
            },
            $html
        );
        
        // SECOND: Match style attributes with explicit background-image
        $html = preg_replace_callback(
            '/style\s*=\s*["\']([^"\']*background-image[^"\']*)["\']/',
            function($matches) use ($CFG) {
                $style = $matches[1];
                
                // Process URLs within the style attribute
                $newStyle = preg_replace_callback(
                    '/url\s*\(\s*["\']?([^)"\'\s]+)["\']?\s*\)/i',
                    function($urlMatches) use ($CFG) {
                        $url = trim($urlMatches[1]);
                        
                        if (empty($url)) {
                            return $urlMatches[0];
                        }
                        
                        // Skip data URLs (should be converted already)
                        if (strpos($url, 'data:') === 0) {
                            return $urlMatches[0];
                        }

                        $resolved = self::resolve_certificate_asset_url_to_file($url);
                        if (!empty($resolved) && is_file($resolved)) {
                            return 'url(\'' . $resolved . '\')';
                        }
                        
                        // Already an absolute filesystem path
                        if (strpos($url, '/') === 0) {
                            if (is_file($url)) {
                                return 'url(\'' . $url . '\')';
                            }
                            $fullPath = $CFG->dirroot . $url;
                            if (is_file($fullPath)) {
                                return 'url(\'' . $fullPath . '\')';
                            }
                            return $urlMatches[0];
                        }
                        
                        // Try to resolve relative paths
                        $path = ltrim($url, '/');
                        $tryPaths = [
                            $CFG->dirroot . '/' . $path,
                            $CFG->dirroot . '/mod/certificatebeautiful/' . $path,
                            $CFG->dirroot . '/mod/certificatebeautiful/_editor/' . $path,
                            $CFG->dirroot . '/theme/' . $CFG->theme . '/pix/' . $path,
                            $CFG->dirroot . '/pix/' . $path,
                        ];
                        
                        foreach ($tryPaths as $tryPath) {
                            if (is_file($tryPath)) {
                                return 'url(\'' . $tryPath . '\')';
                            }
                        }
                        
                        return $urlMatches[0];
                    },
                    $style
                );
                
                return 'style="' . $newStyle . '"';
            },
            $html
        );
        
        return $html;
    }

    /**
     * Process HTML content to clean up GrapeJS builder artifacts
     * and layout styling needed for Beautiful Certificate to render properly.
     *
     * @param string $html
     * @return string
     */
    private static function process_certificate_html(string $html): string {
        // Keep data-gjs-type because Beautiful Certificate CSS targets it
        // for the page wrapper/background element.
        $html = preg_replace('/\s*data-gjs-(?!type\b)[a-z-]+="[^"]*"/', '', $html);
        $html = preg_replace('/\s*data-original-[a-z-]+="[^"]*"/', '', $html);
        
        // Remove GrapeJS builder classes while preserving actual CSS classes
        // Only remove gjs-* and similar builder classes (e.g., "gjs-selected", "gjs-hover")
        $html = preg_replace('/\s+class="([^"]*)gjs-[^\s"]*\s*([^"]*)"/i', ' class="$1$2"', $html);
        $html = preg_replace('/\s+class="([^"]*)builder-[^\s"]*\s*([^"]*)"/i', ' class="$1$2"', $html);
        
        // Clean up empty class attributes
        $html = preg_replace('/\s+class="\s*"/', '', $html);
        
        // Note: Keep 'position: absolute;' and other positioning styles intact
        // Beautiful Certificate relies on these for proper layout rendering
        
        return $html;
    }

    /**
     * Wrap page HTML with the Beautiful Certificate wrapper element expected by template CSS.
     *
     * @param string $html
     * @param string $css
     * @return string
     */
    private static function wrap_certificate_page_html(string $html, string $css): string {
        if (empty($html)) {
            return $html;
        }

        if (stripos($html, 'data-gjs-type="wrapper"') !== false || stripos($html, "data-gjs-type='wrapper'") !== false) {
            return $html;
        }

        $backgroundhtml = '';
        $backgroundurl = self::extract_wrapper_background_image_url($css);
        if (!empty($backgroundurl)) {
            $backgroundpath = self::resolve_certificate_asset_url_to_file($backgroundurl);
            if (!empty($backgroundpath) && is_file($backgroundpath)) {
                $backgroundhtml = '<img class="spotaward-cert-bg-fixed" src="' . s($backgroundpath) . '" alt="" />';
            }
        }

        return '<div data-gjs-type="wrapper" class="spotaward-cert-wrapper">' .
            $backgroundhtml .
            $html .
            '</div>';
    }

    /**
     * Extract the wrapper background image URL from certificate CSS.
     *
     * @param string $css
     * @return string|null
     */
    private static function extract_wrapper_background_image_url(string $css): ?string {
        if (empty($css)) {
            return null;
        }

        if (!preg_match('/\[data-gjs-type=wrapper\]\s*\{([^}]*)\}/is', $css, $wrappermatches)) {
            return null;
        }

        $wrappercss = $wrappermatches[1];
        if (preg_match('/background-image\s*:\s*url\s*\(\s*[\'"]?([^)"\']+)[\'"]?\s*\)/i', $wrappercss, $imagematches)) {
            return trim($imagematches[1]);
        }

        if (preg_match('/background\s*:\s*[^;]*url\s*\(\s*[\'"]?([^)"\']+)[\'"]?\s*\)/i', $wrappercss, $imagematches)) {
            return trim($imagematches[1]);
        }

        return null;
    }

    /**
     * Remove wrapper background declarations from CSS once we inject a fixed image layer.
     *
     * @param string $css
     * @return string
     */
    private static function strip_wrapper_background_css(string $css): string {
        if (empty($css)) {
            return $css;
        }

        return preg_replace_callback(
            '/(\[data-gjs-type=wrapper\]\s*\{)([^}]*)(\})/is',
            function($matches) {
                $declarations = $matches[2];
                $declarations = preg_replace('/background-image\s*:\s*[^;]+;?/i', '', $declarations);
                $declarations = preg_replace('/background-repeat\s*:\s*[^;]+;?/i', '', $declarations);
                $declarations = preg_replace('/background-position\s*:\s*[^;]+;?/i', '', $declarations);
                $declarations = preg_replace('/background-size\s*:\s*[^;]+;?/i', '', $declarations);
                $declarations = preg_replace('/background\s*:\s*[^;]+;?/i', '', $declarations);
                $declarations = preg_replace('/;\s*;/', ';', $declarations);

                return $matches[1] . $declarations . $matches[3];
            },
            $css
        );
    }

    /**
     * Apply the Beautiful Certificate wrapper background as an mPDF watermark image.
     *
     * @param string $html
     * @param string $css
     * @param \Mpdf\Mpdf $mpdf
     * @return void
     */
    private static function apply_certificate_background(string $html, string $css, \Mpdf\Mpdf $mpdf): void {
        $combined = $css . $html;
        if (!preg_match_all('/\[data-gjs-type="?wrapper"?\].*?}/s', $combined, $matches)) {
            return;
        }

        $blockcss = false;
        foreach (array_reverse($matches[0]) as $block) {
            if (strpos($block, 'background-image') !== false || strpos($block, 'background:') !== false) {
                $blockcss = $block;
                break;
            }
        }

        if (!$blockcss || !preg_match('/background.*url\((.*?)\)/', $blockcss, $background)) {
            return;
        }

        $image = trim($background[1], " \t\n\r\0\x0B'\"");
        if ($image === '') {
            return;
        }

        $resolved = self::resolve_certificate_asset_url_to_file($image);
        $source = !empty($resolved) && is_file($resolved) ? $resolved : $image;

        $mpdf->Image(
            $source,
            0,
            0,
            0,
            0,
            '',
            '',
            true,
            true,
            true,
            true,
            true
        );
    }

    /**
     * Merge multiple PDF binaries into a single PDF.
     *
     * @param array $pdfcontents
     * @param string $outputfilename
     * @return string
     */
    public static function merge_pdf_documents(array $pdfcontents, string $outputfilename = 'certificates.pdf'): string {
        global $CFG;

        $pdfcontents = array_values(array_filter($pdfcontents, function($content) {
            return is_string($content) && $content !== '';
        }));

        if (empty($pdfcontents)) {
            return '';
        }

        if (count($pdfcontents) === 1) {
            return $pdfcontents[0];
        }

        require_once(__DIR__ . '/../../../../lib/tcpdf/tcpdf.php');
        require_once(__DIR__ . '/../../../../mod/certificatebeautiful/classes/pdf/vendor/autoload.php');

        check_dir_exists($CFG->tempdir . '/spotaward_merge_pdf');
        $tempdir = make_temp_directory('spotaward_merge_pdf');
        $tempfiles = [];

        try {
            $pdf = new \setasign\Fpdi\TcpdfFpdi('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);

            foreach ($pdfcontents as $index => $content) {
                $temppath = $tempdir . '/cert_' . $index . '.pdf';
                file_put_contents($temppath, $content);
                $tempfiles[] = $temppath;

                $pagecount = $pdf->setSourceFile($temppath);
                for ($page = 1; $page <= $pagecount; $page++) {
                    $templateid = $pdf->importPage($page);
                    $size = $pdf->getTemplateSize($templateid);

                    $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateid, 0, 0, $size['width'], $size['height'], true);
                }
            }

            $merged = $pdf->Output($outputfilename, 'S');
        } finally {
            foreach ($tempfiles as $temppath) {
                if (is_file($temppath)) {
                    @unlink($temppath);
                }
            }
        }

        return $merged;
    }

    /**
     * Load a Beautiful Certificate template model.
     *
     * @param int $templateid
     * @return stdClass
     */
    public static function get_beautiful_certificate_model(int $templateid): stdClass {
        global $DB;

        if (!$templateid) {
            throw new moodle_exception('no_template_configured', 'local_spotaward');
        }

        $model = $DB->get_record('certificatebeautiful_model', ['id' => $templateid], '*', MUST_EXIST);
        $model->pages_info_object = json_decode($model->pages_info);

        if (empty($model->pages_info_object)) {
            throw new moodle_exception('no_template_found', 'local_spotaward');
        }

        return $model;
    }

    /**
     * Build a safe course-name-based base filename for certificate archives.
     *
     * @param int $nominationid
     * @return string
     */
    private static function get_certificate_zip_basename(int $nominationid): string {
        $nomination = self::get_nomination($nominationid);
        $course = get_course($nomination->courseid);
        $basename = clean_filename(format_string($course->fullname));

        return $basename !== '' ? $basename : 'certificates';
    }

    /**
     * Generate and store certificates for all approved students in a nomination.
     *
     * @param int $nominationid
     * @return array
     */
    public static function ensure_nomination_certificates_generated(int $nominationid): array {
        global $DB;

        $nomination = self::get_nomination($nominationid);
        if (!in_array($nomination->status, ['ssteamprogress', 'closed'], true)) {
            throw new moodle_exception('invalidparameter');
        }

        $templateid = (int)get_config('local_spotaward', 'certificate_templateid');
        $model = self::get_beautiful_certificate_model($templateid);

        $course = get_course($nomination->courseid);
        $items = self::get_nomination_items($nominationid);
        $generated = [];

        foreach ($items as $item) {
            if (!in_array($item->status, ['ssteamprogress', 'closed'], true)) {
                continue;
            }

            $student = core_user::get_user($item->studentid);
            if (!$student) {
                continue;
            }

            try {
                $content = self::generate_certificate_using_bc($model, $student, $course, $nomination, $item);
                $generated[$item->id] = $content;
            } catch (\Throwable $e) {
                debugging('Certificate generation failed for item ' . $item->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        if (!empty($generated)) {
            self::delete_nomination_certificate_files($nominationid);
            foreach ($generated as $itemid => $content) {
                $itemobj = array_values(array_filter($items, static fn($i) => (int)$i->id === $itemid))[0];
                self::save_certificate_file($nominationid, $itemid, $itemobj->studentid, $content);
            }
        }

        return $generated;
    }

    /**
     * Generate and store certificates for selected nomination items.
     *
     * @param int $nominationid
     * @param array $itemids
     * @return array
     */
    public static function ensure_selected_nomination_certificates_generated(int $nominationid, array $itemids): array {
        $itemids = self::normalise_nomination_item_ids($itemids);
        if (empty($itemids)) {
            throw new moodle_exception('selectstudentsforbulkcert', 'local_spotaward');
        }

        $nomination = self::get_nomination($nominationid);
        if (!in_array($nomination->status, ['ssteamprogress', 'closed'], true)) {
            throw new moodle_exception('invalidparameter');
        }

        $templateid = (int)get_config('local_spotaward', 'certificate_templateid');
        $model = self::get_beautiful_certificate_model($templateid);
        $course = get_course($nomination->courseid);
        $items = self::get_selected_certificate_items($nominationid, $itemids);

        $generated = [];
        foreach ($items as $item) {
            $student = core_user::get_user($item->studentid);
            if (!$student) {
                continue;
            }

            $content = self::generate_certificate_using_bc($model, $student, $course, $nomination, $item);
            self::save_certificate_file($nominationid, $item->id, $student->id, $content);
            $generated[$item->id] = $content;
        }

        return $generated;
    }

    /**
     * Generate certificate using Beautiful Certificate's proper method.
     *
     * @param stdClass $model
     * @param stdClass $user
     * @param stdClass $course
     * @param stdClass $nomination
     * @param stdClass $item
     * @return string
     */
    public static function generate_certificate_using_bc(stdClass $model, stdClass $user, stdClass $course, 
                                                          stdClass $nomination, stdClass $item): string {
        $nominator = core_user::get_user($nomination->nominatorid);
        $programmanager = core_user::get_user($nomination->programmanagerid);
        $replacements = cert_field_map::get_replacement_fields($course, $user, $nomination, $item, $nominator, $programmanager);

        return self::generate_document_using_bc(
            $model,
            $replacements,
            'Spot Award Certificate',
            'Spot Award Certificate'
        );
    }

    /**
     * Build PR document filename and PDF content for download.
     *
     * @param int $nominationid
     * @return array
     */
    public static function build_pr_document_download(int $nominationid): array {
        $nomination = self::get_nomination($nominationid);
        if ($nomination->status !== 'ssteamprogress') {
            throw new moodle_exception('prdocumentnotavailable', 'local_spotaward');
        }

        $content = self::generate_pr_document_pdf($nominationid);
        $course = get_course($nomination->courseid);
        $base = clean_filename(format_string($course->fullname));
        if ($base === '') {
            $base = 'spot_award_pr';
        }
        $filename = 'Purchase_Request_' . $base . '_' . $nominationid . '.pdf';

        return [$filename, $content];
    }

    /**
     * Generate PR document PDF using configured Beautiful Certificate template.
     *
     * @param int $nominationid
     * @return string
     */
    public static function generate_pr_document_pdf(int $nominationid): string {
        $nomination = self::get_nomination($nominationid);
        if ($nomination->status !== 'ssteamprogress') {
            throw new moodle_exception('prdocumentnotavailable', 'local_spotaward');
        }

        $templateid = (int)get_config('local_spotaward', 'pr_templateid');
        $model = self::get_beautiful_certificate_model($templateid);
        $course = get_course($nomination->courseid);
        $nominator = core_user::get_user($nomination->nominatorid);
        $programmanager = core_user::get_user($nomination->programmanagerid);
        $maacexecutive = !empty($nomination->maacexecutiveid)
            ? core_user::get_user((int)$nomination->maacexecutiveid)
            : null;
        $items = self::get_nomination_items($nominationid);
        [$awardsummary] = self::get_nomination_award_summary($nominationid);
        $replacements = pr_field_map::get_replacement_fields(
            $course,
            $nomination,
            $nominator ?: null,
            $programmanager ?: null,
            $maacexecutive ?: null,
            $items,
            $awardsummary
        );

        return self::generate_document_using_bc(
            $model,
            $replacements,
            'Spot Award PR Document',
            'Purchase Request'
        );
    }

    /**
     * Render a Beautiful Certificate template with custom replacements.
     *
     * @param stdClass $model
     * @param array $replacements
     * @param string $creator
     * @param string $title
     * @return string
     */
    private static function generate_document_using_bc(stdClass $model, array $replacements, string $creator,
            string $title): string {
        global $CFG;

        require_once(__DIR__ . '/../../../../mod/certificatebeautiful/classes/pdf/vendor/autoload.php');
        require_once(__DIR__ . '/../../../../mod/certificatebeautiful/classes/fonts/font_util.php');

        $proporcao = .85;
        $orientation = isset($model->orientation) ? $model->orientation : 'L';

        $mpdf = new \Mpdf\Mpdf(self::get_certificate_mpdf_config([210 * $proporcao, 297 * $proporcao], $orientation));
        $mpdf->autoPageBreak = false;

        $mpdf->SetAuthor($title);
        $mpdf->SetCreator($creator);
        $mpdf->SetTitle($title);

        foreach ($model->pages_info_object as $page) {
            $mpdf->AddPageByArray([]);

            $htmldata = $page->htmldata ?? '';
            $cssdata = $page->cssdata ?? '';

            // IMPORTANT: Decode HTML entities first - Beautiful Certificate may store them encoded
            $htmldata = html_entity_decode($htmldata, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cssdata = html_entity_decode($cssdata, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Match Beautiful Certificate's own PDF renderer behavior.
            $htmldata = str_replace("<section", "<body", $htmldata);
            $htmldata = str_replace("</section>", "</body>", $htmldata);

            // Add Beautiful Certificate system fields - use UPPERCASE (case sensitive!)
            $replacements['{$CERTIFICATE->description}'] = $model->description ?? '';
            $replacements['{$CERTIFICATE->name}'] = $model->name ?? '';
            $replacements['{$CERTIFICATE->id}'] = $model->id ?? '';
            
            // Also add lowercase versions in case template uses them
            $replacements['{$certificate->description}'] = $model->description ?? '';
            $replacements['{$certificate->name}'] = $model->name ?? '';
            $replacements['{$certificate->id}'] = $model->id ?? '';

            // Apply all field replacements to HTML
            foreach ($replacements as $placeholder => $value) {
                if (!empty($value) || $value === '0') {  // Allow '0' as valid value
                    $wrappedvalue = self::is_plain_text_certificate_placeholder($placeholder)
                        ? self::wrap_certificate_plaintext_value((string)$value)
                        : self::wrap_certificate_replacement_value((string)$value);
                    $htmldata = str_replace($placeholder, $wrappedvalue, $htmldata);
                }
            }

            // Apply field replacements to CSS
            if (!empty($cssdata)) {
                foreach ($replacements as $placeholder => $value) {
                    if (!empty($value) || $value === '0') {  // Allow '0' as valid value
                        $cssdata = str_replace($placeholder, (string)$value, $cssdata);
                    }
                }
            }

            // Process CSS for mPDF-compatible image URLs BEFORE language string processing
            // This includes expanding background shorthand and resolving URLs
            $cssdata = self::process_certificate_css($cssdata);

            // Process Beautiful Certificate language strings {#s}key{/s}
            // This must happen AFTER field replacements to avoid interfering with them
            $htmldata = self::process_language_strings($htmldata);
            if (!empty($cssdata)) {
                $cssdata = self::process_language_strings($cssdata);
            }

            // Process date functions {{userdate(...)}}
            $htmldata = self::process_date_functions($htmldata);
            if (!empty($cssdata)) {
                $cssdata = self::process_date_functions($cssdata);
            }

            // Clean up HTML
            $htmldata = self::process_certificate_html($htmldata);
            $cssdata = self::process_certificate_html($cssdata);
            
            // Process inline background-image styles in HTML elements
            $htmldata = self::process_inline_background_images($htmldata);

            $extracss = "
                @page {
                    page-break-inside: avoid;
                }
                html,
                body,
                img {
                    margin: 0;
                    padding: 0;
                }";

            // Combine CSS properly
            $fullcss = $extracss;
            if (!empty($cssdata)) {
                $fullcss .= "\n" . $cssdata;
            }

            if (empty($htmldata)) {
                continue;
            }

            self::apply_certificate_background($htmldata, $cssdata, $mpdf);

            if (!empty($cssdata)) {
                $mpdf->WriteHTML($fullcss, \Mpdf\HTMLParserMode::HEADER_CSS);
                $mpdf->WriteHTML($htmldata);
            } else {
                $mpdf->WriteHTML("<style>{$extracss}</style>\n{$htmldata}");
            }
        }

        return $mpdf->Output('certificate.pdf', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * Build an mPDF config aligned with Beautiful Certificate font handling.
     *
     * @param mixed $format
     * @param string $orientation
     * @return array
     */
    private static function get_certificate_mpdf_config($format, string $orientation = 'L'): array {
        global $CFG;

        $fontdirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontdata = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $defaultfont = 'dejavusans';

        if (class_exists('\mod_certificatebeautiful\fonts\font_util')) {
            $fontlist = \mod_certificatebeautiful\fonts\font_util::mpdf_list_fonts();
            if (!empty($fontlist['path']) && is_array($fontlist['path'])) {
                $fontdirs = array_values(array_unique(array_merge($fontdirs, $fontlist['path'])));
            }
            if (!empty($fontlist['fonts']) && is_array($fontlist['fonts'])) {
                $fontdata = array_merge($fontdata, $fontlist['fonts']);
            }
        }

        $windowsfontdir = getenv('WINDIR') ? getenv('WINDIR') . DIRECTORY_SEPARATOR . 'Fonts' : 'C:\\Windows\\Fonts';
        $arialregular = $windowsfontdir . DIRECTORY_SEPARATOR . 'arial.ttf';
        $arialbold = $windowsfontdir . DIRECTORY_SEPARATOR . 'arialbd.ttf';
        $arialitalic = $windowsfontdir . DIRECTORY_SEPARATOR . 'ariali.ttf';
        $arialbolditalic = $windowsfontdir . DIRECTORY_SEPARATOR . 'arialbi.ttf';

        if (is_file($arialregular)) {
            $fontdirs[] = $windowsfontdir;
            $fontdata['arial'] = [
                'R' => basename($arialregular),
                'B' => is_file($arialbold) ? basename($arialbold) : basename($arialregular),
                'I' => is_file($arialitalic) ? basename($arialitalic) : basename($arialregular),
                'BI' => is_file($arialbolditalic) ? basename($arialbolditalic) : basename($arialregular),
            ];
            $defaultfont = 'arial';
        }

        return [
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => $orientation,
            'tempDir' => "{$CFG->dataroot}/temp/mpdf",
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'fontDir' => array_values(array_unique($fontdirs)),
            'fontdata' => $fontdata,
            'default_font' => $defaultfont,
        ];
    }

    /**
     * Wrap dynamic replacement text so mPDF keeps inherited text styling.
     *
     * @param string $value
     * @return string
     */
    private static function wrap_certificate_replacement_value(string $value): string {
        // Convert newlines to <br> tags
        $value = str_replace(["\r\n", "\r", "\n"], '<br>', $value);
        
        // For mPDF, we need to escape dangerous content but preserve formatting tags
        // escapeTagsRecursive will escape everything except allowed tags
        
        // First, mark allowed tags with placeholders to protect them
        $value = str_replace('<b>', '___B_TAG___', $value);
        $value = str_replace('</b>', '___B_CLOSE___', $value);
        $value = str_replace('<strong>', '___STRONG_TAG___', $value);
        $value = str_replace('</strong>', '___STRONG_CLOSE___', $value);
        $value = str_replace('<i>', '___I_TAG___', $value);
        $value = str_replace('</i>', '___I_CLOSE___', $value);
        $value = str_replace('<em>', '___EM_TAG___', $value);
        $value = str_replace('</em>', '___EM_CLOSE___', $value);
        $value = str_replace('<u>', '___U_TAG___', $value);
        $value = str_replace('</u>', '___U_CLOSE___', $value);
        $value = str_replace('<br>', '___BR_TAG___', $value);
        
        // Now escape everything
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Restore the formatting tags in their unescaped form
        $value = str_replace('___B_TAG___', '<strong>', $value);  // Use <strong> instead of <b>
        $value = str_replace('___B_CLOSE___', '</strong>', $value);
        $value = str_replace('___STRONG_TAG___', '<strong>', $value);
        $value = str_replace('___STRONG_CLOSE___', '</strong>', $value);
        $value = str_replace('___I_TAG___', '<em>', $value);  // Use <em> instead of <i>
        $value = str_replace('___I_CLOSE___', '</em>', $value);
        $value = str_replace('___EM_TAG___', '<em>', $value);
        $value = str_replace('___EM_CLOSE___', '</em>', $value);
        $value = str_replace('___U_TAG___', '<u>', $value);
        $value = str_replace('___U_CLOSE___', '</u>', $value);
        $value = str_replace('___BR_TAG___', '<br>', $value);
        
        return $value;
    }

    /**
     * Determine whether a certificate placeholder should render as plain text.
     *
     * @param string $placeholder
     * @return bool
     */
    private static function is_plain_text_certificate_placeholder(string $placeholder): bool {
        static $plaintextplaceholders = [
            '{recognition_text}',
            '{award_description}',
            '{$SPOTAWARD->award_description}',
            '{$spotaward->award_description}',
        ];

        return in_array($placeholder, $plaintextplaceholders, true);
    }

    /**
     * Render replacement text as plain text, preserving only line breaks.
     *
     * @param string $value
     * @return string
     */
    private static function wrap_certificate_plaintext_value(string $value): string {
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return str_replace(["\r\n", "\r", "\n"], '<br>', $value);
    }

    /**
     * Save certificate PDF to Moodle File API.
     *
     * @param int $nominationid
     * @param int $nominationitemid
     * @param int $userid
     * @param string $pdfcontent
     * @return void
     */
    public static function save_certificate_file(int $nominationid, int $nominationitemid, int $userid, string $pdfcontent): void {
        $fs = get_file_storage();
        $existingfile = $fs->get_file(
            context_system::instance()->id,
            'local_spotaward',
            'certificates',
            $nominationid,
            '/',
            self::get_certificate_filename($nominationitemid, $userid)
        );
        if ($existingfile) {
            $existingfile->delete();
        }
        $legacyfile = $fs->get_file(
            context_system::instance()->id,
            'local_spotaward',
            'certificates',
            $nominationid,
            '/',
            self::get_legacy_certificate_filename($userid)
        );
        if ($legacyfile) {
            $legacyfile->delete();
        }

        $filerecord = [
            'contextid' => context_system::instance()->id,
            'component' => 'local_spotaward',
            'filearea' => 'certificates',
            'itemid' => $nominationid,
            'filepath' => '/',
            'filename' => self::get_certificate_filename($nominationitemid, $userid),
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $fs->create_file_from_string($filerecord, $pdfcontent);
    }

    /**
     * Build certificate filename for a nomination item.
     *
     * @param int $nominationitemid
     * @param int $userid
     * @return string
     */
    private static function get_certificate_filename(int $nominationitemid, int $userid): string {
        return 'certificate_item_' . $nominationitemid . '_user_' . $userid . '.pdf';
    }

    /**
     * Legacy certificate filename format.
     *
     * @param int $userid
     * @return string
     */
    private static function get_legacy_certificate_filename(int $userid): string {
        return 'certificate_' . $userid . '.pdf';
    }

    /**
     * Delete all stored certificate files for a nomination.
     *
     * @param int $nominationid
     * @return void
     */
    private static function delete_nomination_certificate_files(int $nominationid): void {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            context_system::instance()->id,
            'local_spotaward',
            'certificates',
            $nominationid,
            'filename',
            false
        );

        foreach ($files as $file) {
            $file->delete();
        }
    }

    /**
     * Get certificate file for a student.
     *
     * @param int $nominationid
     * @param int $userid
     * @param int $nominationitemid
     * @return stored_file|null
     */
    public static function get_certificate_file(int $nominationid, int $userid, int $nominationitemid = 0): ?\stored_file {
        $fs = get_file_storage();
        if ($nominationitemid > 0) {
            $file = $fs->get_file(
                context_system::instance()->id,
                'local_spotaward',
                'certificates',
                $nominationid,
                '/',
                self::get_certificate_filename($nominationitemid, $userid)
            );
            if ($file) {
                return $file;
            }
        }

        $items = self::get_nomination_items($nominationid);
        foreach ($items as $item) {
            if ((int)$item->studentid !== $userid) {
                continue;
            }

            $file = $fs->get_file(
                context_system::instance()->id,
                'local_spotaward',
                'certificates',
                $nominationid,
                '/',
                self::get_certificate_filename((int)$item->id, $userid)
            );
            if ($file) {
                return $file;
            }
        }

        $file = $fs->get_file(
            context_system::instance()->id,
            'local_spotaward',
            'certificates',
            $nominationid,
            '/',
            self::get_legacy_certificate_filename($userid)
        );
        return $file ?: null;
    }

    /**
     * Check if certificates exist for a nomination.
     *
     * @param int $nominationid
     * @return bool
     */
    public static function certificates_exist(int $nominationid): bool {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            context_system::instance()->id,
            'local_spotaward',
            'certificates',
            $nominationid,
            'filename',
            false
        );
        return count($files) > 0;
    }

    /**
     * Get all certificate files for a nomination.
     *
     * @param int $nominationid
     * @return array
     */
    public static function get_all_certificate_files(int $nominationid): array {
        $fs = get_file_storage();
        return $fs->get_area_files(
            context_system::instance()->id,
            'local_spotaward',
            'certificates',
            $nominationid,
            'filename',
            false
        );
    }

    /**
     * Download certificate for a specific student.
     *
     * @param int $nominationid
     * @param int $userid
     * @param int $nominationitemid
     * @return void
     */
    public static function download_certificate(int $nominationid, int $userid, int $nominationitemid = 0): void {
        $file = self::get_certificate_file($nominationid, $userid, $nominationitemid);
        
        if (!$file) {
            throw new moodle_exception('certificatenotfound', 'local_spotaward');
        }

        send_stored_file($file, 0, 0, true, [
            'filename' => $nominationitemid > 0
                ? self::get_certificate_filename($nominationitemid, $userid)
                : self::get_legacy_certificate_filename($userid)
        ]);
    }

    /**
     * Build all certificates as one merged PDF in temp storage.
     *
     * @param int $nominationid
     * @return array
     */
    private static function build_combined_certificate_pdf_attachment(int $nominationid): array {
        global $CFG;

        self::ensure_nomination_certificates_generated($nominationid);
        $basename = self::get_certificate_zip_basename($nominationid);
        $files = self::get_all_certificate_files($nominationid);

        if (empty($files)) {
            throw new moodle_exception('nocertificates', 'local_spotaward');
        }

        $pdfcontents = [];
        foreach ($files as $file) {
            $pdfcontents[] = $file->get_content();
        }

        $mergedpdf = self::merge_pdf_documents($pdfcontents, $basename . '_certificates.pdf');
        if ($mergedpdf === '') {
            throw new moodle_exception('nocertificates', 'local_spotaward');
        }

        if (strlen($mergedpdf) > self::ADMIN_SHARE_MAX_BYTES) {
            throw new moodle_exception('adminsharecertificatetoolarge', 'local_spotaward');
        }

        check_dir_exists($CFG->tempdir . '/spotaward_share_admin');
        $tmppdf = tempnam($CFG->tempdir . '/spotaward_share_admin', 'spotawardcertpdf');
        if ($tmppdf === false) {
            throw new moodle_exception('generalexceptionmessage', 'error', '', 'Unable to create certificate PDF.');
        }
        file_put_contents($tmppdf, $mergedpdf);

        return [
            'path' => $tmppdf,
            'name' => $basename . '_certificates.pdf',
            'content' => $mergedpdf,
        ];
    }

    /**
     * Build all certificates as a ZIP file in temp storage.
     *
     * Kept for manual "Download All Certificates" so user-facing downloads
     * behave the same as before, even though admin sharing now uses one PDF.
     *
     * @param int $nominationid
     * @return array
     */
    private static function build_certificate_zip_attachment(int $nominationid): array {
        global $CFG;

        self::ensure_nomination_certificates_generated($nominationid);
        $zipbasename = self::get_certificate_zip_basename($nominationid);
        $files = self::get_all_certificate_files($nominationid);

        if (empty($files)) {
            throw new moodle_exception('nocertificates', 'local_spotaward');
        }

        $zipfiles = [];
        foreach ($files as $file) {
            $zipfiles[$file->get_filename()] = [$file->get_content()];
        }

        require_once(__DIR__ . '/../../../../lib/filestorage/zip_packer.php');

        check_dir_exists($CFG->tempdir . '/zip');
        $tmpzip = tempnam($CFG->tempdir . '/zip', 'spotawardzip');
        if ($tmpzip === false) {
            throw new moodle_exception('generalexceptionmessage', 'error', '', 'Unable to create ZIP archive.');
        }

        $zipper = new \zip_packer();
        $result = $zipper->archive_to_pathname($zipfiles, $tmpzip);
        if (!$result || !is_file($tmpzip)) {
            @unlink($tmpzip);
            throw new moodle_exception('generalexceptionmessage', 'error', '', 'Unable to create ZIP archive.');
        }

        $zipcontent = file_get_contents($tmpzip);
        if ($zipcontent === false) {
            @unlink($tmpzip);
            throw new moodle_exception('generalexceptionmessage', 'error', '', 'Unable to read ZIP archive.');
        }

        return [
            'path' => $tmpzip,
            'name' => $zipbasename . '.zip',
            'content' => $zipcontent,
        ];
    }

    /**
     * Build admin attachment ZIP containing the uploaded PR and combined certificates PDF.
     *
     * @param int $nominationid
     * @param string $prpath
     * @param string $prfilename
     * @param array $certificatepdf
     * @return array
     */
    private static function build_admin_documents_bundle(int $nominationid, string $prpath, string $prfilename,
            array $certificatepdf): array {
        global $CFG;

        $prcontent = file_get_contents($prpath);
        if ($prcontent === false) {
            throw new moodle_exception('invalidparameter');
        }

        if (strlen($prcontent) > self::ADMIN_SHARE_MAX_BYTES) {
            throw new moodle_exception('adminshareattachmenttoolarge', 'local_spotaward');
        }

        require_once(__DIR__ . '/../../../../lib/filestorage/zip_packer.php');

        check_dir_exists($CFG->tempdir . '/zip');
        $tmpzip = tempnam($CFG->tempdir . '/zip', 'spotawardadmin');
        if ($tmpzip === false) {
            throw new moodle_exception('generalexceptionmessage', 'error', '', 'Unable to create ZIP archive.');
        }

        $zipper = new \zip_packer();
        $zipfiles = [
            'pr_document/' . clean_filename($prfilename) => [$prcontent],
            'certificates/' . clean_filename($certificatepdf['name']) => [$certificatepdf['content']],
        ];

        $result = $zipper->archive_to_pathname($zipfiles, $tmpzip);
        if (!$result || !is_file($tmpzip)) {
            @unlink($tmpzip);
            throw new moodle_exception('generalexceptionmessage', 'error', '', 'Unable to create ZIP archive.');
        }

        return [
            'path' => $tmpzip,
            'name' => self::get_certificate_zip_basename($nominationid) . '_admin_documents.zip',
        ];
    }

    /**
     * Download all certificates as ZIP.
     *
     * @param int $nominationid
     * @return void
     */
    public static function download_all_certificates(int $nominationid): void {
        $certificatezip = self::build_certificate_zip_attachment($nominationid);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $certificatezip['name'] . '"');
        header('Content-Length: ' . strlen($certificatezip['content']));

        echo $certificatezip['content'];
        @unlink($certificatezip['path']);
        exit;
    }

    /**
     * Get filter options for manager dashboard.
     *
     * @return array
     */
    public static function get_filter_options(): array {
        global $DB;

        $mentoroptions = [0 => get_string('allmentors', 'local_spotaward')];
        $pmoptions = [0 => get_string('allprogrammanagers', 'local_spotaward')];
        $maacoptions = [0 => get_string('allmaacexecutives', 'local_spotaward')];

        $sql = "SELECT u.id, u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       'mentor' AS roletype
                  FROM {user} u
                  JOIN {spotaward_nominations} n ON n.nominatorid = u.id
             UNION
                SELECT u.id, u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       'pm' AS roletype
                  FROM {user} u
                  JOIN {spotaward_nominations} n ON n.programmanagerid = u.id
             UNION
                SELECT u.id, u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       'maac' AS roletype
                  FROM {user} u
                  JOIN {spotaward_nominations} n ON n.maacexecutiveid = u.id
              ORDER BY firstname ASC, lastname ASC";

        foreach ($DB->get_records_sql($sql) as $row) {
            $name = fullname($row);
            if ($row->roletype === 'mentor') {
                $mentoroptions[$row->id] = $name;
            } else if ($row->roletype === 'pm') {
                $pmoptions[$row->id] = $name;
            } else {
                $maacoptions[$row->id] = $name;
            }
        }

        return [$mentoroptions, $pmoptions, $maacoptions];
    }

    /**
     * Build student report data.
     *
     * @param int $studentid
     * @param int $courseid
     * @param string $activitytype
     * @return array
     */
    public static function get_student_report(int $studentid, int $courseid, string $activitytype = ''): array {
        $student = core_user::get_user($studentid, '*', MUST_EXIST);
        $report = self::build_course_activity_report($courseid, [$student], $activitytype);

        return [
            'activitytype' => $report['activitytype'],
            'activitycount' => count($report['activities']),
            'studentcount' => 1,
            'rows' => $report['rowsbystudent'][$studentid] ?? [],
            'summaryrows' => $report['summarybystudent'][$studentid] ?? [],
            'summary' => [
                'attendance' => self::get_attendance_percentage($studentid, $courseid),
                'assignmentcompletion' => self::get_assignment_completion($studentid, $courseid),
                'projectcompletion' => self::get_project_completion($studentid, $courseid),
            ],
        ];
    }

    /**
     * Build course report data.
     *
     * @param int $courseid
     * @param string $activitytype
     * @return array
     */
    public static function get_course_report(int $courseid, string $activitytype = ''): array {
        $students = self::get_course_students($courseid, 0);
        $report = self::build_course_activity_report($courseid, $students, $activitytype);

        return [
            'activitytype' => $report['activitytype'],
            'activitycount' => count($report['activities']),
            'studentcount' => count($students),
            'rows' => $report['rows'],
        ];
    }

    /**
     * Load report-accessible courses for a user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_report_courses_for_user(int $userid): array {
        $supportedcourses = self::get_supported_report_courses();

        if (is_siteadmin($userid) || self::is_manager($userid)) {
            return $supportedcourses;
        }

        $courses = [];

        foreach (self::get_nominator_courses($userid) as $courseid => $label) {
            if (isset($supportedcourses[$courseid])) {
                $courses[$courseid] = $supportedcourses[$courseid];
            }
        }

        if (self::is_program_manager($userid)) {
            $pmroleid = constants::program_manager_roleid();
            if ($pmroleid > 0 && !empty($supportedcourses)) {
                global $DB;
                [$incoursesql, $incourseparams] = $DB->get_in_or_equal(array_keys($supportedcourses), SQL_PARAMS_NAMED);
                $pmcoursesql = "SELECT DISTINCT ctx.instanceid AS courseid
                                  FROM {role_assignments} ra
                                  JOIN {context} ctx ON ctx.id = ra.contextid
                                 WHERE ra.userid = :pmuserid
                                   AND ra.roleid = :pmroleid
                                   AND ctx.contextlevel = :pmcourselevel
                                   AND ctx.instanceid {$incoursesql}";
                $pmparams = array_merge(
                    ['pmuserid' => $userid, 'pmroleid' => $pmroleid, 'pmcourselevel' => CONTEXT_COURSE],
                    $incourseparams
                );
                foreach ($DB->get_records_sql($pmcoursesql, $pmparams) as $record) {
                    $cid = (int)$record->courseid;
                    if (isset($supportedcourses[$cid])) {
                        $courses[$cid] = $supportedcourses[$cid];
                    }
                }
            }
        }

        natcasesort($courses);
        return $courses;
    }

    /**
     * Check whether a user can access a course report.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function can_access_report_course(int $userid, int $courseid): bool {
        $courses = self::get_report_courses_for_user($userid);
        return isset($courses[$courseid]);
    }

    /**
     * Supported report activity type options.
     *
     * @return array
     */
    public static function get_report_activity_type_options(): array {
        return [
            '' => get_string('allactivitytypes', 'local_spotaward'),
            'assignments' => get_string('assignments', 'local_spotaward'),
            'projects' => get_string('projects', 'local_spotaward'),
            'quiz' => get_string('quizlabel', 'local_spotaward'),
            'attendance' => get_string('attendancelabel', 'local_spotaward'),
        ];
    }

    /**
     * Build course activity report rows.
     *
     * @param int $courseid
     * @param array $students
     * @param string $activitytype
     * @return array
     */
    private static function build_course_activity_report(int $courseid, array $students, string $activitytype = ''): array {
        $course = get_course($courseid);
        $activitytype = self::normalize_report_activity_type($activitytype);
        $activities = self::get_course_report_activities($course, $activitytype);
        $studentids = array_map(static function(stdClass $student): int {
            return (int)$student->id;
        }, $students);

        $grades = self::get_grade_item_grade_map($activities, $studentids);
        $attendance = self::get_attendance_report_map($courseid, $activities, $studentids);

        $rows = [];
        $rowsbystudent = [];

        foreach ($students as $student) {
            $studentid = (int)$student->id;
            $studentname = fullname($student);

            foreach ($activities as $activity) {
                $attendancedata = $attendance[$activity['iteminstance']][$studentid] ?? null;
                $gradevalue = $grades[$activity['gradeitemid']][$studentid] ?? null;
                $displaygrade = self::format_report_grade(
                    $activity,
                    $gradevalue,
                    $attendancedata['percentage'] ?? null
                );
                $completiondata = self::get_report_completion_data($activity, $gradevalue, $attendancedata);

                $row = [
                    'studentid' => $studentid,
                    'studentname' => $studentname,
                    'studentemail' => $student->email ?? '',
                    'studentusername' => $student->username ?? '',
                    'activityname' => $activity['activityname'],
                    'category' => $activity['category'],
                    'categorylabel' => $activity['categorylabel'],
                    'typelabel' => $activity['typelabel'],
                    'module' => $activity['module'],
                    'grade' => $displaygrade,
                    'scorepercent' => self::get_report_score_percent($activity, $gradevalue, $attendancedata),
                    'completionvalue' => $completiondata['value'],
                    'completiontotal' => $completiondata['total'],
                ];

                $rows[] = $row;
                $rowsbystudent[$studentid][] = $row;
            }
        }

        return [
            'activitytype' => $activitytype,
            'activities' => $activities,
            'rows' => $rows,
            'rowsbystudent' => $rowsbystudent,
            'summarybystudent' => self::build_report_summary_rows($rowsbystudent),
        ];
    }

    /**
     * Get supported report courses.
     *
     * @return array
     */
    private static function get_supported_report_courses(): array {
        global $DB;
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $records = $DB->get_records_select('course', 'id <> :sitecourse', ['sitecourse' => SITEID], 'fullname ASC',
            'id, shortname, fullname');

        $courses = [];
        foreach ($records as $record) {
            if (self::get_report_course_profile($record) === null) {
                continue;
            }

            $courses[$record->id] = format_string($record->fullname, true, ['context' => context_course::instance($record->id)]);
        }

        return $cache = $courses;
    }

    /**
     * Normalize report activity type filter.
     *
     * @param string $activitytype
     * @return string
     */
    private static function normalize_report_activity_type(string $activitytype): string {
        $activitytype = trim(\core_text::strtolower($activitytype));
        if (in_array($activitytype, ['', 'assignments', 'projects', 'quiz', 'attendance'], true)) {
            return $activitytype;
        }

        return '';
    }

    /**
     * Identify the report profile for a course.
     *
     * @param stdClass $course
     * @return string|null
     */
    private static function get_report_course_profile(stdClass $course): ?string {
        // Match by shortname prefix first — same source of truth as constants::is_allowed_nomination_course_shortname().
        // Longer prefixes listed before shorter ones to avoid partial matches (e.g. ECIP-GW before ECIP-).
        $shortname = \core_text::strtoupper(trim($course->shortname ?? ''));
        $prefixmap = [
            'ADVC102'                  => 'advance_c_programming',
            'DS104'                    => 'dsa',
            'MC105'                    => 'microcontrollers',
            'LI106'                    => 'linux_networking',
            'ECIP-GW_IOT_PROTOCOL102'  => 'iot_gateway',
            'ECIP-PYTHON'              => 'python_programming',
            'ECIP-ARDUINO'             => 'arduino',
            'ECEP-ELARM'               => 'embedded_linux',
            'IOTCLOUD'                 => 'iot_cloud',
            'LS101'                    => 'linux_systems',
            'CPP103'                   => 'cpp_programming',
            'QT107'                    => 'qt_programming',
        ];
        foreach ($prefixmap as $prefix => $profile) {
            if (\core_text::strpos($shortname, $prefix) === 0) {
                return $profile;
            }
        }

        // Fallback: fullname text matching for courses without a recognised shortname prefix.
        $haystack = \core_text::strtolower(trim($course->fullname ?? ''));

        if (strpos($haystack, 'advance') !== false && strpos($haystack, 'c programming') !== false) {
            return 'advance_c_programming';
        }
        if (strpos($haystack, 'data structure') !== false || preg_match('/\bdsa\b/', $haystack)) {
            return 'dsa';
        }
        if (strpos($haystack, 'microcontroller') !== false) {
            return 'microcontrollers';
        }
        if (strpos($haystack, 'linux internals') !== false) {
            return 'linux_networking';
        }
        if (strpos($haystack, 'python programming') !== false) {
            return 'python_programming';
        }
        if (strpos($haystack, 'arduino') !== false) {
            return 'arduino';
        }
        if (strpos($haystack, 'embedded linux') !== false || strpos($haystack, 'elarm') !== false) {
            return 'embedded_linux';
        }
        if (strpos($haystack, 'linux systems') !== false) {
            return 'linux_systems';
        }
        if (strpos($haystack, 'c++ programming') !== false || strpos($haystack, 'cpp programming') !== false) {
            return 'cpp_programming';
        }
        if (strpos($haystack, 'qt programming') !== false) {
            return 'qt_programming';
        }
        if (strpos($haystack, 'iot') !== false && strpos($haystack, 'gateway') !== false) {
            return 'iot_gateway';
        }
        if (strpos($haystack, 'iot') !== false && strpos($haystack, 'cloud') !== false) {
            return 'iot_cloud';
        }

        return null;
    }

    /**
     * Load reportable activities for a course.
     *
     * @param stdClass $course
     * @param string $activitytype
     * @return array
     */
    private static function get_course_report_activities(stdClass $course, string $activitytype = ''): array {
        global $DB;

        if (!self::table_has_field('grade_items', 'itemmodule') ||
            !self::table_has_field('grade_items', 'iteminstance') ||
            !self::table_has_field('grade_items', 'itemname')) {
            return [];
        }

        $sql = "SELECT gi.id, gi.itemmodule, gi.iteminstance, gi.itemname, gi.grademax
                  FROM {grade_items} gi
                 WHERE gi.courseid = :courseid
                   AND gi.itemtype = :itemtype
                   AND gi.itemnumber = :itemnumber
                   AND gi.itemmodule IN ('assign', 'quiz', 'attendance', 'vpl')
              ORDER BY gi.itemmodule ASC, gi.itemname ASC";

        $records = $DB->get_records_sql($sql, [
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemnumber' => 0,
        ]);

        $activities = [];
        foreach ($records as $record) {
            $classification = self::classify_report_activity($course, $record);
            if ($classification === null) {
                continue;
            }

            if ($activitytype !== '' && $classification['category'] !== $activitytype) {
                continue;
            }

            $activities[] = [
                'gradeitemid' => (int)$record->id,
                'module' => $record->itemmodule,
                'iteminstance' => (int)$record->iteminstance,
                'activityname' => trim((string)$record->itemname),
                'grademax' => $record->grademax !== null ? (float)$record->grademax : null,
                'category' => $classification['category'],
                'categorylabel' => $classification['categorylabel'],
                'typelabel' => $classification['typelabel'],
            ];
        }

        usort($activities, [self::class, 'sort_report_activities']);
        return $activities;
    }

    /**
     * Classify a report activity.
     *
     * @param stdClass $course
     * @param stdClass $record
     * @return array|null
     */
    private static function classify_report_activity(stdClass $course, stdClass $record): ?array {
        $profile = self::get_report_course_profile($course);
        $name = trim((string)$record->itemname);
        $prefix = self::extract_report_prefix($name);

        switch ($record->itemmodule) {
            case 'quiz':
                return [
                    'category' => 'quiz',
                    'categorylabel' => get_string('quizlabel', 'local_spotaward'),
                    'typelabel' => get_string('quizlabel', 'local_spotaward'),
                ];

            case 'attendance':
                return [
                    'category' => 'attendance',
                    'categorylabel' => get_string('attendancelabel', 'local_spotaward'),
                    'typelabel' => get_string('attendancelabel', 'local_spotaward'),
                ];

            case 'vpl':
                if (in_array($profile, ['advance_c_programming', 'cpp_programming', 'qt_programming'], true)) {
                    if ($prefix === 'C') {
                        return [
                            'category' => 'assignments',
                            'categorylabel' => get_string('assignments', 'local_spotaward'),
                            'typelabel' => get_string('templateprograms', 'local_spotaward'),
                        ];
                    }

                    if ($prefix === 'T') {
                        return [
                            'category' => 'assignments',
                            'categorylabel' => get_string('assignments', 'local_spotaward'),
                            'typelabel' => get_string('classworks', 'local_spotaward'),
                        ];
                    }
                }

                return [
                    'category' => 'assignments',
                    'categorylabel' => get_string('assignments', 'local_spotaward'),
                    'typelabel' => get_string('assignments', 'local_spotaward'),
                ];

            case 'assign':
                if (in_array($profile, [
                    'advance_c_programming', 'dsa', 'linux_networking', 'python_programming',
                    'linux_systems', 'cpp_programming', 'qt_programming', 'embedded_linux',
                ], true)) {
                    return [
                        'category' => 'projects',
                        'categorylabel' => get_string('projects', 'local_spotaward'),
                        'typelabel' => get_string('projects', 'local_spotaward'),
                    ];
                }

                if (in_array($profile, ['microcontrollers', 'arduino', 'iot_gateway', 'iot_cloud'], true)) {
                    if ($prefix === 'P') {
                        return [
                            'category' => 'projects',
                            'categorylabel' => get_string('projects', 'local_spotaward'),
                            'typelabel' => get_string('projects', 'local_spotaward'),
                        ];
                    }

                    return [
                        'category' => 'assignments',
                        'categorylabel' => get_string('assignments', 'local_spotaward'),
                        'typelabel' => get_string('assignments', 'local_spotaward'),
                    ];
                }

                return [
                    'category' => 'assignments',
                    'categorylabel' => get_string('assignments', 'local_spotaward'),
                    'typelabel' => get_string('assignments', 'local_spotaward'),
                ];
        }

        return null;
    }

    /**
     * Extract the leading report prefix from an activity name.
     *
     * @param string $activityname
     * @return string
     */
    private static function extract_report_prefix(string $activityname): string {
        if (preg_match('/^\s*([A-Z])\s*\d+/i', $activityname, $matches)) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    /**
     * Load grade values indexed by grade item and user.
     *
     * @param array $activities
     * @param array $studentids
     * @return array
     */
    private static function get_grade_item_grade_map(array $activities, array $studentids): array {
        global $DB;

        if (empty($activities) || empty($studentids) ||
            !self::table_has_field('grade_grades', 'itemid') ||
            !self::table_has_field('grade_grades', 'userid') ||
            !self::table_has_field('grade_grades', 'finalgrade')) {
            return [];
        }

        $gradeitemids = array_map(static function(array $activity): int {
            return (int)$activity['gradeitemid'];
        }, $activities);

        [$gradesql, $gradeparams] = $DB->get_in_or_equal($gradeitemids, SQL_PARAMS_NAMED, 'gi');
        [$usersql, $userparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'su');

        $grades = [];
        $sql = "SELECT gg.itemid, gg.userid, gg.finalgrade
                  FROM {grade_grades} gg
                 WHERE gg.itemid $gradesql
                   AND gg.userid $usersql";

        $recordset = $DB->get_recordset_sql($sql, $gradeparams + $userparams);
        foreach ($recordset as $record) {
            $grades[(int)$record->itemid][(int)$record->userid] = $record->finalgrade !== null ? (float)$record->finalgrade : null;
        }
        $recordset->close();

        return $grades;
    }

    /**
     * Load attendance percentages by attendance instance and student.
     *
     * @param int $courseid
     * @param array $activities
     * @param array $studentids
     * @return array
     */
    private static function get_attendance_report_map(int $courseid, array $activities, array $studentids): array {
        global $DB;

        if (empty($activities) || empty($studentids) ||
            !self::table_has_field('attendance', 'id') ||
            !self::table_has_field('attendance', 'course') ||
            !self::table_has_field('attendance_sessions', 'attendanceid') ||
            !self::table_has_field('attendance_log', 'sessionid') ||
            !self::table_has_field('attendance_log', 'studentid')) {
            return [];
        }

        $attendanceids = [];
        foreach ($activities as $activity) {
            if ($activity['module'] === 'attendance') {
                $attendanceids[] = (int)$activity['iteminstance'];
            }
        }

        if (empty($attendanceids)) {
            return [];
        }

        [$attsql, $attparams] = $DB->get_in_or_equal($attendanceids, SQL_PARAMS_NAMED, 'at');
        [$usersql, $userparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'au');

        $totalsql = "SELECT a.id AS attendanceid, COUNT(1) AS totalsessions
                       FROM {attendance_sessions} s
                       JOIN {attendance} a ON a.id = s.attendanceid
                      WHERE a.course = :courseid
                        AND a.id $attsql
                   GROUP BY a.id";
        $totalrecords = $DB->get_records_sql($totalsql, ['courseid' => $courseid] + $attparams);

        $countsql = "SELECT a.id AS attendanceid, al.studentid, COUNT(DISTINCT al.sessionid) AS attended
                       FROM {attendance_log} al
                       JOIN {attendance_sessions} s ON s.id = al.sessionid
                       JOIN {attendance} a ON a.id = s.attendanceid
                      WHERE a.course = :courseid
                        AND a.id $attsql
                        AND al.studentid $usersql
                   GROUP BY a.id, al.studentid";

        $attendedcounts = [];
        $recordset = $DB->get_recordset_sql($countsql, ['courseid' => $courseid] + $attparams + $userparams);
        foreach ($recordset as $record) {
            $attendedcounts[(int)$record->attendanceid][(int)$record->studentid] = (int)$record->attended;
        }
        $recordset->close();

        $percentages = [];
        foreach ($totalrecords as $record) {
            $attendanceid = (int)$record->attendanceid;
            $totalsessions = (int)$record->totalsessions;
            if ($totalsessions <= 0) {
                continue;
            }

            foreach ($studentids as $studentid) {
                $attended = $attendedcounts[$attendanceid][$studentid] ?? 0;
                $percentages[$attendanceid][$studentid] = [
                    'attended' => $attended,
                    'total' => $totalsessions,
                    'percentage' => round(($attended / $totalsessions) * 100, 2) . '%',
                ];
            }
        }

        return $percentages;
    }

    /**
     * Build grouped summary rows by student.
     *
     * @param array $rowsbystudent
     * @return array
     */
    private static function build_report_summary_rows(array $rowsbystudent): array {
        $summarybystudent = [];

        foreach ($rowsbystudent as $studentid => $rows) {
            $groups = [];

            foreach ($rows as $row) {
                $key = $row['typelabel'];
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'activity' => $row['typelabel'],
                        'scoretotal' => 0.0,
                        'scorecount' => 0,
                        'completionvalue' => 0,
                        'completiontotal' => 0,
                        'ordervalue' => self::get_summary_order($row['typelabel']),
                    ];
                }

                if ($row['scorepercent'] !== null) {
                    $groups[$key]['scoretotal'] += $row['scorepercent'];
                    $groups[$key]['scorecount']++;
                }

                $groups[$key]['completionvalue'] += $row['completionvalue'];
                $groups[$key]['completiontotal'] += $row['completiontotal'];
            }

            uasort($groups, static function(array $left, array $right): int {
                if ($left['ordervalue'] !== $right['ordervalue']) {
                    return $left['ordervalue'] <=> $right['ordervalue'];
                }

                return strcasecmp($left['activity'], $right['activity']);
            });

            $summaryrows = [];
            foreach ($groups as $group) {
                $percentage = '-';
                if ($group['scorecount'] > 0) {
                    $average = $group['scoretotal'] / $group['scorecount'];
                    $percentage = rtrim(rtrim(number_format($average, 2, '.', ''), '0'), '.') . '%';
                }

                $completionrate = '-';
                if ($group['completiontotal'] > 0) {
                    $completionpercentage = ($group['completionvalue'] / $group['completiontotal']) * 100;
                    $completionrate = $group['completionvalue'] . ' / ' . $group['completiontotal'] .
                        ' (' . rtrim(rtrim(number_format($completionpercentage, 2, '.', ''), '0'), '.') . '%)';
                }

                $summaryrows[] = [
                    'activity' => $group['activity'],
                    'percentage' => $percentage,
                    'completionrate' => $completionrate,
                ];
            }

            $summarybystudent[$studentid] = $summaryrows;
        }

        return $summarybystudent;
    }

    /**
     * Get score percentage for a report row.
     *
     * @param array $activity
     * @param float|null $grade
     * @param array|null $attendancedata
     * @return float|null
     */
    private static function get_report_score_percent(array $activity, ?float $grade, ?array $attendancedata): ?float {
        if ($activity['module'] === 'attendance') {
            if (empty($attendancedata['total'])) {
                return null;
            }

            return ($attendancedata['attended'] / $attendancedata['total']) * 100;
        }

        if ($grade === null || empty($activity['grademax']) || $activity['grademax'] <= 0) {
            return null;
        }

        return ($grade / $activity['grademax']) * 100;
    }

    /**
     * Get completion data for a report row.
     *
     * @param array $activity
     * @param float|null $grade
     * @param array|null $attendancedata
     * @return array
     */
    private static function get_report_completion_data(array $activity, ?float $grade, ?array $attendancedata): array {
        if ($activity['module'] === 'attendance') {
            return [
                'value' => (int)($attendancedata['attended'] ?? 0),
                'total' => (int)($attendancedata['total'] ?? 0),
            ];
        }

        return [
            'value' => $grade !== null ? 1 : 0,
            'total' => 1,
        ];
    }

    /**
     * Sort summary rows in the expected order.
     *
     * @param string $label
     * @return int
     */
    private static function get_summary_order(string $label): int {
        $order = [
            get_string('attendancelabel', 'local_spotaward') => 10,
            get_string('assignments', 'local_spotaward') => 20,
            get_string('templateprograms', 'local_spotaward') => 30,
            get_string('classworks', 'local_spotaward') => 40,
            get_string('quizlabel', 'local_spotaward') => 50,
            get_string('projects', 'local_spotaward') => 60,
        ];

        return $order[$label] ?? 99;
    }

    /**
     * Format a report grade value for display.
     *
     * @param array $activity
     * @param float|null $grade
     * @param string|null $attendancepercentage
     * @return string
     */
    private static function format_report_grade(array $activity, ?float $grade, ?string $attendancepercentage): string {
        if ($activity['module'] === 'attendance' && $attendancepercentage !== null) {
            return $attendancepercentage;
        }

        if ($grade === null) {
            return '-';
        }

        return self::format_numeric_grade($grade, $activity['grademax']);
    }

    /**
     * Format numeric grade values.
     *
     * @param float $grade
     * @param float|null $grademax
     * @return string
     */
    private static function format_numeric_grade(float $grade, ?float $grademax): string {
        $formattedgrade = rtrim(rtrim(number_format($grade, 2, '.', ''), '0'), '.');

        if ($grademax === null || $grademax <= 0) {
            return $formattedgrade;
        }

        $formattedmax = rtrim(rtrim(number_format($grademax, 2, '.', ''), '0'), '.');
        return $formattedgrade . ' / ' . $formattedmax;
    }

    /**
     * Sort report activities in the required display order.
     *
     * @param array $left
     * @param array $right
     * @return int
     */
    private static function sort_report_activities(array $left, array $right): int {
        $order = [
            'assignments' => 1,
            'projects' => 2,
            'quiz' => 3,
            'attendance' => 4,
        ];

        $leftorder = $order[$left['category']] ?? 99;
        $rightorder = $order[$right['category']] ?? 99;

        if ($leftorder !== $rightorder) {
            return $leftorder <=> $rightorder;
        }

        return strnatcasecmp($left['activityname'], $right['activityname']);
    }

    /**
     * Check whether a table contains a given field.
     *
     * @param string $tablename
     * @param string $fieldname
     * @return bool
     */
    private static function table_has_field(string $tablename, string $fieldname): bool {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table($tablename);
        $field = new \xmldb_field($fieldname);

        return $dbman->table_exists($table) && $dbman->field_exists($table, $field);
    }

    /**
     * Attendance percentage.
     *
     * @param int $studentid
     * @param int $courseid
     * @return string
     */
    private static function get_attendance_percentage(int $studentid, int $courseid): string {
        global $DB;

        if (!self::table_has_field('attendance_sessions', 'id') ||
            !self::table_has_field('attendance_log', 'sessionid') ||
            !self::table_has_field('attendance_log', 'studentid')) {
            return '-';
        }

        try {
            if (self::table_has_field('attendance_sessions', 'courseid')) {
                $totalsessions = (int)$DB->count_records('attendance_sessions', ['courseid' => $courseid]);
                if ($totalsessions === 0) {
                    return '-';
                }

                $sql = "SELECT COUNT(1)
                          FROM {attendance_log} al
                          JOIN {attendance_sessions} s ON s.id = al.sessionid
                         WHERE s.courseid = :courseid
                           AND al.studentid = :studentid";
                $attended = (int)$DB->count_records_sql($sql, ['courseid' => $courseid, 'studentid' => $studentid]);

                return round(($attended / $totalsessions) * 100, 2) . '%';
            }

            if (self::table_has_field('attendance_sessions', 'attendanceid') &&
                self::table_has_field('attendance', 'id') &&
                self::table_has_field('attendance', 'course')) {
                $sql = "SELECT COUNT(1)
                          FROM {attendance_sessions} s
                          JOIN {attendance} a ON a.id = s.attendanceid
                         WHERE a.course = :courseid";
                $totalsessions = (int)$DB->count_records_sql($sql, ['courseid' => $courseid]);
                if ($totalsessions === 0) {
                    return '-';
                }

                $sql = "SELECT COUNT(1)
                          FROM {attendance_log} al
                          JOIN {attendance_sessions} s ON s.id = al.sessionid
                          JOIN {attendance} a ON a.id = s.attendanceid
                         WHERE a.course = :courseid
                           AND al.studentid = :studentid";
                $attended = (int)$DB->count_records_sql($sql, ['courseid' => $courseid, 'studentid' => $studentid]);

                return round(($attended / $totalsessions) * 100, 2) . '%';
            }
        } catch (\dml_exception $e) {
            debugging('Spot Award attendance report skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return '-';
    }

    /**
     * Assignment completion.
     *
     * @param int $studentid
     * @param int $courseid
     * @return string
     */
    private static function get_assignment_completion(int $studentid, int $courseid): string {
        global $DB;

        if (!self::table_has_field('assign', 'course') ||
            !self::table_has_field('assign_grades', 'assignment') ||
            !self::table_has_field('assign_grades', 'userid')) {
            return '-';
        }

        try {
            $total = (int)$DB->count_records('assign', ['course' => $courseid]);
            if ($total === 0) {
                return '-';
            }

            $sql = "SELECT COUNT(DISTINCT ag.assignment)
                      FROM {assign_grades} ag
                      JOIN {assign} a ON a.id = ag.assignment
                     WHERE a.course = :courseid
                       AND ag.userid = :studentid";
            $completed = (int)$DB->count_records_sql($sql, ['courseid' => $courseid, 'studentid' => $studentid]);

            return $completed . ' / ' . $total;
        } catch (\dml_exception $e) {
            debugging('Spot Award assignment report skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '-';
        }
    }

    /**
     * Project completion.
     *
     * @param int $studentid
     * @param int $courseid
     * @return string
     */
    private static function get_project_completion(int $studentid, int $courseid): string {
        global $DB;

        if (!self::table_has_field('course_modules', 'course') ||
            !self::table_has_field('course_modules', 'completion') ||
            !self::table_has_field('course_modules_completion', 'coursemoduleid') ||
            !self::table_has_field('course_modules_completion', 'userid') ||
            !self::table_has_field('course_modules_completion', 'completionstate')) {
            return '-';
        }

        try {
            $sql = "SELECT COUNT(1)
                      FROM {course_modules} cm
                     WHERE cm.course = :courseid
                       AND cm.completion > 0";
            $total = (int)$DB->count_records_sql($sql, ['courseid' => $courseid]);
            if ($total === 0) {
                return '-';
            }

            $sql = "SELECT COUNT(1)
                      FROM {course_modules_completion} cmc
                      JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                     WHERE cm.course = :courseid
                       AND cm.completion > 0
                       AND cmc.userid = :studentid
                       AND cmc.completionstate > 0";
            $completed = (int)$DB->count_records_sql($sql, ['courseid' => $courseid, 'studentid' => $studentid]);

            return $completed . ' / ' . $total;
        } catch (\dml_exception $e) {
            debugging('Spot Award project report skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '-';
        }
    }

    /**
     * Get submission history for nominator.
     *
     * @param int $userid
     * @return array
     */
    public static function get_nominator_submissions(int $userid): array {
        global $DB;

        $sql = "SELECT n.*, c.fullname AS coursename, u.firstname AS pmfirstname, u.lastname AS pmlastname,
                       COALESCE(ni_agg.totalitems, 0) AS totalitems,
                       COALESCE(ni_agg.approveditems, 0) AS approveditems,
                       COALESCE(ni_agg.rejecteditems, 0) AS rejecteditems
                  FROM {spotaward_nominations} n
                  JOIN {course} c ON c.id = n.courseid
             LEFT JOIN {user} u ON u.id = n.programmanagerid
             LEFT JOIN (
                       SELECT nominationid,
                              COUNT(*) AS totalitems,
                              SUM(CASE WHEN status = 'ssteamprogress' THEN 1 ELSE 0 END) AS approveditems,
                              SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejecteditems
                         FROM {spotaward_nomination_items}
                     GROUP BY nominationid
                       ) ni_agg ON ni_agg.nominationid = n.id
                  WHERE n.nominatorid = :userid
               ORDER BY n.timecreated DESC";

        $submissions = array_values($DB->get_records_sql($sql, ['userid' => $userid]));

        foreach ($submissions as $submission) {
            if (in_array($submission->status, ['ssteamprogress', 'closed', 'rejected'], true)) {
                continue;
            }

            $reviewed = $submission->approveditems + $submission->rejecteditems;
            if ($submission->totalitems == 0 || $reviewed == 0) {
                $submission->status = 'pending';
            } else if ($reviewed == $submission->totalitems) {
                $submission->status = 'reviewed';
            } else {
                $submission->status = 'underreview';
            }
        }

        return $submissions;
    }

    /**
     * Get submissions assigned to program manager.
     *
     * @param int $userid
     * @return array
     */
    public static function get_program_manager_submissions(int $userid, string $statusfilter = ''): array {
        global $DB;

        $syscontextid = \context_system::instance()->id;
        $params = ['userid' => $userid, 'syscontextid' => $syscontextid];
        $where = "n.programmanagerid = :userid";

        if ($statusfilter === 'active') {
            $where .= " AND n.status IN ('pending', 'ssteamprogress')";
        } else if ($statusfilter === 'closed') {
            $where .= " AND n.status IN ('closed', 'rejected')";
        }

        $sql = "SELECT n.*, c.fullname AS coursename,
                       u.firstname, u.lastname,
                       maac.firstname AS maacfirstname, maac.lastname AS maaclastname,
                       (SELECT COUNT(1) FROM {spotaward_nomination_items} ni
                         WHERE ni.nominationid = n.id) AS totalitems,
                       (SELECT COUNT(1) FROM {spotaward_nomination_items} ni
                         WHERE ni.nominationid = n.id AND ni.status IN ('ssteamprogress', 'rejected', 'closed')) AS revieweditems,
                       (SELECT COUNT(1) FROM {files} f
                         WHERE f.contextid = :syscontextid
                           AND f.component = 'local_spotaward'
                           AND f.filearea = 'certificates'
                           AND f.itemid = n.id
                           AND f.filename <> '.') AS certificatesexist
                  FROM {spotaward_nominations} n
                  JOIN {course} c ON c.id = n.courseid
                  JOIN {user} u ON u.id = n.nominatorid
             LEFT JOIN {user} maac ON maac.id = n.maacexecutiveid
                 WHERE {$where}
               ORDER BY n.timecreated DESC";

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Count nominations by status, optionally filtered by mentor/PM/MAAC.
     *
     * @param int $mentorid
     * @param int $programmanagerid
     * @param int $maacexecutiveid
     * @return array keys: pending, rejected, ssteamprogress, closed
     */
    public static function get_nomination_counts(int $mentorid = 0, int $programmanagerid = 0,
            int $maacexecutiveid = 0): array {
        global $DB;

        $params = [];
        $where = [];
        if ($mentorid) {
            $where[] = 'n.nominatorid = :mentorid';
            $params['mentorid'] = $mentorid;
        }
        if ($programmanagerid) {
            $where[] = 'n.programmanagerid = :programmanagerid';
            $params['programmanagerid'] = $programmanagerid;
        }
        if ($maacexecutiveid) {
            $where[] = 'n.maacexecutiveid = :maacexecutiveid';
            $params['maacexecutiveid'] = $maacexecutiveid;
        }

        $wheresql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $counts = [
            'pending' => 0,
            'partiallyreviewed' => 0,
            'rejected' => 0,
            'ssteamprogress' => 0,
            'closed' => 0,
        ];

        $sql = "SELECT n.status, COUNT(1) AS cnt
                  FROM {spotaward_nominations} n
                 {$wheresql}
              GROUP BY n.status";
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            if (isset($counts[$record->status])) {
                $counts[$record->status] = (int)$record->cnt;
            }
        }

        // Split 'pending' into pure-pending vs partially-reviewed.
        if ($counts['pending'] > 0) {
            $prparams = $params;
            $prwhere = $wheresql ? $wheresql . ' AND ' : 'WHERE ';
            $prwhere .= "n.status = 'pending'
                         AND EXISTS (
                             SELECT 1 FROM {spotaward_nomination_items} ni
                              WHERE ni.nominationid = n.id AND ni.status IN ('ssteamprogress', 'rejected', 'closed')
                         )";
            $prsql = "SELECT COUNT(1) AS cnt FROM {spotaward_nominations} n {$prwhere}";
            $prcount = (int)$DB->count_records_sql($prsql, $prparams);
            $counts['partiallyreviewed'] = $prcount;
            $counts['pending'] = max(0, $counts['pending'] - $prcount);
        }

        return $counts;
    }

    /**
     * Get manager dashboard data.
     *
     * @param int $mentorid
     * @param int $programmanagerid
     * @return array
     */
    public static function get_manager_dashboard_data(int $mentorid = 0, int $programmanagerid = 0,
            int $maacexecutiveid = 0, string $statusfilter = ''): array {
        global $DB;

        $params = [];
        $where = [];
        if ($mentorid) {
            $where[] = 'n.nominatorid = :mentorid';
            $params['mentorid'] = $mentorid;
        }
        if ($programmanagerid) {
            $where[] = 'n.programmanagerid = :programmanagerid';
            $params['programmanagerid'] = $programmanagerid;
        }
        if ($maacexecutiveid) {
            $where[] = 'n.maacexecutiveid = :maacexecutiveid';
            $params['maacexecutiveid'] = $maacexecutiveid;
        }

        $wheresql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $counts = self::get_nomination_counts($mentorid, $programmanagerid, $maacexecutiveid);

        $statuswhere = '';
        if ($statusfilter === 'active') {
            $statuswhere = "n.status IN ('pending', 'ssteamprogress')";
        } else if ($statusfilter === 'closed') {
            $statuswhere = "n.status IN ('closed', 'rejected')";
        } else if (in_array($statusfilter, ['pending', 'rejected', 'ssteamprogress', 'closed'], true)) {
            $statuswhere = "n.status = :statusfilter";
            $params['statusfilter'] = $statusfilter;
        }

        $combined = $where;
        if ($statuswhere) {
            $combined[] = $statuswhere;
        }
        $combinedsql = $combined ? ('WHERE ' . implode(' AND ', $combined)) : '';

        $syscontextid = \context_system::instance()->id;
        $params['syscontextid'] = $syscontextid;

        $sql = "SELECT n.*, c.fullname AS coursename,
                       mentor.firstname AS mentorfirstname, mentor.lastname AS mentorlastname,
                       pm.firstname AS pmfirstname, pm.lastname AS pmlastname,
                       maac.firstname AS maacfirstname, maac.lastname AS maaclastname,
                       (SELECT COUNT(1) FROM {spotaward_nomination_items} ni
                         WHERE ni.nominationid = n.id) AS totalitems,
                       (SELECT COUNT(1) FROM {spotaward_nomination_items} ni
                         WHERE ni.nominationid = n.id AND ni.status IN ('ssteamprogress', 'rejected', 'closed')) AS revieweditems,
                       (SELECT COUNT(1) FROM {files} f
                         WHERE f.contextid = :syscontextid
                           AND f.component = 'local_spotaward'
                           AND f.filearea = 'certificates'
                           AND f.itemid = n.id
                           AND f.filename <> '.') AS certificatesexist
                  FROM {spotaward_nominations} n
                  JOIN {course} c ON c.id = n.courseid
                  JOIN {user} mentor ON mentor.id = n.nominatorid
                  JOIN {user} pm ON pm.id = n.programmanagerid
             LEFT JOIN {user} maac ON maac.id = n.maacexecutiveid
                  {$combinedsql}
              ORDER BY n.timecreated DESC";

        return [
            'counts' => $counts,
            'records' => array_values($DB->get_records_sql($sql, $params)),
        ];
    }

    /**
     * Get SS Team dashboard data.
     *
     * @return array
     */
    public static function get_ss_team_dashboard_data(string $statusfilter = ''): array {
        $filter = $statusfilter;
        if ($filter === 'active') {
            $filter = 'ssteamprogress';
        }
        global $USER;
        return self::get_manager_dashboard_data(0, 0, (int)$USER->id, $filter);
    }

    /**
     * Refresh nomination status based on items.
     *
     * @param int $nominationid
     * @return void
     */
    public static function refresh_nomination_status(int $nominationid): void {
        global $DB;

        $nomination = self::get_nomination($nominationid);
        $oldstatus = $nomination->status;

        $items = self::get_nomination_items($nominationid);

        $haspending = false;
        $hasssteamprogress = false;
        $hasrejected = false;
        $hasclosed = false;

        foreach ($items as $item) {
            switch ($item->status) {
                case 'pending':
                case 'underreview':
                    $haspending = true;
                    break;
                case 'ssteamprogress':
                    $hasssteamprogress = true;
                    break;
                case 'rejected':
                    $hasrejected = true;
                    break;
                case 'closed':
                    $hasclosed = true;
                    break;
            }
        }

        // Any unreviewed student holds the whole nomination back (Option A).
        // Only when every student has been approved or rejected does the nomination advance.
        if ($haspending) {
            $newstatus = 'pending';
        } else if ($hasssteamprogress) {
            $newstatus = 'ssteamprogress';
        } else if ($hasclosed) {
            $newstatus = 'closed';
        } else if ($hasrejected) {
            $newstatus = 'rejected';
        } else {
            $newstatus = 'pending';
        }

        $DB->update_record('spotaward_nominations', (object)[
            'id' => $nominationid,
            'status' => $newstatus,
            'timemodified' => time(),
        ]);
        unset(self::$nominationcache[$nominationid]);

        if ($oldstatus !== $newstatus) {
            if ($newstatus === 'ssteamprogress') {
                self::send_program_manager_approval_to_ss_notification($nominationid);
                self::send_program_manager_decision_to_mentor_notification($nominationid, 'approved');
                self::send_program_manager_decision_to_program_manager_notification($nominationid, 'approved');
                self::ensure_nomination_certificates_generated($nominationid);
            } else if ($newstatus === 'rejected') {
                self::send_program_manager_decision_to_mentor_notification($nominationid, 'rejected');
                self::send_program_manager_decision_to_program_manager_notification($nominationid, 'rejected');
            }
        }
    }

    /**
     * Get configured manager role ID.
     *
     * @return int
     */
    private static function get_manager_roleid(): int {
        global $DB;

        $shortname = (string)get_config('local_spotaward', 'manager_role');
        if ($shortname === '') {
            $shortname = 'manager';
        }

        $role = $DB->get_record('role', ['shortname' => $shortname], 'id');
        return $role ? (int)$role->id : 0;
    }

    /**
     * Require nomination access.
     *
     * @param stdClass $nomination
     * @param int $userid
     * @return void
     */
    public static function require_nomination_access(stdClass $nomination, int $userid): void {
        if (!self::can_access_nomination($nomination, $userid)) {
            throw new moodle_exception('notauthorised', 'local_spotaward');
        }
    }

    /**
     * Check if user can access nomination.
     *
     * @param stdClass $nomination
     * @param int $userid
     * @return bool
     */
    public static function can_access_nomination(stdClass $nomination, int $userid): bool {
        if (is_siteadmin($userid)) {
            return true;
        }

        if ($nomination->nominatorid == $userid) {
            return true;
        }

        if ($nomination->programmanagerid == $userid) {
            return true;
        }

        if (!empty($nomination->maacexecutiveid) && (int)$nomination->maacexecutiveid === $userid) {
            return true;
        }

        $ismanger = self::is_manager($userid);
        if ($ismanger) {
            return true;
        }

        return false;
    }

    /**
     * Whether the user is the assigned MAAC Executive for the nomination.
     *
     * @param stdClass $nomination
     * @param int $userid
     * @return bool
     */
    public static function is_assigned_maac_executive(stdClass $nomination, int $userid): bool {
        return !empty($nomination->maacexecutiveid) && (int)$nomination->maacexecutiveid === $userid;
    }

    /**
     * Check if a nomination can be deleted.
     *
     * @param stdClass $nomination
     * @param int $userid
     * @return bool
     */
    public static function can_delete_nomination(stdClass $nomination, int $userid): bool {
        if (is_siteadmin($userid) || self::is_manager($userid)) {
            return true;
        }

        return in_array($nomination->status, ['pending', 'rejected'], true);
    }

    /**
     * Update item status.
     *
     * @param int $itemid
     * @param string $status
     * @param int $actorid
     * @param string|null $reason
     * @param bool $skiprefresh
     * @return void
     */
    public static function update_item_status(int $itemid, string $status, int $actorid, ?string $reason = null, bool $skiprefresh = false): void {
        global $DB;

        $item = $DB->get_record('spotaward_nomination_items', ['id' => $itemid], '*', MUST_EXIST);
        $nomination = self::get_nomination($item->nominationid);

        if (!self::can_access_nomination($nomination, $actorid)) {
            throw new moodle_exception('notauthorised', 'local_spotaward');
        }

        $allowedtransitions = [
            'pending'         => ['ssteamprogress', 'rejected'],
            'underreview'     => ['ssteamprogress', 'rejected'],
            'ssteamprogress'  => ['closed', 'rejected'],
            'rejected'        => ['ssteamprogress'],
            'closed'          => [],
        ];
        $fromstatus = $item->status;
        $allowed = $allowedtransitions[$fromstatus] ?? [];
        if (!in_array($status, $allowed, true)) {
            throw new moodle_exception('invalidstatustransition', 'local_spotaward');
        }

        $item->status = $status;
        if ($reason !== null) {
            $item->rejectionreason = $reason;
        }
        $item->reviewedby = $actorid;
        $item->timereviewed = time();
        $DB->update_record('spotaward_nomination_items', $item);

        // Bust caches so refresh_nomination_status() and any downstream callers
        // (e.g. ensure_nomination_certificates_generated) read the updated DB state.
        unset(self::$nominationitemscache[$item->nominationid]);
        unset(self::$nominationcache[$item->nominationid]);

        $DB->insert_record('spotaward_status_track', (object)[
            'nominationid' => $item->nominationid,
            'nominationitemid' => $itemid,
            'actorid' => $actorid,
            'fromstatus' => $fromstatus,
            'tostatus' => $status,
            'reason' => $reason,
            'timecreated' => time(),
        ]);

        if (!$skiprefresh) {
            self::refresh_nomination_status($item->nominationid);
        }
    }

    /**
     * Whether a username should be highlighted in Admission ID tables.
     *
     * @param string $username
     * @return bool
     */
    public static function username_needs_admissionid_highlight(string $username): bool {
        $username = trim($username);
        if ($username === '') {
            return false;
        }

        return strpos($username, '.') !== false;
    }

    /**
     * Close a rejected student ticket with a closure date.
     *
     * @param int $itemid
     * @param int $actorid
     * @param string $reason
     * @param int $closuredate
     * @return void
     */
    public static function close_rejected_ticket(int $itemid, int $actorid, string $reason, int $closuredate): void {
        global $DB;

        $item = $DB->get_record('spotaward_nomination_items', ['id' => $itemid], '*', MUST_EXIST);
        $nomination = self::get_nomination($item->nominationid);
        self::require_nomination_access($nomination, $actorid);

        if (!self::is_ss_team($actorid) && !is_siteadmin($actorid)) {
            throw new moodle_exception('notauthorised', 'local_spotaward');
        }
        if ($item->status !== 'rejected') {
            throw new moodle_exception('invalidparameter');
        }

        $fromstatus = $item->status;
        $item->status = 'closed';
        $item->rejectionreason = $reason;
        $item->closuredate = $closuredate;
        $item->reviewedby = $actorid;
        $item->timereviewed = time();
        $DB->update_record('spotaward_nomination_items', $item);

        $DB->insert_record('spotaward_status_track', (object)[
            'nominationid' => $item->nominationid,
            'nominationitemid' => $itemid,
            'actorid' => $actorid,
            'fromstatus' => $fromstatus,
            'tostatus' => 'closed',
            'reason' => $reason . ' Closure date: ' . userdate($closuredate, get_string('strftimedate')),
            'timecreated' => time(),
        ]);

        self::refresh_nomination_status($item->nominationid);
    }

    /**
     * Close a nomination record from the SS Team dashboard.
     *
     * @param int $nominationid
     * @param int $actorid
     * @param int $closuredate
     * @return void
     */
    public static function close_nomination_record(int $nominationid, int $actorid, int $closuredate): void {
        global $DB;

        $nomination = self::get_nomination($nominationid);
        self::require_nomination_access($nomination, $actorid);

        if (!self::is_ss_team($actorid) && !is_siteadmin($actorid)) {
            throw new moodle_exception('notauthorised', 'local_spotaward');
        }
        if ($nomination->status !== 'ssteamprogress') {
            throw new moodle_exception('invalidparameter');
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $closurelabel = get_string('closuredate', 'local_spotaward') . ': ' .
            userdate($closuredate, get_string('strftimedate'));

        foreach (self::get_nomination_items($nominationid) as $item) {
            if ($item->status === 'rejected') {
                continue;
            }
            $fromstatus = $item->status;
            $item->status = 'closed';
            $item->closuredate = $closuredate;
            $item->reviewedby = $actorid;
            $item->timereviewed = $now;
            $DB->update_record('spotaward_nomination_items', $item);

            $DB->insert_record('spotaward_status_track', (object)[
                'nominationid' => $nominationid,
                'nominationitemid' => $item->id,
                'actorid' => $actorid,
                'fromstatus' => $fromstatus,
                'tostatus' => 'closed',
                'reason' => $closurelabel,
                'timecreated' => $now,
            ]);
        }

        $DB->update_record('spotaward_nominations', (object)[
            'id' => $nominationid,
            'status' => 'closed',
            'timemodified' => $now,
        ]);

        $transaction->allow_commit();

        self::send_record_closed_notification($nominationid, $actorid, $closuredate);
    }

    /**
     * Delete nomination.
     *
     * @param int $nominationid
     * @param int $actorid
     * @return void
     */
    public static function delete_nomination(int $nominationid, int $actorid): void {
        global $DB;

        $nomination = self::get_nomination($nominationid);
        self::require_nomination_access($nomination, $actorid);

        if (!self::can_delete_nomination($nomination, $actorid)) {
            throw new moodle_exception('cannotdeletereviewed', 'local_spotaward');
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('spotaward_status_track', ['nominationid' => $nominationid]);
        $DB->delete_records('spotaward_nomination_items', ['nominationid' => $nominationid]);
        $DB->delete_records('spotaward_nominations', ['id' => $nominationid]);
        $transaction->allow_commit();
    }
}
