<?php

declare(strict_types=1);

const AAVGO_EXTERNAL_CONFIG = '/home/aavgodes/discord-auth-config.php';
const AAVGO_DEFAULT_BASE_URL = 'https://www.aavgodesk.xyz';
const AAVGO_REQUIRED_SCOPES = 'identify guilds.members.read';
const AAVGO_API_BASE = 'https://discord.com/api/v10';
const AAVGO_WEBSITE_API_TIMEOUT = 20;
const AAVGO_DEFAULT_HOURS_SNAPSHOT_PATH = '/home/aavgodes/admin-hours-snapshot.json';
const AAVGO_DEVELOPER_ROLE_ID = '1482312134875418737';
const AAVGO_DEFAULT_ROLE_IDS = [
    'admin' => [
        AAVGO_DEVELOPER_ROLE_ID, // Developer
        '1482732583660818636', // Team Leader
        '1482226842047090809', // Operations Manager
    ],
    'user' => [
        '1484705126026449029', // Trainee
        '1482227287159078964', // Agent
    ],
];
const AAVGO_DEFAULT_ADMIN_USER_IDS = [
    '320128931971727360', // Alpha
    '1186978205018632242', // Astra
];

function aavgo_bootstrap_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function aavgo_parse_id_list(mixed $value): array
{
    if (is_string($value)) {
        $value = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    if (!is_array($value)) {
        return [];
    }

    $ids = [];
    foreach ($value as $item) {
        $id = trim((string) $item);
        if ($id === '') {
            continue;
        }

        $ids[$id] = $id;
    }

    return array_values($ids);
}

function aavgo_normalize_role_ids(array $roleIds): array
{
    return [
        'admin' => aavgo_parse_id_list($roleIds['admin'] ?? []),
        'user' => aavgo_parse_id_list($roleIds['user'] ?? []),
    ];
}

function aavgo_ensure_required_role_ids(array $roleIds): array
{
    $normalized = aavgo_normalize_role_ids($roleIds);
    $normalized['admin'] = aavgo_parse_id_list(array_merge(
        [AAVGO_DEVELOPER_ROLE_ID],
        $normalized['admin']
    ));

    return $normalized;
}

function aavgo_load_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $roleIds = aavgo_ensure_required_role_ids(AAVGO_DEFAULT_ROLE_IDS);
    $envAdminRoleIds = aavgo_parse_id_list(getenv('AAVGO_ADMIN_ROLE_IDS') ?: '');
    $envUserRoleIds = aavgo_parse_id_list(getenv('AAVGO_USER_ROLE_IDS') ?: '');
    $adminUserIds = aavgo_parse_id_list(getenv('AAVGO_ADMIN_USER_IDS') ?: '') ?: AAVGO_DEFAULT_ADMIN_USER_IDS;

    if ($envAdminRoleIds !== []) {
        $roleIds['admin'] = $envAdminRoleIds;
    }

    if ($envUserRoleIds !== []) {
        $roleIds['user'] = $envUserRoleIds;
    }

    $config = [
        'client_id' => getenv('AAVGO_DISCORD_CLIENT_ID') ?: '',
        'client_secret' => getenv('AAVGO_DISCORD_CLIENT_SECRET') ?: '',
        'guild_id' => getenv('AAVGO_DISCORD_GUILD_ID') ?: '',
        'base_url' => rtrim(getenv('AAVGO_BASE_URL') ?: AAVGO_DEFAULT_BASE_URL, '/'),
        'website_api_url' => rtrim((string) (getenv('AAVGO_WEBSITE_API_URL') ?: ''), '/'),
        'website_api_token' => trim((string) (getenv('AAVGO_WEBSITE_API_TOKEN') ?: '')),
        'hours_snapshot_path' => trim((string) (getenv('AAVGO_HOURS_SNAPSHOT_PATH') ?: AAVGO_DEFAULT_HOURS_SNAPSHOT_PATH)),
        'role_ids' => $roleIds,
        'admin_user_ids' => $adminUserIds,
    ];

    if (is_file(AAVGO_EXTERNAL_CONFIG)) {
        $fileConfig = require AAVGO_EXTERNAL_CONFIG;
        if (is_array($fileConfig)) {
            foreach (['client_id', 'client_secret', 'guild_id', 'base_url', 'website_api_url', 'website_api_token', 'hours_snapshot_path'] as $key) {
                if (array_key_exists($key, $fileConfig)) {
                    $config[$key] = (string) $fileConfig[$key];
                }
            }

            $rawRoleIds = $fileConfig['role_ids'] ?? [
                'admin' => $fileConfig['admin_role_ids'] ?? $config['role_ids']['admin'],
                'user' => $fileConfig['user_role_ids'] ?? $config['role_ids']['user'],
            ];

            if (is_array($rawRoleIds)) {
                $config['role_ids'] = aavgo_ensure_required_role_ids($rawRoleIds);
            }

            $rawAdminUserIds = $fileConfig['admin_user_ids'] ?? $fileConfig['developer_user_ids'] ?? $config['admin_user_ids'];
            $config['admin_user_ids'] = aavgo_parse_id_list($rawAdminUserIds);

            $config['base_url'] = rtrim((string) ($config['base_url'] ?: AAVGO_DEFAULT_BASE_URL), '/');
            $config['website_api_url'] = rtrim((string) ($config['website_api_url'] ?? ''), '/');
            $config['website_api_token'] = trim((string) ($config['website_api_token'] ?? ''));
            $config['hours_snapshot_path'] = trim((string) ($config['hours_snapshot_path'] ?: AAVGO_DEFAULT_HOURS_SNAPSHOT_PATH));
        }
    }

    return $config;
}

