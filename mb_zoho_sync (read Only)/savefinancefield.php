<?php
require('../../config.php');
require_login();
require_capability('local/mb_zoho_sync:manage', context_system::instance());

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));

$userid = $data->userid ?? null;
$field = $data->field ?? null;
$value = $data->value ?? null;

if (!$userid || !$field) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

// تعديل سجل financeinfo
if (in_array($field, [
    'scholarship', 'scholarship_reason', 'scholarship_percentage', 'currency',
    'amount_transferred', 'payment_method', 'payment_mode', 'bank_name',
    'bank_holder', 'registration_fees', 'invoice_reg_fees',
    'total_amount', 'discount_amount', 'zoho_id'
])) {
    $finance = $DB->get_record('financeinfo', ['userid' => $userid], '*', IGNORE_MISSING);
    if ($finance) {
        $finance->$field = $value;
        $DB->update_record('financeinfo', $finance);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// تعديل سجل دفع محدد
if (in_array($field, ['payment_name', 'amount', 'payment_date', 'invoice_number', 'notes'])) {
    $id = $data->id ?? null;
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing payment ID']);
        exit;
    }

    $payment = $DB->get_record('financeinfo_payments', ['id' => $id], '*', IGNORE_MISSING);
    if ($payment) {
        if ($field == 'payment_date') {
            $payment->$field = strtotime($value);
        } else {
            $payment->$field = $value;
        }
        $DB->update_record('financeinfo_payments', $payment);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Field not allowed']);
