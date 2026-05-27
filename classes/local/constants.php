<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Spot Award constants.
 *
 * @package   local_spotaward
 */
final class constants {
    /**
     * Get nominator role ID.
     *
     * @return int
     */
    public static function nominator_roleid(): int {
        return self::get_role_id(get_config('local_spotaward', 'nominator_role') ?: 'nominators');
    }

    public static function program_manager_roleid(): int {
        return self::get_role_id(get_config('local_spotaward', 'program_manager_role') ?: 'programmanagers');
    }

    public static function ss_team_roleid(): int {
        return self::get_role_id(get_config('local_spotaward', 'ss_team_role') ?: 'ssteam');
    }

    public static function student_roleid(): int {
        return self::get_role_id(get_config('local_spotaward', 'student_role') ?: 'student');
    }

    /**
     * Get role ID by shortname.
     *
     * @param string $shortname
     * @return int
     */
    private static function get_role_id(string $shortname): int {
        global $DB;
        $role = $DB->get_record('role', ['shortname' => $shortname]);
        return $role ? (int)$role->id : 0;
    }

    /**
     * Module list.
     *
     * @return array
     */
    public static function modules(): array {
        return [
            'Linux Systems' => 'Linux Systems',
            'Advance C Programming' => 'Advance C Programming',
            'C++ Programming' => 'C++ Programming',
            'Data Structure and Algorithms (DSA)' => 'Data Structure and Algorithms (DSA)',
            'Microcontrollers' => 'Microcontrollers',
            'Linux Internals and TCP/IP Networking' => 'Linux Internals and TCP/IP Networking',
            'Embedded Linux on ARM (ELARM)' => 'Embedded Linux on ARM (ELARM)',
            'Python Programming' => 'Python Programming',
            'Arduino' => 'Arduino',
            'Internet of Things Gateway (IoT GW)' => 'Internet of Things Gateway (IoT GW)',
            'Internet of Things Cloud' => 'Internet of Things Cloud',
            'Qt Programming using C++' => 'Qt Programming using C++',
        ];
    }

    /**
     * Course-name fragment to module-code mapping.
     *
     * @return array
     */
    public static function course_module_map(): array {
        return [
            'LINUX SYSTEMS' => 'Linux Systems',
            'LS101' => 'Linux Systems',
            'ADVANCED C' => 'Advance C Programming',
            'ADVC102' => 'Advance C Programming',
            'CPP103' => 'C++ Programming',
            'DATA STRUCTURES' => 'Data Structure and Algorithms (DSA)',
            'DS104' => 'Data Structure and Algorithms (DSA)',
            'MICROCONTROLLER' => 'Microcontrollers',
            'MC105' => 'Microcontrollers',
            'LINUX INTERNALS' => 'Linux Internals and TCP/IP Networking',
            'LI106' => 'Linux Internals and TCP/IP Networking',
            'ELARM' => 'Embedded Linux on ARM (ELARM)',
            'ECEP-ELARM' => 'Embedded Linux on ARM (ELARM)',
            'PYTHON MODULE' => 'Python Programming',
            'ECIP-PYTHON' => 'Python Programming',
            'GATEWAY AND IOT PROTOCOL' => 'Internet of Things Gateway (IoT GW)',
            'ECIP-GW_IOT_PROTOCOL102' => 'Internet of Things Gateway (IoT GW)',
            'IOT CLOUD' => 'Internet of Things Cloud',
            'IOTCLOUD' => 'Internet of Things Cloud',
            'ARDUINO PROGRAMMING' => 'Arduino',
            'ECIP-ARDUINO' => 'Arduino',
            'QT' => 'Qt Programming using C++',
            'QT107' => 'Qt Programming using C++',
        ];
    }

