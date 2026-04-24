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
    if (!is_array($personalHours)) {
        $personalHours = [
            'linkedHotel' => 'Hotel not assigned yet',
            'team' => 'Team pending',
            'role' => $roleSummary,
            'agentStatus' => 'Sync pending',
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
    }
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

$activeSession = is_array($personalHours['activeSession'] ?? null) ? $personalHours['activeSession'] : null;
$activeNow = !$guestMode && ((bool) ($personalHours['activeNow'] ?? false) || $activeSession !== null);
$todayStatusLabel = $guestMode ? 'Sign in required' : ($activeNow ? 'Live now' : aavgo_user_first_text($personalHours, ['agentStatus'], 'Standby'));
$todayStatusClass = $activeNow ? 'is-live' : 'is-idle';
$todayHotelLabel = aavgo_user_first_text($personalHours, ['linkedHotel', 'assignedHotel'], $guestMode ? 'Private after login' : 'Hotel not assigned yet');
$todayTeamLabel = aavgo_user_first_text($personalHours, ['team'], $guestMode ? 'Discord gated' : 'Team pending');
$todayNextAction = $guestMode
    ? 'Log in with Discord to open your private lane.'
    : ($activeNow
        ? 'You are live. Stay in the right voice channel and keep your hours clean.'
        : ($hoursConnected ? 'Post in Attendance before your shift, then wait for the bot confirmation.' : 'Your hours are syncing. Refresh in a moment if something looks old.'));
$attendanceDiscordUrl = 'https://discord.com/channels/1482220918355922974/1489840627209470022';
$csrfToken = aavgo_csrf_token();

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
        <strong>Check today, confirm your hours, keep payroll clean.</strong>
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
            <?php echo $guestMode ? 'Log in with Discord to see your private hours and shift context.' : 'Your shift, pay, and hour history in one clean desk.'; ?>
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

      <section class="dashboard-user-command reveal reveal-delay-1" aria-label="Agent command center">
        <article class="dashboard-user-hero-card">
          <div class="dashboard-user-hero-topline">
            <span class="dashboard-user-eyebrow">Today at Aavgo</span>
            <span class="dashboard-status-pill <?php echo htmlspecialchars($todayStatusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo aavgo_user_text($todayStatusLabel); ?></span>
          </div>
          <h2><?php echo aavgo_user_text($todayHotelLabel); ?></h2>
          <p><?php echo aavgo_user_text($todayNextAction); ?></p>
          <div class="dashboard-user-hero-actions" aria-label="Quick actions">
            <a class="dashboard-user-primary-action" href="<?php echo htmlspecialchars($attendanceDiscordUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open Attendance</a>
            <a class="dashboard-user-secondary-action" href="#user-pay-periods">Check pay</a>
            <a class="dashboard-user-secondary-action" href="#user-history">View history</a>
          </div>
        </article>

        <aside class="dashboard-user-day-card" aria-label="Shift basics">
          <p class="dashboard-user-eyebrow">Your shift card</p>
          <dl>
            <div>
              <dt>Team</dt>
              <dd><?php echo aavgo_user_text($todayTeamLabel); ?></dd>
            </div>
            <div>
              <dt>Session</dt>
              <dd><?php echo aavgo_user_text($sessionSummary, 'Offline right now'); ?></dd>
            </div>
            <div>
              <dt>Role</dt>
              <dd><?php echo aavgo_user_text($personalHours['role'] ?? $roleSummary); ?></dd>
            </div>
          </dl>
        </aside>
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

      <section class="dashboard-user-payroll-row reveal reveal-delay-2">
        <article class="dashboard-panel dashboard-user-payroll-panel" id="user-pay-periods">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Pay periods</p>
              <h2>Clean payroll readout</h2>
            </div>
          </div>
          <div class="dashboard-user-payroll-grid">
            <section class="dashboard-user-pay-card">
              <span><?php echo aavgo_user_text($firstHalf['label'] ?? '1-15'); ?></span>
              <strong><?php echo aavgo_user_hours_label($firstHalf['totalHours'] ?? 0); ?>h</strong>
              <p>First payroll half</p>
              <?php echo aavgo_render_hours_day_list(is_array($firstHalf['days'] ?? null) ? $firstHalf['days'] : []); ?>
            </section>
            <section class="dashboard-user-pay-card is-current">
              <span><?php echo aavgo_user_text($secondHalf['label'] ?? '16-end'); ?></span>
              <strong><?php echo aavgo_user_hours_label($secondHalf['totalHours'] ?? 0); ?>h</strong>
              <p>Second payroll half</p>
              <?php echo aavgo_render_hours_day_list(is_array($secondHalf['days'] ?? null) ? $secondHalf['days'] : []); ?>
            </section>
          </div>
        </article>
      </section>

      <section class="dashboard-user-history reveal reveal-delay-2" id="user-history">
        <article class="dashboard-panel dashboard-user-history-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Hour history</p>
              <h2><?php echo aavgo_user_text($currentMonth['label'] ?? 'Current month'); ?></h2>
            </div>
          </div>
          <div class="dashboard-hours-table-wrap dashboard-user-history-table-wrap">
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

        <article class="dashboard-panel dashboard-user-month-card" id="user-month-summary">
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
                    &middot;
                    <?php echo aavgo_user_text($entry['loginTime'] ?? '--:--'); ?>
                    -
                    <?php echo aavgo_user_text($entry['logoutTime'] ?? '--:--'); ?>
                    &middot;
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

  <?php if (!$guestMode): ?>
    <aside class="aavgo-support-widget" data-support-widget aria-label="Aavgo support">
      <button class="aavgo-support-bubble" type="button" data-support-toggle aria-expanded="false">
        <span>Support</span>
        <strong>Ask Dev</strong>
      </button>
      <form class="aavgo-support-panel" data-support-panel hidden>
        <div class="aavgo-support-head">
          <div>
            <p class="dashboard-kicker">Agent support</p>
            <h2>Send a request</h2>
          </div>
          <button type="button" data-support-close aria-label="Close support">Close</button>
        </div>
        <div class="aavgo-support-type-grid" role="radiogroup" aria-label="Request type">
          <label>
            <input type="radio" name="supportType" value="feature" checked>
            <span>Feature request</span>
          </label>
          <label>
            <input type="radio" name="supportType" value="bug">
            <span>Report a bug</span>
          </label>
        </div>
        <label class="aavgo-support-field">
          <span>Title</span>
          <input name="supportTitle" type="text" maxlength="140" placeholder="Short summary">
        </label>
        <label class="aavgo-support-field">
          <span>Details</span>
          <textarea name="supportMessage" rows="5" maxlength="1200" placeholder="What happened, what should change, or what would help?"></textarea>
        </label>
        <p class="aavgo-support-feedback" data-support-feedback>Requests go to the Developer panel only.</p>
        <button class="aavgo-support-submit" type="submit">Send to developers</button>
      </form>
    </aside>
  <?php endif; ?>

  <script>
    window.AAVGO_LIVE_SIGNALS_ENDPOINT = '/api/live-signals/';
    window.AAVGO_SUPPORT_REQUEST_ENDPOINT = '/api/support-request/';
    window.AAVGO_CSRF_TOKEN = <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
<script src="<?= htmlspecialchars(aavgo_asset_url('/script.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
