/*
 * The service worker that shows a Chrome notification with the site closed.
 *
 * It sits at the root so its scope covers every portal. Chrome only registers
 * it on a secure origin, so on the LAN address over http this file is never
 * fetched - which is exactly why nothing here assumes it is running.
 *
 * Nothing sends to it yet: the server stores each browser's subscription but
 * the sending half is not written. When it is, a push arrives as JSON shaped
 * like the notifications table, and this file is already able to show it.
 */

self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

self.addEventListener("push", (event) => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (error) {
    payload = {};
  }

  const title = payload.title || "Khotwa Education Center";
  const options = {
    body: payload.body || "",
    icon: payload.icon || "assets/images/logo-color.svg",
    badge: payload.badge || "assets/images/logo-color.svg",
    // The link travels with the notification so a tap lands on the right page.
    data: { link: payload.link || "" },
    tag: payload.tag || undefined,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

/*
 * A tap belongs in a tab that is already open where there is one, rather than
 * in a second copy of the portal.
 */
self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const link = (event.notification.data && event.notification.data.link) || "";
  if (!link) return;

  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((windows) => {
      for (const client of windows) {
        if (client.url === link && "focus" in client) return client.focus();
      }
      return self.clients.openWindow ? self.clients.openWindow(link) : undefined;
    })
  );
});
