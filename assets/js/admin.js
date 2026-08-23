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

// Table state (search text, sort, scroll) lives only in the browser, so opening a
// record and coming back would otherwise lose it. It is kept per view for this tab
// and replayed when the back link asks for it.
const tableViewName = new URLSearchParams(window.location.search).get("view") || "";
const tableStateKeyFor = (view) => (view ? `khotwa-table-state:${view}` : "");
const tableStateKey = tableStateKeyFor(tableViewName);

// A record page reads the state of the list it came from, which is not always the
// view in its own address, so the view being read is passed in.
const readTableState = (view = tableViewName) => {
  const key = tableStateKeyFor(view);
  if (!key) return null;
  try {
    return JSON.parse(sessionStorage.getItem(key) || "null");
  } catch (error) {
    return null;
  }
};

const writeTableState = (patch) => {
  if (!tableStateKey) return;
  try {
    sessionStorage.setItem(tableStateKey, JSON.stringify({ ...(readTableState() || {}), ...patch }));
  } catch (error) {
    /* private mode or storage disabled: the state simply is not remembered */
  }
};

const searchInput = document.querySelector("[data-table-search]");
const adminTable = document.querySelector("[data-admin-table]");
const tableRows = [...document.querySelectorAll("[data-record-row]")];
const emptySearchRow = document.querySelector(".search-empty");
const selectAllCheckbox = document.querySelector("[data-select-all]");
const bulkDeleteButton = document.querySelector("[data-bulk-delete]");
const tableActionsForm = document.querySelector("[data-table-actions]");

const isInteractiveTarget = (target) =>
  target.closest("a, button, input, select, textarea, label") !== null;

document.querySelectorAll("[data-detail-url]").forEach((row) => {
  const openRecord = () => {
    // The address of the list carries the server-side filters (?stage=) and the open
    // tab (#panel-...), so it is stored whole for the record's back button to reuse.
    writeTableState({
      scrollY: Math.round(window.scrollY),
      listUrl: window.location.pathname + window.location.search + window.location.hash,
    });
    window.location.href = row.dataset.detailUrl;
  };
  row.addEventListener("dblclick", (event) => {
    if (isInteractiveTarget(event.target)) return;
    openRecord();
  });
  row.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !isInteractiveTarget(event.target)) {
      openRecord();
    }
  });
});

const visibleRows = () => tableRows.filter((row) => !row.hidden);

const updateSelectionControls = () => {
  const visible = visibleRows();
  const selected = tableRows.filter(
    (row) => row.querySelector("[data-record-select]")?.checked
  );
  const selectedVisible = visible.filter(
    (row) => row.querySelector("[data-record-select]")?.checked
  );

  if (bulkDeleteButton) {
    bulkDeleteButton.disabled = selected.length === 0;
    const bulkLabel = t("Delete selected");
    bulkDeleteButton.textContent = selected.length
      ? `${bulkLabel} (${selected.length})`
      : bulkLabel;
  }

  if (selectAllCheckbox) {
    selectAllCheckbox.checked =
      visible.length > 0 && selectedVisible.length === visible.length;
    selectAllCheckbox.indeterminate =
      selectedVisible.length > 0 && selectedVisible.length < visible.length;
  }
};

// Labels written by script land after the translator has walked the page, so they
// have to ask for their own translation or they snap back to English.
const t = (value) => {
  const i18n = window.KhotwaI18n;
  return i18n ? i18n.t(value) : value;
};

const filterTableRows = () => {
  const query = searchInput.value.trim().toLocaleLowerCase();
  let visibleCount = 0;

  tableRows.forEach((row) => {
    const matches = row.textContent.toLocaleLowerCase().includes(query);
    row.hidden = !matches;
    if (matches) visibleCount += 1;
  });

  if (emptySearchRow) {
    emptySearchRow.hidden = visibleCount !== 0;
    const cell = emptySearchRow.firstElementChild;
    if (cell && searchInput?.dataset.searchUrl) {
      cell.textContent = t(
        query === ""
          ? "No matching records."
          : "Nothing on this page matches. Press Enter to search the whole table."
      );
    }
  }
  updateSelectionControls();
};

