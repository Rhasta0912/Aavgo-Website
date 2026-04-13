<?php

declare(strict_types=1);

const AAVGO_EXTERNAL_CONFIG = '/home/aavgodes/discord-auth-config.php';
const AAVGO_DEFAULT_BASE_URL = 'https://www.aavgodesk.xyz';
const AAVGO_REQUIRED_SCOPES = 'identify guilds.members.read';
const AAVGO_API_BASE = 'https://discord.com/api/v10';
const AAVGO_WEBSITE_API_TIMEOUT = 20;
const AAVGO_DEFAULT_HOURS_SNAPSHOT_PATH = '/home/aavgodes/admin-hours-snapshot.json';
const AAVGO_DEFAULT_COMMAND_QUEUE_PATH = '/home/aavgodes/admin-command-queue.json';
const AAVGO_DEFAULT_AUDIT_LOG_PATH = '/home/aavgodes/admin-audit-log.json';
const AAVGO_DEVELOPER_ROLE_ID = '1482312134875418737';
const AAVGO_OPERATIONS_MANAGER_ROLE_ID = '1482226842047090809';
const AAVGO_TEAM_LEADER_ROLE_ID = '1482732583660818636';
const AAVGO_SME_ROLE_ID = '1482382342621233153';
const AAVGO_AGENT_ROLE_ID = '1482227287159078964';
const AAVGO_TRAINEE_ROLE_ID = '1484705126026449029';
const AAVGO_OAUTH_STATE_TTL = 900;
const AAVGO_OAUTH_STATE_COOKIE = 'aavgo_discord_state';
const AAVGO_DEFAULT_ROLE_IDS = [
    'admin' => [
        AAVGO_DEVELOPER_ROLE_ID, // Developer
        AAVGO_TEAM_LEADER_ROLE_ID, // Team Leader
        AAVGO_OPERATIONS_MANAGER_ROLE_ID, // Operations Manager
    ],
    'user' => [
        AAVGO_TRAINEE_ROLE_ID, // Trainee
        AAVGO_AGENT_ROLE_ID, // Agent
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
        'command_queue_path' => trim((string) (getenv('AAVGO_COMMAND_QUEUE_PATH') ?: AAVGO_DEFAULT_COMMAND_QUEUE_PATH)),
        'audit_log_path' => trim((string) (getenv('AAVGO_AUDIT_LOG_PATH') ?: AAVGO_DEFAULT_AUDIT_LOG_PATH)),
        'role_ids' => $roleIds,
        'admin_user_ids' => $adminUserIds,
    ];

    if (is_file(AAVGO_EXTERNAL_CONFIG)) {
        $fileConfig = require AAVGO_EXTERNAL_CONFIG;
        if (is_array($fileConfig)) {
            foreach (['client_id', 'client_secret', 'guild_id', 'base_url', 'website_api_url', 'website_api_token', 'hours_snapshot_path', 'command_queue_path', 'audit_log_path'] as $key) {
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
            $config['command_queue_path'] = trim((string) ($config['command_queue_path'] ?: AAVGO_DEFAULT_COMMAND_QUEUE_PATH));
            $config['audit_log_path'] = trim((string) ($config['audit_log_path'] ?: AAVGO_DEFAULT_AUDIT_LOG_PATH));
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

function aavgo_get_command_queue_path(): string
{
    $path = trim((string) aavgo_get_config('command_queue_path'));
    return $path !== '' ? $path : AAVGO_DEFAULT_COMMAND_QUEUE_PATH;
}

function aavgo_get_audit_log_path(): string
{
    $path = trim((string) aavgo_get_config('audit_log_path'));
    return $path !== '' ? $path : AAVGO_DEFAULT_AUDIT_LOG_PATH;
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

function aavgo_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function aavgo_read_json_file(string $path, array $fallback): array
{
    if ($path === '' || !is_file($path)) {
        return $fallback;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function aavgo_write_json_file(string $path, array $payload): bool
{
    if ($path === '') {
        return false;
    }

    $directory = dirname($path);
    if ($directory !== '' && !is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    $tempPath = $path . '.tmp';
    $written = @file_put_contents($tempPath, $encoded . PHP_EOL, LOCK_EX);
    if ($written === false) {
        @unlink($tempPath);
        return false;
    }

    if (!@rename($tempPath, $path)) {
        @unlink($tempPath);
        return false;
    }

    return true;
}

function aavgo_create_identifier(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(6));
}

function aavgo_user_role_ids_array(array $user): array
{
    return aavgo_parse_id_list($user['role_ids'] ?? []);
}

function aavgo_user_has_role_id(array $user, string $roleId): bool
{
    return in_array($roleId, aavgo_user_role_ids_array($user), true);
}

function aavgo_user_is_developer(array $user): bool
{
    $userId = trim((string) ($user['id'] ?? ''));
    return aavgo_user_has_role_id($user, AAVGO_DEVELOPER_ROLE_ID)
        || ($userId !== '' && in_array($userId, aavgo_get_admin_user_ids(), true));
}

function aavgo_request_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $candidates = [
        $_SERVER[$serverKey] ?? '',
        $_SERVER['REDIRECT_' . $serverKey] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string) $candidate);
        if ($value !== '') {
            return $value;
        }
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $headerName => $value) {
                if (strcasecmp((string) $headerName, $name) === 0) {
                    $normalizedValue = trim((string) $value);
                    if ($normalizedValue !== '') {
                        return $normalizedValue;
                    }
                }
            }
        }
    }

    return '';
}

function aavgo_decode_json_body(): array
{
    $rawBody = file_get_contents('php://input');
    $decoded = json_decode($rawBody ?: '', true);
    return is_array($decoded) ? $decoded : [];
}

function aavgo_extract_sync_token(array $decodedPayload): string
{
    $authorization = aavgo_request_header('Authorization');
    if (
        $authorization !== ''
        && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1
    ) {
        $bearerToken = trim((string) ($matches[1] ?? ''));
        if ($bearerToken !== '') {
            return $bearerToken;
        }
    }

    foreach (['X-Aavgo-Token', 'X-Aavgo-Website-Token'] as $headerName) {
        $headerValue = aavgo_request_header($headerName);
        if ($headerValue !== '') {
            return $headerValue;
        }
    }

    return trim((string) ($decodedPayload['token'] ?? ''));
}

function aavgo_command_queue_template(): array
{
    return [
        'updatedAt' => gmdate('c'),
        'commands' => [],
    ];
}

function aavgo_audit_log_template(): array
{
    return [
        'updatedAt' => gmdate('c'),
        'entries' => [],
    ];
}

function aavgo_sort_descending_by_created_at(array $items): array
{
    usort($items, static function (array $left, array $right): int {
        return strcmp(
            (string) ($right['createdAt'] ?? ''),
            (string) ($left['createdAt'] ?? '')
        );
    });

    return $items;
}

function aavgo_read_command_queue(): array
{
    $queue = aavgo_read_json_file(aavgo_get_command_queue_path(), aavgo_command_queue_template());
    $commands = is_array($queue['commands'] ?? null) ? $queue['commands'] : [];
    $queue['commands'] = array_values(array_filter($commands, 'is_array'));
    $queue['updatedAt'] = trim((string) ($queue['updatedAt'] ?? '')) ?: gmdate('c');
    return $queue;
}

function aavgo_trim_command_history(array $commands): array
{
    $pending = [];
    $completed = [];

    foreach ($commands as $command) {
        $status = trim((string) ($command['status'] ?? 'pending'));
        if ($status === 'pending' || $status === 'processing') {
            $pending[] = $command;
            continue;
        }

        $completed[] = $command;
    }

    $completed = aavgo_sort_descending_by_created_at($completed);
    $completed = array_slice($completed, 0, 140);

    return array_merge($pending, $completed);
}

function aavgo_write_command_queue(array $queue): bool
{
    $queue['commands'] = aavgo_trim_command_history(is_array($queue['commands'] ?? null) ? $queue['commands'] : []);
    $queue['updatedAt'] = gmdate('c');
    return aavgo_write_json_file(aavgo_get_command_queue_path(), $queue);
}

function aavgo_read_audit_log(): array
{
    $log = aavgo_read_json_file(aavgo_get_audit_log_path(), aavgo_audit_log_template());
    $entries = is_array($log['entries'] ?? null) ? $log['entries'] : [];
    $log['entries'] = aavgo_sort_descending_by_created_at(array_values(array_filter($entries, 'is_array')));
    $log['updatedAt'] = trim((string) ($log['updatedAt'] ?? '')) ?: gmdate('c');
    return $log;
}

function aavgo_write_audit_log(array $log): bool
{
    $entries = is_array($log['entries'] ?? null) ? $log['entries'] : [];
    $log['entries'] = array_slice(aavgo_sort_descending_by_created_at(array_values(array_filter($entries, 'is_array'))), 0, 180);
    $log['updatedAt'] = gmdate('c');
    return aavgo_write_json_file(aavgo_get_audit_log_path(), $log);
}

function aavgo_command_action_label(string $action): string
{
    return match ($action) {
        'update_team' => 'Team reassignment',
        'update_hotel' => 'Hotel reassignment',
        'force_logout_agent' => 'Force logout (staff)',
        'force_logout_hotel' => 'Force logout (hotel)',
        'sync_all_roles' => 'Discord role resync',
        'push_snapshot' => 'Snapshot refresh',
        default => 'Leadership action',
    };
}

function aavgo_command_target_label(array $command): string
{
    $payload = is_array($command['payload'] ?? null) ? $command['payload'] : [];
    $displayName = trim((string) ($payload['displayName'] ?? ''));
    $team = trim((string) ($payload['team'] ?? ''));
    $hotelLabel = trim((string) ($payload['hotelLabel'] ?? $payload['hotelId'] ?? ''));

    return match ((string) ($command['action'] ?? '')) {
        'update_team' => trim($displayName . ($team !== '' ? ' -> ' . $team : '')),
        'update_hotel' => trim($displayName . ($hotelLabel !== '' ? ' -> ' . $hotelLabel : '')),
        'force_logout_agent' => $displayName,
        'force_logout_hotel' => $hotelLabel,
        default => $displayName !== '' ? $displayName : $hotelLabel,
    };
}

function aavgo_append_audit_entry(array $entry): void
{
    $log = aavgo_read_audit_log();
    $log['entries'][] = $entry + [
        'id' => aavgo_create_identifier('audit'),
        'createdAt' => gmdate('c'),
    ];
    aavgo_write_audit_log($log);
}

function aavgo_enqueue_admin_command(string $action, array $payload, array $actor): array
{
    $command = [
        'id' => aavgo_create_identifier('cmd'),
        'action' => $action,
        'status' => 'pending',
        'payload' => $payload,
        'actor' => [
            'discordId' => trim((string) ($actor['discordId'] ?? '')),
            'name' => trim((string) ($actor['name'] ?? 'Aavgo Leadership')),
            'roleSummary' => trim((string) ($actor['roleSummary'] ?? '')),
        ],
        'createdAt' => gmdate('c'),
        'completedAt' => null,
        'message' => 'Queued for bot sync.',
    ];

    $queue = aavgo_read_command_queue();
    $queue['commands'][] = $command;
    aavgo_write_command_queue($queue);

    aavgo_append_audit_entry([
        'id' => aavgo_create_identifier('audit'),
        'action' => $action,
        'label' => aavgo_command_action_label($action),
        'target' => aavgo_command_target_label($command),
        'status' => 'queued',
        'message' => 'Queued for secure bot sync.',
        'createdAt' => gmdate('c'),
        'actor' => $command['actor'],
    ]);

    return $command;
}

function aavgo_get_pending_admin_commands(int $limit = 25): array
{
    $queue = aavgo_read_command_queue();
    $pending = array_values(array_filter($queue['commands'], static function (array $command): bool {
        $status = trim((string) ($command['status'] ?? 'pending'));
        return $status === 'pending';
    }));

    usort($pending, static function (array $left, array $right): int {
        return strcmp((string) ($left['createdAt'] ?? ''), (string) ($right['createdAt'] ?? ''));
    });

    return array_slice($pending, 0, max(1, $limit));
}

function aavgo_apply_command_results(array $results): array
{
    $queue = aavgo_read_command_queue();
    $commands = is_array($queue['commands'] ?? null) ? $queue['commands'] : [];
    $indexedResults = [];

    foreach ($results as $result) {
        if (!is_array($result)) {
            continue;
        }

        $id = trim((string) ($result['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $indexedResults[$id] = $result;
    }

    $updated = [];
    foreach ($commands as &$command) {
        if (!is_array($command)) {
            continue;
        }

        $id = trim((string) ($command['id'] ?? ''));
        if ($id === '' || !isset($indexedResults[$id])) {
            continue;
        }

        $result = $indexedResults[$id];
        $status = trim((string) ($result['status'] ?? 'completed'));
        if (!in_array($status, ['completed', 'failed', 'processing'], true)) {
            $status = 'completed';
        }

        $command['status'] = $status;
        $command['message'] = trim((string) ($result['message'] ?? 'Completed.'));
        $command['completedAt'] = trim((string) ($result['completedAt'] ?? gmdate('c')));
        $updated[] = $command;
    }
    unset($command);

    $queue['commands'] = $commands;
    aavgo_write_command_queue($queue);

    foreach ($updated as $command) {
        aavgo_append_audit_entry([
            'id' => aavgo_create_identifier('audit'),
            'action' => (string) ($command['action'] ?? ''),
            'label' => aavgo_command_action_label((string) ($command['action'] ?? '')),
            'target' => aavgo_command_target_label($command),
            'status' => (string) ($command['status'] ?? 'completed'),
            'message' => trim((string) ($command['message'] ?? 'Completed.')),
            'createdAt' => trim((string) ($command['completedAt'] ?? gmdate('c'))),
            'actor' => is_array($command['actor'] ?? null) ? $command['actor'] : [],
        ]);
    }

    return $updated;
}

function aavgo_build_management_payload(array $user, ?array $hoursPayload = null): array
{
    $queue = aavgo_read_command_queue();
    $audit = aavgo_read_audit_log();
    $hoursData = is_array($hoursPayload['data'] ?? null) ? $hoursPayload['data'] : [];
    $meta = is_array($hoursData['meta'] ?? null) ? $hoursData['meta'] : [];

    $pending = array_values(array_filter($queue['commands'], static function (array $command): bool {
        return in_array((string) ($command['status'] ?? 'pending'), ['pending', 'processing'], true);
    }));
    $recent = aavgo_sort_descending_by_created_at($queue['commands']);

    return [
        'viewer' => [
            'displayName' => aavgo_display_name($user),
            'roleSummary' => aavgo_user_role_summary($user),
            'isDeveloper' => aavgo_user_is_developer($user),
        ],
        'actions' => [
            'canReassign' => true,
            'canForceLogout' => true,
            'canSyncAllRoles' => aavgo_user_is_developer($user),
            'canPushSnapshot' => aavgo_user_is_developer($user),
        ],
        'meta' => [
            'teams' => array_values(array_filter($meta['teams'] ?? [], 'is_string')),
            'hotels' => is_array($meta['hotels'] ?? null) ? $meta['hotels'] : [],
        ],
        'queue' => [
            'pendingCount' => count($pending),
            'pending' => array_slice($pending, 0, 12),
            'recent' => array_slice($recent, 0, 24),
        ],
        'audit' => [
            'entries' => array_slice(is_array($audit['entries'] ?? null) ? $audit['entries'] : [], 0, 24),
        ],
    ];
}

function aavgo_build_admin_board_payload(array $user): array
{
    $hoursPayload = aavgo_fetch_hours_bridge_payload();
    $hoursPayload['management'] = aavgo_build_management_payload($user, $hoursPayload);
    return $hoursPayload;
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

function aavgo_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function aavgo_base64url_decode(string $value): string|false
{
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function aavgo_oauth_state_secret(): string
{
    $secret = aavgo_get_config_string('client_secret');
    if ($secret !== '') {
        return $secret;
    }

    $fallback = aavgo_get_website_api_token();
    if ($fallback !== '') {
        return $fallback;
    }

    return 'aavgo-private-front-door';
}

function aavgo_store_oauth_state_cookie(string $state): void
{
    setcookie(AAVGO_OAUTH_STATE_COOKIE, $state, [
        'expires' => time() + AAVGO_OAUTH_STATE_TTL,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function aavgo_clear_oauth_state_cookie(): void
{
    setcookie(AAVGO_OAUTH_STATE_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function aavgo_normalize_after_login_path(string $path): string
{
    $path = trim($path);
    if ($path === '' || str_starts_with($path, '//')) {
        return '';
    }

    $parts = parse_url($path);
    if ($parts === false) {
        return '';
    }

    $normalizedPath = (string) ($parts['path'] ?? '');
    if ($normalizedPath === '' || !str_starts_with($normalizedPath, '/')) {
        return '';
    }

    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    return $normalizedPath . $query;
}

function aavgo_create_oauth_state(string $afterLogin = ''): string
{
    $payload = [
        'nonce' => aavgo_create_state(),
        'iat' => time(),
        'after_login' => aavgo_normalize_after_login_path($afterLogin),
    ];

    $encodedPayload = aavgo_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
    $signature = hash_hmac('sha256', $encodedPayload, aavgo_oauth_state_secret());

    return $encodedPayload . '.' . $signature;
}

function aavgo_validate_oauth_state(string $state): ?array
{
    $parts = explode('.', $state, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$encodedPayload, $providedSignature] = $parts;
    $expectedSignature = hash_hmac('sha256', $encodedPayload, aavgo_oauth_state_secret());
    if (!hash_equals($expectedSignature, $providedSignature)) {
        return null;
    }

    $decodedJson = aavgo_base64url_decode($encodedPayload);
    if (!is_string($decodedJson) || $decodedJson === '') {
        return null;
    }

    $payload = json_decode($decodedJson, true);
    if (!is_array($payload)) {
        return null;
    }

    $issuedAt = (int) ($payload['iat'] ?? 0);
    if ($issuedAt <= 0 || abs(time() - $issuedAt) > AAVGO_OAUTH_STATE_TTL) {
        return null;
    }

    $payload['after_login'] = aavgo_normalize_after_login_path((string) ($payload['after_login'] ?? ''));
    return $payload;
}

function aavgo_role_label_map(): array
{
    return [
        AAVGO_DEVELOPER_ROLE_ID => 'Developer',
        AAVGO_OPERATIONS_MANAGER_ROLE_ID => 'Operations Manager',
        AAVGO_TEAM_LEADER_ROLE_ID => 'Team Leader',
        AAVGO_SME_ROLE_ID => 'SME',
        AAVGO_AGENT_ROLE_ID => 'Agent',
        AAVGO_TRAINEE_ROLE_ID => 'Trainee',
    ];
}

function aavgo_user_role_labels(array $user): array
{
    $roleIds = aavgo_parse_id_list($user['role_ids'] ?? []);
    $labels = [];
    $labelMap = aavgo_role_label_map();

    foreach ($labelMap as $roleId => $label) {
        if (in_array($roleId, $roleIds, true)) {
            $labels[$label] = $label;
        }
    }

    if ($labels === []) {
        $fallback = aavgo_user_access_level($user) === 'admin' ? 'Leadership' : 'User';
        $labels[$fallback] = $fallback;
    }

    return array_values($labels);
}

function aavgo_user_role_summary(array $user): string
{
    return implode(' / ', aavgo_user_role_labels($user));
}

function aavgo_find_hours_person_for_user(array $user): ?array
{
    $userId = trim((string) ($user['id'] ?? ''));
    return $userId !== '' ? aavgo_find_hours_person_by_discord_id($userId) : null;
}

function aavgo_find_hours_person_by_discord_id(string $userId): ?array
{
    $payload = aavgo_fetch_hours_bridge_payload();
    if (!($payload['ok'] ?? false) || !is_array($payload['data']['people'] ?? null)) {
        return null;
    }

    foreach ($payload['data']['people'] as $person) {
        if (!is_array($person)) {
            continue;
        }

        if (trim((string) ($person['discordId'] ?? '')) === $userId) {
            return $person;
        }
    }

    return null;
}

function aavgo_role_ids_from_snapshot_person(array $person): array
{
    $labels = [];

    if (is_array($person['roleLabels'] ?? null)) {
        foreach ($person['roleLabels'] as $label) {
            $text = trim((string) $label);
            if ($text !== '') {
                $labels[] = $text;
            }
        }
    }

    $singleRole = trim((string) ($person['role'] ?? ''));
    if ($singleRole !== '') {
        $labels[] = $singleRole;
    }

    $ids = [];
    foreach ($labels as $label) {
        switch (strtolower($label)) {
            case 'developer':
                $ids[AAVGO_DEVELOPER_ROLE_ID] = AAVGO_DEVELOPER_ROLE_ID;
                break;
            case 'operations manager':
                $ids[AAVGO_OPERATIONS_MANAGER_ROLE_ID] = AAVGO_OPERATIONS_MANAGER_ROLE_ID;
                break;
            case 'team leader':
                $ids[AAVGO_TEAM_LEADER_ROLE_ID] = AAVGO_TEAM_LEADER_ROLE_ID;
                break;
            case 'sme':
                $ids[AAVGO_SME_ROLE_ID] = AAVGO_SME_ROLE_ID;
                break;
            case 'trainee':
                $ids[AAVGO_TRAINEE_ROLE_ID] = AAVGO_TRAINEE_ROLE_ID;
                break;
            case 'agent':
                $ids[AAVGO_AGENT_ROLE_ID] = AAVGO_AGENT_ROLE_ID;
                break;
        }
    }

    return array_values($ids);
}

function aavgo_build_session_user_from_snapshot(array $discordUser, array $person): ?array
{
    $roleIds = aavgo_role_ids_from_snapshot_person($person);
    $accessLevel = aavgo_resolve_access_level($discordUser, $roleIds);
    if ($accessLevel === null) {
        return null;
    }

    return [
        'id' => (string) ($discordUser['id'] ?? ''),
        'username' => (string) ($discordUser['username'] ?? 'Unknown User'),
        'avatar' => (string) ($discordUser['avatar'] ?? ''),
        'global_name' => (string) ($discordUser['global_name'] ?? ''),
        'nickname' => (string) ($person['displayName'] ?? $person['username'] ?? ''),
        'role_ids' => $roleIds,
        'access_level' => $accessLevel,
        'snapshot_fallback' => true,
    ];
}

function aavgo_login_url(): string
{
    $query = http_build_query([
        'client_id' => aavgo_get_config_string('client_id'),
        'redirect_uri' => aavgo_get_callback_url(),
        'response_type' => 'code',
        'scope' => AAVGO_REQUIRED_SCOPES,
        'state' => $_SESSION['discord_oauth_state'] ?? '',
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
<body class="workspace-page workspace-page-access">
  <main class="workspace-message-shell">
    <section class="workspace-message-card reveal reveal-in">
      <div class="workspace-message-main">
        <p class="dashboard-kicker">Private access status</p>
        <h1 class="workspace-message-title">{$safeTitle}</h1>
        <p class="workspace-message-copy">{$safeMessage}</p>
        <div class="workspace-message-actions">
          <a class="button button-primary" href="{$safeActionHref}">{$safeActionLabel}</a>
          <a class="button button-secondary" href="/">Back Home</a>
        </div>
      </div>
      <aside class="workspace-message-aside reveal reveal-delay-1 reveal-in">
        <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>
        <span class="dashboard-chip dashboard-chip-accent">Private front door</span>
        <strong>Discord-secured access only.</strong>
        <p>The route stays hidden until the correct role opens it. If Discord completed in another window or the app, use the secure retry button and the handoff will restart cleanly.</p>
      </aside>
    </section>
  </main>
  <script src="/script.js"></script>
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
