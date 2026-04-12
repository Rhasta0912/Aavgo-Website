<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

if (!aavgo_is_configured()) {
    http_response_code(500);
    aavgo_render_message_page(
        'Discord login is not configured yet.',
        'The website is missing its private Discord auth configuration on the server. Add the server-only config file, then try again.',
        'Back to Home',
        '/'
    );
    exit;
}

$_SESSION['discord_oauth_state'] = aavgo_create_state();

aavgo_redirect(aavgo_login_url());
