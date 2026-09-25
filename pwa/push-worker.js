/**
 * push-worker.js — отправка Web Push из очереди push_outbox.
 * npm i web-push ; Node 18+. Живёт рядом с твоим push-сервером на :3001.
 *
 * env:
 *   VAPID_PUBLIC   — публичный VAPID ключ (тот же, что в window.VAPID_PUBLIC на клиенте)
 *   VAPID_PRIVATE  — приватный VAPID ключ (только здесь, на сервере!)
 *   VAPID_SUBJECT  — mailto:you@dustore.ru  (или https://dustore.ru)
 *   BRIDGE_SECRET  — общий секрет для localhost-эндпоинта
 *   OUTBOX_URL     — https://dustore.ru/chat/push_outbox.php (по умолчанию)
 *   OUTBOX_CONNECT — куда физически подключаться, по умолчанию 127.0.0.1;
 *                    пустая строка — обычный DNS
 *
 * Сгенерировать ключи: npx web-push generate-vapid-keys
 */
const webpush = require('web-push');
const fs = require('fs');
const http = require('http');
const https = require('https');

// секрет: тот же файл, что читает PHP (bridge_secret()); фолбэк — env
function readSecret() {
    const f = process.env.BRIDGE_SECRET_FILE || '/etc/dustore/bridge.secret';
    try { const s = fs.readFileSync(f, 'utf8').trim(); if (s) return s; } catch (e) { }
    return process.env.BRIDGE_SECRET || '';
}

const { VAPID_PUBLIC, VAPID_PRIVATE, VAPID_SUBJECT } = process.env;
const BRIDGE_SECRET = readSecret();
/* На 127.0.0.1:80 у сервера отвечает другой сайт (default vhost), и outbox
   оттуда — 404. Поэтому адрес — боевое имя, а соединение прибиваем к петле:
   Apache выберет vhost dustore.ru по SNI и Host, PHP увидит REMOTE_ADDR 127.0.0.1. */
const OUTBOX_URL = process.env.OUTBOX_URL || 'https://dustore.ru/chat/push_outbox.php';
const OUTBOX_CONNECT = process.env.OUTBOX_CONNECT ?? '127.0.0.1';

if (!VAPID_PUBLIC || !VAPID_PRIVATE || !VAPID_SUBJECT || !BRIDGE_SECRET) {
    console.error('Нужны: VAPID_PUBLIC, VAPID_PRIVATE, VAPID_SUBJECT (env) и секрет (файл /etc/dustore/bridge.secret или env BRIDGE_SECRET)');
    process.exit(1);
}
webpush.setVapidDetails(VAPID_SUBJECT, VAPID_PUBLIC, VAPID_PRIVATE);

const sleep = ms => new Promise(r => setTimeout(r, ms));

/* Раньше любой сбой связи с сайтом глотался молча: outbox отвечал 403 или
   HTML-страницей (редирект на https, чужой vhost на 127.0.0.1) — r.jobs было
   undefined, цикл крутился вхолостую, в логе тишина. Теперь каждое состояние
   пишется в лог один раз при смене, чтобы не заспамить journalctl раз в 2 с. */
let lastState = '';
function state(s, ...details) {
    if (s === lastState) return;
    lastState = s;
    (s === 'ok' ? console.log : console.error)(`[outbox] ${s}`, ...details);
}

let keyChecked = '';
function checkKey(siteKey) {
    if (!siteKey || siteKey === keyChecked) return;
    keyChecked = siteKey;
    if (siteKey !== VAPID_PUBLIC) {
        console.error('[vapid] КЛЮЧИ НЕ СОВПАДАЮТ: сайт раздаёт браузерам ' + siteKey.slice(0, 16)
            + '…, а воркер подписывает ' + VAPID_PUBLIC.slice(0, 16) + '…. Каждая отправка получит 403. '
            + 'Приведи к одной паре: /etc/dustore/push.env (должен читаться PHP) и env воркера.');
    } else {
        console.log('[vapid] ключ сайта и воркера совпадают');
    }
}

