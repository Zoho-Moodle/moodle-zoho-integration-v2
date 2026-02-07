<?php
/**
 * Show actual webhook_sender.php content
 * 
 * INSTRUCTIONS:
 * 1. Upload to: /home/abchorizon-lms/htdocs/lms.abchorizon.com/public/local/moodle_zoho_sync/
 * 2. Visit: https://lms.abchorizon.com/local/moodle_zoho_sync/show_webhook_content.php
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/moodle_zoho_sync/show_webhook_content.php');
$PAGE->set_title('Show Webhook Content');
$PAGE->set_heading('Webhook File Content');

echo $OUTPUT->header();

$file_path = __DIR__ . '/classes/webhook_sender.php';

if (!file_exists($file_path)) {
    echo html_writer::div('❌ File not found: ' . $file_path, 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

$content = file_get_contents($file_path);
$lines = explode("\n", $content);

echo html_writer::tag('h3', '📄 Actual File Content (Lines 38-105)');

echo html_writer::start_div('alert alert-info');
echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">';

for ($i = 37; $i <= 104 && $i < count($lines); $i++) {
    $line_num = $i + 1;
    $line_content = htmlspecialchars($lines[$i]);
    
    // Highlight problem lines
    $style = '';
    if (in_array($line_num, [42, 53, 64, 75, 98, 99, 100, 101])) {
        $style = 'background: #ffeb3b; font-weight: bold;';
    }
    
    echo sprintf('<span style="%s">%03d: %s</span>', $style, $line_num, $line_content) . "\n";
}

echo '</pre>';
echo html_writer::end_div();

echo html_writer::div(
    html_writer::tag('h4', '📊 File Info:') .
    html_writer::tag('p', '<strong>Path:</strong> ' . $file_path) .
    html_writer::tag('p', '<strong>Size:</strong> ' . filesize($file_path) . ' bytes') .
    html_writer::tag('p', '<strong>Modified:</strong> ' . date('Y-m-d H:i:s', filemtime($file_path))) .
    html_writer::tag('p', '<strong>Permissions:</strong> ' . substr(sprintf('%o', fileperms($file_path)), -4)) .
    html_writer::tag('p', '<strong>Total Lines:</strong> ' . count($lines)),
    'alert alert-info'
);

echo $OUTPUT->footer();
