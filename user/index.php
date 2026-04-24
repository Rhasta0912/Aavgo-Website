<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$currentUser = aavgo_current_user();
$guestMode = !is_array($currentUser);

if ($guestMode) {
    $displayName = 'Log in with Discord';
    $safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
    $roleLabels = [];
    $roleSummary = 'Link your account to sync personal hours';
    $safeRoleSummary = htmlspecialchars($roleSummary, ENT_QUOTES, 'UTF-8');
    $showAdminLink = false;
    $hoursPayload = ['ok' => false, 'error' => 'Log in with Discord to sync personal hours.'];
    $personalHours = [
        'linkedHotel' => 'Log in to view your lane',
        'team' => 'Guest view',
        'role' => $roleSummary,
        'agentStatus' => 'Sign in required',
        'todayHours' => 0,
        'weeklyHours' => 0,
        'monthlyHours' => 0,
        'allHours' => 0,
        'payPeriods' => [
            'firstHalf' => ['label' => '1st - 15th', 'totalHours' => 0, 'days' => []],
            'secondHalf' => ['label' => '16th - end', 'totalHours' => 0, 'days' => []],
        ],
        'currentMonth' => ['label' => 'Current month', 'days' => []],
        'recentMonths' => [],
        'recentAdjustments' => [],
        'activeSession' => null,
    ];
    $hoursConnected = false;
    $payPeriods = $personalHours['payPeriods'];
    $firstHalf = $payPeriods['firstHalf'];
    $secondHalf = $payPeriods['secondHalf'];
    $currentMonth = $personalHours['currentMonth'];
    $recentMonths = [];
    $recentAdjustments = [];
    $sessionSummary = 'Log in to view session details';
} else {
    $user = $currentUser;
    $displayName = aavgo_display_name($user);
    $safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
    $roleLabels = aavgo_user_role_labels($user);
    $roleSummary = aavgo_user_role_summary($user);
    $safeRoleSummary = htmlspecialchars($roleSummary, ENT_QUOTES, 'UTF-8');
    $showAdminLink = aavgo_user_can_access($user, 'admin');
    $hoursPayload = aavgo_fetch_hours_bridge_payload();
    $personalHours = aavgo_find_hours_person_for_user($user);
    $hoursConnected = (bool) ($hoursPayload['ok'] ?? false) && is_array($personalHours);
    $payPeriods = is_array($personalHours['payPeriods'] ?? null) ? $personalHours['payPeriods'] : [];
    $firstHalf = is_array($payPeriods['firstHalf'] ?? null) ? $payPeriods['firstHalf'] : ['label' => '1st - 15th', 'totalHours' => 0, 'days' => []];
    $secondHalf = is_array($payPeriods['secondHalf'] ?? null) ? $payPeriods['secondHalf'] : ['label' => '16th - end', 'totalHours' => 0, 'days' => []];
    $currentMonth = is_array($personalHours['currentMonth'] ?? null) ? $personalHours['currentMonth'] : ['label' => 'Current month', 'days' => []];
    $recentMonths = is_array($personalHours['recentMonths'] ?? null) ? $personalHours['recentMonths'] : [];
    $recentAdjustments = is_array($personalHours['recentAdjustments'] ?? null) ? $personalHours['recentAdjustments'] : [];
    $sessionSummary = 'Offline right now';
    if (is_array($personalHours['activeSession'] ?? null)) {
        $sessionSummary = sprintf(
            '%s - %sh',
            trim((string) ($personalHours['activeSession']['kind'] ?? 'Live Shift')),
            aavgo_user_hours_label($personalHours['activeSession']['elapsedHours'] ?? 0)
        );
    }
}

function aavgo_user_hours_label(mixed $value): string
{
    $number = (float) $value;
    if (!is_finite($number)) {
        $number = 0.0;
    }

    $formatted = number_format($number, 1);
    return preg_replace('/\.0$/', '', $formatted) ?: '0';
}

function aavgo_user_text(mixed $value, string $fallback = 'Unavailable'): string
{
    $text = trim((string) $value);
    return htmlspecialchars($text !== '' ? $text : $fallback, ENT_QUOTES, 'UTF-8');
}

