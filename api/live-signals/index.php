<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], 405);
    exit;
}

aavgo_json_response(aavgo_build_live_signal_payload());