function aavgo_get_config(string $key): mixed
{
    $config = aavgo_load_config();
    return $config[$key] ?? null;
}

function aavgo_get_config_string(string $key): string
{
    return trim((string) aavgo_get_config($key));
}

function aavgo_get_role_ids(string $bucket): array
{
    $roleIds = aavgo_get_config('role_ids');
    if (!is_array($roleIds)) {
        return [];
    }

    return aavgo_parse_id_list($roleIds[$bucket] ?? []);
}

function aavgo_get_callback_url(): string
{
    return aavgo_get_config_string('base_url') . '/auth/discord/callback/';
}

function aavgo_get_website_api_url(): string
{
    return aavgo_get_config_string('website_api_url');
}

function aavgo_get_website_api_token(): string
{
    return aavgo_get_config_string('website_api_token');
}

function aavgo_get_hours_snapshot_path(): string
{
    $path = trim((string) aavgo_get_config('hours_snapshot_path'));
    return $path !== '' ? $path : AAVGO_DEFAULT_HOURS_SNAPSHOT_PATH;
}

function aavgo_get_admin_user_ids(): array
{
    return aavgo_parse_id_list(aavgo_get_config('admin_user_ids') ?? []);
}

function aavgo_is_configured(): bool
{
    return aavgo_get_config_string('client_id') !== ''
        && aavgo_get_config_string('client_secret') !== ''
        && aavgo_get_config_string('guild_id') !== '';
}

function aavgo_has_role_mapping(): bool
{
    return aavgo_get_role_ids('admin') !== []
        || aavgo_get_role_ids('user') !== []
        || aavgo_get_admin_user_ids() !== [];
}

function aavgo_is_fully_configured(): bool
{
    return aavgo_is_configured() && aavgo_has_role_mapping();
}

function aavgo_has_hours_bridge(): bool
{
    return aavgo_get_website_api_url() !== '' && aavgo_get_website_api_token() !== '';
}

function aavgo_has_hours_sync_token(): bool
{
    return aavgo_get_website_api_token() !== '';
}

function aavgo_read_pushed_hours_snapshot_payload(): ?array
{
    $path = aavgo_get_hours_snapshot_path();
    if ($path === '' || !is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [
            'ok' => false,
            'configured' => true,
            'error' => 'The pushed hours snapshot could not be read.',
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !is_array($decoded['data'] ?? null)) {
        return [
            'ok' => false,
            'configured' => true,
            'error' => 'The pushed hours snapshot is invalid.',
        ];
    }

    $decoded['configured'] = true;
    return $decoded;
}

function aavgo_redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function aavgo_create_state(): string
{
    return bin2hex(random_bytes(24));
}

function aavgo_login_url(): string
{
    $query = http_build_query([
        'client_id' => aavgo_get_config_string('client_id'),
        'redirect_uri' => aavgo_get_callback_url(),
        'response_type' => 'code',
        'scope' => AAVGO_REQUIRED_SCOPES,
        'state' => $_SESSION['discord_oauth_state'] ?? '',
        'prompt' => 'consent',
    ]);

    return 'https://discord.com/oauth2/authorize?' . $query;
}

function aavgo_api_request(string $method, string $endpoint, array $headers = [], array $body = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The server is missing PHP cURL, which Discord sign-in requires.');
    }

    $url = str_starts_with($endpoint, 'http') ? $endpoint : AAVGO_API_BASE . $endpoint;
    $curl = curl_init($url);

    $normalizedHeaders = array_merge(['Accept: application/json'], $headers);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $normalizedHeaders,
        CURLOPT_TIMEOUT => 20,
    ]);

    if ($body !== []) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body));
    }

    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $curlError !== '') {
        throw new RuntimeException('Discord request failed: ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Discord returned an invalid response.');
    }

    if ($httpCode >= 400) {
        $message = $decoded['error_description'] ?? $decoded['message'] ?? 'Unknown Discord error.';
        throw new RuntimeException('Discord error: ' . $message, $httpCode);
    }

    return $decoded;
}

