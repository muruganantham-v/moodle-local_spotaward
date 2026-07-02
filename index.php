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
 * Build current nomination form state from request values.
 *
 * @return stdClass
 */
function local_spotaward_build_nomination_form_state_from_request(): stdClass {
    return (object)[
        'courseid' => optional_param('courseid', 0, PARAM_INT) ?: optional_param('coursepicker', 0, PARAM_INT),
        'modulename' => optional_param('modulename', '', PARAM_TEXT),
        'awardpayload' => local_spotaward_build_awardpayload_from_request(),
        'professional' => optional_param('professional', '', PARAM_TEXT),
        'programmanagerid' => optional_param('programmanagerid', 0, PARAM_INT),
        'maacexecutiveid' => optional_param('maacexecutiveid', 0, PARAM_INT),
    ];
}

/**
 * Convert stored form state into the draft-context structure used by the form.
 *
 * @param array $state
 * @return array
 */
function local_spotaward_build_draft_context_from_form_state(array $state): array {
    return [
        'courseid' => (int)($state['courseid'] ?? 0),
        'modulename' => (string)($state['modulename'] ?? ''),
        'professional' => (string)($state['professional'] ?? ''),
        'programmanagerid' => (int)($state['programmanagerid'] ?? 0),
        'maacexecutiveid' => (int)($state['maacexecutiveid'] ?? 0),
        'awardallocations' => is_array($state['awardallocations'] ?? null) ? $state['awardallocations'] : [],
        'timesaved' => (int)($state['timesaved'] ?? 0),
    ];
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
$isadmin = api::is_admin($USER->id);
$ismanager = api::is_manager($USER->id);

if ($view === '') {
    if ($isnominator) {
        $view = 'nominator';
    } else if ($ispm) {
        $view = 'programmanager';
    } else if ($isssteam) {
        $view = 'ssteam';
    } else if ($isadmin) {
        $view = 'admin';
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

if ($isadmin && optional_param('downloadadmincertificates', 0, PARAM_INT) && confirm_sesskey()) {
    $selectednominationids = optional_param_array('nominationids', [], PARAM_INT);

    while (ob_get_level()) {
        ob_end_clean();
    }

    api::download_admin_certificates($selectednominationids, (int)$USER->id);
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
    $draftformstate = api::get_draft_form_state();
    $hasrecoverablestate = $hasdraftentries || !empty($draftformstate);
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
    if (!empty($draftformstate)) {
        $draftcontext = local_spotaward_build_draft_context_from_form_state($draftformstate);
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
            'hasdraftentries' => $hasrecoverablestate,
            'draftcontext' => $draftcontext,
            'draftsavedat' => (int)($draftcontext['timesaved'] ?? 0),
            'fielderrors' => $nominationfielderrors,
        ]);

        if (optional_param('submitnominations', '', PARAM_RAW) !== '') {
            require_sesskey();
            $currentdata = local_spotaward_build_nomination_form_state_from_request();
            $draftformstate = api::save_draft_form_state($currentdata, $USER->id);
            $nominationfielderrors = local_spotaward_validate_nomination_request($currentdata);
            if (empty($nominationfielderrors)) {
                api::replace_draft_entries($currentdata, $USER->id);
                api::submit_draft_entries($USER->id);
                api::clear_draft_form_state();
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
            api::clear_draft_form_state();
            local_spotaward_success_redirect(
                new moodle_url('/local/spotaward/index.php', ['view' => 'nominator']),
                get_string('draftentriescleared', 'local_spotaward')
            );
        }

        if (optional_param('previewdraft', '', PARAM_RAW) !== '') {
            require_sesskey();
            $previewdata = local_spotaward_build_nomination_form_state_from_request();
            $draftformstate = api::save_draft_form_state($previewdata, $USER->id);
            $nominationfielderrors = local_spotaward_validate_nomination_request($previewdata);
            if (empty($nominationfielderrors)) {
                api::replace_draft_entries($previewdata, $USER->id);
                api::clear_draft_form_state();
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
            if (!empty($draftformstate)) {
                $draftcontext = local_spotaward_build_draft_context_from_form_state($draftformstate);
                $selectedcourseid = (int)($draftcontext['courseid'] ?? $selectedcourseid);
            }
            $mform = new nomination_form(null, [
                'courseoptions' => $allcourseoptions,
                'selectedprogrammanagerid' => $selectedprogrammanagerid,
                'selectedmaacexecutiveid' => $selectedmaacexecutiveid,
                'selectedcourseid' => $selectedcourseid,
                'hasdraftentries' => !empty($draftcontext),
                'draftcontext' => $draftcontext,
                'draftsavedat' => (int)($draftcontext['timesaved'] ?? 0),
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
if ($isadmin) {
    $tabs['admin'] = get_string('admindashboard', 'local_spotaward');
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
        $PAGE->requires->js_call_amd('local_spotaward/nomination', 'init', [[
            'autosaveurl' => (new moodle_url('/local/spotaward/ajax.php', ['action' => 'autosavedraft']))->out(false),
            'sesskey' => sesskey(),
            'autosaveintervalms' => 60000,
            'autosavedebouncems' => 8000,
            'hasrecoverablestate' => !empty($draftcontext),
            'initialsavedat' => (int)($draftcontext['timesaved'] ?? 0),
            'strings' => [
                'saving' => get_string('draftautosaving', 'local_spotaward'),
                'savedprefix' => get_string('draftautosavedprefix', 'local_spotaward'),
                'unsaved' => get_string('draftchangespending', 'local_spotaward'),
                'failed' => get_string('draftsavefailed', 'local_spotaward'),
                'leavewarning' => get_string('draftleavewarning', 'local_spotaward'),
            ],
        ]]);
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
            'status' => (function() use ($submission) {
                $reviewed = (int)($submission->revieweditems ?? 0);
                $total    = (int)($submission->totalitems ?? 0);
                $hascerts = (int)($submission->certificatesexist ?? 0) > 0;
                if ($submission->status === 'pending' && $reviewed > 0 && $total > 0) {
                    $label = get_string('partiallyreviewed', 'local_spotaward') .
                             ' (' . $reviewed . '/' . $total . ')';
                } else if ($submission->status === 'ssteamprogress' && !$hascerts) {
                    $label = get_string('approvedawaitingss', 'local_spotaward');
                } else {
                    $label = get_string($submission->status, 'local_spotaward');
                }
                return local_spotaward_table_cell(
                    local_spotaward_render_badge($label),
                    ['text' => $label]
                );
            })(),
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
            'status' => (function() use ($record) {
                $reviewed = (int)($record->revieweditems ?? 0);
                $total    = (int)($record->totalitems ?? 0);
                $hascerts = (int)($record->certificatesexist ?? 0) > 0;
                if ($record->status === 'pending' && $reviewed > 0 && $total > 0) {
                    $label = get_string('partiallyreviewed', 'local_spotaward') . ' (' . $reviewed . '/' . $total . ')';
                } else if ($record->status === 'ssteamprogress' && !$hascerts) {
                    $label = get_string('approvedawaitingss', 'local_spotaward');
                } else {
                    $label = get_string($record->status, 'local_spotaward');
                }
                return local_spotaward_table_cell(local_spotaward_render_badge($label), ['text' => $label]);
            })(),
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

if ($view === 'admin' && $isadmin) {
    if (!in_array($section, ['active', 'closed'], true)) {
        $section = 'active';
    }

    $records = api::get_admin_dashboard_data($section);
    $formurl = new moodle_url('/local/spotaward/index.php', ['view' => 'admin', 'section' => $section]);
    $formid = 'spotaward-admin-dashboard-form';
    $buttonid = 'spotaward-admin-download-button';
    $selectallid = 'spotaward-admin-select-all';

    echo html_writer::start_div('spotaward-shell');
    echo html_writer::tag('h3', get_string('admindashboard', 'local_spotaward'), ['class' => 'spotaward-section-title']);
    echo html_writer::start_div('spotaward-subtabs mb-4');
    echo html_writer::link(
        new moodle_url('/local/spotaward/index.php', ['view' => 'admin', 'section' => 'active']),
        get_string('activetab', 'local_spotaward'),
        ['class' => 'spotaward-subtab' . ($section === 'active' ? ' is-active' : '')]
    );
    echo html_writer::link(
        new moodle_url('/local/spotaward/index.php', ['view' => 'admin', 'section' => 'closed']),
        get_string('closedtab', 'local_spotaward'),
        ['class' => 'spotaward-subtab' . ($section === 'closed' ? ' is-active' : '')]
    );
    echo html_writer::end_div();

    $columns = [
        [
            'key' => 'selectrecord',
            'label' => html_writer::checkbox('selectall', 1, false, '', [
                'id' => $selectallid,
                'data-admin-select-all' => '1',
                'aria-label' => get_string('selectallrecords', 'local_spotaward'),
            ]),
            'labelhtml' => true,
            'type' => 'text',
            'filter' => 'none',
            'sortable' => false,
            'searchable' => false,
        ],
        ['key' => 'submissiondate', 'label' => get_string('submitteddate', 'local_spotaward'), 'type' => 'date', 'filter' => 'date'],
        ['key' => 'course', 'label' => get_string('course', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'maacexecutive', 'label' => get_string('maacexecutive', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
        ['key' => 'downloadstatus', 'label' => get_string('status', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
    ];

    $rows = [];
    foreach ($records as $record) {
        $maacname = trim(fullname((object)[
            'firstname' => $record->maacfirstname ?? '',
            'lastname' => $record->maaclastname ?? '',
        ]));
        $coursename = format_string($record->coursename);
        $timestamp = (int)$record->adminsharedtime;
        $downloaded = !empty($record->admindownloadedtime);
        $downloadlabel = $downloaded
            ? get_string('admindownloaded', 'local_spotaward')
            : get_string('adminnotdownloaded', 'local_spotaward');

        $rows[] = [
            'selectrecord' => local_spotaward_table_cell(
                html_writer::checkbox('nominationids[]', (int)$record->id, false, '', [
                    'class' => 'spotaward-admin-record-checkbox',
                    'data-admin-record-checkbox' => '1',
                    'aria-label' => get_string('selectrecordfordownload', 'local_spotaward', (int)$record->id),
                ]),
                ['text' => '', 'search' => '', 'sort' => 0, 'filter' => '', 'class' => 'spotaward-table-checkbox']
            ),
            'submissiondate' => local_spotaward_table_cell(userdate($timestamp), [
                'text' => userdate($timestamp),
                'sort' => $timestamp,
                'date' => userdate($timestamp, '%Y-%m-%d'),
            ]),
            'course' => local_spotaward_table_cell($coursename, ['text' => strip_tags($coursename)]),
            'maacexecutive' => local_spotaward_table_cell(s($maacname), ['text' => $maacname]),
            'downloadstatus' => local_spotaward_table_cell(
                local_spotaward_render_badge($downloadlabel),
                ['text' => $downloadlabel]
            ),
        ];
    }

    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-body');
    if (empty($rows)) {
        echo html_writer::tag('p', get_string('noadminsharedrecords', 'local_spotaward'), ['class' => 'spotaward-empty']);
    } else {
        echo html_writer::start_tag('form', [
            'id' => $formid,
            'method' => 'post',
            'action' => $formurl->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::start_div('mb-3');
        echo html_writer::tag('button', get_string('downloadcertificate', 'local_spotaward'), [
            'type' => 'submit',
            'name' => 'downloadadmincertificates',
            'value' => '1',
            'id' => $buttonid,
            'class' => 'btn btn-primary',
            'disabled' => 'disabled',
        ]);
        echo html_writer::end_div();
        echo local_spotaward_render_data_table($columns, $rows, [
            'id' => 'spotaward-admin-dashboard',
            'label' => get_string('admindashboard', 'local_spotaward'),
            'nosearch' => true,
        ]);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    $PAGE->requires->js_init_code(
        '(function(){' .
        'var form=document.getElementById(' . json_encode($formid) . ');' .
        'if(!form){return;}' .
        'var master=form.querySelector("[data-admin-select-all=\'1\']");' .
        'var button=document.getElementById(' . json_encode($buttonid) . ');' .
        'function getBoxes(){return Array.prototype.slice.call(form.querySelectorAll("input[data-admin-record-checkbox=\'1\']"));}' .
        'function sync(){var boxes=getBoxes();var checked=boxes.filter(function(box){return box.checked;});if(button){button.disabled=checked.length===0;}if(master){master.checked=boxes.length>0&&checked.length===boxes.length;master.indeterminate=checked.length>0&&checked.length<boxes.length;}}' .
        'if(master){master.addEventListener("change",function(){getBoxes().forEach(function(box){box.checked=master.checked;});sync();});}' .
        'form.addEventListener("change",function(e){if(e.target&&e.target.matches("input[data-admin-record-checkbox=\'1\']")){sync();}});' .
        'form.addEventListener("submit",function(e){if(getBoxes().every(function(box){return !box.checked;})){e.preventDefault();window.alert(' . json_encode(get_string('selectrecordsfordownload', 'local_spotaward')) . ');}});' .
        'sync();' .
        '}());'
    );
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
            'status' => (function() use ($record) {
                $reviewed = (int)($record->revieweditems ?? 0);
                $total    = (int)($record->totalitems ?? 0);
                $hascerts = (int)($record->certificatesexist ?? 0) > 0;
                if ($record->status === 'pending' && $reviewed > 0 && $total > 0) {
                    $label = get_string('partiallyreviewed', 'local_spotaward') . ' (' . $reviewed . '/' . $total . ')';
                } else if ($record->status === 'ssteamprogress' && !$hascerts) {
                    $label = get_string('approvedawaitingss', 'local_spotaward');
                } else {
                    $label = get_string($record->status, 'local_spotaward');
                }
                return local_spotaward_table_cell(local_spotaward_render_badge($label), ['text' => $label]);
            })(),
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