let searchFrame;
searchInput?.addEventListener("input", () => {
  window.cancelAnimationFrame(searchFrame);
  searchFrame = window.requestAnimationFrame(filterTableRows);
  writeTableState({ q: searchInput.value });
});
// Typing filters the rows already on screen, which is instant. Enter asks the
// server instead, so the search covers every row of the table and not just this page.
const submitServerSearch = () => {
  const searchUrl = searchInput?.dataset.searchUrl;
  if (!searchUrl) return;

  const term = searchInput.value.trim();
  window.location.href = searchUrl + (term === "" ? "" : "&q=" + encodeURIComponent(term));
};

searchInput?.addEventListener("keydown", (event) => {
  if (event.key !== "Enter") return;
  event.preventDefault();
  submitServerSearch();
});

selectAllCheckbox?.addEventListener("change", () => {
  visibleRows().forEach((row) => {
    const checkbox = row.querySelector("[data-record-select]");
    if (checkbox) checkbox.checked = selectAllCheckbox.checked;
  });
  updateSelectionControls();
});

document.querySelectorAll("[data-record-select]").forEach((checkbox) => {
  checkbox.addEventListener("change", updateSelectionControls);
});

// Pulled out of the click handler so a saved sort can be replayed on return.
const sortTableBy = (columnIndex, direction) => {
  const multiplier = direction === "ascending" ? 1 : -1;

  document.querySelectorAll("[data-sort-column]").forEach((otherButton) => {
    otherButton.dataset.sortDirection = "none";
    otherButton.closest("th")?.setAttribute("aria-sort", "none");
  });

  const button = document.querySelector(`[data-sort-column="${columnIndex}"]`);
  if (button) {
    button.dataset.sortDirection = direction;
    button.closest("th")?.setAttribute("aria-sort", direction);
  }

  tableRows
      .sort((leftRow, rightRow) => {
        const leftValue =
          leftRow.querySelectorAll("td[data-sort-value]")[columnIndex]?.dataset.sortValue || "";
        const rightValue =
          rightRow.querySelectorAll("td[data-sort-value]")[columnIndex]?.dataset.sortValue || "";
        const leftNumber = Number(leftValue.replaceAll(",", ""));
        const rightNumber = Number(rightValue.replaceAll(",", ""));

        if (
          leftValue !== "" &&
          rightValue !== "" &&
          Number.isFinite(leftNumber) &&
          Number.isFinite(rightNumber)
        ) {
          return (leftNumber - rightNumber) * multiplier;
        }

        return (
          leftValue.localeCompare(rightValue, undefined, {
            numeric: true,
            sensitivity: "base",
          }) * multiplier
        );
      })
    .forEach((row) => adminTable?.tBodies[0].insertBefore(row, emptySearchRow));
};

document.querySelectorAll("[data-sort-column]").forEach((button) => {
  button.addEventListener("click", () => {
    const columnIndex = Number(button.dataset.sortColumn);
    const direction =
      (button.dataset.sortDirection || "none") === "ascending" ? "descending" : "ascending";
    sortTableBy(columnIndex, direction);
    writeTableState({ sortColumn: columnIndex, sortDirection: direction });
  });
});

// Coming back from a record: put the table back the way it was left.
if (adminTable && new URLSearchParams(window.location.search).has("restore")) {
  const saved = readTableState();
  if (saved) {
    // A search carried in the URL was already applied by the server, so the saved
    // local term must not fight it.
    const urlQuery = new URLSearchParams(window.location.search).get("q");
    if (searchInput && !urlQuery && typeof saved.q === "string") {
      searchInput.value = saved.q;
      filterTableRows();
    }
    if (Number.isInteger(saved.sortColumn) && saved.sortDirection) {
      sortTableBy(saved.sortColumn, saved.sortDirection);
    }
    if (typeof saved.scrollY === "number") {
      window.requestAnimationFrame(() => window.scrollTo({ top: saved.scrollY }));
    }
  }
  // Tidy the marker out of the address bar once it has been used.
  const cleaned = new URL(window.location.href);
  cleaned.searchParams.delete("restore");
  window.history.replaceState(null, "", cleaned.toString());
}

