<?php
// ── 1) لازم نعرّف CLI_SCRIPT قبل config.php إذا كنا على CLI
if (PHP_SAPI === 'cli') {
    define('CLI_SCRIPT', true);
}

// ── 2) Moodle bootstrap
require_once(__DIR__ . '/../../config.php');
global $DB, $CFG;

// ── 3) إعدادات عامة
ini_set('memory_limit', '1024M');
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// إخراج مناسب حسب السياق (ويب أو CLI)
$logfile = __DIR__ . '/logs/zoho_master_sync_' . date('Y-m-d_His') . '.log';
if (!CLI_SCRIPT) {
    // الويب
    require_login();
    header('Content-Type: text/plain; charset=utf-8');
}

// ── 4) دالة لوج
function log_msg(string $msg): void {
    global $logfile;
    $line = '[' . date('H:i:s') . '] ' . $msg;
    if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
        echo $line . PHP_EOL;
    } else {
        echo $line . "\n";
        @ob_flush(); flush();
    }
    @file_put_contents($logfile, $line . PHP_EOL, FILE_APPEND);
}

// ── 5) إعداد التوكن (نفس طريقتك الأصلية – لا نغيّرها)
$sitebase    = rtrim($CFG->wwwroot, '/');
$getTokenUrl = $sitebase . '/local/mb_zoho_sync/get_token.php';
$tokenPath   = __DIR__ . '/token.json';
$apiBase     = 'https://www.zohoapis.com/crm/v2';

function get_token(string $url, string $path): string {
    log_msg("🔐 Getting Zoho token...");
    @file_get_contents($url);          // refresh
    usleep(400000);                    // 0.4s
    $tok = json_decode(@file_get_contents($path) ?: '[]', true);
    if (empty($tok['access_token'])) {
        throw new moodle_exception('Missing Zoho token');
    }
    log_msg("✅ Token loaded.");
    return $tok['access_token'];
}

// ── 6) HTTP helper
function http_get(string $url, array $headers = [], array $params = []): array {
    $ch = curl_init();
    if ($params) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $res];
}

// ── 7) جلب صفحات من أي موديل Zoho
function fetch_zoho_module(string $module, array $fields, int $perPage = 200, int $maxPages = 100): array {
    global $apiBase, $headers;
    $all = [];
    $page = 1;
    do {
        [$code, $body] = http_get("$apiBase/$module", $headers, [
            'page' => $page,
            'per_page' => $perPage,
            // ملاحظة: لو حبيت تضيف order_by لاحقًا
        ]);
        $json = json_decode($body, true);
        $rows = ($code === 200 && !empty($json['data'])) ? $json['data'] : [];
        log_msg("📦 [$module] page=$page status=$code count=" . count($rows));
        if (!$rows) { break; }

        foreach ($rows as $rec) {
            $row = [];
            foreach ($fields as $f) {
                $v = $rec[$f] ?? null;
                if (is_array($v) && array_key_exists('id', $v)) {
                    // حقول lookup: نخزن id + الاسم
                    $row[$f] = $v['id'];
                    $row[$f . '_name'] = $v['name'] ?? ($v['display_value'] ?? '');
                } else {
                    $row[$f] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
                }
            }
            $all[] = $row;
        }

        $page++;
        $more = !empty($json['info']['more_records']);
    } while ($more && $page <= $maxPages);

    log_msg("✅ [$module] total fetched: " . count($all));
    return $all;
}

// ── 8) UPSERT عام
function upsert_table(string $table, string $key, array $rows): void {
    global $DB;
    $ins = 0; $upd = 0;
    foreach ($rows as $r) {
        if (empty($r[$key])) { continue; }
        $r['last_sync'] = time();
        if ($ex = $DB->get_record($table, [$key => $r[$key]])) {
            $r['id'] = $ex->id;
            $DB->update_record($table, $r);
            $upd++;
        } else {
            $DB->insert_record($table, $r);
            $ins++;
        }
    }
    log_msg("💾 $table → Inserted: $ins | Updated: $upd");
}

