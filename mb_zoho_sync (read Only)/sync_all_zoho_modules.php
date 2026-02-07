<?php
// ============================================================================
// ABC Horizon - Zoho Full Sync Script (Final Version)
// Author: Majd & ChatGPT
// Updated: 2025-10-29
// ============================================================================

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/get_token.php'); // ← التوكن الأساسي من الملف الداخلي

global $DB, $CFG;

// ----------------------------------------------------------------------------
// 🔹 دوال مساعدة
// ----------------------------------------------------------------------------
function logx($msg) {
    echo "[" . date('H:i:s') . "] $msg\n";
    ob_flush(); flush();
}

function table_has_column($table, $column) {
    global $DB, $CFG;
    $bare = preg_replace('/^'.preg_quote($CFG->prefix, '/').'/', '', $table);
    try {
        $cols = $DB->get_columns($bare);
        return isset($cols[$column]);
    } catch (Exception $e) {
        logx("⚠️ فشل فحص الأعمدة: {$e->getMessage()}");
        return false;
    }
}
// ----------------------------------------------------------------------------
// 🔹 دالة جلب التوكن من ملف token.json
// ----------------------------------------------------------------------------
function zoho_get_token() {
    $tokenFile = __DIR__ . '/token.json';
    if (!file_exists($tokenFile)) {
        echo "❌ Token file not found: $tokenFile\n";
        exit;
    }

    $data = json_decode(file_get_contents($tokenFile), true);
    if (!isset($data['access_token']) || !$data['access_token']) {
        echo "❌ Invalid token.json format\n";
        exit;
    }

    return $data['access_token'];
}

// ----------------------------------------------------------------------------
// 🔹 جلب الداتا من Zoho API
// ----------------------------------------------------------------------------
function zoho_fetch_all($moduleName) {
    $token = zoho_get_token(); // الدالة من get_token.php
    $url = "https://www.zohoapis.com/crm/v2/{$moduleName}?per_page=200";
    $records = [];

    while ($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Zoho-oauthtoken $token"]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['data'])) break;

        $records = array_merge($records, $data['data']);
        $url = $data['info']['more_records'] ? $data['info']['next_page_token'] : null;
    }

    return $records;
}

// ----------------------------------------------------------------------------
// 🔹 الدالة العامة للمزامنة
// ----------------------------------------------------------------------------
function sync_module($moduleName, $table, $studentField, $mapFields) {
    global $DB;
    logx("🔄 Syncing {$moduleName} → {$table}.{$studentField}");

    if (!table_has_column($table, $studentField)) {
        logx("❌ العمود {$studentField} غير موجود في {$table}");
        return;
    }

    $records = zoho_fetch_all($moduleName);
    $synced = 0;

    foreach ($records as $rec) {
        $zohoId = $rec['id'] ?? '';
        $studentId = trim($rec['Student_ID'] ?? '');

        if (!$studentId) {
            logx("🎓 Zoho ID: {$zohoId}");
            logx("❌ Skipped (no Student_ID)");
            echo str_repeat('-', 40) . PHP_EOL;
            continue;
        }

        $data = [$studentField => $studentId];
        if (table_has_column($table, 'zoho_id')) $data['zoho_id'] = $zohoId;

        foreach ($mapFields as $dbcol => $zohokey) {
            if (!table_has_column($table, $dbcol)) continue;
            $val = $rec[$zohokey] ?? null;
            if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            $data[$dbcol] = $val;
        }

        try {
            $exists = $DB->get_record($table, [$studentField => $studentId]);
            if ($exists) {
                $data['id'] = $exists->id;
                $DB->update_record($table, (object)$data);
                logx("✅ Updated $studentId");
            } else {
                $DB->insert_record($table, (object)$data);
                logx("✅ Inserted $studentId");
            }
            $synced++;
        } catch (Exception $e) {
            logx("❌ Error {$studentId}: {$e->getMessage()}");
        }

        echo str_repeat('-', 40) . PHP_EOL;
    }

    logx("✅ Done: {$moduleName} (synced={$synced})");
    echo str_repeat('-', 40) . PHP_EOL;
}

// ----------------------------------------------------------------------------
// 🔹 التشغيل الفعلي
// ----------------------------------------------------------------------------
echo PHP_EOL . "========================================" . PHP_EOL;
echo "🚀 ABC Horizon - Full Zoho Sync Start" . PHP_EOL;
echo "========================================" . PHP_EOL . PHP_EOL;

try {
    // 1️⃣ الطلاب
    sync_module('BTEC_Students', 'mdl_student_profile', 'student_id', [
        'display_name' => 'Full_Name',
        'academic_email' => 'Email',
        'phone' => 'Phone',
        'country' => 'Country',
        'city' => 'City',
        'status' => 'Status'
    ]);

    // 2️⃣ التسجيلات
    sync_module('BTEC_Registrations', 'mdl_zoho_registrations', 'student_id_name', [
        'program' => 'Program_Name',
        'program_price' => 'Program_Price',
        'registration_status' => 'Registration_Status',
        'currency' => 'Currency',
        'study_language' => 'Study_Language',
        'study_mode' => 'Study_Mode'
    ]);

    // 3️⃣ الدفعات
    sync_module('BTEC_Payments', 'mdl_zoho_payments', 'student_zoho_id', [
        'payment_amount' => 'Amount',
        'payment_method' => 'Payment_Method',
        'payment_type' => 'Payment_Type',
        'currency' => 'Currency',
        'payment_date' => 'Payment_Date'
    ]);

    // 4️⃣ الشعب
    sync_module('BTEC_Enrollments', 'mdl_zoho_enrollments', 'student_zoho_id', [
        'class_name' => 'Class_Name',
        'class_teacher' => 'Teacher',
        'enrolled_program' => 'Program_Name',
        'start_date' => 'Start_Date',
        'end_date' => 'End_Date'
    ]);

    // 5️⃣ الدرجات
    sync_module('BTEC_Grades', 'mdl_zoho_grades', 'student_zoho_id', [
        'btec_grade_name' => 'Grade_Name',
        'grade' => 'Grade',
        'feedback' => 'Feedback',
        'attempt_number' => 'Attempt_Number',
        'attempt_date' => 'Attempt_Date',
        'grader_name' => 'Grader',
        'iv_name' => 'IV_Name'
    ]);

    echo PHP_EOL . "========================================" . PHP_EOL;
    echo "✅ All Zoho modules synced successfully!" . PHP_EOL;
    echo "========================================" . PHP_EOL;

} catch (Exception $e) {
    echo PHP_EOL . "❌ Exception: {$e->getMessage()}" . PHP_EOL;
}
