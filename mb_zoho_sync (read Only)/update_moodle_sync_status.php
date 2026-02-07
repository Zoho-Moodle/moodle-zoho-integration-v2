<?php
// /local/mb_zoho_sync/sync_btec_templates.php
// UTF-8
// دمج: (A) إنشاء/تحديث الـ grading definitions + areas (من السكربت الأول)
//      (B) جلب حقول Subform P11..P20 عبر get-by-id (من السكربت الثاني)
//      (C) إدراج معايير غير فارغة فقط وبالترتيب: P1..P10 → P11..P20 → M1..M8 → D1..D6
//      (D) JSON نظيف + لوج تفصيلي + Pagination

require('../../config.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/btec/lib.php');

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ========= إعدادات عامة =========
$LOGFILE = __DIR__ . '/btec_template_debug.log';
$SUBFORM_API = 'BTEC_Grading_Template_P1'; // كما طلبت
$ZOHO_MODULE = 'BTEC';
$PER_PAGE    = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 200;
$START_PAGE  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$MAX_PAGES   = isset($_GET['max_pages']) ? max(1, (int)$_GET['max_pages']) : 100; // سقف أمان

// اضبطها true إذا بدك تخطي الوحدات اللي P1 فيها فاضي (سلوكك القديم)
const HARD_SKIP_IF_P1_EMPTY = true;

// ========= أدوات مساعدة =========
function seconds($t) { return round($t, 4); }
function v($x){ return is_scalar($x) ? trim((string)$x) : ''; }

function log_step($msg, $ctx = []) {
    global $LOGFILE;
    $line = "[" . date('Y-m-d H:i:s') . "] " . $msg;
    if (!empty($ctx)) {
        $line .= " " . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    file_put_contents($LOGFILE, $line . "\n", FILE_APPEND);
}

function curl_json($url, $headers = [], $timeout = 30) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $start = microtime(true);
    $body  = curl_exec($ch);
    $info  = curl_getinfo($ch);
    $errno = curl_errno($ch);
    $err   = $errno ? curl_error($ch) : null;
    curl_close($ch);
    $dur = microtime(true) - $start;
    return [
        'http_code' => $info['http_code'] ?? 0,
        'errno'     => $errno,
        'error'     => $err,
        'duration'  => $dur,
        'raw'       => $body,
        'json'      => json_decode($body, true)
    ];
}

function get_access_token(&$steps) {
    $tick = microtime(true);
    log_step("🔄 Requesting token from get_token.php...");
    @file_get_contents('https://elearning.abchorizon.com/local/mb_zoho_sync/get_token.php');
    usleep(300 * 1000);
    $tokenFile = __DIR__ . '/token.json';
    $tokenData = is_file($tokenFile) ? json_decode(file_get_contents($tokenFile), true) : [];
    $access_token = $tokenData['access_token'] ?? '';
    $steps[] = ['step' => 'get_token', 'ok' => $access_token !== '', 'duration_sec' => seconds(microtime(true) - $tick)];
    log_step('🔑 Token read', ['token_present' => $access_token !== '']);
    return $access_token;
}

function zoho_headers($token) {
    return ['Authorization: Zoho-oauthtoken ' . $token, 'Accept: application/json'];
}

// حقل الـ list المبسّط (ما منجيب الـ subform هون)
function list_fields_query_param() {
    $parts = [];
    $parts[] = 'Name';
    $parts[] = 'id';
    for ($i=1;$i<=10;$i++) $parts[] = "P{$i}_description";
    for ($i=1;$i<=8;$i++)  $parts[] = "M{$i}_description";
    for ($i=1;$i<=6;$i++)  $parts[] = "D{$i}_description";
    return implode(',', $parts);
}

// get-by-id للحصول على الـ subform
function fetch_record_with_subform($baseurl, $token, $id) {
    $url = $baseurl . '/' . rawurlencode($id);
    $resp = curl_json($url, zoho_headers($token));
    log_step('🔎 Fetch by id', ['id'=>$id, 'http_code'=>$resp['http_code'], 'errno'=>$resp['errno'], 'duration_sec'=>seconds($resp['duration'])]);
    if ($resp['http_code'] != 200 || empty($resp['json']['data'][0])) {
        return [null, $resp];
    }
    return [$resp['json']['data'][0], $resp];
}

