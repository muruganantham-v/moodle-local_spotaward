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
 * Backup class for local_spotaward.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup structure step for local_spotaward plugin.
 */
class backup_local_spotaward_plugin extends backup_local_plugin {

    /**
     * Defines the course backup structure for this subplugin.
     *
     * @return void
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();

        // Define the wrapper nested element.
        $spotaward = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($spotaward);

        // Define the parent nominations table structure.
        $nominations = new backup_nested_element('nominations', ['id'], [
            'nominatorid', 'programmanagerid', 'maacexecutiveid', 'courseid',
            'modulename', 'awardcategory', 'professional', 'awarddescription',
            'studentcount', 'status', 'adminsharedtime', 'adminsharedby',
            'admindownloadedtime', 'admindownloadedby', 'timecreated', 'timemodified'
        ]);
        $spotaward->add_child($nominations);

        // Define the student-level nomination items table structure.
        $items = new backup_nested_element('nomination_items', ['id'], [
            'nominationid', 'studentid', 'awardcategory', 'professional',
            'awarddescription', 'status', 'rejectionreason', 'closuredate',
            'reviewedby', 'timereviewed'
        ]);
        $nominations->add_child($items);

        // Define the status tracking table structure.
        $tracks = new backup_nested_element('status_tracks', ['id'], [
            'nominationid', 'nominationitemid', 'actorid',
            'fromstatus', 'tostatus', 'reason', 'timecreated'
        ]);
        $nominations->add_child($tracks);

        // Set source database tables and relations.
        $nominations->set_source_table('spotaward_nominations', ['courseid' => backup::VAR_COURSEID]);
        $items->set_source_table('spotaward_nomination_items', ['nominationid' => backup::VAR_PARENTID]);
        $tracks->set_source_table('spotaward_status_track', ['nominationid' => backup::VAR_PARENTID]);

        // Ensure every workflow user can be mapped during cross-site restore,
        // including system-role users who are not enrolled in the course.
        $nominations->annotate_ids('user', 'nominatorid');
        $nominations->annotate_ids('user', 'programmanagerid');
        $nominations->annotate_ids('user', 'maacexecutiveid');
        $nominations->annotate_ids('user', 'adminsharedby');
        $nominations->annotate_ids('user', 'admindownloadedby');
        $items->annotate_ids('user', 'studentid');
        $items->annotate_ids('user', 'reviewedby');
        $tracks->annotate_ids('user', 'actorid');

        // Certificate itemids are nomination IDs and are remapped on restore.
        $nominations->annotate_files('local_spotaward', 'certificates', 'id');
    }
}
