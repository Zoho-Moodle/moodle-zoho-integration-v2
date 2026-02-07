<?php
$functions = [
    'local_mb_zoho_sync_create_rubric' => [
        'classname'   => 'local_mb_zoho_sync\\external',
        'methodname'  => 'create_rubric',
        'classpath'   => 'local/mb_zoho_sync/classes/external.php',
        'description' => 'Create BTEC rubric based on unit data',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
$services = [
    'Zoho Sync service' => [
        'functions' => ['local_mb_zoho_sync_create_rubric'],
        'restrictedusers' => 0,
        'enabled'=>1,
    ],
];