function aavgo_render_hours_day_list(array $days): string
{
    $visibleDays = array_values(array_filter($days, static function (array $day): bool {
        return (float) ($day['totalHours'] ?? 0) > 0;
    }));

    if ($visibleDays === []) {
        return '<p class="dashboard-panel-copy">No tracked days in this range yet.</p>';
    }

    $items = array_slice($visibleDays, 0, 8);
    $html = '<ul class="dashboard-inline-list">';
    foreach ($items as $day) {
        $label = 'Day ' . (int) ($day['day'] ?? 0);
        $hours = aavgo_user_hours_label($day['totalHours'] ?? 0) . 'h';
        $html .= '<li><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong>' . htmlspecialchars($hours, ENT_QUOTES, 'UTF-8') . '</strong></li>';
    }
    $html .= '</ul>';

    return $html;
}

function aavgo_user_first_text(array $source, array $keys, string $fallback = 'Unavailable'): string
{
    foreach ($keys as $key) {
        $value = trim((string) ($source[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return $fallback;
}

function aavgo_user_handover_items(array $person): array
{
    $candidateKeys = ['handoverInbox', 'handoverNotes', 'unreadHandoverNotes', 'pendingHandoverNotes', 'notes'];
    foreach ($candidateKeys as $key) {
        if (!is_array($person[$key] ?? null)) {
            continue;
        }

        $items = [];
        foreach ($person[$key] as $entry) {
            if (is_string($entry)) {
                $text = trim($entry);
                if ($text !== '') {
                    $items[] = ['title' => 'Handover note', 'body' => $text, 'meta' => 'Current lane'];
                }
                continue;
            }

            if (!is_array($entry)) {
                continue;
            }

            $body = aavgo_user_first_text($entry, ['body', 'message', 'note', 'content', 'text'], '');
            if ($body === '') {
                continue;
            }

            $items[] = [
                'title' => aavgo_user_first_text($entry, ['title', 'hotelLabel', 'hotel', 'authorName'], 'Handover note'),
                'body' => $body,
                'meta' => aavgo_user_first_text($entry, ['createdAt', 'updatedAt', 'status'], 'Current lane'),
            ];
        }

        return array_slice($items, 0, 4);
    }

    return [];
}

function aavgo_user_workspace_timeline(array $person, bool $guestMode, bool $hoursConnected): array
{
    $activeSession = is_array($person['activeSession'] ?? null) ? $person['activeSession'] : null;
    $activeNow = (bool) ($person['activeNow'] ?? false) || $activeSession !== null;
    $todayHours = (float) ($person['todayHours'] ?? 0);

    if ($guestMode) {
        return [
            ['label' => 'Discord access', 'state' => 'current', 'detail' => 'Sign in to unlock your workspace.'],
            ['label' => 'Hours sync', 'state' => 'idle', 'detail' => 'Your hours appear after login.'],
            ['label' => 'Shift context', 'state' => 'idle', 'detail' => 'Hotel and handover notes stay private.'],
        ];
    }

    return [
        [
            'label' => 'Attendance',
            'state' => $activeNow ? 'done' : 'current',
            'detail' => $activeNow ? 'Attendance is connected to your live session.' : 'Post your login message before your shift.',
        ],
        [
            'label' => 'Login',
            'state' => $activeNow ? 'done' : ($hoursConnected ? 'current' : 'idle'),
            'detail' => $activeNow ? 'You are live right now.' : 'Wait for the bot confirmation.',
        ],
        [
            'label' => 'Shift desk',
            'state' => $activeNow ? 'current' : 'idle',
            'detail' => $activeNow ? aavgo_user_first_text($activeSession ?? [], ['kind'], 'Live shift') : 'Your hotel lane appears when you are live.',
        ],
        [
            'label' => 'Hours posted',
            'state' => $todayHours > 0 ? 'done' : 'idle',
            'detail' => $todayHours > 0 ? aavgo_user_hours_label($todayHours) . 'h tracked today.' : 'No hours logged today yet.',
        ],
    ];
}

$activeSession = is_array($personalHours['activeSession'] ?? null) ? $personalHours['activeSession'] : null;
$activeNow = !$guestMode && ((bool) ($personalHours['activeNow'] ?? false) || $activeSession !== null);
$todayStatusLabel = $guestMode ? 'Sign in required' : ($activeNow ? 'Live now' : aavgo_user_first_text($personalHours, ['agentStatus'], 'Standby'));
$todayStatusClass = $activeNow ? 'is-live' : 'is-idle';
$todayHotelLabel = aavgo_user_first_text($personalHours, ['linkedHotel', 'assignedHotel'], $guestMode ? 'Private after login' : 'Hotel not assigned yet');
$todayTeamLabel = aavgo_user_first_text($personalHours, ['team'], $guestMode ? 'Discord gated' : 'Team pending');
$todayNextAction = $guestMode
    ? 'Log in with Discord to open your private lane.'
    : ($activeNow
        ? 'You are live. Stay in the right voice channel and check handover before logout.'
        : ($hoursConnected ? 'Post in Attendance before your shift, then wait for the bot confirmation.' : 'Your hours are syncing. Refresh in a moment if something looks old.'));
$handoverItems = aavgo_user_handover_items($personalHours);
$workspaceTimeline = aavgo_user_workspace_timeline($personalHours, $guestMode, $hoursConnected);
$attendanceDiscordUrl = 'https://discord.com/channels/1482220918355922974/1489840627209470022';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aavgo | My Hours</title>
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta
    name="description"
    content="Private Aavgo staff workspace showing personal tracked hours, pay-period totals, and recent hour history."
  >
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;700;800&family=Instrument+Serif:ital@0;1&display=swap"
    rel="stylesheet"
  >
<link rel="stylesheet" href="<?= htmlspecialchars(aavgo_asset_url('/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="workspace-page workspace-dashboard workspace-page-user">
  <div class="dashboard-shell dashboard-shell-user">
    <aside class="dashboard-sidebar dashboard-sidebar-user reveal reveal-in">
      <div class="dashboard-sidebar-glow"></div>
      <div class="dashboard-sidebar-top">
        <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>
      </div>

      <nav class="dashboard-nav dashboard-nav-vertical" aria-label="User navigation">
        <a class="dashboard-nav-link dashboard-user-nav-home is-active" href="/user/">Workspace</a>
        <details class="dashboard-user-nav-menu">
          <summary>Sections</summary>
          <div class="dashboard-user-nav-menu-list">
            <a href="#user-handover">Handover</a>
            <a href="#user-pay-periods">Pay periods</a>
            <a href="#user-history">Hour history</a>
            <?php if ($showAdminLink): ?>
              <a href="/admin/">Leadership board</a>
            <?php endif; ?>
          </div>
        </details>
      </nav>

      <section class="dashboard-sidebar-glance" aria-label="Quick glance">
        <div class="dashboard-sidebar-glance-head">
          <span class="dashboard-sidebar-glance-dot" aria-hidden="true"></span>
          <p class="dashboard-kicker">Start here</p>
        </div>
        <strong>Check today, read handover, confirm your hours.</strong>
        <dl class="dashboard-sidebar-glance-grid">
          <div>
            <dt>Role</dt>
            <dd><?php echo $safeRoleSummary; ?></dd>
          </div>
          <div>
            <dt>View</dt>
            <dd>Workspace</dd>
          </div>
          <div>
            <dt>Sync</dt>
            <dd><?php echo $hoursConnected ? 'Live' : 'Waiting'; ?></dd>
          </div>
        </dl>
      </section>

      <?php if ($guestMode): ?>
        <section class="dashboard-sidebar-bottom dashboard-sidebar-bottom-guest" aria-label="Profile and session actions">
          <a class="dashboard-sidebar-login" href="/auth/discord/login/">
            <p class="dashboard-kicker">Discord login</p>
            <strong>Log in, sync, and link your account.</strong>
            <span>Use Discord to unlock the private hours workspace.</span>
          </a>
        </section>
      <?php else: ?>
        <section class="dashboard-sidebar-bottom" aria-label="Profile and session actions">
          <div class="dashboard-sidebar-footer-copy">
            <strong><?php echo $safeDisplayName; ?></strong>
            <p class="dashboard-profile-role" data-role="<?php echo htmlspecialchars(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $roleSummary)), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $safeRoleSummary; ?></p>
          </div>
          <a class="dashboard-nav-link dashboard-sidebar-logout" href="/auth/logout/" aria-label="Log out"></a>
        </section>
      <?php endif; ?>
    </aside>

    <main class="dashboard-main dashboard-main-user">
      <header class="dashboard-header dashboard-header-admin reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb"><?php echo $guestMode ? 'Workspace / Discord access' : 'Workspace / Agent desk'; ?></p>
          <h1 class="dashboard-title dashboard-title-wide"><?php echo $guestMode ? 'Open your workspace.' : 'Workspace'; ?></h1>
          <p class="dashboard-subtitle">
            <?php echo $guestMode ? 'Log in with Discord to see your private hours and shift context.' : 'Start with Today. Check handover before your shift, then use hours when you need payroll totals.'; ?>
          </p>
        </div>
        <div class="dashboard-toolbar">
          <button class="dashboard-sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
            <span></span>
            <span></span>
            <span></span>
          </button>
          <?php if ($guestMode): ?>
            <a class="dashboard-toolbar-link" href="/auth/discord/login/">Log in with Discord</a>
          <?php else: ?>
            <?php if ($showAdminLink): ?>
              <a class="dashboard-toolbar-link" href="/admin/">Leadership board</a>
            <?php endif; ?>
            <a class="dashboard-toolbar-link" href="/auth/logout/">Log out</a>
          <?php endif; ?>
        </div>
      </header>

      <?php if ($guestMode): ?>
        <section class="dashboard-inline-notice reveal reveal-delay-1">
          <strong>Log in to unlock personal hours.</strong>
          <p>Your Discord account is the key to syncing roles, linking hours, and opening the private workspace.</p>
        </section>

        <section class="dashboard-panel dashboard-login-panel reveal reveal-delay-2">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Private access</p>
              <h2>Continue with Discord</h2>
            </div>
          </div>
          <p class="dashboard-panel-copy">Use the secure Discord handoff to connect your Aavgo account and come back to the same private workspace automatically.</p>
          <a class="button button-primary dashboard-login-button" href="/auth/discord/login/">Log in with Discord</a>
        </section>
      <?php else: ?>
      <?php if (!$hoursConnected): ?>
        <section class="dashboard-inline-notice reveal reveal-delay-1">
          <strong>Your personal hours are still syncing.</strong>
          <p><?php echo aavgo_user_text($hoursPayload['error'] ?? 'The website has not received your personal hours yet.'); ?></p>
        </section>
      <?php endif; ?>

      <section class="dashboard-user-workspace reveal reveal-delay-1" aria-label="Agent workspace overview">
        <article class="dashboard-panel dashboard-user-today-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Today</p>
              <h2><?php echo aavgo_user_text($todayHotelLabel); ?></h2>
            </div>
            <span class="dashboard-status-pill <?php echo htmlspecialchars($todayStatusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo aavgo_user_text($todayStatusLabel); ?></span>
          </div>
          <div class="dashboard-user-today-body">
            <div class="dashboard-user-today-focus">
              <span>Next action</span>
              <strong><?php echo aavgo_user_text($todayNextAction); ?></strong>
            </div>
            <div class="dashboard-user-context-grid">
              <div>
                <span>Team</span>
                <strong><?php echo aavgo_user_text($todayTeamLabel); ?></strong>
              </div>
              <div>
                <span>Session</span>
                <strong><?php echo aavgo_user_text($sessionSummary, 'Offline right now'); ?></strong>
              </div>
              <div>
                <span>Today</span>
                <strong><?php echo aavgo_user_hours_label($personalHours['todayHours'] ?? 0); ?>h</strong>
              </div>
            </div>
          </div>
          <div class="dashboard-user-quick-actions" aria-label="Quick actions">
            <a class="dashboard-user-action" href="<?php echo htmlspecialchars($attendanceDiscordUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Attendance</a>
            <a class="dashboard-user-action" href="#user-handover">Handover</a>
            <a class="dashboard-user-action" href="#user-pay-periods">Pay periods</a>
          </div>
        </article>

        <article class="dashboard-panel dashboard-user-timeline-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Shift timeline</p>
              <h2>Where your day stands</h2>
            </div>
          </div>
          <ol class="dashboard-user-timeline">
            <?php foreach ($workspaceTimeline as $step): ?>
              <?php $state = in_array($step['state'] ?? 'idle', ['done', 'current', 'idle'], true) ? (string) $step['state'] : 'idle'; ?>
              <li class="is-<?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?>">
                <span></span>
                <div>
                  <strong><?php echo aavgo_user_text($step['label'] ?? 'Step'); ?></strong>
                  <p><?php echo aavgo_user_text($step['detail'] ?? 'Waiting for the next sync.'); ?></p>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        </article>
      </section>

      <section class="dashboard-user-grid dashboard-user-grid-handover reveal reveal-delay-2" id="user-handover">
        <article class="dashboard-panel dashboard-user-handover-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Handover inbox</p>
              <h2>Notes for your lane</h2>
            </div>
            <span class="dashboard-chip"><?php echo htmlspecialchars((string) count($handoverItems), ENT_QUOTES, 'UTF-8'); ?> open</span>
          </div>
          <div class="dashboard-user-handover-list">
            <?php if ($handoverItems === []): ?>
              <div class="dashboard-empty-state dashboard-user-empty-state">
                <strong>No unread handover notes.</strong>
                <p>Your lane is clear in the current website snapshot.</p>
              </div>
            <?php else: ?>
              <?php foreach ($handoverItems as $item): ?>
                <article class="dashboard-user-handover-item">
                  <span><?php echo aavgo_user_text($item['meta'] ?? 'Current lane'); ?></span>
                  <strong><?php echo aavgo_user_text($item['title'] ?? 'Handover note'); ?></strong>
                  <p><?php echo aavgo_user_text($item['body'] ?? ''); ?></p>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </article>

        <article class="dashboard-panel dashboard-user-shift-card">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Current assignment</p>
              <h2><?php echo aavgo_user_text($todayHotelLabel); ?></h2>
            </div>
          </div>
          <div class="dashboard-control-list">
            <div class="dashboard-control-item">
              <strong>Role</strong>
              <span><?php echo aavgo_user_text($personalHours['role'] ?? $roleSummary); ?></span>
            </div>
            <div class="dashboard-control-item">
              <strong>Status</strong>
              <span><?php echo aavgo_user_text($todayStatusLabel); ?></span>
            </div>
            <div class="dashboard-control-item">
              <strong>Team</strong>
              <span><?php echo aavgo_user_text($todayTeamLabel); ?></span>
            </div>
          </div>
        </article>
      </section>

      <section class="dashboard-user-stat-grid reveal reveal-delay-1" aria-label="Personal hour totals">
        <article class="dashboard-stat-card">
          <p>Today</p>
          <strong><?php echo aavgo_user_hours_label($personalHours['todayHours'] ?? 0); ?>h</strong>
          <span>Logged today</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Week</p>
          <strong><?php echo aavgo_user_hours_label($personalHours['weeklyHours'] ?? 0); ?>h</strong>
          <span>This week so far</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Month</p>
          <strong><?php echo aavgo_user_hours_label($personalHours['monthlyHours'] ?? 0); ?>h</strong>
          <span>This month so far</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Total</p>
          <strong><?php echo aavgo_user_hours_label($personalHours['allHours'] ?? 0); ?>h</strong>
          <span>Saved to your account</span>
        </article>
      </section>

      <section class="dashboard-user-grid dashboard-user-grid-payroll reveal reveal-delay-2">
        <article class="dashboard-panel" id="user-pay-periods">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Pay periods</p>
              <h2>1st - 15th and 16th - month end</h2>
            </div>
          </div>
          <div class="dashboard-pay-period-grid">
            <section class="dashboard-pay-period-card">
              <span class="dashboard-chip"><?php echo aavgo_user_text($firstHalf['label'] ?? '1-15'); ?></span>
              <strong><?php echo aavgo_user_hours_label($firstHalf['totalHours'] ?? 0); ?>h</strong>
              <p>Hours tracked in the first payroll half.</p>
              <?php echo aavgo_render_hours_day_list(is_array($firstHalf['days'] ?? null) ? $firstHalf['days'] : []); ?>
            </section>
            <section class="dashboard-pay-period-card">
              <span class="dashboard-chip"><?php echo aavgo_user_text($secondHalf['label'] ?? '16-end'); ?></span>
              <strong><?php echo aavgo_user_hours_label($secondHalf['totalHours'] ?? 0); ?>h</strong>
              <p>Hours tracked from the 16th through month end.</p>
              <?php echo aavgo_render_hours_day_list(is_array($secondHalf['days'] ?? null) ? $secondHalf['days'] : []); ?>
            </section>
          </div>
        </article>
      </section>

      <section class="dashboard-user-grid dashboard-user-grid-history reveal reveal-delay-2" id="user-history">
        <article class="dashboard-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Current month history</p>
              <h2><?php echo aavgo_user_text($currentMonth['label'] ?? 'Current month'); ?></h2>
            </div>
          </div>
          <div class="dashboard-hours-table-wrap">
            <table class="dashboard-hours-table dashboard-hours-table-compact">
              <thead>
                <tr>
                  <th>Day</th>
                  <th>Total</th>
                  <th>Shift</th>
                  <th>Training</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $monthDays = is_array($currentMonth['days'] ?? null) ? $currentMonth['days'] : [];
                $visibleMonthDays = array_values(array_filter($monthDays, static function (array $day): bool {
                    return (float) ($day['totalHours'] ?? 0) > 0;
                }));
                ?>
                <?php if ($visibleMonthDays === []): ?>
                  <tr>
                    <td colspan="4">
                      <div class="dashboard-empty-state">
                        <strong>No tracked month history yet.</strong>
                        <p>Daily hour rows will show up here once sessions are recorded in this month.</p>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($visibleMonthDays as $day): ?>
                    <tr>
                      <td>Day <?php echo htmlspecialchars((string) ($day['day'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo aavgo_user_hours_label($day['totalHours'] ?? 0); ?>h</td>
                      <td><?php echo aavgo_user_hours_label($day['shiftHours'] ?? 0); ?>h</td>
                      <td><?php echo aavgo_user_hours_label($day['trainingHours'] ?? 0); ?>h</td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>

        <article class="dashboard-panel" id="user-month-summary">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Recent month totals</p>
              <h2>Quick month-by-month read</h2>
            </div>
          </div>
          <div class="dashboard-mini-grid">
            <?php if ($recentMonths === []): ?>
              <div class="dashboard-mini-card">
                <strong>Waiting for more history</strong>
                <p>Recent month totals will appear once more data is recorded.</p>
              </div>
            <?php else: ?>
              <?php foreach ($recentMonths as $month): ?>
                <div class="dashboard-mini-card">
                  <strong><?php echo aavgo_user_text($month['label'] ?? 'Month'); ?></strong>
                  <p>Total: <?php echo aavgo_user_hours_label($month['totalHours'] ?? 0); ?>h</p>
                  <span>Shift: <?php echo aavgo_user_hours_label($month['shiftHours'] ?? 0); ?>h</span>
                  <span>Training: <?php echo aavgo_user_hours_label($month['trainingHours'] ?? 0); ?>h</span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="dashboard-adjustment-log dashboard-adjustment-log-user is-hidden">
            <?php if ($recentAdjustments === []): ?>
              <div class="dashboard-empty-state">
                <strong>No recent manual adjustments.</strong>
                <p>If leadership edits your hours, the latest entries will appear here.</p>
              </div>
            <?php else: ?>
              <?php foreach ($recentAdjustments as $entry): ?>
                <article class="dashboard-adjustment-item">
                  <div class="dashboard-adjustment-top">
                    <span class="dashboard-chip"><?php echo aavgo_user_text(($entry['mode'] ?? 'shift') === 'training' ? 'Training' : 'Live shift'); ?></span>
                    <strong><?php echo aavgo_user_text($entry['shiftDate'] ?? ''); ?></strong>
                  </div>
                  <p>
                    <?php echo aavgo_user_text($entry['hotelLabel'] ?? 'N/A'); ?>
                    ·
                    <?php echo aavgo_user_text($entry['loginTime'] ?? '--:--'); ?>
                    -
                    <?php echo aavgo_user_text($entry['logoutTime'] ?? '--:--'); ?>
                    ·
                    <?php echo aavgo_user_hours_label($entry['hours'] ?? 0); ?>h
                  </p>
                  <span><?php echo aavgo_user_text($entry['reason'] ?? 'Manual adjustment'); ?></span>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </article>
      </section>
      <?php endif; ?>
    </main>
  </div>

  <script>
    window.AAVGO_LIVE_SIGNALS_ENDPOINT = '/api/live-signals/';
  </script>
<script src="<?= htmlspecialchars(aavgo_asset_url('/script.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
