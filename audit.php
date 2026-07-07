<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_spotaward\local\api;

require_login();
require_capability('moodle/site:config', context_system::instance());

/**
 * Render a human-readable audit status label with fallback for legacy rows.
 *
 * @param string $status
 * @return string
 */
function local_spotaward_audit_status_label(string $status): string {
    $status = trim($status);
    if ($status === '') {
        return '-';
    }

    $stringmanager = get_string_manager();
    if ($stringmanager->string_exists($status, 'local_spotaward')) {
        return get_string($status, 'local_spotaward');
    }

    return ucfirst(str_replace('_', ' ', $status));
}

$systemcontext = context_system::instance();
$page = max(0, optional_param('page', 0, PARAM_INT));
$actorid = optional_param('actorid', 0, PARAM_INT);
$nominationid = optional_param('nominationid', 0, PARAM_INT);
$fromstatus = optional_param('fromstatus', '', PARAM_ALPHA);
$tostatus = optional_param('tostatus', '', PARAM_ALPHA);
$datefrom = optional_param('datefrom', '', PARAM_RAW_TRIMMED);
$dateto = optional_param('dateto', '', PARAM_RAW_TRIMMED);
$auditaction = optional_param('auditaction', '', PARAM_ALPHA);
$selectedauditids = optional_param_array('selectedauditids', [], PARAM_INT);
$perpage = 50;

$filters = [
    'actorid' => $actorid,
    'nominationid' => $nominationid,
    'fromstatus' => $fromstatus,
    'tostatus' => $tostatus,
    'datefrom' => $datefrom,
    'dateto' => $dateto,
];

$baseurl = new moodle_url('/local/spotaward/audit.php', array_filter([
    'actorid' => $actorid ?: null,
    'nominationid' => $nominationid ?: null,
    'fromstatus' => $fromstatus !== '' ? $fromstatus : null,
    'tostatus' => $tostatus !== '' ? $tostatus : null,
    'datefrom' => $datefrom !== '' ? $datefrom : null,
    'dateto' => $dateto !== '' ? $dateto : null,
]));

if ($auditaction !== '') {
    require_sesskey();

    if ($auditaction === 'deleteselected') {
        $deletedcount = api::delete_audit_log_records($selectedauditids);
        if ($deletedcount > 0) {
            redirect($baseurl, get_string('auditlogdeletedselected', 'local_spotaward', $deletedcount), 0,
                \core\output\notification::NOTIFY_SUCCESS);
        }

        redirect($baseurl, get_string('auditlognoselected', 'local_spotaward'), 0,
            \core\output\notification::NOTIFY_WARNING);
    }

    if ($auditaction === 'deleteall') {
        $deletedcount = api::delete_all_audit_log_records();
        redirect($baseurl, get_string('auditlogdeletedall', 'local_spotaward', $deletedcount), 0,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/spotaward/audit.php', $baseurl->params());
$PAGE->set_title(get_string('auditlog', 'local_spotaward'));
$PAGE->set_heading(get_string('auditlog', 'local_spotaward'));
local_spotaward_require_stylesheet();

$actoroptions = api::get_audit_log_actor_options();
$statusoptions = api::get_audit_log_status_options();
$totalauditrows = api::count_audit_log_records($filters);
$records = api::get_audit_log($filters, $page, $perpage);

$columns = [
    ['key' => 'selectrow', 'label' => html_writer::checkbox('selectallaudit', 1, false, '', [
        'id' => 'id_selectallaudit',
        'title' => get_string('selectall'),
    ]), 'labelhtml' => true, 'type' => 'text', 'filter' => 'none', 'sortable' => false, 'searchable' => false],
    ['key' => 'timecreated', 'label' => get_string('date'), 'type' => 'date', 'filter' => 'none'],
    ['key' => 'actor', 'label' => get_string('actor', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'nomination', 'label' => get_string('nomination', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'student', 'label' => get_string('student', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'transition', 'label' => get_string('statuschange', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'reason', 'label' => get_string('reason', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
];

$rows = [];
foreach ($records as $record) {
    $actorname = trim(fullname((object)[
        'firstname' => $record->actorfirstname ?? '',
        'lastname' => $record->actorlastname ?? '',
    ]));
    if ($actorname === '') {
        $actorname = '-';
    }

    $studentname = trim(fullname((object)[
        'firstname' => $record->studentfirstname ?? '',
        'lastname' => $record->studentlastname ?? '',
    ]));
    if ($studentname === '') {
        $studentname = '-';
    }

    $courselabel = trim((string)($record->courseshortname ?? ''));
    if ($courselabel === '') {
        $courselabel = trim((string)($record->coursename ?? ''));
    }
    if ($courselabel === '') {
        $courselabel = get_string('unknowncourse', 'local_spotaward');
    }

    $nominationlabel = '#' . (int)$record->nominationid . ' (' . $courselabel . ')';
    $fromlabel = local_spotaward_audit_status_label((string)$record->fromstatus);
    $tolabel = local_spotaward_audit_status_label((string)$record->tostatus);
    $transitionhtml = html_writer::div(
        local_spotaward_render_badge($fromlabel) .
        html_writer::span(' -> ', 'mx-1') .
        local_spotaward_render_badge($tolabel),
        'd-flex align-items-center flex-wrap gap-1'
    );

    $reason = trim((string)($record->reason ?? ''));
    if ($reason === '') {
        $reason = '-';
    }

    $rows[] = [
        'selectrow' => local_spotaward_table_cell(
            html_writer::checkbox('selectedauditids[]', (int)$record->id, false, '', [
                'class' => 'spotaward-audit-select',
                'data-audit-select' => '1',
            ]),
            ['text' => '', 'search' => '']
        ),
        'timecreated' => local_spotaward_table_cell(
            userdate((int)$record->timecreated),
            [
                'sort' => (int)$record->timecreated,
                'date' => userdate((int)$record->timecreated, '%Y-%m-%d %H:%M:%S'),
                'text' => userdate((int)$record->timecreated),
            ]
        ),
        'actor' => local_spotaward_table_cell(s($actorname), ['text' => $actorname]),
        'nomination' => local_spotaward_table_cell(s($nominationlabel), ['text' => $nominationlabel]),
        'student' => local_spotaward_table_cell(s($studentname), ['text' => $studentname]),
        'transition' => local_spotaward_table_cell($transitionhtml, [
            'text' => $fromlabel . ' -> ' . $tolabel,
        ]),
        'reason' => local_spotaward_table_cell(s($reason), ['text' => $reason]),
    ];
}

echo $OUTPUT->header();
echo html_writer::start_div('local-spotaward-app');
echo html_writer::start_div('spotaward-shell');

echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'local_spotaward_settings']),
        get_string('back'),
        ['class' => 'btn btn-secondary']
    ),
    'spotaward-back-link'
);

echo html_writer::tag('h3', get_string('auditlog', 'local_spotaward'), ['class' => 'spotaward-section-title']);

echo html_writer::start_div('spotaward-card');
echo html_writer::start_div('spotaward-card-header');
echo html_writer::tag('strong', get_string('auditlog', 'local_spotaward'));
echo html_writer::tag('span', get_string('auditrecordsfound', 'local_spotaward', $totalauditrows), ['class' => 'ml-2 text-muted']);
echo html_writer::end_div();
echo html_writer::start_div('spotaward-card-body');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/spotaward/audit.php'))->out(false),
    'class' => 'mb-3 spotaward-filter-form',
]);

echo html_writer::label(get_string('datefrom', 'local_spotaward'), 'id_datefrom', false);
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'datefrom',
    'id' => 'id_datefrom',
    'value' => s($datefrom),
    'class' => 'form-control d-inline-block w-auto',
]);

echo html_writer::label(get_string('dateto', 'local_spotaward'), 'id_dateto', false);
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'dateto',
    'id' => 'id_dateto',
    'value' => s($dateto),
    'class' => 'form-control d-inline-block w-auto',
]);

