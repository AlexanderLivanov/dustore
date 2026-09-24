<?php
/**
 * swad/static/elements/user_settings.php
 *
 * Окно «Настройки» пользователя. Открывается любой кнопкой с атрибутом
 * data-open-settings — сейчас это шестерёнка в профиле (player.php, только у владельца).
 *
 * Сейчас здесь один раздел — «Эффекты»: сила эффекта Float3D отдельно для кнопок
 * и для карточек (swad/js/float3d.js → Float3D.settings). Значения живут в браузере,
 * как и тема (localStorage), и применяются на всех страницах сразу, ещё в <head>.
 *
 * Новый раздел = ещё одна <section class="us-section"> в .us-body.
 *
 * <dialog> + showModal(): браузер сам держит фокус внутри окна, закрывает его по Esc,
 * рисует подложку (::backdrop) и прячет остальную страницу от скринридера —
 * ничего из этого руками писать не нужно.
 */
?>
<dialog class="us-dialog" id="userSettings" aria-labelledby="usTitle">
    <form method="dialog" class="us-box">
        <header class="us-head">
            <h2 id="usTitle">Настройки</h2>
            <button class="us-close" value="close" aria-label="Закрыть">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true">
                    <path d="M5 5l14 14M19 5L5 19" />
                </svg>
            </button>
        </header>

        <div class="us-body">
            <section class="us-section" id="usEffects">
                <h3>Эффекты</h3>
                <p class="us-lead">Как кнопки и карточки откликаются на курсор: наклон, парящий текст, блик и тень.</p>
                <p class="us-note" id="usNoteTouch" hidden>На сенсорном экране эффект не показывается — настройка сработает на компьютере с мышью.</p>
                <p class="us-note" id="usNoteReduced" hidden>В системе включено «уменьшение движения»: наклона не будет, останется только подъём.</p>

                <div class="us-control" data-key="buttons">
                    <div class="us-row">
                        <label for="usButtons">Кнопки</label>
                        <output for="usButtons"></output>
                    </div>
                    <input type="range" id="usButtons" min="0" max="200" step="5" list="usTicks">
                    <div class="us-scale" aria-hidden="true"><span>Выкл</span><span>Норма</span><span>×2</span></div>
                    <div class="us-preview">
                        <button type="button" class="us-demo-btn" data-f3d>Наведи</button>
                    </div>
                </div>

                <div class="us-control" data-key="cards">
                    <div class="us-row">
                        <label for="usCards">Карточки</label>
                        <output for="usCards"></output>
                    </div>
                    <input type="range" id="usCards" min="0" max="200" step="5" list="usTicks">
                    <div class="us-scale" aria-hidden="true"><span>Выкл</span><span>Норма</span><span>×2</span></div>
                    <div class="us-preview">
                        <div class="us-demo-card" data-f3d data-f3d-card data-f3d-float=".us-demo-title">
                            <div class="us-demo-cover"></div>
                            <div class="us-demo-body">
                                <div class="us-demo-title">Название</div>
                                <div class="us-demo-desc">описание — на месте</div>
                            </div>
                        </div>
                    </div>
                </div>

                <datalist id="usTicks">
                    <option value="0"></option>
                    <option value="100"></option>
                    <option value="200"></option>
                </datalist>
            </section>
        </div>

        <footer class="us-foot">
            <span class="us-status" id="usStatus" role="status">Сохраняется в этом браузере</span>
            <button type="button" class="us-reset" id="usReset">Как было</button>
        </footer>
    </form>
</dialog>

