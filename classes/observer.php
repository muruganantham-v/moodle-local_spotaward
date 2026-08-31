<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_spotaward;

defined('MOODLE_INTERNAL') || die();

/**
 * Core event observers for Spot Award lifecycle cleanup.
 *
 * @package local_spotaward
 */
final class observer {
    /**
     * Remove workflow records and certificate files when their course is deleted.
     *
     * @param \core\event\course_deleted $event
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        $context = $event->get_context();
        if ($context && $context->contextlevel === CONTEXT_COURSE) {
            \local_spotaward\privacy\provider::delete_data_for_all_users_in_context($context);
            return;
        }

        // The context may already have been removed by a custom deletion flow.
        // Database records still need deterministic cleanup.
        global $DB;
        $nominations = $DB->get_records('spotaward_nominations',
            ['courseid' => (int)$event->objectid], '', 'id');
        if (!$nominations) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($nominations));
        $DB->delete_records_select('spotaward_status_track', "nominationid $insql", $params);
        $DB->delete_records_select('spotaward_nomination_items', "nominationid $insql", $params);
        $DB->delete_records_select('spotaward_nominations', "id $insql", $params);
    }
}
