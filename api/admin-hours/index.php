<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$user = aavgo_require_access('admin');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    aavgo_json_response(aavgo_build_admin_board_payload($user));
    return;
}

if ($method !== 'POST') {
    aavgo_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    return;
}

if (!aavgo_has_hours_bridge()) {
    aavgo_json_response(['ok' => false, 'error' => 'The hours bridge is not configured yet.'], 503);
    return;
}

if (!function_exists('curl_init')) {
    aavgo_json_response(['ok' => false, 'error' => 'PHP cURL is required to update hours.'], 500);
    return;
}

$decoded = aavgo_decode_json_body();
$action = trim((string) ($decoded['action'] ?? ''));
$payload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];

if ($action === '') {
    aavgo_json_response(['ok' => false, 'error' => 'Missing hours action.'], 400);
    return;
}

$proxyPayload = [
    'action' => $action,
    'payload' => $payload,
    'actor' => [
        'discordId' => (string) ($user['id'] ?? ''),
        'name' => aavgo_display_name($user),
        'roleSummary' => aavgo_user_role_summary($user),
    ],
];

$url = aavgo_get_website_api_url() . '/api/website/admin-hours';
$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . aavgo_get_website_api_token(),
    ],
    CURLOPT_POSTFIELDS => json_encode($proxyPayload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($response === false || $curlError !== '') {
    aavgo_json_response([
      'ok' => false,
      'error' => 'The hours update could not reach the bot.',
    ], 502);
    return;
}

$decodedResponse = json_decode((string) $response, true);
if (!is_array($decodedResponse)) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'The bot returned an invalid hours response.',
    ], 502);
    return;
}

aavgo_json_response($decodedResponse, $httpCode > 0 ? $httpCode : 200);
