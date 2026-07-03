<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/forms/bulk_rejection_form.php');
require_once(__DIR__ . '/forms/rejection_form.php');
require_once(__DIR__ . '/forms/closure_form.php');
require_once(__DIR__ . '/forms/close_record_form.php');
require_once(__DIR__ . '/forms/reassign_nomination_form.php');

use local_spotaward\forms\bulk_rejection_form;
use local_spotaward\forms\close_record_form;
use local_spotaward\forms\closure_form;
use local_spotaward\forms\reassign_nomination_form;
use local_spotaward\forms\rejection_form;
use local_spotaward\local\api;

require_login();

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$itemid = optional_param('itemid', 0, PARAM_INT);
$selecteditemscsv = optional_param('selecteditemscsv', '', PARAM_TEXT);
$selecteditemids = array_values(array_unique(optional_param_array('selecteditems', [], PARAM_INT)));

$nomination = api::get_nomination($id);
api::require_nomination_access($nomination, $USER->id);

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/spotaward/submission.php', ['id' => $id]);
$PAGE->set_title(get_string('submissiondetail', 'local_spotaward'));
$PAGE->set_heading(get_string('submissiondetail', 'local_spotaward'));
local_spotaward_require_stylesheet();
local_spotaward_require_action_success_overlay();

$canreview = is_siteadmin() || ((int)$nomination->programmanagerid === (int)$USER->id);
$cancontinuereview = $canreview && in_array($nomination->status, ['pending', 'underreview'], true);
$canmanagerapprove = is_siteadmin() || api::is_manager($USER->id);
$isssteam = api::is_assigned_maac_executive($nomination, (int)$USER->id);
$ispm = (int)$nomination->programmanagerid === (int)$USER->id;
$cansharetoadmin = is_siteadmin() || api::is_ss_team((int)$USER->id);
$canreassign = (is_siteadmin() || $isssteam || $ispm) && in_array($nomination->status, ['pending', 'ssteamprogress'], true);
$canviewcertificates = ($canmanagerapprove || $isssteam)
    && in_array($nomination->status, ['ssteamprogress', 'closed'], true);

$course = get_course($nomination->courseid);
$nominator = core_user::get_user($nomination->nominatorid);
$programmanager = core_user::get_user($nomination->programmanagerid);
$maacexecutive = !empty($nomination->maacexecutiveid) ? core_user::get_user($nomination->maacexecutiveid) : null;
$items = api::get_nomination_items($id);

if ($action === 'approve' && $itemid && $cancontinuereview && confirm_sesskey()) {
    // Guard against double-submit / back-button: only call the API if the item is
    // still in a reviewable state. If it was already approved, redirect silently.
    $targetitem = null;
    foreach ($items as $sitem) {
        if ((int)$sitem->id === (int)$itemid) {
            $targetitem = $sitem;
            break;
        }
    }
    if ($targetitem && in_array($targetitem->status, ['pending', 'underreview'], true)) {
        api::update_item_status($itemid, 'ssteamprogress', $USER->id);
    }
    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
        get_string('reviewupdated', 'local_spotaward')
    );
}

if ($action === 'reapprove' && $itemid && $canreview
        && in_array($nomination->status, ['pending', 'underreview', 'ssteamprogress'], true)
        && confirm_sesskey()) {
    $targetitem = null;
    foreach ($items as $sitem) {
        if ((int)$sitem->id === (int)$itemid) {
            $targetitem = $sitem;
            break;
        }
    }
    if ($targetitem && $targetitem->status === 'rejected') {
        api::update_item_status($itemid, 'ssteamprogress', $USER->id, '');
    }
    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
        get_string('reviewupdated', 'local_spotaward')
    );
}

if ($action === 'approveall' && $cancontinuereview && confirm_sesskey()) {
    foreach ($items as $item) {
        if ($item->status === 'pending') {
            api::update_item_status($item->id, 'ssteamprogress', $USER->id, null, true);
        }
    }
    api::refresh_nomination_status($id);

    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/index.php', ['view' => 'programmanager']),
        get_string('reviewupdated', 'local_spotaward')
    );
}

