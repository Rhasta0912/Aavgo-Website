<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_access('admin');
$displayName = aavgo_display_name($user);
$safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$roleLabels = aavgo_user_role_labels($user);
$roleSummary = aavgo_user_role_summary($user);
$safeRoleSummary = htmlspecialchars($roleSummary, ENT_QUOTES, 'UTF-8');
$viewerPerson = aavgo_find_hours_person_for_user($user);
$sidebarRoleCandidates = [];
if (aavgo_user_is_developer($user)) {
    $sidebarRoleCandidates[] = 'Developer';
}
foreach ($roleLabels as $roleLabelCandidate) {
    $roleLabelCandidate = trim((string) $roleLabelCandidate);
    if ($roleLabelCandidate !== '') {
        $sidebarRoleCandidates[] = $roleLabelCandidate;
    }
}
if (is_array($viewerPerson)) {
    $snapshotRoleLabels = is_array($viewerPerson['roleLabels'] ?? null) ? array_values(array_filter(array_map('trim', $viewerPerson['roleLabels']))) : [];
    foreach ($snapshotRoleLabels as $snapshotRoleLabel) {
        $sidebarRoleCandidates[] = $snapshotRoleLabel;
    }

    $snapshotRole = trim((string) ($viewerPerson['role'] ?? ''));
    if ($snapshotRole !== '') {
        $sidebarRoleCandidates[] = $snapshotRole;
    }
}
$sidebarRoleLabel = '';
$fallbackSidebarRoleLabel = '';
foreach ($sidebarRoleCandidates as $candidate) {
    $candidate = trim((string) $candidate);
    if ($candidate === '') {
        continue;
    }

    if (!in_array($candidate, ['Leadership', 'User'], true)) {
        $sidebarRoleLabel = $candidate;
        break;
    }

    if ($fallbackSidebarRoleLabel === '') {
        $fallbackSidebarRoleLabel = $candidate;
    }
}
if ($sidebarRoleLabel === '') {
    $sidebarRoleLabel = $fallbackSidebarRoleLabel;
}
if ($sidebarRoleLabel === '') {
    $sidebarRoleLabel = aavgo_user_access_level($user) === 'admin' ? 'Leadership' : 'User';
}
$safeSidebarRoleLabel = htmlspecialchars($sidebarRoleLabel, ENT_QUOTES, 'UTF-8');
$sidebarRoleKey = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $sidebarRoleLabel));
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
<link rel="stylesheet" href="<?= htmlspecialchars(aavgo_asset_url('/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="workspace-page workspace-dashboard workspace-page-admin">
  <div class="dashboard-shell dashboard-shell-admin">
    <aside class="dashboard-sidebar dashboard-sidebar-admin reveal reveal-in">
      <div class="dashboard-sidebar-glow"></div>
      <div class="dashboard-sidebar-top">
        <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>
      </div>

      <nav class="dashboard-nav dashboard-nav-vertical" aria-label="Leadership navigation">
        <div class="dashboard-sidebar-shortcuts is-open" data-sidebar-leadership-group>
          <button class="dashboard-nav-link dashboard-sidebar-shortcut-toggle is-active" type="button" data-sidebar-leadership-toggle aria-expanded="true" aria-controls="sidebar-leadership-shortcuts">
            <span>Leadership board</span>
          </button>
          <div class="dashboard-sidebar-shortcuts-panel" id="sidebar-leadership-shortcuts" data-sidebar-leadership-panel>
            <button class="dashboard-nav-link dashboard-sidebar-shortcut" type="button" data-hours-view="full-hours">
              <span>Full hours</span>
            </button>
            <button class="dashboard-nav-link dashboard-sidebar-shortcut" type="button" data-hours-view="hotel-lanes">
              <span>Hotel lanes</span>
            </button>
          </div>
        </div>
        <?php if ($isDeveloper): ?>
          <a class="dashboard-nav-link" href="/admin/developer/"><span>Developer panel</span></a>
        <?php endif; ?>
        <a class="dashboard-nav-link" href="/user/"><span>User workspace</span></a>
      </nav>
      <section class="dashboard-sidebar-glance" aria-label="Quick glance">
        <div class="dashboard-sidebar-glance-head">
          <span class="dashboard-sidebar-glance-dot" aria-hidden="true"></span>
          <p class="dashboard-kicker">Quick glance</p>
        </div>
        <strong>Visible: <?php echo htmlspecialchars((string) ($summary['totalPeople'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?> staff</strong>
        <dl class="dashboard-sidebar-glance-grid">
          <div>
            <dt>Role</dt>
            <dd><?php echo $safeSidebarRoleLabel; ?></dd>
          </div>
          <div>
            <dt>View</dt>
            <dd>Leadership board</dd>
          </div>
          <div>
            <dt>Sync</dt>
            <dd><?php echo htmlspecialchars($generatedAtLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
          </div>
        </dl>
      </section>
      <section class="dashboard-sidebar-bottom" aria-label="Profile and session actions">
        <div class="dashboard-sidebar-footer-copy">
          <strong><?php echo $safeDisplayName; ?></strong>
          <p class="dashboard-profile-role" data-role="<?php echo htmlspecialchars($sidebarRoleKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $safeSidebarRoleLabel; ?></p>
        </div>
        <a class="dashboard-nav-link dashboard-sidebar-logout" href="/auth/logout/" aria-label="Log out"></a>
      </section>
    </aside>

    <main class="dashboard-main dashboard-main-admin">
      <header class="dashboard-header dashboard-header-admin reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb">Dashboard / Leadership / Operations</p>
          <h1 class="dashboard-title dashboard-title-wide">Leadership board for live hours, clean reassignment, safer control.</h1>
          <p class="dashboard-subtitle">
            The clutter is stripped back. This is now about actual staff, actual hotel/team decisions, and a visible audit trail for every action.
          </p>
        </div>
        <div class="dashboard-toolbar">
          <button class="dashboard-sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
            <span></span>
            <span></span>
            <span></span>
          </button>
          <div class="dashboard-toolbar-menu" data-toolbar-menu>
            <button class="dashboard-toolbar-link dashboard-toolbar-profile" type="button" data-toolbar-menu-toggle aria-expanded="false" aria-haspopup="true">
              <span class="dashboard-toolbar-avatar"><?php echo htmlspecialchars(strtoupper(substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="dashboard-toolbar-profile-copy">
                <strong><?php echo $safeDisplayName; ?></strong>
                <small><?php echo $safeSidebarRoleLabel; ?></small>
              </span>
            </button>
            <div class="dashboard-toolbar-dropdown" data-toolbar-menu-panel hidden>
              <button class="dashboard-toolbar-dropdown-link" type="button" data-theme-toggle>Toggle theme</button>
              <a class="dashboard-toolbar-dropdown-link" href="#leadership-broadcast">Command center</a>
              <a class="dashboard-toolbar-dropdown-link" href="#leadership-full-hours">Full hours</a>
              <a class="dashboard-toolbar-dropdown-link" href="#leadership-hotels">Hotel lanes</a>
              <?php if ($isDeveloper): ?>
                <a class="dashboard-toolbar-dropdown-link" href="/admin/developer/">Developer panel</a>
              <?php endif; ?>
              <a class="dashboard-toolbar-dropdown-link" href="/user/">User workspace</a>
              <a class="dashboard-toolbar-dropdown-link dashboard-toolbar-dropdown-link-danger" href="/auth/logout/">Log out</a>
            </div>
          </div>
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
            <span class="dashboard-chip dashboard-chip-accent" id="hours-sync-label">🔄 <?php echo htmlspecialchars($generatedAtLabel, ENT_QUOTES, 'UTF-8'); ?></span>
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

      <section class="dashboard-view-switch reveal reveal-delay-1" aria-label="Leadership view switcher">
        <button class="dashboard-view-tab is-active" type="button" data-hours-view="board">🗂️ Leadership board</button>
        <button class="dashboard-view-tab" type="button" data-hours-view="full-hours">📊 Full hours</button>
        <button class="dashboard-view-tab" type="button" data-hours-view="hotel-lanes">🏨 Hotel lanes</button>
      </section>

      <section class="dashboard-view-panel is-active reveal-in" data-hours-view-panel="board">
      <section class="dashboard-admin-grid reveal reveal-in reveal-delay-2">
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
          <article class="dashboard-panel dashboard-panel-command" id="leadership-broadcast">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Command center</p>
                <h2>Broadcast one website-wide leadership alert.</h2>
              </div>
              <span class="dashboard-chip dashboard-chip-accent" id="broadcast-live-status">No live alert</span>
            </div>

            <div class="dashboard-command-surface">
              <label class="dashboard-control-field">
                <span>Announcement</span>
                <textarea
                  id="broadcast-message"
                  class="dashboard-control-textarea"
                  rows="4"
                  maxlength="280"
                  placeholder="Write the announcement that should appear across signed-in Aavgo pages."
                ></textarea>
              </label>

              <div class="dashboard-control-row">
                <label class="dashboard-control-field">
                  <span>Target</span>
                  <select id="broadcast-target-select">
                    <option value="">Website-wide (all staff)</option>
                  </select>
                </label>
                <label class="dashboard-control-field">
                  <span>Tone</span>
                  <select id="broadcast-tone-select">
                    <option value="standard">Standard alert</option>
                    <option value="urgent">Urgent alert</option>
                  </select>
                </label>
                <div class="dashboard-control-stack dashboard-control-stack-tight">
                  <button class="button button-primary dashboard-inline-button" id="broadcast-send" type="button">Send broadcast</button>
                  <button class="button button-secondary dashboard-inline-button" id="broadcast-clear" type="button">Clear broadcast</button>
                </div>
              </div>
            </div>

            <div class="dashboard-broadcast-preview" id="broadcast-preview">
              <strong>No live announcement yet.</strong>
              <p>Once sent, the active alert will show here and across signed-in pages with a website beep.</p>
            </div>
            <p class="dashboard-panel-copy" id="broadcast-feedback">SME, Team Leaders, Operations Managers, and Developers can use this channel for fast website-wide alerts.</p>
          </article>

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
      </section>

      <section class="dashboard-view-panel" data-hours-view-panel="full-hours" id="leadership-full-hours" hidden>
        <article class="dashboard-panel dashboard-panel-full-hours reveal reveal-delay-2">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Full hours</p>
              <h2>Spreadsheet-style month view with bulk actions.</h2>
            </div>
            <div class="dashboard-panel-meta">
              <span class="dashboard-chip dashboard-chip-accent" id="hours-bulk-selected-count">0 selected</span>
              <span class="dashboard-chip">Current month</span>
            </div>
          </div>
          <p class="dashboard-panel-copy">Click any staff row to load the editor. Use the checkboxes to select multiple people before bulk team/hotel moves or force logout.</p>

          <div class="dashboard-bulk-shell">
            <div class="dashboard-bulk-actions">
              <button class="button button-secondary dashboard-inline-button" id="hours-bulk-select-visible" type="button">Select filtered</button>
              <button class="button button-secondary dashboard-inline-button" id="hours-bulk-clear" type="button">Clear selection</button>
              <button class="button button-secondary dashboard-inline-button" id="hours-open-editor" type="button">Open editor</button>
            </div>
            <div class="dashboard-bulk-controls">
              <label class="dashboard-control-field">
                <span>Bulk team</span>
                <select id="hours-bulk-team-select">
                  <option value="">Choose a team</option>
                </select>
              </label>
              <button class="button button-secondary dashboard-inline-button" id="hours-bulk-team-submit" type="button">Update selected teams</button>

              <label class="dashboard-control-field">
                <span>Bulk hotel</span>
                <select id="hours-bulk-hotel-select">
                  <option value="">Choose a hotel</option>
                </select>
              </label>
              <button class="button button-secondary dashboard-inline-button" id="hours-bulk-hotel-submit" type="button">Update selected hotels</button>
              <button class="button button-primary dashboard-inline-button" id="hours-bulk-logout-submit" type="button">Force logout selected</button>
            </div>
            <p class="dashboard-panel-copy" id="hours-bulk-feedback">Select one or more staff rows, then apply team, hotel, or logout actions in one pass. Double-click a day cell to edit that date directly.</p>
          </div>

          <div class="dashboard-hours-editor-inline" id="hours-editor-summary-card">
            <div>
              <p class="dashboard-kicker">Manual edit lane</p>
              <h3 id="hours-editor-summary-selected">Pick a staff row to edit hours.</h3>
            </div>
            <div class="dashboard-hours-editor-inline-actions">
              <span class="dashboard-chip">Double-click any day cell</span>
              <button class="button button-secondary dashboard-inline-button" id="hours-open-editor-secondary" type="button">Open hours editor</button>
            </div>
            <p class="dashboard-panel-copy" id="hours-editor-feedback">Select a staff row in the full hours table, then add or remove hours with a clear reason.</p>
          </div>

          <div class="dashboard-hours-sheet-wrap">
            <div class="dashboard-hours-sheet-split">
              <div class="dashboard-hours-sheet-pane dashboard-hours-sheet-pane-left">
                <table class="dashboard-hours-sheet" id="hours-full-board-left">
                  <colgroup id="hours-full-board-left-cols"></colgroup>
                  <thead id="hours-full-board-left-head">
                    <tr>
                      <th>Select</th>
                      <th>Staff</th>
                      <th>Role</th>
                      <th>Team</th>
                      <th>Hotel</th>
                    </tr>
                  </thead>
                  <tbody id="hours-full-board-left-rows">
                    <tr>
                      <td colspan="5">
                        <div class="dashboard-empty-state">
                          <strong>Loading the full hours sheet.</strong>
                          <p>The complete month lane will appear here as soon as the first snapshot is applied.</p>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="dashboard-hours-sheet-pane dashboard-hours-sheet-pane-right">
                <table class="dashboard-hours-sheet" id="hours-full-board-right">
                  <colgroup id="hours-full-board-right-cols"></colgroup>
                  <thead id="hours-full-board-right-head">
                    <tr>
                      <th>1st - 15th</th>
                      <th>16th - end</th>
                      <th>Month</th>
                      <th>All time</th>
                    </tr>
                  </thead>
                  <tbody id="hours-full-board-right-rows">
                    <tr>
                      <td colspan="4">
                        <div class="dashboard-empty-state">
                          <strong>Loading the full hours sheet.</strong>
                          <p>The complete month lane will appear here as soon as the first snapshot is applied.</p>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </article>

      </section>

      <section class="dashboard-view-panel" data-hours-view-panel="hotel-lanes" id="leadership-hotels" hidden>
        <article class="dashboard-panel reveal reveal-in reveal-delay-2">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Hotel command lanes</p>
              <h2>Live hotel rows with quick visibility.</h2>
            </div>
            <span class="dashboard-chip dashboard-chip-accent">All hotels</span>
          </div>
          <p class="dashboard-panel-copy">Hotels are grouped by team so every property stays visible, assigned agents are easy to scan, and logout actions stay readable without crowding the main leadership board. Drag any staff chip onto another hotel card to reassign it instantly.</p>
          <div class="dashboard-hotel-lanes" id="hours-hotel-lanes">
            <div class="dashboard-empty-state">
              <strong>Waiting for hotel lane data.</strong>
              <p>Once the board snapshot is ready, hotel command cards will appear here.</p>
            </div>
          </div>
        </article>
      </section>
    </main>
  </div>

  <div class="dashboard-modal" id="hours-editor-modal" hidden>
    <div class="dashboard-modal-backdrop" data-hours-modal-close></div>
    <div class="dashboard-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="hours-editor-modal-title">
      <div class="dashboard-panel-heading">
        <div>
          <p class="dashboard-kicker">Manual edit lane</p>
          <h2 id="hours-editor-modal-title">Edit a specific day clearly.</h2>
        </div>
        <button class="dashboard-modal-close" type="button" data-hours-modal-close aria-label="Close hours editor">Close</button>
      </div>

      <div class="dashboard-hours-modal-grid">
        <section class="dashboard-hours-modal-card">
          <p class="dashboard-kicker">Selected staff</p>
          <strong id="hours-editor-selected">Pick a staff row to edit hours.</strong>

          <div class="dashboard-control-grid dashboard-control-grid-double">
            <label class="dashboard-control-field">
              <span>Date</span>
              <div class="dashboard-deadline-picker" data-aavgo-date-picker data-value-prefix="Date" data-empty-label="Choose a date">
                <input id="hours-editor-date" type="date" readonly class="dashboard-deadline-native" tabindex="-1" aria-hidden="true">
                <button type="button" class="dashboard-deadline-trigger" id="hours-editor-date-trigger" data-date-trigger aria-haspopup="dialog" aria-expanded="false">
                  Choose a date
                </button>
                <div class="dashboard-deadline-popover" id="hours-editor-date-popover" data-date-popover hidden>
                  <div class="dashboard-deadline-header">
                    <button type="button" class="dashboard-deadline-nav" data-date-prev aria-label="Previous month">‹</button>
                    <strong id="hours-editor-date-month">Month</strong>
                    <button type="button" class="dashboard-deadline-nav" data-date-next aria-label="Next month">›</button>
                  </div>
                  <div class="dashboard-deadline-weekdays" aria-hidden="true">
                    <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                  </div>
                  <div class="dashboard-deadline-grid" id="hours-editor-date-grid"></div>
                  <div class="dashboard-deadline-footer">
                    <button type="button" class="dashboard-deadline-chip" data-date-today>Today</button>
                    <button type="button" class="dashboard-deadline-chip" data-date-nextweek>Next week</button>
                    <button type="button" class="dashboard-deadline-chip" data-date-clear>Clear</button>
                    <button type="button" class="dashboard-deadline-chip" data-date-close>Hide calendar</button>
                  </div>
                </div>
              </div>
            </label>
            <label class="dashboard-control-field">
              <span>Mode</span>
              <select id="hours-editor-mode">
                <option value="shift">Live shift</option>
                <option value="training">Training</option>
              </select>
            </label>
            <label class="dashboard-control-field">
              <span>Login</span>
              <input id="hours-editor-login" type="time">
            </label>
            <label class="dashboard-control-field">
              <span>Logout</span>
              <input id="hours-editor-logout" type="time">
            </label>
            <label class="dashboard-control-field dashboard-control-field-wide">
              <span>Hotel</span>
              <select id="hours-editor-hotel">
                <option value="">Use linked hotel</option>
              </select>
            </label>
            <label class="dashboard-control-field dashboard-control-field-wide">
              <span>Reason</span>
              <input id="hours-editor-reason" type="text" maxlength="140" placeholder="Why are you editing this shift?">
            </label>
          </div>

          <div class="dashboard-control-row">
            <button class="button button-primary dashboard-inline-button" id="hours-editor-add-submit" type="button">Save manual shift</button>
          </div>
        </section>

        <section class="dashboard-hours-modal-card">
          <p class="dashboard-kicker">Remove hours</p>
          <div class="dashboard-control-grid dashboard-control-grid-double">
            <label class="dashboard-control-field">
              <span>Date</span>
              <div class="dashboard-deadline-picker" data-aavgo-date-picker data-value-prefix="Date" data-empty-label="Choose a date">
                <input id="hours-remove-date" type="date" readonly class="dashboard-deadline-native" tabindex="-1" aria-hidden="true">
                <button type="button" class="dashboard-deadline-trigger" id="hours-remove-date-trigger" data-date-trigger aria-haspopup="dialog" aria-expanded="false">
                  Choose a date
                </button>
                <div class="dashboard-deadline-popover" id="hours-remove-date-popover" data-date-popover hidden>
                  <div class="dashboard-deadline-header">
                    <button type="button" class="dashboard-deadline-nav" data-date-prev aria-label="Previous month">‹</button>
                    <strong id="hours-remove-date-month">Month</strong>
                    <button type="button" class="dashboard-deadline-nav" data-date-next aria-label="Next month">›</button>
                  </div>
                  <div class="dashboard-deadline-weekdays" aria-hidden="true">
                    <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                  </div>
                  <div class="dashboard-deadline-grid" id="hours-remove-date-grid"></div>
                  <div class="dashboard-deadline-footer">
                    <button type="button" class="dashboard-deadline-chip" data-date-today>Today</button>
                    <button type="button" class="dashboard-deadline-chip" data-date-nextweek>Next week</button>
                    <button type="button" class="dashboard-deadline-chip" data-date-clear>Clear</button>
                    <button type="button" class="dashboard-deadline-chip" data-date-close>Hide calendar</button>
                  </div>
                </div>
              </div>
            </label>
            <label class="dashboard-control-field">
              <span>Mode</span>
              <select id="hours-remove-mode">
                <option value="shift">Live shift</option>
                <option value="training">Training</option>
              </select>
            </label>
            <label class="dashboard-control-field">
              <span>Hours</span>
              <input id="hours-remove-hours" type="number" min="0.1" step="0.1" placeholder="0.0">
            </label>
            <label class="dashboard-control-field dashboard-control-field-wide">
              <span>Reason</span>
              <input id="hours-remove-reason" type="text" maxlength="140" placeholder="Why are you removing these hours?">
            </label>
          </div>
          <div class="dashboard-control-row">
            <button class="button button-secondary dashboard-inline-button" id="hours-remove-submit" type="button">Remove hours</button>
          </div>
        </section>
      </div>

      <section class="dashboard-hours-modal-card">
        <p class="dashboard-kicker">Recent manual changes</p>
        <div class="dashboard-adjustment-log" id="hours-adjustment-log">
          <div class="dashboard-empty-state">
            <strong>Waiting for a staff selection.</strong>
            <p>Recent manual hour adjustments for the selected staff member will show here.</p>
          </div>
        </div>
      </section>
    </div>
  </div>

  <script id="admin-board-bootstrap" type="application/json"><?php echo $bootstrapJson ?: '{}'; ?></script>
  <script>
    window.AAVGO_ADMIN_HOURS_ENDPOINT = '/api/admin-hours/';
    window.AAVGO_ADMIN_COMMAND_ENDPOINT = '/api/admin-command/';
    window.AAVGO_LIVE_SIGNALS_ENDPOINT = '/api/live-signals/';
  </script>
<script src="<?= htmlspecialchars(aavgo_asset_url('/script.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>

