/*
 * Float3D — наклон элемента за курсором + содержимое, парящее над поверхностью.
 * swad/js/float3d.js (картинка и ВСЕ настройки — swad/css/float3d.css)
 *
 * Подключение элементов — одной строкой, в любом месте страницы:
 *   Float3D.register('.btn');                                   кнопка: парит вся надпись
 *   Float3D.register('.game-card', { float: '.game-title' });   карточка: парит только название
 *   Опции: float   — что внутри парит (без неё — всё содержимое);
 *          surface — видимая поверхность, если это не сам элемент (по ней считается курсор);
 *          card    — профиль «карточка» (.f3d--card в CSS: свой масштаб и подъём).
 * Элементы, дорисованные скриптом позже, подхватываются сами: слушатель один на документ,
 * а элемент «оживает» при первом наведении.
 *
 * Разделение обязанностей:
 *   JS  — ввод и физика. Курсор → nx/ny ∈ [-1..1], подъём lift ∈ [0..1] (с перелётом),
 *         три числа пишутся в --f3d-nx / --f3d-ny / --f3d-lift на элементе.
 *   CSS — вся картинка: transform поверхности, сдвиг и тень парящего слоя, блик.
 *
 * Глубина — эмуляция («2.5D»), а не настоящий preserve-3d. Настоящее 3D ломает любой
 * overflow/opacity/filter между элементом и парящим слоем (а у карточек они есть всегда).
 * Эмуляция считает, куда бы сместился слой на высоте d при наклоне на угол φ: d·tan φ
 * в плоскости поверхности, — и рисует его там, со своей перспективой для «приближения».
 * После поворота поверхности это ровно та же картинка, но работает внутри чего угодно.
 *
 * API: Float3D.register(selector, opts) · Float3D.panel() (или Alt+Shift+F, или ?f3d в адресе)
 *      Float3D.settings.get() / .set({ buttons, cards }) / .reset() — сила эффекта от
 *      пользователя (0..2, окно «Настройки» в профиле); событие 'f3d:settings' на document.
 */
