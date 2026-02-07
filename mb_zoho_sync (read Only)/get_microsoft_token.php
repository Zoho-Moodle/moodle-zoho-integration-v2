<?php

// معلومات الاتصال بـ Microsoft (Azure)
$tenantId = '9d05f014-b335-489d-885e-819f105e8128';  // استبدل بـ Tenant ID
$clientId = '7bf2ed77-e6c5-4952-86e6-49e3932ff814';   // استبدل بـ Client ID
$clientSecret = 'Zx.8Q~OwZAVqWgw.L1IUtzSqP4Ys54YIRPj2fdlw';  // استبدل بـ Client Secret
$scope = 'https://graph.microsoft.com/.default';  // Microsoft Graph API Scope
$grantType = 'client_credentials';  // Grant Type

// بناء الرابط للحصول على التوكن
$tokenUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";

// بيانات الطلب
$data = [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => $scope,
    'grant_type' => $grantType,
];

// إرسال الطلب للحصول على التوكن باستخدام cURL بدلاً من file_get_contents
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);

// التحقق من وجود أخطاء في الاتصال
if(curl_errno($ch)) {
    echo "❌ cURL Error: " . curl_error($ch);
} else {
    $responseData = json_decode($response, true);
    if (isset($responseData['access_token'])) {
        // تخزين التوكن في ملف JSON لاستخدامه لاحقاً
        $tokenData = ['access_token' => $responseData['access_token']];
        file_put_contents(__DIR__ . '/microsoft_token.json', json_encode($tokenData));
        echo "✅ Token fetched and saved successfully.";
    } else {
        echo "❌ Failed to fetch Microsoft Access Token. Response: " . json_encode($responseData);
    }
}

curl_close($ch);
?>
