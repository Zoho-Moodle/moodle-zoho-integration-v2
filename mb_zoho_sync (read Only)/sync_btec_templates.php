<?php
// CLI, Legacy Logic + Step-by-step verbose tracing
// Usage:
//   php sync_btec_templates_legacy_cli.php
//   php sync_btec_templates_legacy_cli.php --unit="Unit Fullname in Zoho/Moodle"
// Notes: Keeps your original behavior (no transactions, areaid=0 in definitions, no pagination)

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/btec/lib.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$logfile = __DIR__ . '/btec_template_debug.log';

function out($msg, $ctx = []) {
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $msg";
    if (!empty($ctx)) {
        $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    echo $line . PHP_EOL;
    file_put_contents($GLOBALS['logfile'], $line . PHP_EOL, FILE_APPEND);
}

function step($n, $title) { out("— المرحلة $n: $title —"); }

function criteria_count($definitionid) {
    global $DB;
    return $DB->count_records('gradingform_btec_criteria', ['definitionid' => $definitionid]);
}

// ---- اختياري: فلترة حسب وحدة معينة للتشخيص ----
$unitFilter = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--unit=') === 0) {
        $unitFilter = trim(substr($arg, strlen('--unit=')));
    }
}

$scriptStart = microtime(true);
out("🚀 بدء السكربت (منطق قديم + تتبّع تفصيلي)");

// ========== 1) الحصول على التوكن ==========
step(1, 'طلب التوكن من get_token.php وقراءة token.json');
$tokStart = microtime(true);
@file_get_contents('https://elearning.abchorizon.com/local/mb_zoho_sync/get_token.php');
sleep(1); // كما في منطقك الأصلي
$tokenPath = __DIR__ . '/token.json';
$tokenData = json_decode(@file_get_contents($tokenPath), true);
$access_token = $tokenData['access_token'] ?? '';
$tokDur = round(microtime(true) - $tokStart, 3);
out("📄 token.json", ['path' => $tokenPath, 'has_token' => (bool)$access_token, 'duration_sec' => $tokDur]);
if (!$access_token) {
    out("❌ فشل: لم يتم العثور على التوكن. إنهاء.");
    exit(1);
}

// ========== 2) جلب بيانات Zoho (بدون Pagination) ==========
step(2, 'جلب بيانات BTEC من Zoho (بدون ترقيم صفحات)');
$zoStart = microtime(true);
$ch = curl_init('https://www.zohoapis.com/crm/v2/BTEC');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Zoho-oauthtoken ' . $access_token],
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 60,
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errno = curl_errno($ch);
$err = $errno ? curl_error($ch) : null;
curl_close($ch);
$zoDur = round(microtime(true) - $zoStart, 3);

out("🌐 Zoho ردّ", ['http_code' => $httpcode, 'errno' => $errno, 'duration_sec' => $zoDur]);
if ($errno || $httpcode >= 400 || !$response) {
    out("❌ خطأ الاتصال بـ Zoho", ['errno' => $errno, 'err' => $err]);
    exit(1);
}

$data = json_decode($response, true);
$units = $data['data'] ?? [];
$total = is_array($units) ? count($units) : 0;
out("📦 عدد السجلات المستلمة", ['units_total' => $total]);

if ($total === 0) {
    out("❌ لا يوجد بيانات 'data' في رد Zoho. إنهاء.");
    exit(1);
}

// ========== 3) معالجة الوحدات ==========
step(3, 'معالجة كل وحدة: تعريف + معايير + grading_areas (منطقك القديم)');

global $DB, $USER;
$stats = [
    'processed' => 0,
    'skipped_empty_p1' => 0,
    'created' => 0,
    'updated' => 0,
    'areas_created' => 0,
    'areas_existing' => 0,
    'errors' => 0
];

// طباعة أول 5 أسماء وحدات لمراجعة سريعة
$preview = [];
foreach ($units as $u) {
    if (isset($u['Name'])) $preview[] = $u['Name'];
    if (count($preview) >= 5) break;
}
out("👀 أمثلة أولية للوحدات", ['sample' => $preview]);

