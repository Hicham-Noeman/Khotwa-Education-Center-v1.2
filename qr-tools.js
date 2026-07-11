(() => {
  const buildQrImageUrl = (text, format = "png", size = 512) => {
    const safeFormat = format === "jpg" || format === "jpeg" ? "jpg" : "png";
    const safeSize = Math.max(128, Math.min(1024, Number(size) || 512));
    return `https://api.qrserver.com/v1/create-qr-code/?size=${safeSize}x${safeSize}&format=${safeFormat}&data=${encodeURIComponent(text)}`;
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
      // Fallback to direct link if blob download is blocked by remote policy.
      triggerDownload(url, fileName);
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

      if (window.QRCode && typeof window.QRCode.toCanvas === "function") {
        canvas = document.createElement("canvas");
        window.QRCode.toCanvas(
          canvas,
          payloadText,
          {
            width: 170,
            margin: 1,
            color: {
              dark: "#0b1c34",
              light: "#ffffff",
            },
          },
          (error) => {
            if (error) {
              canvas = null;
              fallbackImage = document.createElement("img");
              fallbackImage.alt = "Student QR code";
              fallbackImage.loading = "lazy";
              fallbackImage.src = buildQrImageUrl(payloadText, "png", 340);
              fallbackImage.addEventListener("error", () => {
                qrContainer.textContent = "QR code could not be generated.";
              });
              qrContainer.replaceChildren(fallbackImage);
              return;
            }
            qrContainer.replaceChildren(canvas);
          }
        );
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

      const fileBase = toSafeFileBase(card.getAttribute("data-qr-file-base"));
      card.querySelectorAll("[data-qr-download]").forEach((button) => {
        button.addEventListener("click", async () => {
          const format = String(button.getAttribute("data-qr-download") || "png").toLowerCase();
          const extension = format === "jpg" || format === "jpeg" ? "jpg" : "png";

          if (!(canvas instanceof HTMLCanvasElement)) {
            await downloadUrlAsFile(
              buildQrImageUrl(payloadText, extension, 900),
              `${fileBase}.${extension}`
            );
            return;
          }

          if (format === "jpg" || format === "jpeg") {
            const jpgCanvas = document.createElement("canvas");
            jpgCanvas.width = canvas.width;
            jpgCanvas.height = canvas.height;
            const context = jpgCanvas.getContext("2d");
            if (!context) {
              return;
            }
            context.fillStyle = "#ffffff";
            context.fillRect(0, 0, jpgCanvas.width, jpgCanvas.height);
            context.drawImage(canvas, 0, 0);
            triggerDownload(jpgCanvas.toDataURL("image/jpeg", 0.95), `${fileBase}.jpg`);
            return;
          }

          triggerDownload(canvas.toDataURL("image/png"), `${fileBase}.png`);
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

    const setScanResult = (message, isError = false) => {
      result.textContent = message;
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

      const response = await fetch("admin-qr-attendance.php", {
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

    const applyScanForStudent = async (parsed) => {
      const studentId = parsed.id;
      const qrName = parsed.names.en || `Student #${studentId}`;

      const attendanceResult = await syncGeneralAttendance(studentId);
      const serverName = String(attendanceResult?.student?.name_en || "").trim() || qrName;
      const serverId = Number(attendanceResult?.student?.id || studentId);
      const date = String(attendanceResult?.attendance?.date || todayIso());
      const status = String(attendanceResult?.attendance?.status || "unknown").replace(/_/g, " ");
      const action = String(attendanceResult?.action || "existing");

      const { matched } = checkTodayAttendanceForStudent(serverId);
      clearMatchedRows();
      if (matched) {
        matched.classList.add("scan-match");
        matched.scrollIntoView({ behavior: "smooth", block: "center" });
      }

      const actionText = action === "created"
        ? "General attendance added"
        : "General attendance already exists";
      setScanResult(
        `Scanned: ${serverName} (ID: ${serverId}) | ${actionText} for ${date} | status: ${status}`
      );

      openStudentLink.href = `admin-person.php?type=student&id=${serverId}`;
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
        await applyScanForStudent(parsed);
        if (source === "camera") {
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
      setScanResult("Starting camera...");

      if (!window.Html5Qrcode) {
        setScanResult("QR scanner library failed to load. Refresh and try again.", true);
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

    imageInput.addEventListener("change", async () => {
      const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
      if (!file) {
        return;
      }

      if (!window.Html5Qrcode) {
        setScanResult("QR scanner library failed to load. Refresh and try again.", true);
        imageInput.value = "";
        return;
      }

      setScanResult("Reading QR from selected image...");
      try {
        if (isRunning) {
          await stopScanner();
        }
        if (!html5Qr) {
          if (!reader.id) {
            reader.id = "qr-reader-box";
          }
          html5Qr = new window.Html5Qrcode(reader.id);
        }
        const decodedText = await html5Qr.scanFile(file, true);
        await handleDecodedText(decodedText, "image");
      } catch (error) {
        setScanResult("Could not read QR from image. Try a clearer screenshot.", true);
      } finally {
        imageInput.value = "";
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
