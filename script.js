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

function formatHoursValue(value) {
  const number = Number(value ?? 0);
  if (!Number.isFinite(number)) return "0";
  const rounded = Math.round(number * 10) / 10;
  return String(rounded).replace(/\.0$/, "");
}

function formatHours(value) {
  return `${formatHoursValue(value)}h`;
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

function formatAuditLabel(value) {
  if (!value) return "Just now";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Just now";
  return `${date.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric"
  })} ${date.toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit"
  })}`;
}

function safeLocalStorageGet(key) {
  try {
    return window.localStorage.getItem(key) || "";
  } catch (_) {
    return "";
  }
}

function safeLocalStorageSet(key, value) {
  try {
    window.localStorage.setItem(key, value);
  } catch (_) {
    // Ignore storage failures on restricted browsers.
  }
}

function setText(id, value) {
  const node = document.getElementById(id);
  if (node) {
    node.textContent = value;
  }
}

function setHtml(id, html) {
  const node = document.getElementById(id);
  if (node) {
    node.innerHTML = html;
  }
}

function parseJsonScript(id) {
  const node = document.getElementById(id);
  if (!node) return null;

  try {
    return JSON.parse(node.textContent || "{}");
  } catch (_) {
    return null;
  }
}

function normalizeAnnouncement(announcement) {
  if (!announcement || typeof announcement !== "object") return null;

  const id = String(announcement.id || "").trim();
  const message = String(announcement.message || "").trim();
  if (!id || !message) return null;

  const actor = announcement.actor && typeof announcement.actor === "object" ? announcement.actor : {};
  return {
    id,
    message,
    tone: String(announcement.tone || "standard").trim() === "urgent" ? "urgent" : "standard",
    createdAt: String(announcement.createdAt || ""),
    updatedAt: String(announcement.updatedAt || announcement.createdAt || ""),
    actorName: String(actor.name || "Leadership"),
    actorRole: String(actor.roleSummary || "Leadership")
  };
}

const liveSignalState = {
  endpoint: String(window.AAVGO_LIVE_SIGNALS_ENDPOINT || "/api/live-signals/"),
  pollTimer: null,
  banner: null,
  message: null,
  meta: null,
  chip: null,
  dismiss: null,
  audioUnlocked: false,
  audioContext: null,
  pendingTone: "",
  currentAnnouncementId: "",
  lastPlayedId: safeLocalStorageGet("aavgo:last-live-signal-id")
};

function ensureLiveSignalBanner() {
  if (liveSignalState.banner) {
    return liveSignalState.banner;
  }

  const banner = document.createElement("aside");
  banner.className = "live-signal-banner is-hidden";
  banner.innerHTML = `
    <div class="live-signal-shell">
      <span class="live-signal-chip">Leadership alert</span>
      <div class="live-signal-copy">
        <strong class="live-signal-message"></strong>
        <p class="live-signal-meta"></p>
      </div>
      <button class="live-signal-dismiss" type="button" aria-label="Dismiss alert">Hide</button>
    </div>
  `;

  document.body.appendChild(banner);

  liveSignalState.banner = banner;
  liveSignalState.message = banner.querySelector(".live-signal-message");
  liveSignalState.meta = banner.querySelector(".live-signal-meta");
  liveSignalState.chip = banner.querySelector(".live-signal-chip");
  liveSignalState.dismiss = banner.querySelector(".live-signal-dismiss");
  liveSignalState.dismiss?.addEventListener("click", () => {
    banner.classList.add("is-hidden");
  });

  return banner;
}

function unlockAnnouncementAudio() {
  if (liveSignalState.audioUnlocked) {
    if (liveSignalState.audioContext?.state === "suspended") {
      liveSignalState.audioContext.resume().catch(() => {});
    }
    return;
  }

  try {
    const Context = window.AudioContext || window.webkitAudioContext;
    if (!Context) {
      liveSignalState.audioUnlocked = true;
      liveSignalState.pendingTone = "";
      return;
    }

    liveSignalState.audioContext = liveSignalState.audioContext || new Context();
    liveSignalState.audioContext.resume().catch(() => {});
    liveSignalState.audioUnlocked = true;

    if (liveSignalState.pendingTone) {
      playAnnouncementTone(liveSignalState.pendingTone);
      liveSignalState.pendingTone = "";
    }
  } catch (_) {
    liveSignalState.audioUnlocked = true;
    liveSignalState.pendingTone = "";
  }
}

function playSingleBeep(startAt, duration, frequency) {
  const audioContext = liveSignalState.audioContext;
  if (!audioContext) return;

  const oscillator = audioContext.createOscillator();
  const gainNode = audioContext.createGain();
  oscillator.type = "sine";
  oscillator.frequency.setValueAtTime(frequency, startAt);

  gainNode.gain.setValueAtTime(0.0001, startAt);
  gainNode.gain.exponentialRampToValueAtTime(0.12, startAt + 0.02);
  gainNode.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

  oscillator.connect(gainNode);
  gainNode.connect(audioContext.destination);
  oscillator.start(startAt);
  oscillator.stop(startAt + duration + 0.02);
}

function playAnnouncementTone(tone = "standard") {
  if (!liveSignalState.audioUnlocked) {
    liveSignalState.pendingTone = tone;
    return;
  }

  unlockAnnouncementAudio();
  const audioContext = liveSignalState.audioContext;
  if (!audioContext) return;

  const startAt = audioContext.currentTime + 0.02;
  if (tone === "urgent") {
    playSingleBeep(startAt, 0.16, 880);
    playSingleBeep(startAt + 0.22, 0.18, 990);
    return;
  }

  playSingleBeep(startAt, 0.18, 784);
}

function renderLiveSignalBanner(announcement) {
  const banner = ensureLiveSignalBanner();
  const normalized = normalizeAnnouncement(announcement);

  if (!normalized) {
    liveSignalState.currentAnnouncementId = "";
    banner.classList.add("is-hidden");
    banner.classList.remove("is-urgent");
    return;
  }

  liveSignalState.currentAnnouncementId = normalized.id;
  banner.classList.remove("is-hidden");
  banner.classList.toggle("is-urgent", normalized.tone === "urgent");
  if (liveSignalState.message) {
    liveSignalState.message.textContent = normalized.message;
  }
  if (liveSignalState.meta) {
    const parts = [normalized.actorName];
    if (normalized.createdAt) {
      parts.push(formatAuditLabel(normalized.createdAt));
    }
    liveSignalState.meta.textContent = parts.join(" - ");
  }
  if (liveSignalState.chip) {
    liveSignalState.chip.textContent = normalized.tone === "urgent" ? "Urgent broadcast" : "Leadership alert";
  }

  if (normalized.id !== liveSignalState.lastPlayedId) {
    liveSignalState.lastPlayedId = normalized.id;
    safeLocalStorageSet("aavgo:last-live-signal-id", normalized.id);
    playAnnouncementTone(normalized.tone);
  }
}

async function refreshLiveSignals() {
  if (!liveSignalState.endpoint) return;

  try {
    const response = await fetch(liveSignalState.endpoint, {
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    });

    const payload = await response.json().catch(() => ({ ok: false, announcement: null }));
    if (!response.ok || !payload?.ok) {
      renderLiveSignalBanner(null);
      return;
    }

    if (payload?.authenticated === false) {
      renderLiveSignalBanner(null);
      return;
    }

    renderLiveSignalBanner(payload?.announcement || null);
  } catch (_) {
    // Keep the last rendered state if polling fails.
  }
}

function initializeLiveSignals() {
  ["pointerdown", "keydown", "touchstart"].forEach(eventName => {
    window.addEventListener(eventName, unlockAnnouncementAudio, { passive: true });
  });

  refreshLiveSignals();
  liveSignalState.pollTimer = window.setInterval(refreshLiveSignals, 12000);
}

function getPrimaryHotelId(person) {
  return String(person?.linkedHotelId || "").trim();
}

function getPrimaryHotelLabel(person) {
  return String(person?.linkedHotel || "").trim() || "Unassigned";
}

function getRoleSummary(person) {
  const labels = Array.isArray(person?.roleLabels) ? person.roleLabels.filter(Boolean) : [];
  if (labels.length > 0) {
    return labels.join(" / ");
  }
  return String(person?.roleSummary || person?.role || "Agent");
}

function getStatusSummary(person) {
  if (person?.activeNow) {
    return `${String(person?.activeSession?.kind || "Live shift")} - ${formatHours(person?.activeSession?.elapsedHours)}`;
  }
  return String(person?.agentStatus || "Offline");
}

function getSearchHaystack(person) {
  return [
    person?.displayName,
    person?.username,
    person?.role,
    getRoleSummary(person),
    person?.team,
    person?.linkedHotel,
    person?.linkedHotelId,
    person?.agentStatus,
    person?.route,
    person?.activeNow ? "active" : "offline",
    person?.activeSession?.kind
  ]
    .map(normalizeForSearch)
    .join(" ");
}

function selectOptionsMarkup(options, placeholder, currentValue = "") {
  const items = Array.isArray(options) ? options : [];
  return [
    `<option value="">${escapeHtml(placeholder)}</option>`,
    ...items.map(option => {
      const value = typeof option === "string" ? option : option?.id;
      const label = typeof option === "string" ? option : option?.name ?? option?.label ?? option?.id;
      const selected = String(value ?? "") === String(currentValue ?? "") ? " selected" : "";
      return `<option value="${escapeHtml(value ?? "")}"${selected}>${escapeHtml(label ?? "")}</option>`;
    })
  ].join("");
}

function syncSelectOptions(selectId, options, placeholder, currentValue = "") {
  const select = document.getElementById(selectId);
  if (!select) return;
  select.innerHTML = selectOptionsMarkup(options, placeholder, currentValue);
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
      weeklyHours: 0,
      monthlyHours: 0
    };

    current.people += 1;
    current.activeNow += person?.activeNow ? 1 : 0;
    current.todayHours += Number(person?.todayHours || 0);
    current.weeklyHours += Number(person?.weeklyHours || 0);
    current.monthlyHours += Number(person?.monthlyHours || 0);
    buckets.set(teamName, current);
  });

  return [...buckets.values()].sort((left, right) => left.name.localeCompare(right.name));
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

