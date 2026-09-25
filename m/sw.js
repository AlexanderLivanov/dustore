/**
 * /m/sw.js — «самоуничтожающийся» service worker.
 *
 * Раньше у мобилки был свой SW со scope /m/. Теперь один общий /sw.js на весь
 * сайт (кэш + push). Но браузеры, где старый /m/sw.js уже установлен, будут
 * держать его вечно и перехватывать /m/* — поэтому по этому адресу теперь
 * лежит заглушка: при обновлении она чистит свои кэши, снимает регистрацию
 * и перезагружает открытые вкладки, чтобы их подхватил корневой /sw.js.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(k => k.startsWith('dustore-')).map(k => caches.delete(k)));
    await self.registration.unregister();
    const wins = await self.clients.matchAll({ type: 'window' });
    wins.forEach(w => w.navigate(w.url));
  })());
});
