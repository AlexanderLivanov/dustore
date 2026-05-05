/**
 * Dustore Service Worker — v2.0
 *
 * Стратегии по типу ресурса:
 *  PHP/HTML страницы  → Network First (всегда свежие, кеш только как fallback)
 *  CSS / JS           → Stale While Revalidate (отдаём кеш мгновенно + обновляем в фоне)
 *  Картинки / шрифты → Cache First (меняются редко, экономим трафик)
 *  API / POST / APK   → сеть напрямую, без SW
 */

const CACHE_STATIC = 'ds-static-v2';
const CACHE_IMAGES = 'ds-images-v2';
const CACHE_PAGES = 'ds-pages-v2';

const OFFLINE_PAGES = ['/', '/index.php'];

// ─── INSTALL ──────────────────────────────────────────────────────────────────
self.addEventListener('install', evt => {
    evt.waitUntil(
        caches.open(CACHE_PAGES)
            .then(c => c.addAll(OFFLINE_PAGES).catch(() => { }))
    );
    self.skipWaiting();
});

// ─── ACTIVATE ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', evt => {
    const CURRENT = [CACHE_STATIC, CACHE_IMAGES, CACHE_PAGES];
    evt.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys.filter(k => !CURRENT.includes(k)).map(k => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

// ─── FETCH ────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', evt => {
    const req = evt.request;
    const url = new URL(req.url);

    if (req.method !== 'GET') return;
    if (url.origin !== self.location.origin) return;
    if (url.pathname.endsWith('.apk')) return;
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/swad/controllers/')) return;

    const isPage = url.pathname.endsWith('.php') || url.pathname === '/' || !url.pathname.includes('.');
    const isStatic = /\.(css|js|woff2?|ttf|otf|svg)(\?|$)/.test(url.pathname);
    const isImage = /\.(png|jpe?g|gif|webp|ico)(\?|$)/.test(url.pathname);

    if (isPage) {
        // Network First — страницы всегда с сервера
        evt.respondWith(
            fetch(req)
                .then(res => {
                    if (res.ok) {
                        const clone = res.clone();
                        caches.open(CACHE_PAGES).then(c => c.put(req, clone));
                    }
                    return res;
                })
                .catch(() => caches.match(req).then(c => c || caches.match('/index.php')))
        );

    } else if (isStatic) {
        // Stale While Revalidate — кеш сразу, обновление в фоне
        evt.respondWith(
            caches.open(CACHE_STATIC).then(cache =>
                cache.match(req).then(cached => {
                    const fresh = fetch(req).then(res => {
                        if (res.ok) cache.put(req, res.clone());
                        return res;
                    });
                    return cached || fresh;
                })
            )
        );

    } else if (isImage) {
        // Cache First — картинки кешируем надолго
        evt.respondWith(
            caches.open(CACHE_IMAGES).then(cache =>
                cache.match(req).then(cached => {
                    if (cached) return cached;
                    return fetch(req).then(res => {
                        if (res.ok) cache.put(req, res.clone());
                        return res;
                    });
                })
            )
        );
    }
});

// ─── PUSH ─────────────────────────────────────────────────────────────────────
self.addEventListener('push', e => {
    if (!e.data) return;
    try {
        const d = e.data.json();
        self.registration.showNotification(d.title, {
            body: d.body,
            icon: '/swad/static/img/logo_new.png',
            data: { url: d.url }
        });
    } catch { }
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    e.waitUntil(self.clients.openWindow(e.notification.data?.url || '/'));
});

// ─── MESSAGE ──────────────────────────────────────────────────────────────────
self.addEventListener('message', evt => {
    if (evt.data?.type === 'SKIP_WAITING') self.skipWaiting();
});