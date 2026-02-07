<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/mb_zoho_sync/classes/observers.php');

use local_mb_zoho_sync\observers;

// ✅ حدد ID المستخدم الذي تريد اختباره
$test_userid = 8067; // ← بدّلها إلى ID مستخدم حقيقي في Moodle

// ✅ جهّز الحدث يدويًا
$event = \core\event\user_created::create([
    'objectid' => $test_userid,
    'relateduserid' => $test_userid,
    'context' => \context_system::instance(),
    'other' => []
]);

// ✅ نفذ الـ observer
observers::user_created($event);

echo "<hr>✅ Manual trigger complete for user ID: $test_userid";
