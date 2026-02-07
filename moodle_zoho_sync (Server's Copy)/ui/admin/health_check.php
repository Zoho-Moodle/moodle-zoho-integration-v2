<?php
/**
 * Health Check page for Moodle-Zoho Integration Plugin
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/config_manager.php');
require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/event_logger.php');

use local_moodle_zoho_sync\config_manager;
use local_moodle_zoho_sync\event_logger;

// Require login and admin capabilities.
require_login();
admin_externalpage_setup('local_moodle_zoho_sync_health');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// Page setup.
$PAGE->set_url('/local/moodle_zoho_sync/ui/admin/health_check.php');
$PAGE->set_context($context);
$PAGE->set_title('System Health Check');
$PAGE->set_heading('System Health Check');

// Run health checks.
$checks = [];

// Check 1: Backend API connectivity.
$backendurl = config_manager::get('backend_url');
$backendstatus = config_manager::check_backend_health();
$checks[] = [
    'name' => 'Backend API Connection',
    'status' => $backendstatus['healthy'],
    'message' => $backendstatus['healthy'] ? 
        "Connected successfully to {$backendurl}" : 
        "Failed to connect: " . ($backendstatus['error'] ?? 'Unknown error'),
    'details' => "URL: {$backendurl}\nResponse time: " . ($backendstatus['response_time'] ?? 'N/A') . "ms"
];

// Check 2: Database tables.
global $DB;
$tables = ['local_mzi_event_log', 'local_mzi_sync_history', 'local_mzi_config'];
$dbtablesok = true;
$dbdetails = [];
foreach ($tables as $tablename) {
    $exists = $DB->get_manager()->table_exists($tablename);
    $dbdetails[] = "$tablename: " . ($exists ? 'EXISTS' : 'MISSING');
    if (!$exists) {
        $dbtablesok = false;
    }
}
$checks[] = [
    'name' => 'Database Tables',
    'status' => $dbtablesok,
    'message' => $dbtablesok ? 'All required tables exist' : 'Some tables are missing',
    'details' => implode("\n", $dbdetails)
];

// Check 3: Event statistics (last 24 hours).
$since = time() - (24 * 3600);
$stats = event_logger::get_statistics($since);
$failurerate = $stats['total'] > 0 ? ($stats['failed'] / $stats['total']) * 100 : 0;
$healthyrate = $failurerate < 10; // Less than 10% failure rate is healthy

$checks[] = [
    'name' => 'Event Processing (Last 24h)',
    'status' => $healthyrate,
    'message' => $healthyrate ? 
        "Processing events successfully (failure rate: " . round($failurerate, 1) . "%)" :
        "High failure rate: " . round($failurerate, 1) . "%",
    'details' => "Total: {$stats['total']}\nSent: {$stats['sent']}\nFailed: {$stats['failed']}\nPending: {$stats['pending']}"
];

// Check 4: Failed events needing retry.
$maxretries = config_manager::get('max_retry_attempts', 3);
$failedevents = event_logger::get_failed_events($maxretries);
$failedcount = count($failedevents);
$checks[] = [
    'name' => 'Failed Events',
    'status' => $failedcount === 0,
    'message' => $failedcount === 0 ? 
        'No failed events needing attention' : 
        "{$failedcount} events have failed and exceeded retry limit",
    'details' => "Max retry attempts: {$maxretries}\nFailed events: {$failedcount}"
];

// Check 5: Configuration.
$requiredconfigs = ['backend_url', 'enable_user_sync', 'enable_enrollment_sync', 'enable_grade_sync'];
$configok = true;
$configdetails = [];
foreach ($requiredconfigs as $configname) {
    $value = config_manager::get($configname);
    $isset = !empty($value) || $value === '0' || $value === 0;
    $configdetails[] = "$configname: " . ($isset ? 'SET' : 'NOT SET');
    if (!$isset && $configname === 'backend_url') {
        $configok = false;
    }
}
$checks[] = [
    'name' => 'Configuration',
    'status' => $configok,
    'message' => $configok ? 'All required settings configured' : 'Some required settings are missing',
    'details' => implode("\n", $configdetails)
];

// Check 6: Scheduled tasks.
$sql = "SELECT * FROM {task_scheduled} WHERE classname LIKE '%local_moodle_zoho_sync%'";
$tasks = $DB->get_records_sql($sql);
$tasksok = count($tasks) === 3; // Should have 3 tasks
$taskdetails = [];
foreach ($tasks as $task) {
    $taskdetails[] = $task->classname . ": " . ($task->disabled ? 'DISABLED' : 'ENABLED');
}
$checks[] = [
    'name' => 'Scheduled Tasks',
    'status' => $tasksok && count($tasks) > 0,
    'message' => $tasksok ? 
        'All scheduled tasks are configured' : 
        'Expected 3 scheduled tasks, found ' . count($tasks),
    'details' => !empty($taskdetails) ? implode("\n", $taskdetails) : 'No tasks found'
];

// Calculate overall health.
$healthycount = 0;
foreach ($checks as $check) {
    if ($check['status']) {
        $healthycount++;
    }
}
$overallhealth = round(($healthycount / count($checks)) * 100, 1);
$healthclass = $overallhealth >= 80 ? 'success' : ($overallhealth >= 50 ? 'warning' : 'danger');

// Render page.
echo $OUTPUT->header();
echo $OUTPUT->heading('System Health Check');

// Overall health score.
echo html_writer::start_div("alert alert-{$healthclass} mb-4");
echo html_writer::tag('h4', "Overall Health Score: {$overallhealth}%");
echo html_writer::tag('p', "{$healthycount} of " . count($checks) . " checks passed");
echo html_writer::end_div();

// Individual checks.
foreach ($checks as $check) {
    $statusclass = $check['status'] ? 'success' : 'danger';
    $statusicon = $check['status'] ? '✓' : '✗';
    
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');
    
    echo html_writer::tag('h5', 
        html_writer::span($statusicon, "badge badge-{$statusclass} mr-2") . 
        $check['name'], 
        ['class' => 'card-title']
    );
    
    echo html_writer::tag('p', $check['message'], ['class' => 'mb-2']);
    
    if (!empty($check['details'])) {
        echo html_writer::tag('pre', 
            $check['details'], 
            ['class' => 'bg-light p-2 mb-0', 'style' => 'font-size: 0.875rem;']
        );
    }
    
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Action buttons.
echo html_writer::start_div('mt-4');
echo html_writer::link(
    new moodle_url('/local/moodle_zoho_sync/ui/admin/event_logs.php'),
    'View Event Logs',
    ['class' => 'btn btn-primary mr-2']
);
echo html_writer::link(
    new moodle_url('/local/moodle_zoho_sync/ui/admin/statistics.php'),
    'View Statistics',
    ['class' => 'btn btn-secondary mr-2']
);
echo html_writer::link(
    new moodle_url('/admin/settings.php?section=local_moodle_zoho_sync'),
    'Plugin Settings',
    ['class' => 'btn btn-info']
);
echo html_writer::end_div();

echo $OUTPUT->footer();
