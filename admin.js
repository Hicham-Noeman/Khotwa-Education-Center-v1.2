const adminShell = document.querySelector("[data-admin-shell]");
const sidebarToggle = document.querySelector("[data-sidebar-toggle]");
const mobileSidebarToggle = document.querySelector("[data-mobile-sidebar-toggle]");
const sidebarScrim = document.querySelector("[data-sidebar-scrim]");
const sidebarStorageKey = "khotwa-v12-admin-sidebar-collapsed";

const translateAdmin = (value) => window.KhotwaI18n?.t(value) || value;

const setSidebarCollapsed = (collapsed) => {
  adminShell?.classList.toggle("is-sidebar-collapsed", collapsed);
  sidebarToggle?.setAttribute("aria-expanded", String(!collapsed));
  sidebarToggle?.setAttribute(
    "aria-label",
    translateAdmin(collapsed ? "Open navigation panel" : "Close navigation panel")
  );
};

const closeMobileSidebar = () => {
  adminShell?.classList.remove("is-mobile-sidebar-open");
  mobileSidebarToggle?.setAttribute("aria-expanded", "false");
};

if (adminShell && sidebarToggle) {
  setSidebarCollapsed(localStorage.getItem(sidebarStorageKey) === "true");

  sidebarToggle.addEventListener("click", () => {
    const collapsed = !adminShell.classList.contains("is-sidebar-collapsed");
    setSidebarCollapsed(collapsed);
    localStorage.setItem(sidebarStorageKey, String(collapsed));
  });
}

mobileSidebarToggle?.addEventListener("click", () => {
  const isOpen = adminShell?.classList.toggle("is-mobile-sidebar-open") || false;
  mobileSidebarToggle.setAttribute("aria-expanded", String(isOpen));
});

sidebarScrim?.addEventListener("click", closeMobileSidebar);

document.querySelectorAll(".admin-nav a").forEach((link) => {
  link.addEventListener("click", closeMobileSidebar);
});

const searchInput = document.querySelector("[data-table-search]");
const tableRows = [...document.querySelectorAll("[data-admin-table] tbody tr:not(.search-empty)")];
const emptySearchRow = document.querySelector(".search-empty");

document.querySelectorAll("[data-detail-url]").forEach((row) => {
  row.addEventListener("dblclick", () => {
    window.location.href = row.dataset.detailUrl;
  });
  row.addEventListener("keydown", (event) => {
    if (event.key === "Enter") window.location.href = row.dataset.detailUrl;
  });
});

searchInput?.addEventListener("input", () => {
  const query = searchInput.value.trim().toLocaleLowerCase();
  let visibleCount = 0;

  tableRows.forEach((row) => {
    const matches = row.textContent.toLocaleLowerCase().includes(query);
    row.hidden = !matches;
    if (matches) visibleCount += 1;
  });

  if (emptySearchRow) emptySearchRow.hidden = visibleCount !== 0;
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") closeMobileSidebar();
});

document.addEventListener("khotwa:languagechange", () => {
  const collapsed = adminShell?.classList.contains("is-sidebar-collapsed") || false;
  setSidebarCollapsed(collapsed);
  mobileSidebarToggle?.setAttribute("aria-label", translateAdmin("Open navigation panel"));
  sidebarScrim?.setAttribute("aria-label", translateAdmin("Close navigation panel"));
});
