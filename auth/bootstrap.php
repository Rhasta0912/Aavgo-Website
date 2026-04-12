<?php

declare(strict_types=1);

const AAVGO_EXTERNAL_CONFIG = '/home/aavgodes/discord-auth-config.php';
const AAVGO_DEFAULT_BASE_URL = 'https://www.aavgodesk.xyz';
const AAVGO_REQUIRED_SCOPES = 'identify guilds';
const AAVGO_API_BASE = 'https://discord.com/api/v10';

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

function aavgo_load_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = [
        'client_id' => getenv('AAVGO_DISCORD_CLIENT_ID') ?: '',
        'client_secret' => getenv('AAVGO_DISCORD_CLIENT_SECRET') ?: '',
        'guild_id' => getenv('AAVGO_DISCORD_GUILD_ID') ?: '',
        'base_url' => rtrim(getenv('AAVGO_BASE_URL') ?: AAVGO_DEFAULT_BASE_URL, '/'),
    ];

    if (is_file(AAVGO_EXTERNAL_CONFIG)) {
        $fileConfig = require AAVGO_EXTERNAL_CONFIG;
        if (is_array($fileConfig)) {
            $config = array_merge($config, $fileConfig);
            $config['base_url'] = rtrim((string) ($config['base_url'] ?? AAVGO_DEFAULT_BASE_URL), '/');
        }
    }

    return $config;
}

function aavgo_get_config(string $key): string
{
    $config = aavgo_load_config();
    return trim((string) ($config[$key] ?? ''));
}

function aavgo_get_callback_url(): string
{
    return aavgo_get_config('base_url') . '/auth/discord/callback/';
}

function aavgo_is_configured(): bool
{
    return aavgo_get_config('client_id') !== ''
        && aavgo_get_config('client_secret') !== ''
        && aavgo_get_config('guild_id') !== '';
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
        'client_id' => aavgo_get_config('client_id'),
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

    if (!empty($body)) {
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
        throw new RuntimeException('Discord error: ' . $message);
    }

    return $decoded;
}

function aavgo_exchange_code(string $code): array
{
    return aavgo_api_request('POST', '/oauth2/token', [
        'Content-Type: application/x-www-form-urlencoded',
    ], [
        'client_id' => aavgo_get_config('client_id'),
        'client_secret' => aavgo_get_config('client_secret'),
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

function aavgo_fetch_user_guilds(string $accessToken): array
{
    return aavgo_api_request('GET', '/users/@me/guilds', [
        'Authorization: Bearer ' . $accessToken,
    ]);
}

function aavgo_user_in_required_guild(array $guilds): bool
{
    $requiredGuildId = aavgo_get_config('guild_id');

    foreach ($guilds as $guild) {
        if (($guild['id'] ?? '') === $requiredGuildId) {
            return true;
        }
    }

    return false;
}

function aavgo_current_user(): ?array
{
    $user = $_SESSION['aavgo_user'] ?? null;
    return is_array($user) ? $user : null;
}

function aavgo_require_auth(): array
{
    $user = aavgo_current_user();
    if ($user === null) {
        $_SESSION['aavgo_after_login'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        aavgo_redirect('/auth/discord/login/');
    }

    return $user;
}

function aavgo_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="admin-page">
  <div class="site-shell admin-shell">
    <section class="admin-hero reveal reveal-in">
      <div class="admin-hero-copy">
        <p class="eyebrow">Aavgo secure access</p>
        <h1>{$safeTitle}</h1>
        <p class="hero-text">{$safeMessage}</p>
        <div class="hero-actions">
          <a class="button button-primary" href="{$safeActionHref}">{$safeActionLabel}</a>
          <a class="button button-secondary" href="/">Back to Home</a>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
HTML;
}

aavgo_bootstrap_session();
