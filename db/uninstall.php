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
 * Uninstall callbacks for Spot Award System.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom uninstall cleanup callback.
 *
 * @return bool
 */
function xmldb_local_spotaward_uninstall(): bool {
    // Clean up plugin configurations.
    $settings = [
        'menu',
        'nominator_role',
        'program_manager_role',
        'admin_role',
        'ss_team_role',
        'manager_role',
        'student_role',
        'nomination_course_shortnames',
        'zohocliq_bot_url',
        'zohocliq_api_key',
        'certificate_templateid',
        'signature_font',
        'pr_templateid',
    ];

    foreach ($settings as $setting) {
        unset_config($setting, 'local_spotaward');
    }

    // Clean up template configurations.
    require_once(__DIR__ . '/../forms/email_templates_form.php');
    if (class_exists('local_spotaward\forms\email_templates_form')) {
        $fields = array_merge(
            \local_spotaward\forms\email_templates_form::fields(),
            \local_spotaward\forms\email_templates_form::cliq_fields()
        );
        foreach ($fields as $field) {
            unset_config($field['subject'], 'local_spotaward');
            unset_config($field['body'], 'local_spotaward');
        }
    }

    return true;
}
