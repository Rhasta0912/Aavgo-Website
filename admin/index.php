<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

$user = aavgo_require_auth();
$displayName = trim((string) ($user['global_name'] ?? '')) !== ''
    ? (string) $user['global_name']
    : (string) ($user['username'] ?? 'Aavgo User');
$safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aavgo Admin | Protected Access</title>
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta
    name="description"
    content="Protected Aavgo administrator area for operations management and internal controls."
  >
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;700;800&family=Instrument+Serif:ital@0;1&display=swap"
    rel="stylesheet"
  >
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="admin-page">
  <div class="site-shell admin-shell">
    <header class="topbar">
      <a class="brand" href="/" aria-label="Aavgo home">
        <span class="brand-mark">A</span>
        <span class="brand-text">Aavgo Admin</span>
      </a>
      <div class="hero-actions">
        <a class="button button-secondary" href="/">Back to Site</a>
        <a class="button button-ghost" href="/auth/logout/">Log Out</a>
      </div>
    </header>

    <main>
      <section class="admin-hero reveal reveal-in">
        <div class="admin-hero-copy">
          <p class="eyebrow">Protected administrator area</p>
          <h1>
            Welcome back,
            <span class="accent-script"><?php echo $safeDisplayName; ?></span>
          </h1>
          <p class="hero-text">
            Your Discord account has been verified against the Aavgo server, so this admin route is now
            available for internal operations work and future management tools.
          </p>
        </div>
        <div class="admin-hero-badge">
          <span class="pill pill-live">Verified</span>
          <p>Access is granted through Discord sign-in and a server membership check before this page loads.</p>
        </div>
      </section>

      <section class="admin-grid">
        <article class="admin-card reveal reveal-in">
          <p class="aside-label">Prepared for</p>
          <h2>Admin dashboard expansion</h2>
          <p>
            We can build this into a proper control surface for approvals, logs, staffing visibility,
            and Discord-connected actions once you are ready for the next layer.
          </p>
        </article>

        <article class="admin-card reveal reveal-in">
          <p class="aside-label">Security layer</p>
          <h2>Discord-first access</h2>
          <p>
            This route now uses Discord OAuth on the server side, keeps the secret off the public website,
            and blocks non-members before the page is served.
          </p>
        </article>

        <article class="admin-card admin-card-wide reveal reveal-in">
          <p class="aside-label">Next layer</p>
          <h2>What this admin area can grow into</h2>
          <ul class="admin-list">
            <li>Discord webhook controls and moderation actions</li>
            <li>Protected contact-form inbox and escalation tools</li>
            <li>Live operational stats for hotel teams and leadership</li>
            <li>Management-only content, notes, and deployment controls</li>
          </ul>
        </article>
      </section>
    </main>
  </div>

  <script src="/script.js"></script>
</body>
</html>