function getAdminBoardBootstrap() {
  return window.__AAVGO_ADMIN_BOARD__ || parseJsonScript("admin-board-bootstrap") || null;
}

const adminBoardState = {
  payload: getAdminBoardBootstrap(),
  selectedDiscordId: "",
  refreshTimer: null,
  actionInFlight: false
};

function getAdminPeople() {
  return Array.isArray(adminBoardState?.payload?.data?.people) ? adminBoardState.payload.data.people : [];
}

function getAdminManagement() {
  return adminBoardState?.payload?.management || {};
}

function getAdminMeta() {
  const management = getAdminManagement();
  const managementMeta = management?.meta || {};
  const payloadMeta = adminBoardState?.payload?.data?.meta || {};
  return {
    hotels: Array.isArray(managementMeta.hotels) ? managementMeta.hotels : (Array.isArray(payloadMeta.hotels) ? payloadMeta.hotels : []),
    teams: Array.isArray(managementMeta.teams) ? managementMeta.teams : (Array.isArray(payloadMeta.teams) ? payloadMeta.teams : [])
  };
}

function getAdminFilters() {
  return {
    search: normalizeForSearch(document.getElementById("hours-filter-search")?.value || ""),
    role: String(document.getElementById("hours-filter-role")?.value || "").trim(),
    team: String(document.getElementById("hours-filter-team")?.value || "").trim(),
    hotel: String(document.getElementById("hours-filter-hotel")?.value || "").trim(),
    status: String(document.getElementById("hours-filter-status")?.value || "").trim()
  };
}