// The back button on a record page: aimed at the exact list address the record was
// opened from, so filters and the open tab survive the round trip. Its href already
// points at the plain view, which stays the answer when nothing was stored.
document.querySelectorAll("[data-back-to-view]").forEach((link) => {
  const saved = readTableState(link.dataset.backToView);
  if (typeof saved?.listUrl !== "string" || saved.listUrl === "") return;
  const target = new URL(saved.listUrl, window.location.href);
  target.searchParams.set("restore", "1");
  link.href = target.toString();
});

tableActionsForm?.addEventListener("submit", (event) => {
  const submitter = event.submitter;
  if (submitter?.matches("[data-delete-record]")) {
    if (!window.confirm("Delete this record? This action cannot be undone.")) {
      event.preventDefault();
    }
    return;
  }

  if (submitter?.matches("[data-bulk-delete]")) {
    const selectedCount = tableRows.filter(
      (row) => row.querySelector("[data-record-select]")?.checked
    ).length;
    if (
      selectedCount === 0 ||
      !window.confirm(
        `Delete ${selectedCount} selected record${selectedCount === 1 ? "" : "s"}? This action cannot be undone.`
      )
    ) {
      event.preventDefault();
    }
    return;
  }

  event.preventDefault();
});

const setupEditForms = (root = document) => {
  root.querySelectorAll("[data-edit-form]:not([data-edit-ready])").forEach((form) => {
    form.dataset.editReady = "true";
    const fieldset = form.querySelector("[data-edit-fields]");
    const editButton = form.querySelector("[data-edit-toggle]");
    const saveButton = form.querySelector("[data-save-record]");
    const cancelButton = form.querySelector("[data-cancel-edit]");
    const panel = form.closest(".data-panel, .linked-row");
    const status = panel?.querySelector("[data-edit-status]");

    const setEditMode = (isEditing) => {
      if (fieldset) fieldset.disabled = !isEditing;
      if (editButton) editButton.hidden = isEditing;
      if (saveButton) saveButton.hidden = !isEditing;
      if (cancelButton) cancelButton.hidden = !isEditing;
      if (status) {
        status.textContent = t(isEditing ? "Editing" : "Read only");
        status.classList.toggle("is-editing", isEditing);
      }

      if (isEditing) {
        fieldset
          ?.querySelector("input:not([type='hidden']):not(:disabled), select:not(:disabled), textarea:not(:disabled)")
          ?.focus();
      }
    };

    editButton?.addEventListener("click", () => setEditMode(true));
    cancelButton?.addEventListener("click", () => {
      form.reset();
      setEditMode(false);
    });
    form.addEventListener("submit", () => {
      if (fieldset) fieldset.disabled = false;
    });
  });
};

setupEditForms();

// Injected markup arrives after the translator has already walked the page, so a
// linked section opened while the site is in Arabic would stay in English.
const retranslate = () => {
  const i18n = window.KhotwaI18n;
  if (i18n) i18n.apply(i18n.current(), false);
};

const loadLinkedSection = async (section) => {
  if (section.dataset.loaded === "true" || section.dataset.loading === "true") return;

  const content = section.querySelector("[data-linked-content]");
  if (!content) return;

  section.dataset.loading = "true";
  content.setAttribute("aria-busy", "true");
  content.innerHTML = '<p class="linked-empty">Loading records...</p>';

  try {
    const response = await fetch(section.dataset.linkedUrl, {
      credentials: "same-origin",
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });
    if (!response.ok) throw new Error("Linked records request failed.");

    content.innerHTML = await response.text();
    section.dataset.loaded = "true";
    setupEditForms(content);
    retranslate();
  } catch (error) {
    content.innerHTML =
      '<p class="linked-empty linked-load-error">These records could not be loaded. Close and reopen this section to try again.</p>';
    retranslate();
  } finally {
    delete section.dataset.loading;
    content.removeAttribute("aria-busy");
  }
};