echo html_writer::label(get_string('actor', 'local_spotaward'), 'id_actorid', false);
echo html_writer::select($actoroptions, 'actorid', $actorid, false, [
    'id' => 'id_actorid',
    'class' => 'custom-select d-inline-block w-auto',
]);

echo html_writer::label(get_string('nominationidfilter', 'local_spotaward'), 'id_nominationid', false);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'nominationid',
    'id' => 'id_nominationid',
    'value' => $nominationid > 0 ? $nominationid : '',
    'min' => 1,
    'class' => 'form-control d-inline-block w-auto',
]);

echo html_writer::label(get_string('fromstatus', 'local_spotaward'), 'id_fromstatus', false);
echo html_writer::select($statusoptions, 'fromstatus', $fromstatus, false, [
    'id' => 'id_fromstatus',
    'class' => 'custom-select d-inline-block w-auto',
]);

echo html_writer::label(get_string('tostatus', 'local_spotaward'), 'id_tostatus', false);
echo html_writer::select($statusoptions, 'tostatus', $tostatus, false, [
    'id' => 'id_tostatus',
    'class' => 'custom-select d-inline-block w-auto',
]);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('filter'),
]);

echo html_writer::link(
    new moodle_url('/local/spotaward/audit.php'),
    get_string('resetfilters', 'local_spotaward'),
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_tag('form');

if (empty($rows)) {
    echo html_writer::div(get_string('noauditrecords', 'local_spotaward'), 'spotaward-empty');
} else {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $baseurl->out(false),
        'id' => 'spotaward-audit-delete-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::start_div('mb-3 d-flex flex-wrap align-items-center');
    echo html_writer::tag('button', get_string('deleteselected', 'local_spotaward'), [
        'type' => 'submit',
        'name' => 'auditaction',
        'value' => 'deleteselected',
        'class' => 'btn btn-danger mr-2',
        'onclick' => "return confirm('" . addslashes_js(get_string('auditlogconfirmdeleteselected', 'local_spotaward')) . "');",
    ]);
    echo html_writer::tag('button', get_string('deleteallauditlogs', 'local_spotaward'), [
        'type' => 'submit',
        'name' => 'auditaction',
        'value' => 'deleteall',
        'class' => 'btn btn-outline-danger',
        'onclick' => "return confirm('" . addslashes_js(get_string('auditlogconfirmdeleteall', 'local_spotaward')) . "');",
    ]);
    echo html_writer::end_div();

    echo local_spotaward_render_data_table($columns, $rows, [
        'id' => 'spotaward-audit-log-table',
    ]);
    echo html_writer::end_tag('form');

    echo $OUTPUT->paging_bar($totalauditrows, $page, $perpage, $baseurl);
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::script("
document.addEventListener('DOMContentLoaded', function() {
    var selectAll = document.getElementById('id_selectallaudit');
    if (!selectAll) {
        return;
    }

    selectAll.addEventListener('change', function() {
        document.querySelectorAll('[data-audit-select=\"1\"]').forEach(function(box) {
            box.checked = selectAll.checked;
        });
    });
});
");
echo $OUTPUT->footer();
