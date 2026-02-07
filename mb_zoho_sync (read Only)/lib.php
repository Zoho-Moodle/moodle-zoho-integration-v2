<?php
/**
 * File: local/mb_zoho_sync/lib.php
 */

// ✅ هذه الدالة لإضافة رابط إدارة المالية إلى قائمة التنقل
function local_mb_zoho_sync_extend_navigation(global_navigation $navigation) {
    global $PAGE;

    if (has_capability('local/mb_zoho_sync:manage', context_system::instance())) {
        $navigation->add(
            'Financial Manage',
            new moodle_url('/local/mb_zoho_sync/manage.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'finance_manage_node'
        );
    }
}


