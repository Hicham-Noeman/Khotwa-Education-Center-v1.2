(() => {
  const buildQrImageUrl = (text, format = "png", size = 512) => {
    const safeFormat = format === "jpg" || format === "jpeg" ? "jpg" : "png";
    // The remote service tops out at 1000x1000, which is still print-usable.
    const safeSize = Math.max(128, Math.min(1000, Number(size) || 512));
    return `https://api.qrserver.com/v1/create-qr-code/?size=${safeSize}x${safeSize}&format=${safeFormat}&color=223f6b&data=${encodeURIComponent(text)}`;
  };

  const toSafeFileBase = (value) => {
    const normalized = String(value || "student")
      .toLowerCase()
      .replace(/[^a-z0-9_-]+/g, "-")
      .replace(/^-+|-+$/g, "");
    return normalized || "student";
  };

  const payloadFromNode = (node) => {
    const payload = node.getAttribute("data-qr-payload") || "{}";
    try {
      const parsed = JSON.parse(payload);
      if (parsed && typeof parsed === "object") {
        return parsed;
      }
    } catch (error) {
      // Ignore malformed payload and use fallback below.
    }
    return {
      "Full Name in EN": "",
      "Full Name in AR": "",
      ID: "",
    };
  };

  const triggerDownload = (href, fileName) => {
    const link = document.createElement("a");
    link.href = href;
    link.download = fileName;
    document.body.append(link);
    link.click();
    link.remove();
  };

  // Downloads are meant for printing, so they are re-rendered at a much larger
  // size than the small on-screen preview instead of being upscaled from it.
  const PRINT_QR_SIZE = 2048;

  const QR_DARK = "#223f6b";
  const QR_LIGHT = "#ffffff";

  // The three 7x7 finder patterns are drawn as one shape; only the data modules
  // are drawn individually, which is what keeps a styled code readable.
  const isFinderModule = (row, col, count) =>
    (row < 7 && col < 7) || (row < 7 && col >= count - 7) || (row >= count - 7 && col < 7);

  // Soft-cornered squares, matching the rounded-rectangle language of the brand
  // marks rather than plain squares or full circles.
  const MODULE_RADIUS = 0.34;

  // Appends one rounded square to the current path; `corners` is [tl, tr, br, bl].
  const appendRoundedRect = (context, x, y, size, corners) => {
    const [tl, tr, br, bl] = corners;
    context.moveTo(x + tl, y);
    context.lineTo(x + size - tr, y);
    context.quadraticCurveTo(x + size, y, x + size, y + tr);
    context.lineTo(x + size, y + size - br);
    context.quadraticCurveTo(x + size, y + size, x + size - br, y + size);
    context.lineTo(x + bl, y + size);
    context.quadraticCurveTo(x, y + size, x, y + size - bl);
    context.lineTo(x, y + tl);
    context.quadraticCurveTo(x, y, x + tl, y);
    context.closePath();
  };

  const fillRoundedRect = (context, x, y, size, corners) => {
    context.beginPath();
    appendRoundedRect(context, x, y, size, corners);
    context.fill();
  };

  // node-qrcode only paints square modules, so the matrix is taken from
  // QRCode.create() and drawn by hand as rounded modules instead.
  const renderStyledQr = (canvas, text, { width, margin = 2 }) => {
    if (!window.QRCode || typeof window.QRCode.create !== "function") {
      return false;
    }

    let model = null;
    try {
      model = window.QRCode.create(text, { errorCorrectionLevel: "H" });
    } catch (error) {
      return false;
    }

    const count = model?.modules?.size || 0;
    const modules = model?.modules?.data;
    if (!count || !modules) {
      return false;
    }

    const context = canvas.getContext("2d");
    if (!context) {
      return false;
    }

    canvas.width = width;
    canvas.height = width;
    const unit = width / (count + margin * 2);
    const offset = margin * unit;
    const radius = unit * MODULE_RADIUS;

    context.fillStyle = QR_LIGHT;
    context.fillRect(0, 0, width, width);
    context.fillStyle = QR_DARK;

    const isDark = (row, col) =>
      row >= 0 &&
      col >= 0 &&
      row < count &&
      col < count &&
      !isFinderModule(row, col, count) &&
      Boolean(modules[row * count + col]);

    // Every module goes into one path and is filled once, so touching modules
    // merge cleanly instead of leaving anti-aliased seams between separate fills.
    context.beginPath();
    for (let row = 0; row < count; row += 1) {
      for (let col = 0; col < count; col += 1) {
        if (!isDark(row, col)) {
          continue;
        }
        const up = isDark(row - 1, col);
        const down = isDark(row + 1, col);
        const left = isDark(row, col - 1);
        const right = isDark(row, col + 1);
        // A corner only rounds where nothing touches it, so runs of modules
        // read as one continuous rounded bar instead of a line of beads.
        appendRoundedRect(context, offset + col * unit, offset + row * unit, unit, [
          !up && !left ? radius : 0,
          !up && !right ? radius : 0,
          !down && !right ? radius : 0,
          !down && !left ? radius : 0,
        ]);
      }
    }
    context.fill();

    const drawFinder = (startRow, startCol) => {
      const x = offset + startCol * unit;
      const y = offset + startRow * unit;
      // Ring: the 7x7 outline with the 5x5 hole punched out of the same path.
      context.beginPath();
      appendRoundedRect(context, x, y, unit * 7, Array(4).fill(unit * 2));
      appendRoundedRect(context, x + unit, y + unit, unit * 5, Array(4).fill(unit * 1.4));
      context.fill("evenodd");
      // Eye: a rounded 3x3 block centred in the ring.
      fillRoundedRect(context, x + unit * 2, y + unit * 2, unit * 3, Array(4).fill(unit * 0.9));
    };

    drawFinder(0, 0);
    drawFinder(0, count - 7);
    drawFinder(count - 7, 0);
    return true;
  };

  const renderPrintQrCanvas = (text) => {
    const printCanvas = document.createElement("canvas");
    return Promise.resolve(
      renderStyledQr(printCanvas, text, { width: PRINT_QR_SIZE, margin: 2 }) ? printCanvas : null
    );
  };

  // Fallback when the library cannot re-render: scale the preview up with
  // smoothing off so the modules stay hard-edged rather than blurry.
  const upscaleCanvas = (source, targetSize) => {
    const scaled = document.createElement("canvas");
    const factor = Math.max(1, Math.round(targetSize / source.width));
    scaled.width = source.width * factor;
    scaled.height = source.height * factor;
    const context = scaled.getContext("2d");
    if (!context) {
      return source;
    }
    context.imageSmoothingEnabled = false;
    context.fillStyle = "#ffffff";
    context.fillRect(0, 0, scaled.width, scaled.height);
    context.drawImage(source, 0, 0, scaled.width, scaled.height);
    return scaled;
  };

  const canvasToBlobUrl = (source, mimeType, quality) =>
    new Promise((resolve) => {
      if (typeof source.toBlob !== "function") {
        resolve(source.toDataURL(mimeType, quality));
        return;
      }
      source.toBlob(
        (blob) => resolve(blob ? URL.createObjectURL(blob) : source.toDataURL(mimeType, quality)),
        mimeType,
        quality
      );
    });

  const downloadUrlAsFile = async (url, fileName) => {
    try {
      const response = await fetch(url, { mode: "cors" });
      if (!response.ok) {
        throw new Error("Download request failed");
      }
      const blob = await response.blob();
      const objectUrl = URL.createObjectURL(blob);
      triggerDownload(objectUrl, fileName);
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 4000);
    } catch (error) {
      // A cross-origin URL ignores the download attribute, so a plain link would
      // navigate away and lose the page the admin was working on. Open a tab instead.
      window.open(url, "_blank", "noopener");
    }
  };

  const initStudentQrCards = () => {
    const qrCards = document.querySelectorAll("[data-student-qr]");
    if (qrCards.length === 0) {
      return;
    }

    qrCards.forEach((card) => {
      if (card.dataset.qrReady === "true") {
        return;
      }
      card.dataset.qrReady = "true";

      const qrContainer = card.querySelector("[data-qr-canvas]");
      if (!qrContainer) {
        return;
      }

      const payload = payloadFromNode(card);
      const payloadText = JSON.stringify(payload);
      let canvas = null;
      let fallbackImage = null;

      const previewCanvas = document.createElement("canvas");
      // Rendered at device resolution so the modules keep clean edges on retina screens.
      const previewScale = Math.min(3, Math.max(1, window.devicePixelRatio || 1));
      if (renderStyledQr(previewCanvas, payloadText, { width: 170 * previewScale, margin: 1 })) {
        previewCanvas.style.width = "170px";
        previewCanvas.style.height = "170px";
        canvas = previewCanvas;
        qrContainer.replaceChildren(canvas);
      } else {
        fallbackImage = document.createElement("img");
        fallbackImage.alt = "Student QR code";
        fallbackImage.loading = "lazy";
        fallbackImage.src = buildQrImageUrl(payloadText, "png", 340);
        fallbackImage.addEventListener("error", () => {
          qrContainer.textContent = "QR code could not be generated.";
        });
        qrContainer.replaceChildren(fallbackImage);
      }

      // One button opens the format menu; picking a format downloads and closes it.
      const menu = card.querySelector("[data-qr-menu]");
      const menuToggle = card.querySelector("[data-qr-menu-toggle]");
      const menuList = card.querySelector("[data-qr-menu-list]");

      const closeMenu = () => {
        if (!menuList) return;
        menuList.hidden = true;
        menuToggle?.setAttribute("aria-expanded", "false");
      };

      if (menuToggle && menuList) {
        menuToggle.addEventListener("click", (event) => {
          event.stopPropagation();
          const isOpen = !menuList.hidden;
          menuList.hidden = isOpen;
          menuToggle.setAttribute("aria-expanded", isOpen ? "false" : "true");
          if (!isOpen) {
            menuList.querySelector("button")?.focus();
          }
        });

        // Clicking elsewhere or pressing Escape puts the menu away again.
        document.addEventListener("click", (event) => {
          if (!menuList.hidden && !menu?.contains(event.target)) closeMenu();
        });
        document.addEventListener("keydown", (event) => {
          if (event.key === "Escape" && !menuList.hidden) {
            closeMenu();
            menuToggle.focus();
          }
        });
      }

      const fileBase = toSafeFileBase(card.getAttribute("data-qr-file-base"));
      card.querySelectorAll("[data-qr-download]").forEach((button) => {
        button.addEventListener("click", async () => {
          const format = String(button.getAttribute("data-qr-download") || "png").toLowerCase();
          const extension = format === "jpg" || format === "jpeg" ? "jpg" : "png";
          closeMenu();

          let printCanvas = await renderPrintQrCanvas(payloadText);
          if (!printCanvas && canvas instanceof HTMLCanvasElement) {
            printCanvas = upscaleCanvas(canvas, PRINT_QR_SIZE);
          }

          if (!printCanvas) {
            await downloadUrlAsFile(
              buildQrImageUrl(payloadText, extension, 1000),
              `${fileBase}.${extension}`
            );
            return;
          }

          if (extension === "jpg") {
            // JPG has no transparency, so paint the white background first.
            const jpgCanvas = document.createElement("canvas");
            jpgCanvas.width = printCanvas.width;
            jpgCanvas.height = printCanvas.height;
            const context = jpgCanvas.getContext("2d");
            if (!context) {
              return;
            }
            context.fillStyle = "#ffffff";
            context.fillRect(0, 0, jpgCanvas.width, jpgCanvas.height);
            context.drawImage(printCanvas, 0, 0);
            const jpgUrl = await canvasToBlobUrl(jpgCanvas, "image/jpeg", 1);
            triggerDownload(jpgUrl, `${fileBase}.jpg`);
            if (jpgUrl.startsWith("blob:")) {
              window.setTimeout(() => URL.revokeObjectURL(jpgUrl), 4000);
            }
            return;
          }

          const pngUrl = await canvasToBlobUrl(printCanvas, "image/png");
          triggerDownload(pngUrl, `${fileBase}.png`);
          if (pngUrl.startsWith("blob:")) {
            window.setTimeout(() => URL.revokeObjectURL(pngUrl), 4000);
          }
        });
      });
    });
  };

  const parseScanValue = (decodedText) => {
    const trimmed = String(decodedText || "").trim();
    if (!trimmed) {
      return null;
    }

    try {
      const parsed = JSON.parse(trimmed);
      const id = Number(parsed?.ID);
      if (Number.isInteger(id) && id > 0) {
        return {
          id,
          text: JSON.stringify(parsed),
          names: {
            en: String(parsed["Full Name in EN"] || ""),
            ar: String(parsed["Full Name in AR"] || ""),
          },
        };
      }
    } catch (error) {
      // Continue with fallback parsing.
    }

    const numericId = Number(trimmed);
    if (Number.isInteger(numericId) && numericId > 0) {
      return {
        id: numericId,
        text: trimmed,
        names: {
          en: "",
          ar: "",
        },
      };
    }

    return null;
  };

  const initQrScannerModal = () => {
    const modal = document.querySelector("[data-qr-scan-modal]");
    const openButton = document.querySelector("[data-qr-scan-open]");
    const reader = modal?.querySelector("[data-qr-reader]");
    const imageButton = modal?.querySelector("[data-qr-image-button]");
    const imageInput = modal?.querySelector("[data-qr-image-input]");
    const result = modal?.querySelector("[data-qr-scan-result]");
    const toast = modal?.querySelector("[data-qr-scan-toast]");
    const openStudentLink = modal?.querySelector("[data-qr-open-student]");
    const closeButtons = modal?.querySelectorAll("[data-qr-scan-close]");
    const csrf = modal?.getAttribute("data-qr-scan-csrf") || "";
    // The endpoint is supplied by the page so the modal works from any folder.
    const attendanceUrl = modal?.getAttribute("data-qr-scan-url") || "qr-attendance.php";
    const studentUrl = modal?.getAttribute("data-qr-student-url") || "person.php";
    // "lookup" on the overview: read the student's day and change nothing.
    // "attendance" on the attendance page: mark them in, as it always did.
    const scanMode = modal?.getAttribute("data-qr-scan-mode") === "lookup" ? "lookup" : "attendance";
    const lookupUrl = modal?.getAttribute("data-qr-lookup-url") || "student-day.php";
    const dossier = document.querySelector("[data-qr-dossier]");
    const dossierEmpty = document.querySelector("[data-qr-dossier-empty]");

    if (!modal || !openButton || !reader || !result || !openStudentLink || !imageButton || !imageInput || !toast) {
      return;
    }

    let html5Qr = null;
    let isRunning = false;
    let isHandlingScan = false;
    let toastTimerId = null;

    const stopScanner = async () => {
      if (html5Qr && isRunning) {
        try {
          await html5Qr.stop();
          await html5Qr.clear();
        } catch (error) {
          // Ignore stop/clear errors.
        }
      }
      html5Qr = null;
      isRunning = false;
      isHandlingScan = false;
    };

    const closeModal = async () => {
      modal.hidden = true;
      modal.classList.remove("is-open");
      if (toastTimerId) {
        window.clearTimeout(toastTimerId);
        toastTimerId = null;
      }
      toast.hidden = true;
      toast.textContent = "";
      toast.classList.remove("is-error");
      await stopScanner();
    };

    /*
     * Status lines are written after the page has been translated, so they are
     * looked up as they are set. Anything missing from the dictionary comes
     * back unchanged, which is the right outcome for a message that ends in a
     * detail string from the decoder.
     */
    const localize = (message) => {
      const i18n = window.KhotwaI18n;
      return i18n && typeof i18n.t === "function" ? i18n.t(String(message)) : String(message);
    };

    const setScanResult = (message, isError = false) => {
      result.textContent = localize(message);
      result.classList.toggle("is-error", isError);
    };

    const showScanToast = (message, isError = false) => {
      if (toastTimerId) {
        window.clearTimeout(toastTimerId);
      }

      toast.textContent = message;
      toast.classList.toggle("is-error", isError);
      toast.hidden = false;

      toastTimerId = window.setTimeout(() => {
        toast.hidden = true;
        toast.textContent = "";
        toast.classList.remove("is-error");
        toastTimerId = null;
      }, 3000);
    };

    const clearMatchedRows = () => {
      document
        .querySelectorAll("[data-record-row].scan-match")
        .forEach((row) => row.classList.remove("scan-match"));
    };

    const todayIso = () => {
      const now = new Date();
      const y = now.getFullYear();
      const m = String(now.getMonth() + 1).padStart(2, "0");
      const d = String(now.getDate()).padStart(2, "0");
      return `${y}-${m}-${d}`;
    };

    // Dates are shown day/month/year; the ISO form stays for row matching.
    const formatDisplayDate = (iso) => {
      const parts = String(iso).slice(0, 10).split("-");
      return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : String(iso);
    };

    const checkTodayAttendanceForStudent = (studentId) => {
      const targetDate = todayIso();
      const rows = document.querySelectorAll("[data-record-row][data-student-id][data-attendance-date]");
      let matched = null;

      rows.forEach((row) => {
        const rowStudentId = Number(row.getAttribute("data-student-id") || "0");
        const rowDate = String(row.getAttribute("data-attendance-date") || "");
        if (rowStudentId === studentId && rowDate === targetDate) {
          matched = row;
        }
      });

      return { matched, date: targetDate };
    };

    const syncGeneralAttendance = async (studentId) => {
      const body = new URLSearchParams();
      body.set("csrf", csrf);
      body.set("student_id", String(studentId));

      const response = await fetch(attendanceUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: body.toString(),
      });

      let payload = null;
      try {
        payload = await response.json();
      } catch (error) {
        throw new Error("Attendance service returned an invalid response.");
      }

      if (!response.ok || !payload?.success) {
        throw new Error(String(payload?.error || "Could not save general attendance."));
      }

      return payload;
    };

    /*
     * The dossier is built out of real nodes rather than an HTML string: every
     * value in it - a teacher's note, a warning reason - is free text typed by
     * a person, and textContent is what keeps that text from becoming markup.
     */
    const el = (tag, className, text) => {
      const node = document.createElement(tag);
      if (className) {
        node.className = className;
      }
      if (text !== undefined && text !== null && text !== "") {
        node.textContent = String(text);
      }
      return node;
    };

    /*
     * Status words arrive as enum keys ("not_recorded"). The Arabic dictionary
     * is keyed on the title-cased reading ("Not recorded"), so the label is
     * built in that shape rather than lower-cased and left untranslatable.
     */
    const humanize = (value) => {
      const text = String(value || "").replace(/_/g, " ").trim();
      return text === "" ? "" : text.charAt(0).toUpperCase() + text.slice(1);
    };

    /*
     * Names of people and subjects are already held in both languages, so they
     * carry both readings and let language.js swap them without a request.
     */
    const bilingual = (tag, className, en, ar) => {
      const node = el(tag, className, en);
      node.dataset.en = en || "";
      node.dataset.ar = (ar || "").trim() !== "" ? ar : (en || "");
      return node;
    };

    const dossierSection = (title, count) => {
      const section = el("section", "dossier-section");
      const head = el("h3", "dossier-section-head", title);
      if (typeof count === "number") {
        head.append(el("span", "dossier-count", String(count)));
      }
      section.append(head);
      return section;
    };

    const renderDossier = (data) => {
      if (!dossier) {
        return;
      }

      dossier.textContent = "";

      const student = data.student || {};
      const totals = data.totals || {};

      // Identity first, so whoever is holding the phone can confirm the right
      // card was scanned before reading anything below it.
      const head = el("header", "dossier-head");
      if (student.photo_url) {
        const photo = document.createElement("img");
        photo.className = "dossier-photo";
        photo.src = student.photo_url;
        photo.alt = "";
        head.append(photo);
      }
      const identity = el("div", "dossier-identity");
      identity.append(bilingual("strong", null, student.name_en || "Student #" + student.id, student.name_ar));

      // Each fragment is its own node so the translator can reach the ID and the
      // status word; a single joined string would match nothing.
      const meta = el("span", "dossier-meta");
      meta.append(el("span", null, "ID " + student.id));
      meta.append(el("span", null, humanize(student.status)));
      meta.append(el("span", null, formatDisplayDate(data.date)));
      identity.append(meta);
      head.append(identity);
      dossier.append(head);

      // Arrival at the centre. A missing row is not an absence - nobody has
      // marked the student either way - so it is labelled as unmarked.
      const daily = data.daily;
      const dailyBadge = el("div", "dossier-daily status-" + (daily ? String(daily.status) : "unmarked"));
      if (daily) {
        const times = [
          daily.check_in_time ? "in " + daily.check_in_time : "",
          daily.check_out_time ? "out " + daily.check_out_time : "",
        ].filter(Boolean).join(" · ");
        dailyBadge.append(el("strong", null, humanize(daily.status)));
        if (times) {
          dailyBadge.append(el("span", null, times));
        }
        if (daily.notes) {
          dailyBadge.append(el("p", "dossier-daily-note", daily.notes));
        }
      } else {
        dailyBadge.append(el("strong", null, "Not marked today"));
        dailyBadge.append(el("span", null, "No daily attendance record yet"));
      }
      dossier.append(dailyBadge);

      // Subjects. Every active enrolment appears, including ones with no session
      // recorded - that gap is the thing worth seeing.
      const sessions = Array.isArray(data.sessions) ? data.sessions : [];
      const subjectsSection = dossierSection("Subjects today", sessions.length);
      // Number and label are separate nodes: the label is what the dictionary
      // can translate, and the number must survive untouched either way.
      const tally = el("p", "dossier-tally");
      [
        ["Attended", totals.attended || 0],
        ["Missed", totals.missed || 0],
        ["Not recorded", totals.not_recorded || 0],
      ].forEach(([label, count]) => {
        const part = el("span", "dossier-tally-part");
        part.append(el("b", null, String(count)));
        part.append(el("span", null, label));
        tally.append(part);
      });
      subjectsSection.append(tally);
      if (sessions.length === 0) {
        subjectsSection.append(el("p", "dossier-empty", "No active enrollments."));
      } else {
        const list = el("ul", "dossier-list");
        sessions.forEach((row) => {
          const item = el("li", "dossier-row status-" + row.status);
          const main = el("div", "dossier-row-main");
          main.append(bilingual("strong", null, row.subject_en, row.subject_ar));
          if (row.teacher_en) {
            main.append(bilingual("span", "dossier-row-sub", row.teacher_en, row.teacher_ar));
          }
          item.append(main);
          item.append(el("span", "dossier-pill pill-" + row.status, humanize(row.status)));
          list.append(item);
        });
        subjectsSection.append(list);
      }
      dossier.append(subjectsSection);

      const noteList = (title, rows, emptyText) => {
        const section = dossierSection(title, rows.length);
        if (rows.length === 0) {
          section.append(el("p", "dossier-empty", emptyText));
        } else {
          const list = el("ul", "dossier-notes");
          rows.forEach((row) => {
            const item = el("li");
            const label = el("strong", null);
            label.append(bilingual("span", null, row.subject_en, row.subject_ar));
            if (row.teacher_en) {
              label.append(bilingual("span", null, row.teacher_en, row.teacher_ar));
            }
            item.append(label);
            item.append(el("p", null, row.note));
            list.append(item);
          });
          section.append(list);
        }
        return section;
      };

      dossier.append(noteList(
        "Homework",
        Array.isArray(data.homework) ? data.homework : [],
        "No homework recorded for today."
      ));
      dossier.append(noteList(
        "Teacher notes",
        Array.isArray(data.notes) ? data.notes : [],
        "No notes recorded for today."
      ));

      // Warnings are not day-scoped: an open one from last week still stands.
      const warnings = Array.isArray(data.warnings) ? data.warnings : [];
      const warningSection = dossierSection("Open warnings", warnings.length);
      if (warnings.length === 0) {
        warningSection.append(el("p", "dossier-empty", "No open warnings."));
      } else {
        warningSection.classList.add("has-warnings");
        const list = el("ul", "dossier-notes dossier-warnings");
        warnings.forEach((row) => {
          const item = el("li");
          const label = el("strong", null);
          [
            formatDisplayDate(row.date),
            row.type ? humanize(row.type) : "",
            row.number ? "#" + row.number : "",
            humanize(row.status),
          ].filter(Boolean).forEach((part) => label.append(el("span", null, part)));
          item.append(label);
          item.append(el("p", null, row.reason));

          const trail = el("span", "dossier-row-sub");
          if (row.teacher_en) {
            trail.append(bilingual("span", null, row.teacher_en, row.teacher_ar));
          }
          trail.append(el("span", null, row.parent_notified ? "Parent notified" : "Parent not notified"));
          item.append(trail);
          list.append(item);
        });
        warningSection.append(list);
      }
      dossier.append(warningSection);

      dossier.hidden = false;
      if (dossierEmpty) {
        dossierEmpty.hidden = true;
      }

      /*
       * The card is built after the page has already been translated, and the
       * translator does not watch for new nodes - so it is run again over the
       * document now that the card is in it. Without this the whole card would
       * stay English while the rest of the page is Arabic.
       */
      const i18n = window.KhotwaI18n;
      if (i18n && typeof i18n.apply === "function") {
        i18n.apply(i18n.current(), false);
      }
    };

    const lookupStudentDay = async (studentId) => {
      const body = new URLSearchParams();
      body.set("csrf", csrf);
      body.set("student_id", String(studentId));

      const response = await fetch(lookupUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: body.toString(),
      });

      let payload = null;
      try {
        payload = await response.json();
      } catch (error) {
        throw new Error("Lookup service returned an invalid response.");
      }

      if (!response.ok || !payload?.success) {
        throw new Error(String(payload?.error || "Could not load this student."));
      }

      return payload;
    };

    const applyLookupForStudent = async (parsed) => {
      const data = await lookupStudentDay(parsed.id);
      const studentIdValue = Number(data?.student?.id || parsed.id);
      renderDossier(data);

      const label = data?.student?.name_en || "Student #" + studentIdValue;
      setScanResult("Loaded " + label + " (ID: " + studentIdValue + ")");
      openStudentLink.href = studentUrl + "?type=student&id=" + studentIdValue;
      openStudentLink.hidden = false;
    };

    const applyScanForStudent = async (parsed) => {
      const studentId = parsed.id;
      const qrName = parsed.names.en || `Student #${studentId}`;

      const attendanceResult = await syncGeneralAttendance(studentId);
      const serverName = String(attendanceResult?.student?.name_en || "").trim() || qrName;
      const serverId = Number(attendanceResult?.student?.id || studentId);
      const date = String(attendanceResult?.attendance?.date || todayIso());
      const status = String(attendanceResult?.attendance?.status || "unknown").replace(/_/g, " ");
      const checkInTime = String(attendanceResult?.attendance?.check_in_time || "");
      const checkOutTime = String(attendanceResult?.attendance?.check_out_time || "");
      const action = String(attendanceResult?.action || "existing");

      const { matched } = checkTodayAttendanceForStudent(serverId);
      clearMatchedRows();
      if (matched) {
        matched.classList.add("scan-match");
        matched.scrollIntoView({ behavior: "smooth", block: "center" });
      }

      const actionText = action === "checked_in"
        ? `Checked in${checkInTime ? ` at ${checkInTime}` : ""}`
        : action === "checked_out"
          ? `Checked out${checkOutTime ? ` at ${checkOutTime}` : ""}`
          : "Already checked out";
      setScanResult(
        `Scanned: ${serverName} (ID: ${serverId}) | ${actionText} for ${formatDisplayDate(date)} | status: ${status}`
      );

      openStudentLink.href = `${studentUrl}?type=student&id=${serverId}`;
      openStudentLink.hidden = false;

      const tableSearchInput = document.querySelector("[data-table-search]");
      if (tableSearchInput instanceof HTMLInputElement) {
        tableSearchInput.value = String(serverId);
        tableSearchInput.dispatchEvent(new Event("input", { bubbles: true }));
      }
    };

    const handleDecodedText = async (decodedText, source = "camera") => {
      if (isHandlingScan) {
        return;
      }

      const parsed = parseScanValue(decodedText);
      if (!parsed) {
        if (source === "camera") {
          return;
        }
        setScanResult("Selected image is not a valid student QR payload.", true);
        showScanToast("Invalid QR content", true);
        return;
      }

      showScanToast(`QR value: ${parsed.text}`);

      isHandlingScan = true;
      try {
        if (scanMode === "lookup") {
          await applyLookupForStudent(parsed);
        } else {
          await applyScanForStudent(parsed);
        }
        /*
         * In lookup mode the answer is the card, and the card is on the page
         * behind this dialog - so the dialog has to get out of the way however
         * the code was read. Closing only after a camera scan left anyone who
         * picked a photo staring at the unchanged dialog, with the student's
         * day rendered out of sight behind it.
         */
        if (scanMode === "lookup") {
          await closeModal();
        } else if (source === "camera") {
          await stopScanner();
        }
      } catch (error) {
        setScanResult(String(error?.message || "Failed to process attendance."), true);
      } finally {
        isHandlingScan = false;
      }
    };

    const openModal = async () => {
      modal.hidden = false;
      modal.classList.add("is-open");
      openStudentLink.hidden = true;
      openStudentLink.removeAttribute("href");
      clearMatchedRows();

      // Drop the previous student's card before scanning the next one, so a
      // failed scan can never leave the wrong person's day on screen.
      if (dossier) {
        dossier.textContent = "";
        dossier.hidden = true;
      }
      if (dossierEmpty) {
        dossierEmpty.hidden = false;
      }

      setScanResult("Starting camera...");

      if (!window.Html5Qrcode) {
        setScanResult("QR scanner library failed to load. Refresh and try again.", true);
        return;
      }

      /*
       * Browsers hand out a camera only to a secure context, so over plain HTTP
       * on the LAN address there is nothing to grant and no permission prompt
       * to accept. Saying so is the difference between a dead end and reaching
       * for the picture option, which works on any origin.
       */
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        setScanResult(
          "The camera needs a secure (https) connection, so it cannot start on this address. Use “Scan from image” below - take a photo of the code, then pick it.",
          true
        );
        return;
      }

      if (!reader.id) {
        reader.id = "qr-reader-box";
      }

      html5Qr = new window.Html5Qrcode(reader.id);

      const onScanSuccess = async (decodedText) => {
        await handleDecodedText(decodedText, "camera");
      };

      const onScanFailure = () => {
        // Keep scanning silently.
      };

      try {
        await html5Qr.start(
          { facingMode: "environment" },
          {
            fps: 18,
            formatsToSupport: window.Html5QrcodeSupportedFormats
              ? [window.Html5QrcodeSupportedFormats.QR_CODE]
              : undefined,
            rememberLastUsedCamera: true,
            disableFlip: false,
          },
          onScanSuccess,
          onScanFailure
        );
        isRunning = true;
        setScanResult("Camera started. Point at a student QR code (or use Scan from image).");
      } catch (error) {
        setScanResult("Camera access failed. Please allow camera permission and retry.", true);
      }
    };

    openButton.addEventListener("click", () => {
      openModal();
    });

    closeButtons?.forEach((button) => {
      button.addEventListener("click", () => {
        closeModal();
      });
    });

    imageButton.addEventListener("click", () => {
      imageInput.click();
    });

    /*
     * Reading a QR out of a picture always starts from a brand new decoder.
     *
     * The old code reused whatever instance openModal() had built. Over plain
     * HTTP the camera can never start - getUserMedia is refused outside a
     * secure context - so that instance was left half-initialised by a failed
     * start(), and handing it a file threw. Picking a photo appeared to do
     * nothing at all, which is exactly the path someone falls back to when the
     * camera is unavailable.
     */
    const scanImageFile = async (file) => {
      if (!file) {
        return;
      }

      if (!window.Html5Qrcode) {
        setScanResult("QR scanner library failed to load. Refresh and try again.", true);
        return;
      }

      if (!/^image\//i.test(file.type || "")) {
        setScanResult("That file is not an image.", true);
        return;
      }

      setScanResult("Reading QR from selected image...");
      try {
        await stopScanner();
        // start() may have left a video node behind; scanFile needs the box bare.
        reader.textContent = "";
        if (!reader.id) {
          reader.id = "qr-reader-box";
        }
        html5Qr = new window.Html5Qrcode(reader.id);

        const decodedText = await html5Qr.scanFile(file, true);
        await handleDecodedText(decodedText, "image");
      } catch (error) {
        /*
         * The library's own message says whether it found no code at all or
         * choked on the file, which is the difference worth reporting. It can
         * also reject with the image element's error Event - the picture never
         * loaded at all - and "[object Event]" tells nobody anything, so that
         * case gets named instead.
         */
        const detail = error instanceof Event
          ? "The browser could not open that picture."
          : String(error?.message || error || "").trim();
        setScanResult(
          detail === ""
            ? "Could not read a QR code from that image."
            : "Could not read a QR code from that image. " + detail,
          true
        );
      }
    };

    imageInput.addEventListener("change", async () => {
      const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
      try {
        await scanImageFile(file);
      } finally {
        imageInput.value = "";
      }
    });

    // Dropping a picture onto the dialog, or pasting one into it, does the same
    // thing as choosing it from the picker.
    const firstImageFrom = (list) => {
      const files = Array.from(list || []);
      return files.find((entry) => /^image\//i.test(entry.type || "")) || files[0] || null;
    };

    ["dragenter", "dragover"].forEach((type) => {
      modal.addEventListener(type, (event) => {
        event.preventDefault();
        modal.classList.add("is-dropping");
      });
    });

    ["dragleave", "dragend"].forEach((type) => {
      modal.addEventListener(type, (event) => {
        if (event.target === modal || !modal.contains(event.relatedTarget)) {
          modal.classList.remove("is-dropping");
        }
      });
    });

    modal.addEventListener("drop", async (event) => {
      event.preventDefault();
      modal.classList.remove("is-dropping");
      await scanImageFile(firstImageFrom(event.dataTransfer?.files));
    });

    document.addEventListener("paste", async (event) => {
      if (modal.hidden) {
        return;
      }
      const file = firstImageFrom(event.clipboardData?.files);
      if (file) {
        event.preventDefault();
        await scanImageFile(file);
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !modal.hidden) {
        closeModal();
      }
    });
  };

  document.addEventListener("DOMContentLoaded", () => {
    initStudentQrCards();
    initQrScannerModal();
  });
})();