if ($action === 'bulkapprove' && $cancontinuereview && confirm_sesskey()) {
    $approvedcount = api::bulk_update_review_item_status($id, $selecteditemids, 'ssteamprogress', $USER->id);
    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
        get_string('selectedstudentsapproved', 'local_spotaward', $approvedcount)
    );
}

if ($isssteam && in_array($action, ['bulkregenerate', 'bulkshare'], true) && confirm_sesskey()) {
    if (!in_array($nomination->status, ['ssteamprogress', 'closed'], true)) {
        throw new moodle_exception('invalidparameter');
    }

    $selecteditemids = optional_param_array('selecteditems', [], PARAM_INT);
    if ($action === 'bulkregenerate') {
        $generated = api::ensure_selected_nomination_certificates_generated($id, $selecteditemids);
        local_spotaward_success_redirect(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
            get_string('selectedcertificatesregenerated', 'local_spotaward', count($generated))
        );
    }

    $sentcount = api::share_selected_certificates_to_students($id, $selecteditemids);
    local_spotaward_success_redirect(
        new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
        get_string('selectedcertificatessharedsuccess', 'local_spotaward', $sentcount)
    );
}

$rejectionform = null;
if ($action === 'reject' && $itemid && $cancontinuereview) {
    $rejectionform = new rejection_form(null, []);
    $rejectionform->set_data([
        'id' => $id,
        'itemid' => $itemid,
        'action' => 'reject',
    ]);

    if ($rejectionform->is_cancelled()) {
        redirect(new moodle_url('/local/spotaward/submission.php', ['id' => $id]));
    } else if ($data = $rejectionform->get_data()) {
        api::update_item_status((int)$data->itemid, 'rejected', $USER->id, $data->rejectionreason);
        local_spotaward_success_redirect(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
            get_string('reviewupdated', 'local_spotaward')
        );
    }
}

$bulkrejectionform = null;
if ($action === 'bulkreject' && $cancontinuereview) {
    $selecteditemsvalue = $selecteditemscsv !== '' ? $selecteditemscsv : implode(',', $selecteditemids);
    $bulkrejectionform = new bulk_rejection_form(null, [
        'selectedcount' => count(array_filter(array_map('intval', explode(',', $selecteditemsvalue)))),
    ]);
    $bulkrejectionform->set_data([
        'id' => $id,
        'action' => 'bulkreject',
        'selecteditemscsv' => $selecteditemsvalue,
    ]);

    if ($bulkrejectionform->is_cancelled()) {
        redirect(new moodle_url('/local/spotaward/submission.php', ['id' => $id]));
    } else if ($data = $bulkrejectionform->get_data()) {
        $bulkitemids = array_values(array_unique(array_filter(array_map('intval',
            explode(',', (string)$data->selecteditemscsv)))));
        $rejectedcount = api::bulk_update_review_item_status($id, $bulkitemids, 'rejected', $USER->id,
            $data->rejectionreason);
        local_spotaward_success_redirect(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
            get_string('selectedstudentsrejected', 'local_spotaward', $rejectedcount)
        );
    }
}

$closureform = null;
if ($action === 'closeticket' && $itemid && $isssteam) {
    $closureform = new closure_form(null, [
        'returnurl' => (new moodle_url('/local/spotaward/submission.php', ['id' => $id]))->out(false),
    ]);
    $selecteditem = null;
    foreach ($items as $item) {
        if ((int)$item->id === (int)$itemid) {
            $selecteditem = $item;
            break;
        }
    }
    $closureform->set_data([
        'id' => $id,
        'itemid' => $itemid,
        'action' => 'closeticket',
        'rejectionreason' => $selecteditem->rejectionreason ?? '',
        'closuredate' => time(),
    ]);

    if ($closureform->is_cancelled()) {
        redirect(new moodle_url('/local/spotaward/submission.php', ['id' => $id]));
    } else if ($data = $closureform->get_data()) {
        api::close_rejected_ticket((int)$data->itemid, $USER->id, $data->rejectionreason, (int)$data->closuredate);
        local_spotaward_success_redirect(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
            get_string('ticketclosed', 'local_spotaward')
        );
    }
}

