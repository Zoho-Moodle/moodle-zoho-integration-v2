<?php
/**
 * AJAX endpoint to test backend connection
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use local_moodle_zoho_sync\config_manager;

require_login();

// CSRF protection.
$sesskey = required_param('sesskey', PARAM_ALPHANUM);
require_sesskey();

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

header('Content-Type: application/json');

try {
    $result = config_manager::test_backend_connection();
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
