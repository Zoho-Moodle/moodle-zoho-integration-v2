<?php
/**
 * probe_btec_subform_findpopulated.php
 * يمسح صفحات من BTEC ليعثر على أوّل سجل يحتوي صفوف داخل Subform المحدد،
 * ويطبع مفاتيح أول صف وعينة قيم غير فارغة. لا تعديل DB.
 *
 * GET:
 *   page=1            // صفحة البداية للـ list
 *   pages=3           // كم صفحة تمسحها (حد أعلى)
 *   per_page=50       // كم سجل بكل صفحة (<=200)
 *   subform=BTEC_Grading_Template_P1   // API Name للـ subform (افتراضي)
 *   hits=3            // اعرض أول N سجلات فيها صفوف subform (افتراضي 1)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

function http_get($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $info = curl_getinfo($ch);
    $errn = curl_errno($ch);
    $err  = $errn ? curl_error($ch) : null;
    curl_close($ch);
    return [$resp, $info, $errn, $err];
}

function summarize_rows($rows) {
    $out = [
        'count' => is_array($rows) ? count($rows) : 0,
        'row0_keys' => null,
        'row0_non_empty_sample' => (object)[],
    ];
    if (!is_array($rows) || empty($rows)) return $out;
    $r0 = $rows[0];
    if (is_array($r0)) {
        $out['row0_keys'] = array_keys($r0);
        $sample = [];
        foreach ($r0 as $k => $v) {
            if (is_scalar($v)) {
                $val = trim((string)$v);
                if ($val !== '') $sample[$k] = mb_strimwidth($val, 0, 160, '…', 'UTF-8');
            }
        }
        if ($sample) $out['row0_non_empty_sample'] = $sample;
    }
    return $out;
}

function t(){return microtime(true);} function dt($s){return round(microtime(true)-$s,4);}

try {
    // 1) token
    @file_get_contents('https://elearning.abchorizon.com/local/mb_zoho_sync/get_token.php');
    $tok = json_decode(@file_get_contents(__DIR__.'/token.json'), true);
    $access_token = $tok['access_token'] ?? '';
    if (!$access_token) {
        echo json_encode(['status'=>'error','message'=>'Token not found'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    $H = ['Authorization: Zoho-oauthtoken '.$access_token];

    // Params
    $startPage = max(1, (int)($_GET['page'] ?? 1));
    $pages     = max(1, (int)($_GET['pages'] ?? 3));
    $perPage   = max(1, min(200, (int)($_GET['per_page'] ?? 50)));
    $subform   = $_GET['subform'] ?? 'BTEC_Grading_Template_P1';
    $wantHits  = max(1, (int)($_GET['hits'] ?? 1));

    $out = [
        'status' => 'ok',
        'meta' => [
            'start_page' => $startPage,
            'pages_scan' => $pages,
            'per_page' => $perPage,
            'subform' => $subform,
            'hits_wanted' => $wantHits,
            'now' => date('c'),
        ],
        'stats' => [
            'list_requests' => 0,
            'byid_requests' => 0,
            'records_scanned' => 0,
            'hits_found' => 0,
            'duration_sec' => 0,
        ],
        'steps' => [],
        'hits' => [],  // كل عنصر: id, summary (count/row0_keys/sample)
    ];

    $t0 = t();
    $hits = [];

    for ($p = 0; $p < $pages; $p++) {
        $page = $startPage + $p;
        $urlList = "https://www.zohoapis.com/crm/v2/BTEC?page={$page}&per_page={$perPage}";
        $s = t();
        [$resp,$info,$errn,$err] = http_get($urlList, $H);
        $out['stats']['list_requests']++;
        $out['steps'][] = ['step' => 'list', 'page' => $page, 'per_page' => $perPage, 'http_code' => $info['http_code']??null, 'errno' => $errn, 'err' => $err, 'duration_sec' => dt($s)];
        if ($errn || ($info['http_code']??0) >= 400) break;

        $rows = json_decode($resp, true)['data'] ?? [];
        if (!$rows) break;

        foreach ($rows as $r) {
            $id = $r['id'] ?? null;
            if (!$id) continue;

            // get-by-id (لازم يرجّع subform لو فيه بيانات)
            $byIdUrl = "https://www.zohoapis.com/crm/v2/BTEC/{$id}";
            $s2 = t();
            [$resp2,$info2,$errn2,$err2] = http_get($byIdUrl, $H);
            $out['stats']['byid_requests']++;
            $out['stats']['records_scanned']++;
            $ok = (!$errn2 && ($info2['http_code']??0) < 400);

            if ($ok) {
                $wrap = json_decode($resp2, true);
                $rec  = $wrap['data'][0] ?? [];
                // subform ممكن يرجع null/[]/array-of-rows
                $sf   = $rec[$subform] ?? null;
                $sum  = summarize_rows($sf);

                if ($sum['count'] > 0) {
                    $hits[] = [
                        'id' => (string)$id,
                        'summary' => $sum,
                    ];
                }
            }

            $out['steps'][] = [
                'step' => 'get_by_id',
                'id' => (string)$id,
                'http_code' => $info2['http_code']??null,
                'errno' => $errn2,
                'err' => $err2,
                'duration_sec' => dt($s2),
                'has_subform_key' => isset($rec) ? array_key_exists($subform, $rec) : null,
                'subform_type' => isset($sf) ? (is_array($sf) ? 'array' : gettype($sf)) : 'absent',
                'subform_count' => $sum['count'],
                'row0_keys' => $sum['row0_keys'],
            ];

            if (count($hits) >= $wantHits) break 2; // خلصنا
        }
    }

    $out['stats']['hits_found'] = count($hits);
    $out['hits'] = $hits;
    $out['stats']['duration_sec'] = dt($t0);

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