$idx = 0;
foreach ($units as $unit) {
    $idx++;
    $unitname = trim($unit['Name'] ?? '');
    if ($unitFilter && $unitname !== $unitFilter) {
        continue; // تصفية اختيارية لوحدة محددة
    }

    if ($unitname === '') {
        out("⚠️ (#$idx) تخطي: اسم الوحدة فارغ");
        continue;
    }

    out("➡️ (#$idx) بدء وحدة", ['unit' => $unitname]);

    // شرطك الأصلي: لازم يكون P1_description موجود
    $p1 = trim($unit['P1_description'] ?? '');
    if ($p1 === '') {
        $stats['skipped_empty_p1']++;
        out("⚠️ تخطي الوحدة بسبب P1_description فارغ", ['unit' => $unitname]);
        continue;
    }

    try {
        // ---- تعريف التقييم grading_definitions ----
        out("🔎 البحث عن تعريف موجود", ['name' => $unitname, 'method' => 'btec']);
        $existing = $DB->get_record('grading_definitions', ['name' => $unitname, 'method' => 'btec']);

        if ($existing) {
            $definitionid = (int)$existing->id;
            out("ℹ️ تعريف موجود", [
                'definitionid' => $definitionid,
                'areaid_current' => (int)$existing->areaid,
                'timemodified' => (int)$existing->timemodified
            ]);

            // قبل الحذف: عدد المعايير القديم
            $oldcnt = criteria_count($definitionid);
            out("🧮 عدد المعايير قبل الحذف", ['criteria_before' => $oldcnt]);

            // حذف المعايير
            $DB->delete_records('gradingform_btec_criteria', ['definitionid' => $definitionid]);
            $afterdel = criteria_count($definitionid);
            out("🧹 حذف المعايير القديمة", ['criteria_after_delete' => $afterdel]);

            $action = 'Updated';
        } else {
            // إنشاء تعريف جديد (areaid=0 كما في منطقك)
            $definition = new stdClass();
            $definition->areaid = 0; // لا ربط بالـ area (منطقك القديم)
            $definition->name = $unitname;
            $definition->description = '';
            $definition->descriptionformat = FORMAT_HTML;
            $definition->status = 0; // draft
            $definition->copiedfromid = null;
            $definition->timecreated = time();
            $definition->timemodified = time();
            $definition->method = 'btec';
            $definition->usercreated = $USER->id ?? 0;
            $definition->usermodified = $USER->id ?? 0;

            $definitionid = (int)$DB->insert_record('grading_definitions', $definition);
            out("➕ إنشاء تعريف جديد", ['definitionid' => $definitionid]);

            $action = 'Created';
        }

        // ---- بناء 18 معيار (P/M/D) ----
        out("🧩 تجهيز المعايير (P1..P6, M1..M6, D1..D6) مع fallback 'Auto'");
        $fields = [];
        foreach (['P', 'M', 'D'] as $prefix) {
            for ($i = 1; $i <= 6; $i++) {
                $key = "{$prefix}{$i}_description";
                $desc = trim($unit[$key] ?? '');
                if ($desc === '') {
                    $desc = "{$prefix}{$i} - Auto";
                }
                $fields[] = ['shortname' => "{$prefix}{$i}", 'description' => $desc];
            }
        }

        // إدراج المعايير
        $sort = 1;
        $inserted = 0;
        foreach ($fields as $f) {
            $rec = new stdClass();
            $rec->definitionid = $definitionid;
            $rec->sortorder = $sort++;
            $rec->shortname = $f['shortname'];
            $rec->description = $f['description'];
            $rec->descriptionformat = FORMAT_HTML;
            $rec->descriptionmarkers = '';
            $rec->descriptionmarkersformat = FORMAT_HTML;
            $DB->insert_record('gradingform_btec_criteria', $rec);
            $inserted++;
            // اعرض كل معيار مُدرج
            out("↪️ إدراج معيار", ['definitionid' => $definitionid, 'shortname' => $f['shortname']]);
        }
        $finalcnt = criteria_count($definitionid);
        out("✅ عدد المعايير بعد الإدراج", ['criteria_after_insert' => $finalcnt, 'inserted_now' => $inserted]);

        if ($action === 'Created') {
            $stats['created']++;
        } else {
            $stats['updated']++;
        }

        // ---- grading_areas وفق منطقك ----
        $areaname = 'btec_' . md5($unitname);
        out("🔎 فحص grading_areas", ['areaname' => $areaname]);
        $existing_area = $DB->get_record('grading_areas', ['areaname' => $areaname, 'activemethod' => 'btec']);

        if (!$existing_area) {
            $contextid = 1;
            $course = $DB->get_record('course', ['fullname' => $unitname]);
            if ($course) {
                $context = context_course::instance($course->id);
                $contextid = $context->id;
            }
            $area = new stdClass();
            $area->contextid = $contextid;
            $area->component = 'core_grading';
            $area->areaname = $areaname;
            $area->activemethod = 'btec';

            $newareaid = (int)$DB->insert_record('grading_areas', $area);
            $stats['areas_created']++;
            out("🏷️ إنشاء grading_area", ['areaid' => $newareaid, 'contextid' => $contextid]);
        } else {
            $stats['areas_existing']++;
            out("ℹ️ grading_area موجود", ['areaid' => (int)$existing_area->id, 'contextid' => (int)$existing_area->contextid]);
        }

        $stats['processed']++;
        out("🎯 تم إتمام الوحدة", ['unit' => $unitname, 'action' => $action]);

    } catch (Throwable $e) {
        $stats['errors']++;
        out("❌ خطأ أثناء معالجة الوحدة", ['unit' => $unitname, 'error' => $e->getMessage()]);
    }
}

// ========== 4) الملخص ==========
step(4, 'ملخص التشغيل');
$dur = round(microtime(true) - $scriptStart, 3);
out("📊 إحصائيات", [
    'units_total' => $total,
    'processed' => $stats['processed'],
    'skipped_empty_p1' => $stats['skipped_empty_p1'],
    'created' => $stats['created'],
    'updated' => $stats['updated'],
    'areas_created' => $stats['areas_created'],
    'areas_existing' => $stats['areas_existing'],
    'errors' => $stats['errors'],
    'duration_sec' => $dur
]);

out("🏁 انتهى السكربت");
