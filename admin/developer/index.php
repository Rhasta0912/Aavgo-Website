<?php

declare(strict_types=1);

require __DIR__ . '/../../auth/bootstrap.php';

$user = aavgo_require_access('admin');
if (!aavgo_user_is_developer($user)) {
    aavgo_redirect('/admin/');
}

$displayName = aavgo_display_name($user);
$safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$roleSummary = aavgo_user_role_summary($user);
$safeRoleSummary = htmlspecialchars($roleSummary, ENT_QUOTES, 'UTF-8');
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
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="workspace-page workspace-dashboard workspace-page-admin workspace-page-developer">
  <div class="dashboard-shell dashboard-shell-admin">
    <aside class="dashboard-sidebar dashboard-sidebar-admin reveal reveal-in">
      <div class="dashboard-sidebar-glow"></div>
      <div class="dashboard-sidebar-top">
        <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>
      </div>

      <section class="dashboard-profile-card dashboard-profile-card-plain">
        <div class="dashboard-profile-copy">
          <strong><?php echo $safeDisplayName; ?></strong>
          <p><?php echo $safeRoleSummary; ?></p>
        </div>
      </section>

      <nav class="dashboard-nav dashboard-nav-vertical" aria-label="Developer navigation">
        <a class="dashboard-nav-link" href="/admin/">Leadership board</a>
        <a class="dashboard-nav-link is-active" href="/admin/developer/">Developer panel</a>
        <a class="dashboard-nav-link" href="/user/">User workspace</a>
        <a class="dashboard-nav-link" href="/auth/logout/">Log out</a>
      </nav>

      <section class="dashboard-side-section">
        <p class="dashboard-kicker">Developer mode</p>
        <strong>Use this lane for roadmap ownership, maintenance timing, and live platform utilities.</strong>
        <p>Tasks here are detailed on purpose so the leadership board can stay focused on live operations.</p>
      </section>
    </aside>

    <main class="dashboard-main dashboard-main-admin">
      <header class="dashboard-header dashboard-header-admin reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb">Dashboard / Developer / Panel</p>
          <h1 class="dashboard-title dashboard-title-wide">Roadmap board.</h1>
          <p class="dashboard-subtitle">Trello-style roadmap for tasks, fixes, and platform work. Drag cards between To Do, Doing, and Done. Add new items from the plus button.</p>
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
                <small><?php echo $safeRoleSummary; ?></small>
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
        <article class="dashboard-panel dashboard-panel-developer-board dashboard-panel-wide">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Developer board</p>
              <h2>To Do, Doing, Done.</h2>
            </div>
            <div class="dashboard-panel-actions">
              <span class="dashboard-chip dashboard-chip-accent">Drag to move</span>
              <button class="button button-secondary dashboard-developer-add-launcher" type="button" data-developer-task-open onclick="window.__aavgoOpenDeveloperTaskModal && window.__aavgoOpenDeveloperTaskModal('To Do')">+ New item</button>
            </div>
          </div>

          <div class="dashboard-developer-task-list" id="developer-task-list">
            <div class="dashboard-empty-state">
              <strong>No developer tasks yet.</strong>
              <p>Add the first roadmap item, owner, deadline, and urgency.</p>
            </div>
          </div>
        </article>
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

      <form id="developer-task-form" class="dashboard-developer-form-grid">
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
          <input id="developer-task-start" type="date">
        </label>
        <label class="dashboard-control-field">
          <span>Deadline</span>
          <input id="developer-task-deadline" type="date" required>
        </label>
        <label class="dashboard-control-field">
          <span>Urgency</span>
          <select id="developer-task-priority">
            <option value="Normal">Normal</option>
            <option value="Urgent">Urgent</option>
            <option value="Future">Future</option>
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
          <span>Notes</span>
          <textarea id="developer-task-notes" class="dashboard-control-textarea" rows="4" placeholder="Add context, blockers, risks, or rollout notes."></textarea>
        </label>
        <p class="dashboard-panel-copy dashboard-developer-feedback" id="developer-task-feedback">Add the task to the board, then drag it between To Do, Doing, and Done as it changes.</p>
        <div class="dashboard-control-row">
          <button class="button button-primary dashboard-inline-button" id="developer-task-add" type="submit">Add task</button>
          <button class="button button-secondary dashboard-inline-button" id="developer-sync-all" type="button">Resync Discord roles</button>
          <button class="button button-secondary dashboard-inline-button" id="developer-push-snapshot" type="button">Refresh snapshot now</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.AAVGO_ADMIN_COMMAND_ENDPOINT = '/api/admin-command/';
    window.AAVGO_LIVE_SIGNALS_ENDPOINT = '/api/live-signals/';
  </script>
  <script src="/script.js"></script>
</body>
</html>
