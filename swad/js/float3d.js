/*
 * Float3D — наклон элемента за курсором + контент, парящий над поверхностью.
 * swad/js/float3d.js (стили — swad/css/float3d.css)
 *
 * Разделение обязанностей:
 *   JS  — только ввод и физика. Курсор → nx/ny ∈ [-1..1], подъём lift ∈ [0..1]
 *         (с перелётом, если пружина «мягкая»). Три числа пишутся в CSS-переменные
 *         --f3d-nx / --f3d-ny / --f3d-lift на элементе, 60 раз в секунду, пока
 *         что-то движется, и ни кадра дольше.
 *   CSS — вся картинка. Из этих трёх чисел и настроек (--f3d-depth и т.п.)
 *         собираются transform поверхности, слоя, тени и блика. Поэтому
 *         крутить эффект можно из CSS, не трогая этот файл.
 *
 * Разметка после attach():
 *   <button class="f3d">
 *     <span class="f3d-glare">              блик-«фонарик» на поверхности (z = 0)
 *     <span class="f3d-stack">              одна ячейка: слой и тень лежат друг на друге
 *       <span class="f3d-layer">…контент…   парит на --f3d-depth над поверхностью
 *       <span class="f3d-shadow">…копия…    тень контента, лежит на поверхности (z = 0)
 *     …абсолютные дети (бейджи) получают .f3d-badge и парят ещё выше
 *
 * Почему тень — отдельная копия, а не drop-shadow на слое: drop-shadow едет
 * вместе со слоем, и между текстом и тенью нет параллакса. А именно по
 * расхождению «предмет ↔ его тень» глаз и понимает, что предмет висит в воздухе.
 *
 * API:
 *   Float3D.attach('.selector' | element | NodeList)
 *   Float3D.panel()       — живая настройка; то же по Alt+Shift+F или ?f3d в адресе
 *   <el data-f3d>         — подключится сам на DOMContentLoaded
 */