function filterAdminPeople(people) {
  const filters = getAdminFilters();

  return (Array.isArray(people) ? people : []).filter(person => {
    if (filters.search && !getSearchHaystack(person).includes(filters.search)) {
      return false;
    }

    if (filters.role && String(person?.role || "") !== filters.role) {
      return false;
    }

    if (filters.team && String(person?.team || "") !== filters.team) {
      return false;
    }

    if (filters.hotel) {
      const personHotelId = getPrimaryHotelId(person);
      const personHotelLabel = getPrimaryHotelLabel(person);
      if (filters.hotel !== personHotelId && filters.hotel !== personHotelLabel) {
        return false;
      }
    }

    if (filters.status) {
      const status = person?.activeNow ? "active" : "offline";
      if (status !== filters.status) {
        return false;
      }
    }

    return true;
  });
}

function getSelectedStaff(allPeople, visiblePeople) {
  const selectedVisible = visiblePeople.find(
    person => String(person?.discordId || "") === String(adminBoardState.selectedDiscordId || "")
  );
  if (selectedVisible) {
    return selectedVisible;
  }

  const selectedAny = allPeople.find(
    person => String(person?.discordId || "") === String(adminBoardState.selectedDiscordId || "")
  );
  if (selectedAny && visiblePeople.length === 0) {
    return selectedAny;
  }

  const nextStaff = visiblePeople[0] || allPeople[0] || null;
  adminBoardState.selectedDiscordId = String(nextStaff?.discordId || "");
  return nextStaff;
}

