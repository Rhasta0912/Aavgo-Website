<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function aavgo_sync_request_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $candidates = [
        $_SERVER[$serverKey] ?? '',
        $_SERVER['REDIRECT_' . $serverKey] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string) $candidate);
        if ($value !== '') {
            return $value;
        }
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $headerName => $value) {
                if (strcasecmp((string) $headerName, $name) === 0) {
                    $normalizedValue = trim((string) $value);
                    if ($normalizedValue !== '') {
                        return $normalizedValue;
                    }
                }
            }
        }
    }

    return '';
}

function aavgo_sync_extract_token(array $decodedPayload): string
{
    $authorization = aavgo_sync_request_header('Authorization');
    if (
        $authorization !== ''
        && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1
    ) {
        $bearerToken = trim((string) ($matches[1] ?? ''));
        if ($bearerToken !== '') {
            return $bearerToken;
        }
    }

    foreach (['X-Aavgo-Token', 'X-Aavgo-Website-Token'] as $headerName) {
        $headerValue = aavgo_sync_request_header($headerName);
        if ($headerValue !== '') {
            return $headerValue;
        }
    }

    $bodyToken = trim((string) ($decodedPayload['token'] ?? ''));
    return $bodyToken;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], JSON_PRETTY_PRINT);
    exit;
}

$token = aavgo_get_website_api_token();
if ($token === '') {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'Hours sync token is not configured on the website.',
    ], JSON_PRETTY_PRINT);
    exit;
}

$rawBody = file_get_contents('php://input');
$decoded = json_decode($rawBody ?: '', true);
$providedToken = aavgo_sync_extract_token(is_array($decoded) ? $decoded : []);

if ($providedToken === '' || !hash_equals($token, $providedToken)) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Unauthorized.',
    ], JSON_PRETTY_PRINT);
    exit;
}

if (!is_array($decoded) || !is_array($decoded['data'] ?? null)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid snapshot payload.',
    ], JSON_PRETTY_PRINT);
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
        'teams' => is_array($snapshotData['teams'] ?? null) ? $snapshotData['teams'] : [],
        'people' => is_array($snapshotData['people'] ?? null) ? $snapshotData['people'] : [],
    ],
];

$snapshotPath = aavgo_get_hours_snapshot_path();
$directory = dirname($snapshotPath);
if ($directory !== '' && !is_dir($directory)) {
    @mkdir($directory, 0775, true);
}

$encodedSnapshot = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($encodedSnapshot)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to encode snapshot.',
    ], JSON_PRETTY_PRINT);
    exit;
}

$tempPath = $snapshotPath . '.tmp';
$bytesWritten = @file_put_contents($tempPath, $encodedSnapshot . PHP_EOL, LOCK_EX);

if ($bytesWritten === false || !@rename($tempPath, $snapshotPath)) {
    @unlink($tempPath);
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to persist snapshot on the website.',
    ], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    'ok' => true,
    'snapshot_path' => $snapshotPath,
    'syncedAt' => $snapshot['syncedAt'],
], JSON_PRETTY_PRINT);
