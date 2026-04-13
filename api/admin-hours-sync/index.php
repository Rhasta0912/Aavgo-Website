<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$decoded = aavgo_decode_json_body();
$token = aavgo_get_website_api_token();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], 405);
    exit;
}

if ($token === '') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Hours sync token is not configured on the website.',
    ], 503);
    exit;
}

$providedToken = aavgo_extract_sync_token($decoded);
if ($providedToken === '' || !hash_equals($token, $providedToken)) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Unauthorized.',
    ], 401);
    exit;
}

if (!is_array($decoded['data'] ?? null)) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Invalid snapshot payload.',
    ], 400);
    exit;
}

$snapshotData = $decoded['data'];
$snapshot = [
    'ok' => true,
    'configured' => true,
    'source' => 'bot_push',
    'syncedAt' => gmdate('c'),
    'data' => [
        'generatedAt' => trim((string) ($snapshotData['generatedAt'] ?? '')) ?: gmdate('c'),
        'summary' => is_array($snapshotData['summary'] ?? null) ? $snapshotData['summary'] : [],
        'meta' => is_array($snapshotData['meta'] ?? null) ? $snapshotData['meta'] : [],
        'teams' => is_array($snapshotData['teams'] ?? null) ? $snapshotData['teams'] : [],
        'people' => is_array($snapshotData['people'] ?? null) ? $snapshotData['people'] : [],
    ],
];

if (!aavgo_write_json_file(aavgo_get_hours_snapshot_path(), $snapshot)) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Failed to persist snapshot on the website.',
    ], 500);
    exit;
}

aavgo_json_response([
    'ok' => true,
    'snapshot_path' => aavgo_get_hours_snapshot_path(),
    'syncedAt' => $snapshot['syncedAt'],
]);
