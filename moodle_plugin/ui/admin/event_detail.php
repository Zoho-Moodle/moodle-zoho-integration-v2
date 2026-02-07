<?php
/**
 * Event Detail page for Moodle-Zoho Integration Plugin
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require login and admin capabilities.
require_login();
admin_externalpage_setup('local_moodle_zoho_sync_logs');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// Page parameters.
$eventid = required_param('id', PARAM_INT);

// Page setup.
$PAGE->set_url('/local/moodle_zoho_sync/ui/admin/event_detail.php', ['id' => $eventid]);
$PAGE->set_context($context);
$PAGE->set_title('Event Details');
$PAGE->set_heading('Event Details');

// Get event from database.
global $DB;
$event = $DB->get_record('local_mzi_event_log', ['id' => $eventid], '*', MUST_EXIST);

// Render page.
echo $OUTPUT->header();
echo $OUTPUT->heading('Event Details: ' . substr($event->event_id, 0, 8));

// Back button.
echo html_writer::start_div('mb-3');
echo html_writer::link(
    new moodle_url('/local/moodle_zoho_sync/ui/admin/event_logs.php'),
    '← Back to Event Logs',
    ['class' => 'btn btn-secondary']
);

// Retry button (for testing: shows for all events, normally only for failed)
// TODO: Change condition to ($event->status === 'failed') for production
if (true) { // Temporarily show for all events for testing
    echo ' '; // Space between buttons
    $btn_class = $event->status === 'failed' ? 'btn-warning' : 'btn-info';
    $btn_text = $event->status === 'failed' ? '🔄 Retry Event' : '🧪 Test Retry (Dev Mode)';
    echo html_writer::tag('button', 
        $btn_text,
        [
            'class' => 'btn ' . $btn_class,
            'id' => 'retry-event-btn',
            'data-event-id' => $event->event_id,
            'onclick' => 'retryEvent()'
        ]
    );
}
echo html_writer::end_div();

// Event details card.
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');

// Basic info.
echo html_writer::tag('h5', 'Basic Information', ['class' => 'card-title']);

// Determine display type - show action for grade events if available
$display_type = $event->event_type;
if (!empty($event->action) && $event->event_type === 'grade_updated') {
    $display_type = 'grade_' . $event->action; // grade_created or grade_updated
}

$table = new html_table();
$table->attributes['class'] = 'table table-bordered';
$table->data = [
    ['<strong>Event ID</strong>', $event->event_id],
    ['<strong>Event Type</strong>', $display_type],
    ['<strong>Status</strong>', $event->status],
    ['<strong>Retry Count</strong>', $event->retry_count],
    ['<strong>HTTP Status</strong>', $event->http_status ?? 'N/A'],
    ['<strong>Moodle Event ID</strong>', $event->moodle_event_id ?? 'N/A'],
    ['<strong>Created</strong>', userdate($event->timecreated, '%Y-%m-%d %H:%M:%S')],
    ['<strong>Modified</strong>', userdate($event->timemodified, '%Y-%m-%d %H:%M:%S')],
    ['<strong>Processed</strong>', $event->timeprocessed ? userdate($event->timeprocessed, '%Y-%m-%d %H:%M:%S') : 'Not yet'],
];

// Add action row if available
if (!empty($event->action)) {
    // Insert action after event type
    array_splice($table->data, 2, 0, [['<strong>Action</strong>', $event->action]]);
}

echo html_writer::table($table);

// Event data.
if ($event->event_data) {
    echo html_writer::tag('h5', 'Event Data (JSON)', ['class' => 'card-title mt-4']);
    $eventdata = json_decode($event->event_data, true);
    if ($eventdata) {
        echo html_writer::tag('pre', json_encode($eventdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 
            ['class' => 'bg-light p-3', 'style' => 'max-height: 400px; overflow-y: auto;']);
    } else {
        echo html_writer::div('Invalid JSON data', 'alert alert-warning');
    }
}

// Error message.
if ($event->last_error) {
    echo html_writer::tag('h5', 'Last Error Message', ['class' => 'card-title mt-4']);
    echo html_writer::div($event->last_error, 'alert alert-danger');
}

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// JavaScript for retry functionality
?>
<script>
function retryEvent() {
    const btn = document.getElementById('retry-event-btn');
    const eventId = btn.getAttribute('data-event-id');
    
    if (!confirm('Are you sure you want to retry this event?')) {
        return;
    }
    
    // Disable button
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Retrying...';
    
    // Send AJAX request
    fetch(M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax/retry_single_event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'sesskey=' + M.cfg.sesskey + '&event_id=' + encodeURIComponent(eventId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Success!\n\nEvent sent successfully.\nHTTP Status: ' + data.http_status + '\n\nThe page will reload to show updated status.');
            location.reload();
        } else {
            alert('❌ Failed!\n\n' + data.message + '\n\nHTTP Status: ' + (data.http_status || 'N/A'));
            btn.disabled = false;
            btn.innerHTML = '🔄 Retry Event';
        }
    })
    .catch(error => {
        alert('❌ Error: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = '🔄 Retry Event';
    });
}
</script>
<?php

echo $OUTPUT->footer();
