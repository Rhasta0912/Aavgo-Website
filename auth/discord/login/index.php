<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

$currentUser = aavgo_current_user();
if ($currentUser !== null) {
    $afterLogin = (string) ($_SESSION['aavgo_after_login'] ?? '');
    unset($_SESSION['aavgo_after_login']);

    aavgo_redirect(aavgo_resolve_after_login_path($currentUser, $afterLogin));
}

if (!aavgo_is_fully_configured()) {
    http_response_code(500);
    aavgo_render_message_page(
        'Discord login is not configured yet.',
        'The website is missing its private Discord auth setup or allowed role mapping on the server. Update the server-only config, then try again.',
        'Back to Home',
        '/'
    );
    exit;
}

$afterLogin = aavgo_normalize_after_login_path((string) ($_SESSION['aavgo_after_login'] ?? ''));
$callbackUrl = aavgo_get_callback_url();
$state = aavgo_create_oauth_state($afterLogin, $callbackUrl);
$_SESSION['discord_oauth_state'] = $state;
aavgo_store_oauth_state_cookie($state);

$preferBrowser = !isset($_GET['direct']) || (string) $_GET['direct'] !== '1';
aavgo_redirect(aavgo_login_url($preferBrowser, $callbackUrl));
