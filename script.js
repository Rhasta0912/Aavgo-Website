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

function normalizeForSearch(value) {
  return String(value ?? "").trim().toLowerCase();
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

function aggregateHoursSummary(people) {
  return (Array.isArray(people) ? people : []).reduce((acc, person) => {
    acc.totalPeople += 1;
    acc.activeNow += person?.activeNow ? 1 : 0;
    acc.todayHours += Number(person?.todayHours || 0);
    acc.weeklyHours += Number(person?.weeklyHours || 0);
    acc.monthlyHours += Number(person?.monthlyHours || 0);
    return acc;
  }, {
    totalPeople: 0,
    activeNow: 0,
    todayHours: 0,
    weeklyHours: 0,
    monthlyHours: 0
  });
}

function deriveTeamCards(people) {
  const buckets = new Map();

  (Array.isArray(people) ? people : []).forEach(person => {
    const teamName = String(person?.team || "Unassigned");
    const current = buckets.get(teamName) || {
      name: teamName,
      people: 0,
      activeNow: 0,
      todayHours: 0,
      weeklyHours: 0
    };

    current.people += 1;
    current.activeNow += person?.activeNow ? 1 : 0;
    current.todayHours += Number(person?.todayHours || 0);
    current.weeklyHours += Number(person?.weeklyHours || 0);
    buckets.set(teamName, current);
  });

  return [...buckets.values()].sort((left, right) => left.name.localeCompare(right.name));
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

function syncHoursFilterOptions(selectId, values, placeholder) {
  const select = document.getElementById(selectId);
  if (!select) return;

  const previousValue = select.value;
  const normalizedValues = [...new Set((Array.isArray(values) ? values : [])
    .map(value => String(value ?? "").trim())
    .filter(Boolean))]
    .sort((left, right) => left.localeCompare(right));

  select.innerHTML = [
    `<option value="">${escapeHtml(placeholder)}</option>`,
    ...normalizedValues.map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`)
  ].join("");

  if (previousValue && normalizedValues.includes(previousValue)) {
    select.value = previousValue;
  }
}

function getHoursFilters() {
  return {
    search: normalizeForSearch(document.getElementById("hours-filter-search")?.value || ""),
    role: String(document.getElementById("hours-filter-role")?.value || "").trim(),
    team: String(document.getElementById("hours-filter-team")?.value || "").trim(),
    hotel: String(document.getElementById("hours-filter-hotel")?.value || "").trim(),
    status: String(document.getElementById("hours-filter-status")?.value || "").trim()
  };
}

function filterHoursPeople(people) {
  const filters = getHoursFilters();

  return (Array.isArray(people) ? people : []).filter(person => {
    const displayName = normalizeForSearch(person?.displayName || person?.username || "");
    const route = normalizeForSearch(person?.route || "");
    const role = String(person?.role || "").trim();
    const team = String(person?.team || "").trim();
    const hotel = String(person?.linkedHotel || "").trim();
    const status = person?.activeNow ? "active" : "offline";

    if (filters.search && !displayName.includes(filters.search) && !route.includes(filters.search)) {
      return false;
    }

    if (filters.role && role !== filters.role) {
      return false;
    }

    if (filters.team && team !== filters.team) {
      return false;
    }

    if (filters.hotel && hotel !== filters.hotel) {
      return false;
    }

    if (filters.status && status !== filters.status) {
      return false;
    }

    return true;
  });
}

function applyAdminHoursPayload(payload) {
  renderHoursNotice(payload);

  if (!payload?.ok || !payload?.data) {
    setText("hours-summary-total", "0");
    setText("hours-summary-active", "0");
    setText("hours-summary-today", "0h");
    setText("hours-summary-weekly", "0h");
    setText("hours-summary-monthly", "0h");
    setText("hours-sync-label", "Waiting for live sync");
    renderHoursRows([]);
    renderTeamCards([]);
    return;
  }

  const allPeople = Array.isArray(payload.data.people) ? payload.data.people : [];
  syncHoursFilterOptions("hours-filter-role", allPeople.map(person => person?.role), "All roles");
  syncHoursFilterOptions("hours-filter-team", allPeople.map(person => person?.team), "All teams");
  syncHoursFilterOptions("hours-filter-hotel", allPeople.map(person => person?.linkedHotel), "All hotels");

  const people = filterHoursPeople(allPeople);
  const summary = aggregateHoursSummary(people);
  const teams = deriveTeamCards(people);

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

let currentAdminHoursPayload = getAdminBootstrapPayload();

function reapplyCurrentAdminHoursPayload() {
  applyAdminHoursPayload(currentAdminHoursPayload);
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

    if (payload?.ok && payload?.data) {
      currentAdminHoursPayload = payload;
      applyAdminHoursPayload(currentAdminHoursPayload);
      return;
    }

    if (currentAdminHoursPayload?.ok) {
      renderHoursNotice(payload);
      return;
    }

    currentAdminHoursPayload = payload;
    applyAdminHoursPayload(currentAdminHoursPayload);
  } catch (_) {
    const payload = {
      ok: false,
      error: "The live hours board could not refresh right now."
    };

    if (currentAdminHoursPayload?.ok) {
      renderHoursNotice(payload);
      return;
    }

    currentAdminHoursPayload = payload;
    applyAdminHoursPayload(currentAdminHoursPayload);
  }
}

if (document.getElementById("hours-board-rows")) {
  reapplyCurrentAdminHoursPayload();
  window.setInterval(refreshAdminHoursBoard, 30000);

  ["hours-filter-search", "hours-filter-role", "hours-filter-team", "hours-filter-hotel", "hours-filter-status"]
    .forEach(filterId => {
      const node = document.getElementById(filterId);
      if (!node) return;

      node.addEventListener("input", reapplyCurrentAdminHoursPayload);
      node.addEventListener("change", reapplyCurrentAdminHoursPayload);
    });

  const resetButton = document.getElementById("hours-filter-reset");
  if (resetButton) {
    resetButton.addEventListener("click", () => {
      ["hours-filter-search", "hours-filter-role", "hours-filter-team", "hours-filter-hotel", "hours-filter-status"]
        .forEach(filterId => {
          const node = document.getElementById(filterId);
          if (!node) return;
          node.value = "";
        });
      reapplyCurrentAdminHoursPayload();
    });
  }
}