function add_field_if_not_empty(&$out, &$order, $short, $desc){
    if ($desc !== '') {
        $out[] = ['shortname'=>$short, 'description'=>$desc];
        $order[] = $short;
        log_step('➕ Queued criterion', ['shortname'=>$short]);
        return true;
    } else {
        log_step('🛑 Skipped empty criterion', ['shortname'=>$short]);
        return false;
    }
}

// ========= منطق مودل (Areas + Definitions + Criteria) =========
function ensure_grading_area_and_get_id(string $unitname): int {
    global $DB;
    $areaname = 'btec_'.md5($unitname);
    $existing = $DB->get_record('grading_areas', ['areaname'=>$areaname,'activemethod'=>'btec']);
    if ($existing) return (int)$existing->id;

    // حاول ربطها بسياق كورس بنفس الاسم
    $contextid = 1;
    if ($course = $DB->get_record('course', ['fullname'=>$unitname])) {
        $context = context_course::instance($course->id);
        $contextid = $context->id;
    }
    $area = (object)[
        'contextid'=>$contextid,'component'=>'core_grading',
        'areaname'=>$areaname,'activemethod'=>'btec'
    ];
    $areaid = $DB->insert_record('grading_areas',$area);
    log_step("✅ Inserted grading_area", ['unit'=>$unitname,'areaid'=>$areaid,'contextid'=>$contextid]);
    return (int)$areaid;
}

function upsert_definition_bind_area_and_reset_criteria(string $unitname, int $areaid): array {
    global $DB, $USER;
    $existing = $DB->get_record('grading_definitions', ['name'=>$unitname,'method'=>'btec']);
    if ($existing) {
        // عدّل الـ area لو لزم واحذف المعايير القديمة
        $changed = false;
        if ((int)$existing->areaid !== (int)$areaid) {
            $existing->areaid = $areaid;
            $changed = true;
        }
        $existing->timemodified = time();
        $existing->usermodified = $USER->id ?? 0;
        if ($changed) $DB->update_record('grading_definitions', $existing);
        $DB->delete_records('gradingform_btec_criteria', ['definitionid'=>$existing->id]);
        return ['id'=>(int)$existing->id,'action'=>'updated'];
    } else {
        $definition = (object)[
            'areaid'=>$areaid,'name'=>$unitname,'description'=>'','descriptionformat'=>FORMAT_HTML,
            'status'=>0,'copiedfromid'=>null,'timecreated'=>time(),'timemodified'=>time(),
            'method'=>'btec','usercreated'=>$USER->id ?? 0,'usermodified'=>$USER->id ?? 0,
        ];
        $id = (int)$DB->insert_record('grading_definitions',$definition);
        return ['id'=>$id,'action'=>'created'];
    }
}

function insert_criteria_bulk(int $definitionid, array $fields): int {
    global $DB;
    $sort=1; $inserted=0;
    foreach ($fields as $f) {
        $rec = (object)[
            'definitionid'=>$definitionid,'sortorder'=>$sort++,
            'shortname'=>$f['shortname'],'description'=>$f['description'],
            'descriptionformat'=>FORMAT_HTML,'descriptionmarkers'=>'','descriptionmarkersformat'=>FORMAT_HTML
        ];
        $DB->insert_record('gradingform_btec_criteria',$rec);
        $inserted++;
    }
    return $inserted;
}

// ========= التنفيذ =========
$script_start = microtime(true);
$steps = [];
$items = [];
$stats = [
    'zoho_requests' => 0,
    'zoho_pages' => 0,
    'zoho_per_page' => $PER_PAGE,
    'units_total' => 0,
    'units_processed' => 0,
    'units_skipped' => 0,
    'definitions_created' => 0,
    'definitions_updated' => 0,
    'criteria_inserted' => 0,
    'criteria_skipped_empty' => 0,
    'errors' => 0,
];

log_step('🚀 Script started');

