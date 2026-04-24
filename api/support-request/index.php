<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$user = aavgo_require_access('user');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], 405);
    exit;
}

$decoded = aavgo_decode_json_body();
aavgo_require_csrf($decoded);

$type = strtolower(trim((string) ($decoded['type'] ?? '')));
if (!in_array($type, ['feature', 'bug'], true)) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Choose feature request or bug report.',
    ], 422);
    exit;
}

$title = trim((string) ($decoded['title'] ?? ''));
$message = trim((string) ($decoded['message'] ?? ''));
$page = trim((string) ($decoded['page'] ?? ''));
if ($title === '' || strlen($title) > 140) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Add a short title, 140 characters or less.',
    ], 422);
    exit;
}
if ($message === '' || strlen($message) > 1200) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Add details, 1200 characters or less.',
    ], 422);
    exit;
}

$board = aavgo_read_developer_board();
$requests = is_array($board['supportRequests'] ?? null) ? $board['supportRequests'] : [];
$now = gmdate('c');
$entry = [
    'id' => aavgo_create_identifier('support'),
    'type' => $type,
    'status' => 'open',
    'title' => $title,
    'message' => $message,
    'page' => $page,
    'createdAt' => $now,
    'updatedAt' => $now,
    'requester' => [
        'id' => (string) ($user['id'] ?? ''),
        'name' => aavgo_display_name($user),
        'role' => aavgo_user_role_summary($user),
    ],
];

array_unshift($requests, $entry);
$board['supportRequests'] = array_slice($requests, 0, 120);

if (!aavgo_write_developer_board($board)) {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Support request could not be saved right now.',
    ], 500);
    exit;
}

aavgo_json_response([
    'ok' => true,
    'message' => 'Support request sent.',
    'data' => $entry,
]);
