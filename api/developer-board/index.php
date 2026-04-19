<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$user = aavgo_require_access('admin');
if (!aavgo_user_is_developer($user)) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Developer access is required for this board.',
    ], 403);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    aavgo_json_response([
        'ok' => true,
        'data' => aavgo_read_developer_board(),
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], 405);
    exit;
}

$decoded = aavgo_decode_json_body();
$board = is_array($decoded['state'] ?? null) ? $decoded['state'] : $decoded;
$tasks = is_array($board['tasks'] ?? null) ? $board['tasks'] : [];
$history = is_array($board['history'] ?? null) ? $board['history'] : [];
$audit = is_array($board['audit'] ?? null) ? $board['audit'] : [];

$saved = aavgo_write_developer_board([
    'tasks' => $tasks,
    'history' => $history,
    'audit' => $audit,
]);

if (!$saved) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'The shared developer board could not be saved right now.',
    ], 500);
    exit;
}

aavgo_json_response([
    'ok' => true,
    'message' => 'Developer board saved.',
    'data' => aavgo_read_developer_board(),
]);
