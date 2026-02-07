<?php
/**
 * AJAX endpoint to retry a single event
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../classes/webhook_sender.php');
require_once(__DIR__ . '/../../classes/event_logger.php');

use local_moodle_zoho_sync\event_logger;
use local_moodle_zoho_sync\webhook_sender;

require_login();

// CSRF protection
$sesskey = required_param('sesskey', PARAM_ALPHANUM);
require_sesskey();

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

header('Content-Type: application/json');

try {
    $event_id = required_param('event_id', PARAM_TEXT);
    
    // Get event from database
    global $DB;
    $event = $DB->get_record('local_mzi_event_log', ['event_id' => $event_id]);
    
    if (!$event) {
        echo json_encode([
            'success' => false,
            'message' => 'Event not found'
        ]);
        exit;
    }
    
    // Reset retry count and status
    $DB->execute(
        "UPDATE {local_mzi_event_log} SET status = ?, retry_count = 0, next_retry_at = NULL WHERE event_id = ?",
        ['failed', $event_id]
    );
    
    // Parse event data
    $event_data = json_decode($event->event_data, true);
    
    if (!$event_data) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid event data'
        ]);
        exit;
    }
    
    // Send webhook
    $sender = new webhook_sender();
    $backend_url = rtrim(get_config('local_moodle_zoho_sync', 'backend_url'), '/') . '/api/v1/webhooks';
    $api_token = get_config('local_moodle_zoho_sync', 'api_token');
    $ssl_verify = get_config('local_moodle_zoho_sync', 'ssl_verify');
    
    // Build payload
    $payload = [
        'event_id' => $event->event_id,
        'event_type' => $event->event_type,
        'event_data' => $event_data,
        'moodle_event_id' => $event->moodle_event_id,
        'timestamp' => time()
    ];
    
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
    
    // Update event status
    if ($status_code >= 200 && $status_code < 300) {
        event_logger::update_event_status($event_id, 'sent', $status_code);
        echo json_encode([
            'success' => true,
            'message' => 'Event sent successfully',
            'http_status' => $status_code,
            'response' => $response_body
        ]);
    } else {
        $error_msg = $curl_error ?: 'HTTP ' . $status_code;
        event_logger::update_event_status($event_id, 'failed', $status_code, $error_msg);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send event: ' . $error_msg,
            'http_status' => $status_code,
            'response' => $response_body
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
