<?php
// This file is part of Moodle - http://moodle.org/

/**
 * API tests for local_spotaward.
 *
 * @package    local_spotaward
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_spotaward\tests;

use advanced_testcase;
use local_spotaward\local\api;

/**
 * API tests for local_spotaward.
 */
class api_test extends advanced_testcase {

    /**
     * Set up before tests
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test role checks
     */
    public function test_role_checks() {
        global $DB;

        // Create a user
        $user = $this->getDataGenerator()->create_user();
        
        // At this point they have no special roles, so all role checks should return false.
        $this->assertFalse(api::is_manager($user->id));
        $this->assertFalse(api::is_ss_team($user->id));
        
        // This confirms the basic API calls don't fatal error.
        $this->assertTrue(true);
    }
    
    /**
     * Test fetching nomination defaults
     */
    public function test_get_nomination() {
        // Fetching a non-existent nomination should throw a dml_missing_record_exception.
        $this->expectException(\dml_missing_record_exception::class);
        api::get_nomination(-1);
    }
}
