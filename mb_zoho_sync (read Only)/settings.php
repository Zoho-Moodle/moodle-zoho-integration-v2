<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // إضافة رابط للوحة الإدارة.
    $ADMIN->add(
        'localplugins',
        new admin_externalpage(
            'local_financeinfo_manage',
            get_string('pluginname', 'local_mb_zoho_sync'),
            new moodle_url('/local/mb_zoho_sync/manage.php'),
            'local/mb_zoho_sync:manage'
        )
    );
}
