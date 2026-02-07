<?php
require_once(__DIR__.'/../../config.php');
require_login();
require_capability('moodle/grade:managegradingforms', context_system::instance());

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mb_zoho_sync/delete_all_btec_templates.php'));
$PAGE->set_title('Delete ALL BTEC Templates');
$PAGE->set_heading('Delete ALL BTEC Templates');

echo $OUTPUT->header();

global $DB;

echo html_writer::tag('h3', '🚨 جاري حذف جميع قوالب BTEC (بدون شروط)...');

$deleted = 0;
$errors = 0;

$defs = $DB->get_records_select('grading_definitions', "method = 'btec'");

foreach ($defs as $def) {
    try {
        $DB->delete_records('grading_definitions', ['id' => $def->id]);
        echo html_writer::div("✅ Deleted: {$def->name} (ID: {$def->id})", 'alert alert-success');
        $deleted++;
    } catch (Exception $e) {
        echo html_writer::div("❌ Error deleting: {$def->name} (ID: {$def->id}) - {$e->getMessage()}", 'alert alert-danger');
        $errors++;
    }
}

echo html_writer::empty_tag('hr');
echo html_writer::div("🔍 Total templates found: " . count($defs), 'alert alert-info');
echo html_writer::div("✅ Successfully deleted: {$deleted}", 'alert alert-success');
echo html_writer::div("⚠️ Errors: {$errors}", 'alert alert-warning');

echo $OUTPUT->footer();
