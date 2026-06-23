<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\local;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Purchase Request template field map.
 *
 * @package   local_spotaward
 */
final class pr_field_map {
    /**
     * Placeholder class name.
     */
    public const CLASS_NAME = 'spotawardpr';

    /**
     * Supported PR template fields.
     *
     * @return array
     */
    public static function table_structure(): array {
        return [
            ['key' => 'nomination_id', 'label' => get_string('field_nomination_id', 'local_spotaward')],
            ['key' => 'course_name', 'label' => get_string('field_course_name', 'local_spotaward')],
            ['key' => 'course_shortname', 'label' => get_string('field_course_shortname', 'local_spotaward')],
            ['key' => 'module_name', 'label' => get_string('field_module_name', 'local_spotaward')],
            ['key' => 'professional', 'label' => get_string('field_professional', 'local_spotaward')],
            ['key' => 'status', 'label' => get_string('field_status', 'local_spotaward')],
            ['key' => 'student_count', 'label' => get_string('field_student_count', 'local_spotaward')],
            ['key' => 'total_students_count', 'label' => get_string('field_total_students_count', 'local_spotaward')],
            ['key' => 'nomination_date', 'label' => get_string('field_nomination_date', 'local_spotaward')],
            ['key' => 'maac_executive_name', 'label' => get_string('field_maac_executive_name', 'local_spotaward')],
            ['key' => 'maac_executive_email', 'label' => get_string('field_maac_executive_email', 'local_spotaward')],
            ['key' => 'ss_team_name', 'label' => get_string('field_ss_team_name', 'local_spotaward')],
            ['key' => 'ss_team_email', 'label' => get_string('field_ss_team_email', 'local_spotaward')],
            ['key' => 'mentor_name', 'label' => get_string('field_nominated_by', 'local_spotaward')],
            ['key' => 'nominated_by', 'label' => get_string('field_nominated_by', 'local_spotaward')],
            ['key' => 'program_manager_name', 'label' => get_string('field_presented_by', 'local_spotaward')],
            ['key' => 'program_manager_email', 'label' => get_string('field_program_manager_email', 'local_spotaward')],
            ['key' => 'presented_by', 'label' => get_string('field_presented_by', 'local_spotaward')],
            ['key' => 'award_summary', 'label' => get_string('field_award_summary', 'local_spotaward')],
            ['key' => 'student_list', 'label' => get_string('field_student_list', 'local_spotaward')],
            ['key' => 'student_emails', 'label' => get_string('field_student_emails', 'local_spotaward')],
            ['key' => 'admission_ids', 'label' => get_string('field_admission_ids', 'local_spotaward')],
        ];
    }

    /**
     * Build PR template data.
     *
     * @param stdClass $course
     * @param stdClass $nomination
     * @param stdClass|null $nominator
     * @param stdClass|null $programmanager
     * @param stdClass|null $maacexecutive
     * @param array $items
     * @param string $awardsummary
     * @return array
     */
    public static function get_data(stdClass $course, stdClass $nomination, ?stdClass $nominator,
            ?stdClass $programmanager, ?stdClass $maacexecutive, array $items, string $awardsummary): array {
        $studentnames = [];
        $studentemails = [];
        $admissionids = [];

        foreach ($items as $item) {
            $studentnames[] = fullname($item);
            if (!empty($item->email)) {
                $studentemails[] = $item->email;
            }
            if (!empty($item->username)) {
                $admissionids[] = $item->username;
            }
        }

        return [
            'nomination_id' => (string)$nomination->id,
            'course_name' => format_string($course->fullname),
            'course_shortname' => format_string($course->shortname),
            'module_name' => (string)($nomination->modulename ?? ''),
            'professional' => (string)($nomination->professional ?? ''),
            'status' => get_string($nomination->status, 'local_spotaward'),
            'student_count' => (string)($nomination->studentcount ?? count($items)),
            'total_students_count' => (string)($nomination->studentcount ?? count($items)),
            'nomination_date' => userdate((int)$nomination->timecreated, '%d-%m-%Y'),
            'maac_executive_name' => $maacexecutive ? fullname($maacexecutive) : '',
            'maac_executive_email' => $maacexecutive->email ?? '',
            'ss_team_name' => $maacexecutive ? fullname($maacexecutive) : '',
            'ss_team_email' => $maacexecutive->email ?? '',
            'mentor_name' => $nominator ? fullname($nominator) : '',
            'nominated_by' => $nominator ? fullname($nominator) : '',
            'program_manager_name' => $programmanager ? fullname($programmanager) : '',
            'program_manager_email' => $programmanager->email ?? '',
            'presented_by' => $programmanager ? fullname($programmanager) : '',
            'award_summary' => $awardsummary,
            'student_list' => implode("\n", $studentnames),
            'student_emails' => implode("\n", array_values(array_unique($studentemails))),
            'admission_ids' => implode("\n", array_values(array_unique($admissionids))),
        ];
    }

    /**
     * Build PR template placeholder replacements.
     *
     * @param stdClass $course
     * @param stdClass $nomination
     * @param stdClass|null $nominator
     * @param stdClass|null $programmanager
     * @param stdClass|null $maacexecutive
     * @param array $items
     * @param string $awardsummary
     * @return array
     */
    public static function get_replacement_fields(stdClass $course, stdClass $nomination, ?stdClass $nominator,
            ?stdClass $programmanager, ?stdClass $maacexecutive, array $items, string $awardsummary): array {
        $data = self::get_data($course, $nomination, $nominator, $programmanager, $maacexecutive, $items, $awardsummary);
        $replacements = [];

        foreach ($data as $key => $value) {
            $replacements['{$SPOTAWARDPR->' . $key . '}'] = $value;
            $replacements['{$spotawardpr->' . $key . '}'] = $value;
            $replacements['{$SPOTAWARD->' . $key . '}'] = $value;
            $replacements['{$spotaward->' . $key . '}'] = $value;
            $replacements['{' . $key . '}'] = $value;
        }

        $replacements['{$COURSE->fullname}'] = $data['course_name'];
        $replacements['{$COURSE->shortname}'] = $data['course_shortname'];
        $replacements['{$course->fullname}'] = $data['course_name'];
        $replacements['{$course->shortname}'] = $data['course_shortname'];
        $replacements['{course->fullname}'] = $data['course_name'];
        $replacements['{course->shortname}'] = $data['course_shortname'];
        $replacements['{date}'] = $data['nomination_date'];

        return $replacements;
    }
}
