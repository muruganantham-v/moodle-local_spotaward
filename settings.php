<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/local/pr_field_map.php');

if (!function_exists('local_spotaward_settings_role_options')) {
    /**
     * Build selectable role options keyed by shortname.
     *
     * @return array
     */
    function local_spotaward_settings_role_options(): array {
        global $DB;

        $records = $DB->get_records('role', null, 'sortorder ASC, shortname ASC', 'id, shortname, name');
        $options = [];

        foreach ($records as $record) {
            $shortname = trim((string)$record->shortname);
            if ($shortname === '') {
                continue;
            }

            $label = trim((string)$record->name);
            if ($label === '') {
                $label = $shortname;
            } else {
                $label .= ' (' . $shortname . ')';
            }
            $options[$shortname] = $label;
        }

        return $options;
    }
}


if ($hassiteconfig) {
    $settings = new admin_settingpage('local_spotaward_settings', get_string('pluginname', 'local_spotaward'));
    $ADMIN->add('localplugins', $settings);

    $setting = new admin_setting_configcheckbox(
        'local_spotaward/menu',
        get_string('menu', 'local_spotaward'),
        get_string('menu_desc', 'local_spotaward'),
        1
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    $roleoptions = local_spotaward_settings_role_options();

    $settings->add(new admin_setting_configselect(
        'local_spotaward/nominator_role',
        get_string('nominator_role_setting', 'local_spotaward'),
        get_string('nominator_role_setting_desc', 'local_spotaward'),
        'nominators',
        $roleoptions
    ));

$settings->add(new admin_setting_configselect(
    'local_spotaward/program_manager_role',
    get_string('program_manager_role_setting', 'local_spotaward'),
    get_string('program_manager_role_setting_desc', 'local_spotaward'),
    'programmanagers',
    $roleoptions
));

$settings->add(new admin_setting_configselect(
    'local_spotaward/admin_role',
    get_string('admin_role_setting', 'local_spotaward'),
    get_string('admin_role_setting_desc', 'local_spotaward'),
    'admin',
    $roleoptions
));

$settings->add(new admin_setting_configselect(
    'local_spotaward/ss_team_role',
    get_string('ss_team_role_setting', 'local_spotaward'),
    get_string('ss_team_role_setting_desc', 'local_spotaward'),
    'ssteam',
    $roleoptions
));

$settings->add(new admin_setting_configselect(
    'local_spotaward/manager_role',
    get_string('manager_role_setting', 'local_spotaward'),
    get_string('manager_role_setting_desc', 'local_spotaward'),
    'manager',
    $roleoptions
));

    $settings->add(new admin_setting_configselect(
        'local_spotaward/student_role',
        get_string('student_role_setting', 'local_spotaward'),
        get_string('student_role_setting_desc', 'local_spotaward'),
        'student',
        $roleoptions
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_spotaward/nomination_course_shortnames',
        get_string('nomination_course_shortnames_setting', 'local_spotaward'),
        get_string('nomination_course_shortnames_setting_desc', 'local_spotaward'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_heading(
        'local_spotaward/zoho_cliq_heading',
        get_string('zohocliqsettings', 'local_spotaward'),
        get_string('zohocliqsettings_desc', 'local_spotaward')
    ));

    $settings->add(new admin_setting_configtext(
        'local_spotaward/zohocliq_bot_url',
        get_string('zohocliq_bot_url', 'local_spotaward'),
        get_string('zohocliq_bot_url_desc', 'local_spotaward'),
        'https://cliq.zoho.com/api/v2/bots/batchinformer/message',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_spotaward/zohocliq_api_key',
        get_string('zohocliq_api_key', 'local_spotaward'),
        get_string('zohocliq_api_key_desc', 'local_spotaward'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_spotaward/email_template_heading',
        get_string('emailtemplatesettings', 'local_spotaward'),
        ''
    ));

    $settings->add(new admin_setting_description(
        'local_spotaward/configure_email_templates',
        '',
        html_writer::link(
            new moodle_url('/local/spotaward/email_templates.php'),
            get_string('configureemailtemplates', 'local_spotaward'),
            ['class' => 'btn btn-primary']
        )
    ));

    $settings->add(new admin_setting_description(
        'local_spotaward/view_audit_log',
        '',
        html_writer::link(
            new moodle_url('/local/spotaward/audit.php'),
            get_string('viewauditlog', 'local_spotaward'),
            ['class' => 'btn btn-secondary']
        )
    ));

    $settings->add(new admin_setting_heading(
        'local_spotaward/certificate_heading',
        get_string('certificatesettings', 'local_spotaward'),
        ''
    ));

    $options = [0 => get_string('none')];
    if (file_exists(__DIR__ . '/../../mod/certificatebeautiful/classes/models.php')) {
        require_once(__DIR__ . '/../../mod/certificatebeautiful/classes/models.php');
        $templates = \mod_certificatebeautiful\models::list_all();
        $options += $templates;
    }

    $settings->add(new admin_setting_configselect(
        'local_spotaward/certificate_templateid',
        get_string('certificate_template', 'local_spotaward'),
        get_string('certificate_template_desc', 'local_spotaward'),
        0,
        $options
    ));

    $settings->add(new admin_setting_configselect(
        'local_spotaward/pr_templateid',
        get_string('pr_template', 'local_spotaward'),
        get_string('pr_template_desc', 'local_spotaward'),
        0,
        $options
    ));

    $settings->add(new admin_setting_description(
        'local_spotaward/manage_template',
        get_string('managetemplate', 'local_spotaward'),
        html_writer::link(
            new moodle_url('/mod/certificatebeautiful/manage-model-list.php'),
            get_string('managecertificatetemplates', 'local_spotaward'),
            ['class' => 'btn btn-primary', 'target' => '_blank']
        )
    ));

    $fields = \local_spotaward\local\cert_field_map::table_structure();
    foreach (\local_spotaward\local\pr_field_map::table_structure() as $prfield) {
        $fieldkey = (string)($prfield['key'] ?? '');
        if ($fieldkey === '') {
            continue;
        }

        $alreadyexists = false;
        foreach ($fields as $field) {
            if (($field['key'] ?? '') === $fieldkey) {
                $alreadyexists = true;
                break;
            }
        }

        if (!$alreadyexists) {
            $fields[] = $prfield;
        }
    }

    $fieldshtml = '<p>' . get_string('field_mapping_help', 'local_spotaward') . '</p>';
    $fieldshtml .= '<table class="table table-striped table-sm w-auto">';
    $fieldshtml .= '<thead><tr><th>' . get_string('field_label', 'local_spotaward') . '</th><th>' . get_string('placeholder', 'local_spotaward') . '</th></tr></thead>';
    $fieldshtml .= '<tbody>';
    foreach ($fields as $field) {
        $fieldshtml .= '<tr>';
        $fieldshtml .= '<td>' . s($field['label']) . '</td>';
        $fieldshtml .= '<td><code>{' . $field['key'] . '}</code> ' . get_string('or', 'local_spotaward') . ' <code>{$SPOTAWARD->' . $field['key'] . '}</code></td>';
        $fieldshtml .= '</tr>';
    }
    $fieldshtml .= '</tbody></table>';

    $settings->add(new admin_setting_description(
        'local_spotaward/field_mapping',
        get_string('available_fields', 'local_spotaward'),
        $fieldshtml
    ));

}
