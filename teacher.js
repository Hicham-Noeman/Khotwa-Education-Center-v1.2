(() => {
const rosterRows = [...document.querySelectorAll("[data-roster-row]")];
const notesRows = [...document.querySelectorAll("[data-notes-row]")];
const searchInput = document.querySelector("[data-roster-search]");
const searchEmpty = document.querySelector("[data-search-empty]");
const attendanceForm = document.querySelector("[data-attendance-form]");
const submissionForm = document.querySelector("[data-submission-form]");
const reviewRows = [...document.querySelectorAll("[data-review-row]")];
const swipeStack = document.querySelector("[data-swipe-stack]");
const swipeZone = document.querySelector("[data-swipe-zone]");
const swipeEmpty = document.querySelector("[data-swipe-empty]");
const swipeActions = document.querySelector("[data-swipe-actions]");
const swipeCounter = document.querySelector("[data-swipe-counter]");
const notesEmpty = document.querySelector("[data-notes-empty]");
const progressFill = document.querySelector("[data-progress-fill]");
let currentSelectedLetter = 'ALL';
let activeList = [];
let deckOrder = rosterRows.map((row) => row.dataset.enrollmentId);
const draftKey = [
  "khotwa-teacher-attendance-draft",
  document.body.dataset.teacherId || "0",
  document.body.dataset.attendanceDate || "",
].join(":");

const readDraft = () => {
  try {
    return JSON.parse(localStorage.getItem(draftKey) || "{}");
  } catch {
    return {};
  }
};

const writeDraft = (draft) => {
  localStorage.setItem(draftKey, JSON.stringify(draft));
};

const saveDraftEntry = (enrollmentId, entry) => {
  const draft = readDraft();
  draft[enrollmentId] = {
    ...(draft[enrollmentId] || {}),
    ...entry,
    updatedAt: new Date().toISOString(),
  };
  writeDraft(draft);
};

if (document.body.dataset.clearAttendanceDraft === "true") {
  localStorage.removeItem(draftKey);
}

const getStatus = (row) => row.querySelector('input[type="radio"]:checked')?.value || "";

const getStudentLetter = (row) => {
  const name = row.querySelector(".quick-row-copy strong")?.textContent || "";
  const firstChar = name.trim().charAt(0).toUpperCase();
  return (firstChar >= 'A' && firstChar <= 'Z') ? firstChar : '';
};

const renderLetterFilterBar = () => {
  const container = document.querySelector("[data-letter-filter-container]");
  if (!container) return;

  const letters = new Set();
  rosterRows.forEach(row => {
    const letter = getStudentLetter(row);
    if (letter) {
      letters.add(letter);
    }
  });

  const sortedLetters = Array.from(letters).sort();

  if (currentSelectedLetter !== 'ALL' && !sortedLetters.includes(currentSelectedLetter)) {
    currentSelectedLetter = 'ALL';
  }

  container.innerHTML = "";

  const allBtn = document.createElement("button");
  allBtn.type = "button";
  allBtn.className = `letter-btn ${currentSelectedLetter === 'ALL' ? 'is-active' : ''}`;
  allBtn.textContent = "All";
  allBtn.addEventListener("click", () => {
    currentSelectedLetter = 'ALL';
    applyFilters();
  });
  container.appendChild(allBtn);

  sortedLetters.forEach(letter => {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = `letter-btn ${currentSelectedLetter === letter ? 'is-active' : ''}`;
    btn.textContent = letter;
    btn.addEventListener("click", () => {
      currentSelectedLetter = letter;
      applyFilters();
    });
    container.appendChild(btn);
  });
};

const applyFilters = () => {
  const query = searchInput ? searchInput.value.trim().toLowerCase() : "";
  let visibleCount = 0;

  renderLetterFilterBar();

  activeList = [];
  const deckPosition = new Map(deckOrder.map((enrollmentId, index) => [enrollmentId, index]));

  rosterRows.forEach(row => {
    const studentLetter = getStudentLetter(row);
    const haystack = (row.dataset.searchText || row.textContent).toLowerCase();
    const matchesLetter = currentSelectedLetter === 'ALL' || studentLetter === currentSelectedLetter;
    const matchesSearch = query === "" || haystack.includes(query);

    const isVisible = matchesLetter && matchesSearch;
    row.hidden = !isVisible;

    if (isVisible) {
      visibleCount++;
    }
    if (isVisible && getStatus(row) === "") {
      activeList.push(row);
    }
  });
  activeList.sort(
    (left, right) =>
      (deckPosition.get(left.dataset.enrollmentId) ?? 0)
      - (deckPosition.get(right.dataset.enrollmentId) ?? 0)
  );

  if (searchEmpty) {
    searchEmpty.hidden = visibleCount !== 0;
  }

  renderCardStack();
};

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

const persistAttendanceRow = (row) => {
  saveDraftEntry(row.dataset.enrollmentId, {
    status: getStatus(row),
    note: row.querySelector("[data-note-input]")?.value || "",
    homeworkNote: row.querySelector("[data-homework-input]")?.value || "",
  });
};

const setRowStatus = (row, status, persist = true) => {
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
  if (persist) persistAttendanceRow(row);
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

const rotateDeck = (direction) => {
  if (activeList.length < 2) return;

  const activeIds = activeList.map((row) => row.dataset.enrollmentId);
  const activeSet = new Set(activeIds);
  const activeSlots = deckOrder
    .map((enrollmentId, index) => activeSet.has(enrollmentId) ? index : -1)
    .filter((index) => index !== -1);

  if (direction === "previous") {
    activeIds.unshift(activeIds.pop());
  } else {
    activeIds.push(activeIds.shift());
  }

  activeSlots.forEach((slot, index) => {
    deckOrder[slot] = activeIds[index];
  });
  applyFilters();
};

const buildSwipeCard = (row, isTop) => {
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

  if (!isTop) return card;

  let startX = 0;
  let startY = 0;
  let currentX = 0;
  let currentY = 0;
  let dragging = false;
  let gestureAxis = "";

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
      applyFilters();
    }, 220);
  };

  const skipCard = (direction) => {
    const translateY = direction === "previous" ? 120 : -120;
    card.style.transition = "transform 0.22s ease, opacity 0.22s ease";
    card.style.transform = `translateY(${translateY}%)`;
    card.style.opacity = "0";
    window.setTimeout(() => rotateDeck(direction), 200);
  };

  card.addEventListener("pointerdown", (event) => {
    if (!event.isPrimary) return;
    dragging = true;
    gestureAxis = "";
    startX = event.clientX;
    startY = event.clientY;
    currentX = 0;
    currentY = 0;
    card.style.transition = "";
  });

  card.addEventListener("pointermove", (event) => {
    if (!dragging) return;
    currentX = event.clientX - startX;
    currentY = event.clientY - startY;

    if (gestureAxis === "") {
      if (Math.max(Math.abs(currentX), Math.abs(currentY)) < 8) return;
      gestureAxis = Math.abs(currentX) > Math.abs(currentY) ? "horizontal" : "vertical";
      card.setPointerCapture(event.pointerId);
    }

    if (gestureAxis === "horizontal") {
      const rotate = currentX * 0.04;
      card.style.transform = `translateX(${currentX}px) rotate(${rotate}deg)`;
      card.classList.toggle("is-swipe-right", currentX > 40);
      card.classList.toggle("is-swipe-left", currentX < -40);
      card.classList.remove("is-swipe-up", "is-swipe-down");
    } else {
      card.style.transform = `translateY(${currentY}px)`;
      card.classList.toggle("is-swipe-up", currentY < -40);
      card.classList.toggle("is-swipe-down", currentY > 40);
      card.classList.remove("is-swipe-right", "is-swipe-left");
    }
  });

  const finishSwipe = () => {
    if (!dragging) return;
    dragging = false;
    card.classList.remove("is-swipe-right", "is-swipe-left", "is-swipe-up", "is-swipe-down");

    if (gestureAxis === "") {
      resetPosition();
      return;
    }

    if (gestureAxis === "horizontal") {
      if (currentX > 90) {
        dismissCard("attended", 1);
        return;
      }
      if (currentX < -90) {
        dismissCard("missed", -1);
        return;
      }
    } else {
      if (currentY < -90) {
        skipCard("next");
        return;
      }
      if (currentY > 90) {
        skipCard("previous");
        return;
      }
    }
    resetPosition();
  };

  card.addEventListener("pointerup", finishSwipe);
  card.addEventListener("pointercancel", () => {
    dragging = false;
    gestureAxis = "";
    card.classList.remove("is-swipe-right", "is-swipe-left", "is-swipe-up", "is-swipe-down");
    resetPosition();
  });

  return card;
};

