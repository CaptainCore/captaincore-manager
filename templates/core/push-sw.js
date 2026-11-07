// CaptainCore operator notifications: the service worker behind web push.
// Served at <app path>/push-sw.js (Router.php), so its scope is the app.
// Payloads come from ChatPush: {title, body, url, tag, badge, icon}. The
// device needs no login to show them; tapping opens the conversation
// (focusing an open app window when there is one).

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

self.addEventListener('push', e => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { body: e.data ? e.data.text() : '' }; }
  const shown = self.registration.showNotification(d.title || 'New message', {
    body: d.body || '',
    tag: d.tag || 'chat',
    renotify: true,
    icon: d.icon || undefined,
    data: { url: d.url || self.registration.scope + 'chat' }
  });
  const nav = self.navigator;
  const badge = typeof d.badge === 'number' && nav && nav.setAppBadge
    ? (d.badge > 0 ? nav.setAppBadge(d.badge) : nav.clearAppBadge())
    : Promise.resolve();
  e.waitUntil(Promise.all([shown, badge.catch(() => {})]));
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || self.registration.scope + 'chat';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const c of list) {
      if (c.url.indexOf(self.registration.scope) === 0 && 'focus' in c) {
        c.postMessage({ type: 'cc-open', url });
        return c.focus();
      }
    }
    return self.clients.openWindow(url);
  }));
});
