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
const autosaveTimers = new Map();
const autosaveInFlight = new Set();
const attendanceDate = document.body.dataset.attendanceDate || "";
const attendanceCsrfInput = attendanceForm?.querySelector('input[name="csrf"]');
const attendanceCsrfToken = attendanceCsrfInput instanceof HTMLInputElement ? attendanceCsrfInput.value : "";
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

const autosaveAttendanceRow = async (row) => {
  if (!attendanceCsrfToken || !attendanceDate) return;

  const enrollmentId = row.dataset.enrollmentId || "";
  const status = getStatus(row);
  if (!enrollmentId || (status !== "attended" && status !== "missed")) {
    return;
  }

  if (autosaveInFlight.has(enrollmentId)) {
    return;
  }

  autosaveInFlight.add(enrollmentId);
  try {
    const payload = new URLSearchParams();
    payload.set("csrf", attendanceCsrfToken);
    payload.set("attendance_date", attendanceDate);
    payload.set("enrollment_id", enrollmentId);
    payload.set("status", status);
    payload.set("note", row.querySelector("[data-note-input]")?.value || "");
    payload.set("homework_note", row.querySelector("[data-homework-input]")?.value || "");

    const response = await fetch("subject-attendance-save.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: payload.toString(),
    });

    let result = null;
    try {
      result = await response.json();
    } catch {
      result = null;
    }

    if (!response.ok || !result?.success) {
      throw new Error(String(result?.error || "Autosave failed"));
    }
  } catch (error) {
    console.warn("Teacher attendance autosave failed:", error?.message || error);
  } finally {
    autosaveInFlight.delete(enrollmentId);
  }
};

const queueAttendanceAutosave = (row) => {
  const enrollmentId = row.dataset.enrollmentId || "";
  if (!enrollmentId) return;

  if (autosaveTimers.has(enrollmentId)) {
    window.clearTimeout(autosaveTimers.get(enrollmentId));
  }

  const timerId = window.setTimeout(() => {
    autosaveTimers.delete(enrollmentId);
    autosaveAttendanceRow(row);
  }, 650);
  autosaveTimers.set(enrollmentId, timerId);
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
  if (persist) queueAttendanceAutosave(row);
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
  const school = row.querySelector(".quick-row-school")?.textContent || "";

  card.innerHTML = `
    <span class="swipe-card-avatar">${avatar}</span>
    <strong class="swipe-card-name">${name}</strong>
    <small class="swipe-card-arabic">${arabic}</small>
    <span class="swipe-card-meta">${meta}</span>
    ${school ? `<span class="swipe-card-school">${school}</span>` : ""}
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

  /*
   * Every direction advances to the next student.
   *
   * The card leaves along the way it was flicked so the gesture still feels
   * answered, but no direction carries a meaning any more - a swipe cannot
   * record attendance, only the Came / Absent buttons can. That removes the
   * misfires the old axis lock caused, where a sideways flick that started
   * with a little vertical drift silently skipped instead of marking.
   */
  const skipCard = () => {
    const exit = gestureAxis === "horizontal"
      ? `translateX(${currentX > 0 ? 120 : -120}%) rotate(${currentX > 0 ? 12 : -12}deg)`
      : `translateY(${currentY > 0 ? 120 : -120}%)`;

    card.style.transition = "transform 0.22s ease, opacity 0.22s ease";
    card.style.transform = exit;
    card.style.opacity = "0";
    window.setTimeout(() => rotateDeck("next"), 200);
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
    } else {
      card.style.transform = `translateY(${currentY}px)`;
    }

    const travelled = gestureAxis === "horizontal" ? Math.abs(currentX) : Math.abs(currentY);
    card.classList.toggle("is-swipe-next", travelled > 40);
  });

  const finishSwipe = () => {
    if (!dragging) return;
    dragging = false;
    card.classList.remove("is-swipe-next");

    // A tap, or a drag too short to be meant as a swipe, leaves the card alone
    // rather than moving a student the teacher was only pointing at.
    const travelled = gestureAxis === "horizontal"
      ? Math.abs(currentX)
      : (gestureAxis === "vertical" ? Math.abs(currentY) : 0);

    if (travelled > 90) {
      skipCard();
      return;
    }
    resetPosition();
  };

  card.addEventListener("pointerup", finishSwipe);
  card.addEventListener("pointercancel", () => {
    dragging = false;
    gestureAxis = "";
    card.classList.remove("is-swipe-next");
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
      if (row) {
        persistAttendanceRow(row);
        queueAttendanceAutosave(row);
      }
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
  const finalSaveButton = submissionForm?.querySelector("[data-final-save]");
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

attendanceForm?.addEventListener("submit", () => {
  syncAllNoteFields();
  rosterRows.forEach(persistAttendanceRow);
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

/*
 * Behaviour flag draft.
 *
 * The flag form is a plain POST on a tab that teachers leave constantly - to
 * check a name on Students, or to finish marking attendance - and every one of
 * those trips used to wipe what they had typed. The fields are mirrored into
 * localStorage on each keystroke and put back when the tab is next opened, so
 * a half-written flag survives the round trip.
 *
 * The draft is cleared only once the server confirms the flag was filed, which
 * it does by redirecting back with ?flagged=1.
 */
const flagForm = document.querySelector("[data-flag-form]");

if (flagForm) {
  const flagDraftKey = "khotwa-teacher-flag-draft:" + (document.body.dataset.teacherId || "0");
  // Only the teacher's own words are worth restoring; the CSRF token is issued
  // per session and must always come from the freshly rendered form.
  const flagFields = ["student_id", "reason", "conversation_minutes", "notes"];

  const flagInput = (fieldName) => flagForm.elements.namedItem(fieldName);

  const clearFlagDraft = () => {
    try {
      localStorage.removeItem(flagDraftKey);
    } catch {
      /* Private browsing can refuse storage; the form still works without it. */
    }
  };

  const saveFlagDraft = () => {
    const draft = {};
    flagFields.forEach((fieldName) => {
      const field = flagInput(fieldName);
      if (field && field.value !== "") draft[fieldName] = field.value;
    });

    try {
      if (Object.keys(draft).length === 0) {
        localStorage.removeItem(flagDraftKey);
      } else {
        localStorage.setItem(flagDraftKey, JSON.stringify(draft));
      }
    } catch {
      /* Storage full or blocked - typing must not break because of it. */
    }
  };

  const restoreFlagDraft = () => {
    let draft = null;
    try {
      draft = JSON.parse(localStorage.getItem(flagDraftKey) || "null");
    } catch {
      draft = null;
    }
    if (!draft || typeof draft !== "object") return;

    flagFields.forEach((fieldName) => {
      const field = flagInput(fieldName);
      if (!field || typeof draft[fieldName] !== "string") return;

      // A student who is no longer assigned has no option left to select, so
      // the value is dropped rather than silently selecting nothing.
      if (field.tagName === "SELECT" && !field.querySelector(`option[value="${CSS.escape(draft[fieldName])}"]`)) {
        return;
      }
      field.value = draft[fieldName];
    });
  };

  if (new URLSearchParams(window.location.search).has("flagged")) {
    clearFlagDraft();
  } else {
    restoreFlagDraft();
  }

  flagForm.addEventListener("input", saveFlagDraft);
  flagForm.addEventListener("change", saveFlagDraft);

  // The redirect that follows a successful POST clears the draft on arrival;
  // dropping it here would lose the text if the request fails on the way.
  flagForm.addEventListener("submit", saveFlagDraft);
}
})();
