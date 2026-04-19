<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$user = aavgo_require_access('admin');
if (!aavgo_user_is_developer($user)) {
    aavgo_redirect('/admin/');
}

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
    $sidebarRoleLabel = aavgo_user_is_developer($user) ? 'Developer' : 'Leadership';
}
$safeSidebarRoleLabel = htmlspecialchars($sidebarRoleLabel, ENT_QUOTES, 'UTF-8');
$sidebarRoleKey = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $sidebarRoleLabel));
$developerBoardStore = aavgo_read_developer_board();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aavgo | Developer Panel</title>
  <meta name="robots" content="noindex,nofollow,noarchive">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;700;800&family=Instrument+Serif:ital@0;1&display=swap"
    rel="stylesheet"
  >
<link rel="stylesheet" href="<?= htmlspecialchars(aavgo_asset_url('/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="workspace-page workspace-dashboard workspace-page-admin workspace-page-developer">
  <div class="dashboard-shell dashboard-shell-admin">
    <aside class="dashboard-sidebar dashboard-sidebar-admin reveal reveal-in">
      <div class="dashboard-sidebar-glow"></div>
      <div class="dashboard-sidebar-top">
        <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>
      </div>

      <nav class="dashboard-nav dashboard-nav-vertical" aria-label="Developer navigation">
        <a class="dashboard-nav-link" href="/admin/"><span class="dashboard-nav-emoji" aria-hidden="true">🏛️</span><span>Leadership board</span></a>
        <a class="dashboard-nav-link is-active" href="/admin/developer/"><span class="dashboard-nav-emoji" aria-hidden="true">🧭</span><span>Developer panel</span></a>
        <a class="dashboard-nav-link" href="/user/"><span class="dashboard-nav-emoji" aria-hidden="true">👤</span><span>User workspace</span></a>
      </nav>

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
          <p class="dashboard-breadcrumb">Dashboard / Developer / Panel</p>
          <h1 class="dashboard-title dashboard-title-wide">Roadmap board.</h1>
          <p class="dashboard-subtitle">A shared Trello-style board for tasks, fixes, and platform work.</p>
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
              <a class="dashboard-toolbar-dropdown-link" href="/admin/">Leadership board</a>
              <a class="dashboard-toolbar-dropdown-link" href="/user/">User workspace</a>
              <a class="dashboard-toolbar-dropdown-link dashboard-toolbar-dropdown-link-danger" href="/auth/logout/">Log out</a>
            </div>
          </div>
        </div>
      </header>

      <section class="dashboard-admin-grid dashboard-admin-grid-developer reveal reveal-delay-2">
        <section class="dashboard-developer-workspace">
          <div class="dashboard-panel-heading dashboard-developer-workspace-head">
            <div>
              <p class="dashboard-kicker">Developer board</p>
              <h2>To Do, Doing, Done.</h2>
            </div>
            <div class="dashboard-panel-actions">
              <button class="button button-secondary dashboard-developer-add-launcher" type="button" data-developer-task-open onclick="window.__aavgoOpenDeveloperTaskModal && window.__aavgoOpenDeveloperTaskModal('To Do')">+ New item</button>
            </div>
          </div>

          <section class="dashboard-view-switch dashboard-view-switch-developer" aria-label="Developer view switcher">
            <button class="dashboard-view-tab is-active" type="button" data-developer-view="board">Roadmap board</button>
            <button class="dashboard-view-tab" type="button" data-developer-view="archive">Archive</button>
            <button class="dashboard-view-tab" type="button" data-developer-view="audit">Audit</button>
          </section>

          <div class="dashboard-view-panel is-active" data-developer-view-panel="board">
            <div class="dashboard-developer-task-list" id="developer-task-list">
            <div class="dashboard-developer-board">
              <section class="dashboard-developer-lane" data-developer-lane="To Do" data-developer-lane-dropzone="To Do">
                <header class="dashboard-developer-lane-head">
                  <div>
                    <p class="dashboard-kicker">List</p>
                    <h3>To Do</h3>
                    <p class="dashboard-developer-lane-copy">Ready to start.</p>
                  </div>
                  <span class="dashboard-chip">0</span>
                </header>
                <div class="dashboard-developer-task-group-list">
                  <div class="dashboard-developer-lane-empty">
                    <strong>No cards yet.</strong>
                    <p>Start this list with a card for To Do.</p>
                  </div>
                </div>
                <button type="button" class="dashboard-developer-lane-add" data-developer-task-create-status="To Do" onclick="window.__aavgoOpenDeveloperTaskModal && window.__aavgoOpenDeveloperTaskModal('To Do')" aria-label="Add card to To Do">+ Add a card</button>
              </section>
              <section class="dashboard-developer-lane" data-developer-lane="Doing" data-developer-lane-dropzone="Doing">
                <header class="dashboard-developer-lane-head">
                  <div>
                    <p class="dashboard-kicker">List</p>
                    <h3>Doing</h3>
                    <p class="dashboard-developer-lane-copy">Work in motion.</p>
                  </div>
                  <span class="dashboard-chip">0</span>
                </header>
                <div class="dashboard-developer-task-group-list">
                  <div class="dashboard-developer-lane-empty">
                    <strong>No cards yet.</strong>
                    <p>Start this list with a card for Doing.</p>
                  </div>
                </div>
                <button type="button" class="dashboard-developer-lane-add" data-developer-task-create-status="Doing" onclick="window.__aavgoOpenDeveloperTaskModal && window.__aavgoOpenDeveloperTaskModal('Doing')" aria-label="Add card to Doing">+ Add a card</button>
              </section>
              <section class="dashboard-developer-lane" data-developer-lane="Done" data-developer-lane-dropzone="Done">
                <header class="dashboard-developer-lane-head">
                  <div>
                    <p class="dashboard-kicker">List</p>
                    <h3>Done</h3>
                    <p class="dashboard-developer-lane-copy">Finished and ready to archive.</p>
                  </div>
                  <span class="dashboard-chip">0</span>
                </header>
                <div class="dashboard-developer-task-group-list">
                  <div class="dashboard-developer-lane-empty">
                    <strong>No cards yet.</strong>
                    <p>Start this list with a card for Done.</p>
                  </div>
                </div>
                <button type="button" class="dashboard-developer-lane-add" data-developer-task-create-status="Done" onclick="window.__aavgoOpenDeveloperTaskModal && window.__aavgoOpenDeveloperTaskModal('Done')" aria-label="Add card to Done">+ Add a card</button>
              </section>
            </div>
          </div>
          </div>

          <section class="dashboard-view-panel dashboard-panel dashboard-panel-history" data-developer-view-panel="archive" data-developer-archive-dropzone="true">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Archive</p>
                <h2>Archived roadmap items.</h2>
                <p class="dashboard-panel-copy">Drag a finished card here to archive it, or restore it later from this tab.</p>
              </div>
              <span class="dashboard-chip dashboard-chip-accent" id="developer-history-count">0 archived</span>
            </div>
            <div class="dashboard-developer-history-list" id="developer-task-history">
              <div class="dashboard-empty-state">
                <strong>No archived tasks yet.</strong>
                <p>When a task is finished, it moves here instead of being deleted.</p>
              </div>
            </div>
          </section>

          <section class="dashboard-view-panel dashboard-panel dashboard-panel-history" data-developer-view-panel="audit">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Audit</p>
                <h2>Leadership activity trail.</h2>
                <p class="dashboard-panel-copy">Created, moved, archived, restored, and deleted actions land here for quick review.</p>
              </div>
              <span class="dashboard-chip dashboard-chip-accent" id="developer-audit-count">0 events</span>
            </div>
            <div class="dashboard-developer-audit-list" id="developer-task-audit-list">
              <div class="dashboard-empty-state">
                <strong>No activity yet.</strong>
                <p>Every leadership action will appear here once it happens.</p>
              </div>
            </div>
          </section>
        </section>
      </section>
    </main>
  </div>

  <div class="dashboard-modal" id="developer-task-modal" hidden>
    <div class="dashboard-modal-backdrop" data-developer-task-modal-close></div>
    <div class="dashboard-modal-dialog dashboard-developer-task-modal">
      <div class="dashboard-panel-heading dashboard-panel-heading-tight">
        <div>
          <p class="dashboard-kicker">Create task</p>
          <h2>Add a new roadmap item</h2>
        </div>
        <button type="button" class="dashboard-modal-close" data-developer-task-modal-close aria-label="Close task form">Close</button>
      </div>

      <form id="developer-task-form" class="dashboard-developer-form-grid" novalidate onsubmit="return false;">
        <label class="dashboard-control-field dashboard-control-field-wide">
          <span>Task</span>
          <input id="developer-task-title" type="text" maxlength="140" placeholder="What needs to be built or fixed?">
        </label>
        <label class="dashboard-control-field">
          <span>Owner</span>
          <input id="developer-task-owner" type="text" maxlength="60" placeholder="Who is handling this?">
        </label>
        <label class="dashboard-control-field">
          <span>Start date</span>
          <div class="dashboard-deadline-picker" data-aavgo-date-picker data-value-prefix="Start" data-empty-label="Choose a start date">
            <input id="developer-task-start" type="date" data-date-input readonly class="dashboard-deadline-native" tabindex="-1" aria-hidden="true">
            <button type="button" class="dashboard-deadline-trigger" id="developer-task-start-trigger" data-date-trigger aria-haspopup="dialog" aria-expanded="false">
              Choose a start date
            </button>
            <div class="dashboard-deadline-popover" id="developer-task-start-popover" data-date-popover hidden>
              <div class="dashboard-deadline-header">
                <button type="button" class="dashboard-deadline-nav" data-date-prev aria-label="Previous month">&lsaquo;</button>
                <strong id="developer-task-start-month" data-date-month>Month</strong>
                <button type="button" class="dashboard-deadline-nav" data-date-next aria-label="Next month">&rsaquo;</button>
              </div>
              <div class="dashboard-deadline-weekdays" aria-hidden="true">
                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
              </div>
              <div class="dashboard-deadline-grid" id="developer-task-start-grid" data-date-grid></div>
              <div class="dashboard-deadline-footer">
                <button type="button" class="dashboard-deadline-chip" data-date-today>Today</button>
                <button type="button" class="dashboard-deadline-chip" data-date-nextweek>Next week</button>
                <button type="button" class="dashboard-deadline-chip" data-date-clear>Clear</button>
                <button type="button" class="dashboard-deadline-chip" data-date-close>Hide calendar</button>
              </div>
            </div>
          </div>
        </label>
        <label class="dashboard-control-field dashboard-control-field-wide">
          <span>Due date</span>
          <div class="dashboard-deadline-picker" data-aavgo-date-picker data-value-prefix="Due" data-empty-label="Choose a due date">
            <input id="developer-task-deadline" type="date" data-date-input required readonly class="dashboard-deadline-native" tabindex="-1" aria-hidden="true">
            <button type="button" class="dashboard-deadline-trigger" id="developer-task-deadline-trigger" data-date-trigger aria-haspopup="dialog" aria-expanded="false">
              Choose a due date
            </button>
            <div class="dashboard-deadline-popover" id="developer-task-deadline-popover" data-date-popover hidden>
              <div class="dashboard-deadline-header">
                <button type="button" class="dashboard-deadline-nav" data-date-prev aria-label="Previous month">&lsaquo;</button>
                <strong id="developer-task-deadline-month" data-date-month>Month</strong>
                <button type="button" class="dashboard-deadline-nav" data-date-next aria-label="Next month">&rsaquo;</button>
              </div>
              <div class="dashboard-deadline-weekdays" aria-hidden="true">
                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
              </div>
              <div class="dashboard-deadline-grid" id="developer-task-deadline-grid" data-date-grid></div>
              <div class="dashboard-deadline-footer">
                <button type="button" class="dashboard-deadline-chip" data-date-today>Today</button>
                <button type="button" class="dashboard-deadline-chip" data-date-nextweek>Next week</button>
                <button type="button" class="dashboard-deadline-chip" data-date-clear>Clear</button>
                <button type="button" class="dashboard-deadline-chip" data-date-close>Hide calendar</button>
              </div>
            </div>
          </div>
          <small class="dashboard-control-hint">Pick a date from the calendar below, or jump to today / next week.</small>
        </label>
        <label class="dashboard-control-field">
          <span>Urgency</span>
          <select id="developer-task-priority">
            <option value="Normal">Normal</option>
            <option value="Urgent">Urgent</option>
            <option value="Future">Future</option>
            <option value="Done today">Done today</option>
          </select>
        </label>
        <label class="dashboard-control-field">
          <span>Status</span>
          <select id="developer-task-status">
            <option value="To Do">To Do</option>
            <option value="Doing">Doing</option>
            <option value="Done">Done</option>
          </select>
        </label>
        <label class="dashboard-control-field dashboard-control-field-wide">
          <span>Post note</span>
          <textarea id="developer-task-notes" class="dashboard-control-textarea" rows="4" placeholder="Add context, blockers, risks, progress, or rollout notes."></textarea>
        </label>
        <label class="dashboard-control-field dashboard-control-field-wide">
          <span>Attachments</span>
          <input id="developer-task-attachments" type="file" multiple accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.txt,.md,.csv,.json,image/*,application/pdf,text/plain">
          <small class="dashboard-control-hint">Upload files or screenshots that support the note.</small>
        </label>
      </form>

        <p class="dashboard-panel-copy dashboard-developer-feedback" id="developer-task-feedback">Add the task to the board, then drag it between To Do, Doing, and Done as it changes.</p>
        <div class="dashboard-control-row">
          <button class="button button-primary dashboard-inline-button" id="developer-task-add" type="button" onclick="return window.__aavgoSubmitDeveloperTask ? (window.__aavgoSubmitDeveloperTask(), false) : false">Add task</button>
          <button class="button button-secondary dashboard-inline-button" id="developer-sync-all" type="button">Resync Discord roles</button>
          <button class="button button-secondary dashboard-inline-button" id="developer-push-snapshot" type="button">Refresh snapshot now</button>
        </div>
    </div>
  </div>

  <div class="dashboard-modal" id="developer-task-detail-modal" hidden>
    <div class="dashboard-modal-backdrop" data-developer-task-detail-close></div>
    <div class="dashboard-modal-dialog dashboard-developer-detail-modal">
      <div class="dashboard-panel-heading dashboard-panel-heading-tight">
        <div>
          <p class="dashboard-kicker">Card details</p>
          <h2 id="developer-task-detail-title">Roadmap item</h2>
        </div>
        <button type="button" class="dashboard-modal-close" data-developer-task-detail-close aria-label="Close task details">Close</button>
      </div>
      <div id="developer-task-detail-body" class="dashboard-developer-detail-body"></div>
    </div>
  </div>

  <script>
    window.AAVGO_CURRENT_USER = <?php echo json_encode([
      'displayName' => $displayName,
      'roleSummary' => $roleSummary,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.__AAVGO_DEVELOPER_BOARD__ = <?php echo json_encode($developerBoardStore, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.AAVGO_DEVELOPER_BOARD_ENDPOINT = '/api/developer-board/index.php';
    window.AAVGO_ADMIN_COMMAND_ENDPOINT = '/api/admin-command/';
    window.AAVGO_LIVE_SIGNALS_ENDPOINT = '/api/live-signals/';
  </script>
<script src="<?= htmlspecialchars(aavgo_asset_url('/script.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>

