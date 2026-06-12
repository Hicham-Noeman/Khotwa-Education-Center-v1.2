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
const adminTable = document.querySelector("[data-admin-table]");
const tableRows = [...document.querySelectorAll("[data-record-row]")];
const emptySearchRow = document.querySelector(".search-empty");
const selectAllCheckbox = document.querySelector("[data-select-all]");
const bulkDeleteButton = document.querySelector("[data-bulk-delete]");
const tableActionsForm = document.querySelector("[data-table-actions]");

const isInteractiveTarget = (target) =>
  target.closest("a, button, input, select, textarea, label") !== null;

document.querySelectorAll("[data-detail-url]").forEach((row) => {
  row.addEventListener("dblclick", (event) => {
    if (isInteractiveTarget(event.target)) return;
    window.location.href = row.dataset.detailUrl;
  });
  row.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !isInteractiveTarget(event.target)) {
      window.location.href = row.dataset.detailUrl;
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
    bulkDeleteButton.textContent = selected.length
      ? `Delete selected (${selected.length})`
      : "Delete selected";
  }

  if (selectAllCheckbox) {
    selectAllCheckbox.checked =
      visible.length > 0 && selectedVisible.length === visible.length;
    selectAllCheckbox.indeterminate =
      selectedVisible.length > 0 && selectedVisible.length < visible.length;
  }
};

const filterTableRows = () => {
  const query = searchInput.value.trim().toLocaleLowerCase();
  let visibleCount = 0;

  tableRows.forEach((row) => {
    const matches = row.textContent.toLocaleLowerCase().includes(query);
    row.hidden = !matches;
    if (matches) visibleCount += 1;
  });

  if (emptySearchRow) emptySearchRow.hidden = visibleCount !== 0;
  updateSelectionControls();
};

let searchFrame;
searchInput?.addEventListener("input", () => {
  window.cancelAnimationFrame(searchFrame);
  searchFrame = window.requestAnimationFrame(filterTableRows);
});
searchInput?.addEventListener("keydown", (event) => {
  if (event.key === "Enter") event.preventDefault();
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

document.querySelectorAll("[data-sort-column]").forEach((button) => {
  button.addEventListener("click", () => {
    const columnIndex = Number(button.dataset.sortColumn);
    const currentDirection = button.dataset.sortDirection || "none";
    const direction = currentDirection === "ascending" ? "descending" : "ascending";
    const multiplier = direction === "ascending" ? 1 : -1;

    document.querySelectorAll("[data-sort-column]").forEach((otherButton) => {
      otherButton.dataset.sortDirection = "none";
      otherButton.closest("th")?.setAttribute("aria-sort", "none");
    });

    button.dataset.sortDirection = direction;
    button.closest("th")?.setAttribute("aria-sort", direction);

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
  });
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
        status.textContent = isEditing ? "Editing" : "Read only";
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
  } catch (error) {
    content.innerHTML =
      '<p class="linked-empty linked-load-error">These records could not be loaded. Close and reopen this section to try again.</p>';
  } finally {
    delete section.dataset.loading;
    content.removeAttribute("aria-busy");
  }
};

document.querySelectorAll("[data-linked-section]").forEach((section) => {
  section.addEventListener("toggle", () => {
    if (section.open) loadLinkedSection(section);
  });
  if (section.open) loadLinkedSection(section);
});

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
