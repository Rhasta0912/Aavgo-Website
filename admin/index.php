<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_access('admin');
$displayName = aavgo_display_name($user);
$safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$roleLabels = aavgo_user_role_labels($user);
$roleSummary = aavgo_user_role_summary($user);
$safeRoleSummary = htmlspecialchars($roleSummary, ENT_QUOTES, 'UTF-8');
$hoursPayload = aavgo_fetch_hours_bridge_payload();
$hoursSyncUrl = rtrim(aavgo_get_config_string('base_url'), '/') . '/api/admin-hours-sync/';
$safeHoursSyncUrl = htmlspecialchars($hoursSyncUrl, ENT_QUOTES, 'UTF-8');
$safeSnapshotPath = htmlspecialchars(aavgo_get_hours_snapshot_path(), ENT_QUOTES, 'UTF-8');
$safeExternalConfigPath = htmlspecialchars(AAVGO_EXTERNAL_CONFIG, ENT_QUOTES, 'UTF-8');
$hoursData = is_array($hoursPayload['data'] ?? null) ? $hoursPayload['data'] : null;
$summary = is_array($hoursData['summary'] ?? null) ? $hoursData['summary'] : [];
$teams = is_array($hoursData['teams'] ?? null) ? $hoursData['teams'] : [];
$people = is_array($hoursData['people'] ?? null) ? $hoursData['people'] : [];
$generatedAt = trim((string) ($hoursData['generatedAt'] ?? ''));
$generatedAtLabel = 'Waiting for live sync';
if ($generatedAt !== '') {
    $timestamp = strtotime($generatedAt);
    if ($timestamp !== false) {
        $generatedAtLabel = gmdate('M j, Y g:i A', $timestamp) . ' UTC';
    }
}

function aavgo_admin_hours_label(mixed $value): string
{
    $number = (float) $value;
    if (!is_finite($number)) {
        $number = 0.0;
    }

    $formatted = number_format($number, 1);
    return preg_replace('/\.0$/', '', $formatted) ?: '0';
}

function aavgo_admin_cell(mixed $value): string
{
    return htmlspecialchars(aavgo_admin_hours_label($value), ENT_QUOTES, 'UTF-8') . 'h';
}

function aavgo_admin_text(mixed $value, string $fallback = 'Unavailable'): string
{
    $text = trim((string) $value);
    return htmlspecialchars($text !== '' ? $text : $fallback, ENT_QUOTES, 'UTF-8');
}

