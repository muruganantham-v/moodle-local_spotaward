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
 * Certificate placeholder field mapping for Spot Award System.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_spotaward\local;

use stdClass;

defined('MOODLE_INTERNAL') || die();

final class cert_field_map {

    const CLASS_NAME = 'spotaward';

    public static function table_structure(): array {
        return [
            ['key' => 'student_name', 'label' => get_string('field_student_name', 'local_spotaward')],
            ['key' => 'student_firstname', 'label' => get_string('field_student_firstname', 'local_spotaward')],
            ['key' => 'student_lastname', 'label' => get_string('field_student_lastname', 'local_spotaward')],
            ['key' => 'student_email', 'label' => get_string('field_student_email', 'local_spotaward')],
            ['key' => 'student_username', 'label' => get_string('field_student_username', 'local_spotaward')],
            ['key' => 'student_idnumber', 'label' => get_string('field_student_idnumber', 'local_spotaward')],
            ['key' => 'course_name', 'label' => get_string('field_course_name', 'local_spotaward')],
            ['key' => 'course_shortname', 'label' => get_string('field_course_shortname', 'local_spotaward')],
            ['key' => 'module_name', 'label' => get_string('field_module_name', 'local_spotaward')],
            ['key' => 'professional', 'label' => get_string('field_professional', 'local_spotaward')],
            ['key' => 'total_students_count', 'label' => get_string('field_total_students_count', 'local_spotaward')],
            ['key' => 'award_category', 'label' => get_string('field_award_category', 'local_spotaward')],
            ['key' => 'award_description', 'label' => get_string('field_award_description', 'local_spotaward')],
            ['key' => 'nomination_date', 'label' => get_string('field_nomination_date', 'local_spotaward')],
            ['key' => 'issued_date', 'label' => get_string('field_issued_date', 'local_spotaward')],
            ['key' => 'nominated_by', 'label' => get_string('field_nominated_by', 'local_spotaward')],
            ['key' => 'presented_by', 'label' => get_string('field_presented_by', 'local_spotaward')],
            ['key' => 'nominator_email', 'label' => get_string('field_nominator_email', 'local_spotaward')],
            ['key' => 'program_manager_email', 'label' => get_string('field_program_manager_email', 'local_spotaward')],
            ['key' => 'student_institution', 'label' => get_string('field_student_institution', 'local_spotaward')],
            ['key' => 'student_department', 'label' => get_string('field_student_department', 'local_spotaward')],
            ['key' => 'program_manager_signature', 'label' => get_string('field_program_manager_signature', 'local_spotaward')],
            ['key' => 'nominator_signature', 'label' => get_string('field_nominator_signature', 'local_spotaward')],
            ['key' => 'ss_team_signature', 'label' => get_string('field_ss_team_signature', 'local_spotaward')],
        ];
    }

    public static function get_data($course, $student, $nomination, $item, $nominator, $programmanager): array {
        $ssteam = !empty($nomination->maacexecutiveid)
            ? \core_user::get_user((int)$nomination->maacexecutiveid)
            : null;
        $ssteamname = $ssteam ? fullname($ssteam) : '';

        return [
            'student_name' => fullname($student),
            'student_firstname' => $student->firstname ?? '',
            'student_lastname' => $student->lastname ?? '',
            'student_email' => $student->email ?? '',
            'student_username' => $student->username ?? '',
            'student_idnumber' => $student->idnumber ?? '',
            'course_name' => format_string($course->fullname),
            'course_shortname' => format_string($course->shortname),
            'module_name' => $nomination->modulename ?? '',
            'professional' => $item->professional ?? ($nomination->professional ?? ''),
            'total_students_count' => $nomination->studentcount ?? 0,
            'award_category' => $item->awardcategory ?? '',
            'award_description' => $item->awarddescription ?? '',
            'nomination_date' => userdate($nomination->timecreated, '%d-%m-%Y'),
            'issued_date' => userdate($nomination->timemodified ?: $nomination->timecreated, '%d-%m-%Y'),
            'nominated_by' => fullname($nominator),
            'presented_by' => fullname($programmanager),
            'nominator_email' => $nominator->email ?? '',
            'program_manager_email' => $programmanager->email ?? '',
            'student_institution' => $student->institution ?? '',
            'student_department' => $student->department ?? '',
            'program_manager_signature' => fullname($programmanager),
            'nominator_signature' => fullname($nominator),
            'ss_team_signature' => $ssteamname,
        ];
    }

