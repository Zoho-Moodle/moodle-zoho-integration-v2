<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: application/json; charset=utf-8');

try {
    // ====== 1. استدعاء get_token.php لتجديد التوكن ======
    $tokenEndpoint = $CFG->wwwroot . '/local/mb_zoho_sync/get_token.php';
    $ch = curl_init($tokenEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to refresh Zoho token (HTTP $httpCode)");
    }

    // ====== 2. الانتظار لحظة ======
    sleep(1);

    // ====== 3. قراءة التوكن من token.json ======
    $tokenFile = __DIR__ . '/token.json';
    if (!file_exists($tokenFile)) {
        throw new Exception("token.json not found");
    }
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if (empty($tokenData['access_token'])) {
        throw new Exception("Invalid token.json or missing access_token");
    }
    $accessToken = $tokenData['access_token'];

    $headers = [
        "Authorization: Zoho-oauthtoken $accessToken",
        "Content-Type: application/json"
    ];

    // ====== دوال مساعدة ======
    function zoho_get_records($module, $headers) {
        $url = "https://www.zohoapis.com/crm/v2/$module?fields=id,Academic_Email";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        return $data['data'] ?? [];
    }

    function zoho_update_field($module, $recordId, $fieldName, $value, $headers) {
        $url = "https://www.zohoapis.com/crm/v2/$module/$recordId";
        $payload = json_encode(['data' => [[ $fieldName => $value ]]]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        return json_decode($resp, true);
    }

    // ====== 4. جلب المستخدمين من Moodle ======
    global $DB;
    $moodleUsers = $DB->get_records('user', ['deleted' => 0], '', 'id, username');

    // ====== 5. جلب السجلات من Zoho ======
    $students = zoho_get_records('BTEC_Students', $headers);
    $teachers = zoho_get_records('BTEC_Teachers', $headers);

    $countStudents = 0;
    $countTeachers = 0;

    // ====== 6. مطابقة الطلاب ======
    foreach ($students as $s) {
        $email = strtolower(trim($s['Academic_Email'] ?? ''));
        foreach ($moodleUsers as $u) {
            if (strtolower($u->username) === $email) {
                zoho_update_field('BTEC_Students', $s['id'], 'Student_Moodle_ID', $u->id, $headers);
                $countStudents++;
                break;
            }
        }
    }

    // ====== 7. مطابقة المدرسين ======
    foreach ($teachers as $t) {
        $email = strtolower(trim($t['Academic_Email'] ?? ''));
        foreach ($moodleUsers as $u) {
            if (strtolower($u->username) === $email) {
                zoho_update_field('BTEC_Teachers', $t['id'], 'Teacher_Moodle_ID', $u->id, $headers);
                $countTeachers++;
                break;
            }
        }
    }

    // ====== 8. النتيجة ======
    echo json_encode([
        'status' => 'success',
        'students_updated' => $countStudents,
        'teachers_updated' => $countTeachers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

exit;