$closerecordform = null;
if ($action === 'closerecord' && $isssteam && $nomination->status === 'ssteamprogress') {
    $closerecordform = new close_record_form(new moodle_url('/local/spotaward/submission.php', ['id' => $id, 'action' => 'closerecord']), [
        'returnurl' => (new moodle_url('/local/spotaward/submission.php', ['id' => $id]))->out(false),
    ]);
    $closerecordform->set_data([
        'id' => $id,
        'closuredate' => time(),
    ]);

    if ($closerecordform->is_cancelled()) {
        redirect(new moodle_url('/local/spotaward/submission.php', ['id' => $id]));
    } else if ($data = $closerecordform->get_data()) {
        api::close_nomination_record($id, $USER->id, (int)$data->closuredate);
        local_spotaward_success_redirect(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id]),
            get_string('recordclosed', 'local_spotaward')
        );
    }
}

$reassignform = null;
if ($action === 'reassign' && $canreassign) {
    $programmanageroptions = [];
    foreach (api::get_program_managers_for_course((int)$nomination->courseid) as $user) {
        $programmanageroptions[(int)$user->id] = fullname($user);
    }
    if (!isset($programmanageroptions[(int)$nomination->programmanagerid]) && $programmanager) {
        $programmanageroptions[(int)$nomination->programmanagerid] = fullname($programmanager);
    }

    $maacexecutiveoptions = [];
    foreach (api::get_maac_executives_for_course((int)$nomination->courseid) as $user) {
        $maacexecutiveoptions[(int)$user->id] = fullname($user);
    }
    if (!isset($maacexecutiveoptions[(int)$nomination->maacexecutiveid]) && $maacexecutive) {
        $maacexecutiveoptions[(int)$nomination->maacexecutiveid] = fullname($maacexecutive);
    }

    $reassignform = new reassign_nomination_form(null, [
        'programmanageroptions' => $programmanageroptions,
        'maacexecutiveoptions' => $maacexecutiveoptions,
        'currentprogrammanagerid' => (int)$nomination->programmanagerid,
        'currentmaacexecutiveid' => (int)$nomination->maacexecutiveid,
        'userrole' => is_siteadmin($USER->id) ? 'admin' : ($ispm ? 'pm' : ($isssteam ? 'maac' : '')),
    ]);
    $reassignform->set_data([
        'id' => $id,
        'action' => 'reassign',
        'programmanagerid' => (int)$nomination->programmanagerid,
        'maacexecutiveid' => (int)$nomination->maacexecutiveid,
        'currentprogrammanagerid' => (int)$nomination->programmanagerid,
        'currentmaacexecutiveid' => (int)$nomination->maacexecutiveid,
    ]);

    if ($reassignform->is_cancelled()) {
        redirect(new moodle_url('/local/spotaward/submission.php', ['id' => $id]));
    } else if ($data = $reassignform->get_data()) {
        api::reassign_nomination_role(
            $id,
            (int)$USER->id,
            (int)$data->programmanagerid,
            (int)$data->maacexecutiveid
        );
        $returnurl = is_siteadmin($USER->id)
            ? new moodle_url('/local/spotaward/submission.php', ['id' => $id])
            : new moodle_url('/local/spotaward/index.php', ['view' => 'ssteam']);
        local_spotaward_success_redirect(
            $returnurl,
            get_string('reassignmentupdated', 'local_spotaward')
        );
    }
}

/* parse award categories from the stored description (one per line-group: "Category: desc") */
$allcategories = '';
if (!empty($nomination->awarddescription)) {
    $cats = [];
    foreach (preg_split('/\n\n/', $nomination->awarddescription) as $part) {
        if (preg_match('/^(.+?):/', $part, $m)) {
            $cats[] = trim($m[1]);
        }
    }
    $allcategories = implode(', ', $cats);
}

echo $OUTPUT->header();
echo html_writer::start_div('local-spotaward-app');
echo html_writer::start_div('spotaward-shell');

