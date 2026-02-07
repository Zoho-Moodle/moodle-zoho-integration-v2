<?php
// update_zoho_templates_status.php
// يتحقق إذا كان في grading definition باسم الـ unit في DB مودل، ويحدّث حالته بزوهو.

require('../../config.php'); // Moodle bootstrap
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

// ====== إدخالات ======
// نادِيه هيك من Zoho Scheduled Action بعد دقيقتين:
// https://elearning.abchorizon.com/local/mb_zoho_sync/update_zoho_templates_status.php?id=${BTEC.ID}&unit=${BTEC.Name}
$zoho_id = isset($_GET['id'])   ? trim($_GET['id'])   : '';
$unit    = isset($_GET['unit']) ? trim($_GET['unit']) : '';

if ($zoho_id === '' || $unit === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'missing_id_or_unit']);
    exit;
}

// ====== دوال Zoho بسيطة ======
const ZOHO_DC     = 'https://www.zohoapis.com';
const ZOHO_MODULE = 'BTEC';

function get_access_token() {
    $tokenFile = __DIR__ . '/token.json';
    $tokenData = is_file($tokenFile) ? json_decode(file_get_contents($tokenFile), true) : [];
    return $tokenData['access_token'] ?? '';
}

function zoho_update_record($access_token, $record_id, array $fields) {
    $url = ZOHO_DC . '/crm/v2/' . rawurlencode(ZOHO_MODULE);
    $payload = json_encode(['data' => [array_merge(['id' => $record_id], $fields)]], JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Zoho-oauthtoken ' . $access_token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body, $err];
}

// ====== تحقق من DB مودل ======
global $DB;
// نتحقق من وجود تعريف BTEC باسـم الـ unit
$sql = "SELECT id
          FROM {grading_definitions}
         WHERE name = :name AND method = 'btec'
      ORDER BY timemodified DESC";
$params = ['name' => $unit];
$def_exists = $DB->record_exists_sql("SELECT 1 FROM ({$sql}) x", $params);

// ====== حسم الحالة وتحديث زوهو ======
$status = $def_exists ? 'Synced' : 'Not Synced';

$token = get_access_token();
if ($token === '') {
    echo json_encode(['ok'=>false,'error'=>'no_access_token','decided'=>$status]);
    exit;
}

$fields = [
    'Moodle_Grading_Template' => $status,
    'Last_Sync_with_Moodle'   => gmdate('c'),
];

list($zc, $zbody, $zerr) = zoho_update_record($token, $zoho_id, $fields);

// ردّ خفيف (الـ Scheduled Action ما بهمو المحتوى، المهم ما نطوّل)
echo json_encode([
    'ok'      => ($zc >= 200 && $zc < 300),
    'status'  => $status,
    'http'    => $zc,
]);
