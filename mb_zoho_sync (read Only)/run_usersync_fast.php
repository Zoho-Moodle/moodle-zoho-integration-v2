#!/usr/bin/env php
<?php
// Bulk sync Moodle ID to Zoho, with forced token refresh first.
// - يشمل كل المستخدمين (deleted/suspended)
// - يطابق username مع Academic_Email ثم Email
// - يكتب دائمًا حقل Moodle ID بالقيمة الجديدة
// - يجلب Zoho bulk ويعمل batch updates (100/طلب)

if (PHP_SAPI === 'cli') {
    define('CLI_SCRIPT', true);
    require(__DIR__ . '/../../config.php');
} else {
    require(__DIR__ . '/../../config.php');
    require_login();
    if (!is_siteadmin()) { echo "<pre>❌ Access denied</pre>"; exit; }
}

set_time_limit(0);
raise_memory_limit(MEMORY_EXTRA);
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

// ====== Token refresh then read from token.json ======
$getTokenUrl = 'https://elearning.abchorizon.com/local/mb_zoho_sync/get_token.php'; // عدّلها إذا لازم
$tokenPath   = __DIR__ . '/token.json';
$apiBase     = 'https://www.zohoapis.com/crm/v2';

echo "🔐 Refreshing Zoho token...\n";
@file_get_contents($getTokenUrl);
usleep(500000); // 0.5s

$tokenData   = json_decode(@file_get_contents($tokenPath), true) ?: [];
$accessToken = $tokenData['access_token'] ?? '';
if (!$accessToken) {
    fwrite(STDERR, "❌ Failed to load access token from {$tokenPath}\n");
    exit(1);
}

// ====== Modules ======
$modules = [
    ['name' => 'BTEC_Students', 'field' => 'Student_Moodle_ID', 'want' => ['id','Academic_Email','Email','Student_Moodle_ID']],
    ['name' => 'BTEC_Teachers', 'field' => 'Teacher_Moodle_ID', 'want' => ['id','Academic_Email','Email','Teacher_Moodle_ID']],
];
$batchSize = 100; // Zoho max 100

