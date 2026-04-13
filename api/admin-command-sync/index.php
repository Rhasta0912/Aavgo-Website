<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$token = aavgo_get_website_api_token();
if ($token === '') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Website sync token is not configured.',
    ], 503);
    exit;
}

$decoded = aavgo_decode_json_body();
$providedToken = aavgo_extract_sync_token($decoded);
if ($providedToken === '' || !hash_equals($token, $providedToken)) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Unauthorized.',
    ], 401);
    exit;
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'GET') {
    aavgo_json_response([
        'ok' => true,
        'commands' => aavgo_get_pending_admin_commands(25),
    ]);
    exit;
}

if ($requestMethod !== 'POST') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], 405);
    exit;
}

$results = is_array($decoded['results'] ?? null) ? $decoded['results'] : [];
$updated = aavgo_apply_command_results($results);

aavgo_json_response([
    'ok' => true,
    'updated' => count($updated),
]);