$backurl = new moodle_url('/local/spotaward/index.php');
if ($canreview) {
    $backurl = new moodle_url('/local/spotaward/index.php', ['view' => 'programmanager']);
} else if ($isssteam) {
    $backurl = new moodle_url('/local/spotaward/index.php', ['view' => 'ssteam']);
}
echo html_writer::div(
    html_writer::link($backurl, '&larr; ' . get_string('back'), ['class' => 'btn btn-secondary']),
    'spotaward-back-link'
);

echo html_writer::tag('h3', get_string('submissiondetail', 'local_spotaward'), ['class' => 'spotaward-section-title']);
$PAGE->requires->js_init_code(local_spotaward_submission_report_modal_js(new moodle_url('/local/spotaward/ajax.php')));
$PAGE->requires->js_init_code(<<<JS
(function() {
    var forms = document.querySelectorAll('[data-spotaward-bulk-select-form]');
    Array.prototype.forEach.call(forms, function(form) {
        var defaultSelector = form.getAttribute('data-spotaward-default-actions-target');
        var defaultActions = defaultSelector ? document.querySelector(defaultSelector) : null;
        var selectedActions = form.querySelector('[data-spotaward-selected-actions]');
        var checkboxes = form.querySelectorAll('.spotaward-student-select');
        var selectAll = form.querySelector('.spotaward-student-select-all');
        var countLabels = form.querySelectorAll('[data-spotaward-selection-label]');
        if (!checkboxes.length) {
            return;
        }

        function toggleActions() {
            var checkedCount = Array.prototype.filter.call(checkboxes, function(checkbox) {
                return checkbox.checked;
            }).length;
            var hasSelection = checkedCount > 0;
            if (selectAll) {
                selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            }
            if (defaultActions) {
                defaultActions.hidden = hasSelection;
                defaultActions.classList.toggle('hidden', hasSelection);
            }
            if (selectedActions) {
                selectedActions.hidden = !hasSelection;
                selectedActions.classList.toggle('hidden', !hasSelection);
            }
            Array.prototype.forEach.call(countLabels, function(label) {
                var baseLabel = label.getAttribute('data-spotaward-selection-label') || '';
                label.textContent = hasSelection ? baseLabel + ' (' + checkedCount + ')' : baseLabel;
            });
        }

        Array.prototype.forEach.call(checkboxes, function(checkbox) {
            checkbox.addEventListener('change', toggleActions);
        });
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                Array.prototype.forEach.call(checkboxes, function(checkbox) {
                    checkbox.checked = selectAll.checked;
                });
                toggleActions();
            });
        }
        toggleActions();
    });
}());
JS);

echo html_writer::start_div('spotaward-card mb-4');
echo html_writer::start_div('spotaward-card-header');
echo html_writer::tag('strong', get_string('submissiondetail', 'local_spotaward'));
echo html_writer::end_div();
echo html_writer::start_div('spotaward-card-body');
echo html_writer::start_div('spotaward-meta');
$metaitems = [
    get_string('mentor', 'local_spotaward') => fullname($nominator),
    get_string('programmanager', 'local_spotaward') => fullname($programmanager),
    get_string('maacexecutive', 'local_spotaward') => $maacexecutive ? fullname($maacexecutive) : '',
    get_string('course', 'local_spotaward') => html_writer::link(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        format_string($course->fullname),
        ['target' => '_blank', 'style' => 'color: var(--sa-primary); font-weight: 700; text-decoration: none;']
    ),
    get_string('module', 'local_spotaward') => s($nomination->modulename),
    get_string('professional', 'local_spotaward') => s($nomination->professional ?? ''),
    get_string('awardcategories', 'local_spotaward') => s($allcategories),
    get_string('studentcount', 'local_spotaward') => (int)$nomination->studentcount,
    get_string('datesubmitted', 'local_spotaward') => userdate((int)$nomination->timecreated),
];
if ($nomination->status === 'closed') {
    $metaitems[get_string('dateclosed', 'local_spotaward')] = userdate((int)$nomination->timemodified);
}
$totalitems    = count($items);
$revieweditems = count(array_filter($items, function($item) { return $item->status !== 'pending'; }));
$certificateexist = in_array($nomination->status, ['ssteamprogress', 'closed'], true)
    && api::certificates_exist($id);

