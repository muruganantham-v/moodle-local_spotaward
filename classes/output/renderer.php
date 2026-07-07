<?php
// This file is part of Moodle - http://moodle.org/

namespace local_spotaward\output;

use plugin_renderer_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for Spot Award System.
 *
 * @package   local_spotaward
 */
final class renderer extends plugin_renderer_base {
    /**
     * Render draft preview.
     *
     * @param array $rows
     * @return string
     */
    public function draft_preview(array $rows): string {
        if (empty($rows)) {
            return $this->render_card_table(
                get_string('draftentries', 'local_spotaward'),
                \html_writer::tag('p', get_string('nodraftentries', 'local_spotaward'), ['class' => 'spotaward-empty'])
            );
        }

        $columns = [
            ['key' => 'studentname', 'label' => get_string('studentname', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'studentemail', 'label' => get_string('studentemail', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'admissionid', 'label' => get_string('admissionid', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'batchname', 'label' => get_string('batchname', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'module', 'label' => get_string('module', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'mentorname', 'label' => get_string('mentorname', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'programmanagername', 'label' => get_string('programmanager', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'maacexecutivename', 'label' => get_string('maacexecutive', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'awardcategory', 'label' => get_string('awardcategory', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
            ['key' => 'professional', 'label' => get_string('professional', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
            ['key' => 'awarddescription', 'label' => get_string('awarddescription', 'local_spotaward'), 'type' => 'text', 'filter' => 'none'],
        ];

        $tablerows = [];
        foreach ($rows as $row) {
            $admissionid = (string)($row['admissionid'] ?? '');
            $highlightadmissionid = \local_spotaward\local\api::username_needs_admissionid_highlight($admissionid);
            $tablerows[] = [
                '_rowclass' => $highlightadmissionid ? 'spotaward-admissionid-warning-row' : '',
                'studentname' => \local_spotaward_table_cell(s($row['studentname']), ['text' => (string)$row['studentname']]),
                'studentemail' => \local_spotaward_table_cell(s($row['studentemail']), ['text' => (string)$row['studentemail']]),
                'admissionid' => \local_spotaward_table_cell(s($admissionid), ['text' => $admissionid]),
                'batchname' => \local_spotaward_table_cell(s($row['batchname']), ['text' => (string)$row['batchname']]),
                'module' => \local_spotaward_table_cell(s($row['module']), ['text' => (string)$row['module']]),
                'mentorname' => \local_spotaward_table_cell(s($row['mentorname']), ['text' => (string)$row['mentorname']]),
                'programmanagername' => \local_spotaward_table_cell(s($row['programmanagername'] ?? ''), ['text' => (string)($row['programmanagername'] ?? '')]),
                'maacexecutivename' => \local_spotaward_table_cell(s($row['maacexecutivename'] ?? ''), ['text' => (string)($row['maacexecutivename'] ?? '')]),
                'awardcategory' => \local_spotaward_table_cell(s($row['awardcategory']), ['text' => (string)$row['awardcategory']]),
                'professional' => \local_spotaward_table_cell(s($row['professional']), ['text' => (string)$row['professional']]),
                'awarddescription' => \local_spotaward_table_cell($row['awarddescription'], [
                    'text' => trim(html_entity_decode(strip_tags((string)$row['awarddescription']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                ]),
            ];
        }

        return $this->render_card_table(
            get_string('draftentries', 'local_spotaward'),
            \local_spotaward_render_data_table($columns, $tablerows, [
                'id' => 'spotaward-draft-preview',
                'label' => get_string('draftentries', 'local_spotaward'),
                'searchlabel' => 'Search student',
                'searchplaceholder' => 'Admission ID, name, email',
            ])
        );
    }

    /**
     * Render history list.
     *
     * @param array $rows
     * @return string
     */
    public function submission_history(array $rows): string {
        if (empty($rows)) {
            return $this->render_card_table(
                get_string('submissionhistory', 'local_spotaward'),
                \html_writer::tag('p', get_string('historyempty', 'local_spotaward'), ['class' => 'spotaward-empty'])
            );
        }

        $columns = [
            ['key' => 'submitteddate', 'label' => get_string('submitteddate', 'local_spotaward'), 'type' => 'date', 'filter' => 'date'],
            ['key' => 'coursename', 'label' => get_string('course', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
            ['key' => 'module', 'label' => get_string('module', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
            ['key' => 'statuslabel', 'label' => get_string('status', 'local_spotaward'), 'type' => 'text', 'filter' => 'select'],
            ['key' => 'actions', 'label' => get_string('actions', 'local_spotaward'), 'type' => 'text', 'filter' => 'none', 'sortable' => false, 'searchable' => false],
        ];

        $tablerows = [];
        foreach ($rows as $row) {
            $timestamp = !empty($row['submittedtimestamp']) ? (int)$row['submittedtimestamp'] : 0;
            $tablerows[] = [
                'submitteddate' => \local_spotaward_table_cell(s($row['submitteddate']), [
                    'text' => (string)$row['submitteddate'],
                    'sort' => $timestamp,
                    'date' => $timestamp ? userdate($timestamp, '%Y-%m-%d') : '',
                ]),
                'coursename' => \local_spotaward_table_cell(s($row['coursename']), ['text' => (string)$row['coursename']]),
                'module' => \local_spotaward_table_cell(s($row['module']), ['text' => (string)$row['module']]),
                'statuslabel' => \local_spotaward_table_cell($row['statuslabel'], ['text' => strip_tags((string)$row['statuslabel'])]),
                'actions' => \local_spotaward_table_cell(
                    !empty($row['detailsurl'])
                        ? \html_writer::link($row['detailsurl'], get_string('viewdetails', 'local_spotaward'))
                        : '&ndash;',
                    ['text' => !empty($row['detailsurl']) ? get_string('viewdetails', 'local_spotaward') : '', 'search' => '']
                ),
            ];
        }

        return $this->render_card_table(
            get_string('submissionhistory', 'local_spotaward'),
            \local_spotaward_render_data_table($columns, $tablerows, [
                'id' => 'spotaward-submission-history',
                'label' => get_string('submissionhistory', 'local_spotaward'),
                'searchlabel' => get_string('searchtable', 'local_spotaward'),
                'searchplaceholder' => get_string('historysearchplaceholder', 'local_spotaward'),
            ])
        );
    }

    /**
     * Wrap content in the plugin's standard card layout.
     *
     * @param string $title
     * @param string $content
     * @return string
     */
    private function render_card_table(string $title, string $content): string {
        return \html_writer::div(
            \html_writer::div(
                \html_writer::tag('strong', $title),
                'spotaward-card-header'
            ) .
            \html_writer::div($content, 'spotaward-card-body'),
            'spotaward-card'
        );
    }

    /**
     * Render summary cards.
     *
     * @param array $counts
     * @param bool $showrejected
     * @return string
     */
    public function manager_summary(array $counts, bool $showrejected = false): string {
        return $this->render_from_template('local_spotaward/manager_summary', [
            'showpending' => true,
            'showrejected' => $showrejected,
            'pending' => $counts['pending'] ?? 0,
            'pendinglabel' => get_string('pendingtickets', 'local_spotaward'),
            'partiallyreviewed' => $counts['partiallyreviewed'] ?? 0,
            'partiallyreviewedlabel' => get_string('partiallyreviewedtickets', 'local_spotaward'),
            'rejected' => $counts['rejected'] ?? 0,
            'ssteamprogress' => $counts['ssteamprogress'] ?? 0,
            'ssteamprogresslabel' => get_string('totalssteamprogress', 'local_spotaward'),
            'rejectedlabel' => get_string('totalrejected', 'local_spotaward'),
            'closed' => $counts['closed'] ?? 0,
            'closedlabel' => get_string('totalclosed', 'local_spotaward'),
        ]);
    }

    /**
     * Render SS Team summary cards.
     *
     * @param array $counts
     * @return string
     */
    public function ss_team_summary(array $counts): string {
        return $this->render_from_template('local_spotaward/manager_summary', [
            'showpending' => false,
            'showrejected' => false,
            'ssteamprogress' => $counts['ssteamprogress'] ?? 0,
            'ssteamprogresslabel' => get_string('totalssteamprogress', 'local_spotaward'),
            'closed' => $counts['closed'] ?? 0,
            'closedlabel' => get_string('totalclosed', 'local_spotaward'),
        ]);
    }
}
