(() => {
const rosterRows = [...document.querySelectorAll("[data-roster-row]")];
const notesRows = [...document.querySelectorAll("[data-notes-row]")];
const searchInput = document.querySelector("[data-roster-search]");
const searchEmpty = document.querySelector("[data-search-empty]");
const attendanceForm = document.querySelector("[data-attendance-form]");
const saveState = document.querySelector("[data-save-state]");
const swipeStack = document.querySelector("[data-swipe-stack]");
const swipeZone = document.querySelector("[data-swipe-zone]");
const swipeEmpty = document.querySelector("[data-swipe-empty]");
const swipeActions = document.querySelector("[data-swipe-actions]");
const swipeCounter = document.querySelector("[data-swipe-counter]");
const notesEmpty = document.querySelector("[data-notes-empty]");
const progressFill = document.querySelector("[data-progress-fill]");
const quickList = document.querySelector("[data-quick-list]");

const getStatus = (row) => row.querySelector('input[type="radio"]:checked')?.value || "";

const syncNoteFields = (enrollmentId) => {
  const row = rosterRows.find((item) => item.dataset.enrollmentId === enrollmentId);
  if (!row) return;

  const noteInput = row.querySelector("[data-note-input]");
  const homeworkInput = row.querySelector("[data-homework-input]");
  const noteField = document.querySelector(`[data-note-field="${enrollmentId}"]`);
  const homeworkField = document.querySelector(`[data-homework-field="${enrollmentId}"]`);
  if (noteField && noteInput) noteInput.value = noteField.value;
  if (homeworkField && homeworkInput) homeworkInput.value = homeworkField.value;
};

const syncAllNoteFields = () => {
  notesRows.forEach((notesRow) => syncNoteFields(notesRow.dataset.enrollmentId));
};

const setRowStatus = (row, status) => {
  const radio = row.querySelector(`input[type="radio"][value="${status}"]`);
  if (radio) radio.checked = true;

  row.dataset.status = status;
  row.querySelectorAll("[data-set-status]").forEach((button) => {
    button.setAttribute("aria-pressed", button.dataset.setStatus === status ? "true" : "false");
  });

  const enrollmentId = row.dataset.enrollmentId;
  const notesRow = notesRows.find((item) => item.dataset.enrollmentId === enrollmentId);
  if (notesRow) {
    notesRow.hidden = status === "";
    notesRow.dataset.status = status;
    const label = notesRow.querySelector("[data-notes-status-label]");
    if (label) {
      label.textContent = status === "attended" ? "Came" : status === "missed" ? "Did not come" : "Not marked";
    }
  }
};

const updateMetrics = () => {
  let attended = 0;
  let missed = 0;

  rosterRows.forEach((row) => {
    const status = getStatus(row);
    if (status === "attended") attended += 1;
    if (status === "missed") missed += 1;
  });

  const marked = attended + missed;
  const total = rosterRows.length;
  const unmarked = total - marked;

  const attendedNode = document.querySelector("[data-attended-count]");
  const missedNode = document.querySelector("[data-missed-count]");
  const unmarkedNode = document.querySelector("[data-unmarked-count]");
  if (attendedNode) attendedNode.textContent = String(attended);
  if (missedNode) missedNode.textContent = String(missed);
  if (unmarkedNode) unmarkedNode.textContent = String(unmarked);
  if (swipeCounter) swipeCounter.textContent = `${marked} / ${total}`;
  if (progressFill) progressFill.style.width = total > 0 ? `${Math.round((marked / total) * 100)}%` : "0%";
  if (notesEmpty) notesEmpty.hidden = marked > 0;
  if (swipeEmpty) swipeEmpty.hidden = unmarked > 0;
  if (swipeActions) swipeActions.hidden = unmarked === 0;
};

const markDirty = () => {
  if (!saveState) return;
  saveState.textContent = "You have unsaved changes.";
  saveState.classList.add("is-dirty");
};

const buildSwipeCard = (row) => {
  const card = document.createElement("article");
  card.className = "swipe-card";
  card.dataset.enrollmentId = row.dataset.enrollmentId;

  const avatar = row.querySelector(".quick-row-avatar")?.textContent || "?";
  const name = row.querySelector(".quick-row-copy strong")?.textContent || "";
  const arabic = row.querySelector(".quick-row-arabic")?.textContent || "";
  const meta = row.querySelector(".quick-row-meta")?.textContent || "";

  card.innerHTML = `
    <span class="swipe-card-avatar">${avatar}</span>
    <strong class="swipe-card-name">${name}</strong>
    <small class="swipe-card-arabic">${arabic}</small>
    <span class="swipe-card-meta">${meta}</span>
  `;

  let startX = 0;
  let currentX = 0;
  let dragging = false;

  const resetPosition = (animate = true) => {
    card.style.transition = animate ? "transform 0.22s ease, opacity 0.22s ease" : "";
    card.style.transform = "";
    card.style.opacity = "";
  };

  const dismissCard = (status, direction) => {
    card.style.transition = "transform 0.24s ease, opacity 0.24s ease";
    card.style.transform = `translateX(${direction * 120}%) rotate(${direction * 12}deg)`;
    card.style.opacity = "0";
    window.setTimeout(() => {
      setRowStatus(row, status);
      updateMetrics();
      markDirty();
      refreshSwipeStack();
    }, 220);
  };

  card.addEventListener("pointerdown", (event) => {
    if (!event.isPrimary) return;
    dragging = true;
    startX = event.clientX;
    currentX = 0;
    card.setPointerCapture(event.pointerId);
    card.style.transition = "";
  });

  card.addEventListener("pointermove", (event) => {
    if (!dragging) return;
    currentX = event.clientX - startX;
    const rotate = currentX * 0.04;
    card.style.transform = `translateX(${currentX}px) rotate(${rotate}deg)`;
    card.classList.toggle("is-swipe-right", currentX > 40);
    card.classList.toggle("is-swipe-left", currentX < -40);
  });

  const finishSwipe = () => {
    if (!dragging) return;
    dragging = false;
    card.classList.remove("is-swipe-right", "is-swipe-left");

    if (currentX > 90) {
      dismissCard("attended", 1);
      return;
    }
    if (currentX < -90) {
      dismissCard("missed", -1);
      return;
    }
    resetPosition();
  };

  card.addEventListener("pointerup", finishSwipe);
  card.addEventListener("pointercancel", finishSwipe);

  return card;
};

const refreshSwipeStack = () => {
  if (!swipeStack) return;

  swipeStack.innerHTML = "";
  const unmarked = rosterRows.filter((row) => !row.hidden && getStatus(row) === "");
  const stackItems = unmarked.slice(0, 3).reverse();

  stackItems.forEach((row, index) => {
    const card = buildSwipeCard(row);
    card.style.zIndex = String(index + 1);
    card.style.transform = index > 0 ? `scale(${1 - index * 0.04}) translateY(${index * 8}px)` : "";
    swipeStack.appendChild(card);
  });

  swipeStack.dataset.activeId = stackItems.length ? stackItems[stackItems.length - 1].dataset.enrollmentId : "";
};

const markTopSwipeCard = (status) => {
  const enrollmentId = swipeStack?.dataset.activeId;
  if (!enrollmentId) return;
  const row = rosterRows.find((item) => item.dataset.enrollmentId === enrollmentId);
  if (!row) return;

  const topCard = swipeStack.querySelector(".swipe-card:last-child");
  if (topCard) {
    topCard.style.transition = "transform 0.24s ease, opacity 0.24s ease";
    topCard.style.transform = `translateX(${status === "attended" ? 120 : -120}%) rotate(${status === "attended" ? 12 : -12}deg)`;
    topCard.style.opacity = "0";
  }

  window.setTimeout(() => {
    setRowStatus(row, status);
    updateMetrics();
    markDirty();
    refreshSwipeStack();
  }, 180);
};

const setAttendanceMode = (mode) => {
  document.querySelectorAll("[data-attendance-mode]").forEach((tab) => {
    const active = tab.dataset.attendanceMode === mode;
    tab.classList.toggle("is-active", active);
    tab.setAttribute("aria-selected", active ? "true" : "false");
  });

  document.querySelectorAll("[data-attendance-panel]").forEach((panel) => {
    const active = panel.dataset.attendancePanel === mode;
    panel.hidden = !active;
    panel.classList.toggle("is-active", active);
  });

  if (mode === "notes") syncAllNoteFields();
};

rosterRows.forEach((row) => {
  row.querySelectorAll("[data-set-status]").forEach((button) => {
    button.addEventListener("click", () => {
      setRowStatus(row, button.dataset.setStatus);
      updateMetrics();
      markDirty();
      refreshSwipeStack();
    });
  });

  row.addEventListener("keydown", (event) => {
    if (event.target !== row) return;
    if (event.key === "c" || event.key === "C") {
      event.preventDefault();
      setRowStatus(row, "attended");
      updateMetrics();
      markDirty();
      refreshSwipeStack();
    }
    if (event.key === "a" || event.key === "A") {
      event.preventDefault();
      setRowStatus(row, "missed");
      updateMetrics();
      markDirty();
      refreshSwipeStack();
    }
  });
});

document.querySelectorAll("[data-set-status]").forEach((button) => {
  button.addEventListener("click", (event) => {
    event.stopPropagation();
  });
});

document.querySelectorAll("[data-attendance-mode]").forEach((tab) => {
  tab.addEventListener("click", () => setAttendanceMode(tab.dataset.attendanceMode));
});

document.querySelector("[data-go-notes]")?.addEventListener("click", () => setAttendanceMode("notes"));

document.querySelectorAll("[data-swipe-action]").forEach((button) => {
  button.addEventListener("click", () => markTopSwipeCard(button.dataset.swipeAction));
});

document.querySelector("[data-swipe-list-toggle]")?.addEventListener("click", () => {
  swipeZone?.classList.toggle("show-list");
  const toggle = document.querySelector("[data-swipe-list-toggle]");
  if (toggle) {
    toggle.textContent = swipeZone?.classList.contains("show-list") ? "Use swipe" : "Show list";
  }
});

document.querySelectorAll("[data-mark-all]").forEach((button) => {
  button.addEventListener("click", () => {
    const status = button.dataset.markAll;
    rosterRows.filter((row) => !row.hidden).forEach((row) => setRowStatus(row, status));
    updateMetrics();
    markDirty();
    refreshSwipeStack();
  });
});

document.querySelectorAll("[data-note-field], [data-homework-field]").forEach((field) => {
  field.addEventListener("input", () => {
    const enrollmentId = field.dataset.noteField || field.dataset.homeworkField;
    if (enrollmentId) syncNoteFields(enrollmentId);
    markDirty();
  });
});

attendanceForm?.addEventListener("submit", () => {
  syncAllNoteFields();
});

let searchFrame;
searchInput?.addEventListener("input", () => {
  window.cancelAnimationFrame(searchFrame);
  searchFrame = window.requestAnimationFrame(() => {
    const query = searchInput.value.trim().toLocaleLowerCase();
    let visibleCount = 0;

    rosterRows.forEach((row) => {
      const haystack = row.dataset.searchText || row.textContent.toLocaleLowerCase();
      const matches = haystack.includes(query);
      row.hidden = !matches;
      if (matches) visibleCount += 1;
    });

    if (searchEmpty) searchEmpty.hidden = visibleCount !== 0;
    refreshSwipeStack();
  });
});

document.querySelector("[data-date-form]")?.addEventListener("change", (event) => {
  if (event.target.matches('input[type="date"], select')) {
    event.currentTarget.submit();
  }
});

rosterRows.forEach((row) => setRowStatus(row, getStatus(row)));
updateMetrics();
refreshSwipeStack();
})();
