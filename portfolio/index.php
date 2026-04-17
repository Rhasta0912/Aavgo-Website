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
    content="A minimalist portfolio for Cedric and Aavgo, focused on private systems, product design, and clean operations."
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
        <a href="#work">Work</a>
        <a href="#about">About</a>
        <a href="#skills">Skills</a>
        <a href="#contact">Contact</a>
      </nav>
      <a class="button button-secondary portfolio-nav-cta" href="<?= $loginHref ?>">Private login</a>
    </header>

    <main class="portfolio-main">
      <section class="portfolio-hero reveal">
        <div class="portfolio-hero-copy">
          <p class="section-label portfolio-kicker">Portfolio / Product / Systems</p>
          <h1 class="portfolio-title">Minimal interfaces for systems that need to feel calm, sharp, and premium.</h1>
          <p class="hero-text portfolio-copy">
            I design and build private workspaces, leadership dashboards, and role-aware flows that stay clean under pressure.
            The goal is always the same: reduce clutter, surface the right detail, and make the product feel easy to trust.
          </p>
          <div class="hero-actions portfolio-actions">
            <a class="button button-primary" href="#work">Selected work</a>
            <a class="button button-secondary" href="#about">How I work</a>
          </div>
        </div>

        <aside class="surface-card portfolio-hero-aside">
          <p class="section-label">At a glance</p>
          <div class="portfolio-aside-panel">
            <div>
              <span>Focus</span>
              <strong>Private tools, polished UX, and clean operations.</strong>
            </div>
            <div>
              <span>Style</span>
              <strong>Light, minimal, and quietly premium.</strong>
            </div>
            <div>
              <span>Approach</span>
              <strong>Structure first. Visual noise later.</strong>
            </div>
          </div>
        </aside>
      </section>

      <section class="portfolio-strip reveal reveal-delay-1" aria-label="Highlights">
        <article class="portfolio-strip-card">
          <span>01</span>
          <strong>Private dashboard systems</strong>
          <p>Discord-gated experiences with role checks, clean hierarchy, and calm UI.</p>
        </article>
        <article class="portfolio-strip-card">
          <span>02</span>
          <strong>Workflow clarity</strong>
          <p>Staff, hotel, and hours tools designed to stay readable while the pace stays high.</p>
        </article>
        <article class="portfolio-strip-card">
          <span>03</span>
          <strong>Premium restraint</strong>
          <p>Minimal layouts that still feel complete, intentional, and quietly impressive.</p>
        </article>
      </section>

      <section class="portfolio-section reveal reveal-delay-1" id="work">
        <div class="portfolio-section-head">
          <p class="section-label">Selected work</p>
          <h2>Built to feel composed, even when the data is busy.</h2>
        </div>
        <div class="portfolio-work-grid">
          <article class="panel-card portfolio-work-card">
            <p class="portfolio-work-meta">Aavgo / Access</p>
            <h3>Discord-gated private workspace</h3>
            <p>Created a private entry flow that keeps the front door simple while hiding complex role logic behind the scenes.</p>
            <div class="portfolio-chip-row">
              <span class="dashboard-chip">PHP</span>
              <span class="dashboard-chip">Auth</span>
              <span class="dashboard-chip">Role checks</span>
            </div>
          </article>
          <article class="panel-card portfolio-work-card">
            <p class="portfolio-work-meta">Aavgo / Leadership</p>
            <h3>Live operations dashboard</h3>
            <p>Built a calm admin surface for live hours, command actions, and team visibility without crowding the page.</p>
            <div class="portfolio-chip-row">
              <span class="dashboard-chip">Dashboards</span>
              <span class="dashboard-chip">UX hierarchy</span>
              <span class="dashboard-chip">Data views</span>
            </div>
          </article>
          <article class="panel-card portfolio-work-card">
            <p class="portfolio-work-meta">Aavgo / Developer</p>
            <h3>Trello-style roadmap board</h3>
            <p>Designed a spacious task system with archives, activity, drag and drop, and a detail view that stays focused on notes.</p>
            <div class="portfolio-chip-row">
              <span class="dashboard-chip">Roadmaps</span>
              <span class="dashboard-chip">Drag & drop</span>
              <span class="dashboard-chip">Activity trail</span>
            </div>
          </article>
        </div>
      </section>

      <section class="portfolio-split reveal reveal-delay-2" id="about">
        <article class="surface-card portfolio-note-card">
          <p class="section-label">About</p>
          <h2>Clear systems, quiet confidence, and interfaces that do not fight the user.</h2>
          <p>
            I like building websites that feel lighter than the work underneath them. The surface should stay calm,
            the structure should stay obvious, and the important action should always be easy to find.
          </p>
        </article>
        <article class="surface-card portfolio-note-card">
          <p class="section-label">Method</p>
          <h2>Start with structure, then trim until the page feels obvious.</h2>
          <p>
            I usually work from hierarchy first, then spacing, then motion. If a screen feels crowded, I try to replace
            noise with one useful thing instead of three decorative ones.
          </p>
        </article>
      </section>

      <section class="portfolio-section reveal reveal-delay-2" id="skills">
        <div class="portfolio-section-head portfolio-section-head-inline">
          <div>
            <p class="section-label">Skills</p>
            <h2>Tools and strengths.</h2>
          </div>
          <p class="portfolio-section-note">A compact stack for product work, internal tools, and polished dashboards.</p>
        </div>
        <div class="portfolio-skill-grid">
          <div class="portfolio-skill-card">
            <span>Design</span>
            <strong>Minimal layout systems, spacing, motion, and typographic hierarchy.</strong>
          </div>
          <div class="portfolio-skill-card">
            <span>Build</span>
            <strong>PHP, JavaScript, CSS, and server-aware UI flows.</strong>
          </div>
          <div class="portfolio-skill-card">
            <span>Integrate</span>
            <strong>Role sync, Discord flows, APIs, and operational dashboards.</strong>
          </div>
          <div class="portfolio-skill-card">
            <span>Polish</span>
            <strong>Filtering, animation, responsive behavior, and clean handoff details.</strong>
          </div>
        </div>
      </section>

      <section class="portfolio-contact reveal reveal-delay-2" id="contact">
        <div>
          <p class="section-label">Contact</p>
          <h2>Want to see the private workspace?</h2>
          <p>Open the Aavgo login gate to reach the internal tools, or use the portfolio as the public face.</p>
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
