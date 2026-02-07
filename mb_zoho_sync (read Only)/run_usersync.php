#!/usr/bin/env php
<?php
// CLI or Web execution
if (PHP_SAPI === 'cli') {
    define('CLI_SCRIPT', true);
    require(__DIR__ . '/../../config.php');
} else {
    require(__DIR__ . '/../../config.php');
    require_login();
    if (!is_siteadmin()) {
        echo '<pre>❌ Access denied</pre>';
        exit;
    }
}

set_time_limit(0);
raise_memory_limit(MEMORY_EXTRA);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==== Token (assumed working) ====
$tokenPath  = __DIR__ . '/token.json';
$tokenData  = json_decode(@file_get_contents($tokenPath), true) ?: [];
$accessToken = $tokenData['access_token'] ?? '';
if (!$accessToken) {
    echo "❌ Failed to load access token from $tokenPath\n";
    exit(1);
}

// ==== Zoho helper ====
function zoho_request(string $method, string $url, string $token, ?array $body = null): array {
    $ch = curl_init($url);
    $headers = [
        "Authorization: Zoho-oauthtoken $token",
        "Content-Type: application/json",
        "Accept: application/json",
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if (!is_null($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode($raw ?: '[]', true);
    return [$http, $decoded, $err];
}

// ==== Modules & fields ====
$modules = [
    ['name' => 'BTEC_Students', 'field' => 'Student_Moodle_ID'],
    ['name' => 'BTEC_Teachers', 'field' => 'Teacher_Moodle_ID'],
];

// ==== Get ALL Moodle users (include deleted & suspended) ====
global $DB;
$fields = 'id, username, email, firstname, lastname, deleted, suspended';
$users = $DB->get_records('user', null, 'id ASC', $fields);
echo "👥 Total users (all, including deleted/suspended): " . count($users) . "\n";

$results = [];
$ts = date('c');

foreach ($users as $user) {
    // بإمكانك حماية admin/guest إن رغبت:
    if (in_array($user->username, ['guest','admin'], true)) {
        // مرّرهم إذا بدك تزامنهم أيضًا: فقط احذف هذا الـ continue
        continue;
    }

    $entry = [
        'timestamp'      => $ts,
        'moodle_id'      => (string)$user->id,
        'username'       => (string)$user->username,
        'email'          => (string)$user->email,
        'deleted'        => (int)$user->deleted,
        'suspended'      => (int)$user->suspended,
        'zoho_found'     => false,
        'zoho_module'    => null,
        'zoho_id'        => null,
        'matched_field'  => null,   // Academic_Email | Email
        'target_field'   => null,   // Student_Moodle_ID | Teacher_Moodle_ID
        'previous_value' => null,
        'new_value'      => (string)$user->id,
        'updated'        => false,
        'status'         => 'not_found_in_zoho',
        'http_code'      => null,
        'zoho_code'      => null,
        'zoho_message'   => null,
    ];

    // نطبّق نفس المنطق على كل موديول (طلاب/معلمين)
    foreach ($modules as $mod) {
        $moduleName = $mod['name'];
        $targetField = $mod['field'];

        // 1) جرّب match على Academic_Email == username
        $crit1 = '(Academic_Email:equals:' . $user->username . ')';
        $url1  = 'https://www.zohoapis.com/crm/v2/' . $moduleName . '/search?criteria=' . urlencode($crit1);
        [$h1, $r1, $e1] = zoho_request('GET', $url1, $accessToken);

        $record = null;
        $matchedField = null;

        if (!empty($r1['data'][0]['id'])) {
            $record = $r1['data'][0];
            $matchedField = 'Academic_Email';
        } else {
            // 2) إذا ما لقى، جرّب Email == username
            $crit2 = '(Email:equals:' . $user->username . ')';
            $url2  = 'https://www.zohoapis.com/crm/v2/' . $moduleName . '/search?criteria=' . urlencode($crit2);
            [$h2, $r2, $e2] = zoho_request('GET', $url2, $accessToken);

            if (!empty($r2['data'][0]['id'])) {
                $record = $r2['data'][0];
                $matchedField = 'Email';
            }
        }

        if ($record) {
            $zohoId = $record['id'];
            $prev   = $record[$targetField] ?? null;

            // حدّث دائمًا قيمة الـ Moodle ID بغض النظر عن السابق
            $updateUrl = 'https://www.zohoapis.com/crm/v2/' . $moduleName . '/' . $zohoId;
            $body = ['data' => [ [ $targetField => (string)$user->id ] ]];

            [$uh, $ur, $ue] = zoho_request('PUT', $updateUrl, $accessToken, $body);
            $ok = !empty($ur['data'][0]['code']) && $ur['data'][0]['code'] === 'SUCCESS';

            $entry['zoho_found']   = true;
            $entry['zoho_module']  = $moduleName;
            $entry['zoho_id']      = $zohoId;
            $entry['matched_field']= $matchedField;
            $entry['target_field'] = $targetField;
            $entry['previous_value']= $prev;
            $entry['updated']      = $ok;
            $entry['status']       = $ok ? 'updated' : 'update_failed';
            $entry['http_code']    = $uh;
            $entry['zoho_code']    = $ur['data'][0]['code']    ?? null;
            $entry['zoho_message'] = $ur['data'][0]['message'] ?? ($ue ?: null);

            echo $ok
                ? "✅ {$moduleName} set {$targetField}={$user->id} for {$user->username} (ZohoID: {$zohoId}, match: {$matchedField})\n"
                : "⚠️ Update failed for {$user->username} in {$moduleName} (ZohoID: {$zohoId}, HTTP {$uh})\n";

            // إذا لقيته في أحد الموديولين ما في داعي تكمّل على الثاني (احذف break لو بدك تحدث الاثنين)
            break;
        }
        // إذا ما لقي بس بهالموديول، جرّب الموديول التالي ضمن اللوب
    }

    $results[] = $entry;
}

// ==== Save JSON log ====
$logFile = __DIR__ . '/user_sync_log.json';
file_put_contents($logFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "📁 Log saved to $logFile\n";
