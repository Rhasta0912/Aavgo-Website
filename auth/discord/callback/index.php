<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

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

$state = $_GET['state'] ?? '';
$expectedState = $_SESSION['discord_oauth_state'] ?? '';
$cookieState = $_COOKIE[AAVGO_OAUTH_STATE_COOKIE] ?? '';
$code = $_GET['code'] ?? '';
$validatedState = $state !== '' ? aavgo_validate_oauth_state((string) $state) : null;
$sessionStateMatches = $state !== '' && $expectedState !== '' && hash_equals((string) $expectedState, (string) $state);
$cookieStateMatches = $state !== '' && $cookieState !== '' && hash_equals((string) $cookieState, (string) $state);

unset($_SESSION['discord_oauth_state']);
aavgo_clear_oauth_state_cookie();

if ($state === '' || (!$sessionStateMatches && !$cookieStateMatches && $validatedState === null)) {
    http_response_code(403);
    aavgo_render_message_page(
        'Sign-in could not be verified.',
        'The login request no longer matches the secure website handoff. Please start the Discord login again from the front door.',
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

$preferredCallbackUrl = is_array($validatedState)
    ? aavgo_normalize_callback_url((string) ($validatedState['callback_url'] ?? ''))
    : '';

try {
    $tokenData = aavgo_exchange_code((string) $code, $preferredCallbackUrl);
    $accessToken = (string) ($tokenData['access_token'] ?? '');

    if ($accessToken === '') {
        throw new RuntimeException('Discord did not return an access token.');
    }
} catch (Throwable $exception) {
    aavgo_log_auth_failure('token_exchange', $exception, [
        'has_code' => true,
        'request_host' => aavgo_get_request_host(),
        'preferred_callback' => $preferredCallbackUrl ?? '',
        'callback_candidates' => aavgo_get_callback_url_candidates(),
    ]);

    http_response_code(502);
    aavgo_render_message_page(
        'Discord login failed.',
        'Discord could not finish the secure login handoff for this website. If the app opened separately, return to the website and start the sign-in again from here.',
        'Try Again',
        '/auth/discord/login/',
        [
            'secondary_action_label' => 'Direct OAuth',
            'secondary_action_href' => '/auth/discord/login/?direct=1',
            'diagnostics' => [
                'Stage' => 'token_exchange',
                'Discord status' => (string) $exception->getCode(),
                'Detail' => $exception->getMessage(),
                'Host' => aavgo_get_request_host(),
                'Callback' => $preferredCallbackUrl,
            ],
        ]
    );
    exit;
}

try {
    $user = aavgo_fetch_user($accessToken);
} catch (Throwable $exception) {
    aavgo_log_auth_failure('fetch_user', $exception, [
        'request_host' => aavgo_get_request_host(),
    ]);

    http_response_code(502);
    aavgo_render_message_page(
        'Discord login failed.',
        'Discord finished authorization, but the website could not recover your identity from the callback. Please start the sign-in again from the website.',
        'Try Again',
        '/auth/discord/login/',
        [
            'secondary_action_label' => 'Direct OAuth',
            'secondary_action_href' => '/auth/discord/login/?direct=1',
            'diagnostics' => [
                'Stage' => 'fetch_user',
                'Discord status' => (string) $exception->getCode(),
                'Detail' => $exception->getMessage(),
                'Host' => aavgo_get_request_host(),
            ],
        ]
    );
    exit;
}

$guildMembership = [];
$inConfiguredGuild = false;
try {
    $guildMembership = aavgo_fetch_user_guilds($accessToken);
    $inConfiguredGuild = aavgo_user_is_in_configured_guild($guildMembership);
} catch (Throwable $exception) {
    aavgo_log_auth_failure('fetch_user_guilds', $exception, [
        'user_id' => (string) ($user['id'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'request_host' => aavgo_get_request_host(),
    ]);
}

if ($inConfiguredGuild === false) {
    aavgo_logout();
    http_response_code(403);
    aavgo_render_message_page(
        'Access denied.',
        'Your Discord account is not an active member of the Aavgo server, so the private website stays closed.',
        'Back to Home',
        '/'
    );
    exit;
}

$fallbackSessionUser = aavgo_build_identity_fallback_session_user($user);
if (is_array($fallbackSessionUser)) {
    session_regenerate_id(true);
    $_SESSION['aavgo_user'] = $fallbackSessionUser;

    $afterLogin = (string) ($_SESSION['aavgo_after_login'] ?? '');
    if ($afterLogin === '' && is_array($validatedState)) {
        $afterLogin = (string) ($validatedState['after_login'] ?? '');
    }
    unset($_SESSION['aavgo_after_login']);

    aavgo_redirect(aavgo_resolve_after_login_path($_SESSION['aavgo_user'], $afterLogin));
}

aavgo_logout();
http_response_code(403);
aavgo_render_message_page(
    'Access denied.',
    'Only Aavgo Trainees, Agents, Team Leaders, Operations Managers, and approved Developers can enter the private website.',
    'Back to Home',
    '/'
);
exit;
