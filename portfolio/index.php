<?php

declare(strict_types=1);

require __DIR__ . '/../auth/bootstrap.php';

aavgo_cache_headers();

$stylesHref = htmlspecialchars(aavgo_asset_url('/styles.css'), ENT_QUOTES, 'UTF-8');
$loginHref = htmlspecialchars(aavgo_asset_url('/auth/discord/login/'), ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aavgo | Portfolio</title>
  <meta
    name="description"
    content="Aavgo portfolio: a quiet editorial look at private operations software, leadership dashboards, and role-aware workflow design."
  >
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;700;800&family=Instrument+Serif:ital@0;1&display=swap"
    rel="stylesheet"
  >
  <link rel="stylesheet" href="<?= $stylesHref ?>">
</head>
<body class="portfolio-page">
  <div class="portfolio-shell">
    <header class="topbar portfolio-topbar reveal">
      <a class="brand brand-plain portfolio-brand" href="/" aria-label="Aavgo home">Aavgo</a>
      <nav class="portfolio-nav" aria-label="Portfolio navigation">
        <a href="#system">System</a>
        <a href="#interfaces">Interfaces</a>
        <a href="#principles">Principles</a>
        <a href="#access">Access</a>
      </nav>
      <a class="button button-secondary portfolio-nav-cta" href="<?= $loginHref ?>">Private login</a>
    </header>

    <main class="portfolio-main">
      <section class="portfolio-hero reveal">
        <div class="portfolio-hero-copy">
          <p class="section-label portfolio-kicker">Aavgo / Private operations</p>
          <h1 class="portfolio-title">Quiet software for busy teams.</h1>
          <p class="hero-text portfolio-copy">
            Aavgo is built around a simple idea: operations tools should feel composed even when the floor is moving.
            Private access, role-aware dashboards, hours visibility, hotel lanes, and roadmap work all sit behind one calm front door.
          </p>
          <div class="hero-actions portfolio-actions">
            <a class="button button-primary" href="#system">View the system</a>
            <a class="button button-secondary" href="#principles">Design principles</a>
          </div>
        </div>

        <aside class="surface-card portfolio-hero-aside">
          <p class="section-label">Signal</p>
          <div class="portfolio-aside-panel">
            <div>
              <span>Access</span>
              <strong>Discord-gated, role-checked, and intentionally private.</strong>
            </div>
            <div>
              <span>Surface</span>
              <strong>Warm editorial pages outside, focused command rooms inside.</strong>
            </div>
            <div>
              <span>Standard</span>
              <strong>Clarity first. Decoration only when it helps orientation.</strong>
            </div>
          </div>
        </aside>
      </section>

      <section class="portfolio-strip reveal reveal-delay-1" aria-label="Highlights">
        <article class="portfolio-strip-card">
          <span>01</span>
          <strong>Private by default</strong>
          <p>The public layer stays minimal while the working layer protects staff and leadership tools.</p>
        </article>
        <article class="portfolio-strip-card">
          <span>02</span>
          <strong>Operational visibility</strong>
          <p>Hours, hotels, live state, and overtime signals are designed to be readable fast.</p>
        </article>
        <article class="portfolio-strip-card">
          <span>03</span>
          <strong>Premium restraint</strong>
          <p>The interface aims for confidence, not noise: fewer distractions, stronger hierarchy.</p>
        </article>
      </section>

      <section class="portfolio-section reveal reveal-delay-1" id="system">
        <div class="portfolio-section-head">
          <p class="section-label">System</p>
          <h2>A private workspace with public restraint.</h2>
        </div>
        <div class="portfolio-work-grid">
          <article class="panel-card portfolio-work-card">
            <p class="portfolio-work-meta">Aavgo / Gate</p>
            <h3>Access without clutter</h3>
            <p>The homepage behaves like a premium login gate instead of a noisy marketing page. People see the right door, then the role checks decide the rest.</p>
            <div class="portfolio-chip-row">
              <span class="dashboard-chip">PHP</span>
              <span class="dashboard-chip">Auth</span>
              <span class="dashboard-chip">Role checks</span>
            </div>
          </article>
          <article class="panel-card portfolio-work-card">
            <p class="portfolio-work-meta">Aavgo / Command</p>
            <h3>Leadership visibility</h3>
            <p>Admin tools focus on who is active, where they are assigned, what is stale, and which actions need careful confirmation.</p>
            <div class="portfolio-chip-row">
              <span class="dashboard-chip">Dashboards</span>
              <span class="dashboard-chip">UX hierarchy</span>
              <span class="dashboard-chip">Data views</span>
            </div>
          </article>
          <article class="panel-card portfolio-work-card">
            <p class="portfolio-work-meta">Aavgo / Roadmap</p>
            <h3>Shared product memory</h3>
            <p>The developer board keeps notes, attachments, activity, archive, and status in one universal workspace instead of browser-local fragments.</p>
            <div class="portfolio-chip-row">
              <span class="dashboard-chip">Roadmaps</span>
              <span class="dashboard-chip">Drag & drop</span>
              <span class="dashboard-chip">Activity trail</span>
            </div>
          </article>
        </div>
      </section>

      <section class="portfolio-split reveal reveal-delay-2" id="interfaces">
        <article class="surface-card portfolio-note-card">
          <p class="section-label">Interfaces</p>
          <h2>Spreadsheet when precision matters. Cards when attention matters.</h2>
          <p>
            Full Hours is treated like a work surface: frozen identifiers, readable dates, summary context, and clear edit entry points.
            The Developer board is treated like a planning wall: lanes, notes, archived work, and activity only when needed.
          </p>
        </article>
        <article class="surface-card portfolio-note-card">
          <p class="section-label">Tone</p>
          <h2>Exclusive, calm, and useful before it is impressive.</h2>
          <p>
            The visual direction stays warm and restrained: soft whites, floating panels, strong typography, and enough motion to feel alive without making the tools feel jumpy.
          </p>
        </article>
      </section>

      <section class="portfolio-section reveal reveal-delay-2" id="principles">
        <div class="portfolio-section-head portfolio-section-head-inline">
          <div>
            <p class="section-label">Principles</p>
            <h2>What the product protects.</h2>
          </div>
          <p class="portfolio-section-note">The public page is simple because the private app is where the operational complexity belongs.</p>
        </div>
        <div class="portfolio-skill-grid">
          <div class="portfolio-skill-card">
            <span>Security</span>
            <strong>Role gates, CSRF-safe commands, typed confirmations, and no public leakage of internal tools.</strong>
          </div>
          <div class="portfolio-skill-card">
            <span>Readability</span>
            <strong>Frozen identifiers, compact summaries, clear status chips, and fewer always-on blocks.</strong>
          </div>
          <div class="portfolio-skill-card">
            <span>Continuity</span>
            <strong>Website and bot sync preserve roles, live status, hotel assignments, and hours snapshots.</strong>
          </div>
          <div class="portfolio-skill-card">
            <span>Trust</span>
            <strong>Deployment markers, cache-busting, no-store behavior, and live checks before claiming changes are live.</strong>
          </div>
        </div>
      </section>

      <section class="portfolio-contact reveal reveal-delay-2" id="access">
        <div>
          <p class="section-label">Access</p>
          <h2>The working system stays behind the gate.</h2>
          <p>Use the public page as the editorial face. Use Discord login for the private operations workspace.</p>
        </div>
        <div class="portfolio-contact-actions">
          <a class="button button-primary" href="/">Open Aavgo</a>
          <a class="button button-secondary" href="<?= $loginHref ?>">Discord login</a>
        </div>
      </section>

      <footer class="portfolio-footer reveal reveal-delay-2">
        <span>&copy; <span id="year"></span> Aavgo</span>
        <span>Minimal systems. Quiet details. Clear structure.</span>
      </footer>
    </main>
  </div>

  <script>
    window.AAVGO_LIVE_SIGNALS_ENDPOINT = '/api/live-signals/';
    (() => {
      const year = document.getElementById('year');
      if (year) {
        year.textContent = new Date().getFullYear();
      }

      const items = document.querySelectorAll('.reveal');
      if (!('IntersectionObserver' in window)) {
        items.forEach(item => item.classList.add('reveal-in'));
        return;
      }

      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('reveal-in');
          observer.unobserve(entry.target);
        });
      }, { threshold: 0.16 });

      items.forEach(item => observer.observe(item));
    })();
  </script>
</body>
</html>
