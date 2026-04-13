<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$user = aavgo_require_access('admin');
aavgo_json_response(aavgo_build_admin_board_payload($user));
