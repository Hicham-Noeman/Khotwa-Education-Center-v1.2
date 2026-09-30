/*
 * The notification center, on the page side.
 *
 * Two jobs. The bell opens the list and keeps its badge honest while the page
 * stays open, which works everywhere. And where the browser will allow it, the
 * same page asks Chrome for permission to notify with the site closed, and
 * hands the subscription to the server.
 *
 * That second half is deliberately quiet: Chrome refuses the Push API outside a
 * secure context, so on the LAN address over http the page never asks and never
 * warns. Serve the site over https and it starts asking on its own.
 */
(() => {
  const panel = document.querySelector("[data-notifications-panel]");
  const toggles = Array.from(document.querySelectorAll("[data-notifications-toggle]"));
  if (!panel || toggles.length === 0) return;

  const counters = Array.from(document.querySelectorAll("[data-notifications-count]"));
  const list = panel.querySelector("[data-notifications-list]");

  const isArabic = () =>
    (window.KhotwaI18n ? window.KhotwaI18n.current() : document.documentElement.lang) === "ar";

  const showBadge = (count) => {
    counters.forEach((counter) => {
      counter.textContent = String(Math.min(count, 99));
      counter.hidden = count === 0;
    });
  };

  const closePanel = () => {
    panel.hidden = true;
    toggles.forEach((toggle) => toggle.setAttribute("aria-expanded", "false"));
  };

  toggles.forEach((toggle) => {
    toggle.setAttribute("aria-expanded", "false");
    toggle.addEventListener("click", (event) => {
      event.stopPropagation();
      const opening = panel.hidden;
      panel.hidden = !opening;
      toggles.forEach((other) => other.setAttribute("aria-expanded", String(opening)));
    });
  });

  const closeButton = panel.querySelector("[data-notifications-close]");
  if (closeButton) closeButton.addEventListener("click", closePanel);

  // Anywhere outside closes it, the way the language menu behaves.
  document.addEventListener("click", (event) => {
    if (panel.hidden) return;
    if (event.target.closest("[data-notifications-panel], [data-notifications-toggle]")) return;
    closePanel();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !panel.hidden) closePanel();
  });

  /*
   * Redrawing the list from the feed, so a notification raised while the page
   * sits open appears without a refresh. Both languages are kept on the node,
   * so switching language afterwards works the same as on a rendered page.
   */
  const paint = (items) => {
    if (!list) return;
    list.replaceChildren();

    if (items.length === 0) {
      const empty = document.createElement("p");
      empty.className = "notif-empty";
      empty.textContent = isArabic() ? "لا يوجد شيء بعد." : "Nothing yet.";
      list.append(empty);
      return;
    }

    const arabic = isArabic();
    items.forEach((item) => {
      const node = document.createElement("a");
      node.className = "notif-item" + (item.unread ? " is-unread" : "");
      node.href = item.open_url;

      const title = document.createElement("strong");
      title.dataset.i18nSkip = "";
      title.dataset.en = item.title_en;
      title.dataset.ar = item.title_ar;
      title.textContent = arabic ? item.title_ar : item.title_en;

      const body = document.createElement("span");
      body.dataset.i18nSkip = "";
      body.dataset.en = item.body_en;
      body.dataset.ar = item.body_ar;
      body.textContent = arabic ? item.body_ar : item.body_en;

      const when = document.createElement("small");
      when.dataset.i18nSkip = "";
      when.textContent = item.created_at;

      node.append(title, body, when);
      list.append(node);
    });
  };

  /*
   * The sound a new notification makes.
   *
   * Two short tones played straight from the browser rather than a file: it is a
   * handful of bytes instead of a download, it cannot 404, and it sounds the
   * same on every device. Browsers refuse to make noise on a page nobody has
   * touched yet, so the audio is only started once a real gesture has happened -
   * signing in and then tapping anything is enough - and before that the badge
   * is the whole signal.
   */
  const Audio = window.AudioContext || window.webkitAudioContext;
  let audio = null;

  const unlockAudio = () => {
    if (!Audio) return;
    try {
      audio = audio || new Audio();
      if (audio.state === "suspended") audio.resume();
    } catch (error) {
      audio = null;
    }
  };

  ["pointerdown", "keydown"].forEach((event) =>
    document.addEventListener(event, unlockAudio, { once: true, passive: true })
  );

  const chime = () => {
    if (!audio || audio.state !== "running") return;
    try {
      // Two notes a fourth apart, each fading out, which reads as a bell rather
      // than a beep. Kept quiet: this arrives while someone is reading.
      [
        { at: 0, hz: 880 },
        { at: 0.13, hz: 1174.7 },
      ].forEach(({ at, hz }) => {
        const start = audio.currentTime + at;
        const tone = audio.createOscillator();
        const gain = audio.createGain();
        tone.type = "sine";
        tone.frequency.value = hz;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.16, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.42);
        tone.connect(gain).connect(audio.destination);
        tone.start(start);
        tone.stop(start + 0.45);
      });
    } catch (error) {
      // A browser that will not play is not a reason to lose the notification.
    }
  };

  // The bell rings visually too, for a phone on silent and for anyone who never
  // gave the page a gesture to play sound with.
  const ringBell = () => {
    toggles.forEach((toggle) => {
      toggle.classList.remove("is-ringing");
      // Reading the layout restarts the animation on a second arrival.
      void toggle.offsetWidth;
      toggle.classList.add("is-ringing");
      window.setTimeout(() => toggle.classList.remove("is-ringing"), 1600);
    });
  };

  let lastSeenId = 0;
  const seed = list ? list.querySelectorAll(".notif-item").length : 0;

  const refresh = () =>
    fetch(panel.dataset.feedUrl || "notifications-feed.php", { credentials: "same-origin" })
      .then((response) => (response.ok ? response.json() : null))
      .then((data) => {
        if (!data) return;
        showBadge(Number(data.unread) || 0);

        const newest = data.items.length > 0 ? Number(data.items[0].id) : 0;
        // The first answer only establishes where we are; a later one that moves
        // past it is what counts as something new.
        const arrived = lastSeenId !== 0 && newest > lastSeenId;
        if (arrived) paint(data.items);
        if (lastSeenId === 0 && seed === 0 && data.items.length > 0) paint(data.items);
        lastSeenId = Math.max(lastSeenId, newest);

        if (arrived) {
          chime();
          ringBell();
        }
      })
      .catch(() => {});

  /*
   * How often to ask.
   *
   * Every ten seconds while the page is being looked at, which is what makes a
   * notification feel like it arrived rather than like it was found later. A
   * hidden tab is not asked at all - it is not being read, and a phone should
   * not spend its battery on it - and it catches up the moment it comes back,
   * which is also why returning to the page used to be when things appeared.
   */
  const LIVE_INTERVAL = 10000;
  let timer = null;

  const startPolling = () => {
    if (timer !== null) return;
    timer = window.setInterval(refresh, LIVE_INTERVAL);
  };

  const stopPolling = () => {
    if (timer === null) return;
    window.clearInterval(timer);
    timer = null;
  };

  document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
      stopPolling();
      return;
    }
    refresh();
    startPolling();
  });

  window.addEventListener("focus", refresh);

  refresh();
  if (!document.hidden) startPolling();

  document.addEventListener("khotwa:languagechange", () => {
    if (!list) return;
    // Rendered rows carry both languages already; language.js does the swap.
  });

  /*
   * Reading in the other language from now on, so the phone should be told in
   * it too. Defined here and called from the language event below, once the
   * subscribing half further down has been declared.
   */
  let resubscribe = () => {};

  document.addEventListener("khotwa:languagechange", () => resubscribe());

  /*
   * Landing on what the notification was about.
   *
   * The fragment names the panel, and the browser's own jump lands under a bar
   * that is fixed to the top of a phone screen - so the scroll is taken over
   * here, and the panel is flashed once so it is obvious which one was meant.
   */
  const revealTarget = () => {
    const hash = window.location.hash;
    if (hash.length < 2) return;

    let target = null;
    try {
      target = document.querySelector(hash);
    } catch (error) {
      return;
    }
    if (!target) return;

    window.requestAnimationFrame(() => {
      target.scrollIntoView({ behavior: "smooth", block: "start" });
      target.classList.add("notif-landed");
      window.setTimeout(() => target.classList.remove("notif-landed"), 2400);
    });
  };

  revealTarget();
  window.addEventListener("hashchange", revealTarget);

  /*
   * Chrome, with the site closed.
   *
   * Every one of these guards is a browser rule rather than a preference:
   * no service workers and no Push API outside a secure context, and no
   * permission prompt that was not asked for by a gesture the person made.
   */
  const pushReady =
    window.isSecureContext &&
    "serviceWorker" in navigator &&
    "PushManager" in window &&
    "Notification" in window;

  if (!pushReady) return;

  const vapidKey = panel.dataset.vapidKey || "";
  if (vapidKey === "") return;

  const urlBase64ToUint8Array = (base64) => {
    const padded = (base64 + "=".repeat((4 - (base64.length % 4)) % 4))
      .replace(/-/g, "+")
      .replace(/_/g, "/");
    const raw = window.atob(padded);
    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
  };

  const subscribe = () =>
    navigator.serviceWorker
      .register(panel.dataset.workerUrl || "sw.js")
      .then((registration) => registration.pushManager.getSubscription().then((existing) =>
        existing || registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(vapidKey),
        })
      ))
      .then((subscription) =>
        fetch(panel.dataset.subscribeUrl || "notifications-subscribe.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            csrf: panel.dataset.appCsrf || "",
            subscription: subscription.toJSON(),
            // A push is written before it is sent, so the language has to
            // travel with the subscription: there is no page open to pick one
            // at the moment it lands on a phone.
            language: isArabic() ? "ar" : "en",
          }),
        })
      )
      .catch(() => {});

  // The subscription itself does not change with the language; the row behind
  // it does, so re-sending it is all that is needed.
  resubscribe = () => {
    if (Notification.permission === "granted") subscribe();
  };

  if (Notification.permission === "granted") {
    subscribe();
    return;
  }

  if (Notification.permission === "denied") return;

  // Asked on the first bell press rather than on load: a prompt nobody invited
  // is the one people dismiss for good.
  toggles.forEach((toggle) =>
    toggle.addEventListener(
      "click",
      () => Notification.requestPermission().then((result) => {
        if (result === "granted") subscribe();
      }),
      { once: true }
    )
  );
})();