function setActionFeedback(message, isError = false) {
  const node = document.getElementById("hours-action-feedback");
  if (!node) return;
  node.textContent = message;
  node.classList.toggle("is-error", Boolean(isError));
  node.classList.toggle("is-success", !isError && message !== "");
}

function setActionControlsDisabled(disabled) {
  [
    "broadcast-message",
    "broadcast-tone-select",
    "broadcast-send",
    "broadcast-clear",
    "hours-action-team-select",
    "hours-action-team-submit",
    "hours-action-hotel-select",
    "hours-action-hotel-submit",
    "hours-action-logout-submit",
    "hours-hotel-force-select",
    "hours-hotel-force-submit",
    "developer-sync-all",
    "developer-push-snapshot"
  ].forEach(id => {
    const node = document.getElementById(id);
    if (node) {
      node.disabled = disabled;
    }
  });
}

function setBroadcastFeedback(message, isError = false) {
  const node = document.getElementById("broadcast-feedback");
  if (!node) return;
  node.textContent = message;
  node.classList.toggle("is-error", Boolean(isError));
  node.classList.toggle("is-success", !isError && message !== "");
}

function renderBroadcastComposer(announcement) {
  const status = document.getElementById("broadcast-live-status");
  const preview = document.getElementById("broadcast-preview");
  const clearButton = document.getElementById("broadcast-clear");
  const toneSelect = document.getElementById("broadcast-tone-select");
  const textarea = document.getElementById("broadcast-message");
  const normalized = normalizeAnnouncement(announcement);

  if (status) {
    status.textContent = normalized
      ? normalized.tone === "urgent" ? "Urgent live" : "Live alert"
      : "No live alert";
    status.classList.toggle("dashboard-chip-accent", Boolean(normalized));
  }

  if (clearButton) {
    clearButton.disabled = adminBoardState.actionInFlight || !normalized;
  }

  if (!preview) return;

  if (!normalized) {
    preview.innerHTML = `
      <strong>No live announcement yet.</strong>
      <p>Once sent, the active alert will show here and across signed-in pages with a website beep.</p>
    `;
    if (toneSelect && !adminBoardState.actionInFlight) {
      toneSelect.value = "standard";
    }
    return;
  }

  preview.innerHTML = `
    <span class="dashboard-chip ${normalized.tone === "urgent" ? "dashboard-chip-accent" : ""}">
      ${escapeHtml(normalized.tone === "urgent" ? "Urgent" : "Standard")}
    </span>
    <strong>${escapeHtml(normalized.message)}</strong>
    <p>${escapeHtml(normalized.actorName)} · ${escapeHtml(formatAuditLabel(normalized.createdAt))}</p>
  `;

  if (toneSelect && !adminBoardState.actionInFlight) {
    toneSelect.value = normalized.tone;
  }

  if (textarea && !adminBoardState.actionInFlight && textarea.value.trim() === "") {
    textarea.value = normalized.message;
  }

  renderLiveSignalBanner(normalized);
}

