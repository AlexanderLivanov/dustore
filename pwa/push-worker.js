/**
 * push-worker.js — отправка Web Push из очереди push_outbox.
 * npm i web-push ; Node 18+. Живёт рядом с твоим push-сервером на :3001.
 *
 * env:
 *   VAPID_PUBLIC   — публичный VAPID ключ (тот же, что в window.VAPID_PUBLIC на клиенте)
 *   VAPID_PRIVATE  — приватный VAPID ключ (только здесь, на сервере!)
 *   VAPID_SUBJECT  — mailto:you@dustore.ru  (или https://dustore.ru)
 *   BRIDGE_SECRET  — общий секрет для localhost-эндпоинта
 *   OUTBOX_URL     — http://127.0.0.1/chat/push_outbox.php
 *
 * Сгенерировать ключи: npx web-push generate-vapid-keys
 */
const webpush = require('web-push');
const fs = require('fs');

// секрет: тот же файл, что читает PHP (bridge_secret()); фолбэк — env
function readSecret() {
    const f = process.env.BRIDGE_SECRET_FILE || '/etc/dustore/bridge.secret';
    try { const s = fs.readFileSync(f, 'utf8').trim(); if (s) return s; } catch (e) { }
    return process.env.BRIDGE_SECRET || '';
}

const { VAPID_PUBLIC, VAPID_PRIVATE, VAPID_SUBJECT } = process.env;
const BRIDGE_SECRET = readSecret();
const OUTBOX_URL = process.env.OUTBOX_URL || 'http://127.0.0.1/chat/push_outbox.php';

if (!VAPID_PUBLIC || !VAPID_PRIVATE || !VAPID_SUBJECT || !BRIDGE_SECRET) {
    console.error('Нужны: VAPID_PUBLIC, VAPID_PRIVATE, VAPID_SUBJECT (env) и секрет (файл /etc/dustore/bridge.secret или env BRIDGE_SECRET)');
    process.exit(1);
}
webpush.setVapidDetails(VAPID_SUBJECT, VAPID_PUBLIC, VAPID_PRIVATE);

const sleep = ms => new Promise(r => setTimeout(r, ms));

async function drain() {
    while (true) {
        try {
            const r = await fetch(`${OUTBOX_URL}?secret=${encodeURIComponent(BRIDGE_SECRET)}`).then(r => r.json());
            for (const job of (r.jobs || [])) {
                const payload = JSON.stringify(job.payload);
                const expired = [];
                let anyOk = false;

                await Promise.all(job.subscriptions.map(async sub => {
                    try {
                        await webpush.sendNotification({ endpoint: sub.endpoint, keys: sub.keys }, payload);
                        anyOk = true;
                    } catch (e) {
                        if (e.statusCode === 404 || e.statusCode === 410) expired.push(sub.id); // подписка мертва
                        else console.error('push send', e.statusCode || e.message);
                    }
                }));

                if (expired.length) await post({ expired });
                await post({ ack: job.id, status: (anyOk || job.subscriptions.length === 0) ? 'sent' : 'failed' });
            }
        } catch (e) {
            console.error('drain', e.message);
        }
        await sleep(2000);
    }
}

async function post(body) {
    await fetch(OUTBOX_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ secret: BRIDGE_SECRET, ...body }),
    });
}

console.log('push-worker запущен');
drain();