<?php
/**
 * Quick Fix: Reset Stuck Event
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to: /home/abchorizon-lms/htdocs/lms.abchorizon.com/public/local/moodle_zoho_sync/
 * 2. Visit: https://lms.abchorizon.com/local/moodle_zoho_sync/reset_event.php
 * 3. The SQL will execute automatically
 * 4. DELETE THIS FILE after execution (security)
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/moodle_zoho_sync/reset_event.php');
$PAGE->set_title('Reset Event');
$PAGE->set_heading('Reset Stuck Event');

echo $OUTPUT->header();

// Event ID to reset
$event_id = 'a12ec7d1-6a6b-43a5-bceb-245bd8afb4d6';

// Execute SQL
$sql = "UPDATE {local_mzi_event_log} 
        SET status = :status, 
            retry_count = :retrycount, 
            next_retry_at = NULL 
        WHERE event_id = :eventid";

$params = [
    'status' => 'failed',
    'retrycount' => 0,
    'eventid' => $event_id
];

try {
    $DB->execute($sql, $params);
    
    echo html_writer::div(
        html_writer::tag('h3', '✅ Success!', ['style' => 'color: green;']) .
        html_writer::tag('p', "Event <code>$event_id</code> has been reset.") .
        html_writer::tag('p', 'Status: <strong>failed</strong> (ready for manual retry)') .
        html_writer::tag('p', 'Retry Count: <strong>0</strong>') .
        html_writer::tag('p', 'Next Retry: <strong>NULL</strong>'),
        'alert alert-success'
    );
    
    echo html_writer::div(
        html_writer::tag('h4', '📋 Next Steps:') .
        html_writer::tag('ol', 
            html_writer::tag('li', 'Go to Event Logs page') .
            html_writer::tag('li', 'Find event ' . html_writer::tag('code', $event_id)) .
            html_writer::tag('li', 'Click "Retry" button') .
            html_writer::tag('li', 'Event should succeed with HTTP 200 OK')
        ),
        'alert alert-info'
    );
    
    echo html_writer::div(
        html_writer::tag('h4', '⚠️ IMPORTANT:', ['style' => 'color: red;']) .
        html_writer::tag('p', '<strong>DELETE THIS FILE NOW!</strong> (reset_event.php)') .
        html_writer::tag('p', 'This file is a security risk and should not remain on the server.'),
        'alert alert-danger'
    );
    
} catch (Exception $e) {
    echo html_writer::div(
        html_writer::tag('h3', '❌ Error', ['style' => 'color: red;']) .
        html_writer::tag('p', 'Failed to reset event.') .
        html_writer::tag('p', '<strong>Error:</strong> ' . $e->getMessage()),
        'alert alert-danger'
    );
}

echo $OUTPUT->footer();
