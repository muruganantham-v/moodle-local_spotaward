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
 *
 * @group local_spotaward
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

    /**
     * Test spreadsheet formula neutralisation.
     */
    public function test_prepare_csv_cell() {
        $this->assertSame("'=SUM(A1:A2)", api::prepare_csv_cell('=SUM(A1:A2)'));
        $this->assertSame("'  @command", api::prepare_csv_cell('  @command'));
        $this->assertSame('ordinary text', api::prepare_csv_cell('ordinary text'));
        $this->assertSame(42, api::prepare_csv_cell(42));
    }

    /**
     * Course roster and export access remain scoped to the assigned course.
     */
    public function test_nomination_course_access_is_course_scoped() {
        $user = $this->getDataGenerator()->create_user();
        $allowedcourse = $this->getDataGenerator()->create_course(['shortname' => 'ALLOW101']);
        $othercourse = $this->getDataGenerator()->create_course(['shortname' => 'OTHER101']);
        $roleid = create_role('Nominator', 'nominators', 'Test nominator role');
        set_config('nominator_role', 'nominators', 'local_spotaward');
        set_config('nomination_course_shortnames', 'ALLOW', 'local_spotaward');
        assign_capability('local/spotaward:nominate', CAP_ALLOW, $roleid,
            \context_system::instance()->id, true);
        $this->getDataGenerator()->enrol_user($user->id, $allowedcourse->id, $roleid, 'manual');
        api::clear_role_caches();

        $this->assertTrue(api::can_nominate_in_course($user->id, $allowedcourse->id));
        $this->assertFalse(api::can_nominate_in_course($user->id, $othercourse->id));
        $this->assertFalse(api::can_nominate_in_course(get_admin()->id, $othercourse->id));

        $nomination = (object)[
            'nominatorid' => $user->id,
            'programmanagerid' => 0,
            'maacexecutiveid' => 0,
            'courseid' => $allowedcourse->id,
            'adminsharedtime' => 0,
        ];
        $this->assertTrue(api::can_export_nomination_details($nomination, $user->id));
    }
}
