<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/forms/nomination_form.php');

use local_spotaward\forms\nomination_form;
use local_spotaward\local\api;

function local_spotaward_build_awardpayload_from_request(): string {
    $fieldmap = json_decode((string)optional_param('awardfieldmap', '', PARAM_RAW_TRIMMED), true);
    if (!is_array($fieldmap)) {
        return json_encode([]);
    }

    $payload = [];
    foreach ($fieldmap as $fieldname => $category) {
        $studentids = optional_param_array($fieldname, [], PARAM_INT);
        $studentids = array_values(array_unique(array_filter(array_map('intval', $studentids))));
        if (!empty($studentids)) {
            $payload[(string)$category] = $studentids;
        }
    }

    return json_encode($payload);
}

/**
 * Validate nomination form data for preview/submit actions.
 *
 * @param stdClass $data
 * @return array
 */
function local_spotaward_validate_nomination_request(stdClass $data): array {
    $errors = [];

    if (empty($data->courseid) || empty($data->professional) || empty($data->programmanagerid) ||
            empty($data->maacexecutiveid)) {
        if (empty($data->courseid)) {
            $errors['courseid'] = get_string('coursefieldrequired', 'local_spotaward');
        }
        if (empty($data->professional)) {
            $errors['professional'] = get_string('professionalrequired', 'local_spotaward');
        }
        if (empty($data->programmanagerid)) {
            $errors['programmanagerid'] = get_string('programmanagerrequired', 'local_spotaward');
        }
        if (empty($data->maacexecutiveid)) {
            $errors['maacexecutiveid'] = get_string('maacexecutiverequired', 'local_spotaward');
        }
    }

    $awardallocations = json_decode((string)($data->awardpayload ?? ''), true);
    if (!is_array($awardallocations) || empty($awardallocations)) {
        $errors['awardcategoriesui'] = get_string('awardcategoryrequired', 'local_spotaward');
    }

    return $errors;
}


require_login();

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/spotaward/index.php');
$PAGE->set_title(get_string('pluginname', 'local_spotaward'));
$PAGE->set_heading(get_string('pluginname', 'local_spotaward'));
local_spotaward_require_stylesheet();
local_spotaward_require_action_success_overlay();

if (!api::user_can_access($USER->id)) {
    throw new required_capability_exception($systemcontext, 'local/spotaward:nominate', 'nopermissions', '');
}

$view = optional_param('view', '', PARAM_ALPHANUMEXT);
$section = optional_param('section', 'form', PARAM_ALPHA);

$isnominator = api::is_nominator($USER->id);
$ispm = api::is_program_manager($USER->id);
$isssteam = api::is_ss_team($USER->id);
$ismanager = api::is_manager($USER->id);

if ($view === '') {
    if ($isnominator) {
        $view = 'nominator';
    } else if ($ispm) {
        $view = 'programmanager';
    } else if ($isssteam) {
        $view = 'ssteam';
    } else {
        $view = 'manager';
    }
}

if ($ismanager && optional_param('delete', 0, PARAM_INT)) {
    $deleteid = optional_param('delete', 0, PARAM_INT);
    require_sesskey();
    api::delete_nomination($deleteid, $USER->id);
    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/index.php', ['view' => 'manager']),
        get_string('nominationdeleted', 'local_spotaward')
    );
}

