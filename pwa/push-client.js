/**
 * push-client.js — регистрация SW, подписка на Web Push, мост звука.
 * Подключается из chat/index.php. VAPID public key берётся из window.VAPID_PUBLIC.
 */
(function () {
    function urlB64ToUint8(base64) {
        const pad = '='.repeat((4 - base64.length % 4) % 4);
        const b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    }

    window.initPush = async function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        if (!window.VAPID_PUBLIC || window.VAPID_PUBLIC === 'CONFIRM') { console.warn('VAPID_PUBLIC не задан'); return; }

        // CONFIRM: путь/scope SW. Если есть свой SW в /m/ — регистрируй его и вмержи туда sw-push.js
        let reg;
        try { reg = await navigator.serviceWorker.register('/sw.js'); }
        catch (e) { console.warn('SW register failed', e); return; }

        // SW -> клиент: проиграть кастомный звук в фореграунде
        navigator.serviceWorker.addEventListener('message', e => {
            if (e.data && e.data.type === 'chat-sound' && typeof window.chatPing === 'function') window.chatPing();
        });

        // спрашиваем разрешение только по жесту (вызывай initPush из клика, а не автоматически)
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') return;

        let sub = await reg.pushManager.getSubscription();
        if (!sub) {
            sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlB64ToUint8(window.VAPID_PUBLIC),
            });
        }
        await fetch('/chat/push_subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(sub),
        });
    };
})();