<style>
    /* ── Шестерёнка (ставится на странице, напр. в строке с именем профиля) ── */
    .us-gear {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        padding: 0;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, .14);
        background: rgba(255, 255, 255, .07);
        color: #f8f9fa;
        cursor: pointer;
        transition: background .2s, border-color .2s, box-shadow .2s;
    }

    .us-gear svg {
        transition: transform .5s cubic-bezier(.3, 1.5, .5, 1);
    }

    .us-gear:hover {
        background: rgba(195, 33, 120, .28);
        border-color: rgba(255, 91, 168, .55);
        box-shadow: 0 0 18px rgba(195, 33, 120, .45);
    }

    .us-gear:hover svg,
    .us-gear[aria-expanded="true"] svg {
        transform: rotate(90deg);
    }

    .us-gear:focus-visible {
        outline: 2px solid #ff5ba8;
        outline-offset: 3px;
    }

    /* ── Окно ─────────────────────────────────────────────────────────────── */
    .us-dialog {
        --us-accent: #c32178;
        --us-accent-hi: #ff5ba8;
        width: min(560px, calc(100vw - 32px));
        max-height: min(88vh, 760px);
        /* Модальный <dialog> центрируется браузерным margin: auto, а глобальный
           сброс сайта (* { margin: 0 }) его убивает — окно липло к углу. */
        margin: auto;
        padding: 0;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 20px;
        background: rgba(22, 8, 34, .92);
        backdrop-filter: blur(22px) saturate(140%);
        -webkit-backdrop-filter: blur(22px) saturate(140%);
        color: #f8f9fa;
        box-shadow: 0 40px 100px -30px rgba(0, 0, 0, .9);
        overflow: auto;
    }

    body.moonlight-theme .us-dialog {
        --us-accent: #4a78d8;
        --us-accent-hi: #8fb3ff;
        background: rgba(8, 12, 24, .92);
    }

    .us-dialog[open] {
        animation: usIn .28s cubic-bezier(.2, .8, .2, 1);
    }

    .us-dialog::backdrop {
        background: rgba(6, 2, 12, .55);
        backdrop-filter: blur(3px);
        animation: usFade .28s ease;
    }

    @keyframes usIn {
        from { opacity: 0; transform: translateY(10px) scale(.98); }
    }

    @keyframes usFade {
        from { opacity: 0; }
    }

    .us-box {
        display: flex;
        flex-direction: column;
        margin: 0;
    }

    .us-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 22px 6px;
    }

    .us-head h2 {
        margin: 0;
        text-align: left;
        font-size: 1.35rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    /* Глобальные правила pages.css рассчитаны на секции лендинга:
       h2:after рисует декоративную полосу, section — отступы по 80px */
    .us-head h2::after {
        content: none;
    }

    .us-section {
        padding: 0;
    }

    .us-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08);
        color: #c9b8c4;
        cursor: pointer;
        transition: background .2s, color .2s;
    }

    .us-close:hover {
        background: rgba(255, 255, 255, .18);
        color: #fff;
    }

    .us-body {
        padding: 6px 22px 8px;
    }

    .us-section h3 {
        margin: 10px 0 4px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--us-accent-hi);
    }

    .us-lead {
        margin: 0 0 14px;
        font-size: .88rem;
        line-height: 1.5;
        color: #c9b8c4;
    }

    .us-note {
        margin: 0 0 12px;
        padding: 9px 12px;
        border-radius: 10px;
        font-size: .8rem;
        line-height: 1.45;
        color: #f1dbe8;
        background: rgba(255, 255, 255, .06);
        border: 1px dashed rgba(255, 255, 255, .16);
    }

    /* Строка настройки: ползунок слева, живой пример справа */
    .us-control {
        display: grid;
        grid-template-columns: 1fr 170px;
        grid-template-rows: auto auto auto;
        column-gap: 22px;
        align-items: center;
        margin-bottom: 12px;
        padding: 16px 16px 14px;
        border-radius: 16px;
        background: rgba(255, 255, 255, .04);
        border: 1px solid rgba(255, 255, 255, .08);
    }

    .us-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
    }

    .us-row label {
        font-weight: 600;
        font-size: 1rem;
    }

    .us-row output {
        font-size: .82rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--us-accent-hi);
    }

    .us-control.is-off .us-row output {
        color: #8e7f8a;
    }

    .us-preview {
        grid-column: 2;
        grid-row: 1 / 4;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 96px;
    }

    .us-control.is-off .us-preview {
        opacity: .55;
    }

    /* Ползунок: закрашенная часть = значение (--fill ставит скрипт) */
    .us-control input[type="range"] {
        --fill: 50%;
        width: 100%;
        height: 22px;
        margin: 10px 0 2px;
        background: transparent;
        -webkit-appearance: none;
        appearance: none;
        cursor: pointer;
    }

    .us-control input[type="range"]::-webkit-slider-runnable-track {
        height: 6px;
        border-radius: 999px;
        background: linear-gradient(to right, var(--us-accent) var(--fill), rgba(255, 255, 255, .14) var(--fill));
    }

    .us-control input[type="range"]::-moz-range-track {
        height: 6px;
        border-radius: 999px;
        background: linear-gradient(to right, var(--us-accent) var(--fill), rgba(255, 255, 255, .14) var(--fill));
    }

    .us-control input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 20px;
        height: 20px;
        margin-top: -7px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 0 0 4px var(--us-accent), 0 4px 12px rgba(0, 0, 0, .5);
        transition: transform .15s;
    }

    .us-control input[type="range"]::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border: 0;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 0 0 4px var(--us-accent), 0 4px 12px rgba(0, 0, 0, .5);
    }

    .us-control input[type="range"]:active::-webkit-slider-thumb {
        transform: scale(1.12);
    }

    .us-control input[type="range"]:focus-visible {
        outline: 2px solid var(--us-accent-hi);
        outline-offset: 4px;
        border-radius: 8px;
    }

    /* Подписи шкалы: «Норма» ровно по центру — там 100% */
    .us-scale {
        position: relative;
        height: 14px;
        font-size: .7rem;
        color: #8e7f8a;
    }

    .us-scale span {
        position: absolute;
        top: 0;
    }

    .us-scale span:nth-child(1) { left: 0; }
    .us-scale span:nth-child(2) { left: 50%; transform: translateX(-50%); }
    .us-scale span:nth-child(3) { right: 0; }

    /* ── Живые примеры ── */
    .us-demo-btn {
        padding: 11px 24px;
        border: 0;
        border-radius: 999px;
        background: var(--us-accent);
        color: #fff;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 8px 20px -8px rgba(195, 33, 120, .8);
    }

    .us-demo-card {
        width: 150px;
        border-radius: 14px;
        overflow: hidden;
        background: #1b0a26;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .1), 0 10px 26px -12px rgba(0, 0, 0, .9);
        cursor: default;
    }

    body.moonlight-theme .us-demo-card {
        background: #101728;
    }

    .us-demo-cover {
        height: 62px;
        background: linear-gradient(160deg, #14041d 0%, #400c4a 45%, #74155d 78%, #c32178 100%);
    }

    body.moonlight-theme .us-demo-cover {
        background: linear-gradient(160deg, #050a14 0%, #13244a 50%, #2d4f9a 100%);
    }

    .us-demo-body {
        padding: 9px 11px 11px;
    }

    .us-demo-title {
        font-weight: 700;
        font-size: .9rem;
    }

    .us-demo-desc {
        margin-top: 3px;
        font-size: .72rem;
        color: #8e7f8a;
    }

    .us-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 22px 18px;
    }

    .us-status {
        font-size: .78rem;
        color: #8e7f8a;
        transition: color .2s;
    }

    .us-status.is-saved {
        color: #6fdc9b;
    }

    .us-reset {
        padding: 8px 16px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, .16);
        background: transparent;
        color: #f8f9fa;
        font: inherit;
        font-size: .85rem;
        cursor: pointer;
        transition: background .2s;
    }

    .us-reset:hover {
        background: rgba(255, 255, 255, .1);
    }

    @media (max-width: 520px) {
        .us-control {
            grid-template-columns: 1fr;
        }

        .us-preview {
            grid-column: 1;
            grid-row: auto;
            min-height: 0;
            margin-top: 12px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .us-dialog[open],
        .us-dialog::backdrop,
        .us-gear svg {
            animation: none;
            transition: none;
        }
    }
</style>

<script>
    (function () {
        const dlg = document.getElementById('userSettings');
        if (!dlg) return;
        const F = window.Float3D;

        // Без модуля эффектов разделу нечего настраивать
        if (!F || !F.settings) {
            document.getElementById('usEffects').hidden = true;
        }

        const openers = document.querySelectorAll('[data-open-settings]');
        openers.forEach((btn) => btn.addEventListener('click', () => {
            sync();
            dlg.showModal();
            btn.setAttribute('aria-expanded', 'true');
        }));
        dlg.addEventListener('close', () => openers.forEach((b) => b.setAttribute('aria-expanded', 'false')));

        // Клик мимо окна — по подложке. У <dialog> нет отступов, поэтому
        // e.target === dlg бывает только снаружи .us-box.
        dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });

        document.getElementById('usNoteTouch').hidden = matchMedia('(hover: hover) and (pointer: fine)').matches;
        document.getElementById('usNoteReduced').hidden = !matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!F || !F.settings) return;

        const status = document.getElementById('usStatus');
        const STATUS_IDLE = status.textContent;
        let statusTimer = 0;

        const controls = Array.from(dlg.querySelectorAll('.us-control')).map((el) => ({
            el,
            key: el.dataset.key,
            input: el.querySelector('input'),
            out: el.querySelector('output'),
        }));

        const label = (v) => (v === 0 ? 'Выкл' : v === 100 ? 'Норма' : v === 200 ? 'Максимум' : v + '%');

        function paint(c, v) {
            c.input.value = v;
            c.out.textContent = label(v);
            c.input.setAttribute('aria-valuetext', label(v));
            c.input.style.setProperty('--fill', (v / 2) + '%');
            c.el.classList.toggle('is-off', v === 0);
        }

        function sync() {
            const s = F.settings.get();
            controls.forEach((c) => paint(c, Math.round(s[c.key] * 100)));
        }

        function saved() {
            status.textContent = 'Сохранено ✓';
            status.classList.add('is-saved');
            clearTimeout(statusTimer);
            statusTimer = setTimeout(() => {
                status.textContent = STATUS_IDLE;
                status.classList.remove('is-saved');
            }, 1400);
        }

        // Применяется сразу, пока тянешь: примеры справа — живые
        controls.forEach((c) => c.input.addEventListener('input', () => {
            const v = +c.input.value;
            paint(c, v);
            F.settings.set({ [c.key]: v / 100 });
            saved();
        }));

        document.getElementById('usReset').addEventListener('click', () => {
            F.settings.reset();
            sync();
            saved();
        });

        // Поменяли в другой вкладке — ползунки догоняют
        document.addEventListener('f3d:settings', () => { if (dlg.open) sync(); });
        sync();
    })();
</script>