function aavgo_exchange_code(string $code): array
{
    return aavgo_api_request('POST', '/oauth2/token', [
        'Content-Type: application/x-www-form-urlencoded',
    ], [
        'client_id' => aavgo_get_config_string('client_id'),
        'client_secret' => aavgo_get_config_string('client_secret'),
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => aavgo_get_callback_url(),
    ]);
}

function aavgo_fetch_user(string $accessToken): array
{
    return aavgo_api_request('GET', '/users/@me', [
        'Authorization: Bearer ' . $accessToken,
    ]);
}

function aavgo_fetch_current_member(string $accessToken): array
{
    $guildId = rawurlencode(aavgo_get_config_string('guild_id'));

    return aavgo_api_request('GET', '/users/@me/guilds/' . $guildId . '/member', [
        'Authorization: Bearer ' . $accessToken,
    ]);
}

function aavgo_member_role_ids(array $member): array
{
    return aavgo_parse_id_list($member['roles'] ?? []);
}

function aavgo_resolve_access_level(array $discordUser, array $memberRoleIds): ?string
{
    $userId = trim((string) ($discordUser['id'] ?? ''));
    if ($userId !== '' && in_array($userId, aavgo_get_admin_user_ids(), true)) {
        return 'admin';
    }

    if (array_intersect($memberRoleIds, aavgo_get_role_ids('admin')) !== []) {
        return 'admin';
    }

    if (array_intersect($memberRoleIds, aavgo_get_role_ids('user')) !== []) {
        return 'user';
    }

    return null;
}

function aavgo_default_path_for_access_level(string $accessLevel): string
{
    return $accessLevel === 'admin' ? '/admin/' : '/user/';
}

function aavgo_current_user(): ?array
{
    $user = $_SESSION['aavgo_user'] ?? null;
    if (!is_array($user)) {
        return null;
    }

    $accessLevel = (string) ($user['access_level'] ?? '');
    if ($accessLevel !== 'admin' && $accessLevel !== 'user') {
        return null;
    }

    return $user;
}

