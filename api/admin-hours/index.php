<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

aavgo_require_access('admin');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode(aavgo_fetch_hours_bridge_payload(), JSON_PRETTY_PRINT);
