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

$state = $_GET['state'] ?? '';
$expectedState = $_SESSION['discord_oauth_state'] ?? '';
$code = $_GET['code'] ?? '';

unset($_SESSION['discord_oauth_state']);

if ($state === '' || $expectedState === '' || !hash_equals((string) $expectedState, (string) $state)) {
    http_response_code(403);
    aavgo_render_message_page(
        'Sign-in could not be verified.',
        'The login request no longer matches the website session. Please start the Discord login again.',
        'Try Again',
        '/auth/discord/login/'
    );
    exit;
}

if ($code === '') {
    http_response_code(400);
    aavgo_render_message_page(
        'Discord did not return a login code.',
        'The authorization was cancelled or incomplete. Please try signing in again.',
        'Try Again',
        '/auth/discord/login/'
    );
    exit;
}

try {
    $tokenData = aavgo_exchange_code((string) $code);
    $accessToken = (string) ($tokenData['access_token'] ?? '');
    $user = aavgo_fetch_user($accessToken);
    $guilds = aavgo_fetch_user_guilds($accessToken);
} catch (Throwable $exception) {
    http_response_code(502);
    aavgo_render_message_page(
        'Discord login failed.',
        $exception->getMessage(),
        'Try Again',
        '/auth/discord/login/'
    );
    exit;
}

if ($accessToken === '' || !aavgo_user_in_required_guild($guilds)) {
    aavgo_logout();
    http_response_code(403);
    aavgo_render_message_page(
        'Access denied.',
        'Your Discord account is not an active member of the Aavgo server, so this area stays locked.',
        'Back to Home',
        '/'
    );
    exit;
}

session_regenerate_id(true);

$_SESSION['aavgo_user'] = [
    'id' => (string) ($user['id'] ?? ''),
    'username' => (string) ($user['username'] ?? 'Unknown User'),
    'avatar' => (string) ($user['avatar'] ?? ''),
    'global_name' => (string) ($user['global_name'] ?? ''),
];

$afterLogin = $_SESSION['aavgo_after_login'] ?? '/admin/';
unset($_SESSION['aavgo_after_login']);

aavgo_redirect((string) $afterLogin);
