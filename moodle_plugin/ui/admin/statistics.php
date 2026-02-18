<?php
/**
 * Statistics page for Moodle-Zoho Integration Plugin
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/event_logger.php');
require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/config_manager.php');
require_once(__DIR__ . '/includes/navigation.php');

use local_moodle_zoho_sync\event_logger;
use local_moodle_zoho_sync\config_manager;

// Require login and admin capabilities.
require_login();
admin_externalpage_setup('local_moodle_zoho_sync_statistics');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// Page setup.
$PAGE->set_url('/local/moodle_zoho_sync/ui/admin/statistics.php');
$PAGE->set_context($context);
$PAGE->set_title('Sync Statistics');
$PAGE->set_heading('Sync Statistics');

// Get statistics.
$stats24h = event_logger::get_statistics(time() - (24 * 3600)); // Last 24 hours
$stats7d = event_logger::get_statistics(time() - (7 * 24 * 3600)); // Last 7 days
$statsAll = event_logger::get_statistics(); // All time

global $DB;

// Get event counts by type.
$sql = "SELECT event_type, COUNT(*) as count 
        FROM {local_mzi_event_log} 
        GROUP BY event_type 
        ORDER BY count DESC";
$eventcounts = $DB->get_records_sql($sql);

// Get hourly distribution (last 24 hours).
$sql = "SELECT 
            FROM_UNIXTIME(timecreated, '%Y-%m-%d %H:00') as hour,
            status,
            COUNT(*) as count
        FROM {local_mzi_event_log}
        WHERE timecreated > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR))
        GROUP BY hour, status
        ORDER BY hour DESC";
$hourlydata = $DB->get_records_sql($sql);

// Render page.
echo $OUTPUT->header();

// Output navigation
mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('statistics', 'Moodle-Zoho Integration', 'Detailed Analytics & Reports');
mzi_render_breadcrumb('Statistics');
echo '<div class="mzi-content-wrapper">';

echo $OUTPUT->heading('Sync Statistics');

// Time period statistics.
echo html_writer::start_div('row mb-4');

// Last 24 hours card.
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', 'Last 24 Hours', ['class' => 'card-title']);
echo html_writer::tag('p', "Total: <strong>{$stats24h['total']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Sent: <strong class='text-success'>{$stats24h['sent']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Failed: <strong class='text-danger'>{$stats24h['failed']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Pending: <strong class='text-warning'>{$stats24h['pending']}</strong>", ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Last 7 days card.
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', 'Last 7 Days', ['class' => 'card-title']);
echo html_writer::tag('p', "Total: <strong>{$stats7d['total']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Sent: <strong class='text-success'>{$stats7d['sent']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Failed: <strong class='text-danger'>{$stats7d['failed']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Pending: <strong class='text-warning'>{$stats7d['pending']}</strong>", ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// All time card.
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', 'All Time', ['class' => 'card-title']);
echo html_writer::tag('p', "Total: <strong>{$statsAll['total']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Sent: <strong class='text-success'>{$statsAll['sent']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Failed: <strong class='text-danger'>{$statsAll['failed']}</strong>", ['class' => 'mb-1']);
echo html_writer::tag('p', "Pending: <strong class='text-warning'>{$statsAll['pending']}</strong>", ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // row

// Event type distribution.
echo html_writer::tag('h4', 'Event Type Distribution', ['class' => 'mt-4 mb-3']);
if (!empty($eventcounts)) {
    $table = new html_table();
    $table->head = ['Event Type', 'Count', 'Percentage'];
    $table->attributes['class'] = 'table table-striped';
    
    $total = $statsAll['total'];
    foreach ($eventcounts as $eventcount) {
        $percentage = $total > 0 ? round(($eventcount->count / $total) * 100, 1) : 0;
        $table->data[] = [
            str_replace('_', ' ', ucwords($eventcount->event_type)),
            $eventcount->count,
            $percentage . '%'
        ];
    }
    
    echo html_writer::table($table);
} else {
    echo html_writer::div('No events recorded yet.', 'alert alert-info');
}

// Hourly distribution.
echo html_writer::tag('h4', 'Hourly Activity (Last 24 Hours)', ['class' => 'mt-4 mb-3']);
if (!empty($hourlydata)) {
    $table = new html_table();
    $table->head = ['Hour', 'Status', 'Count'];
    $table->attributes['class'] = 'table table-striped';
    
    foreach ($hourlydata as $hourly) {
        $statusclass = '';
        switch ($hourly->status) {
            case 'sent':
                $statusclass = 'text-success';
                break;
            case 'failed':
                $statusclass = 'text-danger';
                break;
            case 'pending':
                $statusclass = 'text-warning';
                break;
        }
        
        $table->data[] = [
            $hourly->hour,
            html_writer::span($hourly->status, $statusclass),
            $hourly->count
        ];
    }
    
    echo html_writer::table($table);
} else {
    echo html_writer::div('No activity in the last 24 hours.', 'alert alert-info');
}

// Configuration summary.
echo html_writer::tag('h4', 'Current Configuration', ['class' => 'mt-4 mb-3']);
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');

$config = [
    'Backend URL' => config_manager::get('backend_url'),
    'User Sync' => config_manager::get('enable_user_sync') ? 'Enabled' : 'Disabled',
    'Enrollment Sync' => config_manager::get('enable_enrollment_sync') ? 'Enabled' : 'Disabled',
    'Grade Sync' => config_manager::get('enable_grade_sync') ? 'Enabled' : 'Disabled',
    'Max Retry Attempts' => config_manager::get('max_retry_attempts', 3),
    'Log Retention (days)' => config_manager::get('log_retention_days', 30),
    'Debug Logging' => config_manager::get('enable_debug') ? 'Enabled' : 'Disabled',
];

$table = new html_table();
$table->attributes['class'] = 'table table-bordered';
foreach ($config as $key => $value) {
    $table->data[] = ["<strong>$key</strong>", $value];
}
echo html_writer::table($table);

echo html_writer::end_div();
echo html_writer::end_div();

echo '</div>'; // Close mzi-content-wrapper
echo '</div>'; // Close mzi-page-container
echo $OUTPUT->footer();
