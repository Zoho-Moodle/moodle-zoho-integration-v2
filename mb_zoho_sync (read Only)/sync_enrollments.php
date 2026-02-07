<?php
require_once(__DIR__ . '/../../config.php');
require_login();
global $DB, $CFG;

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

$logfile = __DIR__ . '/sync_enrollments_log.txt';

/**
 * 🔹 وظيفة مساعدة لعرض السطر في CLI والمتصفح وتسجيله بنفس الوقت
 */
function log_line($message, $logfile) {
    $timestamp = "[" . date('Y-m-d H:i:s') . "]";
    $line = "$timestamp $message\n";
    echo $line;
    @ob_flush();
    @flush();
    file_put_contents($logfile, $line, FILE_APPEND);
}

log_line("🚀 Starting Enrollment Sync Process...", $logfile);

// ✅ 1. جلب توكن Zoho
log_line("🔑 Fetching Zoho access token...", $logfile);
file_get_contents($CFG->wwwroot . '/local/mb_zoho_sync/get_token.php');
sleep(1);
$tokenData = json_decode(file_get_contents(__DIR__ . '/token.json'), true);
$access_token = $tokenData['access_token'] ?? '';

if (!$access_token) {
    log_line("❌ No Zoho access token found. Stopping.", $logfile);
    exit;
}

// ✅ 2. جلب جميع التسجيلات من Moodle
log_line("📦 Fetching active enrollments from Moodle...", $logfile);

$sql = "SELECT ue.id AS enrolmentid, u.id AS userid, u.username, c.id AS courseid, c.fullname
        FROM {user_enrolments} ue
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {user} u ON u.id = ue.userid
        JOIN {course} c ON c.id = e.courseid
        WHERE u.deleted = 0 AND u.suspended = 0 AND c.id > 1";
$enrollments = $DB->get_records_sql($sql);

$total = count($enrollments);
log_line("📊 Found $total Moodle enrollments to process.", $logfile);

$addedCount = 0;
$skippedCount = 0;
$errorCount = 0;

// ✅ 3. المرور على كل تسجيل
foreach ($enrollments as $index => $enr) {
    log_line("────────────────────────────────────────────", $logfile);
    log_line("➡️ Processing [".($index+1)."/$total]: {$enr->username} → {$enr->fullname}", $logfile);

    $studentUsername = trim($enr->username);
    $courseid = $enr->courseid;

    // 🔍 البحث عن الطالب في Zoho عبر Academic_Email
    $studentSearchUrl = "https://www.zohoapis.com/crm/v2/BTEC_Students/search?criteria=(Academic_Email:equals:$studentUsername)";
    $ch = curl_init($studentSearchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Zoho-oauthtoken $access_token"]
    ]);
    $studentResponse = curl_exec($ch);
    curl_close($ch);
    $studentData = json_decode($studentResponse, true);
    $studentZohoId = $studentData['data'][0]['id'] ?? null;

    if (!$studentZohoId) {
        log_line("⚠️ Student not found in Zoho: $studentUsername", $logfile);
        $skippedCount++;
        continue;
    } else {
        log_line("✅ Student found: Zoho ID = $studentZohoId", $logfile);
    }

    // 🔍 البحث عن الكورس في Zoho عبر Moodle_Class_ID
    $classSearchUrl = "https://www.zohoapis.com/crm/v2/BTEC_Classes/search?criteria=(Moodle_Class_ID:equals:$courseid)";
    $ch = curl_init($classSearchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Zoho-oauthtoken $access_token"]
    ]);
    $classResponse = curl_exec($ch);
    curl_close($ch);
    $classData = json_decode($classResponse, true);
    $classZohoId = $classData['data'][0]['id'] ?? null;

    if (!$classZohoId) {
        log_line("⚠️ Class not found in Zoho: Moodle_Class_ID=$courseid ({$enr->fullname})", $logfile);
        $skippedCount++;
        continue;
    } else {
        log_line("✅ Class found: Zoho ID = $classZohoId", $logfile);
    }

    // 🔎 التحقق من وجود التسجيل مسبقًا في Zoho
    $checkUrl = "https://www.zohoapis.com/crm/v2/BTEC_Enrollments/search?criteria=(Enrolled_Students.id:equals:$studentZohoId)and(Classes.id:equals:$classZohoId)";
    $ch = curl_init($checkUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Zoho-oauthtoken $access_token"]
    ]);
    $checkResponse = curl_exec($ch);
    curl_close($ch);
    $checkData = json_decode($checkResponse, true);

    if (!empty($checkData['data'])) {
        log_line("⏭️ Enrollment already exists in Zoho. Skipping.", $logfile);
        $skippedCount++;
        continue;
    }

    // ✅ 4. إنشاء التسجيل الجديد في Zoho
    log_line("➕ Creating new enrollment record in Zoho...", $logfile);

    $payload = [
        "data" => [[
            "Enrolled_Students" => ["id" => $studentZohoId],
            "Classes"           => ["id" => $classZohoId],
            "Name"              => "{$studentUsername} - {$enr->fullname}",
            "Source"            => "Moodle Sync"
        ]]
    ];

    $ch = curl_init("https://www.zohoapis.com/crm/v2/BTEC_Enrollments");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Zoho-oauthtoken $access_token",
            "Content-Type: application/json"
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 201 || $httpCode == 200) {
        log_line("✅ Successfully added enrollment → {$studentUsername} in {$enr->fullname}", $logfile);
        $addedCount++;
    } else {
        log_line("❌ Failed to add enrollment. Response: $response", $logfile);
        $errorCount++;
    }

    // راحة خفيفة بين الطلبات لتجنب Rate Limit
    usleep(300000);
}

log_line("────────────────────────────────────────────", $logfile);
log_line("🏁 Sync Completed.", $logfile);
log_line("📊 Summary → Added: $addedCount | Skipped: $skippedCount | Errors: $errorCount", $logfile);
