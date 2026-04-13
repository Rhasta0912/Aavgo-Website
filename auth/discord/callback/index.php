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

try {
    $tokenData = aavgo_exchange_code((string) $code);
    $accessToken = (string) ($tokenData['access_token'] ?? '');

    if ($accessToken === '') {
        throw new RuntimeException('Discord did not return an access token.');
    }

    $user = aavgo_fetch_user($accessToken);
    $member = aavgo_fetch_current_member($accessToken);
} catch (Throwable $exception) {
    if ((int) $exception->getCode() === 404) {
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

    http_response_code(502);
    aavgo_render_message_page(
        'Discord login failed.',
        'Discord could not finish the secure role check for this website. Confirm the redirect URL and private auth config, then try again.',
        'Try Again',
        '/auth/discord/login/'
    );
    exit;
}

$memberRoleIds = aavgo_member_role_ids($member);
$accessLevel = aavgo_resolve_access_level($user, $memberRoleIds);

if ($accessLevel === null) {
    aavgo_logout();
    http_response_code(403);
    aavgo_render_message_page(
        'Access denied.',
        'Only Aavgo Trainees, Agents, Team Leaders, Operations Managers, and approved Developers can enter the private website.',
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
    'nickname' => (string) ($member['nick'] ?? ''),
    'role_ids' => $memberRoleIds,
    'access_level' => $accessLevel,
];

$afterLogin = (string) ($_SESSION['aavgo_after_login'] ?? '');
if ($afterLogin === '' && is_array($validatedState)) {
    $afterLogin = (string) ($validatedState['after_login'] ?? '');
}
unset($_SESSION['aavgo_after_login']);

aavgo_redirect(aavgo_resolve_after_login_path($_SESSION['aavgo_user'], $afterLogin));
