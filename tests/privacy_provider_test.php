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
use local_spotaward\privacy\provider;

/**
 * Privacy provider tests for local_spotaward.
 */
class privacy_provider_test extends advanced_testcase {

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata() {
        $collection = new collection('local_spotaward');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(3, $itemcollection);

        $tables = array_map(function($item) {
            return $item->get_name();
        }, $itemcollection);

        $this->assertContains('spotaward_nominations', $tables);
        $this->assertContains('spotaward_nomination_items', $tables);
        $this->assertContains('spotaward_status_track', $tables);
    }
}
