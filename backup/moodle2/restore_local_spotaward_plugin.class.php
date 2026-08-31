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
 * Restore class for local_spotaward.
 *
 * @package   local_spotaward
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore structure step for local_spotaward plugin.
 */
class restore_local_spotaward_plugin extends restore_local_plugin {

    /**
     * Restore certificate files after nomination ID mappings exist.
     *
     * @return void
     */
    public function after_execute_course() {
        $this->add_related_files('local_spotaward', 'certificates', 'spotaward_nomination');
    }

    /**
     * Defines the course plugin restore structure.
     *
     * @return array
     */
    protected function define_course_plugin_structure() {
        $paths = [];

        // Define restored elements paths matching backup structure.
        $paths[] = new restore_path_element('spotaward_nomination', $this->get_pathfor('/nominations'));
        $paths[] = new restore_path_element('spotaward_item', $this->get_pathfor('/nominations/nomination_items'));
        $paths[] = new restore_path_element('spotaward_track', $this->get_pathfor('/nominations/status_tracks'));

        return $paths;
    }

    /**
     * Process restored nomination records.
     *
     * @param array|stdClass $data
     * @return void
     */
    public function process_spotaward_nomination($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        // Map course ID to restored context.
        $data->courseid = $this->get_courseid();

        // Translate old user IDs from backup to current site user mappings.
        $data->nominatorid = $this->get_mappingid('user', $data->nominatorid);
        $data->programmanagerid = $this->get_mappingid('user', $data->programmanagerid);
        $data->maacexecutiveid = $data->maacexecutiveid ? $this->get_mappingid('user', $data->maacexecutiveid) : 0;
        $data->adminsharedby = $data->adminsharedby ? $this->get_mappingid('user', $data->adminsharedby) : 0;
        $data->admindownloadedby = $data->admindownloadedby ? $this->get_mappingid('user', $data->admindownloadedby) : 0;

        $newid = $DB->insert_record('spotaward_nominations', $data);
        $this->set_mapping('spotaward_nomination', $oldid, $newid);
    }

    /**
     * Process restored student nomination items.
     *
     * @param array|stdClass $data
     * @return void
     */
    public function process_spotaward_item($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        // Fetch restored parent nomination ID.
        $data->nominationid = $this->get_new_parentid('spotaward_nomination');
        if (!$data->nominationid) {
            return;
        }

        // Translate old student and reviewer user IDs.
        $data->studentid = $this->get_mappingid('user', $data->studentid);
        $data->reviewedby = $data->reviewedby ? $this->get_mappingid('user', $data->reviewedby) : 0;

        $newid = $DB->insert_record('spotaward_nomination_items', $data);
        $this->set_mapping('spotaward_item', $oldid, $newid);
    }

    /**
     * Process restored status tracking logs.
     *
     * @param array|stdClass $data
     * @return void
     */
    public function process_spotaward_track($data) {
        global $DB;
        $data = (object)$data;

        // Fetch restored parent nomination ID.
        $data->nominationid = $this->get_new_parentid('spotaward_nomination');
        if (!$data->nominationid) {
            return;
        }

        // Fetch restored child nomination item ID.
        if ($data->nominationitemid) {
            $data->nominationitemid = $this->get_mappingid('spotaward_item', $data->nominationitemid);
        }

        // Translate actor user ID.
        $data->actorid = $data->actorid ? $this->get_mappingid('user', $data->actorid) : 0;

        $DB->insert_record('spotaward_status_track', $data);
    }
}
