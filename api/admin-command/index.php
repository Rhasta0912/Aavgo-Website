<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$user = aavgo_require_access('admin');
$hoursPayload = aavgo_fetch_hours_bridge_payload();
$hoursData = is_array($hoursPayload['data'] ?? null) ? $hoursPayload['data'] : [];
$people = is_array($hoursData['people'] ?? null) ? $hoursData['people'] : [];
$meta = is_array($hoursData['meta'] ?? null) ? $hoursData['meta'] : [];

function aavgo_find_staff_row(array $people, string $discordId): ?array
{
    foreach ($people as $person) {
        if (!is_array($person)) {
            continue;
        }

        if (trim((string) ($person['discordId'] ?? '')) === $discordId) {
            return $person;
        }
    }

    return null;
}

function aavgo_find_hotel_option(array $meta, string $hotelId): ?array
{
    foreach (($meta['hotels'] ?? []) as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }

        if (trim((string) ($hotel['id'] ?? '')) === $hotelId) {
            return $hotel;
        }
    }

    return null;
}

function aavgo_validate_team_option(array $meta, string $teamName): bool
{
    foreach (($meta['teams'] ?? []) as $team) {
        if (trim((string) $team) === $teamName) {
            return true;
        }
    }

    return false;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    aavgo_json_response([
        'ok' => true,
        'management' => aavgo_build_management_payload($user, $hoursPayload),
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], 405);
    exit;
}

$decoded = aavgo_decode_json_body();
$action = trim((string) ($decoded['action'] ?? ''));
$payload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
$actor = [
    'discordId' => trim((string) ($user['id'] ?? '')),
    'name' => aavgo_display_name($user),
    'roleSummary' => aavgo_user_role_summary($user),
];

if ($action === '') {
    aavgo_json_response([
        'ok' => false,
        'error' => 'Missing admin action.',
    ], 400);
    exit;
}

$normalizedPayload = [];
switch ($action) {
    case 'update_team':
        $discordId = trim((string) ($payload['discordId'] ?? ''));
        $teamName = trim((string) ($payload['team'] ?? ''));
        $person = aavgo_find_staff_row($people, $discordId);

        if ($person === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Selected staff member was not found in the current hours snapshot.'], 404);
            exit;
        }

        if ($teamName === '' || !aavgo_validate_team_option($meta, $teamName)) {
            aavgo_json_response(['ok' => false, 'error' => 'Choose a valid team before sending the reassignment.'], 400);
            exit;
        }

        $normalizedPayload = [
            'discordId' => $discordId,
            'displayName' => (string) ($person['displayName'] ?? $person['username'] ?? 'Unknown'),
            'currentTeam' => (string) ($person['team'] ?? ''),
            'team' => $teamName,
            'hotelLabel' => (string) ($person['linkedHotel'] ?? ''),
        ];
        break;

    case 'update_hotel':
        $discordId = trim((string) ($payload['discordId'] ?? ''));
        $hotelId = trim((string) ($payload['hotelId'] ?? ''));
        $person = aavgo_find_staff_row($people, $discordId);
        $hotel = aavgo_find_hotel_option($meta, $hotelId);

        if ($person === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Selected staff member was not found in the current hours snapshot.'], 404);
            exit;
        }

        if ($hotel === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Choose a valid hotel before sending the reassignment.'], 400);
            exit;
        }

        $normalizedPayload = [
            'discordId' => $discordId,
            'displayName' => (string) ($person['displayName'] ?? $person['username'] ?? 'Unknown'),
            'hotelId' => $hotelId,
            'hotelLabel' => (string) ($hotel['name'] ?? $hotelId),
        ];
        break;

    case 'force_logout_agent':
        $discordId = trim((string) ($payload['discordId'] ?? ''));
        $person = aavgo_find_staff_row($people, $discordId);
        if ($person === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Selected staff member was not found in the current hours snapshot.'], 404);
            exit;
        }

        $normalizedPayload = [
            'discordId' => $discordId,
            'displayName' => (string) ($person['displayName'] ?? $person['username'] ?? 'Unknown'),
            'hotelLabel' => (string) ($person['linkedHotel'] ?? ''),
        ];
        break;

    case 'force_logout_hotel':
        $hotelId = trim((string) ($payload['hotelId'] ?? ''));
        $hotel = aavgo_find_hotel_option($meta, $hotelId);
        if ($hotel === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Choose a valid hotel before forcing a logout.'], 400);
            exit;
        }

        $normalizedPayload = [
            'hotelId' => $hotelId,
            'hotelLabel' => (string) ($hotel['name'] ?? $hotelId),
        ];
        break;

    case 'sync_all_roles':
    case 'push_snapshot':
        if (!aavgo_user_is_developer($user)) {
            aavgo_json_response(['ok' => false, 'error' => 'Developer access is required for that tool.'], 403);
            exit;
        }

        $normalizedPayload = [];
        break;

    default:
        aavgo_json_response([
            'ok' => false,
            'error' => 'Unsupported admin action.',
        ], 400);
        exit;
}

$command = aavgo_enqueue_admin_command($action, $normalizedPayload, $actor);

aavgo_json_response([
    'ok' => true,
    'message' => 'Leadership action queued for bot sync.',
    'command' => $command,
    'management' => aavgo_build_management_payload($user, $hoursPayload),
]);