function renderHoursNotice(payload) {
  const notice = document.getElementById("hours-board-notice");
  if (!notice) return;

  if (payload?.ok || Array.isArray(payload?.data?.people)) {
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

function renderHoursRows(people, selectedDiscordId) {
  const tableBody = document.getElementById("hours-board-rows");
  if (!tableBody) return;

  if (!Array.isArray(people) || people.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="9">
          <div class="dashboard-empty-state">
            <strong>No rows match the current lane.</strong>
            <p>Widen the filters or wait for the next bot snapshot.</p>
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
      : escapeHtml(person?.agentStatus || "Offline");
    const isSelected = String(person?.discordId || "") === String(selectedDiscordId || "");
    const roleSummary = getRoleSummary(person);
    const roleSecondary = roleSummary !== String(person?.role || "") ? roleSummary : "";

    return `
      <tr class="${activeNow ? "is-live" : ""} ${isSelected ? "is-selected" : ""}" data-discord-id="${escapeHtml(person?.discordId || "")}">
        <td>
          <div class="dashboard-staff-cell">
            <strong>${escapeHtml(person?.displayName || "Unknown")}</strong>
            <span>${escapeHtml(person?.username || "")}</span>
          </div>
        </td>
        <td>
          <div class="dashboard-inline-stack">
            <strong>${escapeHtml(person?.role || "Agent")}</strong>
            ${roleSecondary ? `<span>${escapeHtml(roleSecondary)}</span>` : ""}
          </div>
        </td>
        <td>${escapeHtml(person?.team || "Unassigned")}</td>
        <td>${escapeHtml(getPrimaryHotelLabel(person))}</td>
        <td>${activeLabel}</td>
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
      <div class="dashboard-empty-state">
        <strong>Waiting for filtered totals.</strong>
        <p>Once rows are visible in the lane, grouped totals will appear here.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = teams.map(team => `
    <article class="dashboard-mini-card">
      <div class="dashboard-mini-card-top">
        <strong>${escapeHtml(team?.name || "Unassigned")}</strong>
        <span>${escapeHtml(String(team?.people ?? 0))} tracked</span>
      </div>
      <p>${escapeHtml(String(team?.activeNow ?? 0))} active now</p>
      <div class="dashboard-mini-metrics">
        <span>Today <strong>${formatHours(team?.todayHours)}</strong></span>
        <span>Week <strong>${formatHours(team?.weeklyHours)}</strong></span>
        <span>Month <strong>${formatHours(team?.monthlyHours)}</strong></span>
      </div>
    </article>
  `).join("");
}

function renderAuditLog(entries) {
  const container = document.getElementById("hours-audit-log");
  if (!container) return;

  if (!Array.isArray(entries) || entries.length === 0) {
    container.innerHTML = `
      <div class="dashboard-empty-state">
        <strong>No audit entries yet.</strong>
        <p>Queued and completed leadership actions will appear here.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = entries.map(entry => {
    const status = String(entry?.status || "queued");
    const actorName = entry?.actor?.name ? ` by ${entry.actor.name}` : "";
    return `
      <article class="dashboard-audit-item">
        <div class="dashboard-audit-topline">
          <span class="dashboard-chip ${status === "failed" ? "dashboard-chip-muted" : "dashboard-chip-accent"}">${escapeHtml(status)}</span>
          <span>${escapeHtml(formatAuditLabel(entry?.createdAt))}</span>
        </div>
        <strong>${escapeHtml(entry?.label || "Leadership action")}</strong>
        <p>${escapeHtml((entry?.target || "General workspace action") + actorName)}</p>
        <span>${escapeHtml(entry?.message || "Queued.")}</span>
      </article>
    `;
  }).join("");
}

function renderPeriodDays(days) {
  const items = (Array.isArray(days) ? days : [])
    .filter(day => Number(day?.totalHours || 0) > 0)
    .slice(0, 4);

  if (items.length === 0) {
    return '<p class="dashboard-period-copy">No tracked days yet in this cut.</p>';
  }

  return `
    <ul class="dashboard-inline-list">
      ${items.map(day => `
        <li>
          <span>Day ${escapeHtml(day?.day ?? "-")}</span>
          <strong>${formatHours(day?.totalHours)}</strong>
        </li>
      `).join("")}
    </ul>
  `;
}

function renderSelectedPeriods(person) {
  const container = document.getElementById("hours-selected-periods");
  if (!container) return;

  const firstHalf = person?.payPeriods?.firstHalf || { label: "1st - 15th", totalHours: 0, days: [] };
  const secondHalf = person?.payPeriods?.secondHalf || { label: "16th - month end", totalHours: 0, days: [] };

  container.innerHTML = `
    <article class="dashboard-period-card">
      <span class="dashboard-chip">${escapeHtml(firstHalf?.label || "1st - 15th")}</span>
      <strong>${formatHours(firstHalf?.totalHours)}</strong>
      <p>First payroll cut for the selected staff member.</p>
      ${renderPeriodDays(firstHalf?.days)}
    </article>
    <article class="dashboard-period-card">
      <span class="dashboard-chip">${escapeHtml(secondHalf?.label || "16th - month end")}</span>
      <strong>${formatHours(secondHalf?.totalHours)}</strong>
      <p>Second payroll cut through the end of the month.</p>
      ${renderPeriodDays(secondHalf?.days)}
    </article>
  `;
}

function renderSelectedHistory(person) {
  const container = document.getElementById("hours-selected-history");
  if (!container) return;

  if (!person) {
    container.innerHTML = `
      <strong>Hour history</strong>
      <p>Select a staff row to load current-month activity and recent month totals.</p>
    `;
    return;
  }

  const monthDays = (Array.isArray(person?.currentMonth?.days) ? person.currentMonth.days : [])
    .filter(day => Number(day?.totalHours || 0) > 0)
    .slice(-6)
    .reverse();
  const recentMonths = Array.isArray(person?.recentMonths) ? person.recentMonths : [];

  container.innerHTML = `
    <div class="dashboard-history-head">
      <div>
        <strong>${escapeHtml(person?.currentMonth?.label || "Current month")}</strong>
        <p>Recent tracked days for ${escapeHtml(person?.displayName || "the selected staff member")}.</p>
      </div>
      <span class="dashboard-chip dashboard-chip-accent">${formatHours(person?.monthlyHours)}</span>
    </div>
    ${
      monthDays.length > 0
        ? `<ul class="dashboard-inline-list">
            ${monthDays.map(day => `
              <li>
                <span>Day ${escapeHtml(day?.day ?? "-")}</span>
                <strong>${formatHours(day?.totalHours)}</strong>
              </li>
            `).join("")}
          </ul>`
        : '<p class="dashboard-period-copy">No current-month day entries yet.</p>'
    }
    <div class="dashboard-history-month-grid">
      ${recentMonths.map(month => `
        <article class="dashboard-history-month-card">
          <span>${escapeHtml(month?.label || "Recent month")}</span>
          <strong>${formatHours(month?.totalHours)}</strong>
          <p>Shift ${formatHours(month?.shiftHours)} / Training ${formatHours(month?.trainingHours)}</p>
        </article>
      `).join("")}
    </div>
  `;
}

function renderSelectedStaff(person) {
  setText("hours-selected-name", person?.displayName || "Pick a staff row");
  setText("hours-selected-route", person?.route || "/user");
  setText("hours-selected-role-summary", person ? getRoleSummary(person) : "No staff selected yet");
  setText("hours-selected-hotel", person ? getPrimaryHotelLabel(person) : "Unavailable");
  setText("hours-selected-team", person?.team || "Unavailable");
  setText("hours-selected-status", person ? getStatusSummary(person) : "Unavailable");

  renderSelectedPeriods(person);
  renderSelectedHistory(person);

  if (!person) {
    setActionControlsDisabled(true);
    setActionFeedback("Select a staff member to unlock reassignment and logout controls.", false);
    return;
  }

  setActionControlsDisabled(adminBoardState.actionInFlight);
  setActionFeedback(`${person.displayName || person.username || "Staff member"} is ready for reassignment, forced logout, or hotel-level review.`, false);

  const meta = getAdminMeta();
  syncSelectOptions("hours-action-team-select", meta.teams || [], "Choose a team", person?.team || "");
  syncSelectOptions("hours-action-hotel-select", meta.hotels || [], "Choose a hotel", getPrimaryHotelId(person));
}

function normalizeAdminPayload(payload) {
  const nextPayload = payload && typeof payload === "object" ? payload : {};
  const nextData = nextPayload.data && typeof nextPayload.data === "object" ? nextPayload.data : {};
  const nextManagement = nextPayload.management && typeof nextPayload.management === "object" ? nextPayload.management : {};

  return {
    ok: Boolean(nextPayload.ok || Array.isArray(nextData.people)),
    configured: Boolean(nextPayload.configured),
    error: String(nextPayload.error || ""),
    data: {
      summary: nextData.summary || {},
      people: Array.isArray(nextData.people) ? nextData.people : [],
      teams: Array.isArray(nextData.teams) ? nextData.teams : [],
      meta: nextData.meta || {},
      generatedAt: nextData.generatedAt || ""
    },
    management: {
      viewer: nextManagement.viewer || {},
      actions: nextManagement.actions || {},
      meta: nextManagement.meta || {},
      queue: nextManagement.queue || { pendingCount: 0, recent: [] },
      audit: nextManagement.audit || { entries: [] },
      signals: nextManagement.signals || { announcement: null }
    }
  };
}

function applyAdminBoardPayload(payload) {
  adminBoardState.payload = normalizeAdminPayload(payload);
  window.__AAVGO_ADMIN_BOARD__ = adminBoardState.payload;

  const allPeople = getAdminPeople();
  const meta = getAdminMeta();
  const roleOptions = [...new Set(allPeople.map(person => String(person?.role || "").trim()).filter(Boolean))]
    .sort((left, right) => left.localeCompare(right));

  syncSelectOptions("hours-filter-role", roleOptions, "All roles", document.getElementById("hours-filter-role")?.value || "");
  syncSelectOptions("hours-filter-team", meta.teams || [], "All teams", document.getElementById("hours-filter-team")?.value || "");
  syncSelectOptions("hours-filter-hotel", meta.hotels || [], "All hotels", document.getElementById("hours-filter-hotel")?.value || "");
  syncSelectOptions("hours-hotel-force-select", meta.hotels || [], "Choose a hotel", document.getElementById("hours-hotel-force-select")?.value || "");

  const visiblePeople = filterAdminPeople(allPeople);
  const selectedStaff = getSelectedStaff(allPeople, visiblePeople);
  const summary = aggregateHoursSummary(visiblePeople);

  setText("hours-summary-total", String(summary.totalPeople));
  setText("hours-summary-active", String(summary.activeNow));
  setText("hours-summary-today", formatHours(summary.todayHours));
  setText("hours-summary-weekly", formatHours(summary.weeklyHours));
  setText("hours-summary-monthly", formatHours(summary.monthlyHours));
  setText("hours-filter-result-count", `${visiblePeople.length} visible`);
  setText("hours-sync-label", formatSyncLabel(adminBoardState.payload?.data?.generatedAt));
  setText("hours-queue-count", String(getAdminManagement()?.queue?.pendingCount || 0));

  renderHoursNotice(adminBoardState.payload);
  renderHoursRows(visiblePeople, selectedStaff?.discordId || "");
  renderTeamCards(deriveTeamCards(visiblePeople));
  renderAuditLog(getAdminManagement()?.audit?.entries || []);
  renderBroadcastComposer(getAdminManagement()?.signals?.announcement || null);
  renderSelectedStaff(selectedStaff);
}

function findSelectedStaff() {
  return getAdminPeople().find(
    person => String(person?.discordId || "") === String(adminBoardState.selectedDiscordId || "")
  ) || null;
}

async function refreshAdminBoard() {
  if (!window.AAVGO_ADMIN_HOURS_ENDPOINT) return;

  try {
    const response = await fetch(window.AAVGO_ADMIN_HOURS_ENDPOINT, {
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    });

    const payload = await response.json().catch(() => ({
      ok: false,
      error: "The leadership board returned an unreadable response."
    }));

    applyAdminBoardPayload(payload);
  } catch (_) {
    applyAdminBoardPayload({
      ...adminBoardState.payload,
      ok: false,
      error: "The leadership board could not refresh right now."
    });
  }
}

async function sendAdminCommand(action, payload = {}) {
  if (!window.AAVGO_ADMIN_COMMAND_ENDPOINT || adminBoardState.actionInFlight) return;

  const isBroadcastAction = action === "broadcast_announcement" || action === "clear_announcement";
  adminBoardState.actionInFlight = true;
  setActionControlsDisabled(true);

  try {
    const response = await fetch(window.AAVGO_ADMIN_COMMAND_ENDPOINT, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ action, payload })
    });

    const data = await response.json().catch(() => ({
      ok: false,
      error: "The leadership action returned an unreadable response."
    }));

    if (!response.ok || !data?.ok) {
      if (isBroadcastAction) {
        setBroadcastFeedback(data?.error || "The leadership broadcast failed.", true);
      } else {
        setActionFeedback(data?.error || "The leadership action failed.", true);
      }
      return;
    }

    if (data.management) {
      adminBoardState.payload = normalizeAdminPayload({
        ...adminBoardState.payload,
        management: data.management
      });
    }

    applyAdminBoardPayload(adminBoardState.payload);
    if (isBroadcastAction) {
      setBroadcastFeedback(data?.message || "Leadership broadcast updated.", false);
      const broadcastInput = document.getElementById("broadcast-message");
      if (broadcastInput && action === "clear_announcement") {
        broadcastInput.value = "";
      }
    } else {
      setActionFeedback(data?.message || "Leadership action queued for bot sync.", false);
    }
    window.setTimeout(refreshAdminBoard, 2200);
  } catch (_) {
    if (isBroadcastAction) {
      setBroadcastFeedback("The leadership broadcast could not be sent right now.", true);
    } else {
      setActionFeedback("The leadership action could not be sent right now.", true);
    }
  } finally {
    adminBoardState.actionInFlight = false;
    setActionControlsDisabled(false);
  }
}

