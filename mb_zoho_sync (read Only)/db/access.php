<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Define capabilities (permissions) for the local_mb_zoho_sync plugin.
 *
 * Each capability is an array with properties like:
 * - captype      : 'read' or 'write'
 * - contextlevel : CONTEXT_SYSTEM or CONTEXT_COURSE etc.
 * - archetypes   : Roles that are allowed or prevented by default
 */

$capabilities = [
    // صلاحية لإدارة المعلومات المالية (المدير)
    'local/mb_zoho_sync:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            // منح الصلاحية للمدير بشكل افتراضي
            'manager' => CAP_ALLOW,
        ]
    ],

    // صلاحية لعرض المعلومات المالية فقط (الطالب)
    'local/mb_zoho_sync:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            // منح الصلاحية للطالب (أو غيره حسب رغبتك)
            'student' => CAP_ALLOW,
        ]
    ],

    // إذا لديك صلاحيات أخرى، يمكنك إضافتها هنا...
];
