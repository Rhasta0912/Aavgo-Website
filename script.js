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
  const target = announcement.target && typeof announcement.target === "object" ? announcement.target : null;
  const targetDiscordId = target ? String(target.discordId || "").trim() : "";
  return {
    id,
    message,
    tone: String(announcement.tone || "standard").trim() === "urgent" ? "urgent" : "standard",
    createdAt: String(announcement.createdAt || ""),
    updatedAt: String(announcement.updatedAt || announcement.createdAt || ""),
    actorName: String(actor.name || "Leadership"),
    actorRole: String(actor.roleSummary || "Leadership"),
    targetDiscordId,
    targetName: targetDiscordId ? String(target.name || "Selected staff") : ""
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
    if (normalized.targetDiscordId) {
      liveSignalState.chip.textContent = normalized.tone === "urgent" ? "Urgent direct alert" : "Direct alert";
    } else {
      liveSignalState.chip.textContent = normalized.tone === "urgent" ? "Urgent broadcast" : "Leadership alert";
    }
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

function syncBroadcastTargets(people) {
  const select = document.getElementById("broadcast-target-select");
  if (!select) return;

  const currentValue = String(select.value || "").trim();
  const options = (Array.isArray(people) ? people : [])
    .map(person => ({
      id: String(person?.discordId || "").trim(),
      name: String(person?.displayName || person?.username || "Unknown")
    }))
    .filter(option => option.id);

  options.sort((left, right) => left.name.localeCompare(right.name));
  syncSelectOptions("broadcast-target-select", options, "Website-wide (all staff)", currentValue);
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

function deriveHotelLaneCards(people) {
  const buckets = new Map();

  (Array.isArray(people) ? people : []).forEach(person => {
    const hotelId = getPrimaryHotelId(person) || "UNASSIGNED";
    const hotelLabel = getPrimaryHotelLabel(person);
    const current = buckets.get(hotelId) || {
      id: hotelId,
      label: hotelLabel,
      people: 0,
      activeNow: 0,
      todayHours: 0,
      weeklyHours: 0,
      monthlyHours: 0,
      staff: []
    };

    current.people += 1;
    current.activeNow += person?.activeNow ? 1 : 0;
    current.todayHours += Number(person?.todayHours || 0);
    current.weeklyHours += Number(person?.weeklyHours || 0);
    current.monthlyHours += Number(person?.monthlyHours || 0);
    current.staff.push(person);
    buckets.set(hotelId, current);
  });

  return [...buckets.values()]
    .map(lane => ({
      ...lane,
      todayHours: Number(lane.todayHours || 0),
      weeklyHours: Number(lane.weeklyHours || 0),
      monthlyHours: Number(lane.monthlyHours || 0),
      staff: lane.staff.sort((left, right) => String(left?.displayName || "").localeCompare(String(right?.displayName || "")))
    }))
    .sort((left, right) => String(left.label || "").localeCompare(String(right.label || "")));
}

function getDayNumbersForFullHours(people) {
  const maxDay = Math.max(
    0,
    ...(Array.isArray(people) ? people : []).map(person => Array.isArray(person?.currentMonth?.days) ? person.currentMonth.days.length : 0)
  );

  const count = Math.max(31, maxDay);
  return Array.from({ length: count }, (_, index) => index + 1);
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
  selectedDiscordIds: [],
  view: "board",
  refreshTimer: null,
  actionInFlight: false,
  allowEmptySelection: false
};

const THEME_STORAGE_KEY = "aavgo_theme";
const SIDEBAR_STORAGE_KEY = "aavgo_sidebar";

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
    teams: Array.isArray(managementMeta.teams) ? managementMeta.teams : (Array.isArray(payloadMeta.teams) ? payloadMeta.teams : []),
    roles: Array.isArray(managementMeta.roles) ? managementMeta.roles : (Array.isArray(payloadMeta.roles) ? payloadMeta.roles : [])
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

function getSelectedBulkDiscordIds() {
  return Array.isArray(adminBoardState.selectedDiscordIds)
    ? [...new Set(adminBoardState.selectedDiscordIds.map(value => String(value || "").trim()).filter(Boolean))]
    : [];
}

function setSelectedBulkDiscordIds(values) {
  adminBoardState.selectedDiscordIds = [...new Set(
    (Array.isArray(values) ? values : [values])
      .map(value => String(value || "").trim())
      .filter(Boolean)
  )];
}

function toggleSelectedBulkDiscordId(discordId, checked) {
  const current = new Set(getSelectedBulkDiscordIds());
  if (checked) {
    current.add(String(discordId || "").trim());
  } else {
    current.delete(String(discordId || "").trim());
  }
  setSelectedBulkDiscordIds([...current]);
}

function clearSelectedStaff() {
  adminBoardState.selectedDiscordId = "";
  setSelectedBulkDiscordIds([]);
  adminBoardState.allowEmptySelection = true;
}

function applyTheme(mode) {
  const nextMode = mode === "light" ? "light" : "dark";
  document.body.classList.toggle("theme-light", nextMode === "light");
  document.querySelectorAll("[data-theme-toggle]").forEach(node => {
    node.textContent = nextMode === "light" ? "Dark mode" : "Light mode";
  });
  localStorage.setItem(THEME_STORAGE_KEY, nextMode);
}

function initializeThemeToggle() {
  const stored = localStorage.getItem(THEME_STORAGE_KEY);
  applyTheme(stored === "light" ? "light" : "dark");
  document.querySelectorAll("[data-theme-toggle]").forEach(node => {
    node.addEventListener("click", () => {
      const nextMode = document.body.classList.contains("theme-light") ? "dark" : "light";
      applyTheme(nextMode);
    });
  });
}

function applySidebarState(state) {
  const isCollapsed = state === "collapsed";
  document.body.classList.toggle("sidebar-collapsed", isCollapsed);
}

function initializeSidebarToggle() {
  const stored = safeLocalStorageGet(SIDEBAR_STORAGE_KEY);
  applySidebarState(stored === "collapsed" ? "collapsed" : "expanded");

  document.querySelectorAll("[data-sidebar-toggle]").forEach(node => {
    node.addEventListener("click", () => {
      const nextState = document.body.classList.contains("sidebar-collapsed") ? "expanded" : "collapsed";
      applySidebarState(nextState);
      safeLocalStorageSet(SIDEBAR_STORAGE_KEY, nextState);
    });
  });
}

function initializeToolbarMenu() {
  const shell = document.querySelector("[data-toolbar-menu]");
  const toggle = document.querySelector("[data-toolbar-menu-toggle]");
  const panel = document.querySelector("[data-toolbar-menu-panel]");
  if (!shell || !toggle || !panel) return;

  let hideTimer = null;

  const setOpen = (open) => {
    if (hideTimer) {
      window.clearTimeout(hideTimer);
      hideTimer = null;
    }

    shell.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    panel.setAttribute("aria-hidden", open ? "false" : "true");

    if (open) {
      panel.hidden = false;
      window.requestAnimationFrame(() => {
        shell.classList.add("is-open");
      });
      return;
    }

    shell.classList.remove("is-open");
    hideTimer = window.setTimeout(() => {
      if (!shell.classList.contains("is-open")) {
        panel.hidden = true;
      }
    }, 210);
  };

  toggle.addEventListener("click", event => {
    event.stopPropagation();
    setOpen(!shell.classList.contains("is-open"));
  });

  panel.addEventListener("click", event => {
    if (event.target.closest("a, button")) {
      setOpen(false);
    }
  });

  document.addEventListener("click", event => {
    if (!shell.contains(event.target)) {
      setOpen(false);
    }
  });

  document.addEventListener("keydown", event => {
    if (event.key === "Escape") {
      setOpen(false);
    }
  });

  panel.hidden = true;
  panel.setAttribute("aria-hidden", "true");
  toggle.setAttribute("aria-expanded", "false");
}

function setHoursEditorOpen(open) {
  const modal = document.getElementById("hours-editor-modal");
  if (!modal) return;
  modal.hidden = !open;
  document.body.classList.toggle("dashboard-modal-open", open);
}

function openHoursEditorModal(person = null, options = {}) {
  if (person?.discordId) {
    adminBoardState.selectedDiscordId = String(person.discordId);
    adminBoardState.allowEmptySelection = false;
  }

  const shiftDate = String(options.shiftDate || "").trim();
  const hours = Number(options.hours || 0);
  const loginTime = String(options.loginTime || "").trim();
  const logoutTime = String(options.logoutTime || "").trim();
  const mode = String(options.mode || "shift").trim() === "training" ? "training" : "shift";

  const editorDate = document.getElementById("hours-editor-date");
  const removeDate = document.getElementById("hours-remove-date");
  const editorMode = document.getElementById("hours-editor-mode");
  const removeMode = document.getElementById("hours-remove-mode");
  const editorLogin = document.getElementById("hours-editor-login");
  const editorLogout = document.getElementById("hours-editor-logout");
  const removeHours = document.getElementById("hours-remove-hours");

  if (shiftDate) {
    if (editorDate) editorDate.value = shiftDate;
    if (removeDate) removeDate.value = shiftDate;
  }
  if (editorMode) editorMode.value = mode;
  if (removeMode) removeMode.value = mode;
  if (editorLogin) editorLogin.value = loginTime;
  if (editorLogout) editorLogout.value = logoutTime;
  if (removeHours && hours > 0) removeHours.value = String(Math.round(hours * 10) / 10);

  applyAdminBoardPayload(adminBoardState.payload);
  setHoursEditorOpen(true);
}

function roundHoursStep(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) return 0;
  return Math.round(number * 10) / 10;
}

function hoursToClock(hours) {
  const safeHours = Math.max(0, Math.min(23.9, roundHoursStep(hours)));
  const wholeHours = Math.floor(safeHours);
  const minutes = Math.round((safeHours - wholeHours) * 60);
  const normalizedHours = String(wholeHours).padStart(2, "0");
  const normalizedMinutes = String(Math.min(minutes, 59)).padStart(2, "0");
  return `${normalizedHours}:${normalizedMinutes}`;
}

async function quickSetCellHours(person, shiftDate, currentHours, nextHours) {
  const safeNext = roundHoursStep(nextHours);
  const safeCurrent = roundHoursStep(currentHours);
  if (!person?.discordId) return;
  if (!Number.isFinite(safeNext) || safeNext < 0 || safeNext > 24) {
    setEditorFeedback("Use a value between 0 and 24 hours for a single day.", true);
    return;
  }

  const delta = roundHoursStep(Math.abs(safeNext - safeCurrent));
  if (delta === 0) {
    setEditorFeedback("That day already has that total.", false);
    return;
  }

  const reason = "Quick full-hours grid update";
  if (safeNext > safeCurrent) {
    return sendAdminCommand("add_manual_hours", {
      discordId: person.discordId,
      shiftDate,
      loginTime: "00:00",
      logoutTime: hoursToClock(delta),
      mode: "training",
      hotelId: "",
      reason
    }, { feedback: "editor" });
  }

  return sendAdminCommand("remove_manual_hours", {
    discordId: person.discordId,
    shiftDate,
    hours: delta,
    mode: "training",
    reason
  }, { feedback: "editor" });
}

function openInlineHoursCellEditor(cell, person, shiftDate, currentHours) {
  if (!cell || !person?.discordId) return;

  const existing = document.querySelector(".dashboard-hours-inline-input");
  if (existing) {
    existing.blur();
  }

  cell.classList.add("is-editing");
  const previousMarkup = cell.innerHTML;
  cell.innerHTML = `<input class="dashboard-hours-inline-input" type="number" min="0" max="24" step="0.1" value="${escapeHtml(formatHoursValue(currentHours))}">`;
  const input = cell.querySelector(".dashboard-hours-inline-input");
  if (!input) return;

  let finished = false;
  const finish = (commit) => {
    if (finished) return;
    finished = true;
    const nextValue = Number(input.value || 0);
    cell.classList.remove("is-editing");
    if (commit) {
      const displayValue = roundHoursStep(nextValue);
      cell.setAttribute("data-hours", String(displayValue));
      cell.classList.toggle("has-hours", displayValue > 0);
      cell.innerHTML = `<div class="dashboard-hours-cell-copy">${displayValue > 0 ? escapeHtml(formatHoursValue(displayValue)) : ""}</div>`;
      setEditorFeedback("Saving hours...", false);
      void quickSetCellHours(person, shiftDate, currentHours, displayValue).then(success => {
        if (success === false) {
          cell.setAttribute("data-hours", String(currentHours));
          cell.classList.toggle("has-hours", Number(currentHours) > 0);
          cell.innerHTML = previousMarkup;
        }
      });
      return;
    }
    cell.innerHTML = previousMarkup;
  };

  input.focus();
  input.select();
  input.addEventListener("keydown", event => {
    const isEnter = event.key === "Enter" || event.code === "Enter" || event.code === "NumpadEnter" || event.keyCode === 13;
    if (isEnter) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      finish(false);
    }
  });
  input.addEventListener("keyup", event => {
    const isEnter = event.key === "Enter" || event.code === "Enter" || event.code === "NumpadEnter" || event.keyCode === 13;
    if (!isEnter) return;
    event.preventDefault();
    event.stopPropagation();
    window.requestAnimationFrame(() => finish(true));
  });
  input.addEventListener("change", () => finish(true));
  input.addEventListener("blur", () => finish(true), { once: true });
}

