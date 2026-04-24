const viewButtons = Array.from(document.querySelectorAll("[data-view]"));
const panels = Array.from(document.querySelectorAll("[data-panel]"));
const clock = document.querySelector("#revampClock");

function setView(viewName) {
  panels.forEach((panel) => {
    panel.classList.toggle("is-active", panel.dataset.panel === viewName);
  });

  viewButtons.forEach((button) => {
    button.classList.toggle("is-active", button.dataset.view === viewName);
  });
}

function updateClock() {
  if (!clock) return;

  const now = new Date();
  clock.textContent = new Intl.DateTimeFormat("en-PH", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
    timeZone: "Asia/Manila",
  }).format(now);
}

viewButtons.forEach((button) => {
  button.addEventListener("click", () => setView(button.dataset.view));
});

updateClock();
window.setInterval(updateClock, 30_000);
