<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_access('user');
$displayName = aavgo_display_name($user);
$safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$safeAvatarLetter = htmlspecialchars(strtoupper(substr($displayName, 0, 1) ?: 'A'), ENT_QUOTES, 'UTF-8');
$showAdminLink = aavgo_user_can_access($user, 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aavgo | User Workspace</title>
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta
    name="description"
    content="Private Aavgo user workspace for Trainees and Agents."
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
  <div class="dashboard-shell">
    <aside class="dashboard-sidebar reveal reveal-in">
      <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>

      <section class="dashboard-profile-card">
        <div class="dashboard-avatar"><?php echo $safeAvatarLetter; ?></div>
        <div class="dashboard-profile-copy">
          <strong><?php echo $safeDisplayName; ?></strong>
          <p>Operations route is active</p>
        </div>
      </section>

      <div class="dashboard-sidebar-meta">
        <span class="dashboard-chip dashboard-chip-accent">User</span>
        <span class="dashboard-chip">Role verified</span>
      </div>

      <div class="dashboard-command-box">
        <span class="dashboard-command-label">Operations mode</span>
        <strong>Clear shifts, cleaner handovers, and a calmer day surface.</strong>
      </div>

      <nav class="dashboard-nav" aria-label="User navigation">
        <a class="dashboard-nav-link is-active" href="/user/">Operations desk</a>
        <a class="dashboard-nav-link" href="#insight">Shift insight</a>
        <a class="dashboard-nav-link" href="#focus">Focus lane</a>
        <?php if ($showAdminLink): ?>
          <a class="dashboard-nav-link" href="/admin/">Leadership board</a>
        <?php endif; ?>
        <a class="dashboard-nav-link" href="/">Front door</a>
        <a class="dashboard-nav-link" href="/auth/logout/">Log out</a>
      </nav>

      <div class="dashboard-side-note">
        <p class="dashboard-kicker">Today</p>
        <h3>Ready for a clean shift</h3>
        <p>Training, handovers, and hotel rhythm now sit in one quieter workspace instead of a messy public front layer.</p>
      </div>
    </aside>

    <main class="dashboard-main">
      <header class="dashboard-header reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb">Workspace / Operations</p>
          <h1 class="dashboard-title">Your private Aavgo desk, <?php echo $safeDisplayName; ?></h1>
          <p class="dashboard-subtitle">
            Trainees and Agents land in a calmer dashboard built for actual work, not noise. Discord opened this route only after role verification passed.
          </p>
        </div>
        <div class="dashboard-toolbar">
          <?php if ($showAdminLink): ?>
            <a class="dashboard-toolbar-link" href="/admin/">Leadership board</a>
          <?php endif; ?>
          <a class="dashboard-toolbar-link" href="/">Front door</a>
          <a class="dashboard-toolbar-link" href="/auth/logout/">Log out</a>
        </div>
      </header>

      <section class="dashboard-hero-card reveal reveal-delay-1" id="overview">
        <div class="dashboard-hero-grid">
          <div>
            <p class="dashboard-kicker">User workspace</p>
            <h2 class="dashboard-hero-title">A premium operations desk for shift flow, training, and handover clarity.</h2>
            <p class="dashboard-hero-copy">
              This route should feel composed the second staff enters it. The interface is meant to guide work forward without looking generic or crowding the eye.
            </p>
          </div>

          <div class="dashboard-hero-aside">
            <span class="dashboard-chip dashboard-chip-accent">Operations tier</span>
            <strong>Private before productive</strong>
            <p>The website stays closed until the right role is confirmed, so the full experience belongs only to active Aavgo staff.</p>
          </div>
        </div>
      </section>

      <section class="dashboard-stat-grid reveal reveal-delay-1">
        <article class="dashboard-stat-card">
          <p>Shift readiness</p>
          <strong>91%</strong>
          <span>Lane is clean for the next handoff</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Hotels watched</p>
          <strong>06</strong>
          <span>Across the current working board</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Guest queue</p>
          <strong>12</strong>
          <span>Spread across active support lanes</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Training pace</p>
          <strong>08/10</strong>
          <span>Tasks closed without extra confusion</span>
        </article>
      </section>

      <section class="dashboard-content-grid reveal reveal-delay-2" id="insight">
        <article class="dashboard-panel dashboard-panel-chart">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Shift rhythm</p>
              <h2>Weekly productivity team</h2>
            </div>
            <span class="dashboard-chip">Today</span>
          </div>

          <div class="dashboard-chart-surface">
            <svg class="dashboard-line-chart" viewBox="0 0 520 220" preserveAspectRatio="none" aria-hidden="true">
              <defs>
                <linearGradient id="userLine" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="#6b73ff"></stop>
                  <stop offset="58%" stop-color="#8ba1f8"></stop>
                  <stop offset="100%" stop-color="#7ab3a1"></stop>
                </linearGradient>
              </defs>
              <path d="M0 164 C40 160 72 154 108 156 S176 146 210 148 S270 140 314 138 S384 130 430 120 S484 98 520 92" fill="none" stroke="url(#userLine)" stroke-width="3" stroke-linecap="round"></path>
              <line x1="408" y1="22" x2="408" y2="190" stroke="rgba(255,255,255,0.18)" stroke-width="1" stroke-dasharray="4 6"></line>
              <rect x="380" y="78" width="94" height="34" rx="17" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.12)"></rect>
              <text x="427" y="99" text-anchor="middle" fill="#f8f4ee" font-size="12">86% smooth</text>
            </svg>
          </div>

          <div class="dashboard-axis">
            <span>Mon</span>
            <span>Tue</span>
            <span>Wed</span>
            <span>Thu</span>
            <span>Fri</span>
            <span>Sat</span>
            <span>Sun</span>
          </div>
        </article>

        <aside class="dashboard-stack">
          <article class="dashboard-panel">
            <div class="dashboard-panel-heading">
              <div>
                <p class="dashboard-kicker">Today's lane</p>
                <h2>In motion</h2>
              </div>
            </div>
            <div class="dashboard-task-list">
              <div class="dashboard-task-item">
                <strong>Front desk handover</strong>
                <span>Ready for the final review note</span>
              </div>
              <div class="dashboard-task-item">
                <strong>Training queue</strong>
                <span>Two new staff items waiting</span>
              </div>
              <div class="dashboard-task-item">
                <strong>Guest follow-up</strong>
                <span>One lane needs a second response</span>
              </div>
            </div>
          </article>

          <article class="dashboard-panel">
            <p class="dashboard-kicker">Quiet reminder</p>
            <h2>Smooth beats rushed.</h2>
            <p class="dashboard-panel-copy">
              This page is meant to help staff move with confidence. The better the layout feels, the easier it is to keep the workflow clean.
            </p>
          </article>
        </aside>
      </section>

      <section class="dashboard-content-grid dashboard-content-grid-bottom reveal reveal-delay-2" id="focus">
        <article class="dashboard-panel dashboard-focus-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Current task</p>
              <h2>Finalize front desk handover document</h2>
            </div>
            <span class="dashboard-chip">83% complete</span>
          </div>
          <p class="dashboard-panel-copy">
            Complete the strategic handover note, confirm priority rooms, and close the shift summary before the next lane opens.
          </p>
          <div class="dashboard-progress">
            <span style="width: 83%;"></span>
          </div>
          <div class="dashboard-action-row">
            <span class="dashboard-action-pill">Mark complete</span>
            <span class="dashboard-action-pill">Add notes</span>
            <span class="dashboard-action-pill">Pause work</span>
            <span class="dashboard-action-pill">Delegate</span>
          </div>
          <div class="dashboard-message-card">
            <strong>Latest update</strong>
            <p>The report is mostly done. Final room notes and queue confirmation should close the task cleanly.</p>
          </div>
        </article>

        <article class="dashboard-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Fast actions</p>
              <h2>Operational tools</h2>
            </div>
          </div>
          <div class="dashboard-control-list">
            <div class="dashboard-control-item">
              <strong>Start shift route</strong>
              <span>Enter the next hotel lane with less friction.</span>
            </div>
            <div class="dashboard-control-item">
              <strong>Handover notes</strong>
              <span>Keep the next person clear on what changed.</span>
            </div>
            <div class="dashboard-control-item">
              <strong>Training queue</strong>
              <span>Protect the beginner flow without lowering the quality bar.</span>
            </div>
          </div>
        </article>
      </section>
    </main>
  </div>

  <script src="/script.js"></script>
</body>
</html>