/* http(s).request, а не fetch: только так можно подменить адрес подключения,
   сохранив имя в SNI и Host. Секрет едет по петле, поэтому сертификат самих
   себя не проверяем — origin-сертификат (Cloudflare и т.п.) проверку и не прошёл бы. */
function call(method, url, body) {
    const u = new URL(url);
    const family = OUTBOX_CONNECT.includes(':') ? 6 : 4;
    const pin = OUTBOX_CONNECT ? {
        lookup: (host, opts, cb) => (opts && opts.all)
            ? cb(null, [{ address: OUTBOX_CONNECT, family }])
            : cb(null, OUTBOX_CONNECT, family),
        rejectUnauthorized: false,
    } : {};
    return new Promise((resolve, reject) => {
        const req = (u.protocol === 'https:' ? https : http).request(u, {
            method,
            timeout: 10000,
            headers: body ? { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) } : {},
            ...pin,
        }, res => {
            let text = '';
            res.setEncoding('utf8');
            res.on('data', c => text += c);
            res.on('end', () => resolve({ status: res.statusCode, location: res.headers.location, text }));
        });
        req.on('timeout', () => req.destroy(new Error('timeout')));
        req.on('error', reject);
        req.end(body);
    });
}

async function fetchJobs() {
    const res = await call('GET', `${OUTBOX_URL}?secret=${encodeURIComponent(BRIDGE_SECRET)}`);
    if (res.status >= 300 && res.status < 400) {
        state('redirect', res.status, '→', res.location,
            '— outbox должен отвечать сразу: после редиректа запрос уйдёт не с 127.0.0.1 и PHP ответит 403');
        return [];
    }
    let r;
    try { r = JSON.parse(res.text); } catch {
        state('not-json', res.status, res.text.slice(0, 120).replace(/\s+/g, ' '), '— отвечает не тот сайт (vhost) или PHP упал');
        return [];
    }
    if (res.status !== 200 || !r.ok) {
        const hint = res.status === 403 ? '— не тот секрет (/etc/dustore/bridge.secret) или запрос пришёл не с 127.0.0.1'
                   : res.status === 404 ? '— это не dustore: проверь OUTBOX_URL / OUTBOX_CONNECT'
                   : '';
        state('refused', res.status, res.text.slice(0, 120), hint);
        return [];
    }
    state('ok');
    checkKey(r.vapid);
    return r.jobs || [];
}

async function drain() {
    while (true) {
        try {
            for (const job of await fetchJobs()) {
                const payload = JSON.stringify(job.payload);
                const expired = [];
                let anyOk = false;

                await Promise.all(job.subscriptions.map(async sub => {
                    try {
                        await webpush.sendNotification({ endpoint: sub.endpoint, keys: sub.keys }, payload);
                        anyOk = true;
                    } catch (e) {
                        if (e.statusCode === 404 || e.statusCode === 410) { expired.push(sub.id); return; } // подписка мертва
                        const host = (() => { try { return new URL(sub.endpoint).host; } catch { return '?'; } })();
                        const hint = e.statusCode === 403 ? ' ← подписка сделана другим VAPID-ключом' : '';
                        console.error(`[send] job ${job.id} sub ${sub.id} @${host}: ${e.statusCode || e.message} ${String(e.body || '').slice(0, 160)}${hint}`);
                    }
                }));

                if (expired.length) await post({ expired });
                await post({ ack: job.id, status: (anyOk || job.subscriptions.length === 0) ? 'sent' : 'failed' });
                if (anyOk) console.log(`[send] job ${job.id}: доставлено в push-сервис`);
            }
        } catch (e) {
            state('unreachable', e.message, '— сайт по ' + OUTBOX_URL + ' не отвечает');
        }
        await sleep(2000);
    }
}

async function post(body) {
    const res = await call('POST', OUTBOX_URL, JSON.stringify({ secret: BRIDGE_SECRET, ...body }));
    if (res.status !== 200) console.error('[outbox] POST', res.status, JSON.stringify(body).slice(0, 80));
}

console.log('push-worker запущен, outbox:', OUTBOX_URL + (OUTBOX_CONNECT ? ' через ' + OUTBOX_CONNECT : ''), 'vapid:', VAPID_PUBLIC.slice(0, 16) + '…');
drain();
