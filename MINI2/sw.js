const CACHE_NAME = 'dustchat-v1';
const STATIC_FILES = [
    '/',
    '/index.html',
    '/js/app.js',
    '/manifest.json',
    '/icons/logo_new.png',
];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE_NAME).then(c => c.addAll(STATIC_FILES))
    );
    self.skipWaiting();
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // API запросы — всегда сеть, без кэша
    if (url.pathname.includes('/api/')) {
        e.respondWith(fetch(e.request).catch(() =>
            new Response(JSON.stringify({ error: 'offline' }), {
                status: 503, headers: { 'Content-Type': 'application/json' }
            })
        ));
        return;
    }

    // Статика — cache-first
    e.respondWith(
        caches.match(e.request).then(cached => cached || fetch(e.request).then(resp => {
            const clone = resp.clone();
            caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
            return resp;
        }))
    );
});
