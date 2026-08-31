<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Privacy provider tests for local_spotaward.
 *
 * @package    local_spotaward
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_spotaward\tests;

use advanced_testcase;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use local_spotaward\local\api;
use local_spotaward\privacy\provider;

/**
 * Privacy provider tests for local_spotaward.
 *
 * @group local_spotaward
 */
class privacy_provider_test extends advanced_testcase {

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata() {
        $collection = new collection('local_spotaward');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(4, $itemcollection);

        $tables = array_map(function($item) {
            return $item->get_name();
        }, $itemcollection);

        $this->assertContains('spotaward_nominations', $tables);
        $this->assertContains('spotaward_nomination_items', $tables);
        $this->assertContains('spotaward_status_track', $tables);
        $this->assertContains('zoho_cliq', $tables);
    }

    /**
     * User deletion anonymises reviewer references and removes generated PDFs.
     */
    public function test_delete_user_data_removes_reviewer_and_certificate_data() {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $nominationid = $DB->insert_record('spotaward_nominations', (object)[
            'nominatorid' => $user->id,
            'programmanagerid' => $user->id,
            'maacexecutiveid' => $user->id,
            'courseid' => $course->id,
            'modulename' => 'Module',
            'professional' => 'Professional',
            'studentcount' => 1,
            'status' => 'closed',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $itemid = $DB->insert_record('spotaward_nomination_items', (object)[
            'nominationid' => $nominationid,
            'studentid' => $user->id,
            'awardcategory' => 'Award',
            'professional' => 'Professional',
            'status' => 'closed',
            'rejectionreason' => 'Reviewer-authored personal data',
            'reviewedby' => $user->id,
            'timereviewed' => time(),
        ]);
        api::save_certificate_file($nominationid, $itemid, $user->id, '%PDF-test');

        $approved = new approved_contextlist(
            $user,
            'local_spotaward',
            [\context_course::instance($course->id)->id]
        );
        provider::delete_data_for_user($approved);

        $item = $DB->get_record('spotaward_nomination_items', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame(0, (int)$item->studentid);
        $this->assertSame(0, (int)$item->reviewedby);
        $this->assertSame('', (string)$item->rejectionreason);
        $this->assertFalse(api::has_certificate_file($nominationid, $user->id, $itemid));
    }
}
