/**
 * push-client.js — регистрация SW, подписка на Web Push, мост звука.
 * Подключается из chat/index.php и мобильных /m/chat, /m/profile.
 * VAPID public key берётся из window.VAPID_PUBLIC.
 *
 *   await initPush()  → { ok: true } | { ok: false, reason: 'текст для человека' }
 *   await pushState() → 'on' | 'off' | 'denied' | 'unsupported'
 *
 * Звать initPush из клика: разрешение браузеры спрашивают только в ответ на жест.
 */
(function () {
    const supported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    function urlB64ToUint8(base64) {
        const pad = '='.repeat((4 - base64.length % 4) % 4);
        const b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    }

    const sameKey = (buf, key) => {
        const a = new Uint8Array(buf);
        return a.length === key.length && a.every((v, i) => v === key[i]);
    };

    /* pushManager.subscribe() падает с «no active Service Worker», пока SW ещё
       ставится, — а при первом заходе он ставится как раз сейчас. */
    function activated(reg) {
        if (reg.active) return Promise.resolve(reg);
        const sw = reg.installing || reg.waiting;
        if (!sw) return navigator.serviceWorker.ready;
        return new Promise((res, rej) => {
            sw.addEventListener('statechange', () => {
                if (sw.state === 'activated') res(reg);
                if (sw.state === 'redundant') rej(new Error('service worker не установился'));
            });
        });
    }

    // SW -> клиент: проиграть кастомный звук в фореграунде. Слушатель один на
    // страницу — initPush зовут и при старте, и по кнопке, иначе звук двоился бы.
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', e => {
            if (e.data && e.data.type === 'chat-sound' && typeof window.chatPing === 'function') window.chatPing();
        });
    }

    async function current() {
        if (!supported()) return null;
        const reg = await navigator.serviceWorker.getRegistration('/');
        return reg ? reg.pushManager.getSubscription() : null;
    }

    window.pushState = async function () {
        if (!supported()) return 'unsupported';
        if (Notification.permission === 'denied') return 'denied';
        if (Notification.permission !== 'granted') return 'off';
        return (await current().catch(() => null)) ? 'on' : 'off';
    };

    window.initPush = async function () {
        if (!supported()) return { ok: false, reason: 'Браузер не поддерживает уведомления' };
        if (!window.VAPID_PUBLIC || window.VAPID_PUBLIC === 'CONFIRM') {
            console.warn('[push] VAPID_PUBLIC не задан');
            return { ok: false, reason: 'Уведомления не настроены на сервере' };
        }
        try {
            // Разрешение — ПЕРВЫМ делом, пока жест пользователя «свежий». Раньше
            // перед ним ждали регистрацию SW, и Safari/Firefox успевали решить,
            // что клика уже не было, — окно разрешения просто не появлялось.
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') {
                return { ok: false, reason: perm === 'denied' ? 'Запрещены в настройках браузера' : 'Разрешение не выдано' };
            }

            const reg = await activated(await navigator.serviceWorker.register('/sw.js'));
            const key = urlB64ToUint8(window.VAPID_PUBLIC);

            // Подписка, сделанная со СТАРЫМ ключом (ключи меняли), выглядит живой,
            // но сервер с новым ключом получает на неё 403 — и так навсегда.
            // Раньше её молча переиспользовали. Теперь сверяем и пересоздаём.
            // Браузер, не отдающий options.applicationServerKey, сверить не даёт —
            // тогда подписку не трогаем, иначе пересоздавали бы её на каждом заходе.
            let sub = await reg.pushManager.getSubscription();
            const subKey = sub && sub.options && sub.options.applicationServerKey;
            if (subKey && !sameKey(subKey, key)) {
                console.info('[push] подписка сделана другим VAPID-ключом — пересоздаю');
                await sub.unsubscribe();
                sub = null;
            }
            if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });

            const r = await fetch('/chat/push_subscribe.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(sub),
            });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || !j.ok) {
                console.error('[push] сервер не сохранил подписку', r.status, j);
                return { ok: false, reason: r.status === 401 ? 'Сессия истекла — войдите заново' : 'Сервер не сохранил подписку' };
            }
            return { ok: true };
        } catch (e) {
            console.error('[push] подписка не удалась', e);
            return { ok: false, reason: 'Не удалось включить: ' + (e && e.message || e) };
        }
    };
})();