function aavgo_admin_unique_values(array $people, string $key): array
{
    $values = [];
    foreach ($people as $person) {
        if (!is_array($person)) {
            continue;
        }

        $value = trim((string) ($person[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        $values[$value] = $value;
    }

    $items = array_values($values);
    natcasesort($items);
    return array_values($items);
}

$roleOptions = aavgo_admin_unique_values($people, 'role');
$teamOptions = aavgo_admin_unique_values($people, 'team');
$hotelOptions = aavgo_admin_unique_values($people, 'linkedHotel');

$bootstrapJson = json_encode(
    $hoursPayload,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aavgo | Leadership Suite</title>
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta
    name="description"
    content="Private Aavgo leadership suite for Team Leaders, Operations Managers, Developers, and approved leadership roles."
  >
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;700;800&family=Instrument+Serif:ital@0;1&display=swap"
    rel="stylesheet"
  >
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="workspace-page workspace-dashboard workspace-page-admin">
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

      <nav class="dashboard-nav" aria-label="Leadership navigation">
        <a class="dashboard-nav-link is-active" href="/admin/">Hours board</a>
        <a class="dashboard-nav-link" href="#hours-filters">Filters</a>
        <a class="dashboard-nav-link" href="#live-board">Live staff hours</a>
        <a class="dashboard-nav-link" href="#team-board">Team totals</a>
        <a class="dashboard-nav-link" href="/user/">User workspace</a>
        <a class="dashboard-nav-link" href="/">Front door</a>
        <a class="dashboard-nav-link" href="/auth/logout/">Log out</a>
      </nav>
    </aside>

    <main class="dashboard-main">
      <header class="dashboard-header reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb">Dashboard / Leadership / Hours</p>
          <h1 class="dashboard-title dashboard-title-wide">Live hours with cleaner control.</h1>
          <p class="dashboard-subtitle">
            The leadership board is now focused on real people, real hours, and the quickest path to finding the right lane.
          </p>
        </div>
        <div class="dashboard-toolbar">
          <a class="dashboard-toolbar-link" href="#hours-filters">Filter board</a>
          <a class="dashboard-toolbar-link" href="/user/">User workspace</a>
          <a class="dashboard-toolbar-link" href="/auth/logout/">Log out</a>
        </div>
      </header>

      <section class="dashboard-stat-grid dashboard-stat-grid-balanced reveal reveal-delay-1">
        <article class="dashboard-stat-card">
          <p>People tracked</p>
          <strong id="hours-summary-total"><?php echo aavgo_admin_text($summary['totalPeople'] ?? '0'); ?></strong>
          <span>Visible in the current board view</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Active right now</p>
          <strong id="hours-summary-active"><?php echo aavgo_admin_text($summary['activeNow'] ?? '0'); ?></strong>
          <span>Sessions currently open across the filtered lane</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Today total</p>
          <strong id="hours-summary-today"><?php echo aavgo_admin_hours_label($summary['todayHours'] ?? 0); ?>h</strong>
          <span>Combined tracked hours since the current PH day opened</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Weekly total</p>
          <strong id="hours-summary-weekly"><?php echo aavgo_admin_hours_label($summary['weeklyHours'] ?? 0); ?>h</strong>
          <span>Combined tracked hours for the current PH week</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Monthly total</p>
          <strong id="hours-summary-monthly"><?php echo aavgo_admin_hours_label($summary['monthlyHours'] ?? 0); ?>h</strong>
          <span>Combined tracked hours for the current PH month</span>
        </article>
      </section>

      <section class="dashboard-filter-bar reveal reveal-delay-1" id="hours-filters">
        <label class="dashboard-filter-control dashboard-filter-search">
          <span>Search</span>
          <input id="hours-filter-search" type="search" placeholder="Search staff name">
        </label>
        <label class="dashboard-filter-control">
          <span>Role</span>
          <select id="hours-filter-role">
            <option value="">All roles</option>
            <?php foreach ($roleOptions as $roleOption): ?>
              <option value="<?php echo htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dashboard-filter-control">
          <span>Team</span>
          <select id="hours-filter-team">
            <option value="">All teams</option>
            <?php foreach ($teamOptions as $teamOption): ?>
              <option value="<?php echo htmlspecialchars($teamOption, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($teamOption, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dashboard-filter-control">
          <span>Hotel</span>
          <select id="hours-filter-hotel">
            <option value="">All hotels</option>
            <?php foreach ($hotelOptions as $hotelOption): ?>
              <option value="<?php echo htmlspecialchars($hotelOption, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($hotelOption, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dashboard-filter-control">
          <span>Status</span>
          <select id="hours-filter-status">
            <option value="">All staff</option>
            <option value="active">Active now</option>
            <option value="offline">Offline</option>
          </select>
        </label>
        <button class="dashboard-filter-reset" id="hours-filter-reset" type="button">Clear filters</button>
      </section>

      <section class="dashboard-content-grid dashboard-content-grid-ops reveal reveal-delay-2">
        <article class="dashboard-panel dashboard-panel-board" id="live-board">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Live hours board</p>
              <h2>Actual staff and actual hour totals</h2>
            </div>
            <div class="dashboard-panel-meta">
              <span class="dashboard-chip dashboard-chip-accent">Auto-refresh</span>
              <span class="dashboard-chip" id="hours-sync-label"><?php echo htmlspecialchars($generatedAtLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>

          <div id="hours-board-notice">
            <?php if (!($hoursPayload['ok'] ?? false)): ?>
              <div class="dashboard-inline-notice">
                <strong>Live hours are not connected yet.</strong>
                <p><?php echo aavgo_admin_text($hoursPayload['error'] ?? 'The admin hours bridge is not configured yet.'); ?></p>
                <div class="dashboard-setup-list">
                  <div class="dashboard-setup-item">
                    <strong>1. Bot host</strong>
                    <p>Set <code>AAVGO_WEBSITE_API_TOKEN</code> and <code>AAVGO_WEBSITE_SYNC_URL=<?php echo $safeHoursSyncUrl; ?></code> on the bot host, then restart the bot.</p>
                  </div>
                  <div class="dashboard-setup-item">
                    <strong>2. Website config</strong>
                    <p>Open <code><?php echo $safeExternalConfigPath; ?></code> and keep the same shared value in <code>website_api_token</code>. The website will accept secure bot uploads with that token.</p>
                  </div>
                  <div class="dashboard-setup-item">
                    <strong>3. Local snapshot</strong>
                    <p>The pushed snapshot is stored on the website at <code><?php echo $safeSnapshotPath; ?></code>. Once the bot posts the first sync, this board will read from that local file automatically.</p>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="dashboard-hours-table-wrap" id="hours-board-table">
            <table class="dashboard-hours-table">
              <thead>
                <tr>
                  <th>Staff</th>
                  <th>Role</th>
                  <th>Team</th>
                  <th>Hotel</th>
                  <th>Active now</th>
                  <th>Today</th>
                  <th>Week</th>
                  <th>Month</th>
                  <th>All time</th>
                </tr>
              </thead>
              <tbody id="hours-board-rows">
                <?php if ($people === []): ?>
                  <tr>
                    <td colspan="9">
                      <div class="dashboard-empty-state">
                        <strong>No live hour rows yet.</strong>
                        <p>Once the hours bridge responds, every actual staff row will appear here.</p>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($people as $person): ?>
                    <?php
                    $activeNow = !empty($person['activeNow']);
                    $activeSession = is_array($person['activeSession'] ?? null) ? $person['activeSession'] : null;
                    $activeLabel = $activeNow && $activeSession !== null
                        ? (($activeSession['kind'] ?? 'Live Shift') . ' - ' . aavgo_admin_hours_label($activeSession['elapsedHours'] ?? 0) . 'h')
                        : 'Offline';
                    ?>
                    <tr class="<?php echo $activeNow ? 'is-live' : ''; ?>">
                      <td>
                        <div class="dashboard-staff-cell">
                          <strong><?php echo aavgo_admin_text($person['displayName'] ?? 'Unknown'); ?></strong>
                          <span><?php echo aavgo_admin_text($person['route'] ?? '/user'); ?></span>
                        </div>
                      </td>
                      <td><?php echo aavgo_admin_text($person['role'] ?? 'Agent'); ?></td>
                      <td><?php echo aavgo_admin_text($person['team'] ?? 'Unassigned'); ?></td>
                      <td><?php echo aavgo_admin_text($person['linkedHotel'] ?? 'Unassigned'); ?></td>
                      <td>
                        <span class="dashboard-status-pill <?php echo $activeNow ? 'is-live' : 'is-idle'; ?>">
                          <?php echo aavgo_admin_text($activeLabel, 'Offline'); ?>
                        </span>
                      </td>
                      <td><?php echo aavgo_admin_cell($person['todayHours'] ?? 0); ?></td>
                      <td><?php echo aavgo_admin_cell($person['weeklyHours'] ?? 0); ?></td>
                      <td><?php echo aavgo_admin_cell($person['monthlyHours'] ?? 0); ?></td>
                      <td><?php echo aavgo_admin_cell($person['allHours'] ?? 0); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>

        <aside class="dashboard-stack" id="team-board">
          <article class="dashboard-panel">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Team totals</p>
                <h2>Organized by lane</h2>
              </div>
            </div>
            <div class="dashboard-mini-grid" id="hours-team-cards">
              <?php if ($teams === []): ?>
                <div class="dashboard-mini-card">
                  <strong>Waiting for data</strong>
                  <p>The team breakdown appears here as soon as the bridge returns live data.</p>
                </div>
              <?php else: ?>
                <?php foreach ($teams as $team): ?>
                  <div class="dashboard-mini-card">
                    <strong><?php echo aavgo_admin_text($team['name'] ?? 'Unassigned'); ?></strong>
                    <p><?php echo aavgo_admin_text($team['people'] ?? '0'); ?> people - <?php echo aavgo_admin_text($team['activeNow'] ?? '0'); ?> active</p>
                    <span>Today: <?php echo aavgo_admin_cell($team['todayHours'] ?? 0); ?></span>
                    <span>Week: <?php echo aavgo_admin_cell($team['weeklyHours'] ?? 0); ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </article>
        </aside>
      </section>
    </main>
  </div>

  <script id="admin-hours-bootstrap" type="application/json"><?php echo $bootstrapJson ?: '{}'; ?></script>
  <script>
    window.AAVGO_ADMIN_HOURS_ENDPOINT = '/api/admin-hours/';
  </script>
  <script src="/script.js"></script>
</body>
</html>
