<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_access('admin');
$displayName = aavgo_display_name($user);
$safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$safeAvatarLetter = htmlspecialchars(strtoupper(substr($displayName, 0, 1) ?: 'A'), ENT_QUOTES, 'UTF-8');
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
    content="Private Aavgo leadership suite for Team Leaders and Operations Managers."
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
  <div class="dashboard-shell">
    <aside class="dashboard-sidebar reveal reveal-in">
      <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>

      <section class="dashboard-profile-card">
        <div class="dashboard-avatar"><?php echo $safeAvatarLetter; ?></div>
        <div class="dashboard-profile-copy">
          <strong><?php echo $safeDisplayName; ?></strong>
          <p>Leadership access is active</p>
        </div>
      </section>

      <div class="dashboard-sidebar-meta">
        <span class="dashboard-chip dashboard-chip-accent">Admin</span>
        <span class="dashboard-chip">Discord verified</span>
      </div>

      <div class="dashboard-command-box">
        <span class="dashboard-command-label">Leadership mode</span>
        <strong>Escalations, approvals, and hotel pressure in one place.</strong>
      </div>

      <nav class="dashboard-nav" aria-label="Leadership navigation">
        <a class="dashboard-nav-link is-active" href="/admin/">Leadership board</a>
        <a class="dashboard-nav-link" href="#insight">Insight</a>
        <a class="dashboard-nav-link" href="#actions">Action lane</a>
        <a class="dashboard-nav-link" href="/user/">User workspace</a>
        <a class="dashboard-nav-link" href="/">Front door</a>
        <a class="dashboard-nav-link" href="/auth/logout/">Log out</a>
      </nav>

      <div class="dashboard-side-note">
        <p class="dashboard-kicker">Tonight</p>
        <h3>4 live pressure points</h3>
        <p>Coverage review, handover follow-up, pending approvals, and team support are the current focus.</p>
      </div>
    </aside>

    <main class="dashboard-main">
      <header class="dashboard-header reveal reveal-in">
        <div>
          <p class="dashboard-breadcrumb">Dashboard / Leadership</p>
          <h1 class="dashboard-title">Operational command for <?php echo $safeDisplayName; ?></h1>
          <p class="dashboard-subtitle">
            Team Leaders and Operations Managers land here after Discord verifies the right role. This is the
            premium oversight surface for service quality, staffing clarity, and leadership decisions.
          </p>
        </div>
        <div class="dashboard-toolbar">
          <a class="dashboard-toolbar-link" href="/user/">Open staff view</a>
          <a class="dashboard-toolbar-link" href="/">Front door</a>
          <a class="dashboard-toolbar-link" href="/auth/logout/">Log out</a>
        </div>
      </header>

      <section class="dashboard-hero-card reveal reveal-delay-1" id="overview">
        <div class="dashboard-hero-grid">
          <div>
            <p class="dashboard-kicker">Leadership suite</p>
            <h2 class="dashboard-hero-title">A tighter control room for coverage, approvals, and live operational pressure.</h2>
            <p class="dashboard-hero-copy">
              The layout is built to feel private, trusted, and high-end before heavier reporting tools even arrive.
              Leadership should step into a surface that already feels like command, not clutter.
            </p>
          </div>

          <div class="dashboard-hero-aside">
            <span class="dashboard-chip dashboard-chip-accent">Leadership tier</span>
            <strong>Locked before render</strong>
            <p>No public browsing, no mixed staff surface, and no access unless Discord confirms the role first.</p>
          </div>
        </div>
      </section>

      <section class="dashboard-stat-grid reveal reveal-delay-1">
        <article class="dashboard-stat-card">
          <p>Coverage health</p>
          <strong>94%</strong>
          <span>Across active hotel lanes</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Approvals waiting</p>
          <strong>03</strong>
          <span>Need leadership eyes tonight</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Hotels monitored</p>
          <strong>06</strong>
          <span>Across the live operations board</span>
        </article>
        <article class="dashboard-stat-card">
          <p>Escalations open</p>
          <strong>01</strong>
          <span>Contained but still active</span>
        </article>
      </section>

      <section class="dashboard-content-grid reveal reveal-delay-2" id="insight">
        <article class="dashboard-panel dashboard-panel-chart">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Leadership pulse</p>
              <h2>Weekly service pressure</h2>
            </div>
            <span class="dashboard-chip">Today</span>
          </div>

          <div class="dashboard-chart-surface">
            <svg class="dashboard-line-chart" viewBox="0 0 520 220" preserveAspectRatio="none" aria-hidden="true">
              <defs>
                <linearGradient id="adminLine" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="#6171ff"></stop>
                  <stop offset="55%" stop-color="#a38ae6"></stop>
                  <stop offset="100%" stop-color="#d8b05f"></stop>
                </linearGradient>
              </defs>
              <path d="M0 160 C40 156 68 150 100 154 S160 144 194 148 S262 136 302 142 S376 126 420 118 S474 92 520 86" fill="none" stroke="url(#adminLine)" stroke-width="3" stroke-linecap="round"></path>
              <line x1="420" y1="20" x2="420" y2="190" stroke="rgba(255,255,255,0.18)" stroke-width="1" stroke-dasharray="4 6"></line>
              <rect x="390" y="74" width="92" height="34" rx="17" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.12)"></rect>
              <text x="436" y="95" text-anchor="middle" fill="#f8f4ee" font-size="12">87% stable</text>
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
                <p class="dashboard-kicker">Complete today</p>
                <h2>Leadership lane</h2>
              </div>
            </div>
            <div class="dashboard-task-list">
              <div class="dashboard-task-item">
                <strong>Finalize handover review</strong>
                <span>Needs final note before sign-off</span>
              </div>
              <div class="dashboard-task-item">
                <strong>Approve hotel lane update</strong>
                <span>Coverage change requested at 11:30 PM</span>
              </div>
              <div class="dashboard-task-item">
                <strong>Close escalation report</strong>
                <span>Waiting for final manager comment</span>
              </div>
            </div>
          </article>

          <article class="dashboard-panel">
            <p class="dashboard-kicker">Leadership note</p>
            <h2>Keep the floor calm.</h2>
            <p class="dashboard-panel-copy">
              The premium feel matters here because leadership should arrive to clarity, not noise. A clean board helps the right decisions happen faster.
            </p>
          </article>
        </aside>
      </section>

      <section class="dashboard-content-grid dashboard-content-grid-bottom reveal reveal-delay-2" id="actions">
        <article class="dashboard-panel dashboard-focus-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Current focus</p>
              <h2>Resolve tonight's handover pressure</h2>
            </div>
            <span class="dashboard-chip">83% complete</span>
          </div>
          <p class="dashboard-panel-copy">
            Confirm staffing balance, close the review thread, and release a single clear update to the operations lane.
          </p>
          <div class="dashboard-progress">
            <span style="width: 83%;"></span>
          </div>
          <div class="dashboard-action-row">
            <span class="dashboard-action-pill">Mark complete</span>
            <span class="dashboard-action-pill">Add note</span>
            <span class="dashboard-action-pill">Pause lane</span>
            <span class="dashboard-action-pill">Delegate</span>
          </div>
          <div class="dashboard-message-card">
            <strong>Latest update</strong>
            <p>Coverage is back in range. Final leadership sign-off should be the last move before midnight closeout.</p>
          </div>
        </article>

        <article class="dashboard-panel">
          <div class="dashboard-panel-heading">
            <div>
              <p class="dashboard-kicker">Control surface</p>
              <h2>Protected next steps</h2>
            </div>
          </div>
          <div class="dashboard-control-list">
            <div class="dashboard-control-item">
              <strong>Approvals</strong>
              <span>Route sensitive decisions through one protected lane.</span>
            </div>
            <div class="dashboard-control-item">
              <strong>Hotel review</strong>
              <span>Keep leadership eyes on lane health, not scattered notes.</span>
            </div>
            <div class="dashboard-control-item">
              <strong>Escalation notes</strong>
              <span>Build toward a trusted internal record of what changed and why.</span>
            </div>
          </div>
        </article>
      </section>
    </main>
  </div>

  <script src="/script.js"></script>
</body>
</html>