(function () {
    'use strict';
    if (window.Float3D) return;

    const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const STEP = 1 / 240;          // шаг физики: пружина устойчива при любой частоте кадров

    const registry = [];            // { sel, float, surface, card }
    let selector = '';
    const instances = new WeakMap();
    const active = new Set();

    /* ── Сила эффекта от пользователя ──────────────────────────────────────
       Два множителя 0..2: кнопки и карточки. 1 — как задано в CSS, 0 — выкл.
       Хранятся в браузере, как и тема (dustore_theme), и применяются прямо здесь,
       в <head>, до первой отрисовки: без мигания «сначала сильно, потом слабо». */
    const USER_KEY = 'dustore_effects';
    const USER_DEFAULT = { buttons: 1, cards: 1 };
    let user = Object.assign({}, USER_DEFAULT);

    const clampPower = (v) => (Number.isFinite(+v) ? Math.max(0, Math.min(2, +v)) : 1);

    function loadUser() {
        let saved = {};
        try { saved = JSON.parse(localStorage.getItem(USER_KEY) || '{}') || {}; } catch (e) { /* приватный режим */ }
        // ?? а не ||: ноль — законное значение «выключено»
        user = { buttons: clampPower(saved.buttons ?? 1), cards: clampPower(saved.cards ?? 1) };
    }

    function applyUser() {
        const s = document.documentElement.style;
        s.setProperty('--f3d-user-buttons', String(user.buttons));
        s.setProperty('--f3d-user-cards', String(user.cards));
        document.dispatchEvent(new CustomEvent('f3d:settings', { detail: Object.assign({}, user) }));
    }

    const settings = {
        defaults: Object.freeze(Object.assign({}, USER_DEFAULT)),
        get: () => Object.assign({}, user),
        set(patch) {
            user = {
                buttons: clampPower(patch.buttons ?? user.buttons),
                cards: clampPower(patch.cards ?? user.cards),
            };
            try { localStorage.setItem(USER_KEY, JSON.stringify(user)); } catch (e) { /* приватный режим */ }
            applyUser();
        },
        reset() {
            try { localStorage.removeItem(USER_KEY); } catch (e) { /* приватный режим */ }
            user = Object.assign({}, USER_DEFAULT);
            applyUser();
        },
    };

    loadUser();
    applyUser();
    // Поменяли в другой вкладке — подхватываем без перезагрузки
    addEventListener('storage', (e) => {
        if (e.key !== USER_KEY && e.key !== null) return;
        loadUser();
        applyUser();
    });

    // Скролл/ресайз сдвигают элементы под курсором — кэш прямоугольников устаревает
    let layoutStamp = 0;
    addEventListener('resize', () => { layoutStamp++; }, { passive: true });
    addEventListener('scroll', (e) => {
        layoutStamp++;
        // Прокрутилась страница — элементы уехали из-под неподвижного курсора
        if (e.target === document || e.target === document.scrollingElement) releaseAll();
    }, { capture: true, passive: true });

    /* Пружина: x тянется к target с жёсткостью k, трение c гасит скорость.
       Коэффициент затухания ζ = c / (2·√k): < 1 — с перелётом, ≥ 1 — плавно, без него. */
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
    // Разбить computed-список по запятым, не ломая cubic-bezier(a, b, c, d)
    const splitList = (s) => s.split(/,(?![^(]*\))/).map((t) => t.trim());

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
        if (reducedMotion.matches) cfg.liftC = Math.max(cfg.liftC, 2 * Math.sqrt(cfg.liftK));
        return cfg;
    }

    class Float {
        constructor(el, entry) {
            this.el = el;
            // Опции можно задать и прямо в разметке: data-f3d-float=".title" data-f3d-card
            this.entry = entry = Object.assign({}, entry, {
                float: entry.float || el.dataset.f3dFloat,
                card: entry.card || el.hasAttribute('data-f3d-card'),
            });
            this.surface = (entry.surface && el.querySelector(entry.surface)) || el;
            this.nx = new Spring();
            this.ny = new Spring();
            this.lift = new Spring();
            this.raf = 0;
            this.stamp = -1;
            this.on = false;
            this.tick = this.tick.bind(this);
            this.build();
            // Поверхность меняет размер сама (раскрытие карточки) — перемерить при следующем движении
            if (this.surface !== el && window.ResizeObserver) {
                new ResizeObserver(() => { this.stamp = -1; }).observe(this.surface);
            }
        }

        build() {
            const el = this.el;
            el.classList.add('f3d');
            if (this.entry.card) el.classList.add('f3d--card');

            if (this.entry.float) {
                // Карточка: парят только отмеченные элементы, сами по себе — без обёрток,
                // чтобы не трогать их вёрстку (многоточие, переносы и т.п.)
                el.querySelectorAll(this.entry.float).forEach((t) => t.classList.add('f3d-float'));
            } else {
                // Кнопка: всё содержимое — в один слой (текстовый узел трансформировать нельзя)
                const layer = document.createElement('span');
                layer.className = 'f3d-layer f3d-float';
                // У flex-кнопки слой повторяет её раскладку: gap, выравнивание, направление
                if (/flex/.test(getComputedStyle(el).display)) layer.classList.add('f3d-layer--flex');
                for (const node of Array.from(el.childNodes)) {
                    // Абсолютных детей (бейджи) не переносим: слой с transform стал бы
                    // для них containing block, и top/right считались бы уже от него
                    if (node.nodeType === Node.ELEMENT_NODE && getComputedStyle(node).position === 'absolute') {
                        node.classList.add('f3d-badge');
                        continue;
                    }
                    layer.appendChild(node);
                }
                el.prepend(layer);
            }

            const glare = document.createElement('span');
            glare.className = 'f3d-glare';
            glare.setAttribute('aria-hidden', 'true');
            this.surface.appendChild(glare);
        }

        /* Прямоугольник поверхности В ПОКОЕ, без нашего transform: иначе наклон меняет
           rect, rect меняет наклон — и у краёв элемент дрожит (обратная связь). */
        measure() {
            const el = this.el;
            const prev = el.style.transform;
            el.style.transform = 'none';
            this.rect = this.surface.getBoundingClientRect();
            el.style.transform = prev;
            this.stamp = layoutStamp;
            this.cfg = readConfig(el);
        }

        // Сила от пользователя: 0 — элемент не оживает вовсе
        power() {
            return this.entry.card ? user.cards : user.buttons;
        }

        contains(e) {
            if (this.stamp !== layoutStamp) this.measure();
            const r = this.rect;
            return e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
        }

        aim(e) {
            if (this.stamp !== layoutStamp) this.measure();
            if (reducedMotion.matches) return;           // подъём оставляем, наклон — нет
            const r = this.rect;
            this.nx.target = clamp(((e.clientX - r.left) / r.width) * 2 - 1);
            this.ny.target = clamp(((e.clientY - r.top) / r.height) * 2 - 1);
            this.wake();
        }

        enter(e) {
            this.measure();
            this.lift.target = 1;
            this.aim(e);
        }

        leave() {
            this.nx.target = this.ny.target = this.lift.target = 0;
            this.wake();
        }

        press(down) {
            this.lift.target = down ? this.cfg.press : 1;
            this.wake();
        }

        /* Если у элемента есть свой transition на transform (у .platform-card — 0.3s),
           он догонял бы каждый кадр пружины с опозданием — двойное сглаживание.
           Гасим ТОЛЬКО transform: в конец списка дописываем «transform 0s» —
           при повторе свойства в transition действует последнее упоминание,
           а background и прочие переходы продолжают работать как были. */
        freezeTransition() {
            const cs = getComputedStyle(this.el);
            const props = splitList(cs.transitionProperty);
            const durs = splitList(cs.transitionDuration);
            const touches = props.some((p, i) => /^(all|transform)$/.test(p) && parseFloat(durs[i % durs.length]) > 0);
            if (!touches) return;
            const fns = splitList(cs.transitionTimingFunction);
            const delays = splitList(cs.transitionDelay);
            const list = props.map((p, i) => `${p} ${durs[i % durs.length]} ${fns[i % fns.length]} ${delays[i % delays.length]}`);
            this.prevTransition = this.el.style.transition;
            this.el.style.transition = list.concat('transform 0s').join(', ');
        }

        wake() {
            if (!this.on) {
                this.on = true;
                this.freezeTransition();
                this.el.classList.add('f3d-on');
            }
            if (this.raf) return;
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
            // Полностью лёг — снимаем transform совсем: без него текст снова растровый и резкий
            if (this.lift.target === 0) {
                this.on = false;
                this.el.classList.remove('f3d-on');
                s.removeProperty('--f3d-nx');
                s.removeProperty('--f3d-ny');
                s.removeProperty('--f3d-lift');
                if (this.prevTransition !== undefined) {
                    s.transition = this.prevTransition;
                    this.prevTransition = undefined;
                }
            }
        }
    }

    function instanceFor(el) {
        let f = instances.get(el);
        if (f) return f;
        // Неактивная кнопка не должна «приглашать» к нажатию
        if (el.matches(':disabled, [aria-disabled="true"]')) return null;
        const entry = registry.find((r) => el.matches(r.sel));
        if (!entry) return null;
        f = new Float(el, entry);
        instances.set(el, f);
        return f;
    }

    // Все зарегистрированные элементы под курсором — от внутреннего к внешнему
    // (кнопка внутри карточки: наклоняются обе)
    function chainAt(target) {
        const chain = [];
        let el = target && target.closest ? target.closest(selector) : null;
        while (el) {
            const f = instanceFor(el);
            if (f) chain.push(f);
            el = el.parentElement ? el.parentElement.closest(selector) : null;
        }
        return chain;
    }

    function releaseAll() {
        active.forEach((f) => f.leave());
        active.clear();
    }

    /* Один слушатель на весь документ. Зона наведения — прямоугольник элемента
       в покое, а не его наклонённая фигура: у края край уходит вглубь и
       выскальзывает из-под курсора, и без этого элемент дрожал бы
       enter/leave/enter… Поэтому «ушёл» = вышел за прямоугольник или
       оказался над другим, не вложенным, элементом. */
    function onMove(e) {
        if (e.pointerType === 'touch' || !selector) return;
        const chain = chainAt(e.target).filter((f) => f.power() > 0);
        for (const f of active) {
            if (chain.includes(f)) continue;
            const foreign = chain.some((c) => !c.el.contains(f.el));
            if (!foreign && f.power() > 0 && f.el.isConnected && f.contains(e)) { f.aim(e); continue; }
            f.leave();
            active.delete(f);
        }
        for (const f of chain) {
            if (active.has(f)) f.aim(e);
            else { active.add(f); f.enter(e); }
        }
    }

    // Нажатие: содержимое проседает к поверхности, на отпускании — выпрыгивает обратно.
    // Проседает только самый внутренний элемент (кнопка, а не карточка под ней).
    // Берём его по прямоугольникам покоя, а не по e.target: у края наклонённая кнопка
    // выскальзывает из-под курсора, и target'ом оказалась бы карточка под ней.
    let pressed = null;
    function onDown(e) {
        if (e.pointerType === 'touch' || !selector) return;
        const hits = [...active].filter((f) => f.el.isConnected && f.contains(e));
        const f = hits.find((h) => !hits.some((o) => o !== h && h.el.contains(o.el)));
        if (!f) return;
        pressed = f;
        f.press(true);
    }
    function onUp() {
        if (!pressed) return;
        if (active.has(pressed)) pressed.press(false);
        pressed = null;
    }

    let listening = false;
    function listen() {
        if (listening || !finePointer.matches) return;
        listening = true;
        document.addEventListener('pointermove', onMove, { passive: true });
        document.addEventListener('pointerdown', onDown, { passive: true });
        document.addEventListener('pointerup', onUp, { passive: true });
        // Курсор ушёл за пределы окна — pointermove больше не придёт
        document.addEventListener('pointerout', (e) => { if (!e.relatedTarget) releaseAll(); });
    }

    function register(sel, opts) {
        // Кривой селектор в реестре уронил бы closest() на каждом движении мыши по сайту
        try { document.querySelector(sel); } catch (e) { console.warn('Float3D: неверный селектор', sel); return; }
        registry.push(Object.assign({ sel }, opts));
        selector = registry.map((r) => r.sel).join(', ');
        listen();
    }

    /* ======================================================================
       ПАНЕЛЬ ЖИВОЙ НАСТРОЙКИ — Alt+Shift+F, ?f3d в адресе или Float3D.panel()
       Две вкладки = два уровня каскада: «Все» — :root (общие настройки),
       «Карточки» — .f3d--card (то, что у карточек своё). Если у карточек
       параметр переопределён, в «Все» он помечен — двигать его там для
       карточек бесполезно, и это видно сразу, а не «почему-то не работает».
       Правки — поверх CSS через <style id="f3d-live">, видны только тебе и
       только с открытой панелью. В код — кнопкой «Копировать CSS».
       Панель в Shadow DOM: стили сайта её не задевают, и наоборот.
       ====================================================================== */
    const STORE = 'f3d:tuning:v2';
    const PARAMS = [
        ['Поверхность', [
            ['--f3d-perspective', 'Перспектива', 150, 1200, 10, 'px', 'меньше — сильнее перспектива'],
            ['--f3d-tilt', 'Наклон', 0, 35, 0.5, 'deg'],
            ['--f3d-hover-scale', 'Увеличение', 1, 1.25, 0.01, ''],
            ['--f3d-hover-rise', 'Подъём', -16, 0, 0.5, 'px'],
        ]],
        ['Парение', [
            ['--f3d-depth', 'Высота парения', 0, 90, 1, 'px', 'главный рычаг'],
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
            ['--f3d-tilt-stiffness', 'Наклон: жёсткость', 20, 900, 5, ''],
            ['--f3d-tilt-damping', 'Наклон: трение', 2, 60, 1, ''],
            ['--f3d-lift-stiffness', 'Подъём: жёсткость', 20, 900, 5, ''],
            ['--f3d-lift-damping', 'Подъём: трение', 2, 60, 1, '', 'меньше — сильнее пружинит'],
            ['--f3d-press', 'Просадка при нажатии', 0, 1, 0.05, '', '1 — не проседает, 0 — ложится'],
        ]],
    ];
    const ALL = PARAMS.flatMap(([, list]) => list);
    const UNIT = Object.fromEntries(ALL.map(([name, , , , , unit]) => [name, unit]));
    const SCOPES = {
        all: { label: 'Все', selector: ':root', live: ':root:root' },
        card: { label: 'Карточки', selector: '.f3d--card', live: '.f3d--card.f3d--card' },
    };
    const PRESETS = {
        'Сдержанно': { '--f3d-depth': 10, '--f3d-tilt': 7, '--f3d-perspective': 600, '--f3d-drift': 0.5, '--f3d-shadow-offset': 2, '--f3d-glare': 0.08, '--f3d-lift-damping': 26 },
        'Выразительно': { '--f3d-depth': 26, '--f3d-tilt': 14, '--f3d-perspective': 360, '--f3d-drift': 2, '--f3d-shadow-offset': 6, '--f3d-glare': 0.2, '--f3d-lift-damping': 13 },
        'Голограмма': { '--f3d-depth': 40, '--f3d-tilt': 20, '--f3d-perspective': 300, '--f3d-drift': 3, '--f3d-shadow-offset': 10, '--f3d-shadow-blur': 6, '--f3d-shadow-opacity': 0.7, '--f3d-glare': 0.38, '--f3d-lift-damping': 8 },
    };

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

    function refreshConfigs() {
        active.forEach((f) => { f.cfg = readConfig(f.el); });
    }

    // Значения из CSS как есть: через невидимый «зонд» с нужным классом
    function readScope(scope) {
        const probe = document.createElement('div');
        if (scope === 'card') probe.className = 'f3d f3d--card';
        probe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none';
        document.body.appendChild(probe);
        const cs = getComputedStyle(probe);
        const out = {};
        ALL.forEach(([name]) => { out[name] = parseFloat(cs.getPropertyValue(name)) || 0; });
        probe.remove();
        return out;
    }

    function loadStored() {
        try { return JSON.parse(localStorage.getItem(STORE) || '{}') || {}; } catch (e) { return {}; }
    }

    function saveStored(v) {
        try { localStorage.setItem(STORE, JSON.stringify(v)); } catch (e) { /* приватный режим */ }
    }

    function closePanel() {
        panelHost.remove();
        panelHost = null;
        if (!/[?&]f3d\b/.test(location.search)) liveStyle().textContent = '';
        document.documentElement.classList.remove('f3d-xray');
        refreshConfigs();
    }

    function panel() {
        if (panelHost) { closePanel(); return; }

        // База — то, что реально задано в CSS, без живых правок
        liveStyle().textContent = '';
        const base = { all: readScope('all'), card: readScope('card') };
        // Что карточки переопределяют у себя (значение отличается от общего)
        const cardOwn = new Set(ALL.map(([n]) => n).filter((n) => base.card[n] !== base.all[n]));
        const stored = loadStored();
        const cur = {
            all: Object.assign({}, base.all, stored.all),
            card: Object.assign({}, base.card, stored.card),
        };
        // Параметры, которые карточки берут у «Всех», пока их не тронули во вкладке «Карточки»
        const touched = new Set(Object.keys(stored.card || {}));
        const ownCard = (n) => cardOwn.has(n) || touched.has(n);
        const valueOf = (sc, n) => (sc === 'card' && !ownCard(n) ? cur.all[n] : cur[sc][n]);
        let scope = 'all';

        const changed = (sc) => ALL.map(([n]) => n)
            .filter((n) => (sc === 'all' || ownCard(n)) && cur[sc][n] !== base[sc][n]);
        const commit = () => {
            const block = (sc) => {
                const names = changed(sc);
                return names.length ? `${SCOPES[sc].live} {\n${names.map((n) => `  ${n}: ${cur[sc][n]}${UNIT[n]};`).join('\n')}\n}` : '';
            };
            liveStyle().textContent = [block('all'), block('card')].filter(Boolean).join('\n');
            const pick = (sc) => Object.fromEntries(changed(sc).map((n) => [n, cur[sc][n]]));
            saveStored({ all: pick('all'), card: pick('card') });
            refreshConfigs();
        };

        panelHost = document.createElement('div');
        panelHost.id = 'f3d-panel';
        document.body.appendChild(panelHost);
        const root = panelHost.attachShadow({ mode: 'open' });

        root.innerHTML = `
<style>
  :host { all: initial; position: fixed; right: 16px; bottom: 16px; z-index: 2147483000;
          font: 12px/1.35 system-ui, -apple-system, 'Segoe UI', sans-serif; color: #eee; }
  .box { width: 310px; max-height: min(80vh, 760px); overflow: auto; padding: 14px 14px 12px;
         border-radius: 16px; background: rgba(18, 8, 28, .84); backdrop-filter: blur(18px) saturate(140%);
         -webkit-backdrop-filter: blur(18px) saturate(140%);
         border: 1px solid rgba(255,255,255,.12); box-shadow: 0 24px 60px -20px rgba(0,0,0,.8); }
  header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
  h1 { flex: 1; margin: 0; font-size: 13px; font-weight: 700; letter-spacing: .02em; }
  h1 small { font-weight: 400; opacity: .5; margin-left: 4px; }
  h2 { margin: 14px 0 6px; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: #ff5ba8; }
  .tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 3px; padding: 3px; margin-bottom: 10px;
          border-radius: 10px; background: rgba(0,0,0,.35); }
  .tabs button { justify-content: center; text-align: center; border-radius: 8px; border-color: transparent; background: none; }
  .tabs button[aria-selected="true"] { background: rgba(195,33,120,.5); }
  .row { display: grid; grid-template-columns: 1fr auto; gap: 2px 8px; align-items: center; margin: 6px 0; }
  .row label { opacity: .85; }
  .row output { text-align: right; font-variant-numeric: tabular-nums; opacity: .7; }
  .row input { grid-column: 1 / -1; width: 100%; margin: 0; accent-color: #c32178; }
  .row.muted { opacity: .38; }
  .hint { grid-column: 1 / -1; font-size: 10.5px; opacity: .45; margin-top: -2px; }
  .own { font-size: 10px; color: #5ab0ff; margin-left: 6px; }
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
  textarea { width: 100%; height: 140px; margin-top: 8px; box-sizing: border-box; font: 11px ui-monospace, monospace;
             color: #eee; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; }
</style>
<div class="box" role="dialog" aria-label="Настройка Float3D">
  <header><h1>Float3D<small>Alt+Shift+F</small></h1><button class="x" data-act="close" title="Закрыть">×</button></header>
  <div class="tabs" role="tablist">
    ${Object.entries(SCOPES).map(([id, s]) => `<button role="tab" data-scope="${id}">${s.label}</button>`).join('')}
  </div>
  <div class="chips">
    <button data-act="xray" aria-pressed="false" title="Развернуть кнопки боком и показать слои">Рентген</button>
    ${Object.keys(PRESETS).map((p) => `<button data-preset="${p}">${p}</button>`).join('')}
  </div>
  <div id="rows"></div>
  <div class="actions">
    <button class="primary" data-act="copy">Копировать CSS</button>
    <button data-act="reset">Сброс вкладки</button>
  </div>
  <div class="foot">Правки видны только тебе и только с открытой панелью. В код — «Копировать CSS» → вставить блок в swad/css/float3d.css.</div>
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
                row.innerHTML = `<label>${label}<span class="own" hidden>у карточек своё</span></label><output></output>
                    <input type="range" min="${min}" max="${max}" step="${step}">
                    ${hint ? `<div class="hint">${hint}</div>` : ''}`;
                const input = row.querySelector('input');
                const out = row.querySelector('output');
                const show = () => { out.textContent = `${+(+valueOf(scope, name)).toFixed(3)}${unit}`; };
                input.addEventListener('input', () => {
                    if (scope === 'card') touched.add(name);
                    cur[scope][name] = +input.value;
                    show();
                    commit();
                });
                inputs[name] = { row, input, show, own: row.querySelector('.own') };
                rows.appendChild(row);
            });
        });

        const render = () => {
            root.querySelectorAll('[data-scope]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.scope === scope)));
            Object.entries(inputs).forEach(([name, it]) => {
                it.input.value = valueOf(scope, name);
                it.show();
                const own = scope === 'all' && cardOwn.has(name);
                it.own.hidden = !own;
            });
        };

        root.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const act = btn.dataset.act;
            if (btn.dataset.scope) {
                scope = btn.dataset.scope;
                render();
            } else if (btn.dataset.preset) {
                const preset = PRESETS[btn.dataset.preset];
                if (scope === 'card') Object.keys(preset).forEach((n) => touched.add(n));
                Object.assign(cur[scope], preset);
                render();
                commit();
            } else if (act === 'reset') {
                cur[scope] = Object.assign({}, base[scope]);
                if (scope === 'card') touched.clear();
                render();
                commit();
            } else if (act === 'xray') {
                const on = document.documentElement.classList.toggle('f3d-xray');
                // Элементы «оживают» при первом наведении — для рентгена оживляем все сразу
                if (on) document.querySelectorAll(selector).forEach(instanceFor);
                btn.setAttribute('aria-pressed', String(on));
            } else if (act === 'close') {
                closePanel();
            } else if (act === 'copy') {
                // «Все» — весь блок :root целиком; «Карточки» — только то, чем они отличаются
                const names = ALL.map(([n]) => n).filter((n) => scope === 'all' || ownCard(n));
                const css = `${SCOPES[scope].selector} {\n${names.map((n) => `    ${n}: ${+(+valueOf(scope, n)).toFixed(3)}${UNIT[n]};`).join('\n')}\n}`;
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

        render();
        commit();
    }

    addEventListener('keydown', (e) => {
        if (e.altKey && e.shiftKey && e.code === 'KeyF') {
            e.preventDefault();
            panel();
        }
    });

    function boot() {
        if (/[?&]f3d\b/.test(location.search)) panel();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();

    // [data-f3d] в разметке — подключится без единой строки JS
    register('[data-f3d]', {});

    window.Float3D = { register, attach: register, panel, settings };
})();