if ((is_siteadmin() || $ismanager || $isssteam) && optional_param('downloadcert', 0, PARAM_INT) && confirm_sesskey()) {
    $downloadnomid = optional_param('downloadcert', 0, PARAM_INT);
    $downloaduserid = optional_param('userid', 0, PARAM_INT);
    $downloaditemid = optional_param('itemid', 0, PARAM_INT);

    $nomination = api::get_nomination($downloadnomid);
    api::require_nomination_access($nomination, $USER->id);

    if (!in_array($nomination->status, ['ssteamprogress', 'closed'], true) || !$downloaduserid) {
        throw new moodle_exception('invalidparameter');
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    api::download_certificate($downloadnomid, $downloaduserid, $downloaditemid);
    exit;
}

if (($ismanager || $isssteam) && optional_param('downloadallcert', 0, PARAM_INT) && confirm_sesskey()) {
    $downloadnomid = optional_param('downloadallcert', 0, PARAM_INT);

    $nomination = api::get_nomination($downloadnomid);
    api::require_nomination_access($nomination, $USER->id);

    if (!in_array($nomination->status, ['ssteamprogress', 'closed'], true)) {
        throw new moodle_exception('invalidparameter');
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    api::download_all_certificates($downloadnomid);
    exit;
}

if (($ismanager || $isssteam) && optional_param('sharecertificates', 0, PARAM_INT) && require_sesskey()) {
    $sharenomid = optional_param('sharecertificates', 0, PARAM_INT);

    $nomination = api::get_nomination($sharenomid);
    api::require_nomination_access($nomination, $USER->id);

    if (!in_array($nomination->status, ['ssteamprogress', 'closed'], true)) {
        throw new moodle_exception('invalidparameter');
    }

    $sentcount = api::share_certificates_to_students($sharenomid);
    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/submission.php', ['id' => $sharenomid]),
        get_string('certificatessharedsuccess', 'local_spotaward', $sentcount)
    );
}

$output = $PAGE->get_renderer('local_spotaward');
$selectedcourseid = optional_param('courseid', 0, PARAM_INT);
$nominationformerror = null;
$nominationfielderrors = [];

$mform = null;
if ($isnominator) {
    $draftentries = api::get_draft_entries();
    $hasdraftentries = !empty($draftentries);
    $draftcontext = [];
    if ($hasdraftentries) {
        $firstdraftentry = reset($draftentries);
        $awardallocations = [];
        foreach ($draftentries as $draftentry) {
            $awardallocations[$draftentry['awardcategory']] = array_map('intval', $draftentry['studentids'] ?? []);
        }
        $draftcontext = [
            'courseid' => (int)($firstdraftentry['courseid'] ?? 0),
            'modulename' => (string)($firstdraftentry['modulename'] ?? ''),
            'professional' => (string)($firstdraftentry['professional'] ?? ''),
            'programmanagerid' => (int)($firstdraftentry['programmanagerid'] ?? 0),
            'maacexecutiveid' => (int)($firstdraftentry['maacexecutiveid'] ?? 0),
            'awardallocations' => $awardallocations,
        ];
        $selectedcourseid = $draftcontext['courseid'];
    }

    $isformpost = optional_param('submitnominations', '', PARAM_RAW) !== ''
        || optional_param('previewdraft', '', PARAM_RAW) !== ''
        || optional_param('cleardraft', '', PARAM_RAW) !== '';

    if ($section === 'form' || $isformpost) {
        $allcourseoptions = api::get_nominator_courses($USER->id);
        $selectedprogrammanagerid = (int)($draftcontext['programmanagerid'] ?? 0);
        $selectedmaacexecutiveid = (int)($draftcontext['maacexecutiveid'] ?? 0);

        $mform = new nomination_form(null, [
            'courseoptions' => $allcourseoptions,
            'selectedprogrammanagerid' => $selectedprogrammanagerid,
            'selectedmaacexecutiveid' => $selectedmaacexecutiveid,
            'selectedcourseid' => $selectedcourseid,
            'hasdraftentries' => $hasdraftentries,
            'draftcontext' => $draftcontext,
            'fielderrors' => $nominationfielderrors,
        ]);

        if (optional_param('submitnominations', '', PARAM_RAW) !== '') {
            require_sesskey();
            $currentdata = (object)[
                'courseid' => optional_param('courseid', 0, PARAM_INT) ?: optional_param('coursepicker', 0, PARAM_INT),
                'modulename' => optional_param('modulename', '', PARAM_TEXT),
                'awardpayload' => local_spotaward_build_awardpayload_from_request(),
                'professional' => optional_param('professional', '', PARAM_TEXT),
                'programmanagerid' => optional_param('programmanagerid', 0, PARAM_INT),
                'maacexecutiveid' => optional_param('maacexecutiveid', 0, PARAM_INT),
            ];
            $nominationfielderrors = local_spotaward_validate_nomination_request($currentdata);
            if (empty($nominationfielderrors)) {
                api::replace_draft_entries($currentdata, $USER->id);
                api::submit_draft_entries($USER->id);
                local_spotaward_success_redirect(
                    new moodle_url('/local/spotaward/index.php', ['view' => 'nominator']),
                    get_string('submissioncreated', 'local_spotaward')
                );
            }
            $nominationformerror = reset($nominationfielderrors);
            $mform->set_data($currentdata);
        }

        if (optional_param('cleardraft', '', PARAM_RAW) !== '') {
            require_sesskey();
            api::clear_draft_entries();
            local_spotaward_success_redirect(
                new moodle_url('/local/spotaward/index.php', ['view' => 'nominator']),
                get_string('draftentriescleared', 'local_spotaward')
            );
        }

        if (optional_param('previewdraft', '', PARAM_RAW) !== '') {
            require_sesskey();
            $previewdata = (object)[
                'courseid' => optional_param('courseid', 0, PARAM_INT) ?: optional_param('coursepicker', 0, PARAM_INT),
                'modulename' => optional_param('modulename', '', PARAM_TEXT),
                'awardpayload' => local_spotaward_build_awardpayload_from_request(),
                'professional' => optional_param('professional', '', PARAM_TEXT),
                'programmanagerid' => optional_param('programmanagerid', 0, PARAM_INT),
                'maacexecutiveid' => optional_param('maacexecutiveid', 0, PARAM_INT),
            ];
            $nominationfielderrors = local_spotaward_validate_nomination_request($previewdata);
            if (empty($nominationfielderrors)) {
                api::replace_draft_entries($previewdata, $USER->id);
                local_spotaward_success_redirect(
                    new moodle_url('/local/spotaward/index.php', ['view' => 'nominator']),
                    get_string('draftpreviewupdated', 'local_spotaward')
                );
            }
            $nominationformerror = reset($nominationfielderrors);
            $mform->set_data($previewdata);
        } else if ($mform->is_cancelled()) {
            redirect(new moodle_url('/local/spotaward/index.php', ['view' => 'nominator']));
        }

        if (!empty($nominationfielderrors)) {
            $mform = new nomination_form(null, [
                'courseoptions' => $allcourseoptions,
                'selectedprogrammanagerid' => $selectedprogrammanagerid,
                'selectedmaacexecutiveid' => $selectedmaacexecutiveid,
                'selectedcourseid' => $selectedcourseid,
                'hasdraftentries' => $hasdraftentries,
                'draftcontext' => $draftcontext,
                'fielderrors' => $nominationfielderrors,
            ]);
            if (!empty($previewdata ?? null)) {
                $mform->set_data($previewdata);
            } else if (!empty($currentdata ?? null)) {
                $mform->set_data($currentdata);
            }
        }
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('local-spotaward-app');

$tabs = [];
if ($isnominator) {
    $tabs['nominator'] = get_string('nominationform', 'local_spotaward');
}
if ($ispm) {
    $tabs['programmanager'] = get_string('programmanagerdashboard', 'local_spotaward');
}
if ($isssteam) {
    $tabs['ssteam'] = get_string('ssteamdashboard', 'local_spotaward');
}
if ($ismanager) {
    $tabs['manager'] = get_string('managerdashboard', 'local_spotaward');
}

if (count($tabs) > 1) {
    echo html_writer::start_div('spotaward-tabs mb-4');
    foreach ($tabs as $tabkey => $tablabel) {
        $classes = 'spotaward-tab' . ($view === $tabkey ? ' is-active' : '');
        echo html_writer::link(new moodle_url('/local/spotaward/index.php', ['view' => $tabkey]), $tablabel, ['class' => $classes]);
    }
    echo html_writer::end_div();
}



if ($view === 'nominator' && $isnominator) {
    if (!in_array($section, ['form', 'history'], true)) {
        $section = 'form';
    }

    echo html_writer::start_div('spotaward-shell');
    echo html_writer::tag('h3', get_string('nominationform', 'local_spotaward'), ['class' => 'spotaward-section-title']);
    echo html_writer::start_div('spotaward-subtabs mb-4');
    echo html_writer::link(
        new moodle_url('/local/spotaward/index.php', ['view' => 'nominator', 'section' => 'form']),
        get_string('formtab', 'local_spotaward'),
        ['class' => 'spotaward-subtab' . ($section === 'form' ? ' is-active' : '')]
    );
    echo html_writer::link(
        new moodle_url('/local/spotaward/index.php', ['view' => 'nominator', 'section' => 'history']),
        get_string('historytab', 'local_spotaward'),
        ['class' => 'spotaward-subtab' . ($section === 'history' ? ' is-active' : '')]
    );
    echo html_writer::end_div();

    if ($section === 'form') {
        $PAGE->requires->js_init_code(local_spotaward_nomination_form_js(new moodle_url('/local/spotaward/ajax.php')));
        $PAGE->requires->js_call_amd('local_spotaward/nomination', 'init');
    }

    if ($section === 'form') {
        echo html_writer::start_div('spotaward-card');
        echo html_writer::start_div('spotaward-card-body');
        if (!empty($nominationformerror)) {
            echo $OUTPUT->notification($nominationformerror, 'notifyproblem');
        }
        $mform->display();
        echo html_writer::end_div();
        echo html_writer::end_div();

        if (!empty(api::get_draft_entries())) {
            echo $output->draft_preview(api::get_draft_preview_rows($USER->id));
        }
    } else {
        $historyrows = [];
        foreach (api::get_nominator_submissions($USER->id) as $submission) {
            $historyrows[] = [
                'id' => $submission->id,
                'submitteddate' => userdate($submission->timecreated),
                'submittedtimestamp' => (int)$submission->timecreated,
                'coursename' => format_string($submission->coursename),
                'module' => s($submission->modulename),
                'statuslabel' => local_spotaward_render_badge(get_string($submission->status, 'local_spotaward')),
                'detailsurl' => (new moodle_url('/local/spotaward/submission.php', ['id' => $submission->id]))->out(false),
            ];
        }
        echo $output->submission_history($historyrows);
    }
    echo html_writer::end_div();
}

if ($view === 'programmanager' && $ispm) {
    if (!in_array($section, ['active', 'closed'], true)) {
        $section = 'active';
    }

    echo html_writer::start_div('spotaward-shell');
    echo html_writer::tag('h3', get_string('programmanagerdashboard', 'local_spotaward'), ['class' => 'spotaward-section-title']);
    echo $output->manager_summary(api::get_nomination_counts(0, $USER->id), true);
    echo html_writer::start_div('spotaward-subtabs mb-4');
    echo html_writer::link(
        new moodle_url('/local/spotaward/index.php', ['view' => 'programmanager', 'section' => 'active']),
        get_string('activetab', 'local_spotaward'),
        ['class' => 'spotaward-subtab' . ($section === 'active' ? ' is-active' : '')]
    );
    echo html_writer::link(
        new moodle_url('/local/spotaward/index.php', ['view' => 'programmanager', 'section' => 'closed']),
        get_string('closedtab', 'local_spotaward'),
        ['class' => 'spotaward-subtab' . ($section === 'closed' ? ' is-active' : '')]
    );
    echo html_writer::end_div();

    $columns = [
        ['key' => 'mentor', 'label' => get_string('mentor', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'maacexecutive', 'label' => get_string('maacexecutive', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'course', 'label' => get_string('course', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'module', 'label' => get_string('module', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'professional', 'label' => get_string('professional', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'submitteddate', 'label' => get_string('submitteddate', 'local_spotaward'), 'type' => 'date', 'filter' => 'date'],
        [
            'key' => 'status',
            'label' => get_string('status', 'local_spotaward'),
            'type' => 'text',
            'filter' => 'select',
        ],
        ['key' => 'actions', 'label' => get_string('actions', 'local_spotaward'), 'type' => 'text', 'filter' => 'none', 'sortable' => false, 'searchable' => false],
    ];

    $rows = [];
    foreach (api::get_program_manager_submissions($USER->id, $section) as $submission) {
        $mentorname = fullname((object)['firstname' => $submission->firstname, 'lastname' => $submission->lastname]);
        $maacname = fullname((object)['firstname' => $submission->maacfirstname, 'lastname' => $submission->maaclastname]);
        $coursetitle = format_string($submission->coursename);
        $modulename = (string)$submission->modulename;
        $professional = (string)($submission->professional ?? '');
        $timestamp = (int)$submission->timecreated;
        $rows[] = [
            'mentor' => local_spotaward_table_cell(s($mentorname), ['text' => $mentorname]),
            'maacexecutive' => local_spotaward_table_cell(s($maacname), ['text' => $maacname]),
            'course' => local_spotaward_table_cell($coursetitle, ['text' => strip_tags($coursetitle)]),
            'module' => local_spotaward_table_cell(s($modulename), ['text' => $modulename]),
            'professional' => local_spotaward_table_cell(s($professional), ['text' => $professional]),
            'submitteddate' => local_spotaward_table_cell(userdate($timestamp), [
                'text' => userdate($timestamp),
                'sort' => $timestamp,
                'date' => userdate($timestamp, '%Y-%m-%d'),
            ]),
            'status' => local_spotaward_table_cell(local_spotaward_render_badge(get_string($submission->status, 'local_spotaward')), ['text' => get_string($submission->status, 'local_spotaward')]),
            'actions' => local_spotaward_table_cell(html_writer::link(
                new moodle_url('/local/spotaward/submission.php', ['id' => $submission->id]),
                get_string('reviewsubmission', 'local_spotaward')
            ), ['text' => get_string('reviewsubmission', 'local_spotaward'), 'search' => '']),
        ];
    }

    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-body');
    echo local_spotaward_render_data_table($columns, $rows, [
        'id' => 'spotaward-programmanager-dashboard',
        'label' => get_string('programmanagerdashboard', 'local_spotaward'),
        'nosearch' => true,
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($view === 'ssteam' && $isssteam) {
    if (!in_array($section, ['active', 'closed'], true)) {
        $section = 'active';
    }

    $dashboard = api::get_ss_team_dashboard_data($section);

    echo html_writer::start_div('spotaward-shell');
    echo html_writer::tag('h3', get_string('ssteamdashboard', 'local_spotaward'), ['class' => 'spotaward-section-title']);
    echo html_writer::start_div('spotaward-subtabs mb-4');
    echo html_writer::link(
        new moodle_url('/local/spotaward/index.php', ['view' => 'ssteam', 'section' => 'active']),
        get_string('activetab', 'local_spotaward'),
        ['class' => 'spotaward-subtab' . ($section === 'active' ? ' is-active' : '')]
    );
    echo html_writer::link(
        new moodle_url('/local/spotaward/index.php', ['view' => 'ssteam', 'section' => 'closed']),
        get_string('closedtab', 'local_spotaward'),
        ['class' => 'spotaward-subtab' . ($section === 'closed' ? ' is-active' : '')]
    );
    echo html_writer::end_div();
    echo $output->ss_team_summary($dashboard['counts']);

    $columns = [
        ['key' => 'submissionid', 'label' => get_string('submissionid', 'local_spotaward'), 'type' => 'number', 'filter' => 'none'],
        ['key' => 'submitteddate', 'label' => get_string('submitteddate', 'local_spotaward'), 'type' => 'date', 'filter' => 'date'],
        ['key' => 'mentor', 'label' => get_string('mentor', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'programmanager', 'label' => get_string('programmanager', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'course', 'label' => get_string('course', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'module', 'label' => get_string('module', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'professional', 'label' => get_string('professional', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'studentcount', 'label' => get_string('studentcount', 'local_spotaward'), 'type' => 'number', 'filter' => 'none'],
        ['key' => 'status', 'label' => get_string('status', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
        ['key' => 'actions', 'label' => get_string('actions', 'local_spotaward'), 'type' => 'text', 'filter' => 'none', 'sortable' => false, 'searchable' => false],
    ];

    $rows = [];
    foreach ($dashboard['records'] as $record) {
        $actions = [
            html_writer::link(new moodle_url('/local/spotaward/submission.php', ['id' => $record->id]), get_string('viewdetails', 'local_spotaward')),
        ];
        $mentorname = fullname((object)['firstname' => $record->mentorfirstname, 'lastname' => $record->mentorlastname]);
        $pmname = fullname((object)['firstname' => $record->pmfirstname, 'lastname' => $record->pmlastname]);
        $coursename = format_string($record->coursename);
        $modulename = (string)$record->modulename;
        $professional = (string)($record->professional ?? '');
        $timestamp = (int)$record->timecreated;
        $rows[] = [
            'submissionid' => local_spotaward_table_cell(s((string)$record->id), ['text' => (string)$record->id, 'sort' => (int)$record->id]),
            'submitteddate' => local_spotaward_table_cell(userdate($timestamp), [
                'text' => userdate($timestamp),
                'sort' => $timestamp,
                'date' => userdate($timestamp, '%Y-%m-%d'),
            ]),
            'mentor' => local_spotaward_table_cell(s($mentorname), ['text' => $mentorname]),
            'programmanager' => local_spotaward_table_cell(s($pmname), ['text' => $pmname]),
            'course' => local_spotaward_table_cell($coursename, ['text' => strip_tags($coursename)]),
            'module' => local_spotaward_table_cell(s($modulename), ['text' => $modulename]),
            'professional' => local_spotaward_table_cell(s($professional), ['text' => $professional]),
            'studentcount' => local_spotaward_table_cell(s((string)$record->studentcount), ['text' => (string)$record->studentcount, 'sort' => (int)$record->studentcount]),
            'status' => local_spotaward_table_cell(local_spotaward_render_badge(get_string($record->status, 'local_spotaward')), ['text' => get_string($record->status, 'local_spotaward')]),
            'actions' => local_spotaward_table_cell(implode(' | ', $actions), ['text' => get_string('viewdetails', 'local_spotaward'), 'search' => '']),
        ];
    }

    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-body');
    echo local_spotaward_render_data_table($columns, $rows, [
        'id' => 'spotaward-ssteam-dashboard',
        'label' => get_string('ssteamdashboard', 'local_spotaward'),
        'nosearch' => true,
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($view === 'manager' && $ismanager) {
    $mentorid = optional_param('mentorid', 0, PARAM_INT);
    $programmanagerid = optional_param('programmanagerid', 0, PARAM_INT);
    $maacexecutiveid = optional_param('maacexecutiveid', 0, PARAM_INT);
    [$mentoroptions, $pmoptions, $maacoptions] = api::get_filter_options();
    $dashboard = api::get_manager_dashboard_data($mentorid, $programmanagerid, $maacexecutiveid);

    echo html_writer::start_div('spotaward-shell');
    echo html_writer::tag('h3', get_string('managerdashboard', 'local_spotaward'), ['class' => 'spotaward-section-title']);
    echo $output->manager_summary($dashboard['counts'], true);

    $columns = [
        ['key' => 'submissionid', 'label' => get_string('submissionid', 'local_spotaward'), 'type' => 'number', 'filter' => 'none'],
        ['key' => 'submitteddate', 'label' => get_string('submitteddate', 'local_spotaward'), 'type' => 'date', 'filter' => 'date'],
        ['key' => 'mentor', 'label' => get_string('mentor', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'programmanager', 'label' => get_string('programmanager', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'maacexecutive', 'label' => get_string('maacexecutive', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'course', 'label' => get_string('course', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'module', 'label' => get_string('module', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'professional', 'label' => get_string('professional', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'studentcount', 'label' => get_string('studentcount', 'local_spotaward'), 'type' => 'number', 'filter' => 'none'],
        ['key' => 'status', 'label' => get_string('status', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'actions', 'label' => get_string('actions', 'local_spotaward'), 'type' => 'text', 'filter' => 'none', 'sortable' => false, 'searchable' => false],
    ];

    $rows = [];
    foreach ($dashboard['records'] as $record) {
        $actions = [
            html_writer::link(new moodle_url('/local/spotaward/submission.php', ['id' => $record->id]), get_string('viewdetails', 'local_spotaward')),
        ];
        if ($ismanager && api::can_delete_nomination($record, $USER->id)) {
            $actions[] = html_writer::link(
                new moodle_url('/local/spotaward/index.php', ['view' => 'manager', 'delete' => $record->id, 'sesskey' => sesskey()]),
                get_string('delete', 'local_spotaward'),
                [
                    'onclick' => 'return confirm("' . get_string('confirmdelete', 'local_spotaward') . '");',
                    'data-spotaward-success' => '1',
                ]
            );
        }

        $mentorname = fullname((object)['firstname' => $record->mentorfirstname, 'lastname' => $record->mentorlastname]);
        $pmname = fullname((object)['firstname' => $record->pmfirstname, 'lastname' => $record->pmlastname]);
        $maacname = fullname((object)['firstname' => $record->maacfirstname, 'lastname' => $record->maaclastname]);
        $coursename = format_string($record->coursename);
        $modulename = (string)$record->modulename;
        $professional = (string)($record->professional ?? '');
        $timestamp = (int)$record->timecreated;
        $rows[] = [
            'submissionid' => local_spotaward_table_cell(s((string)$record->id), ['text' => (string)$record->id, 'sort' => (int)$record->id]),
            'submitteddate' => local_spotaward_table_cell(userdate($timestamp), [
                'text' => userdate($timestamp),
                'sort' => $timestamp,
                'date' => userdate($timestamp, '%Y-%m-%d'),
            ]),
            'mentor' => local_spotaward_table_cell(s($mentorname), ['text' => $mentorname]),
            'programmanager' => local_spotaward_table_cell(s($pmname), ['text' => $pmname]),
            'maacexecutive' => local_spotaward_table_cell(s($maacname), ['text' => $maacname]),
            'course' => local_spotaward_table_cell($coursename, ['text' => strip_tags($coursename)]),
            'module' => local_spotaward_table_cell(s($modulename), ['text' => $modulename]),
            'professional' => local_spotaward_table_cell(s($professional), ['text' => $professional]),
            'studentcount' => local_spotaward_table_cell(s((string)$record->studentcount), ['text' => (string)$record->studentcount, 'sort' => (int)$record->studentcount]),
            'status' => local_spotaward_table_cell(local_spotaward_render_badge(get_string($record->status, 'local_spotaward')), ['text' => get_string($record->status, 'local_spotaward')]),
            'actions' => local_spotaward_table_cell(implode(' | ', $actions), ['text' => get_string('viewdetails', 'local_spotaward') . ' ' . get_string('delete', 'local_spotaward'), 'search' => '']),
        ];
    }

    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-header');
    echo html_writer::tag('h4', get_string('aggregatedreport', 'local_spotaward'));
    echo html_writer::end_div();
    echo html_writer::start_div('spotaward-card-body');
    echo local_spotaward_render_data_table($columns, $rows, [
        'id' => 'spotaward-manager-dashboard',
        'label' => get_string('aggregatedreport', 'local_spotaward'),
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $OUTPUT->footer();