if ($nomination->status === 'pending' && $revieweditems > 0 && $totalitems > 0) {
    $statuslabel = get_string('partiallyreviewed', 'local_spotaward') .
                   ' (' . $revieweditems . '/' . $totalitems . ')';
} else if ($nomination->status === 'ssteamprogress' && !$certificateexist) {
    $statuslabel = get_string('approvedawaitingss', 'local_spotaward');
} else {
    $statuslabel = get_string($nomination->status, 'local_spotaward');
}
$metaitems[get_string('status', 'local_spotaward')] = local_spotaward_render_badge($statuslabel);
foreach ($metaitems as $label => $value) {
    echo html_writer::div(
        html_writer::tag('span', $label, ['class' => 'spotaward-meta-label']) .
        html_writer::div($value, 'spotaward-meta-value'),
        'spotaward-meta-item'
    );
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

$actionbuttons = [];
if (in_array($nomination->status, ['ssteamprogress', 'closed'], true)) {
    if ($isssteam && $nomination->status === 'ssteamprogress') {
        $actionbuttons[] = html_writer::link(
            new moodle_url('/local/spotaward/download_pr.php', ['nominationid' => $id, 'sesskey' => sesskey()]),
            get_string('downloadpr', 'local_spotaward'),
            ['class' => 'btn btn-info']
        );
    }

    if ($isssteam && $certificateexist) {
        $actionbuttons[] = html_writer::link(
            new moodle_url('/local/spotaward/view_certificate.php', ['nominationid' => $id, 'userid' => 0, 'sesskey' => sesskey()]),
            get_string('viewallcertificates', 'local_spotaward'),
            ['class' => 'btn btn-primary', 'target' => '_blank']
        );
        $actionbuttons[] = html_writer::link(
            new moodle_url('/local/spotaward/index.php', ['downloadallcert' => $id, 'sesskey' => sesskey()]),
            get_string('downloadallcertificates', 'local_spotaward'),
            ['class' => 'btn btn-warning']
        );
        $actionbuttons[] = html_writer::link(
            new moodle_url('/local/spotaward/index.php', ['sharecertificates' => $id, 'sesskey' => sesskey()]),
            get_string('sharecertificatestostudents', 'local_spotaward'),
            [
                'class' => 'btn btn-success',
                'onclick' => 'return confirm("' . get_string('sharecertificatestostudentsconfirm', 'local_spotaward') . '");',
                'data-spotaward-success' => '1',
            ]
        );
    } else if (!$isssteam && $canviewcertificates && $certificateexist) {
        $actionbuttons[] = html_writer::link(
            new moodle_url('/local/spotaward/view_certificate.php', ['nominationid' => $id, 'userid' => 0, 'sesskey' => sesskey()]),
            get_string('viewallcertificates', 'local_spotaward'),
            ['class' => 'btn btn-primary', 'target' => '_blank']
        );
        $actionbuttons[] = html_writer::link(
            new moodle_url('/local/spotaward/index.php', ['downloadallcert' => $id, 'sesskey' => sesskey()]),
            get_string('downloadallcertificates', 'local_spotaward'),
            ['class' => 'btn btn-warning']
        );
    }

    if ($cansharetoadmin && $nomination->status === 'ssteamprogress') {
        $sharetoadminbutton = html_writer::link(
            new moodle_url('/local/spotaward/share_admin.php', ['id' => $id]),
            get_string('sharetoadmin', 'local_spotaward'),
            ['class' => 'btn btn-primary']
        );
        $sharetoadminshared = !empty($nomination->adminsharedtime);
        if ($sharetoadminshared) {
            $sharetoadminbutton .= html_writer::div(
                html_writer::span('&#10003;', 'spotaward-admin-sent-icon') .
                html_writer::span(get_string('alreadysent', 'local_spotaward'), 'spotaward-admin-sent-text'),
                'spotaward-admin-sent-confirmation'
            );
        }
        $actionbuttons[] = html_writer::div(
            $sharetoadminbutton,
            'spotaward-share-admin-action' . ($sharetoadminshared ? ' has-confirmation' : '')
        );
        $actionbuttons[] = html_writer::link(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id, 'action' => 'closerecord']),
            get_string('distributed', 'local_spotaward'),
            ['class' => 'btn btn-danger']
        );
    }
}

