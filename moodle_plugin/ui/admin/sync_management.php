<?php
/**
 * Sync Management page for Moodle-Zoho Integration Plugin
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/event_logger.php');
require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/config_manager.php');
require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/webhook_sender.php');

use local_moodle_zoho_sync\event_logger;
use local_moodle_zoho_sync\config_manager;
use local_moodle_zoho_sync\webhook_sender;

// Require login and admin capabilities.
require_login();
admin_externalpage_setup('local_moodle_zoho_sync_management');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// Handle actions.
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'retry_failed':
            if ($confirm) {
                // Retry failed events.
                $maxretries = config_manager::get('max_retry_attempts', 3);
                $failedevents = event_logger::get_failed_events($maxretries);
                
                $sender = new webhook_sender();
                $retried = 0;
                
                foreach ($failedevents as $event) {
                    try {
                        event_logger::update_event_status($event->event_id, 'retrying');
                        $retried++;
                    } catch (Exception $e) {
                        // Continue with other events
                    }
                }
                
                redirect(
                    new moodle_url('/local/moodle_zoho_sync/ui/admin/sync_management.php'),
                    "Queued {$retried} failed events for retry",
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            break;
            
        case 'cleanup_old':
            if ($confirm) {
                $retentiondays = config_manager::get('log_retention_days', 30);
                $deleted = event_logger::cleanup_old_logs($retentiondays);
                
                redirect(
                    new moodle_url('/local/moodle_zoho_sync/ui/admin/sync_management.php'),
                    "Deleted {$deleted} old event log records",
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            break;
    }
}

// Page setup.
$PAGE->set_url('/local/moodle_zoho_sync/ui/admin/sync_management.php');
$PAGE->set_context($context);
$PAGE->set_title('Sync Management');
$PAGE->set_heading('Sync Management');

// Get statistics.
$stats = event_logger::get_statistics();
$maxretries = config_manager::get('max_retry_attempts', 3);
$failedevents = event_logger::get_failed_events($maxretries);

// Render page.
echo $OUTPUT->header();
echo $OUTPUT->heading('Sync Management');

// Statistics overview.
echo html_writer::start_div('row mb-4');

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h1', $stats['total'], ['class' => 'text-primary']);
echo html_writer::tag('p', 'Total Events', ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h1', $stats['sent'], ['class' => 'text-success']);
echo html_writer::tag('p', 'Sent Successfully', ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h1', $stats['failed'], ['class' => 'text-danger']);
echo html_writer::tag('p', 'Failed', ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h1', $stats['pending'], ['class' => 'text-warning']);
echo html_writer::tag('p', 'Pending', ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // row

// Actions section.
echo html_writer::tag('h4', 'Management Actions', ['class' => 'mb-3']);

// Retry failed events.
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', 'Retry Failed Events', ['class' => 'card-title']);
echo html_writer::tag('p', 
    'Retry all events that have failed and not exceeded the maximum retry limit. ' .
    'Currently ' . count($failedevents) . ' failed events need attention.'
);

if (count($failedevents) > 0) {
    $retryurl = new moodle_url('/local/moodle_zoho_sync/ui/admin/sync_management.php', [
        'action' => 'retry_failed',
        'confirm' => 1,
        'sesskey' => sesskey()
    ]);
    echo html_writer::link($retryurl, 'Retry Failed Events', [
        'class' => 'btn btn-warning',
        'onclick' => "return confirm('Are you sure you want to retry " . count($failedevents) . " failed events?');"
    ]);
} else {
    echo html_writer::tag('button', 'No Failed Events', [
        'class' => 'btn btn-success',
        'disabled' => 'disabled'
    ]);
}
echo html_writer::end_div();
echo html_writer::end_div();

// Cleanup old logs.
$retentiondays = config_manager::get('log_retention_days', 30);
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', 'Cleanup Old Logs', ['class' => 'card-title']);
echo html_writer::tag('p', 
    "Delete event logs older than {$retentiondays} days. " .
    "This helps maintain database performance and reduce storage usage."
);

$cleanupurl = new moodle_url('/local/moodle_zoho_sync/ui/admin/sync_management.php', [
    'action' => 'cleanup_old',
    'confirm' => 1,
    'sesskey' => sesskey()
]);
echo html_writer::link($cleanupurl, 'Cleanup Old Logs', [
    'class' => 'btn btn-danger',
    'onclick' => "return confirm('Are you sure you want to delete logs older than {$retentiondays} days?');"
]);
echo html_writer::end_div();
echo html_writer::end_div();

// Quick links.
echo html_writer::tag('h4', 'Quick Links', ['class' => 'mb-3']);
echo html_writer::start_div('list-group');

$links = [
    ['url' => '/local/moodle_zoho_sync/ui/admin/event_logs.php', 'title' => 'View Event Logs', 'icon' => '📋'],
    ['url' => '/local/moodle_zoho_sync/ui/admin/statistics.php', 'title' => 'View Statistics', 'icon' => '📊'],
    ['url' => '/local/moodle_zoho_sync/ui/admin/health_check.php', 'title' => 'System Health Check', 'icon' => '🩺'],
    ['url' => '/admin/settings.php?section=local_moodle_zoho_sync', 'title' => 'Plugin Settings', 'icon' => '⚙️'],
    ['url' => '/admin/tasklogs.php', 'title' => 'Scheduled Task Logs', 'icon' => '⏰'],
];

foreach ($links as $link) {
    echo html_writer::link(
        new moodle_url($link['url']),
        $link['icon'] . ' ' . $link['title'],
        ['class' => 'list-group-item list-group-item-action']
    );
}

echo html_writer::end_div();

echo $OUTPUT->footer();
