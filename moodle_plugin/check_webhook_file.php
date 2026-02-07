<?php
/**
 * Check webhook_sender.php content on server
 * 
 * INSTRUCTIONS:
 * 1. Upload to: /home/abchorizon-lms/htdocs/lms.abchorizon.com/public/local/moodle_zoho_sync/
 * 2. Visit: https://lms.abchorizon.com/local/moodle_zoho_sync/check_webhook_file.php
 * 3. DELETE after checking (security)
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/moodle_zoho_sync/check_webhook_file.php');
$PAGE->set_title('Check Webhook File');
$PAGE->set_heading('Webhook File Inspector');

echo $OUTPUT->header();

$file_path = __DIR__ . '/classes/webhook_sender.php';

if (!file_exists($file_path)) {
    echo html_writer::div('❌ File not found: ' . $file_path, 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

$content = file_get_contents($file_path);
$lines = explode("\n", $content);

echo html_writer::tag('h3', '🔍 Webhook Sender File Check');

// Check critical lines
$checks = [
    [
        'name' => 'Line 42: send_user_created endpoint',
        'line_num' => 42,
        'expected' => "return \$this->send_webhook_with_logging('/api/v1/webhooks', 'user_created', \$user_data, \$moodleeventid);",
        'contains' => '/api/v1/webhooks'
    ],
    [
        'name' => 'Line 53: send_user_updated endpoint',
        'line_num' => 53,
        'expected' => "return \$this->send_webhook_with_logging('/api/v1/webhooks', 'user_updated', \$user_data, \$moodleeventid);",
        'contains' => '/api/v1/webhooks'
    ],
    [
        'name' => 'Line 64: send_enrollment_created endpoint',
        'line_num' => 64,
        'expected' => "return \$this->send_webhook_with_logging('/api/v1/webhooks', 'enrollment_created', \$enrollment_data, \$moodleeventid);",
        'contains' => '/api/v1/webhooks'
    ],
    [
        'name' => 'Line 75: send_grade_updated endpoint',
        'line_num' => 75,
        'expected' => "return \$this->send_webhook_with_logging('/api/v1/webhooks', 'grade_updated', \$grade_data, \$moodleeventid);",
        'contains' => '/api/v1/webhooks'
    ],
    [
        'name' => 'Line 98: Payload structure',
        'line_num' => 98,
        'expected' => "\$payload = [",
        'contains' => '$payload'
    ],
    [
        'name' => 'Line 99: event_id field',
        'line_num' => 99,
        'expected' => "'event_id' => \$eventid,",
        'contains' => 'event_id'
    ],
    [
        'name' => 'Line 100: event_type field',
        'line_num' => 100,
        'expected' => "'event_type' => \$eventtype,",
        'contains' => 'event_type'
    ],
    [
        'name' => 'Line 101: event_data field',
        'line_num' => 101,
        'expected' => "'event_data' => \$data,",
        'contains' => 'event_data'
    ]
];

$all_passed = true;

foreach ($checks as $check) {
    $line_index = $check['line_num'] - 1;
    $actual_line = isset($lines[$line_index]) ? trim($lines[$line_index]) : 'LINE NOT FOUND';
    $expected_line = trim($check['expected']);
    
    $passed = (strpos($actual_line, $check['contains']) !== false);
    
    if ($passed) {
        echo html_writer::div(
            html_writer::tag('strong', '✅ ' . $check['name'], ['style' => 'color: green;']) . '<br>' .
            html_writer::tag('code', htmlspecialchars($actual_line)),
            'alert alert-success'
        );
    } else {
        $all_passed = false;
        echo html_writer::div(
            html_writer::tag('strong', '❌ ' . $check['name'], ['style' => 'color: red;']) . '<br>' .
            html_writer::tag('small', 'Expected contains: ' . htmlspecialchars($check['contains'])) . '<br>' .
            html_writer::tag('code', 'Actual: ' . htmlspecialchars($actual_line), ['style' => 'color: red;']),
            'alert alert-danger'
        );
    }
}

// Show file modification time
$mod_time = filemtime($file_path);
echo html_writer::div(
    html_writer::tag('h4', '📅 File Information:') .
    html_writer::tag('p', '<strong>Last Modified:</strong> ' . date('Y-m-d H:i:s', $mod_time)) .
    html_writer::tag('p', '<strong>File Size:</strong> ' . filesize($file_path) . ' bytes') .
    html_writer::tag('p', '<strong>Total Lines:</strong> ' . count($lines)),
    'alert alert-info'
);

// Final verdict
if ($all_passed) {
    echo html_writer::div(
        html_writer::tag('h3', '✅ All Checks Passed!', ['style' => 'color: green;']) .
        html_writer::tag('p', 'The webhook_sender.php file is correctly updated.') .
        html_writer::tag('p', '<strong>Next Step:</strong> Purge cache and retry the event.'),
        'alert alert-success'
    );
} else {
    echo html_writer::div(
        html_writer::tag('h3', '❌ File Needs Update!', ['style' => 'color: red;']) .
        html_writer::tag('p', 'The webhook_sender.php file is NOT updated correctly.') .
        html_writer::tag('p', '<strong>Action Required:</strong> Upload the correct webhook_sender.php file.'),
        'alert alert-danger'
    );
}

echo html_writer::div(
    html_writer::tag('h4', '⚠️ Security Warning:', ['style' => 'color: red;']) .
    html_writer::tag('p', '<strong>DELETE THIS FILE NOW!</strong> (check_webhook_file.php)'),
    'alert alert-warning'
);

echo $OUTPUT->footer();