function initializeAdminBoard() {
  if (!document.getElementById("hours-board-rows")) return;

  applyAdminBoardPayload(adminBoardState.payload || {
    ok: false,
    error: "The leadership board has not loaded yet.",
    data: { people: [], meta: {} },
    management: { queue: { pendingCount: 0 }, audit: { entries: [] }, meta: { teams: [], hotels: [] } }
  });

  document.getElementById("hours-board-rows")?.addEventListener("click", event => {
    const row = event.target.closest("tr[data-discord-id]");
    if (!row) return;
    adminBoardState.selectedDiscordId = String(row.getAttribute("data-discord-id") || "");
    applyAdminBoardPayload(adminBoardState.payload);
  });

  ["hours-filter-search", "hours-filter-role", "hours-filter-team", "hours-filter-hotel", "hours-filter-status"]
    .forEach(filterId => {
      const node = document.getElementById(filterId);
      if (!node) return;
      node.addEventListener("input", () => applyAdminBoardPayload(adminBoardState.payload));
      node.addEventListener("change", () => applyAdminBoardPayload(adminBoardState.payload));
    });

  document.getElementById("hours-filter-reset")?.addEventListener("click", () => {
    ["hours-filter-search", "hours-filter-role", "hours-filter-team", "hours-filter-hotel", "hours-filter-status"]
      .forEach(filterId => {
        const node = document.getElementById(filterId);
        if (node) node.value = "";
      });
    applyAdminBoardPayload(adminBoardState.payload);
  });

  document.getElementById("broadcast-send")?.addEventListener("click", () => {
    const message = String(document.getElementById("broadcast-message")?.value || "").trim();
    const tone = String(document.getElementById("broadcast-tone-select")?.value || "standard").trim();

    if (!message) {
      setBroadcastFeedback("Write the announcement before sending it.", true);
      return;
    }

    sendAdminCommand("broadcast_announcement", { message, tone });
  });

  document.getElementById("broadcast-clear")?.addEventListener("click", () => {
    sendAdminCommand("clear_announcement");
  });

  document.getElementById("hours-action-team-submit")?.addEventListener("click", () => {
    const person = findSelectedStaff();
    const team = String(document.getElementById("hours-action-team-select")?.value || "").trim();

    if (!person) {
      setActionFeedback("Pick a staff row before changing the team.", true);
      return;
    }

    if (!team) {
      setActionFeedback("Choose a target team first.", true);
      return;
    }

    sendAdminCommand("update_team", { discordId: person.discordId, team });
  });

  document.getElementById("hours-action-hotel-submit")?.addEventListener("click", () => {
    const person = findSelectedStaff();
    const hotelId = String(document.getElementById("hours-action-hotel-select")?.value || "").trim();

    if (!person) {
      setActionFeedback("Pick a staff row before changing the hotel.", true);
      return;
    }

    if (!hotelId) {
      setActionFeedback("Choose a target hotel first.", true);
      return;
    }

    sendAdminCommand("update_hotel", { discordId: person.discordId, hotelId });
  });

  document.getElementById("hours-action-logout-submit")?.addEventListener("click", () => {
    const person = findSelectedStaff();

    if (!person) {
      setActionFeedback("Pick a staff row before forcing a logout.", true);
      return;
    }

    sendAdminCommand("force_logout_agent", { discordId: person.discordId });
  });

  document.getElementById("hours-hotel-force-submit")?.addEventListener("click", () => {
    const hotelId = String(document.getElementById("hours-hotel-force-select")?.value || "").trim();
    if (!hotelId) {
      setActionFeedback("Choose a hotel before forcing a hotel-wide logout.", true);
      return;
    }

    sendAdminCommand("force_logout_hotel", { hotelId });
  });

  document.getElementById("developer-sync-all")?.addEventListener("click", () => {
    sendAdminCommand("sync_all_roles");
  });

  document.getElementById("developer-push-snapshot")?.addEventListener("click", () => {
    sendAdminCommand("push_snapshot");
  });

  adminBoardState.refreshTimer = window.setInterval(refreshAdminBoard, 30000);
}

initializeAdminBoard();
initializeLiveSignals();
