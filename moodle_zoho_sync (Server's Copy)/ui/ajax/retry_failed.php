<?php
/**
 * AJAX endpoint to retry all failed events
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use local_moodle_zoho_sync\event_logger;
use local_moodle_zoho_sync\webhook_sender;

require_login();

// CSRF protection.
$sesskey = required_param('sesskey', PARAM_ALPHANUM);
require_sesskey();

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

header('Content-Type: application/json');

try {
    $logger = new event_logger();
    $failed_events = $logger->get_failed_events();
    
    if (empty($failed_events)) {
        echo json_encode([
            'success' => true,
            'message' => 'No failed events to retry',
            'count' => 0
        ]);
        exit;
    }
    
    // Trigger retry task to run immediately
    $task = \core\task\manager::get_scheduled_task('local_moodle_zoho_sync\task\retry_failed_webhooks');
    if ($task) {
        \core\task\manager::queue_adhoc_task($task);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Retry initiated for ' . count($failed_events) . ' events',
        'count' => count($failed_events)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
