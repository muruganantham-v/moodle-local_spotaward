<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function xmldb_local_spotaward_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026040900) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('spotaward_nomination_items');
        $field = new xmldb_field('awardcategory', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'studentid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026040900, 'local', 'spotaward');
    }

    if ($oldversion < 2026041000) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('spotaward_nomination_items');
        $field = new xmldb_field('awarddescription', XMLDB_TYPE_TEXT, null, null, null, null, null, 'awardcategory');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026041000, 'local', 'spotaward');
    }

    if ($oldversion < 2026041100) {
        $nominations = $DB->get_records('spotaward_nominations');
        
        foreach ($nominations as $nomination) {
            if (empty($nomination->awarddescription)) {
                continue;
            }

            $parts = preg_split('/\n\n/', $nomination->awarddescription);
            $categoryMap = [];
            
            foreach ($parts as $part) {
                if (preg_match('/^(.+?):\s*(.*)$/s', $part, $matches)) {
                    $categoryMap[$matches[1]] = $matches[2];
                }
            }

            $items = $DB->get_records('spotaward_nomination_items', ['nominationid' => $nomination->id]);
            
            if (!empty($items) && !empty($categoryMap)) {
                $firstCategory = array_key_first($categoryMap);
                $firstDesc = $categoryMap[$firstCategory];
                
                foreach ($items as $item) {
                    $DB->update_record('spotaward_nomination_items', (object)[
                        'id' => $item->id,
                        'awardcategory' => $firstCategory,
                        'awarddescription' => $firstDesc,
                    ]);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026041100, 'local', 'spotaward');
    }

    if ($oldversion < 2026041102) {
        // Upgrade version 1.1.0 - Added certificate generation feature
        // No database changes required. This is a code update for:
        // - Certificate generation and download functionality
        // - Support for PDF generation with TCPDF
        // - All nominees can now download certificates after final workflow approval
        
        upgrade_plugin_savepoint(true, 2026041102, 'local', 'spotaward');
    }

    if ($oldversion < 2026042204) {
        $dbman = $DB->get_manager();

        $nominationtable = new xmldb_table('spotaward_nominations');
        $nominationfield = new xmldb_field('professional', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'awardcategory');
        if (!$dbman->field_exists($nominationtable, $nominationfield)) {
            $dbman->add_field($nominationtable, $nominationfield);
        }

        $itemtable = new xmldb_table('spotaward_nomination_items');
        $itemfield = new xmldb_field('professional', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'awardcategory');
        if (!$dbman->field_exists($itemtable, $itemfield)) {
            $dbman->add_field($itemtable, $itemfield);
        }

        if ($dbman->field_exists($nominationtable, $nominationfield) && $dbman->field_exists($itemtable, $itemfield)) {
            $sql = "UPDATE {spotaward_nomination_items}
                       SET professional = (
                           SELECT n.professional
                             FROM {spotaward_nominations} n
                            WHERE n.id = {spotaward_nomination_items}.nominationid
                       )
                     WHERE professional = ''";
            $DB->execute($sql);
        }

        upgrade_plugin_savepoint(true, 2026042204, 'local', 'spotaward');
    }

    if ($oldversion < 2026042206) {
        $defaults = [
            'submission_pm_subject' => 'New Submission for Approval – {{course}} ({{module}})',
            'submission_pm_body' => "Hi {{program_manager_name}},\n\nA new submission has been raised for your review.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Mentor: {{mentor_name}}\n- Total Students: {{total_students}}\n- Moodle Link: {{moodle_link}}\n\nDescription:\n{{description}}\n\nPlease review and take necessary action.\n\nThanks,\n{{nominator_name}}",
            'pm_to_ss_subject' => 'SS Team Action Required - {{course}} ({{module}})',
            'pm_to_ss_body' => "Hi {{recipient_name}},\n\nThe following request has been approved by the Program Manager and assigned to the SS Team.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Mentor: {{mentor_name}}\n- Total Students: {{total_students}}\n- Moodle Link: {{moodle_link}}\n- Approved By: {{program_manager_name}}\n\nDescription:\n{{description}}\n\nPlease proceed with SS Team processing.\n\nThanks,\nEmertxe information technology",
            'pm_to_mentor_subject' => 'Update on Your Submission – {{course}} ({{module}})',
            'pm_to_mentor_body' => "Hi {{mentor_name}},\n\nYour submission has been reviewed by the Program Manager.\n\nStatus: {{status}}\n\nComments (if any):\n{{pm_comments}}\n\nRegards,\nEmertxe information technology",
            'ss_to_admin_subject' => 'PR Document Shared - {{course}} ({{module}})',
            'ss_to_admin_body' => "Hi {{recipient_name}},\n\nThe SS Team has shared the PR document for this request.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Mentor: {{mentor_name}}\n- Total Students: {{total_students}}\n\nThe uploaded PR document and any selected certificate attachment are included.\n\nThanks,\nEmertxe information technology",
        ];

        foreach ($defaults as $key => $value) {
            $current = get_config('local_spotaward', $key);
            if ($current === false || $current === null || $current === '') {
                set_config($key, $value, 'local_spotaward');
            }
        }

        upgrade_plugin_savepoint(true, 2026042206, 'local', 'spotaward');
    }

    if ($oldversion < 2026042207) {
        $defaults = [
            'student_certificate_subject' => 'Your Spot Award Certificate - {{course}}',
            'student_certificate_body' => "Hi {{student_firstname}},\n\nCongratulations on receiving the Spot Award for {{course}}.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Award Category: {{award_category}}\n\nYour certificate is attached to this email.\n\nRegards,\nEmertxe information technology",
        ];

        foreach ($defaults as $key => $value) {
            $current = get_config('local_spotaward', $key);
            if ($current === false || $current === null || $current === '') {
                set_config($key, $value, 'local_spotaward');
            }
        }

        upgrade_plugin_savepoint(true, 2026042207, 'local', 'spotaward');
    }

    if ($oldversion < 2026042208) {
        $dbman = $DB->get_manager();
        $itemtable = new xmldb_table('spotaward_nomination_items');
        $closurefield = new xmldb_field('closuredate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'rejectionreason');
        if (!$dbman->field_exists($itemtable, $closurefield)) {
            $dbman->add_field($itemtable, $closurefield);
        }

        $DB->set_field('spotaward_nomination_items', 'status', 'ssteamprogress', ['status' => 'approved']);
        $DB->set_field('spotaward_nomination_items', 'status', 'ssteamprogress', ['status' => 'l2approved']);
        $DB->set_field('spotaward_nominations', 'status', 'pending', ['status' => 'underreview']);
        $DB->set_field('spotaward_nominations', 'status', 'ssteamprogress', ['status' => 'reviewed']);
        $DB->set_field('spotaward_nominations', 'status', 'ssteamprogress', ['status' => 'waitingforl2approval']);
        $DB->set_field('spotaward_nominations', 'status', 'ssteamprogress', ['status' => 'l2approved']);

        $defaults = [
            'pm_to_ss_subject' => 'SS Team Action Required - {{course}} ({{module}})',
            'pm_to_ss_body' => "Hi {{recipient_name}},\n\nThe following request has been approved by the Program Manager and assigned to the SS Team.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Mentor: {{mentor_name}}\n- Total Students: {{total_students}}\n- Moodle Link: {{moodle_link}}\n- Approved By: {{program_manager_name}}\n\nDescription:\n{{description}}\n\nPlease proceed with SS Team processing.\n\nThanks,\nEmertxe information technology",
            'ss_to_admin_subject' => 'PR Document Shared - {{course}} ({{module}})',
            'ss_to_admin_body' => "Hi {{recipient_name}},\n\nThe SS Team has shared the PR document for this request.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Mentor: {{mentor_name}}\n- Total Students: {{total_students}}\n\nThe uploaded PR document and any selected certificate attachment are included.\n\nThanks,\nEmertxe information technology",
        ];

        foreach ($defaults as $key => $value) {
            $current = get_config('local_spotaward', $key);
            if ($current === false || $current === null || $current === '') {
                set_config($key, $value, 'local_spotaward');
            }
        }

        foreach (['pm_to_l2_subject', 'pm_to_l2_body', 'l2_to_teams_subject', 'l2_to_teams_body',
                'l2_to_pm_nominator_subject', 'l2_to_pm_nominator_body', 'l2_team_members'] as $key) {
            unset_config($key, 'local_spotaward');
        }

        upgrade_plugin_savepoint(true, 2026042208, 'local', 'spotaward');
    }

    if ($oldversion < 2026042209) {
        $defaults = [
            'record_closed_subject' => 'Spot Award Record Closed - {{course}} ({{module}})',
            'record_closed_body' => "Hi {{recipient_name}},\n\nThe Spot Award record has been closed.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Mentor: {{mentor_name}}\n- Program Manager: {{program_manager_name}}\n- Total Students: {{total_students}}\n- Closure Date: {{closure_date}}\n- Closed By: {{closed_by}}\n\nRegards,\nEmertxe information technology",
        ];

        foreach ($defaults as $key => $value) {
            $current = get_config('local_spotaward', $key);
            if ($current === false || $current === null || $current === '') {
                set_config($key, $value, 'local_spotaward');
            }
        }

        upgrade_plugin_savepoint(true, 2026042209, 'local', 'spotaward');
    }

    if ($oldversion < 2026042210) {
        $oldbody = "Hi {{recipient_name}},\n\nThe SS Team has shared the PR document for this request.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Mentor: {{mentor_name}}\n- Total Students: {{total_students}}\n\nThe uploaded PR document is attached.\n\nThanks,\nEmertxe information technology";
        $newbody = "Hi {{recipient_name}},\n\nThe SS Team has shared the PR document for this request.\n\nDetails:\n- Course: {{course}}\n- Module: {{module}}\n- Mentor: {{mentor_name}}\n- Total Students: {{total_students}}\n\nThe uploaded PR document and any selected certificate attachment are included.\n\nThanks,\nEmertxe information technology";

        $current = get_config('local_spotaward', 'ss_to_admin_body');
        if ($current === false || $current === null || $current === '' || $current === $oldbody) {
            set_config('ss_to_admin_body', $newbody, 'local_spotaward');
        }

        upgrade_plugin_savepoint(true, 2026042210, 'local', 'spotaward');
    }

    if ($oldversion < 2026042211) {
        upgrade_plugin_savepoint(true, 2026042211, 'local', 'spotaward');
    }

    if ($oldversion < 2026042212) {
        foreach (['zohocliq_enabled', 'zohocliq_nominator_webhook', 'zohocliq_program_manager_webhook',
                'zohocliq_ss_team_webhook', 'zohocliq_admin_webhook'] as $key) {
            unset_config($key, 'local_spotaward');
        }

        $current = get_config('local_spotaward', 'zohocliq_bot_url');
        if ($current === false || $current === null || $current === '') {
            set_config('zohocliq_bot_url', 'https://cliq.zoho.com/api/v2/bots/batchinformer/message',
                'local_spotaward');
        }

        upgrade_plugin_savepoint(true, 2026042212, 'local', 'spotaward');
    }

    if ($oldversion < 2026042213) {
        $dbman = $DB->get_manager();
        $nominationtable = new xmldb_table('spotaward_nominations');

        $sharedtimefield = new xmldb_field('adminsharedtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
        if (!$dbman->field_exists($nominationtable, $sharedtimefield)) {
            $dbman->add_field($nominationtable, $sharedtimefield);
        }

        $sharedbyfield = new xmldb_field('adminsharedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'adminsharedtime');
        if (!$dbman->field_exists($nominationtable, $sharedbyfield)) {
            $dbman->add_field($nominationtable, $sharedbyfield);
        }

        upgrade_plugin_savepoint(true, 2026042213, 'local', 'spotaward');
    }

    if ($oldversion < 2026042214) {
        $defaults = [
            'submission_pm_subject' => 'Spot Award Nominations Submitted - {{module}} | {{professional}} | {{course}}',
            'submission_pm_body' => "Dear {{program_manager_name}},\n\nI would like to inform you that I have submitted the Spot Award nominations for the {{module}} module. Kindly review and approve the nominations at the earliest so the distribution process can be initiated.\n\nNomination Details:\nProfessional: {{professional}}\nBatch ID: {{course}}\nModule: {{module}}\nTotal Nominations: {{total_students}} students\n\nRegards,\n{{mentor_name}}\nMentor - {{module}}\nEmertxe Information Technologies",
            'pm_to_ss_subject' => 'Spot Award Nominations Approved - {{module}} | {{professional}} | {{course}}',
            'pm_to_ss_body' => "Dear SS team,\n\nThis is to inform you that the Spot Award nominations submitted for {{module}} have been approved. Please proceed with further steps.\n\nApproval Details:\nProfessional: {{professional}}\nBatch ID: {{course}}\nModule: {{module}}\nTotal Approved Nominations: {{total_students}} students\n\nRegards,\n{{program_manager_name}}\nProgram Manager\nEmertxe Information Technologies",
            'pm_to_mentor_subject' => 'Spot Award Nominations Approved - {{module}} | {{professional}} | {{course}}',
            'pm_to_mentor_body' => "Dear {{mentor_name}},\n\nThis is to inform you that the Spot Award nominations submitted for {{module}} have been approved. Please proceed with further steps.\n\nApproval Details:\nProfessional: {{professional}}\nBatch ID: {{course}}\nModule: {{module}}\nTotal Approved Nominations: {{total_students}} students\n\nRegards,\n{{program_manager_name}}\nProgram Manager\nEmertxe Information Technologies",
            'ss_to_admin_subject' => 'Spot Award Certificate Print Request - {{module}} | {{course}} | {{professional}}',
            'ss_to_admin_body' => "Dear {{recipient_name}},\n\nRequesting for a Spot Award Certificate Print request.\n\nProcessing Details:\nProfessional: {{professional}}\nBatch ID: {{course}}\nModule: {{module}}\nTotal Certificates: {{total_students}} students\nMode: {{certificate_mode}}\n\nRegards,\nSS team\nSS team - {{course}}\nEmertxe Information Technologies",
            'record_closed_subject' => 'Spot Award Distribution Completed - {{module}} | {{professional}} | {{course}}',
            'record_closed_body' => "Dear {{recipient_name}},\n\nThis is to confirm that the Spot Award certificates for {{module}} have been successfully distributed to all nominated students.\n\nDistribution Summary:\nProfessional: {{professional}}\nBatch ID: {{course}}\nModule: {{module}}\nTotal Awards Distributed: {{total_students}} students\nDate of Distribution: {{closure_date}}\n\nThe Spot Award distribution process for this module is now complete.\n\nRegards,\nSS team\nSS team - {{course}}\nEmertxe Information Technologies",
        ];

        foreach ($defaults as $key => $value) {
            set_config($key, $value, 'local_spotaward');
        }

        upgrade_plugin_savepoint(true, 2026042214, 'local', 'spotaward');
    }

    if ($oldversion < 2026042215) {
        $defaults = [
            'submission_pm_body' => '<p>Dear {{program_manager_name}},</p><p>I would like to inform you that I have submitted the Spot Award nominations for the {{module}} module. Kindly review and approve the nominations at the earliest so the distribution process can be initiated.</p><p><strong>Nomination Details:</strong></p><table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;"><thead><tr><th align="left">Field</th><th align="left">Value</th></tr></thead><tbody><tr><td>Professional</td><td>{{professional}}</td></tr><tr><td>Batch ID</td><td>{{course}}</td></tr><tr><td>Module</td><td>{{module}}</td></tr><tr><td>Total Nominations</td><td>{{total_students}} students</td></tr></tbody></table><p>Regards,<br>{{mentor_name}}<br>Mentor - {{module}}<br>Emertxe Information Technologies</p>',
            'pm_to_ss_body' => '<p>Dear SS team,</p><p>This is to inform you that the Spot Award nominations submitted for {{module}} have been approved. Please proceed with further steps.</p><p><strong>Approval Details:</strong></p><table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;"><thead><tr><th align="left">Field</th><th align="left">Value</th></tr></thead><tbody><tr><td>Professional</td><td>{{professional}}</td></tr><tr><td>Batch ID</td><td>{{course}}</td></tr><tr><td>Module</td><td>{{module}}</td></tr><tr><td>Total Approved Nominations</td><td>{{total_students}} students</td></tr></tbody></table><p>Regards,<br>{{program_manager_name}}<br>Program Manager<br>Emertxe Information Technologies</p>',
            'pm_to_mentor_body' => '<p>Dear {{mentor_name}},</p><p>This is to inform you that the Spot Award nominations submitted for {{module}} have been approved. Please proceed with further steps.</p><p><strong>Approval Details:</strong></p><table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;"><thead><tr><th align="left">Field</th><th align="left">Value</th></tr></thead><tbody><tr><td>Professional</td><td>{{professional}}</td></tr><tr><td>Batch ID</td><td>{{course}}</td></tr><tr><td>Module</td><td>{{module}}</td></tr><tr><td>Total Approved Nominations</td><td>{{total_students}} students</td></tr></tbody></table><p>Regards,<br>{{program_manager_name}}<br>Program Manager<br>Emertxe Information Technologies</p>',
            'ss_to_admin_body' => '<p>Dear {{recipient_name}},</p><p>Requesting for a Spot Award Certificate Print request.</p><p><strong>Processing Details:</strong></p><table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;"><thead><tr><th align="left">Field</th><th align="left">Value</th></tr></thead><tbody><tr><td>Professional</td><td>{{professional}}</td></tr><tr><td>Batch ID</td><td>{{course}}</td></tr><tr><td>Module</td><td>{{module}}</td></tr><tr><td>Total Certificates</td><td>{{total_students}} students</td></tr><tr><td>Mode</td><td>{{certificate_mode}}</td></tr></tbody></table><p>Regards,<br>SS team<br>SS team - {{course}}<br>Emertxe Information Technologies</p>',
            'record_closed_body' => '<p>Dear {{recipient_name}},</p><p>This is to confirm that the Spot Award certificates for {{module}} have been successfully distributed to all nominated students.</p><p><strong>Distribution Summary:</strong></p><table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;"><thead><tr><th align="left">Field</th><th align="left">Value</th></tr></thead><tbody><tr><td>Professional</td><td>{{professional}}</td></tr><tr><td>Batch ID</td><td>{{course}}</td></tr><tr><td>Module</td><td>{{module}}</td></tr><tr><td>Total Awards Distributed</td><td>{{total_students}} students</td></tr><tr><td>Date of Distribution</td><td>{{closure_date}}</td></tr></tbody></table><p>The Spot Award distribution process for this module is now complete.</p><p>Regards,<br>SS team<br>SS team - {{course}}<br>Emertxe Information Technologies</p>',
            'cliq_submission_pm_subject' => 'Spot Award Nominations Submitted - {{module}} | {{professional}} | {{course}}',
            'cliq_submission_pm_body' => "Dear {{program_manager_name}},\n\nI would like to inform you that I have submitted the Spot Award nominations for the {{module}} module. Kindly review and approve the nominations at the earliest so the distribution process can be initiated.\n\n*Nomination Details*\nField | Value\nProfessional | {{professional}}\nBatch ID | {{course}}\nModule | {{module}}\nTotal Nominations | {{total_students}} students\n\nRegards,\n{{mentor_name}}\nMentor - {{module}}\nEmertxe Information Technologies",
            'cliq_pm_to_ss_subject' => 'Spot Award Nominations Approved - {{module}} | {{professional}} | {{course}}',
            'cliq_pm_to_ss_body' => "Dear SS team,\n\nThis is to inform you that the Spot Award nominations submitted for {{module}} have been approved. Please proceed with further steps.\n\n*Approval Details*\nField | Value\nProfessional | {{professional}}\nBatch ID | {{course}}\nModule | {{module}}\nTotal Approved Nominations | {{total_students}} students\n\nRegards,\n{{program_manager_name}}\nProgram Manager\nEmertxe Information Technologies",
            'cliq_pm_to_mentor_subject' => 'Spot Award Nominations Approved - {{module}} | {{professional}} | {{course}}',
            'cliq_pm_to_mentor_body' => "Dear {{mentor_name}},\n\nThis is to inform you that the Spot Award nominations submitted for {{module}} have been approved. Please proceed with further steps.\n\n*Approval Details*\nField | Value\nProfessional | {{professional}}\nBatch ID | {{course}}\nModule | {{module}}\nTotal Approved Nominations | {{total_students}} students\n\nRegards,\n{{program_manager_name}}\nProgram Manager\nEmertxe Information Technologies",
            'cliq_ss_to_admin_subject' => 'Spot Award Certificate Print Request - {{module}} | {{course}} | {{professional}}',
            'cliq_ss_to_admin_body' => "Dear {{recipient_name}},\n\nRequesting for a Spot Award Certificate Print request.\n\n*Processing Details*\nField | Value\nProfessional | {{professional}}\nBatch ID | {{course}}\nModule | {{module}}\nTotal Certificates | {{total_students}} students\nMode | {{certificate_mode}}\n\nRegards,\nSS team\nSS team - {{course}}\nEmertxe Information Technologies",
            'cliq_record_closed_subject' => 'Spot Award Distribution Completed - {{module}} | {{professional}} | {{course}}',
            'cliq_record_closed_body' => "Dear {{recipient_name}},\n\nThis is to confirm that the Spot Award certificates for {{module}} have been successfully distributed to all nominated students.\n\n*Distribution Summary*\nField | Value\nProfessional | {{professional}}\nBatch ID | {{course}}\nModule | {{module}}\nTotal Awards Distributed | {{total_students}} students\nDate of Distribution | {{closure_date}}\n\nThe Spot Award distribution process for this module is now complete.\n\nRegards,\nSS team\nSS team - {{course}}\nEmertxe Information Technologies",
            'cliq_student_certificate_subject' => 'Your Spot Award Certificate - {{course}}',
            'cliq_student_certificate_body' => "Hi {{student_firstname}},\n\nCongratulations on receiving the Spot Award for {{course}}.\n\nDetails:\nCourse | {{course}}\nModule | {{module}}\nAward Category | {{award_category}}\n\nYour certificate is attached to this email.\n\nRegards,\nEmertxe information technology",
        ];

        foreach ($defaults as $key => $value) {
            set_config($key, $value, 'local_spotaward');
        }

        upgrade_plugin_savepoint(true, 2026042215, 'local', 'spotaward');
    }

    if ($oldversion < 2026042216) {
        $defaults = [
            'cliq_submission_pm_body' => "Dear {{program_manager_name}},\n\nI would like to inform you that I have submitted the Spot Award nominations for the {{module}} module. Kindly review and approve the nominations at the earliest so the distribution process can be initiated.\n\n*Nomination Details*\n```Field                  Value\nProfessional           {{professional}}\nBatch ID               {{course}}\nModule                 {{module}}\nTotal Nominations      {{total_students}} students```\n\nRegards,\n{{mentor_name}}\nMentor - {{module}}\nEmertxe Information Technologies",
            'cliq_pm_to_ss_body' => "Dear SS team,\n\nThis is to inform you that the Spot Award nominations submitted for {{module}} have been approved. Please proceed with further steps.\n\n*Approval Details*\n```Field                        Value\nProfessional                 {{professional}}\nBatch ID                     {{course}}\nModule                       {{module}}\nTotal Approved Nominations   {{total_students}} students```\n\nRegards,\n{{program_manager_name}}\nProgram Manager\nEmertxe Information Technologies",
            'cliq_pm_to_mentor_body' => "Dear {{mentor_name}},\n\nThis is to inform you that the Spot Award nominations submitted for {{module}} have been approved. Please proceed with further steps.\n\n*Approval Details*\n```Field                        Value\nProfessional                 {{professional}}\nBatch ID                     {{course}}\nModule                       {{module}}\nTotal Approved Nominations   {{total_students}} students```\n\nRegards,\n{{program_manager_name}}\nProgram Manager\nEmertxe Information Technologies",
            'cliq_ss_to_admin_body' => "Dear {{recipient_name}},\n\nRequesting for a Spot Award Certificate Print request.\n\n*Processing Details*\n```Field                  Value\nProfessional           {{professional}}\nBatch ID               {{course}}\nModule                 {{module}}\nTotal Certificates     {{total_students}} students\nMode                   {{certificate_mode}}```\n\nRegards,\nSS team\nSS team - {{course}}\nEmertxe Information Technologies",
            'cliq_record_closed_body' => "Dear {{recipient_name}},\n\nThis is to confirm that the Spot Award certificates for {{module}} have been successfully distributed to all nominated students.\n\n*Distribution Summary*\n```Field                      Value\nProfessional               {{professional}}\nBatch ID                   {{course}}\nModule                     {{module}}\nTotal Awards Distributed   {{total_students}} students\nDate of Distribution       {{closure_date}}```\n\nThe Spot Award distribution process for this module is now complete.\n\nRegards,\nSS team\nSS team - {{course}}\nEmertxe Information Technologies",
            'cliq_student_certificate_body' => "Hi {{student_firstname}},\n\nCongratulations on receiving the Spot Award for {{course}}.\n\n*Details*\n```Field            Value\nCourse           {{course}}\nModule           {{module}}\nAward Category   {{award_category}}```\n\nYour certificate is attached to this email.\n\nRegards,\nEmertxe information technology",
        ];

        foreach ($defaults as $key => $value) {
            set_config($key, $value, 'local_spotaward');
        }

        upgrade_plugin_savepoint(true, 2026042216, 'local', 'spotaward');
    }

    if ($oldversion < 2026042217) {
        $dbman = $DB->get_manager();
        $nominationtable = new xmldb_table('spotaward_nominations');

        $maacfield = new xmldb_field('maacexecutiveid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'programmanagerid');
        if (!$dbman->field_exists($nominationtable, $maacfield)) {
            $dbman->add_field($nominationtable, $maacfield);
        }

        $maacindex = new xmldb_index('maacexecutive_idx', XMLDB_INDEX_NOTUNIQUE, ['maacexecutiveid']);
        if (!$dbman->index_exists($nominationtable, $maacindex)) {
            $dbman->add_index($nominationtable, $maacindex);
        }

        $DB->execute("UPDATE {spotaward_nominations}
                         SET maacexecutiveid = programmanagerid
                       WHERE maacexecutiveid = 0");

        upgrade_plugin_savepoint(true, 2026042217, 'local', 'spotaward');
    }

    if ($oldversion < 2026042218) {
        $current = get_config('local_spotaward', 'nomination_course_shortnames');
        if ($current === false || $current === null || trim((string)$current) === '') {
            set_config(
                'nomination_course_shortnames',
                implode("\n", \local_spotaward\local\constants::default_nomination_course_shortname_prefixes()),
                'local_spotaward'
            );
        }

        upgrade_plugin_savepoint(true, 2026042218, 'local', 'spotaward');
    }

    if ($oldversion < 2026052702) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('spotaward_cert_backgrounds');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        upgrade_plugin_savepoint(true, 2026052702, 'local', 'spotaward');
    }

    return true;
}