function aavgo_display_name(array $user): string
{
    $candidates = [
        (string) ($user['nickname'] ?? ''),
        (string) ($user['global_name'] ?? ''),
        (string) ($user['username'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return 'Aavgo Member';
}

function aavgo_user_access_level(array $user): string
{
    return (string) ($user['access_level'] ?? 'user');
}

function aavgo_user_default_path(array $user): string
{
    return aavgo_default_path_for_access_level(aavgo_user_access_level($user));
}

function aavgo_user_can_access(array $user, string $route): bool
{
    $accessLevel = aavgo_user_access_level($user);

    if ($accessLevel === 'admin') {
        return $route === 'admin' || $route === 'user';
    }

    return $route === 'user';
}

function aavgo_route_group_from_path(string $path): ?string
{
    $cleanPath = (string) (parse_url($path, PHP_URL_PATH) ?? '');

    if (str_starts_with($cleanPath, '/admin')) {
        return 'admin';
    }

    if (str_starts_with($cleanPath, '/user')) {
        return 'user';
    }

    return null;
}

function aavgo_resolve_after_login_path(array $user, string $requestedPath): string
{
    $defaultPath = aavgo_user_default_path($user);
    if ($requestedPath === '') {
        return $defaultPath;
    }

    $targetGroup = aavgo_route_group_from_path($requestedPath);
    if ($targetGroup === null) {
        return $defaultPath;
    }

    if (!aavgo_user_can_access($user, $targetGroup)) {
        return $defaultPath;
    }

    return aavgo_default_path_for_access_level($targetGroup);
}

function aavgo_require_auth(): array
{
    $user = aavgo_current_user();
    if ($user === null) {
        $_SESSION['aavgo_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        aavgo_redirect('/auth/discord/login/');
    }

    return $user;
}

function aavgo_require_access(string $route): array
{
    $user = aavgo_require_auth();

    if (aavgo_user_can_access($user, $route)) {
        return $user;
    }

    $defaultPath = aavgo_user_default_path($user);
    if ($defaultPath !== ($_SERVER['REQUEST_URI'] ?? '')) {
        aavgo_redirect($defaultPath);
    }

    http_response_code(403);
    aavgo_render_message_page(
        'That area is not open to this role.',
        'Aavgo verified your Discord access, but this route belongs to a different workspace tier.',
        'Open My Workspace',
        $defaultPath
    );
    exit;
}

function aavgo_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function aavgo_render_message_page(string $title, string $message, string $actionLabel, string $actionHref): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeActionLabel = htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8');
    $safeActionHref = htmlspecialchars($actionHref, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeTitle}</title>
  <meta name="robots" content="noindex,nofollow,noarchive">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="workspace-page workspace-dashboard workspace-page-access">
  <div class="dashboard-shell dashboard-shell-message dashboard-shell-operations">
    <aside class="dashboard-sidebar reveal reveal-in">
      <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>
      <section class="dashboard-profile-card">
        <div class="dashboard-avatar">A</div>
        <div class="dashboard-profile-copy">
          <strong>Private front door</strong>
          <p>Discord-secured website surface</p>
        </div>
      </section>
      <div class="dashboard-sidebar-meta">
        <span class="dashboard-chip dashboard-chip-accent">Access gate</span>
        <span class="dashboard-chip">No public browse</span>
      </div>
      <div class="dashboard-command-box">
        <span class="dashboard-command-label">Access mode</span>
        <strong>The website stays private until the right Discord role opens the route.</strong>
      </div>
      <div class="dashboard-side-note">
        <p class="dashboard-kicker">Status</p>
        <h3>{$safeTitle}</h3>
        <p>{$safeMessage}</p>
      </div>
    </aside>

    <main class="dashboard-main">
      <section class="dashboard-hero-card dashboard-message-hero reveal reveal-in">
        <div class="dashboard-hero-grid">
          <div>
            <p class="dashboard-kicker">Private access status</p>
            <h1 class="dashboard-title dashboard-title-message">{$safeTitle}</h1>
            <p class="dashboard-subtitle">{$safeMessage}</p>
          </div>
          <div class="dashboard-hero-aside">
            <span class="dashboard-chip dashboard-chip-accent">Next step</span>
            <strong>Return through the secure front door.</strong>
            <p>Move back into the correct workspace lane without dropping out of the premium private surface.</p>
          </div>
        </div>
        <div class="dashboard-action-row dashboard-action-row-message">
          <a class="button button-primary" href="{$safeActionHref}">{$safeActionLabel}</a>
          <a class="button button-secondary" href="/">Back Home</a>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
HTML;
}

function aavgo_fetch_hours_bridge_payload(): array
{
    $snapshotPayload = aavgo_read_pushed_hours_snapshot_payload();
    if ($snapshotPayload !== null) {
        return $snapshotPayload;
    }

    if (aavgo_has_hours_sync_token()) {
        return [
            'ok' => false,
            'configured' => true,
            'error' => 'Waiting for the bot to push the first live hours snapshot to the website.',
        ];
    }

    if (!aavgo_has_hours_bridge()) {
        return [
            'ok' => false,
            'configured' => false,
            'error' => 'The admin hours bridge is not configured yet.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'configured' => true,
            'error' => 'PHP cURL is required to load live admin hours.',
        ];
    }

    $url = aavgo_get_website_api_url() . '/api/website/admin-hours';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => AAVGO_WEBSITE_API_TIMEOUT,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . aavgo_get_website_api_token(),
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $curlError !== '') {
        return [
            'ok' => false,
            'configured' => true,
            'error' => 'The hours bridge could not be reached right now.',
            'details' => $curlError,
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'configured' => true,
            'error' => 'The hours bridge returned an invalid response.',
        ];
    }

    if ($httpCode >= 400 || !($decoded['ok'] ?? false)) {
        return [
            'ok' => false,
            'configured' => true,
            'error' => (string) ($decoded['error'] ?? 'The hours bridge returned an error.'),
        ];
    }

    return [
        'ok' => true,
        'configured' => true,
        'data' => $decoded['data'] ?? null,
    ];
}

aavgo_bootstrap_session();