// ====== HTTP helper ======
function zreq(string $method, string $url, string $token, ?array $body=null, int $timeout=30): array {
    $ch = curl_init($url);
    $headers = [
        "Authorization: Zoho-oauthtoken $token",
        "Accept: application/json",
        "Content-Type: application/json",
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    if (!is_null($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $dec = json_decode($raw ?: '[]', true);
    return [$http, $dec, $err];
}

// ====== Fetch all records from a module (paged) ======
function fetchModuleAll(string $module, array $fields, string $apiBase, string $token): array {
    $records = [];
    $page = 1; $perPage = 200;
    do {
        $fieldsParam = '&fields=' . urlencode(implode(',', $fields));
        $url = "{$apiBase}/{$module}?page={$page}&per_page={$perPage}{$fieldsParam}";
        [$http,$res,$e] = zreq('GET',$url,$token,null,30);

        if ($http === 429) { sleep(2); continue; }
        if ($http >= 400) {
            fwrite(STDERR, "⚠️  GET {$module} page {$page} HTTP {$http} ".($e?:'')."\n");
            break;
        }

        $data = $res['data'] ?? [];
        $count = count($data);
        $records = array_merge($records, $data);
        fwrite(STDOUT, "⬇️  {$module} page {$page} (+{$count}) total=".count($records)."\n");
        $page++;
    } while (!empty($data));

    return $records;
}

// ====== Build Zoho indexes (academic/email) ======
$index = [
    'BTEC_Students' => ['byAcademic'=>[], 'byEmail'=>[], 'field'=>'Student_Moodle_ID'],
    'BTEC_Teachers' => ['byAcademic'=>[], 'byEmail'=>[], 'field'=>'Teacher_Moodle_ID'],
];

foreach ($modules as $m) {
    $mod  = $m['name'];
    $want = $m['want'];
    $list = fetchModuleAll($mod, $want, $apiBase, $accessToken);

    foreach ($list as $r) {
        $id  = $r['id'] ?? null;
        if (!$id) continue;
        $acad = trim((string)($r['Academic_Email'] ?? ''));
        $mail = trim((string)($r['Email'] ?? ''));
        if ($acad !== '') $index[$mod]['byAcademic'][strtolower($acad)] = $id;
        if ($mail !== '') $index[$mod]['byEmail'][strtolower($mail)]     = $id;
    }
    fwrite(STDOUT, "✅ Indexed {$mod}: acad=".count($index[$mod]['byAcademic']).", email=".count($index[$mod]['byEmail'])."\n");
}

// ====== Load ALL Moodle users (include deleted/suspended) ======
global $DB;
$fields = 'id, username, email, deleted, suspended';
$users  = $DB->get_records('user', null, 'id ASC', $fields);
fwrite(STDOUT, "👥 Moodle users (all): ".count($users)."\n");

// ====== Build updates per module ======
$updates = [
    'BTEC_Students' => [], // ['id'=>zohoId, 'Student_Moodle_ID'=>'123']
    'BTEC_Teachers' => [],
];
$stats = ['matched'=>0, 'matched_acad'=>0, 'matched_email'=>0];

foreach ($users as $u) {
    $username = trim((string)$u->username);
    if ($username === '') continue;

    $key = strtolower($username);

    foreach (['BTEC_Students','BTEC_Teachers'] as $mod) {
        $targetField = $index[$mod]['field'];
        $zohoId = $index[$mod]['byAcademic'][$key] ?? null;
        $matchBy = null;

        if ($zohoId) {
            $matchBy = 'Academic_Email';
        } else {
            $zohoId = $index[$mod]['byEmail'][$key] ?? null;
            if ($zohoId) $matchBy = 'Email';
        }

        if ($zohoId) {
            $updates[$mod][] = ['id' => $zohoId, $targetField => (string)$u->id];
            $stats['matched']++;
            if ($matchBy === 'Academic_Email') $stats['matched_acad']++; else $stats['matched_email']++;
        }
    }
}

fwrite(STDOUT, "🔎 Matches total={$stats['matched']} (acad={$stats['matched_acad']}, email={$stats['matched_email']})\n");

// ====== Batch updates ======
function batchUpdate(string $module, array $payloads, string $apiBase, string $token, int $batchSize): array {
    if (empty($payloads)) return [0,0];
    $ok=0; $fail=0;
    $chunks = array_chunk($payloads, $batchSize);
    foreach ($chunks as $i => $chunk) {
        $url  = "{$apiBase}/{$module}";
        $body = ['data' => $chunk];

        [$http,$res,$e] = zreq('PUT',$url,$token,$body,60);
        if ($http === 429) { sleep(2); [$http,$res,$e] = zreq('PUT',$url,$token,$body,60); }

        if ($http >= 400) {
            fwrite(STDERR, "💥 PUT {$module} batch ".($i+1)."/".count($chunks)." HTTP {$http} ".($e?:'')."\n");
            $fail += count($chunk);
            continue;
        }

        $data = $res['data'] ?? [];
        foreach ($data as $row) {
            if (($row['code'] ?? '') === 'SUCCESS') $ok++; else $fail++;
        }
        fwrite(STDOUT, "✍️  {$module} batch ".($i+1)."/".count($chunks)." → ok={$ok} fail={$fail}\n");
    }
    return [$ok,$fail];
}

list($okS,$failS) = batchUpdate('BTEC_Students', $updates['BTEC_Students'], $apiBase, $accessToken, $batchSize);
list($okT,$failT) = batchUpdate('BTEC_Teachers', $updates['BTEC_Teachers'], $apiBase, $accessToken, $batchSize);

// ====== Summary + JSON log ======
$log = [
    'ts' => date('c'),
    'matched_total' => $stats['matched'],
    'matched_by_academic' => $stats['matched_acad'],
    'matched_by_email' => $stats['matched_email'],
    'students_updates' => count($updates['BTEC_Students']),
    'teachers_updates' => count($updates['BTEC_Teachers']),
    'students_ok' => $okS, 'students_fail' => $failS,
    'teachers_ok' => $okT, 'teachers_fail' => $failT,
];
file_put_contents(__DIR__.'/user_sync_log.json', json_encode($log, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

echo "——— Summary ———\n";
echo "BTEC_Students: ok={$okS} fail={$failS}\n";
echo "BTEC_Teachers: ok={$okT} fail={$failT}\n";
echo "📁 Log saved to ".(__DIR__.'/user_sync_log.json')."\n";
