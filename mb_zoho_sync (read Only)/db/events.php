<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_created',
        'callback'  => 'local_mb_zoho_sync\observers::user_created_handler',  // ✅ تم التصحيح هنا
        'priority'  => 9999,
        'internal'  => false,
    ],
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback'  => 'local_mb_zoho_sync\observers::submission_graded_handler',
        'priority'  => 9999,
        'internal'  => false,
    ],
];
