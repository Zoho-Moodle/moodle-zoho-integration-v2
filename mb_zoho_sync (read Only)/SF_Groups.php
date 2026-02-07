<?php
$tokenPath = __DIR__ . '/microsoft_token.json';
file_get_contents('https://elearning.abchorizon.com/local/mb_zoho_sync/get_microsoft_token.php');
sleep(1);

if (!file_exists($tokenPath)) {
    echo "<p style='color:red; text-align:center;'>❌ Token file not found.</p>";
    exit;
}

$tokenData = json_decode(file_get_contents($tokenPath), true);
$access_token = $tokenData['access_token'] ?? '';

if (!$access_token) {
    echo "<p style='color:red; text-align:center;'>❌ Token missing.</p>";
    exit;
}

$ownerEmail = 'student.affairs@abchorizon.com';

function apiRequest($url, $token) {
    $headers = [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 1. Get user ID
$userQuery = urlencode("userPrincipalName eq '$ownerEmail'");
$userInfo = apiRequest("https://graph.microsoft.com/v1.0/users?\$filter=$userQuery", $access_token);
$ownerId = $userInfo['value'][0]['id'] ?? null;

if (!$ownerId) {
    die("<p style='color:red;'>❌ لم يتم العثور على الحساب.</p>");
}

// 2. Get all groups
$groups = apiRequest("https://graph.microsoft.com/v1.0/groups?\$top=999", $access_token);
$groupsList = $groups['value'] ?? [];

$results = [];

foreach ($groupsList as $group) {
    $groupId = $group['id'];
    $groupName = $group['displayName'];
    $createdDate = $group['createdDateTime'] ?? 'N/A';

    // Get owners
    $owners = apiRequest("https://graph.microsoft.com/v1.0/groups/$groupId/owners", $access_token);
    $ownerEmails = array_column($owners['value'], 'mail');

    if (in_array($ownerEmail, $ownerEmails)) {
        // Get members
        $members = apiRequest("https://graph.microsoft.com/v1.0/groups/$groupId/members", $access_token);
        $validMembers = [];

        foreach ($members['value'] as $member) {
            if (
                isset($member['mail']) &&
                !str_contains($member['userPrincipalName'] ?? '', '#EXT#') &&
                strpos($member['userPrincipalName'] ?? '', 'bot') === false
            ) {
                $validMembers[] = $member['mail'];
            }
        }

        $results[] = [
            'group' => $groupName,
            'id' => $groupId,
            'created' => $createdDate,
            'members' => $validMembers
        ];
    }
}

// 3. Display
echo "<h2>✅ مجموعات يملكها $ownerEmail</h2>";
foreach ($results as $groupData) {
    echo "<h3>{$groupData['group']} ({$groupData['id']})</h3>";
    echo "<p>📅 تاريخ الإنشاء: {$groupData['created']}</p><ul>";
    foreach ($groupData['members'] as $email) {
        echo "<li>$email</li>";
    }
    echo "</ul>";
}

// 4. Save to file
$savePath = __DIR__ . '/student_affairs_groups_' . date('Ymd_His') . '.json';
file_put_contents($savePath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "<p style='color:green;'>📁 تم حفظ الملف في: $savePath</p>";
?>
