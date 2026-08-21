/* ============================================================
 * MINI · sw.js — Service Worker
 * Полный офлайн-кеш оболочки: приложение должно открываться
 * на телефоне без единого байта сети (белые списки/офлайн).
 * Бизнес-логики здесь нет и не будет (правило проекта).
 *
 * Два отличия от «наивного» addAll():
 *  1. Кешируем каждый файл ПО ОТДЕЛЬНОСТИ (Promise.allSettled),
 *     а не единым addAll() — тот падает целиком, если недоступен
 *     хотя бы один ресурс, и тогда не закешируется НИЧЕГО.
 *  2. Navigation fallback: если браузер перезагружает страницу
 *     офлайн (а не просто дозапрашивает css/js уже открытой
 *     страницы), запрос идёт с mode:'navigate' — отвечаем ему
 *     закешированным index.html, иначе получим "no internet" от
 *     самого браузера, а не наш экран.
 * ============================================================ */

const CACHE = 'mini-shell-v5';

const SHELL = [
  './',
  './index.html',
  './manifest.json',
  './css/app.css',
  './css/messenger.css',
  './css/modal.css',
  './css/network.css',
  './js/storage.js',
  './js/crypto.js',
  './js/auth.js',
  './js/disk.js',
  './js/directory.js',
  './js/network.js',
  './js/modal.js',
  './js/settings.js',
  './js/messenger.js',
  './js/app.js',
  './assets/icons/icon.svg',
  './assets/icons/icon-maskable.svg',
  './assets/fonts/caveat-cyrillic.woff2',
  './assets/fonts/caveat-cyrillic-ext.woff2',
  './assets/fonts/caveat-latin.woff2',
  './assets/fonts/caveat-latin-ext.woff2',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then(async (cache) => {
      const results = await Promise.allSettled(
        SHELL.map((url) => cache.add(url))
      );
      results.forEach((r, i) => {
        if (r.status === 'rejected') {
          console.warn('MINI SW: не удалось закешировать', SHELL[i], r.reason);
        }
      });
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((names) => Promise.all(names.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);

  /* API Яндекса, любые внешние домены и не-GET — всегда сеть, никакого кеша */
  if (url.origin !== self.location.origin || req.method !== 'GET') return;

  /* Навигация (открытие/перезагрузка страницы) офлайн: отдаём index.html из кеша.
     Без этого браузер покажет свой системный экран "нет соединения",
     а не наше приложение. */
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(() => caches.match('./index.html'))
    );
    return;
  }

  /* Статика оболочки: кеш в приоритете, сеть — в фоне на обновление */
  e.respondWith(
    caches.match(req).then((cached) => {
      const fresh = fetch(req)
        .then((res) => {
          if (res.ok) {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
          }
          return res;
        })
        .catch(() => cached);
      return cached || fresh;
    })
  );
});

