<?php
require('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/mb_zoho_sync:manage', $context);

header('Content-Type: application/json');

$paymentid = required_param('id', PARAM_INT);

if ($DB->record_exists('financeinfo_payments', ['id' => $paymentid])) {
    $DB->delete_records('financeinfo_payments', ['id' => $paymentid]);
    echo json_encode(['status' => 'success', 'message' => 'Payment deleted successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Payment record not found.']);
} 
