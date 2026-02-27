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
 * Database upgrade script for the Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade from the given old version.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool Success
 */
function xmldb_local_moodle_zoho_sync_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Placeholder for future upgrades.
    if ($oldversion < 2026020100) {
        // Initial release - no upgrades needed yet.
        upgrade_plugin_savepoint(true, 2026020100, 'local', 'moodle_zoho_sync');
    }

    // Version 2026020101: Rename tables from mb_zoho_* to local_mzi_*.
    // Also rename timestamp fields to Moodle standard names.
    if ($oldversion < 2026020101) {

        // Rename tables if they exist with old names.
        
        // 1. Event log table.
        if ($dbman->table_exists('mb_zoho_event_log')) {
            $table = new xmldb_table('mb_zoho_event_log');
            $dbman->rename_table($table, 'local_mzi_event_log');
        }
        
        // 2. Sync history table.
        if ($dbman->table_exists('mb_zoho_sync_history')) {
            $table = new xmldb_table('mb_zoho_sync_history');
            $dbman->rename_table($table, 'local_mzi_sync_history');
        }
        
        // 3. Config table.
        if ($dbman->table_exists('mb_zoho_config')) {
            $table = new xmldb_table('mb_zoho_config');
            $dbman->rename_table($table, 'local_mzi_config');
        }
        
        // Rename timestamp fields in event_log table.
        $table = new xmldb_table('local_mzi_event_log');
        
        if ($dbman->field_exists($table, new xmldb_field('created_at'))) {
            $field = new xmldb_field('created_at');
            $dbman->rename_field($table, $field, 'timecreated');
        }
        
        if ($dbman->field_exists($table, new xmldb_field('updated_at'))) {
            $field = new xmldb_field('updated_at');
            $dbman->rename_field($table, $field, 'timemodified');
        }
        
        if ($dbman->field_exists($table, new xmldb_field('processed_at'))) {
            $field = new xmldb_field('processed_at');
            $dbman->rename_field($table, $field, 'timeprocessed');
        }
        
        // Rename index from created_at_idx to timecreated_idx.
        $index = new xmldb_index('created_at_idx', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        $newindex = new xmldb_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        
        // Rename timestamp fields in sync_history table.
        $table = new xmldb_table('local_mzi_sync_history');
        
        if ($dbman->field_exists($table, new xmldb_field('started_at'))) {
            $field = new xmldb_field('started_at');
            $dbman->rename_field($table, $field, 'timestarted');
        }
        
        if ($dbman->field_exists($table, new xmldb_field('completed_at'))) {
            $field = new xmldb_field('completed_at');
            $dbman->rename_field($table, $field, 'timecompleted');
        }
        
        // Rename index.
        $index = new xmldb_index('started_at_idx', XMLDB_INDEX_NOTUNIQUE, array('timestarted'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        $newindex = new xmldb_index('timestarted_idx', XMLDB_INDEX_NOTUNIQUE, array('timestarted'));
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        
        // Rename timestamp field in config table.
        $table = new xmldb_table('local_mzi_config');
        
        if ($dbman->field_exists($table, new xmldb_field('updated_at'))) {
            $field = new xmldb_field('updated_at');
            $dbman->rename_field($table, $field, 'timemodified');
        }

        upgrade_plugin_savepoint(true, 2026020101, 'local', 'moodle_zoho_sync');
    }
    
    // Version 2026020102: Add next_retry_at field for exponential backoff retry system.
    if ($oldversion < 2026020102) {
        $table = new xmldb_table('local_mzi_event_log');
        
        // Add next_retry_at field for production-grade retry scheduling.
        $field = new xmldb_field('next_retry_at', XMLDB_TYPE_INTEGER, '10', null, false, null, null, 'retry_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add index for efficient retry task query.
        $index = new xmldb_index('next_retry_at_idx', XMLDB_INDEX_NOTUNIQUE, array('next_retry_at'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        upgrade_plugin_savepoint(true, 2026020102, 'local', 'moodle_zoho_sync');
    }
    
    // Version 2026020801: Add detailed context columns to event_log for enhanced UI.
    if ($oldversion < 2026020801) {
        $table = new xmldb_table('local_mzi_event_log');

        // Add student_name column.
        $field = new xmldb_field('student_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'timeprocessed');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add course_name column.
        $field = new xmldb_field('course_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'student_name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add assignment_name column.
        $field = new xmldb_field('assignment_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'course_name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add grade_name column.
        $field = new xmldb_field('grade_name', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'assignment_name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add related_id column for generic reference.
        $field = new xmldb_field('related_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'grade_name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026020801, 'local', 'moodle_zoho_sync');
    }

    // Version 2026020802: Add BTEC templates tracking table.
    if ($oldversion < 2026020802) {
        $table = new xmldb_table('local_mzi_btec_templates');

        // Add fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('definition_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('zoho_unit_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('unit_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('zoho_unit_id_unique', XMLDB_KEY_UNIQUE, ['zoho_unit_id']);
        $table->add_key('definition_id_fk', XMLDB_KEY_FOREIGN, ['definition_id'], 'grading_definitions', ['id']);

        // Add indexes (foreign key already creates index for definition_id).
        $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);

        // Create table if it doesn't exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026020802, 'local', 'moodle_zoho_sync');
    }

    // Version 2026020900: Add grade queue table for hybrid observer + scheduled task.
    if ($oldversion < 2026020900) {
        $table = new xmldb_table('local_mzi_grade_queue');

        // Define fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        
        // Moodle IDs
        $table->add_field('grade_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('student_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assignment_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('course_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        // Zoho Integration
        $table->add_field('zoho_record_id', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('composite_key', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('workflow_state', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        
        // Status Tracking
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'BASIC_SENT');
        
        // Timestamps
        $table->add_field('basic_sent_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('enriched_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('failed_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        
        // Flags
        $table->add_field('needs_enrichment', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('needs_rr_check', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        
        // Error Handling
        $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('retry_count', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('composite_key_unique', XMLDB_KEY_UNIQUE, ['composite_key']);

        // Add indexes.
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('needs_enrichment_idx', XMLDB_INDEX_NOTUNIQUE, ['needs_enrichment']);
        $table->add_index('needs_rr_check_idx', XMLDB_INDEX_NOTUNIQUE, ['needs_rr_check']);
        $table->add_index('grade_id_idx', XMLDB_INDEX_NOTUNIQUE, ['grade_id']);
        $table->add_index('student_assignment_idx', XMLDB_INDEX_NOTUNIQUE, ['student_id', 'assignment_id']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // Create table if it doesn't exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026020900, 'local', 'moodle_zoho_sync');
    }

    // Version 2026020901: Add workflow_state field to grade queue table + Enhanced F grade logic
    if ($oldversion < 2026020901) {
        $table = new xmldb_table('local_mzi_grade_queue');
        
        // Add workflow_state field after composite_key
        $field = new xmldb_field('workflow_state', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'composite_key');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026020901, 'local', 'moodle_zoho_sync');
    }

    // Version 2026021501: Add Student Dashboard tables (students, webhook_logs, sync_status)
    if ($oldversion < 2026021501) {
        
        // 1. Create local_mzi_students table
        $table = new xmldb_table('local_mzi_students');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('moodle_user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_student_id', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            
            // Basic Information
            $table->add_field('student_id', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('registration_number', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('first_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('last_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('academic_email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('phone_number', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('date_of_birth', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('nationality', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('address', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('city', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('photo_url', XMLDB_TYPE_CHAR, '512', null, null, null, null);
            
            // Academic Information
            $table->add_field('academic_program', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('registration_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('study_language', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            
            // Sync Metadata
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('zoho_modified_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            
            // Define keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('moodle_user_fk', XMLDB_KEY_FOREIGN, ['moodle_user_id'], 'user', ['id']);
            
            // Define indexes (FK on moodle_user_id already creates index automatically)
            $table->add_index('zoho_student_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_student_id']);
            $table->add_index('academic_email_idx', XMLDB_INDEX_NOTUNIQUE, ['academic_email']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        // 2. Create local_mzi_webhook_logs table
        $table = new xmldb_table('local_mzi_webhook_logs');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('module', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_record_id', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('operation', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('processed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('processed_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            $table->add_index('module_idx', XMLDB_INDEX_NOTUNIQUE, ['module']);
            $table->add_index('zoho_record_id_idx', XMLDB_INDEX_NOTUNIQUE, ['zoho_record_id']);
            $table->add_index('processed_idx', XMLDB_INDEX_NOTUNIQUE, ['processed']);
            $table->add_index('created_at_idx', XMLDB_INDEX_NOTUNIQUE, ['created_at']);
            
            $dbman->create_table($table);
        }

        // 3. Create local_mzi_sync_status table
        $table = new xmldb_table('local_mzi_sync_status');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('module', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('last_full_sync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('last_incremental_sync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('total_records', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sync_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            $table->add_index('module_idx', XMLDB_INDEX_UNIQUE, ['module']);
            $table->add_index('sync_status_idx', XMLDB_INDEX_NOTUNIQUE, ['sync_status']);
            
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026021501, 'local', 'moodle_zoho_sync');
    }

    // Version 2026021601: Add Student Dashboard tables
    if ($oldversion < 2026021601) {

        // 1. Create local_mzi_registrations table
        $table = new xmldb_table('local_mzi_registrations');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('student_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_registration_id', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('registration_number', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('program_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('program_level', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('registration_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('expected_graduation', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('registration_status', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('total_fees', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('paid_amount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('remaining_amount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('currency', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('payment_plan', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('number_of_installments', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('zoho_modified_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('student_fk', XMLDB_KEY_FOREIGN, ['student_id'], 'local_mzi_students', ['id']);
            
            $table->add_index('zoho_registration_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_registration_id']);
            $table->add_index('registration_status_idx', XMLDB_INDEX_NOTUNIQUE, ['registration_status']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        // 2. Create local_mzi_installments table
        $table = new xmldb_table('local_mzi_installments');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('registration_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_installment_id', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('installment_number', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
            $table->add_field('due_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('amount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('paid_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('registration_fk', XMLDB_KEY_FOREIGN, ['registration_id'], 'local_mzi_registrations', ['id']);
            
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('due_date_idx', XMLDB_INDEX_NOTUNIQUE, ['due_date']);
            
            $dbman->create_table($table);
        }

        // 3. Create local_mzi_payments table
        $table = new xmldb_table('local_mzi_payments');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('registration_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_payment_id', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('payment_number', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('payment_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('payment_amount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('payment_method', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('voucher_number', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('bank_name', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('receipt_number', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('payment_notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('payment_status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('zoho_modified_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('registration_fk', XMLDB_KEY_FOREIGN, ['registration_id'], 'local_mzi_registrations', ['id']);
            
            $table->add_index('zoho_payment_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_payment_id']);
            $table->add_index('payment_date_idx', XMLDB_INDEX_NOTUNIQUE, ['payment_date']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        // 4. Create local_mzi_classes table
        $table = new xmldb_table('local_mzi_classes');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('zoho_class_id', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('class_number', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('class_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('unit_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('program_level', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('teacher_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('class_type', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('start_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('end_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('schedule', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('class_status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('zoho_modified_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            $table->add_index('zoho_class_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_class_id']);
            $table->add_index('class_status_idx', XMLDB_INDEX_NOTUNIQUE, ['class_status']);
            $table->add_index('program_level_idx', XMLDB_INDEX_NOTUNIQUE, ['program_level']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        // 5. Create local_mzi_enrollments table
        $table = new xmldb_table('local_mzi_enrollments');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('student_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('class_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_enrollment_id', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('enrollment_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('enrollment_status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('attendance_percentage', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
            $table->add_field('completion_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('student_fk', XMLDB_KEY_FOREIGN, ['student_id'], 'local_mzi_students', ['id']);
            $table->add_key('class_fk', XMLDB_KEY_FOREIGN, ['class_id'], 'local_mzi_classes', ['id']);
            
            $table->add_index('enrollment_status_idx', XMLDB_INDEX_NOTUNIQUE, ['enrollment_status']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        // 6. Create local_mzi_grades table
        $table = new xmldb_table('local_mzi_grades');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('student_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('class_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_grade_id', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('grade_number', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('assignment_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('btec_grade_name', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('numeric_grade', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
            $table->add_field('submission_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('grade_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('learning_outcomes', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('feedback_acknowledged', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('feedback_acknowledged_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('attempt_number', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
            $table->add_field('is_resubmission', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('zoho_modified_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('student_fk', XMLDB_KEY_FOREIGN, ['student_id'], 'local_mzi_students', ['id']);
            $table->add_key('class_fk', XMLDB_KEY_FOREIGN, ['class_id'], 'local_mzi_classes', ['id']);
            
            $table->add_index('zoho_grade_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_grade_id']);
            $table->add_index('btec_grade_name_idx', XMLDB_INDEX_NOTUNIQUE, ['btec_grade_name']);
            $table->add_index('feedback_acknowledged_idx', XMLDB_INDEX_NOTUNIQUE, ['feedback_acknowledged']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        // 7. Create local_mzi_requests table
        $table = new xmldb_table('local_mzi_requests');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('student_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_request_id', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('request_number', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('request_type', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('request_status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('priority', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('requested_classes', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('grade_details', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('change_information', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('admin_notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('admin_response', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('reviewed_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('reviewed_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('zoho_modified_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('student_fk', XMLDB_KEY_FOREIGN, ['student_id'], 'local_mzi_students', ['id']);
            $table->add_key('reviewed_by_fk', XMLDB_KEY_FOREIGN, ['reviewed_by'], 'user', ['id']);
            
            $table->add_index('zoho_request_id_idx', XMLDB_INDEX_NOTUNIQUE, ['zoho_request_id']);
            $table->add_index('request_type_idx', XMLDB_INDEX_NOTUNIQUE, ['request_type']);
            $table->add_index('request_status_idx', XMLDB_INDEX_NOTUNIQUE, ['request_status']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026021601, 'local', 'moodle_zoho_sync');
    }

    // Version 2026021602: Fix missing tables (consolidated student dashboard setup)
    if ($oldversion < 2026021602) {
        
        // Check and create local_mzi_students table if missing
        $table = new xmldb_table('local_mzi_students');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('moodle_user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_student_id', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            
            // Basic Information
            $table->add_field('student_id', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('registration_number', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('first_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('last_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('academic_email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('phone_number', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('date_of_birth', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('nationality', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('address', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('city', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('photo_url', XMLDB_TYPE_CHAR, '512', null, null, null, null);
            
            // Academic Information
            $table->add_field('academic_program', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('registration_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('study_language', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            
            // Sync Metadata
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('zoho_modified_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            
            // Define keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('moodle_user_fk', XMLDB_KEY_FOREIGN, ['moodle_user_id'], 'user', ['id']);
            
            // Define indexes (FK on moodle_user_id already creates index automatically)
            $table->add_index('zoho_student_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_student_id']);
            $table->add_index('academic_email_idx', XMLDB_INDEX_NOTUNIQUE, ['academic_email']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        // Check and create local_mzi_webhook_logs table if missing
        $table = new xmldb_table('local_mzi_webhook_logs');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('module', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_record_id', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('operation', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('processed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('processed_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            $table->add_index('module_idx', XMLDB_INDEX_NOTUNIQUE, ['module']);
            $table->add_index('zoho_record_id_idx', XMLDB_INDEX_NOTUNIQUE, ['zoho_record_id']);
            $table->add_index('processed_idx', XMLDB_INDEX_NOTUNIQUE, ['processed']);
            $table->add_index('created_at_idx', XMLDB_INDEX_NOTUNIQUE, ['created_at']);
            
            $dbman->create_table($table);
        }

        // Check and create local_mzi_sync_status table if missing
        $table = new xmldb_table('local_mzi_sync_status');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('module', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('last_full_sync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('last_incremental_sync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('total_records', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sync_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            $table->add_index('module_idx', XMLDB_INDEX_UNIQUE, ['module']);
            $table->add_index('sync_status_idx', XMLDB_INDEX_NOTUNIQUE, ['sync_status']);
            
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026021602, 'local', 'moodle_zoho_sync');
    }

    // Version 2026021603: Fix missing tables (consolidated student dashboard setup - final)
    if ($oldversion < 2026021603) {
        
        // Check and create local_mzi_students table if missing
        $table = new xmldb_table('local_mzi_students');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('moodle_user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_student_id', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            
            // Basic Information
            $table->add_field('student_id', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('registration_number', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('first_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('last_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('academic_email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('phone_number', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('date_of_birth', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('nationality', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('address', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('city', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('photo_url', XMLDB_TYPE_CHAR, '512', null, null, null, null);
            
            // Academic Information
            $table->add_field('academic_program', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('registration_date', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('study_language', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            
            // Sync Metadata
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('zoho_modified_time', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            
            // Define keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('moodle_user_fk', XMLDB_KEY_FOREIGN, ['moodle_user_id'], 'user', ['id']);
            
            // Define indexes (FK on moodle_user_id already creates index automatically)
            $table->add_index('zoho_student_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_student_id']);
            $table->add_index('academic_email_idx', XMLDB_INDEX_NOTUNIQUE, ['academic_email']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);
            
            $dbman->create_table($table);
        }

        // Check and create local_mzi_webhook_logs table if missing
        $table = new xmldb_table('local_mzi_webhook_logs');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('module', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_record_id', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('operation', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('processed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('processed_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            $table->add_index('module_idx', XMLDB_INDEX_NOTUNIQUE, ['module']);
            $table->add_index('zoho_record_id_idx', XMLDB_INDEX_NOTUNIQUE, ['zoho_record_id']);
            $table->add_index('processed_idx', XMLDB_INDEX_NOTUNIQUE, ['processed']);
            $table->add_index('created_at_idx', XMLDB_INDEX_NOTUNIQUE, ['created_at']);
            
            $dbman->create_table($table);
        }

        // Check and create local_mzi_sync_status table if missing
        $table = new xmldb_table('local_mzi_sync_status');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('module', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('last_full_sync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('last_incremental_sync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('total_records', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sync_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            $table->add_index('module_idx', XMLDB_INDEX_UNIQUE, ['module']);
            $table->add_index('sync_status_idx', XMLDB_INDEX_NOTUNIQUE, ['sync_status']);
            
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026021603, 'local', 'moodle_zoho_sync');
    }

    // Version 2026022001: Add moodle_class_id to local_mzi_classes
    // Required for enrollment: maps Zoho class → Moodle course ID
    if ($oldversion < 2026022001) {
        $table = new xmldb_table('local_mzi_classes');
        $field = new xmldb_field(
            'moodle_class_id',
            XMLDB_TYPE_CHAR, '20',
            null, null, null, null,
            'class_status'  // insert after class_status
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026022001, 'local', 'moodle_zoho_sync');
    }

    // Version 2026022100: Add local_mzi_request_windows table for admin-controlled request activation windows.
    if ($oldversion < 2026022100) {
        $table = new xmldb_table('local_mzi_request_windows');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('request_type', XMLDB_TYPE_CHAR,    '50',  null, XMLDB_NOTNULL);
            $table->add_field('enabled',      XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '1');
            $table->add_field('start_date',   XMLDB_TYPE_INTEGER, '10',  null, null);
            $table->add_field('end_date',     XMLDB_TYPE_INTEGER, '10',  null, null);
            $table->add_field('message',      XMLDB_TYPE_CHAR,    '255', null, null);
            $table->add_field('updated_by',   XMLDB_TYPE_INTEGER, '10',  null, null);
            $table->add_field('updated_at',   XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary',        XMLDB_KEY_PRIMARY,  ['id']);
            $table->add_index('request_type_idx', XMLDB_INDEX_UNIQUE, ['request_type']);
            $table->add_index('enabled_idx',      XMLDB_INDEX_NOTUNIQUE, ['enabled']);

            $dbman->create_table($table);

            // Seed default rows for all five request types (enabled, no date restriction).
            $now = time();
            $types = ['Enroll Next Semester', 'Class Drop', 'Late Submission', 'Change Information', 'Student Card'];
            foreach ($types as $t) {
                $DB->insert_record('local_mzi_request_windows', (object)[
                    'request_type' => $t,
                    'enabled'      => 1,
                    'start_date'   => null,
                    'end_date'     => null,
                    'message'      => '',
                    'updated_by'   => null,
                    'updated_at'   => $now,
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026022100, 'local', 'moodle_zoho_sync');
    }

    // Version 2026022200: Add local_mzi_teachers table;
    //                     add Zoho FK ID columns to local_mzi_classes;
    //                     add new Zoho fields to local_mzi_enrollments.
    if ($oldversion < 2026022200) {

        // 1. Create local_mzi_teachers table (teachers from Zoho BTEC_Teachers module).
        $table = new xmldb_table('local_mzi_teachers');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',                XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('zoho_teacher_id',   XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL);
            $table->add_field('moodle_user_id',    XMLDB_TYPE_INTEGER, '10',  null, null);
            $table->add_field('teacher_name',      XMLDB_TYPE_CHAR,    '255', null, null);
            $table->add_field('email',             XMLDB_TYPE_CHAR,    '100', null, null);
            $table->add_field('academic_email',    XMLDB_TYPE_CHAR,    '100', null, null);
            $table->add_field('phone_number',      XMLDB_TYPE_CHAR,    '30',  null, null);
            $table->add_field('created_at',        XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updated_at',        XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('synced_at',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoho_created_time', XMLDB_TYPE_CHAR,    '30',  null, null);
            $table->add_field('zoho_modified_time',XMLDB_TYPE_CHAR,    '30',  null, null);

            $table->add_key('primary',        XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('moodle_user_fk', XMLDB_KEY_FOREIGN, ['moodle_user_id'], 'user', ['id']);
            $table->add_index('zoho_teacher_id_idx', XMLDB_INDEX_UNIQUE,    ['zoho_teacher_id']);
            $table->add_index('academic_email_idx',  XMLDB_INDEX_NOTUNIQUE, ['academic_email']);
            $table->add_index('synced_at_idx',       XMLDB_INDEX_NOTUNIQUE, ['synced_at']);

            $dbman->create_table($table);
        }

        // 2. Add Zoho FK ID columns to local_mzi_classes.
        $table = new xmldb_table('local_mzi_classes');
        $class_fields = [
            ['teacher_zoho_id', XMLDB_TYPE_CHAR, '20'],
            ['unit_zoho_id',    XMLDB_TYPE_CHAR, '20'],
            ['program_zoho_id', XMLDB_TYPE_CHAR, '20'],
        ];
        foreach ($class_fields as [$fname, $ftype, $flen]) {
            $field = new xmldb_field($fname, $ftype, $flen, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // 3. Add new Zoho fields to local_mzi_enrollments.
        $table = new xmldb_table('local_mzi_enrollments');
        $enrol_fields = [
            // [name, type, len, unsigned, notnull, seq, default]
            ['zoho_student_id',  XMLDB_TYPE_CHAR,    '20',  null, null,          null, null],
            ['zoho_class_id',    XMLDB_TYPE_CHAR,    '20',  null, null,          null, null],
            ['end_date',         XMLDB_TYPE_CHAR,    '20',  null, null,          null, null],
            ['enrollment_type',  XMLDB_TYPE_CHAR,    '50',  null, null,          null, null],
            ['student_name',     XMLDB_TYPE_CHAR,    '255', null, null,          null, null],
            ['class_name',       XMLDB_TYPE_CHAR,    '255', null, null,          null, null],
            ['enrolled_program', XMLDB_TYPE_CHAR,    '255', null, null,          null, null],
            ['moodle_course_id', XMLDB_TYPE_CHAR,    '20',  null, null,          null, null],
            ['synced_to_moodle', XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0'],
        ];
        foreach ($enrol_fields as [$fname, $ftype, $flen, $unsigned, $notnull, $seq, $default]) {
            $field = new xmldb_field($fname, $ftype, $flen, $unsigned, $notnull, $seq, $default);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // 4. Add missing personal fields to local_mzi_students.
        $table = new xmldb_table('local_mzi_students');
        $student_fields = [
            ['gender',                  XMLDB_TYPE_CHAR, '20',  null, null, null, null],
            ['emergency_contact_name',  XMLDB_TYPE_CHAR, '255', null, null, null, null],
            ['emergency_contact_phone', XMLDB_TYPE_CHAR, '30',  null, null, null, null],
        ];
        foreach ($student_fields as [$fname, $ftype, $flen, $unsigned, $notnull, $seq, $default]) {
            $field = new xmldb_field($fname, $ftype, $flen, $unsigned, $notnull, $seq, $default);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // 5. Add denorm + status fields to local_mzi_grades.
        $table = new xmldb_table('local_mzi_grades');
        $grade_fields = [
            ['zoho_student_id', XMLDB_TYPE_CHAR, '20',  null, null, null, null],
            ['zoho_class_id',   XMLDB_TYPE_CHAR, '20',  null, null, null, null],
            ['unit_name',       XMLDB_TYPE_CHAR, '255', null, null, null, null],
            ['grade_status',    XMLDB_TYPE_CHAR, '20',  null, null, null, null],
        ];
        foreach ($grade_fields as [$fname, $ftype, $flen, $unsigned, $notnull, $seq, $default]) {
            $field = new xmldb_field($fname, $ftype, $flen, $unsigned, $notnull, $seq, $default);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026022200, 'local', 'moodle_zoho_sync');
    }

    // Version 2026022201: Add national_id column to local_mzi_students (National_Number from Zoho).
    if ($oldversion < 2026022201) {
        $table = new xmldb_table('local_mzi_students');
        $field = new xmldb_field('national_id', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'city');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026022201, 'local', 'moodle_zoho_sync');
    }

    // Version 2026022202: Fix 5 critical DB schema bugs; add major/sub_major to students.
    //
    // Critical fixes (DB INSERT was crashing):
    //   - Add zoho_student_id to local_mzi_registrations
    //   - Add zoho_registration_id to local_mzi_payments
    //   - Add class_short_name to local_mzi_classes
    //   - Add study_mode to local_mzi_registrations
    // High fixes (data loss / duplicate risk):
    //   - Make zoho_enrollment_id UNIQUE in local_mzi_enrollments
    //   - Make zoho_request_id UNIQUE in local_mzi_requests
    //   - Add request_date column to local_mzi_requests
    // New fields:
    //   - Add major, sub_major to local_mzi_students (Major / Sub_Major from Zoho BTEC_Students)
    if ($oldversion < 2026022202) {

        // 1. Add major + sub_major to local_mzi_students.
        $table = new xmldb_table('local_mzi_students');
        $field = new xmldb_field('major', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('sub_major', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'major');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 2. Add zoho_student_id to local_mzi_registrations (was crashing on INSERT).
        $table = new xmldb_table('local_mzi_registrations');
        $field = new xmldb_field('zoho_student_id', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'zoho_registration_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 3. Add study_mode to local_mzi_registrations.
        $field = new xmldb_field('study_mode', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'payment_plan');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 4. Add zoho_registration_id to local_mzi_payments (was crashing on INSERT).
        $table = new xmldb_table('local_mzi_payments');
        $field = new xmldb_field('zoho_registration_id', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'zoho_payment_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 5. Add class_short_name to local_mzi_classes (was crashing on INSERT).
        $table = new xmldb_table('local_mzi_classes');
        $field = new xmldb_field('class_short_name', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'class_name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 6. Add UNIQUE index on zoho_enrollment_id in local_mzi_enrollments.
        $table = new xmldb_table('local_mzi_enrollments');
        $index = new xmldb_index('zoho_enrollment_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_enrollment_id']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // 7. Fix zoho_request_id index to be UNIQUE in local_mzi_requests.
        $table = new xmldb_table('local_mzi_requests');
        // Drop the old non-unique index first.
        $old_index = new xmldb_index('zoho_request_id_idx', XMLDB_INDEX_NOTUNIQUE, ['zoho_request_id']);
        if ($dbman->index_exists($table, $old_index)) {
            $dbman->drop_index($table, $old_index);
        }
        $new_index = new xmldb_index('zoho_request_id_idx', XMLDB_INDEX_UNIQUE, ['zoho_request_id']);
        if (!$dbman->index_exists($table, $new_index)) {
            $dbman->add_index($table, $new_index);
        }

        // 8. Add request_date to local_mzi_requests.
        $field = new xmldb_field('request_date', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026022202, 'local', 'moodle_zoho_sync');
    }

    // Version 2026022203: Allow nullable class_id in grades; expand btec_grade_name to 255.
    if ($oldversion < 2026022203) {

        $table = new xmldb_table('local_mzi_grades');

        // Step 1: Drop the class_fk foreign key — DDL requires no FK dependency before changing the field.
        $class_fk = new xmldb_key('class_fk', XMLDB_KEY_FOREIGN, ['class_id'], 'local_mzi_classes', ['id']);
        if ($dbman->find_key_name($table, $class_fk)) {
            $dbman->drop_key($table, $class_fk);
        }

        // Step 2: Make class_id nullable — grades can arrive before the class is synced.
        $field = new xmldb_field('class_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'student_id');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }

        // Step 3: Re-add the FK (MySQL allows NULL in FK columns — unlinked grades simply have class_id = NULL).
        $dbman->add_key($table, $class_fk);

        // Step 4: Expand btec_grade_name 50 → 255 — must drop dependent index first.
        $btec_idx = new xmldb_index('btec_grade_name_idx', XMLDB_INDEX_NOTUNIQUE, ['btec_grade_name']);
        if ($dbman->index_exists($table, $btec_idx)) {
            $dbman->drop_index($table, $btec_idx);
        }
        $field = new xmldb_field('btec_grade_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'assignment_name');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        // Re-add the index after resize.
        if (!$dbman->index_exists($table, $btec_idx)) {
            $dbman->add_index($table, $btec_idx);
        }

        upgrade_plugin_savepoint(true, 2026022203, 'local', 'moodle_zoho_sync');
    }

    // Version 2026022500: Create local_mzi_btec_templates and local_mzi_request_windows
    // if they were not present in older installs (they exist in install.xml but not in upgrade steps).
    if ($oldversion < 2026022500) {

        // ── local_mzi_btec_templates ──────────────────────────────────────────
        $table = new xmldb_table('local_mzi_btec_templates');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('definition_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoho_unit_id',  XMLDB_TYPE_CHAR,    '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('unit_name',     XMLDB_TYPE_CHAR,   '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('synced_at',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary',             XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('zoho_unit_id_unique', XMLDB_KEY_UNIQUE,  ['zoho_unit_id']);

            $table->add_index('synced_at_idx', XMLDB_INDEX_NOTUNIQUE, ['synced_at']);

            $dbman->create_table($table);
        }

        // ── local_mzi_request_windows ─────────────────────────────────────────
        $table = new xmldb_table('local_mzi_request_windows');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER,  '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('request_type', XMLDB_TYPE_CHAR,     '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('enabled',      XMLDB_TYPE_INTEGER,   '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('start_date',   XMLDB_TYPE_INTEGER,  '10', null, null,           null, null);
            $table->add_field('end_date',     XMLDB_TYPE_INTEGER,  '10', null, null,           null, null);
            $table->add_field('message',      XMLDB_TYPE_CHAR,    '255', null, null,           null, null);
            $table->add_field('updated_by',   XMLDB_TYPE_INTEGER,  '10', null, null,           null, null);
            $table->add_field('updated_at',   XMLDB_TYPE_INTEGER,  '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('request_type_idx', XMLDB_INDEX_UNIQUE,    ['request_type']);
            $table->add_index('enabled_idx',      XMLDB_INDEX_NOTUNIQUE, ['enabled']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022500, 'local', 'moodle_zoho_sync');
    }

    // Version 2026022600: Add photo_pending_url and photo_pending_status to local_mzi_students
    // for the photo approval workflow (student uploads → pending → Zoho approves/rejects).
    if ($oldversion < 2026022600) {

        $table = new xmldb_table('local_mzi_students');

        $field_pending_url = new xmldb_field(
            'photo_pending_url', XMLDB_TYPE_CHAR, '512', null, null, null, null, 'photo_url'
        );
        if (!$dbman->field_exists($table, $field_pending_url)) {
            $dbman->add_field($table, $field_pending_url);
        }

        $field_pending_status = new xmldb_field(
            'photo_pending_status', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'photo_pending_url'
        );
        if (!$dbman->field_exists($table, $field_pending_status)) {
            $dbman->add_field($table, $field_pending_status);
        }

        upgrade_plugin_savepoint(true, 2026022600, 'local', 'moodle_zoho_sync');
    }

    return true;
}
