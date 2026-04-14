<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$user = aavgo_require_access('admin');
$hoursPayload = aavgo_fetch_hours_bridge_payload();
$hoursData = is_array($hoursPayload['data'] ?? null) ? $hoursPayload['data'] : [];
$people = is_array($hoursData['people'] ?? null) ? $hoursData['people'] : [];
$meta = is_array($hoursData['meta'] ?? null) ? $hoursData['meta'] : [];
$meta['hotels'] = aavgo_normalize_hotel_options(is_array($meta['hotels'] ?? null) ? $meta['hotels'] : []);

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

function aavgo_normalize_discord_id_list(mixed $value): array
{
    $list = is_array($value) ? $value : [$value];
    $normalized = [];

    foreach ($list as $item) {
        $discordId = trim((string) $item);
        if ($discordId !== '') {
            $normalized[$discordId] = $discordId;
        }
    }

    return array_values($normalized);
}

function aavgo_find_staff_rows_by_ids(array $people, array $discordIds): array
{
    $indexed = [];
    foreach ($people as $person) {
        if (!is_array($person)) {
            continue;
        }

        $discordId = trim((string) ($person['discordId'] ?? ''));
        if ($discordId !== '') {
            $indexed[$discordId] = $person;
        }
    }

    $rows = [];
    foreach ($discordIds as $discordId) {
        if (isset($indexed[$discordId])) {
            $rows[] = $indexed[$discordId];
        }
    }

    return $rows;
}

function aavgo_validate_shift_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    return strtotime($value . ' 00:00:00 UTC') !== false;
}