function getSelectedBulkStaff(allPeople) {
  const selectedIds = new Set(getSelectedBulkDiscordIds());
  return (Array.isArray(allPeople) ? allPeople : []).filter(person => selectedIds.has(String(person?.discordId || "")));
}

function setBulkFeedback(message, isError = false) {
  const node = document.getElementById("hours-bulk-feedback");
  if (!node) return;
  node.textContent = message;
  node.classList.toggle("is-error", Boolean(isError));
  node.classList.toggle("is-success", !isError && message !== "");
}

function setEditorFeedback(message, isError = false) {
  const node = document.getElementById("hours-editor-feedback");
  if (!node) return;
  node.textContent = message;
  node.classList.toggle("is-error", Boolean(isError));
  node.classList.toggle("is-success", !isError && message !== "");
}

function setAdminView(nextView) {
  adminBoardState.view = nextView === "full-hours" ? "full-hours" : "board";
  document.querySelectorAll("[data-hours-view]").forEach(node => {
    node.classList.toggle("is-active", node.getAttribute("data-hours-view") === adminBoardState.view);
  });
  document.querySelectorAll("[data-hours-view-panel]").forEach(node => {
    const isActive = node.getAttribute("data-hours-view-panel") === adminBoardState.view;
    node.classList.toggle("is-active", isActive);
    node.hidden = !isActive;
  });
}

