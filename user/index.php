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
    content="Private Aavgo staff workspace showing personal tracked hours and current assignment."
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
  <div class="dashboard-shell dashboard-shell-operations dashboard-shell-roomy">
    <aside class="dashboard-sidebar reveal reveal-in">
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

      <nav class="dashboard-nav" aria-label="User navigation">
        <a class="dashboard-nav-link is-active" href="/user/">My hours</a>
        <?php if ($showAdminLink): ?>
          <a class="dashboard-nav-link" href="/admin/">Leadership board</a>
        <?php endif; ?>
        <a class="dashboard-nav-link" href="/">Front door</a>
        <a class="dashboard-nav-link" href="/auth/logout/">Log out</a>
      </nav>
    </aside>

    <main class="dashboard-main">
      <header class="dashboard-header reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb">Workspace / Personal hours</p>
          <h1 class="dashboard-title dashboard-title-wide">Your tracked hours, without the clutter.</h1>
          <p class="dashboard-subtitle">
            This route is now focused on the one thing staff should see first: their own time, their current lane, and a clean way back out.
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

      <section class="dashboard-stat-grid dashboard-stat-grid-balanced reveal reveal-delay-1">
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
          <span>Total hours connected to your account</span>
        </article>
      </section>

      <section class="dashboard-content-grid dashboard-content-grid-bottom reveal reveal-delay-2">
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

        <article class="dashboard-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Quick actions</p>
              <h2>Stay in the right lane</h2>
            </div>
          </div>
          <div class="dashboard-control-list">
            <div class="dashboard-control-item">
              <strong>Front door</strong>
              <span>Return to the private entrance without leaving the secure site surface.</span>
            </div>
            <?php if ($showAdminLink): ?>
              <div class="dashboard-control-item">
                <strong>Leadership board</strong>
                <span>Because your role allows it, you can jump straight into the live management view.</span>
              </div>
            <?php endif; ?>
            <div class="dashboard-control-item">
              <strong>Log out</strong>
              <span>End the session cleanly when you move away from the desk.</span>
            </div>
          </div>
        </article>
      </section>
    </main>
  </div>

  <script src="/script.js"></script>
</body>
</html>
