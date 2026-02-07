<?php
// local/mb_zoho_sync/get_token.php
$client_id     = '1000.MWF0F07X5TIIH74MLQX1YGZ1PEW8JD';
$client_secret = '3efa2af391616f94296ef69b1e3b3de55fe1846fb7';
$refresh_token = '1000.23e6352845195b9beb2871ceb5ac662e.2ab701643b43af368e7e29b7e4531229';

$url = 'https://accounts.zoho.com/oauth/v2/token'; // US
$postData = http_build_query([
    'grant_type'    => 'refresh_token',
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'refresh_token' => $refresh_token
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $postData,
  CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT        => 20,
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!empty($result['access_token'])) {
    file_put_contents(__DIR__.'/token.json', json_encode([
        'access_token' => $result['access_token'],
        'created_at'   => time()
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo "✅ Access Token saved successfully.\n";
} else {
    echo "❌ Failed to get token:\n$response\n";
}