function aavgo_validate_clock_time(string $value): bool
{
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return false;
    }

    [$hours, $minutes] = array_map('intval', explode(':', $value, 2));
    return $hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59;
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
$managementPayload = aavgo_build_management_payload($user, $hoursPayload);
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
    case 'broadcast_announcement':
        if (!(bool) ($managementPayload['actions']['canBroadcast'] ?? false)) {
            aavgo_json_response(['ok' => false, 'error' => 'This role cannot send leadership broadcasts.'], 403);
            exit;
        }

        $targetDiscordId = trim((string) ($payload['targetDiscordId'] ?? ''));
        $target = null;
        if ($targetDiscordId !== '') {
            $targetRow = aavgo_find_staff_row($people, $targetDiscordId);
            if ($targetRow === null) {
                aavgo_json_response(['ok' => false, 'error' => 'The selected staff target was not found in the current hours snapshot.'], 404);
                exit;
            }
            $target = [
                'discordId' => $targetDiscordId,
                'name' => trim((string) ($targetRow['displayName'] ?? $targetRow['username'] ?? 'Selected staff')),
                'roleSummary' => trim((string) ($targetRow['roleSummary'] ?? $targetRow['role'] ?? 'Staff')),
            ];
        }

        $announcement = aavgo_publish_announcement(
            (string) ($payload['message'] ?? ''),
            trim((string) ($payload['tone'] ?? 'standard')),
            $actor,
            $target
        );

        aavgo_json_response([
            'ok' => true,
            'message' => 'Leadership broadcast is live across the signed-in website.',
            'announcement' => $announcement,
            'management' => aavgo_build_management_payload($user, $hoursPayload),
        ]);
        exit;

    case 'clear_announcement':
        if (!(bool) ($managementPayload['actions']['canBroadcast'] ?? false)) {
            aavgo_json_response(['ok' => false, 'error' => 'This role cannot clear leadership broadcasts.'], 403);
            exit;
        }

        if (!aavgo_clear_announcement($actor)) {
            aavgo_json_response(['ok' => false, 'error' => 'There is no live announcement to clear.'], 400);
            exit;
        }

        aavgo_json_response([
            'ok' => true,
            'message' => 'The live leadership broadcast was cleared.',
            'management' => aavgo_build_management_payload($user, $hoursPayload),
        ]);
        exit;

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

    case 'bulk_update_team':
        if (!(bool) ($managementPayload['actions']['canBulkManage'] ?? false) || !(bool) ($managementPayload['actions']['canReassign'] ?? false)) {
            aavgo_json_response(['ok' => false, 'error' => 'This role cannot bulk reassign teams from the website.'], 403);
            exit;
        }

        $discordIds = aavgo_normalize_discord_id_list($payload['discordIds'] ?? []);
        $teamName = trim((string) ($payload['team'] ?? ''));
        $rows = aavgo_find_staff_rows_by_ids($people, $discordIds);

        if ($discordIds === [] || $rows === []) {
            aavgo_json_response(['ok' => false, 'error' => 'Select at least one valid staff member before changing the team.'], 400);
            exit;
        }

        if ($teamName === '' || !aavgo_validate_team_option($meta, $teamName)) {
            aavgo_json_response(['ok' => false, 'error' => 'Choose a valid team before sending the bulk reassignment.'], 400);
            exit;
        }

        $normalizedPayload = [
            'discordIds' => $discordIds,
            'displayNames' => array_values(array_map(static fn(array $row): string => (string) ($row['displayName'] ?? $row['username'] ?? 'Unknown'), $rows)),
            'team' => $teamName,
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

    case 'bulk_update_hotel':
        if (!(bool) ($managementPayload['actions']['canBulkManage'] ?? false) || !(bool) ($managementPayload['actions']['canReassign'] ?? false)) {
            aavgo_json_response(['ok' => false, 'error' => 'This role cannot bulk reassign hotels from the website.'], 403);
            exit;
        }

        $discordIds = aavgo_normalize_discord_id_list($payload['discordIds'] ?? []);
        $hotelId = trim((string) ($payload['hotelId'] ?? ''));
        $rows = aavgo_find_staff_rows_by_ids($people, $discordIds);
        $hotel = aavgo_find_hotel_option($meta, $hotelId);

        if ($discordIds === [] || $rows === []) {
            aavgo_json_response(['ok' => false, 'error' => 'Select at least one valid staff member before changing the hotel.'], 400);
            exit;
        }

        if ($hotel === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Choose a valid hotel before sending the bulk hotel move.'], 400);
            exit;
        }

        $normalizedPayload = [
            'discordIds' => $discordIds,
            'displayNames' => array_values(array_map(static fn(array $row): string => (string) ($row['displayName'] ?? $row['username'] ?? 'Unknown'), $rows)),
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

    case 'bulk_force_logout_agents':
        if (!(bool) ($managementPayload['actions']['canBulkManage'] ?? false) || !(bool) ($managementPayload['actions']['canForceLogout'] ?? false)) {
            aavgo_json_response(['ok' => false, 'error' => 'This role cannot bulk force logout staff from the website.'], 403);
            exit;
        }

        $discordIds = aavgo_normalize_discord_id_list($payload['discordIds'] ?? []);
        $rows = aavgo_find_staff_rows_by_ids($people, $discordIds);

        if ($discordIds === [] || $rows === []) {
            aavgo_json_response(['ok' => false, 'error' => 'Select at least one valid staff member before forcing a logout.'], 400);
            exit;
        }

        $normalizedPayload = [
            'discordIds' => $discordIds,
            'displayNames' => array_values(array_map(static fn(array $row): string => (string) ($row['displayName'] ?? $row['username'] ?? 'Unknown'), $rows)),
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

    case 'add_manual_hours':
        if (!(bool) ($managementPayload['actions']['canEditHours'] ?? false)) {
            aavgo_json_response(['ok' => false, 'error' => 'This role cannot edit hours from the website.'], 403);
            exit;
        }

        $discordId = trim((string) ($payload['discordId'] ?? ''));
        $person = aavgo_find_staff_row($people, $discordId);
        $shiftDate = trim((string) ($payload['shiftDate'] ?? ''));
        $loginTime = trim((string) ($payload['loginTime'] ?? ''));
        $logoutTime = trim((string) ($payload['logoutTime'] ?? ''));
        $mode = trim((string) ($payload['mode'] ?? 'shift'));
        $reason = trim((string) ($payload['reason'] ?? ''));
        $hotelId = trim((string) ($payload['hotelId'] ?? ''));

        if ($person === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Select a valid staff member before editing hours.'], 404);
            exit;
        }

        if (!aavgo_validate_shift_date($shiftDate) || !aavgo_validate_clock_time($loginTime) || !aavgo_validate_clock_time($logoutTime)) {
            aavgo_json_response(['ok' => false, 'error' => 'Use a valid date and 24-hour login/logout times for the manual hours entry.'], 400);
            exit;
        }

        if ($reason === '') {
            aavgo_json_response(['ok' => false, 'error' => 'Add a reason before saving the manual hours correction.'], 400);
            exit;
        }

        if ($mode === 'shift' && $hotelId !== '' && aavgo_find_hotel_option($meta, $hotelId) === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Choose a valid hotel for live-shift hour corrections.'], 400);
            exit;
        }

        $normalizedPayload = [
            'discordId' => $discordId,
            'displayName' => (string) ($person['displayName'] ?? $person['username'] ?? 'Unknown'),
            'shiftDate' => $shiftDate,
            'loginTime' => $loginTime,
            'logoutTime' => $logoutTime,
            'mode' => $mode,
            'reason' => $reason,
            'hotelId' => $hotelId,
        ];
        break;

    case 'remove_manual_hours':
        if (!(bool) ($managementPayload['actions']['canEditHours'] ?? false)) {
            aavgo_json_response(['ok' => false, 'error' => 'This role cannot edit hours from the website.'], 403);
            exit;
        }

        $discordId = trim((string) ($payload['discordId'] ?? ''));
        $person = aavgo_find_staff_row($people, $discordId);
        $shiftDate = trim((string) ($payload['shiftDate'] ?? ''));
        $hours = (float) ($payload['hours'] ?? 0);
        $mode = trim((string) ($payload['mode'] ?? 'shift'));
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($person === null) {
            aavgo_json_response(['ok' => false, 'error' => 'Select a valid staff member before removing hours.'], 404);
            exit;
        }

        if (!aavgo_validate_shift_date($shiftDate) || $hours <= 0) {
            aavgo_json_response(['ok' => false, 'error' => 'Use a valid date and a positive hour amount for the removal.'], 400);
            exit;
        }

        if ($reason === '') {
            aavgo_json_response(['ok' => false, 'error' => 'Add a reason before saving the manual hour removal.'], 400);
            exit;
        }

        $normalizedPayload = [
            'discordId' => $discordId,
            'displayName' => (string) ($person['displayName'] ?? $person['username'] ?? 'Unknown'),
            'shiftDate' => $shiftDate,
            'hours' => $hours,
            'mode' => $mode,
            'reason' => $reason,
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
