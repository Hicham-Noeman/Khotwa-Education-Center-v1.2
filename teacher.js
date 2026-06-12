(() => {
const rosterRows = [...document.querySelectorAll("[data-roster-row]")];
const searchInput = document.querySelector("[data-roster-search]");
const searchEmpty = document.querySelector("[data-search-empty]");
const attendanceForm = document.querySelector("[data-attendance-form]");
const saveState = document.querySelector("[data-save-state]");

const updateMetrics = () => {
  let attended = 0;
  let missed = 0;

  rosterRows.forEach((row) => {
    const status = row.querySelector('input[type="radio"]:checked')?.value || "";
    if (status === "attended") attended += 1;
    if (status === "missed") missed += 1;
  });

  const attendedNode = document.querySelector("[data-attended-count]");
  const missedNode = document.querySelector("[data-missed-count]");
  const unmarkedNode = document.querySelector("[data-unmarked-count]");
  if (attendedNode) attendedNode.textContent = String(attended);
  if (missedNode) missedNode.textContent = String(missed);
  if (unmarkedNode) unmarkedNode.textContent = String(rosterRows.length - attended - missed);
};

const updateRowState = (row) => {
  const status = row.querySelector('input[type="radio"]:checked')?.value || "";
  const label = row.querySelector("[data-status-label]");
  row.dataset.status = status;
  if (label) {
    label.textContent =
      status === "attended" ? "Came" : status === "missed" ? "Did not come" : "Not marked";
  }
};

const markDirty = () => {
  if (!saveState) return;
  saveState.textContent = "You have unsaved attendance changes.";
  saveState.classList.add("is-dirty");
};

attendanceForm?.addEventListener("input", (event) => {
  const row = event.target.closest("[data-roster-row]");
  if (row) updateRowState(row);
  updateMetrics();
  markDirty();
});

document.querySelectorAll("[data-mark-all]").forEach((button) => {
  button.addEventListener("click", () => {
    const status = button.dataset.markAll;
    rosterRows.filter((row) => !row.hidden).forEach((row) => {
      const radio = row.querySelector(`input[type="radio"][value="${status}"]`);
      if (radio) radio.checked = true;
      updateRowState(row);
    });
    updateMetrics();
    markDirty();
  });
});

let searchFrame;
searchInput?.addEventListener("input", () => {
  window.cancelAnimationFrame(searchFrame);
  searchFrame = window.requestAnimationFrame(() => {
    const query = searchInput.value.trim().toLocaleLowerCase();
    let visibleCount = 0;

    rosterRows.forEach((row) => {
      const matches = row.textContent.toLocaleLowerCase().includes(query);
      row.hidden = !matches;
      if (matches) visibleCount += 1;
    });

    if (searchEmpty) searchEmpty.hidden = visibleCount !== 0;
  });
});

document.querySelector("[data-date-form]")?.addEventListener("change", (event) => {
  if (event.target.matches('input[type="date"], select')) {
    event.currentTarget.submit();
  }
});

updateMetrics();
})();
