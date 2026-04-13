<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

$currentUser = aavgo_current_user();
if ($currentUser !== null) {
    $afterLogin = (string) ($_SESSION['aavgo_after_login'] ?? '');
    unset($_SESSION['aavgo_after_login']);

    aavgo_redirect(aavgo_resolve_after_login_path($currentUser, $afterLogin));
}

if (!aavgo_is_fully_configured()) {
    http_response_code(500);
    aavgo_render_message_page(
        'Discord login is not configured yet.',
        'The website is missing its private Discord auth setup or allowed role mapping on the server. Update the server-only config, then try again.',
        'Back to Home',
        '/'
    );
    exit;
}

$afterLogin = aavgo_normalize_after_login_path((string) ($_SESSION['aavgo_after_login'] ?? ''));
$callbackUrl = aavgo_get_callback_url();
$flow = (!isset($_GET['direct']) || (string) $_GET['direct'] !== '1') ? 'bridge' : 'direct';
$state = aavgo_create_oauth_state($afterLogin, $callbackUrl, $flow);
$_SESSION['discord_oauth_state'] = $state;
aavgo_store_oauth_state_cookie($state);

$preferBrowser = $flow === 'bridge';
$authorizeUrl = aavgo_login_url($preferBrowser, $callbackUrl);

if ($flow === 'direct') {
    aavgo_redirect($authorizeUrl);
}

$safeAuthorizeUrl = htmlspecialchars($authorizeUrl, ENT_QUOTES, 'UTF-8');
$safeState = htmlspecialchars($state, ENT_QUOTES, 'UTF-8');
$safePollUrl = htmlspecialchars('/auth/discord/poll/?state=' . rawurlencode($state), ENT_QUOTES, 'UTF-8');
$safeClaimUrl = htmlspecialchars('/auth/discord/claim/?state=' . rawurlencode($state), ENT_QUOTES, 'UTF-8');
$jsAuthorizeUrl = json_encode($authorizeUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsPollUrl = json_encode('/auth/discord/poll/?state=' . rawurlencode($state), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsClaimUrl = json_encode('/auth/discord/claim/?state=' . rawurlencode($state), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Discord Sign-In</title>
  <meta name="robots" content="noindex,nofollow,noarchive">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="workspace-page workspace-page-access">
  <main class="workspace-message-shell">
    <section class="workspace-message-card reveal reveal-in">
      <div class="workspace-message-main">
        <p class="dashboard-kicker">Secure browser handoff</p>
        <h1 class="workspace-message-title">Continue with Discord</h1>
        <p class="workspace-message-copy">
          Keep this page open. Aavgo will open Discord in a separate window, then this tab will finish the private sign-in as soon as Discord approves it.
        </p>
        <div class="workspace-message-actions">
          <a class="button button-primary" href="{$safeAuthorizeUrl}" target="aavgoDiscordAuth" rel="noopener">Open Discord</a>
          <a class="button button-secondary" href="/auth/discord/login/?direct=1">Direct OAuth</a>
          <a class="button button-secondary" href="/">Back Home</a>
        </div>
        <section class="workspace-message-diagnostics">
          <p class="dashboard-kicker">Handoff status</p>
          <ul class="workspace-message-diagnostics-list">
            <li><span>State</span><strong id="aavgo-auth-state">{$safeState}</strong></li>
            <li><span>Status</span><strong id="aavgo-auth-status">Opening Discord...</strong></li>
            <li id="aavgo-auth-stage-row" hidden><span>Stage</span><strong id="aavgo-auth-stage"></strong></li>
            <li id="aavgo-auth-detail-row" hidden><span>Detail</span><strong id="aavgo-auth-detail"></strong></li>
          </ul>
        </section>
      </div>
      <aside class="workspace-message-aside reveal reveal-delay-1 reveal-in">
        <a class="dashboard-brand" href="/" aria-label="Aavgo home">Aavgo</a>
        <span class="dashboard-chip dashboard-chip-accent">Private front door</span>
        <strong>Keep this tab open.</strong>
        <p>Discord can finish in another window or the app. This page keeps polling the website so the final session can still land in the original browser.</p>
      </aside>
    </section>
  </main>
  <script>
    (() => {
      const authorizeUrl = {$jsAuthorizeUrl};
      const pollUrl = {$jsPollUrl};
      const claimUrl = {$jsClaimUrl};
      const statusNode = document.getElementById('aavgo-auth-status');
      const stageRow = document.getElementById('aavgo-auth-stage-row');
      const stageNode = document.getElementById('aavgo-auth-stage');
      const detailRow = document.getElementById('aavgo-auth-detail-row');
      const detailNode = document.getElementById('aavgo-auth-detail');

      const setStatus = message => {
        if (statusNode) statusNode.textContent = message;
      };

      const setFailure = (stage, detail) => {
        setStatus('Discord handoff failed.');
        if (stageRow && stageNode) {
          stageNode.textContent = stage || 'unknown';
          stageRow.hidden = false;
        }
        if (detailRow && detailNode) {
          detailNode.textContent = detail || 'No extra details returned.';
          detailRow.hidden = false;
        }
      };

      try {
        const popup = window.open(authorizeUrl, 'aavgoDiscordAuth', 'popup=yes,width=560,height=760,resizable=yes,scrollbars=yes');
        if (!popup) {
          setStatus('Popup blocked. Use Open Discord or Direct OAuth.');
        } else {
          setStatus('Waiting for Discord approval...');
        }
      } catch (_) {
        setStatus('Could not open Discord automatically. Use Open Discord.');
      }

      const poll = async () => {
        try {
          const response = await fetch(pollUrl, { credentials: 'same-origin', cache: 'no-store' });
          const payload = await response.json();
          if (payload && payload.ready) {
            setStatus('Approved. Finishing sign-in...');
            window.location.replace(claimUrl);
            return;
          }
          if (payload && payload.failed) {
            setFailure(payload.stage, payload.detail);
            return;
          }
        } catch (_) {
          setStatus('Waiting for Discord approval...');
        }

        window.setTimeout(poll, 1000);
      };

      window.addEventListener('message', event => {
        if (event.origin !== window.location.origin) return;
        if (event.data && event.data.type === 'aavgo-auth-ready') {
          setStatus('Approved. Finishing sign-in...');
          window.location.replace(claimUrl);
        }
      });

      window.setTimeout(poll, 800);
    })();
  </script>
  <script src="/script.js"></script>
</body>
</html>
HTML;
