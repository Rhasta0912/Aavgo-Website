<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_access('user');
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
$firstHalf = is_array($payPeriods['firstHalf'] ?? null) ? $payPeriods['firstHalf'] : ['label' => '1-15', 'totalHours' => 0, 'days' => []];
$secondHalf = is_array($payPeriods['secondHalf'] ?? null) ? $payPeriods['secondHalf'] : ['label' => '16-end', 'totalHours' => 0, 'days' => []];
$currentMonth = is_array($personalHours['currentMonth'] ?? null) ? $personalHours['currentMonth'] : ['label' => 'Current month', 'days' => []];
$recentMonths = is_array($personalHours['recentMonths'] ?? null) ? $personalHours['recentMonths'] : [];

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

$sessionSummary = 'Offline right now';
if (is_array($personalHours['activeSession'] ?? null)) {
    $sessionSummary = sprintf(
        '%s - %sh',
        trim((string) ($personalHours['activeSession']['kind'] ?? 'Live Shift')),
        aavgo_user_hours_label($personalHours['activeSession']['elapsedHours'] ?? 0)
    );
}
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
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="workspace-page workspace-dashboard workspace-page-user">
  <div class="dashboard-shell dashboard-shell-user">
    <aside class="dashboard-sidebar dashboard-sidebar-user reveal reveal-in">
      <div class="dashboard-sidebar-glow"></div>
      <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>

      <section class="dashboard-profile-card dashboard-profile-card-plain">
        <div class="dashboard-profile-copy">
          <strong><?php echo $safeDisplayName; ?></strong>
          <p><?php echo $safeRoleSummary; ?></p>
        </div>
      </section>

      <div class="dashboard-sidebar-meta">
        <?php foreach ($roleLabels as $roleLabel): ?>
          <span class="dashboard-chip <?php echo $roleLabel === 'Developer' ? 'dashboard-chip-accent' : ''; ?>">
            <?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?>
          </span>
        <?php endforeach; ?>
      </div>

      <nav class="dashboard-nav dashboard-nav-vertical" aria-label="User navigation">
        <a class="dashboard-nav-link is-active" href="/user/">My hours</a>
        <a class="dashboard-nav-link" href="#user-pay-periods">Pay periods</a>
        <a class="dashboard-nav-link" href="#user-history">Hour history</a>
        <?php if ($showAdminLink): ?>
          <a class="dashboard-nav-link" href="/admin/">Leadership board</a>
        <?php endif; ?>
        <a class="dashboard-nav-link" href="/">Front door</a>
        <a class="dashboard-nav-link" href="/auth/logout/">Log out</a>
      </nav>

      <section class="dashboard-side-section">
        <p class="dashboard-kicker">Staff mode</p>
        <strong>Your own hours come first, with the pay-period view ready for payroll checks.</strong>
        <p>Leadership tools stay out of the way unless your role opens that lane too.</p>
      </section>
    </aside>

    <main class="dashboard-main dashboard-main-user">
      <header class="dashboard-header dashboard-header-admin reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb">Workspace / Personal hours</p>
          <h1 class="dashboard-title dashboard-title-wide">Your hours, your current lane, and the two payroll cuts that matter.</h1>
          <p class="dashboard-subtitle">
            The user workspace is intentionally quiet now: just the hours you need, the pay periods you care about, and a clean log out path.
          </p>
        </div>
        <div class="dashboard-toolbar">
          <?php if ($showAdminLink): ?>
            <a class="dashboard-toolbar-link" href="/admin/">Leadership board</a>
          <?php endif; ?>
          <a class="dashboard-toolbar-link" href="/auth/logout/">Log out</a>
        </div>
      </header>

      <?php if (!$hoursConnected): ?>
        <section class="dashboard-inline-notice reveal reveal-delay-1">
          <strong>Your personal hours are still syncing.</strong>
          <p><?php echo aavgo_user_text($hoursPayload['error'] ?? 'The website has not received your personal hours yet.'); ?></p>
        </section>
      <?php endif; ?>

      <section class="dashboard-stat-grid dashboard-stat-grid-admin reveal reveal-delay-1">
        <article class="dashboard-stat-card">
          <p>Today</p>
          <strong><?php echo aavgo_user_hours_label($personalHours['todayHours'] ?? 0); ?>h</strong>
          <span>Tracked in the current PH day</span>
        </article>
        <article class="dashboard-stat-card">
          <p>This week</p>
          <strong><?php echo aavgo_user_hours_label($personalHours['weeklyHours'] ?? 0); ?>h</strong>
          <span>Tracked in the current PH week</span>
        </article>
        <article class="dashboard-stat-card">
          <p>This month</p>
          <strong><?php echo aavgo_user_hours_label($personalHours['monthlyHours'] ?? 0); ?>h</strong>
          <span>Tracked in the current PH month</span>
        </article>
        <article class="dashboard-stat-card">
          <p>All time</p>
          <strong><?php echo aavgo_user_hours_label($personalHours['allHours'] ?? 0); ?>h</strong>
          <span>Total hours attached to your account</span>
        </article>
      </section>

      <section class="dashboard-user-grid reveal reveal-delay-2">
        <article class="dashboard-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Current lane</p>
              <h2><?php echo aavgo_user_text($personalHours['linkedHotel'] ?? 'Hotel not assigned yet'); ?></h2>
            </div>
            <span class="dashboard-chip dashboard-chip-accent"><?php echo aavgo_user_text($personalHours['team'] ?? 'Team pending'); ?></span>
          </div>
          <div class="dashboard-control-list">
            <div class="dashboard-control-item">
              <strong>Role</strong>
              <span><?php echo aavgo_user_text($personalHours['role'] ?? $roleSummary); ?></span>
            </div>
            <div class="dashboard-control-item">
              <strong>Status</strong>
              <span><?php echo aavgo_user_text($personalHours['agentStatus'] ?? 'Standby'); ?></span>
            </div>
            <div class="dashboard-control-item">
              <strong>Session</strong>
              <span><?php echo aavgo_user_text($sessionSummary, 'Offline right now'); ?></span>
            </div>
          </div>
        </article>

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

        <article class="dashboard-panel">
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
        </article>
      </section>
    </main>
  </div>

  <script>
    window.AAVGO_LIVE_SIGNALS_ENDPOINT = '/api/live-signals/';
  </script>
  <script src="/script.js"></script>
</body>
</html>
