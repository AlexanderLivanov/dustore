/* Dustore PWA — Service Worker v2 */

const CACHE_NAME = 'dustore-pwa-v2';
const SHELL_CACHE = 'dustore-shell-v2';
const IMAGE_CACHE = 'dustore-images-v2';

const SHELL_ASSETS = [
    '/m/',
    '/m/manifest.json',
];

self.addEventListener('install', event => {
    /* Не кэшируем CSS/JS в SW — у них теперь ?v= параметры, браузер сам кэширует */
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then(cache => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => ![CACHE_NAME, SHELL_CACHE, IMAGE_CACHE].includes(k))
                    .map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (url.origin !== location.origin) return;

    /* API и контроллеры — только сеть */
    if (url.pathname.startsWith('/swad/controllers/') ||
        url.pathname.startsWith('/m/api/') ||
        url.searchParams.has('ajax')) return;

    /* CSS и JS — только сеть (cache-busting через ?v= на стороне PHP) */
    if (url.pathname.endsWith('.css') || url.pathname.endsWith('.js')) return;

    /* Изображения — stale-while-revalidate */
    if (/\.(png|jpg|jpeg|webp|gif|svg|ico)$/i.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request, IMAGE_CACHE));
        return;
    }

    /* HTML страницы /m/* — network first */
    if (url.pathname.startsWith('/m')) {
        event.respondWith(networkFirst(request, CACHE_NAME));
        return;
    }
});

async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        return cached || offlinePage();
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const fetchP = fetch(request).then(r => {
        if (r.ok) cache.put(request, r.clone());
        return r;
    }).catch(() => null);
    return cached || fetchP;
}

function offlinePage() {
    return new Response(
        `<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Dustore — нет соединения</title>
<style>
body{font-family:-apple-system,system-ui,sans-serif;background:#14041d;color:#fff;
display:flex;flex-direction:column;align-items:center;justify-content:center;
min-height:100vh;text-align:center;padding:32px;gap:16px}
h1{font-size:22px}p{font-size:14px;color:rgba(255,255,255,.5);line-height:1.6}
button{background:#c32178;color:#fff;border:none;padding:13px 28px;
border-radius:9px;font-size:15px;font-weight:700;cursor:pointer}
</style></head><body>
<div style="font-size:52px">📡</div>
<h1>Нет подключения</h1>
<p>Проверь соединение<br>и попробуй ещё раз</p>
<button onclick="location.reload()">Обновить</button>
</body></html>`,
        { headers: { 'Content-Type': 'text/html;charset=utf-8' } }
    );
}