const renderCardStack = () => {
  if (!swipeStack) return;

  swipeStack.innerHTML = "";
  const stackItems = activeList.slice(0, 3);

  if (stackItems.length === 0) {
    const remainingCount = rosterRows.filter((row) => getStatus(row) === "").length;
    const emptyTitle = document.querySelector("[data-swipe-empty-title]");
    const emptyMessage = document.querySelector("[data-swipe-empty-message]");
    const notesButton = document.querySelector("[data-go-notes]");
    if (emptyTitle) {
      emptyTitle.textContent = remainingCount === 0
        ? "All students marked"
        : "No unmarked students for this letter";
    }
    if (emptyMessage) {
      emptyMessage.textContent = remainingCount === 0
        ? "Add any attendance notes, then save."
        : "Choose All or another letter to continue.";
    }
    if (notesButton) notesButton.hidden = remainingCount !== 0;
    swipeStack.hidden = true;
    if (swipeEmpty) swipeEmpty.hidden = false;
    if (swipeActions) swipeActions.hidden = true;
    return;
  }

  swipeStack.hidden = false;
  if (swipeEmpty) swipeEmpty.hidden = true;
  if (swipeActions) swipeActions.hidden = false;

  for (let i = stackItems.length - 1; i >= 0; i--) {
    const row = stackItems[i];
    const card = buildSwipeCard(row, i === 0);
    card.style.zIndex = String(stackItems.length - i);
    const scale = 1 - i * 0.04;
    const translateY = i * 8;
    card.style.transform = `scale(${scale}) translateY(${translateY}px)`;
    swipeStack.appendChild(card);
  }

  swipeStack.dataset.activeId = stackItems.length ? stackItems[0].dataset.enrollmentId : "";
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
    applyFilters();
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
      applyFilters();
    });
  });

  row.addEventListener("keydown", (event) => {
    if (event.target !== row) return;
    if (event.key === "c" || event.key === "C") {
      event.preventDefault();
      setRowStatus(row, "attended");
      updateMetrics();
      applyFilters();
    }
    if (event.key === "a" || event.key === "A") {
      event.preventDefault();
      setRowStatus(row, "missed");
      updateMetrics();
      applyFilters();
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
document.querySelector("[data-go-mark]")?.addEventListener("click", () => setAttendanceMode("mark"));

document.querySelectorAll("[data-swipe-action]").forEach((button) => {
  button.addEventListener("click", () => markTopSwipeCard(button.dataset.swipeAction));
});

document.querySelectorAll("[data-mark-all]").forEach((button) => {
  button.addEventListener("click", () => {
    const status = button.dataset.markAll;
    rosterRows.filter((row) => !row.hidden).forEach((row) => setRowStatus(row, status));
    updateMetrics();
    applyFilters();
  });
});

document.querySelectorAll("[data-note-field], [data-homework-field]").forEach((field) => {
  field.addEventListener("input", () => {
    const enrollmentId = field.dataset.noteField || field.dataset.homeworkField;
    if (enrollmentId) {
      syncNoteFields(enrollmentId);
      const row = rosterRows.find((item) => item.dataset.enrollmentId === enrollmentId);
      if (row) persistAttendanceRow(row);
    }
  });
});

const hydrateAttendanceDraft = () => {
  const draft = readDraft();
  rosterRows.forEach((row) => {
    const entry = draft[row.dataset.enrollmentId];
    if (!entry) return;

    const noteInput = row.querySelector("[data-note-input]");
    const homeworkInput = row.querySelector("[data-homework-input]");
    const noteField = document.querySelector(`[data-note-field="${row.dataset.enrollmentId}"]`);
    const homeworkField = document.querySelector(`[data-homework-field="${row.dataset.enrollmentId}"]`);
    if (noteInput) noteInput.value = entry.note || "";
    if (homeworkInput) homeworkInput.value = entry.homeworkNote || "";
    if (noteField) noteField.value = entry.note || "";
    if (homeworkField) homeworkField.value = entry.homeworkNote || "";
    if (entry.status === "attended" || entry.status === "missed") {
      setRowStatus(row, entry.status, false);
    }
  });
};

const getReviewStatus = (row) =>
  row.querySelector('input[type="radio"]:checked')?.value || "";

const persistReviewRow = (row) => {
  saveDraftEntry(row.dataset.enrollmentId, {
    status: getReviewStatus(row),
    note: row.querySelector("[data-review-note]")?.value || "",
    homeworkNote: row.querySelector("[data-review-homework]")?.value || "",
  });
};

const setReviewStatus = (row, status, persist = true) => {
  const radio = row.querySelector(`input[type="radio"][value="${status}"]`);
  if (radio) radio.checked = true;
  row.dataset.status = status;
  row.querySelectorAll("[data-review-status]").forEach((button) => {
    button.setAttribute("aria-pressed", button.dataset.reviewStatus === status ? "true" : "false");
  });
  const label = row.querySelector("[data-review-status-label]");
  if (label) label.textContent = status === "attended" ? "Came" : status === "missed" ? "Absent" : "Not marked";
  if (persist) persistReviewRow(row);
};

const updateReviewMetrics = () => {
  let came = 0;
  let absent = 0;
  reviewRows.forEach((row) => {
    const status = getReviewStatus(row);
    if (status === "attended") came += 1;
    if (status === "missed") absent += 1;
  });
  const cameNode = document.querySelector("[data-review-came-count]");
  const absentNode = document.querySelector("[data-review-absent-count]");
  const unmarkedNode = document.querySelector("[data-review-unmarked-count]");
  const finalSaveButton = document.querySelector("[data-final-save]");
  if (cameNode) cameNode.textContent = String(came);
  if (absentNode) absentNode.textContent = String(absent);
  if (unmarkedNode) unmarkedNode.textContent = String(reviewRows.length - came - absent);
  if (finalSaveButton) finalSaveButton.disabled = came + absent === 0;
};

const hydrateSubmissionDraft = () => {
  const draft = readDraft();
  reviewRows.forEach((row) => {
    const entry = draft[row.dataset.enrollmentId];
    if (!entry) {
      setReviewStatus(row, getReviewStatus(row), false);
      return;
    }
    const note = row.querySelector("[data-review-note]");
    const homework = row.querySelector("[data-review-homework]");
    if (note) note.value = entry.note || "";
    if (homework) homework.value = entry.homeworkNote || "";
    if (entry.status === "attended" || entry.status === "missed") {
      setReviewStatus(row, entry.status, false);
    }
  });
  updateReviewMetrics();
};

reviewRows.forEach((row) => {
  row.querySelectorAll("[data-review-status]").forEach((button) => {
    button.addEventListener("click", () => {
      setReviewStatus(row, button.dataset.reviewStatus);
      updateReviewMetrics();
    });
  });
  row.querySelectorAll("[data-review-note], [data-review-homework]").forEach((field) => {
    field.addEventListener("input", () => persistReviewRow(row));
  });
});

submissionForm?.addEventListener("submit", () => {
  reviewRows.forEach(persistReviewRow);
});

let searchFrame;
searchInput?.addEventListener("input", () => {
  window.cancelAnimationFrame(searchFrame);
  searchFrame = window.requestAnimationFrame(() => {
    applyFilters();
  });
});

rosterRows.forEach((row) => setRowStatus(row, getStatus(row), false));
hydrateAttendanceDraft();
updateMetrics();
applyFilters();
hydrateSubmissionDraft();
})();
