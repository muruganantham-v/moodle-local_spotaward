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
 * Audit log page.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
        $deletedcount = api::delete_audit_log_records($selectedauditids, (int)$USER->id);
        if ($deletedcount > 0) {
            redirect($baseurl, get_string('auditlogdeletedselected', 'local_spotaward', $deletedcount), 0,
                \core\output\notification::NOTIFY_SUCCESS);
        }

        redirect($baseurl, get_string('auditlognoselected', 'local_spotaward'), 0,
            \core\output\notification::NOTIFY_WARNING);
    }

    if ($auditaction === 'deleteall') {
        // Bug #10 fix: A JS confirm() dialog is bypassable by a crafted POST.
        // Require the admin to explicitly type the confirmation token server-side.
        $confirmtoken = optional_param('confirmdeleteall', '', PARAM_TEXT);
        $expectedtoken = get_string('auditlogdeletealltoken', 'local_spotaward');
        if (trim($confirmtoken) !== $expectedtoken) {
            redirect($baseurl,
                get_string('auditlogdeleteallconfirm', 'local_spotaward'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        $deletedcount = api::delete_all_audit_log_records((int)$USER->id);
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
    $actorname = trim(local_spotaward_fullname($record->actorfirstname ?? '', $record->actorlastname ?? ''));
    if ($actorname === '') {
        $actorname = '-';
    }

    $studentname = trim(local_spotaward_fullname($record->studentfirstname ?? '', $record->studentlastname ?? ''));
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
        'type'    => 'button',
        'class'   => 'btn btn-outline-danger',
        'onclick' => 'localSpotawardOpenDeleteAll(); return false;',
    ]);
    echo html_writer::end_div();

    $deletealltoken = get_string('auditlogdeletealltoken', 'local_spotaward');
    $deleteallprompt = get_string('auditlogdeleteallconfirm', 'local_spotaward');

    echo html_writer::start_div('spotaward-report-backdrop', [
        'id' => 'spotaward-deleteall-modal',
        'role' => 'dialog',
        'aria-modal' => 'true',
        'aria-labelledby' => 'spotaward-deleteall-title',
    ]);
    echo html_writer::start_div('spotaward-report-modal', ['style' => 'max-width: 500px;']);

    // Header.
    echo html_writer::start_div('spotaward-report-header');
    echo html_writer::tag('h3', get_string('deleteallauditlogs', 'local_spotaward'), [
        'class' => 'spotaward-report-title text-danger',
        'id' => 'spotaward-deleteall-title',
    ]);
    echo html_writer::tag('button', '&times;', [
        'type' => 'button',
        'class' => 'spotaward-report-close',
        'onclick' => 'localSpotawardCloseDeleteAll();',
        'aria-label' => get_string('closebuttontitle'),
    ]);
    echo html_writer::end_div();

    // Body.
    echo html_writer::start_div('spotaward-report-body');
    echo html_writer::tag('p', $deleteallprompt, ['class' => 'mb-2 font-weight-bold']);
    echo html_writer::tag('p', 'To confirm, please type <strong class="badge badge-danger text-uppercase px-2 py-1" style="font-size:0.9rem;letter-spacing:1px;">' . s($deletealltoken) . '</strong> below:', ['class' => 'text-muted small mb-3']);
    echo html_writer::label($deleteallprompt, 'spotaward-deleteall-input', false, ['class' => 'sr-only']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'id' => 'spotaward-deleteall-input',
        'class' => 'form-control form-control-lg text-center font-weight-bold mb-2',
        'placeholder' => $deletealltoken,
        'autocomplete' => 'off',
        'style' => 'letter-spacing: 2px;',
    ]);
    echo html_writer::div(get_string('logsdeletenomatch', 'local_spotaward'), 'text-danger small text-center mb-3 font-weight-bold', [
        'id' => 'spotaward-deleteall-feedback',
        'style' => 'display:none;',
    ]);

    // Action buttons.
    echo html_writer::start_div('d-flex justify-content-end gap-2 mt-3');
    echo html_writer::tag('button', get_string('cancel'), [
        'type' => 'button',
        'class' => 'btn btn-secondary mr-2',
        'onclick' => 'localSpotawardCloseDeleteAll();',
    ]);
    echo html_writer::tag('button', get_string('deleteallauditlogs', 'local_spotaward'), [
        'type' => 'button',
        'id' => 'spotaward-deleteall-confirm-btn',
        'class' => 'btn btn-danger',
        'onclick' => 'localSpotawardSubmitDeleteAll();',
    ]);
    echo html_writer::end_div();

    echo html_writer::end_div(); // .spotaward-report-body
    echo html_writer::end_div(); // .spotaward-report-modal
    echo html_writer::end_div(); // .spotaward-report-backdrop

    echo html_writer::script("
function localSpotawardOpenDeleteAll() {
    var modal = document.getElementById('spotaward-deleteall-modal');
    var input = document.getElementById('spotaward-deleteall-input');
    var feedback = document.getElementById('spotaward-deleteall-feedback');
    if (!modal) return;
    if (input) {
        input.value = '';
        input.classList.remove('is-invalid');
    }
    if (feedback) {
        feedback.style.display = 'none';
    }
    modal.classList.add('is-open');
    if (input) {
        setTimeout(function() { input.focus(); }, 50);
    }
}

function localSpotawardCloseDeleteAll() {
    var modal = document.getElementById('spotaward-deleteall-modal');
    if (modal) {
        modal.classList.remove('is-open');
    }
}

function localSpotawardSubmitDeleteAll() {
    var input = document.getElementById('spotaward-deleteall-input');
    var feedback = document.getElementById('spotaward-deleteall-feedback');
    var expected = " . json_encode($deletealltoken) . ";
    if (!input || input.value.trim() !== expected) {
        if (input) {
            input.classList.add('is-invalid');
            input.focus();
        }
        if (feedback) {
            feedback.style.display = 'block';
        }
        return;
    }
    var form = document.getElementById('spotaward-audit-delete-form');
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'confirmdeleteall';
    hidden.value = input.value.trim();
    form.appendChild(hidden);
    var action = document.createElement('input');
    action.type = 'hidden';
    action.name = 'auditaction';
    action.value = 'deleteall';
    form.appendChild(action);
    form.submit();
}

document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('spotaward-deleteall-modal');
    var input = document.getElementById('spotaward-deleteall-input');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                localSpotawardCloseDeleteAll();
            }
        });
    }
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                localSpotawardSubmitDeleteAll();
            }
        });
        input.addEventListener('input', function() {
            input.classList.remove('is-invalid');
            var feedback = document.getElementById('spotaward-deleteall-feedback');
            if (feedback) feedback.style.display = 'none';
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            localSpotawardCloseDeleteAll();
        }
    });
});
");

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
