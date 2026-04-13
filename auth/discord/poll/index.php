<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$state = trim((string) ($_GET['state'] ?? ''));
$validatedState = $state !== '' ? aavgo_validate_oauth_state($state) : null;

if ($state === '' || $validatedState === null) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'ready' => false,
        'error' => 'invalid_state',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$handoff = aavgo_peek_auth_handoff($state);

echo json_encode([
    'ok' => true,
    'ready' => is_array($handoff),
], JSON_UNESCAPED_SLASHES);