if (!empty($actionbuttons)) {
    $actionrowclasses = 'spotaward-action-row mb-3';
    if (!empty($nomination->adminsharedtime)) {
        $actionrowclasses .= ' has-admin-confirmation';
    }
    echo html_writer::div(implode(' ', $actionbuttons), $actionrowclasses, [
        'data-spotaward-default-actions' => '1',
    ]);
}

if ($canreassign) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id, 'action' => 'reassign']),
            get_string('reassignnomination', 'local_spotaward'),
            ['class' => 'btn btn-secondary mb-3']
        ),
        'spotaward-reassign-action'
    );
}

if ($rejectionform) {
    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-header');
    echo html_writer::tag('h4', get_string('reject', 'local_spotaward'));
    echo html_writer::end_div();
    echo html_writer::start_div('spotaward-card-body');
    $rejectionform->display();
    echo html_writer::end_div();
    echo html_writer::end_div();
}
if ($bulkrejectionform) {
    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-header');
    echo html_writer::tag('h4', get_string('rejectselectedstudents', 'local_spotaward'));
    echo html_writer::end_div();
    echo html_writer::start_div('spotaward-card-body');
    $bulkrejectionform->display();
    echo html_writer::end_div();
    echo html_writer::end_div();
}
if ($closureform) {
    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-header');
    echo html_writer::tag('h4', get_string('closeticket', 'local_spotaward'));
    echo html_writer::end_div();
    echo html_writer::start_div('spotaward-card-body');
    $closureform->display();
    echo html_writer::end_div();
    echo html_writer::end_div();
}
if ($closerecordform) {
    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-header');
    echo html_writer::tag('h4', get_string('closerecord', 'local_spotaward'));
    echo html_writer::end_div();
    echo html_writer::start_div('spotaward-card-body');
    $closerecordform->display();
    echo html_writer::end_div();
    echo html_writer::end_div();
}
if ($reassignform) {
    echo html_writer::start_div('spotaward-card');
    echo html_writer::start_div('spotaward-card-header');
    echo html_writer::tag('h4', get_string('reassignnomination', 'local_spotaward'));
    echo html_writer::end_div();
    echo html_writer::start_div('spotaward-card-body');
    $reassignform->display();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($cancontinuereview) {
    $haspendingitems = false;
    foreach ($items as $item) {
        if ($item->status === 'pending') {
            $haspendingitems = true;
            break;
        }
    }
    if ($haspendingitems) {
        echo html_writer::start_div('mb-3');
        echo html_writer::link(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id, 'action' => 'approveall', 'sesskey' => sesskey()]),
            get_string('approveall', 'local_spotaward'),
            [
                'class' => 'btn btn-success',
                'onclick' => 'return confirm("' . get_string('confirmapproveall', 'local_spotaward') . '");',
                'data-spotaward-success' => '1',
            ]
        );
        echo html_writer::end_div();
    }
}

$showpmreviewbulkactions = $cancontinuereview;
$showbulkcertificateactions = $isssteam && in_array($nomination->status, ['ssteamprogress', 'closed'], true);

