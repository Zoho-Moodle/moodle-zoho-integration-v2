<?php
/**
 * Manual Retry - Send Webhook Directly
 * 
 * INSTRUCTIONS:
 * 1. Upload to: /home/abchorizon-lms/htdocs/lms.abchorizon.com/public/local/moodle_zoho_sync/
 * 2. Visit: https://lms.abchorizon.com/local/moodle_zoho_sync/manual_retry.php
 * 3. DELETE after success
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/webhook_sender.php');
require_once(__DIR__ . '/classes/event_logger.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/moodle_zoho_sync/manual_retry.php');
$PAGE->set_title('Manual Retry');
$PAGE->set_heading('Manual Webhook Retry');

echo $OUTPUT->header();

$event_id = 'a12ec7d1-6a6b-43a5-bceb-245bd8afb4d6';

// Get event from database
$event = $DB->get_record('local_mzi_event_log', ['event_id' => $event_id]);

if (!$event) {
    echo html_writer::div('❌ Event not found: ' . $event_id, 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(
    html_writer::tag('h4', '📦 Event Found:') .
    html_writer::tag('p', '<strong>Event ID:</strong> ' . $event->event_id) .
    html_writer::tag('p', '<strong>Event Type:</strong> ' . $event->event_type) .
    html_writer::tag('p', '<strong>Status:</strong> ' . $event->status) .
    html_writer::tag('p', '<strong>Retry Count:</strong> ' . $event->retry_count) .
    html_writer::tag('p', '<strong>HTTP Status:</strong> ' . $event->http_status),
    'alert alert-info'
);

// Reset for retry
$DB->execute(
    "UPDATE {local_mzi_event_log} SET status = ?, retry_count = 0, next_retry_at = NULL WHERE event_id = ?",
    ['failed', $event_id]
);

echo html_writer::div('✅ Event reset to failed status (retry_count = 0)', 'alert alert-success');

// Parse event data
$event_data = json_decode($event->event_data, true);

if (!$event_data) {
    echo html_writer::div('❌ Failed to parse event data', 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(
    html_writer::tag('h4', '📤 Sending Webhook...') .
    html_writer::tag('p', '<strong>Backend URL:</strong> ' . get_config('local_moodle_zoho_sync', 'backend_url')) .
    html_writer::tag('p', '<strong>Endpoint:</strong> /api/v1/webhooks') .
    html_writer::tag('p', '<strong>Event Type:</strong> ' . $event->event_type),
    'alert alert-info'
);

// Create webhook sender
$sender = new \local_moodle_zoho_sync\webhook_sender();

// Build payload manually (same as webhook_sender does)
$payload = [
    'event_id' => $event->event_id,
    'event_type' => $event->event_type,
    'event_data' => $event_data,
    'moodle_event_id' => $event->moodle_event_id,
    'timestamp' => time()
];

// Get backend URL and token
$backend_url = rtrim(get_config('local_moodle_zoho_sync', 'backend_url'), '/') . '/api/v1/webhooks';
$api_token = get_config('local_moodle_zoho_sync', 'api_token');
$ssl_verify = get_config('local_moodle_zoho_sync', 'ssl_verify');

// Send with cURL
$ch = curl_init($backend_url);

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
];

if (!empty($api_token)) {
    $headers[] = 'Authorization: Bearer ' . $api_token;
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => $ssl_verify ? true : false,
    CURLOPT_SSL_VERIFYHOST => $ssl_verify ? 2 : 0,
]);

$response_body = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Show results
echo html_writer::start_div('alert ' . ($status_code >= 200 && $status_code < 300 ? 'alert-success' : 'alert-danger'));
echo html_writer::tag('h4', $status_code >= 200 && $status_code < 300 ? '✅ Success!' : '❌ Failed');
echo html_writer::tag('p', '<strong>HTTP Status:</strong> ' . $status_code);

if ($curl_error) {
    echo html_writer::tag('p', '<strong>cURL Error:</strong> ' . htmlspecialchars($curl_error));
}

if ($response_body) {
    echo html_writer::tag('p', '<strong>Response:</strong>');
    echo '<pre style="background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;">' . 
         htmlspecialchars($response_body) . '</pre>';
}

echo html_writer::end_div();

// Update database
if ($status_code >= 200 && $status_code < 300) {
    \local_moodle_zoho_sync\event_logger::update_event_status($event_id, 'sent', $status_code);
    echo html_writer::div('✅ Event status updated to: sent', 'alert alert-success');
} else {
    $error_msg = $curl_error ?: 'HTTP ' . $status_code;
    \local_moodle_zoho_sync\event_logger::update_event_status($event_id, 'failed', $status_code, $error_msg);
    echo html_writer::div('❌ Event status updated to: failed', 'alert alert-danger');
}

// Show payload sent
echo html_writer::div(
    html_writer::tag('h4', '📦 Payload Sent:') .
    '<pre style="background: #f5f5f5; padding: 10px; max-height: 400px; overflow: auto;">' .
    htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT)) . '</pre>',
    'alert alert-info'
);

echo html_writer::div(
    html_writer::tag('h4', '⚠️ Security:', ['style' => 'color: red;']) .
    html_writer::tag('p', '<strong>DELETE THIS FILE NOW!</strong> (manual_retry.php)'),
    'alert alert-danger'
);

echo $OUTPUT->footer();
