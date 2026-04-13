<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

$state = trim((string) ($_GET['state'] ?? ''));
$validatedState = $state !== '' ? aavgo_validate_oauth_state($state) : null;

if ($state === '' || $validatedState === null) {
    http_response_code(403);
    aavgo_render_message_page(
        'Sign-in could not be verified.',
        'The login handoff is no longer valid. Please start Discord sign-in again from the private front door.',
        'Try Again',
        '/auth/discord/login/'
    );
    exit;
}

$handoff = aavgo_take_auth_handoff($state);
if (!is_array($handoff) || !is_array($handoff['user'] ?? null)) {
    http_response_code(409);
    aavgo_render_message_page(
        'Still waiting on Discord.',
        'Discord has not finished the secure handoff yet, or the one-time handoff expired. Start the sign-in again if this keeps happening.',
        'Try Again',
        '/auth/discord/login/'
    );
    exit;
}

session_regenerate_id(true);
$_SESSION['aavgo_user'] = $handoff['user'];
unset($_SESSION['aavgo_after_login']);

$afterLogin = (string) ($handoff['afterLogin'] ?? ($validatedState['after_login'] ?? ''));
aavgo_redirect(aavgo_resolve_after_login_path($_SESSION['aavgo_user'], $afterLogin));
