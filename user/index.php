<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_access('user');
$safeDisplayName = htmlspecialchars(aavgo_display_name($user), ENT_QUOTES, 'UTF-8');
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
<body class="workspace-page workspace-page-user">
  <div class="site-shell">
    <header class="topbar topbar-minimal">
      <a class="brand brand-plain" href="/" aria-label="Aavgo home">Aavgo</a>
      <div class="hero-actions">
        <?php if ($showAdminLink): ?>
          <a class="button button-secondary" href="/admin/">Leadership Suite</a>
        <?php endif; ?>
        <a class="button button-secondary" href="/">Front Door</a>
        <a class="button button-ghost" href="/auth/logout/">Log Out</a>
      </div>
    </header>

    <main class="workspace-main">
      <section class="workspace-hero reveal reveal-in">
        <div class="workspace-copy">
          <p class="eyebrow">User workspace</p>
          <h1>
            Your premium shift space is open,
            <span class="accent-script"><?php echo $safeDisplayName; ?></span>
          </h1>
          <p class="hero-text">
            Discord verified your Aavgo role before this page unlocked. This route is for Trainees and Agents who
            need a cleaner, calmer place to work, learn, and move through the platform without the public layer in front.
          </p>
          <div class="role-band role-band-compact">
            <span>Trainees</span>
            <span>Agents</span>
            <span>Private access</span>
          </div>
        </div>

        <aside class="workspace-panel workspace-panel-soft">
          <p class="section-label">Access tier</p>
          <strong class="hero-metric">Operations</strong>
          <p>
            The user route opens only after role verification, so the full experience stays reserved for active Aavgo staff.
          </p>
        </aside>
      </section>

      <section class="workspace-grid">
        <article class="surface-card reveal">
          <p class="section-label">Flow</p>
          <h2>Beginner-friendly on purpose</h2>
          <p>
            This side of Aavgo is built to feel premium without becoming confusing. Staff should know exactly where
            to go, what to press, and what the next step means.
          </p>
        </article>

        <article class="surface-card reveal reveal-delay-1">
          <p class="section-label">Protected route</p>
          <h2>Role first, website second</h2>
          <p>
            If Discord does not confirm an allowed Aavgo role, this route never opens. The login wall is now a true
            gate instead of a decorative page.
          </p>
          <div class="metric-grid">
            <div class="metric-card">
              <span class="metric-kicker">Route</span>
              <strong>/user</strong>
            </div>
            <div class="metric-card">
              <span class="metric-kicker">Audience</span>
              <strong>Staff access</strong>
            </div>
          </div>
        </article>

        <article class="surface-card surface-card-wide reveal reveal-delay-2">
          <p class="section-label">Where this can grow</p>
          <h2>A polished home for the real workflow</h2>
          <ul class="list-clean">
            <li>Shift start and training entry with a more premium surface</li>
            <li>Cleaner handover, check-in, and hotel workflow views</li>
            <li>Beginner-safe actions that still feel high-end and intentional</li>
          </ul>
        </article>
      </section>
    </main>
  </div>

  <script src="/script.js"></script>
</body>
</html>