// Profile sections are tabs rather than a long scroll: one panel is visible at a
// time and a linked section loads its records the first time it is opened.
const setupProfileTabs = () => {
  const tabs = Array.from(document.querySelectorAll("[data-profile-tab]"));
  if (tabs.length === 0) return;

  const panels = Array.from(document.querySelectorAll("[data-profile-panel]"));

  const activate = (key, { moveFocus = false } = {}) => {
    const tab = tabs.find((item) => item.dataset.profileTab === key);
    if (!tab) return;

    tabs.forEach((item) => {
      const isActive = item === tab;
      item.classList.toggle("is-active", isActive);
      item.setAttribute("aria-selected", String(isActive));
    });

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.profilePanel !== key;
    });

    const panel = panels.find((item) => item.dataset.profilePanel === key);
    if (panel?.hasAttribute("data-linked-section")) loadLinkedSection(panel);
    if (moveFocus) tab.focus();

    // The strip scrolls sideways. Nudging its scrollLeft directly keeps the chosen
    // tab in view without scrollIntoView's side effect of scrolling the page too.
    const strip = tab.parentElement;
    if (strip) {
      const tabBox = tab.getBoundingClientRect();
      const stripBox = strip.getBoundingClientRect();
      const margin = 14;
      if (tabBox.left < stripBox.left) {
        strip.scrollLeft -= stripBox.left - tabBox.left + margin;
      } else if (tabBox.right > stripBox.right) {
        strip.scrollLeft += tabBox.right - stripBox.right + margin;
      }
    }

    // Keeps the section in the URL so a reload or a shared link lands in the same place.
    const url = new URL(window.location.href);
    url.hash = key === "main" ? "" : `panel-${key}`;
    window.history.replaceState(null, "", url.toString());
  };

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => activate(tab.dataset.profileTab));
  });

  // Left/right arrows walk the tab strip, like a normal tab list.
  document.querySelector("[role=tablist]")?.addEventListener("keydown", (event) => {
    const step = event.key === "ArrowRight" ? 1 : event.key === "ArrowLeft" ? -1 : 0;
    if (step === 0) return;
    event.preventDefault();
    const current = tabs.findIndex((item) => item.classList.contains("is-active"));
    const next = tabs[(current + step + tabs.length) % tabs.length];
    activate(next.dataset.profileTab, { moveFocus: true });
  });

  const fromHash = window.location.hash.replace(/^#(panel-|linked-)/, "");
  if (fromHash && tabs.some((item) => item.dataset.profileTab === fromHash)) {
    activate(fromHash);
    return;
  }

  // Load whichever panel the server rendered as active.
  const active = panels.find((panel) => !panel.hidden && panel.hasAttribute("data-linked-section"));
  if (active) loadLinkedSection(active);
};

setupProfileTabs();

// Status toasts: dismiss themselves after a moment, or on click. Hovering keeps a
// toast on screen so a message is never yanked away mid-read.
// Any button carrying data-confirm asks before it submits. Used by the warnings
// board, where completing a warning deletes it for good.
document.addEventListener("submit", (event) => {
  const submitter = event.submitter;
  const message = submitter?.getAttribute?.("data-confirm");
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
}, true);

const setupToasts = () => {
  document.querySelectorAll("[data-toast]").forEach((toast) => {
    const timeout = Number(toast.dataset.toastTimeout) || 4500;
    let timer = null;

    const dismiss = () => {
      window.clearTimeout(timer);
      toast.classList.add("is-leaving");
      toast.addEventListener("animationend", () => toast.remove(), { once: true });
      // Fallback if the animation never fires (reduced motion, background tab).
      window.setTimeout(() => toast.remove(), 600);
    };

    const start = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(dismiss, timeout);
    };

    toast.querySelector("[data-toast-close]")?.addEventListener("click", dismiss);
    toast.addEventListener("mouseenter", () => window.clearTimeout(timer));
    toast.addEventListener("mouseleave", start);
    toast.addEventListener("focusin", () => window.clearTimeout(timer));
    toast.addEventListener("focusout", start);
    start();
  });
};

setupToasts();

updateSelectionControls();

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") closeMobileSidebar();
});

document.addEventListener("khotwa:languagechange", () => {
  const collapsed = adminShell?.classList.contains("is-sidebar-collapsed") || false;
  setSidebarCollapsed(collapsed);
  mobileSidebarToggle?.setAttribute("aria-label", translateAdmin("Open navigation panel"));
  sidebarScrim?.setAttribute("aria-label", translateAdmin("Close navigation panel"));
});