function setActiveNavLink(hash) {
  if (!hash) return;
  document.querySelectorAll(".dashboard-nav-link").forEach(link => {
    link.classList.toggle("is-active", link.getAttribute("href") === hash || link.getAttribute("href") === "/admin/");
  });
}

function filterAdminPeople(people) {
  const filters = getAdminFilters();
  const roleFilter = normalizeForSearch(filters.role);
  const teamFilter = normalizeForSearch(filters.team);
  const hotelFilter = normalizeForSearch(filters.hotel);

  return (Array.isArray(people) ? people : []).filter(person => {
    if (filters.search && !getSearchHaystack(person).includes(filters.search)) {
      return false;
    }

    if (roleFilter) {
      const roleValues = new Set(
        [person?.role, ...(Array.isArray(person?.roleLabels) ? person.roleLabels : [])]
          .map(value => normalizeForSearch(value))
          .filter(Boolean)
      );
      if (!roleValues.has(roleFilter)) {
        return false;
      }
    }

    if (teamFilter && normalizeForSearch(person?.team) !== teamFilter) {
      return false;
    }

    if (hotelFilter) {
      const personHotelId = normalizeForSearch(getPrimaryHotelId(person));
      const personHotelLabel = normalizeForSearch(getPrimaryHotelLabel(person));
      if (hotelFilter !== personHotelId && hotelFilter !== personHotelLabel) {
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
  if (!adminBoardState.selectedDiscordId && adminBoardState.allowEmptySelection) {
    return null;
  }
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
  adminBoardState.allowEmptySelection = false;
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
    "hours-bulk-team-select",
    "hours-bulk-team-submit",
    "hours-bulk-hotel-select",
    "hours-bulk-hotel-submit",
    "hours-bulk-logout-submit",
    "hours-bulk-select-visible",
    "hours-bulk-clear",
    "hours-editor-date",
    "hours-editor-mode",
    "hours-editor-login",
    "hours-editor-logout",
    "hours-editor-hotel",
    "hours-editor-reason",
    "hours-editor-add-submit",
    "hours-remove-date",
    "hours-remove-hours",
    "hours-remove-mode",
    "hours-remove-reason",
    "hours-remove-submit",
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
  const targetSelect = document.getElementById("broadcast-target-select");
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
    if (targetSelect && !adminBoardState.actionInFlight) {
      targetSelect.value = "";
    }
    return;
  }

  preview.innerHTML = `
    <div class="dashboard-broadcast-meta">
      <span class="dashboard-chip ${normalized.tone === "urgent" ? "dashboard-chip-accent" : ""}">
        ${escapeHtml(normalized.tone === "urgent" ? "Urgent" : "Standard")}
      </span>
      ${normalized.targetDiscordId ? `<span class="dashboard-chip">Target: ${escapeHtml(normalized.targetName || "Selected staff")}</span>` : ""}
    </div>
    <strong>${escapeHtml(normalized.message)}</strong>
    <p>${escapeHtml(normalized.actorName)} - ${escapeHtml(formatAuditLabel(normalized.createdAt))}</p>
  `;

  if (toneSelect && !adminBoardState.actionInFlight) {
    toneSelect.value = normalized.tone;
  }

  if (textarea && !adminBoardState.actionInFlight && textarea.value.trim() === "") {
    textarea.value = normalized.message;
  }

  if (targetSelect && !adminBoardState.actionInFlight && normalized.targetDiscordId) {
    targetSelect.value = normalized.targetDiscordId;
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

function renderFullHoursRows(people) {
  const table = document.getElementById("hours-full-board");
  const cols = document.getElementById("hours-full-board-cols");
  const head = document.getElementById("hours-full-board-head");
  const body = document.getElementById("hours-full-board-rows");
  if (!table || !cols || !head || !body) return;

  const dayNumbers = getDayNumbersForFullHours(people);
  cols.innerHTML = `
      <col style="width:124px">
      <col style="width:300px">
      <col style="width:204px">
      <col style="width:232px">
      <col style="width:260px">
      ${dayNumbers.map(() => `<col style="width:132px">`).join("")}
      <col style="width:140px">
      <col style="width:140px">
      <col style="width:140px">
      <col style="width:140px">
  `;
  head.innerHTML = `
    <tr>
      <th>Select</th>
      <th>Staff</th>
      <th>Role</th>
      <th>Team</th>
      <th>Hotel</th>
      ${dayNumbers.map(day => `<th>D${day}</th>`).join("")}
      <th>1st - 15th</th>
      <th>16th - end</th>
      <th>Month</th>
      <th>All time</th>
    </tr>
  `;

  if (!Array.isArray(people) || people.length === 0) {
    body.innerHTML = `
      <tr>
        <td colspan="${dayNumbers.length + 9}">
          <div class="dashboard-empty-state">
            <strong>No staff rows match this lane.</strong>
            <p>Widen the filters or wait for the next snapshot to fill the full-hours sheet.</p>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  const selectedIds = new Set(getSelectedBulkDiscordIds());
  body.innerHTML = people.map(person => {
    const dayMap = new Map(
      (Array.isArray(person?.currentMonth?.days) ? person.currentMonth.days : [])
        .map(day => [Number(day?.day || 0), Number(day?.totalHours || 0)])
    );
    const isSelected = selectedIds.has(String(person?.discordId || ""));

    return `
      <tr class="${isSelected ? "is-selected" : ""}" data-full-hours-row="${escapeHtml(person?.discordId || "")}">
        <td>
          <label class="dashboard-checkbox">
            <input type="checkbox" data-full-hours-select="${escapeHtml(person?.discordId || "")}" ${isSelected ? "checked" : ""}>
            <span></span>
          </label>
        </td>
        <td>
          <div class="dashboard-staff-cell">
            <strong>${escapeHtml(person?.displayName || "Unknown")}</strong>
            <span>${escapeHtml(person?.username || "")}</span>
          </div>
        </td>
        <td><div class="dashboard-hours-cell-copy">${escapeHtml(getRoleSummary(person))}</div></td>
        <td><div class="dashboard-hours-cell-copy">${escapeHtml(person?.team || "Unassigned")}</div></td>
        <td><div class="dashboard-hours-cell-copy">${escapeHtml(getPrimaryHotelLabel(person))}</div></td>
        ${dayNumbers.map(day => {
          const hours = Number(dayMap.get(day) || 0);
          const className = hours > 0 ? "dashboard-hours-cell has-hours" : "dashboard-hours-cell";
          return `<td class="${className}" data-day="${day}" data-hours="${hours}" title="Double-click to edit hours">
            <div class="dashboard-hours-cell-copy">${hours > 0 ? escapeHtml(formatHoursValue(hours)) : ""}</div>
          </td>`;
        }).join("")}
        <td><div class="dashboard-hours-cell-copy">${formatHours(person?.payPeriods?.firstHalf?.totalHours)}</div></td>
        <td><div class="dashboard-hours-cell-copy">${formatHours(person?.payPeriods?.secondHalf?.totalHours)}</div></td>
        <td><div class="dashboard-hours-cell-copy">${formatHours(person?.monthlyHours)}</div></td>
        <td><div class="dashboard-hours-cell-copy">${formatHours(person?.allHours)}</div></td>
      </tr>
    `;
  }).join("");
}

function renderHotelLaneCards(lanes) {
  const container = document.getElementById("hours-hotel-lanes");
  if (!container) return;

  if (!Array.isArray(lanes) || lanes.length === 0) {
    container.innerHTML = `
      <div class="dashboard-empty-state">
        <strong>No hotel command lanes available.</strong>
        <p>Hotel summaries appear here once people are visible in the current lane.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = lanes.map(lane => `
    <article class="dashboard-hotel-lane-card">
      <div class="dashboard-hotel-lane-head">
        <div>
          <strong>${escapeHtml(lane?.label || "Unassigned")}</strong>
          <span>${escapeHtml(String(lane?.people ?? 0))} tracked</span>
        </div>
        <button
          class="button button-secondary dashboard-inline-button"
          type="button"
          data-hotel-lane-logout="${escapeHtml(lane?.id || "")}"
          ${lane?.id === "UNASSIGNED" ? "disabled" : ""}
        >
          Force logout
        </button>
      </div>
      <div class="dashboard-mini-metrics">
        <span>Active <strong>${escapeHtml(String(lane?.activeNow ?? 0))}</strong></span>
        <span>Today <strong>${formatHours(lane?.todayHours)}</strong></span>
        <span>Week <strong>${formatHours(lane?.weeklyHours)}</strong></span>
      </div>
      <ul class="dashboard-inline-list dashboard-inline-list-staff">
        ${(Array.isArray(lane?.staff) ? lane.staff.slice(0, 6) : []).map(person => `
          <li>
            <span>${escapeHtml(person?.displayName || "Unknown")}</span>
            <strong>${escapeHtml(person?.activeNow ? "Live" : "Idle")}</strong>
          </li>
        `).join("")}
      </ul>
    </article>
  `).join("");
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
    .sort((left, right) => Number(left?.day || 0) - Number(right?.day || 0));

  if (items.length === 0) {
    return '<p class="dashboard-period-copy">No tracked days yet in this cut.</p>';
  }

  return `
    <ul class="dashboard-inline-list dashboard-period-days">
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
    .sort((left, right) => Number(left?.day || 0) - Number(right?.day || 0));
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
        ? `<ul class="dashboard-inline-list dashboard-history-days">
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

function renderAdjustmentLog(person) {
  const container = document.getElementById("hours-adjustment-log");
  if (!container) return;

  if (!person) {
    container.innerHTML = `
      <div class="dashboard-empty-state">
        <strong>Waiting for a staff selection.</strong>
        <p>Recent manual hour adjustments for the selected staff member will show here.</p>
      </div>
    `;
    return;
  }

  const adjustments = Array.isArray(person?.recentAdjustments) ? person.recentAdjustments : [];
  if (adjustments.length === 0) {
    container.innerHTML = `
      <div class="dashboard-empty-state">
        <strong>No recent manual adjustments.</strong>
        <p>${escapeHtml(person?.displayName || "This staff member")} has no recent manual hour entries yet.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = adjustments.map(entry => `
    <article class="dashboard-adjustment-item">
      <div class="dashboard-adjustment-top">
        <span class="dashboard-chip">${escapeHtml(entry?.mode === "training" ? "Training" : "Live shift")}</span>
        <strong>${escapeHtml(entry?.shiftDate || "")}</strong>
      </div>
      <p>${escapeHtml(entry?.hotelLabel || "N/A")} &middot; ${escapeHtml(entry?.loginTime || "--:--")} - ${escapeHtml(entry?.logoutTime || "--:--")} &middot; ${formatHours(entry?.hours)}</p>
      <span>${escapeHtml(entry?.reason || "Manual adjustment")}</span>
    </article>
  `).join("");
}

function syncHoursEditorState(person) {
  setText("hours-editor-selected", person?.displayName
    ? `${person.displayName} &middot; ${getRoleSummary(person)}`
    : "Pick a staff row to edit hours.");

  setText("hours-editor-summary-selected", person?.displayName
    ? `${person.displayName} &middot; ${getRoleSummary(person)}`
    : "Pick a staff row to edit hours.");

  renderAdjustmentLog(person);

  if (!person) {
    return;
  }

  const editorHotel = document.getElementById("hours-editor-hotel");
  if (editorHotel && !editorHotel.value) {
    editorHotel.value = getPrimaryHotelId(person);
  }

  const editorDate = document.getElementById("hours-editor-date");
  if (editorDate && !editorDate.value) {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    editorDate.value = `${year}-${month}-${day}`;
  }

  const removeDate = document.getElementById("hours-remove-date");
  if (removeDate && !removeDate.value && editorDate?.value) {
    removeDate.value = editorDate.value;
  }
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
  syncHoursEditorState(person);

  if (!person) {
    setActionControlsDisabled(true);
    setActionFeedback("Select a staff member to unlock reassignment and logout controls.", false);
    setEditorFeedback("Select a staff row in the full hours table, then add or remove hours with a clear reason.", false);
    return;
  }

  setActionControlsDisabled(adminBoardState.actionInFlight);
  setActionFeedback(`${person.displayName || person.username || "Staff member"} is ready for reassignment, forced logout, or hotel-level review.`, false);

  const meta = getAdminMeta();
  syncSelectOptions("hours-action-team-select", meta.teams || [], "Choose a team", person?.team || "");
  syncSelectOptions("hours-action-hotel-select", meta.hotels || [], "Choose a hotel", getPrimaryHotelId(person));
  syncSelectOptions("hours-editor-hotel", meta.hotels || [], "Use linked hotel", getPrimaryHotelId(person));
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
      hotelLanes: Array.isArray(nextData.hotelLanes) ? nextData.hotelLanes : [],
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
  const roleOptions = [...new Set(
    [
      ...(Array.isArray(meta.roles) ? meta.roles : []),
      ...allPeople.flatMap(person => Array.isArray(person?.roleLabels) ? person.roleLabels : [person?.role])
    ].map(value => String(value || "").trim()).filter(Boolean)
  )]
    .sort((left, right) => left.localeCompare(right));

  const validIds = new Set(allPeople.map(person => String(person?.discordId || "")));
  setSelectedBulkDiscordIds(getSelectedBulkDiscordIds().filter(discordId => validIds.has(discordId)));
  if (adminBoardState.selectedDiscordId && !validIds.has(String(adminBoardState.selectedDiscordId || ""))) {
    adminBoardState.selectedDiscordId = "";
    adminBoardState.allowEmptySelection = true;
  }

  syncSelectOptions("hours-filter-role", roleOptions, "All roles", document.getElementById("hours-filter-role")?.value || "");
  syncSelectOptions("hours-filter-team", meta.teams || [], "All teams", document.getElementById("hours-filter-team")?.value || "");
  syncSelectOptions("hours-filter-hotel", meta.hotels || [], "All hotels", document.getElementById("hours-filter-hotel")?.value || "");
  syncSelectOptions("hours-hotel-force-select", meta.hotels || [], "Choose a hotel", document.getElementById("hours-hotel-force-select")?.value || "");
  syncSelectOptions("hours-bulk-team-select", meta.teams || [], "Choose a team", document.getElementById("hours-bulk-team-select")?.value || "");
  syncSelectOptions("hours-bulk-hotel-select", meta.hotels || [], "Choose a hotel", document.getElementById("hours-bulk-hotel-select")?.value || "");
  syncSelectOptions("hours-editor-hotel", meta.hotels || [], "Use linked hotel", document.getElementById("hours-editor-hotel")?.value || "");

  const visiblePeople = filterAdminPeople(allPeople);
  const selectedStaff = getSelectedStaff(allPeople, visiblePeople);
  syncBroadcastTargets(allPeople);
  const selectedBulkPeople = getSelectedBulkStaff(visiblePeople);
  const summary = aggregateHoursSummary(visiblePeople);

  setText("hours-summary-total", String(summary.totalPeople));
  setText("hours-summary-active", String(summary.activeNow));
  setText("hours-summary-today", formatHours(summary.todayHours));
  setText("hours-summary-weekly", formatHours(summary.weeklyHours));
  setText("hours-summary-monthly", formatHours(summary.monthlyHours));
  setText("hours-filter-result-count", `${visiblePeople.length} visible`);
  setText("hours-sync-label", formatSyncLabel(adminBoardState.payload?.data?.generatedAt));
  setText("hours-queue-count", String(getAdminManagement()?.queue?.pendingCount || 0));
  setText("hours-bulk-selected-count", `${selectedBulkPeople.length} selected`);

  renderHoursNotice(adminBoardState.payload);
  renderHoursRows(visiblePeople, selectedStaff?.discordId || "");
  renderFullHoursRows(visiblePeople);
  renderTeamCards(deriveTeamCards(visiblePeople));
  renderHotelLaneCards(deriveHotelLaneCards(visiblePeople));
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

async function sendAdminCommand(action, payload = {}, options = {}) {
  if (!window.AAVGO_ADMIN_COMMAND_ENDPOINT || adminBoardState.actionInFlight) return;

  const isBroadcastAction = action === "broadcast_announcement" || action === "clear_announcement";
  const feedbackChannel = String(options.feedback || "action");
  const setFeedback = (message, isError = false) => {
    if (feedbackChannel === "bulk") {
      setBulkFeedback(message, isError);
      return;
    }
    if (feedbackChannel === "editor") {
      setEditorFeedback(message, isError);
      return;
    }
    setActionFeedback(message, isError);
  };

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
        setFeedback(data?.error || "The leadership action failed.", true);
      }
      return false;
    }

    if (data.management) {
      adminBoardState.payload = normalizeAdminPayload({
        ...adminBoardState.payload,
        management: data.management
      });
    }

    const shouldClearSelection = action.startsWith("bulk_");
    if (shouldClearSelection) {
      clearSelectedStaff();
    }

    applyAdminBoardPayload(adminBoardState.payload);
    if (isBroadcastAction) {
      setBroadcastFeedback(data?.message || "Leadership broadcast updated.", false);
      const broadcastInput = document.getElementById("broadcast-message");
      if (broadcastInput && action === "clear_announcement") {
        broadcastInput.value = "";
      }
    } else {
      setFeedback(data?.message || "Leadership action queued for bot sync.", false);
    }
    window.setTimeout(refreshAdminBoard, 2200);
    return true;
  } catch (_) {
    if (isBroadcastAction) {
      setBroadcastFeedback("The leadership broadcast could not be sent right now.", true);
    } else {
      setFeedback("The leadership action could not be sent right now.", true);
    }
    return false;
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
  setAdminView(adminBoardState.view);

  document.querySelectorAll("[data-hours-view]").forEach(node => {
    node.addEventListener("click", () => {
      setAdminView(node.getAttribute("data-hours-view") || "board");
    });
  });

  document.querySelectorAll(".dashboard-nav-link, .dashboard-toolbar-link, .dashboard-toolbar-dropdown-link").forEach(link => {
    link.addEventListener("click", event => {
      const href = link.getAttribute("href") || "";
      if (!href.startsWith("#")) return;
      event.preventDefault();
      if (href === "#leadership-full-hours") {
        setAdminView("full-hours");
      } else if (href.startsWith("#leadership-")) {
        setAdminView("board");
      }
      const target = document.querySelector(href);
      if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
      setActiveNavLink(href);
    });
  });

  document.getElementById("hours-board-rows")?.addEventListener("click", event => {
    const row = event.target.closest("tr[data-discord-id]");
    if (!row) return;
    const discordId = String(row.getAttribute("data-discord-id") || "");
    const isAlreadySelected = String(adminBoardState.selectedDiscordId) === discordId;
    if (isAlreadySelected) {
      clearSelectedStaff();
    } else {
      adminBoardState.selectedDiscordId = discordId;
      adminBoardState.allowEmptySelection = false;
      toggleSelectedBulkDiscordId(discordId, true);
    }
    applyAdminBoardPayload(adminBoardState.payload);
  });

  document.getElementById("hours-full-board-rows")?.addEventListener("click", event => {
    const cell = event.target.closest("td[data-day]");
    if (cell) {
      const row = cell.closest("tr[data-full-hours-row]");
      const discordId = String(row?.getAttribute("data-full-hours-row") || "");
      const day = Number(cell.getAttribute("data-day") || 0);
      const hours = Number(cell.getAttribute("data-hours") || 0);
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      const maxDay = new Date(year, month + 1, 0).getDate();
      const safeDay = Math.min(Math.max(day, 1), maxDay);
      const dateValue = `${year}-${String(month + 1).padStart(2, "0")}-${String(safeDay).padStart(2, "0")}`;

      if (discordId) {
        adminBoardState.selectedDiscordId = discordId;
        adminBoardState.allowEmptySelection = false;
        toggleSelectedBulkDiscordId(discordId, true);
      }

      const editorDate = document.getElementById("hours-editor-date");
      if (editorDate) editorDate.value = dateValue;
      const removeDate = document.getElementById("hours-remove-date");
      if (removeDate) removeDate.value = dateValue;
      const removeHours = document.getElementById("hours-remove-hours");
      if (removeHours && hours > 0) removeHours.value = String(hours);
      return;
    }

    const checkbox = event.target.closest("input[data-full-hours-select]");
    if (checkbox) {
      const discordId = checkbox.getAttribute("data-full-hours-select") || "";
      toggleSelectedBulkDiscordId(discordId, checkbox.checked);
      adminBoardState.selectedDiscordId = checkbox.checked ? String(discordId) : adminBoardState.selectedDiscordId;
      adminBoardState.allowEmptySelection = !checkbox.checked;
      if (!checkbox.checked && adminBoardState.selectedDiscordId === String(discordId)) {
        adminBoardState.selectedDiscordId = "";
      }
      applyAdminBoardPayload(adminBoardState.payload);
      return;
    }

    const row = event.target.closest("tr[data-full-hours-row]");
    if (!row) return;
    const discordId = String(row.getAttribute("data-full-hours-row") || "");
    const isAlreadySelected = String(adminBoardState.selectedDiscordId) === discordId;
    if (isAlreadySelected) {
      toggleSelectedBulkDiscordId(discordId, false);
      adminBoardState.selectedDiscordId = "";
      adminBoardState.allowEmptySelection = true;
    } else {
      adminBoardState.selectedDiscordId = discordId;
      adminBoardState.allowEmptySelection = false;
      toggleSelectedBulkDiscordId(discordId, true);
    }
    applyAdminBoardPayload(adminBoardState.payload);
  });

  document.getElementById("hours-full-board-rows")?.addEventListener("dblclick", event => {
    const cell = event.target.closest("td[data-day]");
    const row = event.target.closest("tr[data-full-hours-row]");
    if (!cell || !row) return;

    const discordId = String(row.getAttribute("data-full-hours-row") || "");
    const person = getAdminPeople().find(entry => String(entry?.discordId || "") === discordId) || null;
    const day = Number(cell.getAttribute("data-day") || 0);
    const hours = Number(cell.getAttribute("data-hours") || 0);
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const maxDay = new Date(year, month + 1, 0).getDate();
    const safeDay = Math.min(Math.max(day, 1), maxDay);
    const shiftDate = `${year}-${String(month + 1).padStart(2, "0")}-${String(safeDay).padStart(2, "0")}`;
    openInlineHoursCellEditor(cell, person, shiftDate, hours);
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

  document.getElementById("hours-bulk-select-visible")?.addEventListener("click", () => {
    const visiblePeople = filterAdminPeople(getAdminPeople());
    setSelectedBulkDiscordIds(visiblePeople.map(person => String(person?.discordId || "")));
    if (visiblePeople[0]?.discordId) {
      adminBoardState.selectedDiscordId = String(visiblePeople[0].discordId);
      adminBoardState.allowEmptySelection = false;
    }
    setBulkFeedback(`${visiblePeople.length} visible staff row(s) selected.`, false);
    applyAdminBoardPayload(adminBoardState.payload);
  });

  document.getElementById("hours-bulk-clear")?.addEventListener("click", () => {
    clearSelectedStaff();
    setBulkFeedback("Selection cleared.", false);
    applyAdminBoardPayload(adminBoardState.payload);
  });

  const openEditor = () => {
    const person = findSelectedStaff();
    if (!person) {
      setEditorFeedback("Pick a staff row first, then open the editor.", true);
      return;
    }
    openHoursEditorModal(person);
  };

  document.getElementById("hours-open-editor")?.addEventListener("click", openEditor);
  document.getElementById("hours-open-editor-secondary")?.addEventListener("click", openEditor);

  document.querySelectorAll("[data-hours-modal-close]").forEach(node => {
    node.addEventListener("click", () => setHoursEditorOpen(false));
  });

  document.getElementById("broadcast-send")?.addEventListener("click", () => {
    const message = String(document.getElementById("broadcast-message")?.value || "").trim();
    const tone = String(document.getElementById("broadcast-tone-select")?.value || "standard").trim();
    const targetDiscordId = String(document.getElementById("broadcast-target-select")?.value || "").trim();

    if (!message) {
      setBroadcastFeedback("Write the announcement before sending it.", true);
      return;
    }

    sendAdminCommand("broadcast_announcement", {
      message,
      tone,
      targetDiscordId: targetDiscordId || ""
    });
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

  document.getElementById("hours-bulk-team-submit")?.addEventListener("click", () => {
    const selected = getSelectedBulkDiscordIds();
    const team = String(document.getElementById("hours-bulk-team-select")?.value || "").trim();
    if (selected.length === 0) {
      setBulkFeedback("Select at least one staff row before applying a bulk team move.", true);
      return;
    }
    if (!team) {
      setBulkFeedback("Choose a target team first.", true);
      return;
    }
    sendAdminCommand("bulk_update_team", { discordIds: selected, team }, { feedback: "bulk" });
  });

  document.getElementById("hours-bulk-hotel-submit")?.addEventListener("click", () => {
    const selected = getSelectedBulkDiscordIds();
    const hotelId = String(document.getElementById("hours-bulk-hotel-select")?.value || "").trim();
    if (selected.length === 0) {
      setBulkFeedback("Select at least one staff row before applying a bulk hotel move.", true);
      return;
    }
    if (!hotelId) {
      setBulkFeedback("Choose a target hotel first.", true);
      return;
    }
    sendAdminCommand("bulk_update_hotel", { discordIds: selected, hotelId }, { feedback: "bulk" });
  });

  document.getElementById("hours-bulk-logout-submit")?.addEventListener("click", () => {
    const selected = getSelectedBulkDiscordIds();
    if (selected.length === 0) {
      setBulkFeedback("Select at least one staff row before forcing a bulk logout.", true);
      return;
    }
    sendAdminCommand("bulk_force_logout_agents", { discordIds: selected }, { feedback: "bulk" });
  });

  document.getElementById("hours-editor-add-submit")?.addEventListener("click", () => {
    const person = findSelectedStaff();
    const shiftDate = String(document.getElementById("hours-editor-date")?.value || "").trim();
    const loginTime = String(document.getElementById("hours-editor-login")?.value || "").trim();
    const logoutTime = String(document.getElementById("hours-editor-logout")?.value || "").trim();
    const mode = String(document.getElementById("hours-editor-mode")?.value || "shift").trim();
    const hotelId = String(document.getElementById("hours-editor-hotel")?.value || "").trim();
    const reason = String(document.getElementById("hours-editor-reason")?.value || "").trim();

    if (!person) {
      setEditorFeedback("Select a staff row before adding hours.", true);
      return;
    }
    if (!shiftDate || !loginTime || !logoutTime || !reason) {
      setEditorFeedback("Fill in the date, login, logout, and reason before adding hours.", true);
      return;
    }

    sendAdminCommand("add_manual_hours", {
      discordId: person.discordId,
      shiftDate,
      loginTime,
      logoutTime,
      mode,
      hotelId,
      reason
    }, { feedback: "editor" });
  });

  document.getElementById("hours-remove-submit")?.addEventListener("click", () => {
    const person = findSelectedStaff();
    const shiftDate = String(document.getElementById("hours-remove-date")?.value || "").trim();
    const hours = Number(document.getElementById("hours-remove-hours")?.value || 0);
    const mode = String(document.getElementById("hours-remove-mode")?.value || "shift").trim();
    const reason = String(document.getElementById("hours-remove-reason")?.value || "").trim();

    if (!person) {
      setEditorFeedback("Select a staff row before removing hours.", true);
      return;
    }
    if (!shiftDate || !Number.isFinite(hours) || hours <= 0 || !reason) {
      setEditorFeedback("Fill in the date, hour amount, and reason before removing hours.", true);
      return;
    }

    sendAdminCommand("remove_manual_hours", {
      discordId: person.discordId,
      shiftDate,
      hours,
      mode,
      reason
    }, { feedback: "editor" });
  });

  document.getElementById("hours-hotel-lanes")?.addEventListener("click", event => {
    const button = event.target.closest("button[data-hotel-lane-logout]");
    if (!button) return;
    const hotelId = String(button.getAttribute("data-hotel-lane-logout") || "").trim();
    if (!hotelId || hotelId === "UNASSIGNED") {
      return;
    }
    sendAdminCommand("force_logout_hotel", { hotelId }, { feedback: "bulk" });
  });

  document.getElementById("developer-sync-all")?.addEventListener("click", () => {
    sendAdminCommand("sync_all_roles");
  });

  document.getElementById("developer-push-snapshot")?.addEventListener("click", () => {
    sendAdminCommand("push_snapshot");
  });

  adminBoardState.refreshTimer = window.setInterval(refreshAdminBoard, 30000);
}

function initializeDeveloperTodoList() {
  const list = document.getElementById("dev-todo-list");
  const input = document.getElementById("dev-todo-input");
  const addButton = document.getElementById("dev-todo-add");
  if (!list || !input || !addButton) return;

  const STORAGE_KEY = "aavgo_dev_todos";
  const render = (items) => {
    list.innerHTML = "";
    if (!items.length) {
      const empty = document.createElement("li");
      empty.className = "dashboard-dev-todo-empty";
      empty.textContent = "No dev tasks yet.";
      list.appendChild(empty);
      return;
    }

    items.forEach((item, index) => {
      const entry = document.createElement("li");
      entry.className = "dashboard-dev-todo-item";
      entry.innerHTML = `
        <span>${escapeHtml(item)}</span>
        <button type="button" data-dev-todo-remove="${index}">Remove</button>
      `;
      list.appendChild(entry);
    });
  };

  const load = () => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const items = raw ? JSON.parse(raw) : [];
      return Array.isArray(items) ? items : [];
    } catch (_) {
      return [];
    }
  };

  const save = (items) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    render(items);
  };

  render(load());

  addButton.addEventListener("click", () => {
    const value = String(input.value || "").trim();
    if (!value) return;
    const items = load();
    items.push(value);
    input.value = "";
    save(items);
  });

  list.addEventListener("click", event => {
    const button = event.target.closest("button[data-dev-todo-remove]");
    if (!button) return;
    const index = Number(button.getAttribute("data-dev-todo-remove"));
    const items = load();
    if (!Number.isFinite(index)) return;
    items.splice(index, 1);
    save(items);
  });
}

function initializeDeveloperWorkspace() {
  const list = document.getElementById("developer-task-list");
  const addButton = document.getElementById("developer-task-add");
  if (!list || !addButton) return;

  const fields = {
    title: document.getElementById("developer-task-title"),
    owner: document.getElementById("developer-task-owner"),
    start: document.getElementById("developer-task-start"),
    deadline: document.getElementById("developer-task-deadline"),
    priority: document.getElementById("developer-task-priority"),
    status: document.getElementById("developer-task-status"),
    notes: document.getElementById("developer-task-notes")
  };
  const feedback = document.getElementById("developer-task-feedback");
  const formShell = document.querySelector(".dashboard-developer-form-shell");
  const STORAGE_KEY = "aavgo_developer_tasks";
  const STATUS_ORDER = [
    "To Do",
    "Doing",
    "Done"
  ];
  const STATUS_ALIAS_MAP = new Map([
    ["backlog", "To Do"],
    ["planned", "To Do"],
    ["to do", "To Do"],
    ["todo", "To Do"],
    ["doing", "Doing"],
    ["in progress", "Doing"],
    ["blocked", "Doing"],
    ["ready to deploy", "Doing"],
    ["done", "Done"],
    ["completed logs", "Done"]
  ]);

  const createTaskId = () => {
    if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
      return crypto.randomUUID();
    }
    return `task_${Date.now()}_${Math.random().toString(16).slice(2)}`;
  };

  const normalizeStatus = (status) => {
    const value = String(status || "").trim();
    const normalized = STATUS_ALIAS_MAP.get(value.toLowerCase()) || value;
    return STATUS_ORDER.includes(normalized) ? normalized : "To Do";
  };

  const normalizeTask = (item = {}) => ({
    id: String(item.id || createTaskId()),
    title: String(item.title || "").trim(),
    owner: String(item.owner || "").trim(),
    startDate: String(item.startDate ?? item.when ?? "").trim(),
    deadlineDate: String(item.deadlineDate ?? item.deadline ?? "").trim(),
    priority: String(item.priority || "Normal").trim(),
    status: normalizeStatus(item.status),
    notes: String(item.notes || "").trim()
  });

  const load = () => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(parsed)) return [];
      const normalized = parsed.map(entry => normalizeTask(entry));
      if (normalized.some((item, index) => String(parsed[index]?.id || "") !== item.id)) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized));
      }
      return normalized;
    } catch (_) {
      return [];
    }
  };

  const setFeedback = (message, isError = false) => {
    if (!feedback) return;
    feedback.textContent = message;
    feedback.classList.toggle("is-error", Boolean(isError));
  };

  const sortTasks = (items) => {
    const statusRank = new Map(STATUS_ORDER.map((status, index) => [status, index]));
    return [...items].sort((left, right) => {
      const leftRank = statusRank.get(normalizeStatus(left.status)) ?? 999;
      const rightRank = statusRank.get(normalizeStatus(right.status)) ?? 999;
      if (leftRank !== rightRank) return leftRank - rightRank;
      const leftDeadline = String(left.deadlineDate || "");
      const rightDeadline = String(right.deadlineDate || "");
      if (leftDeadline !== rightDeadline) return leftDeadline.localeCompare(rightDeadline);
      return String(left.title || "").localeCompare(String(right.title || ""));
    });
  };

  const renderTaskCard = (item) => `
    <article class="dashboard-developer-task-card" data-task-id="${escapeHtml(item.id)}">
      <div class="dashboard-developer-task-labels">
        <span class="dashboard-chip dashboard-chip-accent">${escapeHtml(item.status || "To Do")}</span>
        <span class="dashboard-chip">${escapeHtml(item.priority || "Normal")}</span>
      </div>
      <div class="dashboard-developer-task-top">
        <div>
          <strong>${escapeHtml(item.title || "Untitled card")}</strong>
          <p class="dashboard-developer-task-meta">
            Owner: ${escapeHtml(item.owner || "Unassigned")}
            ${item.startDate ? ` &middot; Starts ${escapeHtml(item.startDate)}` : " &middot; Starts anytime"}
            ${item.deadlineDate ? ` &middot; Deadline ${escapeHtml(item.deadlineDate)}` : ""}
          </p>
        </div>
        <button type="button" class="dashboard-developer-task-remove" data-developer-task-remove="${escapeHtml(item.id)}" aria-label="Remove task">×</button>
      </div>
      <p class="dashboard-developer-task-notes">${escapeHtml(item.notes || "No notes yet.")}</p>
      <label class="dashboard-control-field">
        <span>Move card</span>
        <select data-developer-task-status-change="${escapeHtml(item.id)}">
          ${STATUS_ORDER.map(status => `<option value="${escapeHtml(status)}"${normalizeStatus(item.status) === status ? " selected" : ""}>${escapeHtml(status)}</option>`).join("")}
        </select>
      </label>
    </article>
  `;

  const renderTaskLane = (status, items) => {
    const groupItems = items.filter(item => normalizeStatus(item.status) === status);
    const laneCopy = status === "To Do"
      ? "Ready to start."
      : status === "Doing"
        ? "Work in motion."
        : "Finished and ready to archive.";
    return `
      <section class="dashboard-developer-lane" data-developer-lane="${escapeHtml(status)}">
        <header class="dashboard-developer-lane-head">
          <div>
            <p class="dashboard-kicker">List</p>
            <h3>${escapeHtml(status)}</h3>
            <p class="dashboard-developer-lane-copy">${escapeHtml(laneCopy)}</p>
          </div>
          <span class="dashboard-chip">${groupItems.length}</span>
        </header>
        <div class="dashboard-developer-task-group-list">
          ${groupItems.length ? groupItems.map(renderTaskCard).join("") : `
            <div class="dashboard-developer-lane-empty">
              <strong>No cards yet.</strong>
              <p>Start this list with a card for ${escapeHtml(status)}.</p>
            </div>
          `}
        </div>
        <button
          type="button"
          class="dashboard-developer-lane-add"
          data-developer-task-create-status="${escapeHtml(status)}"
          aria-label="Add card to ${escapeHtml(status)}"
        >+ Add a card</button>
      </section>
    `;
  };

  const render = (items) => {
    const sorted = sortTasks(items.map(normalizeTask));
    list.innerHTML = `
      <div class="dashboard-developer-board">
        ${STATUS_ORDER.map(status => renderTaskLane(status, sorted)).join("")}
      </div>
    `;
    if (!sorted.length) {
      const emptyBoard = list.querySelector(".dashboard-developer-board");
      if (emptyBoard) {
        emptyBoard.insertAdjacentHTML("beforebegin", `
          <div class="dashboard-empty-state">
            <strong>No developer tasks yet.</strong>
            <p>Add the first roadmap item, owner, deadline, and urgency.</p>
          </div>
        `);
      }
    }
  };

  const save = (items) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    render(items);
  };

  const openTaskFormForStatus = (status = "") => {
    if (fields.status && STATUS_ORDER.includes(status)) {
      fields.status.value = status;
    }
    if (formShell) {
      formShell.scrollIntoView({ behavior: "smooth", block: "start" });
    }
    window.setTimeout(() => {
      fields.title?.focus();
    }, 320);
  };

  render(load());

  addButton.addEventListener("click", () => {
    const title = String(fields.title?.value || "").trim();
    const deadlineDate = String(fields.deadline?.value || "").trim();
    if (!title) {
      setFeedback("Task name is required before adding it.", true);
      return;
    }
    if (!deadlineDate) {
      setFeedback("Deadline is required so the board can stay structured.", true);
      return;
    }
    const items = load();
    items.unshift({
      id: createTaskId(),
      title,
      owner: String(fields.owner?.value || "").trim(),
      startDate: String(fields.start?.value || "").trim(),
      deadlineDate,
      priority: String(fields.priority?.value || "Normal").trim(),
      status: normalizeStatus(fields.status?.value || "To Do"),
      notes: String(fields.notes?.value || "").trim()
    });
    Object.values(fields).forEach(field => {
      if (!field) return;
      if ("value" in field) field.value = "";
    });
    if (fields.priority) fields.priority.value = "Normal";
    if (fields.status) fields.status.value = "To Do";
    if (fields.deadline) fields.deadline.value = "";
    setFeedback("Task added to the board.", false);
    save(items);
  });

  list.addEventListener("click", event => {
    const createButton = event.target.closest("button[data-developer-task-create-status]");
    if (createButton) {
      openTaskFormForStatus(String(createButton.getAttribute("data-developer-task-create-status") || ""));
      setFeedback("Task form opened for that lane. Fill in the details below.", false);
      return;
    }

    const button = event.target.closest("button[data-developer-task-remove]");
    if (!button) return;
    const taskId = String(button.getAttribute("data-developer-task-remove") || "");
    const items = load();
    const nextItems = items.filter(item => String(item.id || "") !== taskId);
    if (nextItems.length === items.length) return;
    setFeedback("Task removed from the board.", false);
    save(nextItems);
  });

  list.addEventListener("change", event => {
    const select = event.target.closest("select[data-developer-task-status-change]");
    if (!select) return;
    const taskId = String(select.getAttribute("data-developer-task-status-change") || "");
    const items = load();
    const nextItems = items.map(item => {
      if (String(item.id || "") !== taskId) return item;
      return { ...item, status: normalizeStatus(select.value) };
    });
    setFeedback("Task status updated.", false);
    save(nextItems);
  });
}

initializeAdminBoard();
initializeLiveSignals();
initializeThemeToggle();
initializeSidebarToggle();
initializeToolbarMenu();
initializeDeveloperTodoList();
initializeDeveloperWorkspace();


