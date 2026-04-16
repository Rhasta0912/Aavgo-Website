<?php

declare(strict_types=1);

require __DIR__ . '/auth/bootstrap.php';

aavgo_cache_headers();

$stylesHref = htmlspecialchars(aavgo_asset_url('/styles.css'), ENT_QUOTES, 'UTF-8');
$scriptSrc = htmlspecialchars(aavgo_asset_url('/script.js'), ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aavgo | Private Access</title>
  <meta
    name="description"
    content="Aavgo is a private hospitality operations platform. Sign in with Discord to unlock the full website."
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
<body class="gateway-page-premium frontdoor-page">
  <div class="gateway-shell-premium gateway-shell-simple">
    <main class="gateway-main gateway-main-centered frontdoor-main">
      <section class="frontdoor-stage reveal reveal-in">
        <div class="login-stage-copy frontdoor-card">
          <div class="frontdoor-badge-row">
            <a class="brand brand-plain frontdoor-brand" href="/" aria-label="Aavgo home">Aavgo</a>
            <span class="dashboard-chip frontdoor-chip">Private Access</span>
          </div>

          <p class="eyebrow-light frontdoor-brow">Invitation-only front door</p>
          <h1 class="frontdoor-title">Log in</h1>
          <p class="hero-text frontdoor-copy">
            Continue with Discord to enter the private Aavgo workspace. Access is verified quietly, the door opens once, and the rest stays out of sight.
          </p>

          <div class="hero-actions hero-actions-centered frontdoor-actions">
            <a class="button button-primary frontdoor-button" href="/auth/discord/login/">Log in with Discord</a>
          </div>

          <p class="locked-note frontdoor-note">
            Invitation-only access for approved Aavgo members. No public browsing. No public workspace surface.
          </p>

          <div class="frontdoor-meta" aria-label="Access details">
            <div class="frontdoor-meta-card">
              <span>Private</span>
              <strong>Discrete by design</strong>
            </div>
            <div class="frontdoor-meta-card">
              <span>Verified</span>
              <strong>Discord opens the door</strong>
            </div>
            <div class="frontdoor-meta-card">
              <span>Aavgo</span>
              <strong>&copy; <span id="year"></span></strong>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script>
    window.AAVGO_LIVE_SIGNALS_ENDPOINT = '/api/live-signals/';
  </script>
  <script src="<?= $scriptSrc ?>"></script>
</body>
</html>