$columns = [];
if ($showpmreviewbulkactions || $showbulkcertificateactions) {
    $columns[] = [
        'key' => 'selectstudent',
        'label' => html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'class' => 'spotaward-student-select-all',
            'aria-label' => get_string('selectall'),
        ]),
        'type' => 'text',
        'filter' => 'none',
        'sortable' => false,
        'searchable' => false,
        'labelhtml' => true,
    ];
}
$columns = array_merge($columns, [
    ['key' => 'studentname', 'label' => get_string('studentname', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'studentemail', 'label' => get_string('studentemail', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'admissionid', 'label' => get_string('admissionid', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'awardcategory', 'label' => get_string('awardcategory', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
    ['key' => 'awarddescription', 'label' => get_string('awarddescription', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'status', 'label' => get_string('status', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'rejectionreason', 'label' => get_string('rejectionreason', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
    ['key' => 'actions', 'label' => get_string('actions', 'local_spotaward'), 'type' => 'text', 'filter' => 'none', 'sortable' => false, 'searchable' => false],
]);

$rows = [];
foreach ($items as $item) {
    $actions = [
        html_writer::link(
            '#',
            get_string('viewreport', 'local_spotaward'),
            [
                'class' => 'spotaward-view-report',
                'data-itemid' => $item->id,
            ]
        ),
    ];

    if ($cancontinuereview && $item->status === 'pending') {
        $actions[] = html_writer::link(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id, 'action' => 'approve', 'itemid' => $item->id, 'sesskey' => sesskey()]),
            get_string('approve', 'local_spotaward'),
            ['data-spotaward-success' => '1']
        );
        $actions[] = html_writer::link(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id, 'action' => 'reject', 'itemid' => $item->id]),
            get_string('reject', 'local_spotaward')
        );
    }

    if ($canreview && $item->status === 'rejected' && in_array($nomination->status, ['pending', 'ssteamprogress'], true)) {
        $actions[] = html_writer::link(
            new moodle_url('/local/spotaward/submission.php', ['id' => $id, 'action' => 'reapprove', 'itemid' => $item->id, 'sesskey' => sesskey()]),
            get_string('reapprove', 'local_spotaward'),
            ['data-spotaward-success' => '1']
        );
    }

    if (($isssteam || $canmanagerapprove) &&
            in_array($item->status, ['ssteamprogress', 'closed'], true) &&
            api::get_certificate_file($id, $item->studentid, $item->id)) {
        $actions[] = html_writer::link(
            new moodle_url('/local/spotaward/view_certificate.php', ['nominationid' => $id, 'userid' => $item->studentid, 'itemid' => $item->id, 'sesskey' => sesskey(), 'action' => 'view']),
            get_string('viewcertificate', 'local_spotaward'),
            ['target' => '_blank']
        );
        $actions[] = html_writer::link(
            new moodle_url('/local/spotaward/view_certificate.php', ['nominationid' => $id, 'userid' => $item->studentid, 'itemid' => $item->id, 'sesskey' => sesskey(), 'action' => 'download']),
            get_string('downloadcertificate', 'local_spotaward')
        );
    }


    $itemcategory = s($item->awardcategory ?? '');
    $itemdescription = $item->awarddescription ?? '';
    $studentname = fullname($item);
    $studentemail = (string)$item->email;
    $admissionid = (string)$item->username;
    $highlightadmissionid = api::username_needs_admissionid_highlight($admissionid);
    $statuslabel = get_string($item->status, 'local_spotaward');
    $rejectionreason = (string)$item->rejectionreason;

    $row = [
        '_rowclass' => $highlightadmissionid ? 'spotaward-admissionid-warning-row' : '',
        'studentname' => local_spotaward_table_cell(s($studentname), ['text' => $studentname]),
        'studentemail' => local_spotaward_table_cell(s($studentemail), ['text' => $studentemail]),
        'admissionid' => local_spotaward_table_cell(s($admissionid), ['text' => $admissionid]),
        'awardcategory' => local_spotaward_table_cell($itemcategory, ['text' => trim(html_entity_decode(strip_tags($itemcategory), ENT_QUOTES | ENT_HTML5, 'UTF-8'))]),
        'awarddescription' => local_spotaward_table_cell($itemdescription, [
            'text' => trim(html_entity_decode(strip_tags((string)$itemdescription), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ]),
        'status' => local_spotaward_table_cell(local_spotaward_render_badge($statuslabel), ['text' => $statuslabel]),
        'rejectionreason' => local_spotaward_table_cell(s($rejectionreason), ['text' => $rejectionreason]),
        'actions' => local_spotaward_table_cell(implode(' | ', $actions), ['text' => implode(' ', array_map('strip_tags', $actions)), 'search' => '']),
    ];
    if ($showpmreviewbulkactions || $showbulkcertificateactions) {
        $checkbox = '';
        $isselectableforreview = $showpmreviewbulkactions && in_array($item->status, ['pending', 'underreview'], true);
        $isselectableforcertificates = $showbulkcertificateactions && in_array($item->status, ['ssteamprogress', 'closed'], true);
        if ($isselectableforreview || $isselectableforcertificates) {
            $checkbox = html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'name' => 'selecteditems[]',
                'value' => (int)$item->id,
                'class' => 'spotaward-student-select',
                'aria-label' => get_string('select') . ' ' . $studentname,
            ]);
        }
        $row = ['selectstudent' => local_spotaward_table_cell($checkbox, ['text' => '', 'search' => ''])] + $row;
    }
    $rows[] = $row;
}

echo html_writer::start_div('spotaward-card');
echo html_writer::start_div('spotaward-card-header');
echo html_writer::tag('h4', get_string('studentstatus', 'local_spotaward'));
echo html_writer::end_div();
echo html_writer::start_div('spotaward-card-body');
if ($showpmreviewbulkactions || $showbulkcertificateactions) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/spotaward/submission.php', ['id' => $id]))->out(false),
        'class' => 'spotaward-bulk-certificate-form',
        'data-spotaward-bulk-select-form' => '1',
        'data-spotaward-default-actions-target' => $showbulkcertificateactions ? '[data-spotaward-default-actions]' : '',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    if ($showpmreviewbulkactions) {
        echo html_writer::div(
            html_writer::tag('button', get_string('approveselectedstudents', 'local_spotaward'), [
                'type' => 'submit',
                'name' => 'action',
                'value' => 'bulkapprove',
                'class' => 'btn btn-success',
                'onclick' => 'return confirm("' . get_string('confirmapproveselectedstudents', 'local_spotaward') . '");',
                'data-spotaward-success-submit' => '1',
                'data-spotaward-progress-message' => 'Approving selected students...',
                'data-spotaward-success-message' => 'Selected students approved',
                'data-spotaward-selection-label' => get_string('approveselectedstudents', 'local_spotaward'),
            ]) .
            html_writer::tag('button', get_string('rejectselectedstudents', 'local_spotaward'), [
                'type' => 'submit',
                'name' => 'action',
                'value' => 'bulkreject',
                'class' => 'btn btn-danger',
                'data-spotaward-selection-label' => get_string('rejectselectedstudents', 'local_spotaward'),
            ]),
            'spotaward-action-row mb-3 hidden',
            ['data-spotaward-selected-actions' => '1', 'hidden' => 'hidden']
        );
    } else if ($showbulkcertificateactions) {
        echo html_writer::div(
            html_writer::tag('button', get_string('regenerateselectedcertificates', 'local_spotaward'), [
                'type' => 'submit',
                'name' => 'action',
                'value' => 'bulkregenerate',
                'class' => 'btn btn-warning',
                'data-spotaward-success-submit' => '1',
                'data-spotaward-progress-message' => 'Re-generating certificate...',
                'data-spotaward-success-message' => 'Re-generated certificate',
                'data-spotaward-selection-label' => get_string('regenerateselectedcertificates', 'local_spotaward'),
            ]) .
            html_writer::tag('button', get_string('shareselectedcertificatestostudents', 'local_spotaward'), [
                'type' => 'submit',
                'name' => 'action',
                'value' => 'bulkshare',
                'class' => 'btn btn-success',
                'data-spotaward-success-submit' => '1',
                'data-spotaward-progress-message' => 'Sharing certificate to selected student...',
                'data-spotaward-success-message' => 'Certificate shared to selected student',
                'data-spotaward-selection-label' => get_string('shareselectedcertificatestostudents', 'local_spotaward'),
            ]),
            'spotaward-action-row mb-3 hidden',
            ['data-spotaward-selected-actions' => '1', 'hidden' => 'hidden']
        );
    }
}
echo local_spotaward_render_data_table($columns, $rows, [
    'id' => 'spotaward-submission-items',
    'label' => get_string('studentstatus', 'local_spotaward'),
    'searchlabel' => 'Search student',
    'searchplaceholder' => 'name, email, admission id',
    'downloadpdfurl' => (new moodle_url('/local/spotaward/download_details.php', ['id' => $id]))->out(false),
    'downloadpdflabel' => 'Download Student details',
]);
if ($showpmreviewbulkactions || $showbulkcertificateactions) {
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