    public static function get_replacement_fields($course, $student, $nomination, $item, $nominator, $programmanager): array {
        $data = self::get_data($course, $student, $nomination, $item, $nominator, $programmanager);
        
        $replacements = [];
        
        // Generate all placeholder variations for maximum compatibility
        foreach ($data as $key => $value) {
            $replacements['{$SPOTAWARD->' . $key . '}'] = $value;
            $replacements['{$spotaward->' . $key . '}'] = $value;
            $replacements['{' . $key . '}'] = $value;
        }
        
        // Add backward compatible placeholders (from original implementation)
        // Lowercase versions
        $replacements['{user->firstname}'] = $data['student_firstname'];
        $replacements['{user->lastname}'] = $data['student_lastname'];
        $replacements['{user->fullname}'] = $data['student_name'];
        $replacements['{user->username}'] = $data['student_username'];
        $replacements['{user->email}'] = $data['student_email'];
        $replacements['{user->idnumber}'] = $data['student_idnumber'];
        $replacements['{user->institution}'] = $data['student_institution'];
        $replacements['{user->department}'] = $data['student_department'];
        
        // Lowercase with $
        $replacements['{$user->firstname}'] = $data['student_firstname'];
        $replacements['{$user->lastname}'] = $data['student_lastname'];
        $replacements['{$user->fullname}'] = $data['student_name'];
        $replacements['{$user->username}'] = $data['student_username'];
        $replacements['{$user->email}'] = $data['student_email'];
        $replacements['{$user->idnumber}'] = $data['student_idnumber'];
        $replacements['{$user->institution}'] = $data['student_institution'];
        $replacements['{$user->department}'] = $data['student_department'];
        
        // UPPERCASE versions (Beautiful Certificate standard!)
        $replacements['{$USER->firstname}'] = $data['student_firstname'];
        $replacements['{$USER->lastname}'] = $data['student_lastname'];
        $replacements['{$USER->fullname}'] = $data['student_name'];
        $replacements['{$USER->username}'] = $data['student_username'];
        $replacements['{$USER->email}'] = $data['student_email'];
        $replacements['{$USER->idnumber}'] = $data['student_idnumber'];
        $replacements['{$USER->institution}'] = $data['student_institution'];
        $replacements['{$USER->department}'] = $data['student_department'];
        
        // Course information - all variations
        $replacements['{$course->fullname}'] = $data['course_name'];
        $replacements['{$course->shortname}'] = $data['course_shortname'];
        $replacements['{$COURSE->fullname}'] = $data['course_name'];
        $replacements['{$COURSE->shortname}'] = $data['course_shortname'];
        $replacements['{course->fullname}'] = $data['course_name'];
        $replacements['{course->shortname}'] = $data['course_shortname'];

        // Site information - all variations
        global $SITE;
        $sitename = format_string($SITE->fullname ?? '');
        $siteshortname = format_string($SITE->shortname ?? '');
        $replacements['{$SITE->fullname}'] = $sitename;
        $replacements['{$site->fullname}'] = $sitename;
        $replacements['{$SITE->shortname}'] = $siteshortname;
        $replacements['{$site->shortname}'] = $siteshortname;
        $replacements['{$SITE->summary}'] = $SITE->summary ?? '';
        $replacements['{$site->summary}'] = $SITE->summary ?? '';
        $replacements['{site->fullname}'] = $sitename;
        $replacements['{site->shortname}'] = $siteshortname;
        $replacements['{$SITE-&gt;fullname}'] = $sitename;
        $replacements['{$site-&gt;fullname}'] = $sitename;
        $replacements['{$SITE-&gt;shortname}'] = $siteshortname;
        $replacements['{$site-&gt;shortname}'] = $siteshortname;
        
        // Certificate issue dates
        $replacements['{certificateissue->date}'] = $data['issued_date'];
        $replacements['{certificateissue->issuedate}'] = $data['issued_date'];
        
        // Additional common placeholders
        $replacements['{roll_no}'] = $data['student_username'];
        $replacements['{recognition_text}'] = $data['award_description'];
        $replacements['{date}'] = $data['issued_date'];
        
        // Spot Award specific award information with UPPERCASE
        $replacements['{$SPOTAWARD->student_name}'] = $data['student_name'];
        $replacements['{$SPOTAWARD->award_category}'] = $data['award_category'];
        $replacements['{$SPOTAWARD->award_description}'] = $data['award_description'];
        $replacements['{$SPOTAWARD->module_name}'] = $data['module_name'];
        $replacements['{$SPOTAWARD->professional}'] = $data['professional'];
        $replacements['{$SPOTAWARD->total_students_count}'] = $data['total_students_count'];
        $replacements['{$SPOTAWARD->nominated_by}'] = $data['nominated_by'];
        $replacements['{$SPOTAWARD->issued_date}'] = $data['issued_date'];

        // Custom signature field replacements
        $sigfont = strtolower(constants::signature_font());
        $pm_sig_html = '<span style="font-family: \'' . $sigfont . '\';">' . s($data['program_manager_signature']) . '</span>';
        $nominator_sig_html = '<span style="font-family: \'' . $sigfont . '\';">' . s($data['nominator_signature']) . '</span>';
        $ssteam_sig_html = '<span style="font-family: \'' . $sigfont . '\';">' . s($data['ss_team_signature']) . '</span>';

        $replacements['{$SPOTAWARD->program_manager_signature}'] = $pm_sig_html;
        $replacements['{$spotaward->program_manager_signature}'] = $pm_sig_html;
        $replacements['{program_manager_signature}'] = $pm_sig_html;

        $replacements['{$SPOTAWARD->nominator_signature}'] = $nominator_sig_html;
        $replacements['{$spotaward->nominator_signature}'] = $nominator_sig_html;
        $replacements['{nominator_signature}'] = $nominator_sig_html;

        $replacements['{$SPOTAWARD->ss_team_signature}'] = $ssteam_sig_html;
        $replacements['{$spotaward->ss_team_signature}'] = $ssteam_sig_html;
        $replacements['{ss_team_signature}'] = $ssteam_sig_html;
        
        return $replacements;
    }
}
