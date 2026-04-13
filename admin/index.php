<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_access('admin');
$displayName = aavgo_display_name($user);
$safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$roleLabels = aavgo_user_role_labels($user);
$roleSummary = aavgo_user_role_summary($user);
$safeRoleSummary = htmlspecialchars($roleSummary, ENT_QUOTES, 'UTF-8');
$boardPayload = aavgo_build_admin_board_payload($user);
$hoursData = is_array($boardPayload['data'] ?? null) ? $boardPayload['data'] : [];
$summary = is_array($hoursData['summary'] ?? null) ? $hoursData['summary'] : [];
$management = is_array($boardPayload['management'] ?? null) ? $boardPayload['management'] : [];
$isDeveloper = (bool) (($management['actions']['canSyncAllRoles'] ?? false) || ($management['viewer']['isDeveloper'] ?? false));
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

$bootstrapJson = json_encode(
    $boardPayload,
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
  <div class="dashboard-shell dashboard-shell-admin">
    <aside class="dashboard-sidebar dashboard-sidebar-admin reveal reveal-in">
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

      <nav class="dashboard-nav dashboard-nav-vertical" aria-label="Leadership navigation">
        <a class="dashboard-nav-link is-active" href="/admin/">Leadership board</a>
        <a class="dashboard-nav-link" href="#leadership-filters">Filters</a>
        <a class="dashboard-nav-link" href="#leadership-hours">Live hours</a>
        <a class="dashboard-nav-link" href="#leadership-actions">Staff controls</a>
        <a class="dashboard-nav-link" href="#leadership-audit">Audit log</a>
        <a class="dashboard-nav-link" href="/user/">User workspace</a>
        <a class="dashboard-nav-link" href="/auth/logout/">Log out</a>
      </nav>

      <section class="dashboard-side-section">
        <p class="dashboard-kicker">Control mode</p>
        <strong>Leadership actions now stay in one lane: live hours, clean reassignment, and readable audit history.</strong>
        <p>The rail stays anchored, the board stays calm, and the controls stay close to the data they affect.</p>
      </section>
    </aside>

    <main class="dashboard-main dashboard-main-admin">
      <header class="dashboard-header dashboard-header-admin reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb">Dashboard / Leadership / Operations</p>
          <h1 class="dashboard-title dashboard-title-wide">Leadership board for live hours, clean reassignment, and safer control.</h1>
          <p class="dashboard-subtitle">
            The clutter is stripped back. This is now about actual staff, actual hotel/team decisions, and a visible audit trail for every action.
          </p>
        </div>
        <div class="dashboard-toolbar">
          <a class="dashboard-toolbar-link" href="#leadership-actions">Open controls</a>
          <a class="dashboard-toolbar-link" href="/user/">User workspace</a>
          <a class="dashboard-toolbar-link" href="/auth/logout/">Log out</a>
        </div>
      </header>

      <section class="dashboard-stat-grid dashboard-stat-grid-admin reveal reveal-delay-1">
        <article class="dashboard-stat-card">
          <p>Staff tracked</p>
          <strong id="hours-summary-total"><?php echo htmlspecialchars((string) ($summary['totalPeople'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></strong>
          <span>Visible inside the current board view</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Active now</p>
          <strong id="hours-summary-active"><?php echo htmlspecialchars((string) ($summary['activeNow'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></strong>
          <span>Current live sessions in the filtered lane</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Today</p>
          <strong id="hours-summary-today"><?php echo aavgo_admin_hours_label($summary['todayHours'] ?? 0); ?>h</strong>
          <span>Tracked since the current PH day opened</span>
        </article>
        <article class="dashboard-stat-card">
          <p>This week</p>
          <strong id="hours-summary-weekly"><?php echo aavgo_admin_hours_label($summary['weeklyHours'] ?? 0); ?>h</strong>
          <span>Combined tracked hours for the live week</span>
        </article>
        <article class="dashboard-stat-card">
          <p>This month</p>
          <strong id="hours-summary-monthly"><?php echo aavgo_admin_hours_label($summary['monthlyHours'] ?? 0); ?>h</strong>
          <span>Combined tracked hours for the live month</span>
        </article>
      </section>

      <section class="dashboard-filter-shell reveal reveal-delay-1" id="leadership-filters">
        <div class="dashboard-filter-shell-head">
          <div>
            <p class="dashboard-kicker">Filter lane</p>
            <h2>Search people fast, then narrow by role, team, hotel, or live status.</h2>
          </div>
          <div class="dashboard-filter-shell-meta">
            <span class="dashboard-chip dashboard-chip-accent" id="hours-sync-label"><?php echo htmlspecialchars($generatedAtLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="dashboard-chip" id="hours-filter-result-count">0 visible</span>
          </div>
        </div>
        <div class="dashboard-filter-grid">
          <label class="dashboard-filter-field dashboard-filter-field-search">
            <span>Search</span>
            <input id="hours-filter-search" type="search" placeholder="Search name, role, hotel, team, status">
          </label>
          <label class="dashboard-filter-field">
            <span>Role</span>
            <select id="hours-filter-role">
              <option value="">All roles</option>
            </select>
          </label>
          <label class="dashboard-filter-field">
            <span>Team</span>
            <select id="hours-filter-team">
              <option value="">All teams</option>
            </select>
          </label>
          <label class="dashboard-filter-field">
            <span>Hotel</span>
            <select id="hours-filter-hotel">
              <option value="">All hotels</option>
            </select>
          </label>
          <label class="dashboard-filter-field">
            <span>Status</span>
            <select id="hours-filter-status">
              <option value="">All staff</option>
              <option value="active">Active now</option>
              <option value="offline">Offline</option>
            </select>
          </label>
          <button class="dashboard-filter-reset" id="hours-filter-reset" type="button">Reset lane</button>
        </div>
      </section>

      <section class="dashboard-admin-grid reveal reveal-delay-2">
        <article class="dashboard-panel dashboard-panel-board" id="leadership-hours">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Live staff board</p>
              <h2>Every real staff row stays visible, but the controls live off to the right.</h2>
            </div>
            <div class="dashboard-panel-meta">
              <span class="dashboard-chip">Queue <span id="hours-queue-count">0</span></span>
              <span class="dashboard-chip dashboard-chip-accent">Auto-refresh</span>
            </div>
          </div>

          <div id="hours-board-notice">
            <?php if (!($boardPayload['ok'] ?? false)): ?>
              <div class="dashboard-inline-notice">
                <strong>Live hours are not connected yet.</strong>
                <p><?php echo htmlspecialchars((string) ($boardPayload['error'] ?? 'The live hours bridge is not configured yet.'), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
            <?php endif; ?>
          </div>

          <div class="dashboard-hours-table-wrap">
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
                <tr>
                  <td colspan="9">
                    <div class="dashboard-empty-state">
                      <strong>Loading live staff rows.</strong>
                      <p>The board will fill from the latest pushed snapshot.</p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        <aside class="dashboard-stack dashboard-stack-admin" id="leadership-actions">
          <article class="dashboard-panel">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Selected staff</p>
                <h2 id="hours-selected-name">Pick a staff row</h2>
              </div>
              <span class="dashboard-chip" id="hours-selected-route">/user</span>
            </div>

            <div class="dashboard-selected-summary">
              <div class="dashboard-selected-pill" id="hours-selected-role-summary">No staff selected yet</div>
              <div class="dashboard-selected-meta">
                <div>
                  <span>Hotel</span>
                  <strong id="hours-selected-hotel">Unavailable</strong>
                </div>
                <div>
                  <span>Team</span>
                  <strong id="hours-selected-team">Unavailable</strong>
                </div>
                <div>
                  <span>Status</span>
                  <strong id="hours-selected-status">Unavailable</strong>
                </div>
              </div>
            </div>

            <div class="dashboard-period-grid" id="hours-selected-periods">
              <article class="dashboard-period-card">
                <span class="dashboard-chip">1st - 15th</span>
                <strong>0h</strong>
                <p>Pick a staff row to load this payroll cut.</p>
              </article>
              <article class="dashboard-period-card">
                <span class="dashboard-chip">16th - month end</span>
                <strong>0h</strong>
                <p>The second payroll cut appears here.</p>
              </article>
            </div>

            <div class="dashboard-mini-history" id="hours-selected-history">
              <strong>Hour history</strong>
              <p>Once a staff row is selected, recent month totals and current-month activity will appear here.</p>
            </div>

            <div class="dashboard-control-stack">
              <label class="dashboard-control-field">
                <span>Reassign team</span>
                <select id="hours-action-team-select">
                  <option value="">Choose a team</option>
                </select>
              </label>
              <button class="button button-secondary dashboard-inline-button" id="hours-action-team-submit" type="button">Update team</button>

              <label class="dashboard-control-field">
                <span>Reassign hotel</span>
                <select id="hours-action-hotel-select">
                  <option value="">Choose a hotel</option>
                </select>
              </label>
              <button class="button button-secondary dashboard-inline-button" id="hours-action-hotel-submit" type="button">Update hotel</button>

              <button class="button button-primary dashboard-inline-button" id="hours-action-logout-submit" type="button">Force logout staff member</button>
            </div>
            <p class="dashboard-panel-copy" id="hours-action-feedback">Select a staff member to unlock reassignment and logout controls.</p>
          </article>

          <article class="dashboard-panel">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Hotel-wide controls</p>
                <h2>Force logout by hotel</h2>
              </div>
            </div>
            <label class="dashboard-control-field">
              <span>Hotel</span>
              <select id="hours-hotel-force-select">
                <option value="">Choose a hotel</option>
              </select>
            </label>
            <button class="button button-secondary dashboard-inline-button" id="hours-hotel-force-submit" type="button">Force logout hotel</button>
            <p class="dashboard-panel-copy">This closes active sessions for the selected hotel and lets the bot clean up Discord status on the same pass.</p>
          </article>

          <article class="dashboard-panel" id="developer-tool-panel" <?php echo $isDeveloper ? '' : 'hidden'; ?>>
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Developer tools</p>
                <h2>Role-sync and snapshot utilities</h2>
              </div>
            </div>
            <div class="dashboard-control-stack">
              <button class="button button-secondary dashboard-inline-button" id="developer-sync-all" type="button">Resync Discord roles</button>
              <button class="button button-secondary dashboard-inline-button" id="developer-push-snapshot" type="button">Refresh snapshot now</button>
            </div>
            <p class="dashboard-panel-copy">Developer-only controls for deep maintenance. Every action is written into the website audit log and the Discord bot audit trail.</p>
          </article>

          <article class="dashboard-panel" id="leadership-audit">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Audit log</p>
                <h2>Every leadership action leaves a trail.</h2>
              </div>
            </div>
            <div class="dashboard-audit-list" id="hours-audit-log">
              <div class="dashboard-empty-state">
                <strong>Waiting for audit events.</strong>
                <p>Queued and completed actions will appear here.</p>
              </div>
            </div>
          </article>

          <article class="dashboard-panel">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Team totals</p>
                <h2>Current filtered lane totals</h2>
              </div>
            </div>
            <div class="dashboard-mini-grid" id="hours-team-cards">
              <div class="dashboard-mini-card">
                <strong>Waiting for data</strong>
                <p>Filtered team totals will appear here.</p>
              </div>
            </div>
          </article>
        </aside>
      </section>
    </main>
  </div>

  <script id="admin-board-bootstrap" type="application/json"><?php echo $bootstrapJson ?: '{}'; ?></script>
  <script>
    window.AAVGO_ADMIN_HOURS_ENDPOINT = '/api/admin-hours/';
    window.AAVGO_ADMIN_COMMAND_ENDPOINT = '/api/admin-command/';
  </script>
  <script src="/script.js"></script>
</body>
</html>
