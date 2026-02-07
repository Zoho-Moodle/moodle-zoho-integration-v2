<?php
require('../../config.php');
require_login();

global $DB, $USER;

$context = context_system::instance();
$userid = optional_param('id', 0, PARAM_INT); // إذا تم تمرير ID لطالب معين

// التأكد من الصلاحيات
if ($userid && $userid != $USER->id) {
    require_capability('local/mb_zoho_sync:manage', $context);
    $user = core_user::get_user($userid, '*', MUST_EXIST);
} else {
    require_capability('local/mb_zoho_sync:view', $context);
    $user = $USER;
    $userid = $USER->id;
}

$PAGE->set_url(new moodle_url('/local/mb_zoho_sync/index.php', ['id' => $userid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('financeinfo', 'local_mb_zoho_sync'));
$PAGE->set_heading(fullname($user));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('financeinfo', 'local_mb_zoho_sync') . ': ' . fullname($user));

// استعلام البيانات المالية
$records = $DB->get_records('financeinfo', ['userid' => $userid]);

if ($records) {
    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Amount');
    echo html_writer::tag('th', 'Status');
    echo html_writer::tag('th', 'Payment Date');
    echo html_writer::tag('th', 'Description');
    echo html_writer::end_tag('tr');

    foreach ($records as $record) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', format_float($record->amount, 2));
        echo html_writer::tag('td', ucfirst($record->status));
        echo html_writer::tag('td', $record->payment_date);
        echo html_writer::tag('td', $record->description);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('table');
} else {
    echo $OUTPUT->notification(get_string('nofinancialdata', 'local_financeinfo'), 'notifymessage');
}

echo $OUTPUT->footer();
