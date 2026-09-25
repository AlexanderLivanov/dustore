/*
 * hell.js — живой слой темы Madness («Адское пекло»).
 * Стили темы — swad/css/theme-madness.css; огонь внизу главной — hellfire в index.php.
 *
 * Что делает, пока на <body> висит .madness-theme:
 *   • угли поднимаются снизу экрана, покачиваются и гаснут;
 *   • за курсором сыплются искры — чем быстрее ведёшь, тем гуще;
 *   • по клику — вспышка искр и кольцо жара;
 *   • при выборе темы в меню — «зажигание»: экран на миг заливает пламенем снизу.
 * Всё рисуется на одном прозрачном canvas поверх страницы (pointer-events: none —
 * клики проходят насквозь). Тема выключена или вкладка скрыта — цикл стоит,
 * canvas пустой. «Уменьшение движения» в системе — слой не запускается вовсе.
 *
 * API: Hell.burst(x, y), Hell.ignite()
 */
(function () {
    'use strict';
    if (window.Hell) return;

    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
    const MAX = 280;                 // потолок частиц: дальше новые просто не рождаются

    let canvas = null, ctx = null, W = 0, H = 0, DPR = 1;
    let raf = 0, last = 0, running = false, emberAcc = 0;
    const parts = [];
    const lastPointer = { x: -1, y: -1 };

    const madness = () => document.body && document.body.classList.contains('madness-theme');

    /* Спрайт свечения рисуем один раз: радиальный градиент «белое ядро → жар → ничто».
       Каждая частица — один drawImage этого спрайта, это в разы дешевле, чем
       градиент или shadowBlur на каждую частицу каждый кадр. */
    const sprite = document.createElement('canvas');
    (function paintSprite() {
        const s = 64;
        sprite.width = sprite.height = s;
        const g = sprite.getContext('2d');
        const grd = g.createRadialGradient(s / 2, s / 2, 0, s / 2, s / 2, s / 2);
        grd.addColorStop(0, 'rgba(255,245,220,1)');
        grd.addColorStop(0.18, 'rgba(255,190,90,.95)');
        grd.addColorStop(0.42, 'rgba(255,90,20,.55)');
        grd.addColorStop(1, 'rgba(200,20,0,0)');
        g.fillStyle = grd;
        g.fillRect(0, 0, s, s);
    })();

    function ensureCanvas() {
        if (canvas) return;
        canvas = document.createElement('canvas');
        canvas.className = 'hell-layer';
        canvas.setAttribute('aria-hidden', 'true');
        document.body.appendChild(canvas);
        ctx = canvas.getContext('2d');
        resize();
    }

    function resize() {
        if (!canvas) return;
        DPR = Math.min(window.devicePixelRatio || 1, 1.5);   // ретина x3 для искр не нужна
        W = window.innerWidth;
        H = window.innerHeight;
        canvas.width = Math.round(W * DPR);
        canvas.height = Math.round(H * DPR);
        ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    }

    /* ── Частицы ──
       kind: 'ember' — уголь снизу, 'spark' — искра (с гравитацией), 'ring' — кольцо жара */
    function add(p) {
        if (parts.length < MAX) parts.push(p);
    }

    function spawnEmber() {
        add({
            kind: 'ember',
            x: Math.random() * W,
            y: H + 8,
            vx: (Math.random() - 0.5) * 18,
            vy: -(28 + Math.random() * 70),
            age: 0,
            life: 3.5 + Math.random() * 4.5,
            size: 3 + Math.random() * 5,
            phase: Math.random() * 6.28,
            wob: 0.6 + Math.random() * 1.6,
        });
    }

    function spawnSparks(x, y, n, speed, dirX = 0, dirY = 0) {
        for (let i = 0; i < n; i++) {
            const a = Math.random() * Math.PI * 2;
            const v = speed * (0.35 + Math.random() * 0.65);
            add({
                kind: 'spark',
                x, y,
                vx: Math.cos(a) * v + dirX,
                vy: Math.sin(a) * v + dirY - 40,
                age: 0,
                life: 0.35 + Math.random() * 0.55,
                size: 2 + Math.random() * 3.5,
                phase: 0,
                wob: 0,
            });
        }
    }

    function burst(x, y) {
        spawnSparks(x, y, 22, 260);
        add({ kind: 'ring', x, y, vx: 0, vy: 0, age: 0, life: 0.45, size: 0, phase: 0, wob: 0 });
        wake();
    }

    function update(dt) {
        // Плотность углей — от площади экрана: на телефоне их меньше, на 4K не каша
        const rate = Math.max(6, Math.min(28, 16 * (W * H) / (1440 * 900))) * (finePointer.matches ? 1 : 0.6);
        emberAcc += dt * rate;
        while (emberAcc >= 1) { spawnEmber(); emberAcc -= 1; }

        for (let i = parts.length - 1; i >= 0; i--) {
            const p = parts[i];
            p.age += dt;
            if (p.kind === 'ember') {
                p.phase += dt * p.wob;
                p.x += (p.vx + Math.sin(p.phase * 2.2) * 22) * dt;
                p.y += p.vy * dt;
                p.vy -= 6 * dt;                                   // тёплый воздух тянет вверх
            } else if (p.kind === 'spark') {
                p.vy += 420 * dt;                                 // искры падают
                p.vx *= 1 - 2.2 * dt;
                p.x += p.vx * dt;
                p.y += p.vy * dt;
            }
            if (p.age >= p.life || p.y < -20) parts.splice(i, 1);
        }
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);
        ctx.globalCompositeOperation = 'lighter';                 // свет складывается, как у настоящих искр
        for (const p of parts) {
            const t = p.age / p.life;
            if (p.kind === 'ring') {
                const r = 6 + t * 46;
                ctx.globalAlpha = (1 - t) * 0.6;
                ctx.strokeStyle = 'rgba(255,120,40,1)';
                ctx.lineWidth = 2.5 * (1 - t) + 0.5;
                ctx.beginPath();
                ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
                ctx.stroke();
                continue;
            }
            // Разгорается быстро, тлеет долго; у углей ещё и мерцание
            const fade = t < 0.12 ? t / 0.12 : 1 - (t - 0.12) / 0.88;
            const flick = p.kind === 'ember' ? 0.7 + 0.3 * Math.sin(p.age * 13 + p.phase * 5) : 1;
            ctx.globalAlpha = Math.max(0, fade * flick);
            const s = p.size * (p.kind === 'spark' ? 1 - t * 0.6 : 1);
            ctx.drawImage(sprite, p.x - s, p.y - s, s * 2, s * 2);
        }
        ctx.globalAlpha = 1;
        ctx.globalCompositeOperation = 'source-over';
    }

    function frame(now) {
        raf = 0;
        if (!running) return;
        const dt = Math.min((now - last) / 1000, 1 / 20);
        last = now;
        update(dt);
        draw();
        raf = requestAnimationFrame(frame);
    }

    function wake() {
        if (!running || raf) return;
        last = performance.now();
        raf = requestAnimationFrame(frame);
    }

    function start() {
        if (running || reducedMotion.matches || !madness()) return;
        ensureCanvas();
        canvas.hidden = false;
        running = true;
        wake();
    }

    function stop() {
        running = false;
        if (raf) cancelAnimationFrame(raf);
        raf = 0;
        parts.length = 0;
        if (canvas) {
            ctx.clearRect(0, 0, W, H);
            canvas.hidden = true;
        }
    }

    /* ── «Зажигание» при выборе темы ──
       Снизу за полсекунды поднимается стена пламени и тут же опадает, из-под неё —
       залп углей. Только при выборе в меню: на обычной загрузке страницы это бы бесило. */
    function ignite() {
        if (reducedMotion.matches) return;
        const veil = document.createElement('div');
        veil.className = 'hell-ignite';
        veil.setAttribute('aria-hidden', 'true');
        document.body.appendChild(veil);
        veil.addEventListener('animationend', () => veil.remove(), { once: true });
        setTimeout(() => veil.remove(), 1900);                    // страховка, если animationend не придёт
        for (let i = 0; i < 70; i++) spawnEmber();
        for (let i = 0; i < 12; i++) spawnSparks(Math.random() * W, H - 10, 3, 320, 0, -260);
        wake();
    }

    /* ── Ввод ── */
    addEventListener('pointermove', (e) => {
        if (!running || e.pointerType === 'touch') return;
        if (lastPointer.x >= 0) {
            const dx = e.clientX - lastPointer.x, dy = e.clientY - lastPointer.y;
            const n = Math.min(4, Math.floor(Math.hypot(dx, dy) / 16));
            // Искры летят назад, против движения, — как из-под точильного камня
            if (n > 0) spawnSparks(e.clientX, e.clientY, n, 60, -dx * 2.5, -dy * 2.5);
        }
        lastPointer.x = e.clientX;
        lastPointer.y = e.clientY;
    }, { passive: true });

    addEventListener('pointerdown', (e) => {
        if (running) burst(e.clientX, e.clientY);
    }, { passive: true });

    addEventListener('resize', resize, { passive: true });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { running = false; if (raf) cancelAnimationFrame(raf); raf = 0; }
        else if (madness()) { running = false; start(); }
    });

    addEventListener('dustore:themechange', (e) => {
        const theme = e.detail && e.detail.theme;
        if (theme === 'madness') {
            start();
            if (e.detail.source === 'user') ignite();
        } else {
            stop();
        }
    });

    function boot() {
        if (madness()) start();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();

    window.Hell = { burst, ignite };
})();
