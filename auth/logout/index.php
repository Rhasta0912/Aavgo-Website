<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

aavgo_logout();
aavgo_redirect('/');
