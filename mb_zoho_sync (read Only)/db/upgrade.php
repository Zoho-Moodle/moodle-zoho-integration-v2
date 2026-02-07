<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_mb_zoho_sync
 */
function xmldb_local_mb_zoho_sync_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    /**
     * Helper: add field if missing
     */
    $ensure_field = function(xmldb_table $table, xmldb_field $field) use ($dbman) {
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    };

    /**
     * Helper: add index if missing
     */
    $ensure_index = function(xmldb_table $table, xmldb_index $index) use ($dbman) {
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    };

    // =====================================================
    //  🔹 ZOHO STUDENTS TABLE
    // =====================================================
    if ($oldversion < 2025102500) {
        $table = new xmldb_table('zoho_students');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('zoho_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('student_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('first_name', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('last_name', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('display_name', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('birth_date', XMLDB_TYPE_CHAR, '50', null, null);
            $table->add_field('academic_email', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('phone_number', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('passport_number', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('address', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('country', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('city', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('student_image', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('modified_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('last_sync', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('zoho_id_unique', XMLDB_INDEX_UNIQUE, ['zoho_id']);
            $dbman->create_table($table);
        } else {
            $ensure_field($table, new xmldb_field('passport_number', XMLDB_TYPE_CHAR, '100', null, null));
            $ensure_field($table, new xmldb_field('student_image', XMLDB_TYPE_TEXT, null, null, null));
        }

        // =====================================================
        //  🔹 ZOHO REGISTRATIONS
        // =====================================================
        $table = new xmldb_table('zoho_registrations');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('zoho_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('registration_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('student_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('program', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('sub_major', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('major', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('program_price', XMLDB_TYPE_NUMBER, '10,2', null, null);
            $table->add_field('registration_status', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('registration_date', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('remaining_amount', XMLDB_TYPE_NUMBER, '10,2', null, null);
            $table->add_field('study_language', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('study_mode', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('currency', XMLDB_TYPE_CHAR, '50', null, null);
            $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('modified_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('last_sync', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('zoho_id_unique', XMLDB_INDEX_UNIQUE, ['zoho_id']);
            $dbman->create_table($table);
        }

        // =====================================================
        //  🔹 ZOHO PAYMENTS
        // =====================================================
        $table = new xmldb_table('zoho_payments');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('zoho_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('payment_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('student_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('registration_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('payment_amount', XMLDB_TYPE_NUMBER, '10,2', null, null);
            $table->add_field('currency', XMLDB_TYPE_CHAR, '50', null, null);
            $table->add_field('payment_type', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('payment_method', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('payment_date', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('modified_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('last_sync', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('zoho_id_unique', XMLDB_INDEX_UNIQUE, ['zoho_id']);
            $dbman->create_table($table);
        }

        // =====================================================
        //  🔹 ZOHO ENROLLMENTS
        // =====================================================
        $table = new xmldb_table('zoho_enrollments');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('zoho_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('enrollment_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('student_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('class_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('class_name', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('class_teacher', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('enrolled_program', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('start_date', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('end_date', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('modified_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('last_sync', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('zoho_id_unique', XMLDB_INDEX_UNIQUE, ['zoho_id']);
            $dbman->create_table($table);
        }

        // =====================================================
        //  🔹 ZOHO GRADES
        // =====================================================
        $table = new xmldb_table('zoho_grades');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('zoho_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('student_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('class_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('grade_name', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('grade', XMLDB_TYPE_CHAR, '50', null, null);
            $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('attempt_number', XMLDB_TYPE_INTEGER, '5', null, null);
            $table->add_field('attempt_date', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('grader_name', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('iv_name', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('moodle_grade_id', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('grade_status', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('modified_time', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('last_sync', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('zoho_id_unique', XMLDB_INDEX_UNIQUE, ['zoho_id']);
            $dbman->create_table($table);
        }

        // =====================================================
        //  🔹 Save upgrade point
        // =====================================================
        upgrade_plugin_savepoint(true, 2025102500, 'local', 'mb_zoho_sync');
    }

    return true;
}
