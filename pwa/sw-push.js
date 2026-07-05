/**
 * sw-push.js — обработчики Web Push для Service Worker.
 *
 * Если у тебя УЖЕ есть SW (в /m/) — не регистрируй второй, а ВМЕРЖИ эти три
 * обработчика в свой sw.js. Два SW с пересекающимся scope конфликтуют.
 * Если своего SW под /chat/ нет — используй этот файл как есть (см. push-client.js).
 */

self.addEventListener('push', event => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { }
    event.waitUntil((async () => {
        const clientsArr = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const focused = clientsArr.find(c => c.focused);
        if (focused) {
            // вкладка в фокусе -> кастомный WebAudio-звук в приложении, без системной нотификации
            focused.postMessage({ type: 'chat-sound' });
            return;
        }
        await self.registration.showNotification(data.title || 'Dustore', {
            body: data.body || '',
            icon: '/pwa/icon-192.png',    // CONFIRM: свои иконки
            badge: '/pwa/badge.png',
            tag: 'dustore-chat',
            renotify: true,
            data: { url: data.url || '/chat/' },
        });
    })());
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/chat/';
    event.waitUntil((async () => {
        const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const c of all) { if (c.url.includes('/chat') && 'focus' in c) return c.focus(); }
        if (self.clients.openWindow) return self.clients.openWindow(url);
    })());
});