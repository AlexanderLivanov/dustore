/**
 * Dustore Service Worker — v2.1
 *
 * Стратегии по типу ресурса:
 *  PHP/HTML страницы  → Network First (всегда свежие, кеш только как fallback)
 *  CSS / JS           → Stale While Revalidate (отдаём кеш мгновенно + обновляем в фоне)
 *  Картинки / шрифты  → Cache First (меняются редко, экономим трафик)
 *  API / POST / APK   → сеть напрямую, без SW
 *
 * v2.1: Web Push — звук в фореграунде (вкладка открыта → кастомный звук вместо
 *       нотификации), фокус существующей вкладки чата, обход SW для /chat/api.php.
 * v2.2: баннер глушится только когда в фокусе сам чат (раньше — любая вкладка
 *       сайта, и пуш молча пропадал); клик ведёт в нужную беседу, на телефоне —
 *       в /m/chat; pushsubscriptionchange сам переподписывает устройство.
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
    if (url.pathname === '/chat/api.php') return;   // API мессенджера — всегда живой, мимо SW

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
const CHAT_PATH = /^\/(m\/)?chat(\/|$)/;
const isChat = c => CHAT_PATH.test(new URL(c.url).pathname);
const windows = () => self.clients.matchAll({ type: 'window', includeUncontrolled: true });

self.addEventListener('push', e => {
    if (!e.data) return;
    let d = {};
    try { d = e.data.json(); } catch { return; }
    e.waitUntil((async () => {
        const all = await windows();
        // Счётчик непрочитанных в открытых вкладках обновляем всегда
        all.forEach(c => c.postMessage({ type: 'badge' }));
        // Глушим баннер, только если человек прямо сейчас смотрит в чат: там
        // новое сообщение и так появится, хватит звука. Раньше хватало любой
        // вкладки сайта в фокусе — и пуш пропадал без следа, а звук играть было
        // некому: chatPing есть только на странице чата.
        const chat = all.find(c => c.focused && isChat(c));
        if (chat) {
            chat.postMessage({ type: 'chat-sound' });
            return;
        }
        await self.registration.showNotification(d.title || 'Dustore', {
            body: d.body || '',
            icon: '/swad/static/img/logo_new.png',
            tag: d.url || 'dustore-chat',        // по тегу на беседу: вторая беседа не затирает первую
            renotify: true,
            data: { url: d.url || '/chat/' }
        });
    })());
});

/* Адрес из пуша — десктопный (/chat/?conversation=N). На телефоне сервер и сам
   редиректит в /m/chat, но у установленного PWA есть cookie pwa_standalone,
   при которой редиректа нет, — поэтому переписываем здесь. */
const onMobile = all => all.some(c => new URL(c.url).pathname.startsWith('/m/'))
    || /Android|iPhone|iPad|iPod|Mobile/i.test(self.navigator.userAgent);

function forDevice(url, mobile) {
    const u = new URL(url, self.location.origin);
    if (mobile && /^\/chat(\/|$)/.test(u.pathname)) u.pathname = '/m/chat';
    return u.pathname + u.search;
}

self.addEventListener('notificationclick', e => {
    e.notification.close();
    e.waitUntil((async () => {
        const all = await windows();
        const mobile = onMobile(all);
        const target = forDevice(e.notification.data?.url || '/', mobile);
        // Открытый чат переводим на нужную беседу, а не плодим вкладки. На телефоне
        // окно приложения одно — его и используем. Чужую вкладку на десктопе
        // (человек там что-то читает) не трогаем — открываем новую.
        // navigate() доступен только подконтрольным вкладкам, отсюда catch.
        const tab = all.find(isChat) || (mobile ? all[0] : null);
        if (tab) {
            const moved = await tab.navigate(target).catch(() => null);
            return (moved || tab).focus();
        }
        if (self.clients.openWindow) return self.clients.openWindow(target);
    })());
});

/* Браузер сам меняет подписку (Firefox — регулярно, Chrome — при сбросе данных).
   Старый endpoint после этого мёртв: воркер получит 410 и удалит его, а новый
   надо сохранить, иначе пуши на это устройство тихо закончатся. */
self.addEventListener('pushsubscriptionchange', e => {
    e.waitUntil((async () => {
        const key = e.oldSubscription?.options?.applicationServerKey;
        const sub = e.newSubscription
            || (key && await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }));
        if (!sub) return;
        await fetch('/chat/push_subscribe.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(sub),
        });
    })());
});

// ─── MESSAGE ──────────────────────────────────────────────────────────────────
self.addEventListener('message', evt => {
    if (evt.data?.type === 'SKIP_WAITING') self.skipWaiting();
});