// ── 9) البدء
try {
    $token   = get_token($getTokenUrl, $tokenPath);
    $headers = ["Authorization: Zoho-oauthtoken $token"];

    log_msg("🚀 Start Zoho Master Sync");
    log_msg("────────────────────────────────────────────");

    // ===== STUDENTS =====
    $students = fetch_zoho_module('BTEC_Students', [
        'id','Name','First_Name','Last_Name','Birth_Date','Academic_Email','Email',
        'Phone_Number','Address','Country','City','Status','Created_Time','Modified_Time','Student_Image'
    ]);
    $rows = [];
    foreach ($students as $s) {
        $rows[] = [
            'zoho_id'        => $s['id'],
            'student_id'     => $s['Name'],
            'first_name'     => $s['First_Name'],
            'last_name'      => $s['Last_Name'],
            'display_name'   => trim(($s['First_Name'] ?? '') . ' ' . ($s['Last_Name'] ?? '')),
            'birth_date'     => $s['Birth_Date'],
            'academic_email' => $s['Academic_Email'],
            'email'          => $s['Email'],
            'phone_number'   => $s['Phone_Number'],
            'address'        => $s['Address'],
            'country'        => $s['Country'],
            'city'           => $s['City'],
            'status'         => $s['Status'],
            'student_image'  => $s['Student_Image'],
            'created_time'   => !empty($s['Created_Time'])  ? strtotime($s['Created_Time'])  : null,
            'modified_time'  => !empty($s['Modified_Time']) ? strtotime($s['Modified_Time']) : null,
        ];
    }
    upsert_table('zoho_students', 'zoho_id', $rows);

    // ===== REGISTRATIONS =====
    $regs = fetch_zoho_module('BTEC_Registrations', [
        'id','Name','Student_ID','Program','Sub_Major','Major','Program_Price',
        'Registration_Status','Registration_Date','Remaining_Amount',
        'Study_Language','Study_Mode','Currency','Created_Time','Modified_Time'
    ]);
    $rows = [];
    foreach ($regs as $r) {
        $rows[] = [
            'zoho_id'             => $r['id'],
            'registration_id'     => $r['Name'],
            'student_zoho_id'     => $r['Student_ID'],
            'student_id_name'     => $r['Student_ID_name'] ?? '',
            'program_id'          => $r['Program'],
            'program'             => $r['Program_name'] ?? '',
            'sub_major'           => $r['Sub_Major'],
            'major'               => $r['Major'],
            'program_price'       => $r['Program_Price'],
            'registration_status' => $r['Registration_Status'],
            'registration_date'   => !empty($r['Registration_Date']) ? strtotime($r['Registration_Date']) : null,
            'remaining_amount'    => $r['Remaining_Amount'],
            'study_language'      => $r['Study_Language'],
            'study_mode'          => $r['Study_Mode'],
            'currency'            => $r['Currency'],
            'created_time'        => !empty($r['Created_Time'])  ? strtotime($r['Created_Time'])  : null,
            'updated_time'        => !empty($r['Modified_Time']) ? strtotime($r['Modified_Time']) : null,
        ];
    }
    upsert_table('zoho_registrations', 'zoho_id', $rows);

    // ===== PAYMENTS =====
    $pays = fetch_zoho_module('BTEC_Payments', [
        'id','Name','Student_ID','Registration_ID','Payment_Amount','Currency',
        'Payment_Type','Payment_Method','Payment_Date','Created_Time','Modified_Time'
    ]);
    $rows = [];
    foreach ($pays as $p) {
        $rows[] = [
            'zoho_id'              => $p['id'],
            'payment_id'           => $p['Name'],
            'student_zoho_id'      => $p['Student_ID'],
            'registration_zoho_id' => $p['Registration_ID'],
            'payment_amount'       => $p['Payment_Amount'],
            'currency'             => $p['Currency'],
            'payment_type'         => $p['Payment_Type'],
            'payment_method'       => $p['Payment_Method'],
            'payment_date'         => !empty($p['Payment_Date']) ? strtotime($p['Payment_Date']) : null,
            'created_time'         => !empty($p['Created_Time'])  ? strtotime($p['Created_Time'])  : null,
            'updated_time'         => !empty($p['Modified_Time']) ? strtotime($p['Modified_Time']) : null,
        ];
    }
    upsert_table('zoho_payments', 'zoho_id', $rows);

    // ===== ENROLLMENTS =====
    $enrs = fetch_zoho_module('BTEC_Enrollments', [
        'id','Name','Enrolled_Students','Student_Name','Classes','Class_Name',
        'Class_Teacher','Enrolled_Program','Start_Date','End_Date','Created_Time','Modified_Time'
    ]);
    $rows = [];
    foreach ($enrs as $e) {
        $rows[] = [
            'zoho_id'          => $e['id'],
            'enrollment_id'    => $e['Name'],
            'student_zoho_id'  => $e['Enrolled_Students'],
            'student_name'     => $e['Student_Name'],
            'class_zoho_id'    => $e['Classes'],
            'class_name'       => $e['Class_Name'],
            'class_teacher'    => $e['Class_Teacher'],
            'enrolled_program' => $e['Enrolled_Program'],
            'start_date'       => !empty($e['Start_Date']) ? strtotime($e['Start_Date']) : null,
            'end_date'         => !empty($e['End_Date'])   ? strtotime($e['End_Date'])   : null,
            'created_time'     => !empty($e['Created_Time'])  ? strtotime($e['Created_Time'])  : null,
            'modified_time'    => !empty($e['Modified_Time']) ? strtotime($e['Modified_Time']) : null,
        ];
    }
    upsert_table('zoho_enrollments', 'zoho_id', $rows);

    // ===== GRADES =====
    $grs = fetch_zoho_module('BTEC_Grades', [
        'id','Name','Student','Class','BTEC_Grade_Name','Grade','Feedback',
        'Attempt_Number','Attempt_Date','Grader_Name','IV_Name','Moodle_Grade_ID',
        'Moodle_Grade_Composite_Key','Grade_Status','Created_Time','Modified_Time'
    ]);
    $rows = [];
    foreach ($grs as $g) {
        $rows[] = [
            'zoho_id'                    => $g['id'],
            'grade_record_id'            => $g['Name'],
            'student_zoho_id'            => $g['Student'],
            'class_zoho_id'              => $g['Class'],
            'btec_grade_name'            => $g['BTEC_Grade_Name'],
            'grade'                      => $g['Grade'],
            'feedback'                   => $g['Feedback'],
            'attempt_number'             => $g['Attempt_Number'],
            'attempt_date'               => !empty($g['Attempt_Date']) ? strtotime($g['Attempt_Date']) : null,
            'grader_name'                => $g['Grader_Name'],
            'iv_name'                    => $g['IV_Name'],
            'moodle_grade_id'            => $g['Moodle_Grade_ID'],
            'moodle_grade_composite_key' => $g['Moodle_Grade_Composite_Key'],
            'grade_status'               => $g['Grade_Status'],
            'created_time'               => !empty($g['Created_Time'])  ? strtotime($g['Created_Time'])  : null,
            'updated_time'               => !empty($g['Modified_Time']) ? strtotime($g['Modified_Time']) : null,
        ];
    }
    upsert_table('zoho_grades', 'zoho_id', $rows);

    log_msg("🎯 Sync completed successfully!");
    log_msg("Log file: $logfile");

} catch (Throwable $e) {
    log_msg('❌ ERROR: ' . $e->getMessage());
    if (!CLI_SCRIPT) {
        http_response_code(500);
    }
}