    /**
     * Allowed course shortname prefixes for nomination course selection.
     *
     * @return array
     */
    public static function nomination_course_shortname_prefixes(): array {
        $defaultprefixes = self::default_nomination_course_shortname_prefixes();

        $configured = (string)(get_config('local_spotaward', 'nomination_course_shortnames') ?? '');
        if ($configured === '') {
            return $defaultprefixes;
        }

        $prefixes = preg_split('/\r\n|\r|\n/', $configured);
        $prefixes = array_values(array_filter(array_map(
            static function(string $prefix): string {
                return \core_text::strtoupper(trim($prefix));
            },
            $prefixes
        )));

        return !empty($prefixes) ? $prefixes : $defaultprefixes;
    }

    /**
     * Default course shortname prefixes for nomination course selection.
     *
     * @return array
     */
    public static function default_nomination_course_shortname_prefixes(): array {
        return [
            'LS101',
            'ADVC102',
            'CPP103',
            'DS104',
            'MC105',
            'LI106',
            'QT107',
            'ECEP-ELARM',
            'ECIP-PYTHON',
            'ECIP-ARDUINO',
            'ECIP-GW_IOT_PROTOCOL102',
            'IOTCLOUD',
        ];
    }

    /**
     * Whether a course shortname is allowed in the nomination course picker.
     *
     * @param string $shortname
     * @return bool
     */
    public static function is_allowed_nomination_course_shortname(string $shortname): bool {
        $shortname = \core_text::strtoupper(trim($shortname));
        if ($shortname === '') {
            return false;
        }

        foreach (self::nomination_course_shortname_prefixes() as $prefix) {
            if (\core_text::strpos($shortname, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve module code from the selected course name.
     *
     * @param string $coursename
     * @return string
     */
    public static function module_for_course_name(string $coursename): string {
        $coursename = \core_text::strtoupper(trim($coursename));
        if ($coursename === '') {
            return '';
        }

        foreach (self::course_module_map() as $fragment => $modulecode) {
            if (\core_text::strpos($coursename, $fragment) !== false) {
                return $modulecode;
            }
        }

        return '';
    }

    /**
     * Resolve module code from a course shortname/fullname pair.
     *
     * @param string $shortname
     * @param string $fullname
     * @return string
     */
    public static function module_for_course(string $shortname, string $fullname): string {
        $module = self::module_for_course_name($shortname);
        if ($module !== '') {
            return $module;
        }

        return self::module_for_course_name($fullname);
    }

    /**
     * Award categories.
     *
     * @return array
     */
    public static function award_categories(): array {
        return [
            'Perfect Attendance' => 'Perfect Attendance',
            'Most Regular Student' => 'Most Regular Student',
            'Top Performer - Module Test' => 'Top Performer - Module Test',
            'Enthusiastic Learner - End of the Module' => 'Enthusiastic Learner - End of the Module',
            'Enthusiastic Learner - Mid C' => 'Enthusiastic Learner - Mid C',
            'Best Problem Solver - Mid C' => 'Best Problem Solver - Mid C',
            'Project Enthusiast' => 'Project Enthusiast',
            'The \'10\'-er' => 'The \'10\'-er',
            'Fast Coder - Mid C' => 'Fast Coder - Mid C',
            'Logical Thinker - Mid C' => 'Logical Thinker - Mid C',
            'Perfect coder - Mid C' => 'Perfect coder - Mid C',
            'Quiz Champion - Mid C' => 'Quiz Champion - Mid C',
            'Best Problem Solver - End of the Module' => 'Best Problem Solver - End of the Module',
            'Logical Thinker - End of the Module' => 'Logical Thinker - End of the Module',
            'Perfect coder - End of the Module' => 'Perfect coder - End of the Module',
            'Quiz Champion - End of the Module' => 'Quiz Champion - End of the Module',
        ];
    }

    /**
     * Award categories for courses that include Mid C awards.
     *
     * @return array
     */
    public static function advanced_c_award_categories(): array {
        return self::award_categories();
    }

    /**
     * Award categories for all other modules.
     *
     * @return array
     */
    public static function standard_award_categories(): array {
        $allowed = [
            'Perfect Attendance',
            'Most Regular Student',
            'Top Performer - Module Test',
            'Enthusiastic Learner - End of the Module',
            'Project Enthusiast',
            'The \'10\'-er',
            'Best Problem Solver - End of the Module',
            'Logical Thinker - End of the Module',
            'Perfect coder - End of the Module',
            'Quiz Champion - End of the Module',
        ];

        return array_intersect_key(self::award_categories(), array_flip($allowed));
    }

    /**
     * Resolve award categories from the selected course name.
     *
     * @param string $coursename
     * @return array
     */
    public static function award_categories_for_course_name(string $coursename): array {
        $coursename = \core_text::strtoupper(trim($coursename));
        if ($coursename !== '' && \core_text::strpos($coursename, 'ADVANCED C') !== false) {
            return self::advanced_c_award_categories();
        }

        return self::standard_award_categories();
    }

    /**
     * Resolve award categories from course shortname/fullname.
     *
     * @param string $shortname
     * @param string $fullname
     * @return array
     */
    public static function award_categories_for_course(string $shortname, string $fullname): array {
        $shortname = \core_text::strtoupper(trim($shortname));
        if ($shortname !== '' && \core_text::strpos($shortname, 'ADVC102') === 0) {
            return self::advanced_c_award_categories();
        }

        return self::award_categories_for_course_name($fullname);
    }

    /**
     * Award description templates keyed by base category.
     *
     * @return array
     */
    public static function award_description_templates(): array {
        return [
            'Perfect Attendance' => 'Your commendable achievement for maintaining 100% attendance in <Module-Name>',
            'Most Regular Student' => 'Your commendable achievement for maintaining 95% attendance in <Module-Name>',
            'Top Performer - Module Test' => 'Your top notch performance in Module Test of <Module-Name>',
            'Enthusiastic Learner' => 'Showing a lot of enthusiasm and inclination towards learning <Module-Name>. This goes a long way in building a solid career in core industry.',
            'Best Problem Solver' => 'Demonstrating problem solving capabilities in <Module-Name>. This goes a long way in building a solid career in core industry.',
            'Project Enthusiast' => 'Going extra mile by completing more projects in <Module-Name>. Projects are demonstration of your capability. This will speak in your placements.',
            'The \'10\'-er' => 'Your dedication and hardwork in completing 10 projects as a part of PowerTrack. Projects are foundations, will definitely have a positive impact on your placements. Keep it up!',
            'Fast Coder' => 'Completing a coding exercise or challenge the quickest.',
            'Logical Thinker' => 'Providing a particularly clever or efficient solution to a problem discussed in class.',
            'Perfect coder' => 'Writing code that follows all guidelines, including proper naming conventions, documentation, and overall neatness.',
            'Quiz Champion' => 'Answering the most quiz questions correctly or tackling the toughest questions with ease.',
        ];
    }

    /**
     * Convert category variant into its base category.
     *
     * @param string $awardcategory
     * @return string
     */
    public static function base_award_category(string $awardcategory): string {
        $awardcategory = trim($awardcategory);
        $suffixes = [
            ' - End of the Module',
            ' - Mid C',
        ];

        foreach ($suffixes as $suffix) {
            if (substr($awardcategory, -strlen($suffix)) === $suffix) {
                return substr($awardcategory, 0, -strlen($suffix));
            }
        }

        return $awardcategory;
    }

    /**
     * Get generated award description for a category and module.
     *
     * @param string $awardcategory
     * @param string $modulename
     * @return string
     */
    public static function generated_award_description(string $awardcategory, string $modulename): string {
        $basecategory = self::base_award_category($awardcategory);
        $templates = self::award_description_templates();
        if (!array_key_exists($basecategory, $templates)) {
            return '';
        }

        $modulelabel = trim($modulename);
        if ($modulelabel !== '') {
            $modulelabel .= ' Module';
        }

        return str_replace('<Module-Name>', $modulelabel, $templates[$basecategory]);
    }

    /**
     * Professional options.
     *
     * @return array
     */
    public static function professionals(): array {
        return [
            'Embedded Professional' => 'Embedded Professional',
            'IoT Professional' => 'IoT Professional',
        ];
    }
}
