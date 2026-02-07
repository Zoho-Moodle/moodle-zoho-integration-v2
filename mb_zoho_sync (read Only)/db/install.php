<?php

function xmldb_local_mb_zoho_sync_install() {
    global $DB;

    $dbman = $DB->get_manager();

    // ✅ جدول financeinfo
    $financeinfo = new xmldb_table('financeinfo');

    $financeinfo->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $financeinfo->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $financeinfo->add_field('scholarship', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $financeinfo->add_field('scholarship_reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $financeinfo->add_field('scholarship_percentage', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
    $financeinfo->add_field('currency', XMLDB_TYPE_CHAR, '50', null, null, null, null);
    $financeinfo->add_field('amount_transferred', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $financeinfo->add_field('payment_method', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $financeinfo->add_field('payment_mode', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $financeinfo->add_field('bank_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $financeinfo->add_field('bank_holder', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $financeinfo->add_field('registration_fees', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
    $financeinfo->add_field('invoice_reg_fees', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $financeinfo->add_field('total_amount', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
    $financeinfo->add_field('discount_amount', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
    $financeinfo->add_field('zoho_id', XMLDB_TYPE_CHAR, '255', null, null, null, null);

    $financeinfo->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $financeinfo->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

    if (!$dbman->table_exists($financeinfo)) {
        $dbman->create_table($financeinfo);
    }

    // ✅ جدول financeinfo_payments
    $payments = new xmldb_table('financeinfo_payments');

    $payments->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $payments->add_field('financeinfoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $payments->add_field('payment_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $payments->add_field('amount', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
    $payments->add_field('payment_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $payments->add_field('invoice_number', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $payments->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);

    $payments->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $payments->add_key('financeinfoid', XMLDB_KEY_FOREIGN, ['financeinfoid'], 'financeinfo', ['id']);

    if (!$dbman->table_exists($payments)) {
        $dbman->create_table($payments);
    }

    // ✅ جدول sync_sharepoint
    $sync = new xmldb_table('sync_sharepoint');

    $sync->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $sync->add_field('course_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $sync->add_field('team_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $sync->add_field('object_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $sync->add_field('status', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
    $sync->add_field('sharepoint_link', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

    $sync->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

    if (!$dbman->table_exists($sync)) {
        $dbman->create_table($sync);
    }

    return true;
}