// 1) التوكن
$token = get_access_token($steps);
if ($token === '') {
    echo json_encode([
        'status' => 'error',
        'error'  => 'access_token_not_found',
        'steps'  => $steps,
        'duration_sec' => seconds(microtime(true) - $script_start),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$baseurl = 'https://www.zohoapis.com/crm/v2/' . $ZOHO_MODULE;

$unit_id = isset($_GET['id']) ? $_GET['id'] : ''; // أخذ الـ id من الـ URL
if (empty($unit_id)) {
    echo json_encode([
        'status' => 'error',
        'error'  => 'unit_id_missing',
        'message' => 'Please provide an id in the URL.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// استعلام فقط عن الـ unit المحدد
$url = $baseurl . '/' . rawurlencode($unit_id);
$resp = curl_json($url, zoho_headers($token));
$steps[] = ['step' => 'fetch_unit_by_id', 'unit_id' => $unit_id, 'http_code' => $resp['http_code'], 'errno' => $resp['errno'], 'err' => $resp['error'], 'duration_sec' => seconds($resp['duration'])];
log_step('🌐 Zoho fetch unit', ['unit_id' => $unit_id, 'http_code' => $resp['http_code'], 'errno' => $resp['errno'], 'duration_sec' => seconds($resp['duration'])]);

if ($resp['http_code'] != 200 || empty($resp['json']['data'][0])) {
    echo json_encode([
        'status' => 'error',
        'error'  => 'unit_not_found',
        'message' => 'The specified unit could not be found in Zoho.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// مواصلة العمليات على الـ unit
$all = [$resp['json']['data'][0]]; // تهيئة المتغير all للبيانات المستلمة


// 3) معالجة كل وحدة
global $DB, $USER;
foreach ($all as $unit) {
    $unitname = v($unit['Name'] ?? '');
    $unitid   = v($unit['id'] ?? '');

    $item = [
        'unit' => $unitname,
        'zoho_id' => $unitid,
        'synced' => false,
        'action' => 'none',
        'reason' => '',
        'definitionid' => null,
        'areaid' => null,
        'criteria_inserted' => 0,
        'criteria_skipped_empty' => 0,
        'subform_rows' => 0,
        'subform_used_count' => 0,
        'order' => [],
        'timing_sec' => 0.0,
    ];
    $t0 = microtime(true);

    if ($unitname === '' || $unitid === '') {
        $stats['units_skipped']++;
        $item['reason'] = 'missing_name_or_id';
        $items[] = $item;
        continue;
    }

    // شرط P1 (اختياري): إذا كانت فارغة، تجاهل الوحدة
    $p1 = v($unit['P1_description'] ?? '');
    if (HARD_SKIP_IF_P1_EMPTY && $p1 === '') {
        $stats['units_skipped']++;
        $item['reason'] = 'empty_P1_description';
        log_step('⚠️ Skipping unit (empty P1_description)', ['unit'=>$unitname]);
        $item['timing_sec'] = seconds(microtime(true) - $t0);
        $items[] = $item;
        continue;
    }

    try {
        // (بقية المعالجة كما هي)
        
        // جهّز الحقول بالترتيب
        $fields = [];
        $order  = [];

        // --- P1..P10 من السجل الأساسي ---
        for ($i=1; $i<=10; $i++){
            $key = "P{$i}_description";
            $desc = v($unit[$key] ?? '');
            if (!add_field_if_not_empty($fields, $order, "P{$i}", $desc)) {
                $stats['criteria_skipped_empty']++;
            }
        }


        // --- Subform (P11..P20) عبر get-by-id ---
        $recordFull = null; $subformRows = [];
        list($recordFull, $respById) = fetch_record_with_subform($baseurl, $token, $unitid);
        $stats['zoho_requests']++;
        if ($recordFull && array_key_exists($SUBFORM_API, $recordFull) && is_array($recordFull[$SUBFORM_API])) {
            $subformRows = $recordFull[$SUBFORM_API];
        }
        $item['subform_rows'] = is_array($subformRows) ? count($subformRows) : 0;
        log_step('📑 Subform presence', ['unit'=>$unitname,'key'=>$SUBFORM_API,'rows'=>$item['subform_rows']]);

        // أول قيمة غير فاضية لكل P11..P20 عبر صفوف السب-فورم
        $sf_used_count = 0;
        for ($i=11; $i<=20; $i++){
            $col = "P{$i}_description";
            $picked = '';
            $fromRowId = null;
            if (!empty($subformRows)) {
                foreach ($subformRows as $r){
                    $rowid = isset($r['id']) ? (string)$r['id'] : null;
                    $val = v($r[$col] ?? '');
                    if ($val !== ''){
                        $picked = $val;
                        $fromRowId = $rowid;
                        break;
                    }
                }
            }
            if ($picked !== ''){
                $fields[] = ['shortname'=>"P{$i}", 'description'=>$picked];
                $order[]  = "P{$i}";
                $sf_used_count++;
                log_step('➕ Queued criterion from subform', ['unit'=>$unitname,'shortname'=>"P{$i}",'row_id'=>$fromRowId]);
            } else {
                log_step('🛑 Skipped empty subform criterion', ['unit'=>$unitname,'shortname'=>"P{$i}"]);
                $stats['criteria_skipped_empty']++;
            }
        }
        $item['subform_used_count'] = $sf_used_count;

        // --- M1..M8 ---
        for ($i=1; $i<=8; $i++){
            $key = "M{$i}_description";
            $desc = v($unit[$key] ?? '');
            if (!add_field_if_not_empty($fields, $order, "M{$i}", $desc)) {
                $stats['criteria_skipped_empty']++;
            }
        }

        // --- D1..D6 ---
        for ($i=1; $i<=6; $i++){
            $key = "D{$i}_description";
            $desc = v($unit[$key] ?? '');
            if (!add_field_if_not_empty($fields, $order, "D{$i}", $desc)) {
                $stats['criteria_skipped_empty']++;
            }
        }

        if (empty($fields)) {
            $stats['units_skipped']++;
            $item['reason'] = 'no_nonempty_criteria_after_filters';
            log_step('ℹ️ Skipped unit (no criteria to insert)', ['unit'=>$unitname]);
            $item['timing_sec'] = seconds(microtime(true) - $t0);
            $items[] = $item;
            continue;
        }

        // --- Area + Definition + Criteria داخل Transaction مثل السكربت الأول ---
        $areaid = ensure_grading_area_and_get_id($unitname);
        $item['areaid'] = $areaid;

        $tx = $DB->start_delegated_transaction();
        $up = upsert_definition_bind_area_and_reset_criteria($unitname, $areaid);
        $definitionid = (int)$up['id'];
        $item['definitionid'] = $definitionid;
        $item['action'] = $up['action'];
        if ($up['action'] === 'created') $stats['definitions_created']++;
        if ($up['action'] === 'updated') $stats['definitions_updated']++;

        log_step(($up['action']==='created' ? '📌 Created grading_definitions' : '📌 Updated grading_definitions'),
                 ['unit'=>$unitname,'definitionid'=>$definitionid,'areaid'=>$areaid]);

        $inserted = insert_criteria_bulk($definitionid, $fields);
        $stats['criteria_inserted'] += $inserted;

        $DB->commit_delegated_transaction($tx);

        $item['criteria_inserted'] = $inserted;
        $item['order'] = $order;
        $item['synced'] = true;
        $item['reason'] = '';
        $stats['units_processed']++;

        log_step('✅ Unit synced', [
            'unit'=>$unitname,
            'definitionid'=>$definitionid,
            'criteria_inserted'=>$inserted,
            'subform_used_count'=>$sf_used_count,
            'order'=>implode(',', $order),
            'duration_sec'=>seconds(microtime(true) - $t0)
        ]);

    } catch (Throwable $e) {
        $stats['errors']++;
        $item['synced'] = false;
        $item['reason'] = 'exception: ' . $e->getMessage();
        log_step('❌ Error syncing unit', ['unit'=>$unitname,'error'=>$e->getMessage()]);
    }

    $item['timing_sec'] = seconds(microtime(true) - $t0);
    $items[] = $item;
}

// ========= إخراج JSON =========
echo json_encode([
    'status' => 'ok',
    'meta' => [
        'now' => gmdate('c'),
        'module' => $ZOHO_MODULE,
        'subform_api' => $SUBFORM_API,
        'start_page' => $START_PAGE,
        'per_page' => $PER_PAGE
    ],
    'stats' => array_merge($stats, [
        'duration_sec' => seconds(microtime(true) - $script_start)
    ]),
    'items' => $items,
    'steps' => $steps,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
