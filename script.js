const revealItems = document.querySelectorAll(".reveal");

if ("IntersectionObserver" in window) {
  const revealObserver = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("reveal-in");
        revealObserver.unobserve(entry.target);
      });
    },
    { threshold: 0.16 }
  );

  revealItems.forEach(item => revealObserver.observe(item));
} else {
  revealItems.forEach(item => item.classList.add("reveal-in"));
}

const yearNode = document.getElementById("year");
if (yearNode) {
  yearNode.textContent = new Date().getFullYear();
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function formatHours(value) {
  const number = Number(value ?? 0);
  if (!Number.isFinite(number)) return "0h";
  const oneDecimal = Math.round(number * 10) / 10;
  return `${String(oneDecimal).replace(/\.0$/, "")}h`;
}

function formatSyncLabel(value) {
  if (!value) return "Waiting for live sync";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Waiting for live sync";
  return `${date.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric"
  })} - ${date.toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit"
  })}`;
}

function setText(id, value) {
  const node = document.getElementById(id);
  if (node) node.textContent = value;
}

function renderHoursNotice(payload) {
  const notice = document.getElementById("hours-board-notice");
  if (!notice) return;

  if (payload?.ok) {
    notice.innerHTML = "";
    return;
  }

  const message = escapeHtml(payload?.error || "The live hours bridge is unavailable right now.");
  notice.innerHTML = `
    <div class="dashboard-inline-notice">
      <strong>Live hours are not connected yet.</strong>
      <p>${message}</p>
    </div>
  `;
}

function renderHoursRows(people) {
  const tableBody = document.getElementById("hours-board-rows");
  if (!tableBody) return;

  if (!Array.isArray(people) || people.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="9">
          <div class="dashboard-empty-state">
            <strong>No live hour rows yet.</strong>
            <p>Once the hours bridge responds, every actual staff row will appear here.</p>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  tableBody.innerHTML = people.map(person => {
    const activeNow = Boolean(person?.activeNow);
    const activeSession = person?.activeSession || null;
    const activeLabel = activeNow && activeSession
      ? `${escapeHtml(activeSession.kind || "Live Shift")} - ${formatHours(activeSession.elapsedHours)}`
      : "Offline";

    return `
      <tr class="${activeNow ? "is-live" : ""}">
        <td>
          <div class="dashboard-staff-cell">
            <strong>${escapeHtml(person?.displayName || "Unknown")}</strong>
            <span>${escapeHtml(person?.route || "/user")}</span>
          </div>
        </td>
        <td>${escapeHtml(person?.role || "Agent")}</td>
        <td>${escapeHtml(person?.team || "Unassigned")}</td>
        <td>${escapeHtml(person?.linkedHotel || "Unassigned")}</td>
        <td>
          <span class="dashboard-status-pill ${activeNow ? "is-live" : "is-idle"}">
            ${activeLabel}
          </span>
        </td>
        <td>${formatHours(person?.todayHours)}</td>
        <td>${formatHours(person?.weeklyHours)}</td>
        <td>${formatHours(person?.monthlyHours)}</td>
        <td>${formatHours(person?.allHours)}</td>
      </tr>
    `;
  }).join("");
}

function renderTeamCards(teams) {
  const container = document.getElementById("hours-team-cards");
  if (!container) return;

  if (!Array.isArray(teams) || teams.length === 0) {
    container.innerHTML = `
      <div class="dashboard-mini-card">
        <strong>Waiting for data</strong>
        <p>The team breakdown appears here as soon as the bridge returns live data.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = teams.map(team => `
    <div class="dashboard-mini-card">
      <strong>${escapeHtml(team?.name || "Unassigned")}</strong>
      <p>${escapeHtml(team?.people ?? 0)} people - ${escapeHtml(team?.activeNow ?? 0)} active</p>
      <span>Today: ${formatHours(team?.todayHours)}</span>
      <span>Week: ${formatHours(team?.weeklyHours)}</span>
    </div>
  `).join("");
}

function applyAdminHoursPayload(payload) {
  renderHoursNotice(payload);

  if (!payload?.ok || !payload?.data) {
    renderHoursRows([]);
    renderTeamCards([]);
    return;
  }

  const summary = payload.data.summary || {};
  const people = Array.isArray(payload.data.people) ? payload.data.people : [];
  const teams = Array.isArray(payload.data.teams) ? payload.data.teams : [];

  setText("hours-summary-total", String(summary.totalPeople ?? 0));
  setText("hours-summary-active", String(summary.activeNow ?? 0));
  setText("hours-summary-today", formatHours(summary.todayHours));
  setText("hours-summary-weekly", formatHours(summary.weeklyHours));
  setText("hours-summary-monthly", formatHours(summary.monthlyHours));
  setText("hours-sync-label", formatSyncLabel(payload.data.generatedAt));

  renderHoursRows(people);
  renderTeamCards(teams);
}

function getAdminBootstrapPayload() {
  const node = document.getElementById("admin-hours-bootstrap");
  if (!node) return null;

  try {
    return JSON.parse(node.textContent || "{}");
  } catch (_) {
    return null;
  }
}

async function refreshAdminHoursBoard() {
  if (!window.AAVGO_ADMIN_HOURS_ENDPOINT) return;

  try {
    const response = await fetch(window.AAVGO_ADMIN_HOURS_ENDPOINT, {
      credentials: "same-origin",
      headers: {
        Accept: "application/json"
      }
    });

    const payload = await response.json().catch(() => ({
      ok: false,
      error: "The live hours board returned an unreadable response."
    }));

    applyAdminHoursPayload(payload);
  } catch (_) {
    applyAdminHoursPayload({
      ok: false,
      error: "The live hours board could not refresh right now."
    });
  }
}

if (document.getElementById("hours-board-rows")) {
  applyAdminHoursPayload(getAdminBootstrapPayload());
  window.setInterval(refreshAdminHoursBoard, 30000);
}