(function () {
    'use strict';
    if (window.Float3D) return;

    const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const instances = new Map();
    const STEP = 1 / 240;      // шаг физики: пружина устойчива при любой частоте кадров

    // Любой скролл или ресайз сдвигает элементы под курсором — кэш rect устаревает
    let layoutStamp = 0;
    const bumpLayout = () => { layoutStamp++; };
    addEventListener('scroll', bumpLayout, { capture: true, passive: true });
    addEventListener('resize', bumpLayout, { passive: true });

    /* Пружина: x тянется к target с жёсткостью k, трение c гасит скорость.
       Коэффициент затухания ζ = c / (2·√k): < 1 — с перелётом («пружинит»),
       ≥ 1 — плавно, без перелёта. Полунеявный Эйлер — две строки и стабилен. */
    class Spring {
        constructor() { this.x = 0; this.v = 0; this.target = 0; }
        step(dt, k, c) {
            this.v += (-k * (this.x - this.target) - c * this.v) * dt;
            this.x += this.v * dt;
        }
        get resting() { return Math.abs(this.x - this.target) < 1e-3 && Math.abs(this.v) < 1e-2; }
        snap() { this.x = this.target; this.v = 0; }
    }

    const clamp = (v) => Math.max(-1, Math.min(1, v));

    function span(cls) {
        const el = document.createElement('span');
        el.className = cls;
        return el;
    }

    function readConfig(el) {
        const cs = getComputedStyle(el);
        const num = (name, fallback) => {
            const v = parseFloat(cs.getPropertyValue(name));
            return Number.isFinite(v) ? v : fallback;
        };
        const cfg = {
            tiltK: num('--f3d-tilt-stiffness', 170),
            tiltC: num('--f3d-tilt-damping', 20),
            liftK: num('--f3d-lift-stiffness', 320),
            liftC: num('--f3d-lift-damping', 13),
            press: num('--f3d-press', 0.3),
        };
        // При «уменьшить движение» — без перелётов
        if (reducedMotion.matches) cfg.liftC = Math.max(cfg.liftC, 2 * Math.sqrt(cfg.liftK));
        return cfg;
    }

    class Float {
        constructor(el) {
            this.el = el;
            this.nx = new Spring();
            this.ny = new Spring();
            this.lift = new Spring();
            this.hover = false;
            this.raf = 0;
            this.stamp = -1;
            this.tick = this.tick.bind(this);
            this.build();
            this.bind();
        }

        build() {
            const el = this.el;
            const glare = span('f3d-glare');
            const stack = span('f3d-stack');
            const layer = span('f3d-layer');
            const shadow = span('f3d-shadow');
            glare.setAttribute('aria-hidden', 'true');
            shadow.setAttribute('aria-hidden', 'true');

            // Абсолютных детей (бейджи) в слой не переносим: слой с transform стал бы
            // для них containing block, и top/right считались бы уже от него
            for (const node of Array.from(el.childNodes)) {
                if (node.nodeType === Node.ELEMENT_NODE && getComputedStyle(node).position === 'absolute') {
                    node.classList.add('f3d-badge');
                    continue;
                }
                layer.appendChild(node);
            }
            // Тень после слоя: querySelector по кнопке находит оригинал, а не копию
            stack.append(layer, shadow);
            el.prepend(glare, stack);
            el.classList.add('f3d');

            this.layer = layer;
            this.shadow = shadow;
            this.syncShadow();

            // Сторонний код меняет содержимое (иконка темы, подпись профиля) — тень догоняет сама
            let queued = false;
            new MutationObserver(() => {
                if (queued) return;
                queued = true;
                queueMicrotask(() => { queued = false; this.syncShadow(); });
            }).observe(layer, { childList: true, subtree: true, characterData: true, attributes: true });
        }

        syncShadow() {
            const copy = document.createDocumentFragment();
            this.layer.childNodes.forEach((n) => copy.appendChild(n.cloneNode(true)));
            copy.querySelectorAll('[id]').forEach((n) => n.removeAttribute('id'));
            this.shadow.replaceChildren(copy);
        }

        bind() {
            const el = this.el;
            const isTouch = (e) => e.pointerType === 'touch';

            /* Зона наведения — прямоугольник элемента В ПОКОЕ, а не его наклонённая
               фигура. Иначе у края: край уходит вглубь и выскальзывает из-под курсора →
               pointerleave → элемент ложится обратно → снова под курсором → enter…
               и так по кругу, кнопка дрожит. Поэтому pointerleave внутри прямоугольника
               игнорируем и досматриваем курсор на уровне документа. */
            const inside = (e) => {
                const r = this.rect;
                return !!r && e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
            };
            const release = () => {
                removeEventListener('pointermove', onDocMove);
                removeEventListener('pointerout', onWindowOut);
                this.hover = false;
                this.nx.target = this.ny.target = this.lift.target = 0;
                this.wake();
            };
            const onDocMove = (e) => {
                if (this.stamp !== layoutStamp) this.measure();
                if (!inside(e)) { release(); return; }
                this.aim(e);
                this.wake();
            };
            // Курсор ушёл за пределы окна — документ больше не пришлёт pointermove
            const onWindowOut = (e) => { if (!e.relatedTarget) release(); };

            el.addEventListener('pointerenter', (e) => {
                if (isTouch(e)) return;
                if (!this.hover || this.stamp !== layoutStamp) this.measure();
                this.hover = true;
                this.aim(e);
                this.lift.target = 1;
                this.wake();
            });
            el.addEventListener('pointermove', (e) => {
                if (isTouch(e) || !this.hover) return;
                this.aim(e);
                this.wake();
            });
            el.addEventListener('pointerleave', (e) => {
                if (!this.hover) return;
                if (!isTouch(e) && this.stamp === layoutStamp && inside(e)) {
                    addEventListener('pointermove', onDocMove);
                    addEventListener('pointerout', onWindowOut);
                    return;
                }
                release();
            });
            // Нажатие: контент проседает к поверхности, на отпускании — выпрыгивает обратно
            el.addEventListener('pointerdown', (e) => {
                if (isTouch(e)) return;
                this.lift.target = this.cfg.press;
                this.wake();
            });
            el.addEventListener('pointerup', () => {
                if (!this.hover) return;
                this.lift.target = 1;
                this.wake();
            });
        }

        /* Размер и позицию меряем БЕЗ нашего transform: иначе наклон меняет rect,
           rect меняет наклон — и на краях кнопка дрожит (обратная связь). */
        measure() {
            const el = this.el;
            const prev = el.style.transform;
            el.style.transform = 'none';
            this.rect = el.getBoundingClientRect();
            el.style.transform = prev;
            this.stamp = layoutStamp;
            this.cfg = readConfig(el);
        }

        aim(e) {
            if (this.stamp !== layoutStamp) this.measure();
            if (reducedMotion.matches) return;           // подъём оставляем, наклон — нет
            const r = this.rect;
            this.nx.target = clamp(((e.clientX - r.left) / r.width) * 2 - 1);
            this.ny.target = clamp(((e.clientY - r.top) / r.height) * 2 - 1);
        }

        wake() {
            if (this.raf) return;
            if (!this.cfg) this.cfg = readConfig(this.el);
            this.el.classList.add('f3d-on');
            this.last = performance.now();
            this.raf = requestAnimationFrame(this.tick);
        }

        tick(now) {
            const c = this.cfg;
            let dt = Math.min((now - this.last) / 1000, 1 / 20);
            this.last = now;
            for (; dt > 1e-6; dt -= STEP) {
                const h = Math.min(STEP, dt);
                this.nx.step(h, c.tiltK, c.tiltC);
                this.ny.step(h, c.tiltK, c.tiltC);
                this.lift.step(h, c.liftK, c.liftC);
            }

            const done = this.nx.resting && this.ny.resting && this.lift.resting;
            if (done) { this.nx.snap(); this.ny.snap(); this.lift.snap(); }

            const s = this.el.style;
            s.setProperty('--f3d-nx', this.nx.x.toFixed(4));
            s.setProperty('--f3d-ny', this.ny.x.toFixed(4));
            s.setProperty('--f3d-lift', this.lift.x.toFixed(4));

            if (!done) {
                this.raf = requestAnimationFrame(this.tick);
                return;
            }
            this.raf = 0;
            // В покое снимаем transform совсем — текст снова растровый и резкий
            if (!this.hover && this.lift.x === 0) {
                this.el.classList.remove('f3d-on');
                s.removeProperty('--f3d-nx');
                s.removeProperty('--f3d-ny');
                s.removeProperty('--f3d-lift');
            }
        }
    }

    function attach(target) {
        if (!finePointer.matches) return [];
        const list = typeof target === 'string' ? document.querySelectorAll(target)
            : target instanceof Element ? [target] : Array.from(target || []);
        const created = [];
        list.forEach((el) => {
            if (instances.has(el)) return;
            const f = new Float(el);
            instances.set(el, f);
            created.push(f);
        });
        return created;
    }

    /* ======================================================================
       ПАНЕЛЬ ЖИВОЙ НАСТРОЙКИ
       Alt+Shift+F, ?f3d в адресе или Float3D.panel() из консоли.
       Правки идут поверх CSS через <style id="f3d-live"> и сохраняются
       в localStorage, но применяются, только пока панель открыта или в адресе
       есть ?f3d. Источник правды — CSS: «Копировать CSS» и вставить в файл.
       Панель живёт в Shadow DOM — стили сайта её не задевают, и наоборот.
       ====================================================================== */
    const STORE = 'f3d:tuning';
    const PARAMS = [
        ['Поверхность', [
            ['--f3d-perspective', 'Перспектива', 150, 1200, 10, 'px', 'меньше — сильнее «рыбий глаз»'],
            ['--f3d-tilt', 'Наклон', 0, 35, 0.5, 'deg'],
            ['--f3d-hover-scale', 'Увеличение', 1, 1.25, 0.01, ''],
            ['--f3d-hover-rise', 'Подъём кнопки', -12, 0, 0.5, 'px'],
        ]],
        ['Парение', [
            ['--f3d-depth', 'Высота контента', 0, 90, 1, 'px', 'главный рычаг'],
            ['--f3d-drift', 'Доп. сдвиг к курсору', 0, 12, 0.5, 'px'],
            ['--f3d-badge-depth', 'Бейджи, × высоты', 1, 3, 0.1, ''],
        ]],
        ['Тень и свет', [
            ['--f3d-shadow-offset', 'Отлёт тени', 0, 20, 0.5, 'px'],
            ['--f3d-shadow-blur', 'Размытие тени', 0, 12, 0.5, 'px'],
            ['--f3d-shadow-opacity', 'Плотность тени', 0, 1, 0.05, ''],
            ['--f3d-glare', 'Блик', 0, 0.6, 0.01, ''],
        ]],
        ['Физика', [
            ['--f3d-tilt-stiffness', 'Наклон: жёсткость', 20, 600, 5, ''],
            ['--f3d-tilt-damping', 'Наклон: трение', 2, 60, 1, ''],
            ['--f3d-lift-stiffness', 'Подъём: жёсткость', 20, 800, 5, ''],
            ['--f3d-lift-damping', 'Подъём: трение', 2, 60, 1, 'меньше — сильнее пружинит'],
            ['--f3d-press', 'Просадка при нажатии', 0, 1, 0.05, ''],
        ]],
    ];
    const PRESETS = {
        'Тонко': { '--f3d-depth': 14, '--f3d-tilt': 9, '--f3d-perspective': 520, '--f3d-drift': 1, '--f3d-shadow-offset': 3, '--f3d-glare': 0.1, '--f3d-lift-damping': 22 },
        'Голограмма': { '--f3d-depth': 40, '--f3d-tilt': 20, '--f3d-perspective': 300, '--f3d-drift': 3, '--f3d-shadow-offset': 10, '--f3d-shadow-blur': 6, '--f3d-shadow-opacity': 0.7, '--f3d-glare': 0.38, '--f3d-lift-damping': 8, '--f3d-hover-scale': 1.1 },
    };
    const ALL = PARAMS.flatMap(([, list]) => list);

    let panelHost = null;

    function liveStyle() {
        let s = document.getElementById('f3d-live');
        if (!s) {
            s = document.createElement('style');
            s.id = 'f3d-live';
            document.head.appendChild(s);
        }
        return s;
    }

    function applyLive(values) {
        const body = Object.entries(values).map(([k, v]) => `  ${k}: ${v};`).join('\n');
        // Тройной класс — перебить и дефолты модуля, и настройки конкретной страницы
        liveStyle().textContent = body ? `.f3d.f3d.f3d {\n${body}\n}` : '';
        instances.forEach((f) => { f.cfg = readConfig(f.el); });
    }

    function loadStored() {
        try { return JSON.parse(localStorage.getItem(STORE) || '{}') || {}; } catch (e) { return {}; }
    }

    function saveStored(values) {
        try { localStorage.setItem(STORE, JSON.stringify(values)); } catch (e) { /* приватный режим */ }
    }

    // Закрыли панель — живые правки и рентген убираем: на экране снова ровно то, что в CSS
    function closePanel() {
        panelHost.remove();
        panelHost = null;
        if (!/[?&]f3d\b/.test(location.search)) liveStyle().textContent = '';
        document.documentElement.classList.remove('f3d-xray');
        instances.forEach((f) => { f.cfg = readConfig(f.el); });
    }

    function panel() {
        if (panelHost) { closePanel(); return; }

        const sample = instances.keys().next().value;
        if (!sample) { console.warn('Float3D: на странице нет подключённых элементов'); return; }

        // Базовые значения — то, что сейчас реально действует из CSS (без живых правок)
        liveStyle().textContent = '';
        const cs = getComputedStyle(sample);
        const base = {};
        ALL.forEach(([name]) => {
            base[name] = parseFloat(cs.getPropertyValue(name)) || 0;
        });
        const current = Object.assign({}, base, loadStored());
        const withUnits = () => {
            const out = {};
            ALL.forEach(([name, , , , , unit]) => {
                if (current[name] !== base[name]) out[name] = current[name] + unit;
            });
            return out;
        };
        const commit = () => { const v = withUnits(); applyLive(v); saveStored(Object.fromEntries(Object.keys(v).map((k) => [k, current[k]]))); };

        panelHost = document.createElement('div');
        panelHost.id = 'f3d-panel';
        document.body.appendChild(panelHost);
        const root = panelHost.attachShadow({ mode: 'open' });

        root.innerHTML = `
<style>
  :host { all: initial; position: fixed; right: 16px; bottom: 16px; z-index: 2147483000;
          font: 12px/1.35 system-ui, -apple-system, 'Segoe UI', sans-serif; color: #eee; }
  .box { width: 300px; max-height: min(78vh, 720px); overflow: auto; padding: 14px 14px 12px;
         border-radius: 16px; background: rgba(18, 8, 28, .82); backdrop-filter: blur(18px) saturate(140%);
         -webkit-backdrop-filter: blur(18px) saturate(140%);
         border: 1px solid rgba(255,255,255,.12); box-shadow: 0 24px 60px -20px rgba(0,0,0,.8); }
  header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
  h1 { flex: 1; margin: 0; font-size: 13px; font-weight: 700; letter-spacing: .02em; }
  h1 small { font-weight: 400; opacity: .5; margin-left: 4px; }
  h2 { margin: 14px 0 6px; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: #ff5ba8; }
  .row { display: grid; grid-template-columns: 1fr 56px; gap: 2px 8px; align-items: center; margin: 6px 0; }
  .row label { opacity: .85; }
  .row output { text-align: right; font-variant-numeric: tabular-nums; opacity: .7; }
  .row input { grid-column: 1 / -1; width: 100%; margin: 0; accent-color: #c32178; }
  .hint { grid-column: 1 / -1; font-size: 10.5px; opacity: .45; margin-top: -2px; }
  .chips, .actions { display: flex; flex-wrap: wrap; gap: 6px; }
  .actions { margin-top: 14px; }
  button { all: unset; cursor: pointer; padding: 6px 10px; border-radius: 999px; font-size: 11.5px;
           background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.1); }
  button:hover { background: rgba(255,255,255,.16); }
  button.primary { background: #c32178; border-color: transparent; font-weight: 700; }
  button.primary:hover { background: #e62e8a; }
  button[aria-pressed="true"] { background: rgba(90,176,255,.25); border-color: rgba(90,176,255,.6); }
  .x { padding: 2px 8px; font-size: 14px; }
  .foot { margin-top: 10px; font-size: 10.5px; opacity: .45; }
  textarea { width: 100%; height: 120px; margin-top: 8px; box-sizing: border-box; font: 11px ui-monospace, monospace;
             color: #eee; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; }
</style>
<div class="box" role="dialog" aria-label="Настройка Float3D">
  <header><h1>Float3D<small>Alt+Shift+F</small></h1><button class="x" data-act="close" title="Закрыть">×</button></header>
  <div class="chips">
    <button data-act="xray" aria-pressed="false" title="Разобрать кнопки на слои и показать их сбоку">Рентген</button>
    ${Object.keys(PRESETS).map((p) => `<button data-preset="${p}">${p}</button>`).join('')}
  </div>
  <div id="rows"></div>
  <div class="actions">
    <button class="primary" data-act="copy">Копировать CSS</button>
    <button data-act="reset">Сброс к CSS</button>
  </div>
  <div class="foot">Правки видны только тебе и только с открытой панелью (или с ?f3d в адресе). В код попадают через «Копировать CSS».</div>
</div>`;

        const rows = root.getElementById('rows');
        const inputs = {};
        PARAMS.forEach(([title, list]) => {
            const h = document.createElement('h2');
            h.textContent = title;
            rows.appendChild(h);
            list.forEach(([name, label, min, max, step, unit, hint]) => {
                const row = document.createElement('div');
                row.className = 'row';
                row.innerHTML = `<label>${label}</label><output></output>
                    <input type="range" min="${min}" max="${max}" step="${step}">
                    ${hint ? `<div class="hint">${hint}</div>` : ''}`;
                const input = row.querySelector('input');
                const out = row.querySelector('output');
                const show = () => { out.textContent = `${+(+current[name]).toFixed(2)}${unit}`; };
                input.value = current[name];
                show();
                input.addEventListener('input', () => { current[name] = +input.value; show(); commit(); });
                inputs[name] = { input, show };
                rows.appendChild(row);
            });
        });

        const syncInputs = () => Object.entries(inputs).forEach(([name, { input, show }]) => { input.value = current[name]; show(); });

        root.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const act = btn.dataset.act;
            if (btn.dataset.preset) {
                Object.assign(current, base, PRESETS[btn.dataset.preset]);
                syncInputs();
                commit();
            } else if (act === 'reset') {
                Object.assign(current, base);
                syncInputs();
                commit();
            } else if (act === 'xray') {
                const on = document.documentElement.classList.toggle('f3d-xray');
                btn.setAttribute('aria-pressed', String(on));
            } else if (act === 'close') {
                closePanel();
            } else if (act === 'copy') {
                const css = `/* Float3D — подобрано в панели. Замени блок настроек в CSS. */\n{\n${ALL.map(([name, , , , , unit]) => `    ${name}: ${+(+current[name]).toFixed(3)}${unit};`).join('\n')}\n}`;
                const fallback = () => {
                    let ta = root.querySelector('textarea');
                    if (!ta) { ta = document.createElement('textarea'); root.querySelector('.box').appendChild(ta); }
                    ta.value = css;
                    ta.select();
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(css).then(() => {
                        btn.textContent = 'Скопировано ✓';
                        setTimeout(() => { btn.textContent = 'Копировать CSS'; }, 1400);
                    }, fallback);
                } else {
                    fallback();
                }
            }
        });

        commit();
    }

    addEventListener('keydown', (e) => {
        if (e.altKey && e.shiftKey && e.code === 'KeyF') {
            e.preventDefault();
            panel();
        }
    });

    function boot() {
        attach('[data-f3d]');
        if (/[?&]f3d\b/.test(location.search)) panel();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();

    window.Float3D = { attach, panel };
})();
