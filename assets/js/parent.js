/*
 * Parent portal: the panels that are read a week at a time.
 *
 * Subject attendance, daily attendance and homework all behave the same way. The
 * page carries every row it was given and shows the week that is active, so
 * stepping back and forth never waits on the server, and nothing has to grow
 * wider than the screen: a row carries its own detail block, hidden beside it,
 * and opening the row lifts that block into the sheet.
 */
(() => {
  const panels = Array.from(document.querySelectorAll("[data-weekpanel]"));
  const sheet = document.querySelector("[data-weeksheet]");
  if (panels.length === 0 && !sheet) return;
  const sheetBody = sheet && sheet.querySelector("[data-weeksheet-body]");
  let sourceRow = null;

  // Mondays are compared as plain YYYY-MM-DD text, so a step goes out to a date
  // and back again rather than through anything a timezone can shift.
  const pad = (value) => String(value).padStart(2, "0");

  const dateFor = (monday, addDays) => {
    const [year, month, day] = monday.split("-").map(Number);
    return new Date(year, month - 1, day + addDays);
  };

  const asKey = (date) =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

  const asDayMonthYear = (date) =>
    `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;

  const closeSheet = () => {
    if (!sheet) return;
    sheet.hidden = true;
    document.body.classList.remove("weeksheet-open");
    if (sheetBody) sheetBody.replaceChildren();
    if (sourceRow) sourceRow.focus();
    sourceRow = null;
  };

  // The detail block lives in the page, so it is already in the language on
  // screen; the sheet shows a copy of it and takes a fresh one on a switch.
  const fillSheet = () => {
    if (!sourceRow || !sheetBody) return;
    const detail = sourceRow.querySelector("[data-weekpanel-detail]");
    if (!detail) return;
    const copy = detail.cloneNode(true);
    copy.hidden = false;
    copy.removeAttribute("data-weekpanel-detail");
    sheetBody.replaceChildren(copy);
  };

  const openSheet = (row) => {
    if (!sheet || !row.querySelector("[data-weekpanel-detail]")) return;
    sourceRow = row;
    fillSheet();
    sheet.hidden = false;
    document.body.classList.add("weeksheet-open");
    const close = sheet.querySelector("[data-weeksheet-close]");
    if (close) close.focus();
  };

  panels.forEach((panel) => {
    const rows = Array.from(panel.querySelectorAll("[data-weekpanel-row]"));
    const emptyRow = panel.querySelector("[data-weekpanel-empty]");
    const range = panel.querySelector("[data-weekpanel-range]");
    const nowButton = panel.querySelector("[data-weekpanel-now-button]");
    const thisWeek = panel.dataset.weekpanelNow || "";
    let active = thisWeek;

    /*
     * Billing is owed by the month, so it is stepped a year at a time: a panel
     * says which it is, and the only difference is how a step is taken and how
     * the period reads once it has been.
     */
    const byYear = panel.dataset.weekpanelUnit === "year";

    const step = (from, amount) =>
      byYear ? String(Number(from) + amount) : asKey(dateFor(from, amount * 7));

    const periodLabel = (from) =>
      byYear
        ? from
        // Saturday closes the week: the center does not run on Sunday.
        : `${asDayMonthYear(dateFor(from, 0))} – ${asDayMonthYear(dateFor(from, 5))}`;

    const render = () => {
      let shown = 0;
      rows.forEach((row) => {
        const matches = row.dataset.week === active;
        row.hidden = !matches;
        if (matches) shown += 1;
      });

      if (emptyRow) emptyRow.hidden = shown > 0;
      if (range) range.textContent = periodLabel(active);
      if (nowButton) nowButton.hidden = active === thisWeek;
    };

    panel.querySelectorAll("[data-weekpanel-step]").forEach((button) => {
      button.addEventListener("click", () => {
        active = step(active, Number(button.dataset.weekpanelStep) || 0);
        render();
      });
    });

    if (nowButton) {
      nowButton.addEventListener("click", () => {
        active = thisWeek;
        render();
      });
    }

    render();
  });

  /*
   * Opening a row is delegated, so a table that is not stepped through by period
   * - the warnings, which are answered rather than browsed by date - behaves the
   * same way just by marking its rows.
   */
  document.addEventListener("click", (event) => {
    if (event.target.closest("[data-weeksheet]")) return;
    const row = event.target.closest("[data-weekpanel-row]");
    if (row) openSheet(row);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;
    const row = event.target.closest && event.target.closest("[data-weekpanel-row]");
    if (!row) return;
    event.preventDefault();
    openSheet(row);
  });

  if (sheet) {
    // The backdrop closes it; a press inside the card does not.
    sheet.addEventListener("click", (event) => {
      if (event.target === sheet || event.target.closest("[data-weeksheet-close]")) closeSheet();
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !sheet.hidden) closeSheet();
    });
  }

  document.addEventListener("khotwa:languagechange", fillSheet);
})();
