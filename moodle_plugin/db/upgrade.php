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

    return true;
}
