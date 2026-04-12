<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_access('admin');
$safeDisplayName = htmlspecialchars(aavgo_display_name($user), ENT_QUOTES, 'UTF-8');
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
<body class="workspace-page workspace-page-admin">
  <div class="site-shell">
    <header class="topbar topbar-minimal">
      <a class="brand brand-plain" href="/" aria-label="Aavgo home">Aavgo</a>
      <div class="hero-actions">
        <a class="button button-secondary" href="/user/">User View</a>
        <a class="button button-secondary" href="/">Front Door</a>
        <a class="button button-ghost" href="/auth/logout/">Log Out</a>
      </div>
    </header>

    <main class="workspace-main">
      <section class="workspace-hero reveal reveal-in">
        <div class="workspace-copy">
          <p class="eyebrow">Leadership suite</p>
          <h1>
            Operational command for
            <span class="accent-script"><?php echo $safeDisplayName; ?></span>
          </h1>
          <p class="hero-text">
            Discord verified your leadership role before this page opened. This management layer is reserved
            for Team Leaders and Operations Managers who need the premium oversight view, escalation posture,
            and protected expansion space for Aavgo.
          </p>
          <div class="role-band role-band-compact">
            <span>Team Leaders</span>
            <span>Operations Managers</span>
            <span>Role-locked access</span>
          </div>
        </div>

        <aside class="workspace-panel workspace-panel-dark">
          <p class="section-label">Access tier</p>
          <strong class="hero-metric">Leadership</strong>
          <p>
            This route stays closed unless Discord confirms the right role. No public browsing, no shared staff surface,
            and no management tools shown before the gate opens.
          </p>
        </aside>
      </section>

      <section class="workspace-grid">
        <article class="surface-card reveal">
          <p class="section-label">Priority lane</p>
          <h2>Escalation visibility</h2>
          <p>
            Track the moments that need a stronger hand: coverage pressure, handover review, and the places where a
            clean leadership decision keeps service stable.
          </p>
          <ul class="list-clean">
            <li>Leadership-only route with Discord verification first</li>
            <li>Clearer room for approvals, reviews, and protected controls</li>
            <li>Built to feel polished before data-heavy tooling lands</li>
          </ul>
        </article>

        <article class="surface-card reveal reveal-delay-1">
          <p class="section-label">Next build layer</p>
          <h2>Management control surface</h2>
          <p>
            This suite is ready to grow into a true operational board for staffing, issue routing, approvals,
            escalation notes, and Discord-connected leadership tools.
          </p>
          <div class="metric-grid">
            <div class="metric-card">
              <span class="metric-kicker">Route</span>
              <strong>/admin</strong>
            </div>
            <div class="metric-card">
              <span class="metric-kicker">Audience</span>
              <strong>Lead roles only</strong>
            </div>
          </div>
        </article>

        <article class="surface-card surface-card-wide reveal reveal-delay-2">
          <p class="section-label">Why this feels different</p>
          <h2>Premium by default, not afterthought later</h2>
          <p>
            The front door is now exclusive on purpose, and the inside of leadership space matches that tone.
            Aavgo should feel like a place people want to be trusted with, not just another utility page.
          </p>
        </article>
      </section>
    </main>
  </div>

  <script src="/script.js"></script>
</body>
</html>
