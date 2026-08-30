// Weekly school-schedule planner: a Monday-to-Sunday grid where a session is
// painted by dragging over a day, or typed into the row above the grid. The whole
// week posts as one JSON payload, so the form still saves like any other record.
(() => {
  const DAYS = [
    ["monday", "Monday", "Mon"],
    ["tuesday", "Tuesday", "Tue"],
    ["wednesday", "Wednesday", "Wed"],
    ["thursday", "Thursday", "Thu"],
    ["friday", "Friday", "Fri"],
    ["saturday", "Saturday", "Sat"],
    ["sunday", "Sunday", "Sun"],
  ];

  const t = (text) => window.KhotwaI18n?.t?.(text) ?? text;

  const minutesToLabel = (minutes) => {
    const hour = Math.floor(minutes / 60);
    const suffix = hour < 12 ? "AM" : "PM";
    const shown = hour % 12 === 0 ? 12 : hour % 12;
    return `${shown}:${String(minutes % 60).padStart(2, "0")} ${suffix}`;
  };

  const minutesToClock = (minutes) =>
    `${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`;

  const clockToMinutes = (value) => {
    const parts = String(value || "").split(":");
    if (parts.length < 2) return null;
    const minutes = Number(parts[0]) * 60 + Number(parts[1]);
    return Number.isFinite(minutes) ? minutes : null;
  };

  const overlaps = (a, b) => a.day === b.day && a.start < b.end && a.end > b.start;

  const init = (planner) => {
    planner.dataset.plannerReady = "true";

    const grid = planner.querySelector("[data-schedule-grid]");
    const payloadInput = planner.querySelector("[data-schedule-payload]");
    const fieldset = planner.querySelector("[data-edit-fields]");
    const hint = planner.querySelector("[data-schedule-hint]");
    if (!grid || !payloadInput) return;

    let config = { start: 7 * 60, end: 21 * 60, step: 30 };
    try {
      config = { ...config, ...JSON.parse(planner.dataset.scheduleWindow || "{}") };
    } catch (error) {
      // Keep the defaults when the attribute is unreadable.
    }
    const slotCount = Math.max(1, Math.round((config.end - config.start) / config.step));

    const readBlocks = (raw) => {
      let parsed = [];
      try {
        parsed = JSON.parse(raw || "[]");
      } catch (error) {
        parsed = [];
      }
      return (Array.isArray(parsed) ? parsed : []).map((entry) => ({
        id: Number(entry.id) || 0,
        day: String(entry.day || ""),
        // The server sends "07:30:00"; a round-trip through the form sends minutes.
        start: typeof entry.start === "number" ? entry.start : clockToMinutes(entry.start),
        end: typeof entry.end === "number" ? entry.end : clockToMinutes(entry.end),
        note: String(entry.note || ""),
      }));
    };

    const initialBlocks = readBlocks(planner.dataset.scheduleBlocks);
    let blocks = initialBlocks.map((block) => ({ ...block }));

    const downloadButton = planner.querySelector("[data-schedule-download]");

    const isEditing = () => !fieldset || !fieldset.disabled;

    const say = (text, tone = "") => {
      if (!hint) return;
      hint.textContent = t(text);
      hint.className = `schedule-hint${tone ? ` is-${tone}` : ""}`;
    };

    const idleHint = () => {
      say(
        isEditing()
          ? "Drag down a day to add a session, right-click one to note it, or press × to remove it."
          : "Press Edit to change this schedule."
      );
    };

    const syncPayload = () => {
      payloadInput.value = JSON.stringify(
        blocks.map((block) => ({
          id: block.id,
          day: block.day,
          start: minutesToClock(block.start),
          end: minutesToClock(block.end),
          note: block.note,
        }))
      );
    };

    const buildGrid = () => {
      grid.replaceChildren();
      grid.style.setProperty("--schedule-slots", String(slotCount));

      const head = document.createElement("div");
      head.className = "schedule-head";
      head.append(document.createElement("span"));
      DAYS.forEach(([, label, short]) => {
        const cell = document.createElement("span");
        cell.className = "schedule-head-day";
        cell.innerHTML = `<b>${t(label)}</b><i>${t(short)}</i>`;
        head.append(cell);
      });
      grid.append(head);

      const body = document.createElement("div");
      body.className = "schedule-body";

      const axis = document.createElement("div");
      axis.className = "schedule-axis";
      for (let slot = 0; slot <= slotCount; slot += 1) {
        const minutes = config.start + slot * config.step;
        if (minutes % 60 !== 0) continue;
        const label = document.createElement("span");
        label.className = "schedule-axis-label";
        label.style.top = `calc(${slot} * var(--schedule-slot))`;
        label.textContent = minutesToLabel(minutes);
        axis.append(label);
      }
      body.append(axis);

      DAYS.forEach(([key]) => {
        const column = document.createElement("div");
        column.className = "schedule-day";
        column.dataset.day = key;
        for (let slot = 0; slot < slotCount; slot += 1) {
          const cell = document.createElement("span");
          cell.className = "schedule-slot";
          if ((config.start + slot * config.step) % 60 === 0) cell.classList.add("is-hour");
          column.append(cell);
        }
        const layer = document.createElement("div");
        layer.className = "schedule-blocks";
        layer.dataset.blocks = key;
        column.append(layer);
        body.append(column);
      });

      grid.append(body);
    };

    const renderBlocks = () => {
      grid.querySelectorAll("[data-blocks]").forEach((layer) => layer.replaceChildren());

      blocks.forEach((block, index) => {
        const layer = grid.querySelector(`[data-blocks="${block.day}"]`);
        if (!layer) return;

        const slots = (block.end - block.start) / config.step;
        const node = document.createElement("div");
        node.className = "schedule-block";
        // An hour or less has no room for a second line, so the time and the note
        // sit side by side there instead of the note being clipped away.
        if (slots <= 1) node.classList.add("is-compact");
        node.dataset.index = String(index);
        node.style.top = `calc(${(block.start - config.start) / config.step} * var(--schedule-slot))`;
        node.style.height = `calc(${(block.end - block.start) / config.step} * var(--schedule-slot))`;
        node.innerHTML = `<strong>${minutesToLabel(block.start)} – ${minutesToLabel(block.end)}</strong>`;
        if (block.note) {
          const note = document.createElement("small");
          note.textContent = block.note;
          node.append(note);
        }
        node.title = block.note || t("Right-click to add a note");

        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "schedule-block-remove";
        remove.setAttribute("aria-label", t("Remove session"));
        remove.textContent = "×";
        node.append(remove);
        layer.append(node);
      });

      syncPayload();
    };

    // A session is only added where the day is still free, so the grid can never
    // hold two sessions the server would reject as overlapping.
    const addBlock = (day, start, end, note) => {
      const candidate = { id: 0, day, start, end, note };
      if (blocks.some((block) => overlaps(block, candidate))) return false;
      blocks.push(candidate);
      blocks.sort((a, b) => a.start - b.start || a.day.localeCompare(b.day));
      return true;
    };

    // --- notes ---------------------------------------------------------------
    // Right-clicking a session opens a small note field over it. The note rides
    // along with the session and is saved with the rest of the week.
    let noteEditor = null;

    function closeNoteEditor() {
      noteEditor?.remove();
      noteEditor = null;
    }

    const openNoteEditor = (node, index) => {
      closeNoteEditor();
      const block = blocks[index];
      if (!block) return;

      noteEditor = document.createElement("div");
      noteEditor.className = "schedule-note-editor";
      noteEditor.innerHTML =
        `<label>${t("Note")}</label>` +
        '<input type="text" maxlength="255">' +
        `<div><button type="button" data-note-save>${t("Save note")}</button>` +
        `<button type="button" data-note-clear>${t("Clear")}</button></div>`;

      const field = noteEditor.querySelector("input");
      field.value = block.note;
      field.placeholder = t("What happens in this session?");

      // Sits beside the session it belongs to -- to its right, or to its left when
      // the session is against the far edge -- so the block stays readable.
      const width = 240;
      const gap = 10;
      const plannerBox = planner.getBoundingClientRect();
      const blockBox = node.getBoundingClientRect();
      const rightSide = blockBox.right - plannerBox.left + gap;
      const leftSide = blockBox.left - plannerBox.left - width - gap;
      noteEditor.style.left = `${Math.max(
        0,
        rightSide + width <= plannerBox.width ? rightSide : leftSide
      )}px`;
      noteEditor.style.top = `${Math.max(0, blockBox.top - plannerBox.top)}px`;
      planner.append(noteEditor);
      // A session low on the grid would push the popover past the bottom edge.
      const overflow = noteEditor.getBoundingClientRect().bottom - plannerBox.bottom;
      if (overflow > 0) {
        noteEditor.style.top = `${Math.max(0, blockBox.top - plannerBox.top - overflow)}px`;
      }
      field.focus();
      field.select();

      const apply = (value) => {
        blocks[index].note = value;
        closeNoteEditor();
        renderBlocks();
        say("Note saved on the session. Press Save schedule to keep it.", "good");
      };

      noteEditor.querySelector("[data-note-save]").addEventListener("click", () => apply(field.value.trim()));
      noteEditor.querySelector("[data-note-clear]").addEventListener("click", () => apply(""));
      field.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          apply(field.value.trim());
        }
        if (event.key === "Escape") closeNoteEditor();
      });
    };

    grid.addEventListener("contextmenu", (event) => {
      const node = event.target.closest(".schedule-block");
      if (!node) return;
      event.preventDefault();
      if (!isEditing()) {
        say("Press Edit before adding a note.", "warn");
        return;
      }
      openNoteEditor(node, Number(node.dataset.index));
    });

    // A click anywhere else puts the note field away.
    document.addEventListener("pointerdown", (event) => {
      if (noteEditor && !noteEditor.contains(event.target)) closeNoteEditor();
    });

    // --- painting on the grid ---------------------------------------------
    let drag = null;

    const slotFromEvent = (column, event) => {
      const box = column.getBoundingClientRect();
      const ratio = (event.clientY - box.top) / box.height;
      return Math.min(slotCount - 1, Math.max(0, Math.floor(ratio * slotCount)));
    };

    const paintPreview = (column, from, to) => {
      let preview = column.querySelector(".schedule-preview");
      if (!preview) {
        preview = document.createElement("div");
        preview.className = "schedule-preview";
        column.append(preview);
      }
      const top = Math.min(from, to);
      const span = Math.abs(to - from) + 1;
      preview.style.top = `calc(${top} * var(--schedule-slot))`;
      preview.style.height = `calc(${span} * var(--schedule-slot))`;
      preview.textContent = `${minutesToLabel(config.start + top * config.step)} – ${minutesToLabel(
        config.start + (top + span) * config.step
      )}`;
    };

    grid.addEventListener("pointerdown", (event) => {
      if (!isEditing() || event.button === 2) return;

      const removeButton = event.target.closest(".schedule-block-remove");
      if (removeButton) {
        const index = Number(removeButton.closest(".schedule-block").dataset.index);
        blocks.splice(index, 1);
        renderBlocks();
        say("Session removed. Press Save schedule to keep the change.", "good");
        return;
      }

      // A saved session is only removed or noted, never dragged over: painting
      // starts on empty grid, so a stray drag cannot rewrite a saved session.
      if (event.target.closest(".schedule-block")) return;

      const column = event.target.closest(".schedule-day");
      if (!column) return;
      event.preventDefault();
      closeNoteEditor();

      const slot = slotFromEvent(column, event);
      drag = { column, from: slot, to: slot, pointerId: event.pointerId };
      paintPreview(column, slot, slot);

      // The move and the release are watched on the window rather than on the
      // grid: a drag that ends off the column -- past the last day, over the
      // scrollbar, outside the panel -- still has to finish the session.
      window.addEventListener("pointermove", onDragMove);
      window.addEventListener("pointerup", endDrag);
      window.addEventListener("pointercancel", endDrag);
      window.addEventListener("blur", endDrag);
    });

    function onDragMove(event) {
      if (!drag || event.pointerId !== drag.pointerId) return;
      drag.to = slotFromEvent(drag.column, event);
      paintPreview(drag.column, drag.from, drag.to);
    }

    function endDrag(event) {
      if (!drag || (event?.pointerId !== undefined && event.pointerId !== drag.pointerId)) return;

      window.removeEventListener("pointermove", onDragMove);
      window.removeEventListener("pointerup", endDrag);
      window.removeEventListener("pointercancel", endDrag);
      window.removeEventListener("blur", endDrag);

      const { column, from, to } = drag;
      drag = null;
      column.querySelector(".schedule-preview")?.remove();

      const first = Math.min(from, to);
      const start = config.start + first * config.step;
      const end = start + (Math.abs(to - from) + 1) * config.step;

      if (addBlock(column.dataset.day, start, end, "")) {
        renderBlocks();
        say("Session set. Press Save schedule to keep it.", "good");
      } else {
        say("That time overlaps a session already on that day.", "warn");
      }
    }

    // --- edit-mode wiring --------------------------------------------------
    // Edit / Cancel live in admin.js and only toggle the fieldset, so the planner
    // watches that flag rather than duplicating the button handling.
    if (fieldset) {
      new MutationObserver(() => {
        planner.classList.toggle("is-editing", isEditing());
        renderBlocks();
        idleHint();
      }).observe(fieldset, { attributes: true, attributeFilter: ["disabled"] });
    }

    planner.addEventListener("reset", () => {
      closeNoteEditor();
      window.setTimeout(() => {
        blocks = initialBlocks.map((block) => ({ ...block }));
        renderBlocks();
      }, 0);
    });

    // --- PNG export --------------------------------------------------------
    downloadButton?.addEventListener("click", () => {
      const scale = 2;
      const padding = 28;
      const gutter = 74;
      const headHeight = 96;
      const dayHeight = 40;
      const slotHeight = 26;
      const columnWidth = 178;
      const width = padding * 2 + gutter + columnWidth * DAYS.length;
      const height = padding * 2 + headHeight + dayHeight + slotHeight * slotCount;

      const canvas = document.createElement("canvas");
      canvas.width = width * scale;
      canvas.height = height * scale;
      const context = canvas.getContext("2d");
      if (!context) return;
      context.scale(scale, scale);

      const font = (size, weight = 400) =>
        `${weight} ${size}px "RB", "Segoe UI", Tahoma, "Noto Sans Arabic", sans-serif`;

      context.fillStyle = "#ffffff";
      context.fillRect(0, 0, width, height);

      context.fillStyle = "#223f6b";
      context.font = font(30, 800);
      context.textBaseline = "alphabetic";
      context.fillText(planner.dataset.scheduleName || "Student", padding, padding + 34);
      context.fillStyle = "#6f7d91";
      context.font = font(14, 600);
      context.fillText(
        `Weekly school schedule · ${minutesToLabel(config.start)} – ${minutesToLabel(config.end)}`,
        padding,
        padding + 58
      );

      const gridTop = padding + headHeight;
      const gridLeft = padding + gutter;

      // Day header band.
      context.fillStyle = "#223f6b";
      roundRect(context, gridLeft, gridTop, columnWidth * DAYS.length, dayHeight, 10);
      context.fill();
      context.fillStyle = "#ffffff";
      context.font = font(14, 700);
      context.textAlign = "center";
      DAYS.forEach(([, label], index) => {
        context.fillText(label, gridLeft + columnWidth * (index + 0.5), gridTop + 26);
      });

      // Slot lines and the hour labels down the side.
      const bodyTop = gridTop + dayHeight;
      context.textAlign = "right";
      for (let slot = 0; slot <= slotCount; slot += 1) {
        const y = bodyTop + slot * slotHeight;
        const minutes = config.start + slot * config.step;
        const onHour = minutes % 60 === 0;
        context.strokeStyle = onHour ? "#d7dfea" : "#eef2f7";
        context.lineWidth = 1;
        context.beginPath();
        context.moveTo(gridLeft, y + 0.5);
        context.lineTo(gridLeft + columnWidth * DAYS.length, y + 0.5);
        context.stroke();
        if (onHour) {
          context.fillStyle = "#6f7d91";
          context.font = font(12, 700);
          context.fillText(minutesToLabel(minutes), gridLeft - 12, y + 4);
        }
      }

      context.strokeStyle = "#d7dfea";
      for (let index = 0; index <= DAYS.length; index += 1) {
        const x = gridLeft + columnWidth * index;
        context.beginPath();
        context.moveTo(x + 0.5, bodyTop);
        context.lineTo(x + 0.5, bodyTop + slotHeight * slotCount);
        context.stroke();
      }

      // Sessions.
      context.textAlign = "left";
      blocks.forEach((block) => {
        const dayIndex = DAYS.findIndex(([key]) => key === block.day);
        if (dayIndex < 0) return;
        const x = gridLeft + columnWidth * dayIndex + 4;
        const y = bodyTop + ((block.start - config.start) / config.step) * slotHeight + 2;
        const blockWidth = columnWidth - 8;
        const blockHeight = ((block.end - block.start) / config.step) * slotHeight - 4;

        context.fillStyle = "#223f6b";
        roundRect(context, x, y, blockWidth, blockHeight, 8);
        context.fill();

        const times = `${minutesToLabel(block.start)} – ${minutesToLabel(block.end)}`;
        context.fillStyle = "#ffffff";
        context.font = font(11, 700);

        if (blockHeight >= 34) {
          context.fillText(trimText(context, times, blockWidth - 18), x + 9, y + 17);
          if (block.note) {
            context.fillStyle = "#f6c66a";
            context.font = font(10, 600);
            context.fillText(trimText(context, block.note, blockWidth - 18), x + 9, y + 31);
          }
        } else {
          // A half-hour session is one line tall: the note rides beside the time,
          // and only the note is trimmed so the hours always stay readable.
          context.font = font(10, 700);
          context.fillText(times, x + 9, y + 15);
          if (block.note) {
            const used = context.measureText(times).width + 15;
            context.fillStyle = "#f6c66a";
            context.font = font(9, 600);
            context.fillText(trimText(context, block.note, blockWidth - used - 9), x + used, y + 15);
          }
        }
      });

      canvas.toBlob((blob) => {
        if (!blob) return;
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `${planner.dataset.scheduleFile || "schedule"}.png`;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 4000);
      }, "image/png");
    });

    buildGrid();
    renderBlocks();
    idleHint();
    planner.classList.toggle("is-editing", isEditing());
  };

  const roundRect = (context, x, y, width, height, radius) => {
    const r = Math.min(radius, width / 2, height / 2);
    context.beginPath();
    context.moveTo(x + r, y);
    context.arcTo(x + width, y, x + width, y + height, r);
    context.arcTo(x + width, y + height, x, y + height, r);
    context.arcTo(x, y + height, x, y, r);
    context.arcTo(x, y, x + width, y, r);
    context.closePath();
  };

  const trimText = (context, text, maxWidth) => {
    if (context.measureText(text).width <= maxWidth) return text;
    let trimmed = text;
    while (trimmed.length > 1 && context.measureText(`${trimmed}…`).width > maxWidth) {
      trimmed = trimmed.slice(0, -1);
    }
    return `${trimmed}…`;
  };

  const setup = (root = document) => {
    root
      .querySelectorAll("[data-schedule-planner]:not([data-planner-ready])")
      .forEach((planner) => init(planner));
  };

  window.KhotwaSchedulePlanner = { setup };
  setup();
})();
