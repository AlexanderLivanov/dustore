<?php

/**
 * devs/includes/shell.php — оболочка консоли: док снизу + тайловые окна.
 * Подключается из header.php, когда страницу открыли напрямую (не в окне).
 * Каждое окно — iframe с обычной страницей консоли; footer.php внутри окна сообщает оболочке app/title.
 *
 * Сохранение: страница в окне вызывает dsWM.saver(window, {save}) и dsWM.saverState(window, state, msg);
 * индикатор слева от дока показывает состояние активного окна, клик или Ctrl+S — сохранить.
 * Клавиши (работают и внутри окон):
 *   Ctrl/⌘+K — поиск разделов   Alt+1…9 — приложение из дока   Alt+J/K — следующее/предыдущее окно
 *   Alt+Enter — окно в мастер    Alt+F — развернуть              Alt+M — свернуть   Alt+W — закрыть
 */
$ds_uname = $curr_user_data['username'] ?? '';
$ds_upic  = $curr_user_data['profile_picture'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $page_title ?> — Dustore.Devs</title>
    <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script>
        // Оболочка не должна открываться внутри окна (старые браузеры без Sec-Fetch-Dest)
        if (window.top !== window) {
            const u = new URL(location.href);
            u.searchParams.set('embed', '1');
            location.replace(u);
        }
    </script>
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
<?= $ds_tokens ?>

        html,
        body {
            height: 100%;
            overflow: hidden;
            font-family: 'Inter', -apple-system, sans-serif;
            color: var(--tp);
            background:
                radial-gradient(1100px 600px at 15% -10%, rgba(195, 33, 120, .16), transparent 60%),
                radial-gradient(900px 500px at 100% 110%, rgba(110, 40, 200, .12), transparent 60%),
                var(--dark);
        }

        button {
            font: inherit;
            color: inherit;
            background: none;
            border: 0;
            cursor: pointer;
        }

        /* ── Рабочий стол и окна ── */
        .desk {
            position: absolute;
            inset: 10px 10px 92px;
        }

        .win {
            position: absolute;
            display: flex;
            flex-direction: column;
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
            transition: left .24s cubic-bezier(.2, .8, .2, 1), top .24s cubic-bezier(.2, .8, .2, 1),
                width .24s cubic-bezier(.2, .8, .2, 1), height .24s cubic-bezier(.2, .8, .2, 1),
                opacity .2s, transform .24s, border-color .15s;
        }

        .win.on {
            border-color: rgba(195, 33, 120, .55);
            box-shadow: 0 0 0 1px rgba(195, 33, 120, .2), 0 14px 40px rgba(0, 0, 0, .45);
            z-index: 2;
        }

        .win.solo {
            z-index: 3;
        }

        .win.min,
        .win.behind {
            opacity: 0;
            pointer-events: none;
        }

        .win.min {
            transform: translateY(60px) scale(.25);
            transform-origin: 50% 100%;
        }

        .win.lift {
            opacity: .75;
        }

        .win.drop {
            border-color: var(--pl);
            box-shadow: 0 0 0 2px var(--pl);
        }

        .win-bar {
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 8px 0 12px;
            background: rgba(255, 255, 255, .025);
            border-bottom: 1px solid var(--bd);
            font-size: 12px;
            font-weight: 600;
            color: var(--ts);
            cursor: grab;
            user-select: none;
            touch-action: none;
        }

        .win.on .win-bar {
            color: var(--tt);
        }

        .lights {
            display: flex;
            gap: 7px;
            margin-right: 4px;
        }

        .lights button {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .13);
        }

        .win.on .l-close { background: #ff5f57; }
        .win.on .l-min { background: #febc2e; }
        .win.on .l-max { background: #28c840; }

        .win-ico {
            font-size: 16px;
            color: var(--p);
        }

        .win-title {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .win-tool {
            width: 26px;
            height: 26px;
            border-radius: 7px;
            display: grid;
            place-items: center;
            color: var(--tm);
        }

        .win-tool:hover {
            background: rgba(255, 255, 255, .06);
            color: var(--tt);
        }

        .win-tool[disabled] {
            visibility: hidden;
        }

        .win-tool .material-icons {
            font-size: 17px;
        }

        .win iframe {
            flex: 1;
            width: 100%;
            border: 0;
            background: var(--dark);
        }

        /* во время перетаскивания iframe'ы не должны перехватывать мышь */
        .desk.busy iframe {
            pointer-events: none;
        }

        .desk.resizing .win {
            transition: none;
        }

        .gutter {
            position: absolute;
            z-index: 4;
            display: grid;
            place-items: center;
        }

        .gutter.v { width: 8px; cursor: col-resize; }
        .gutter.h { height: 8px; cursor: row-resize; }

        .gutter::after {
            content: '';
            border-radius: 2px;
            background: transparent;
            transition: background .15s;
        }

        .gutter.v::after { width: 3px; height: 40px; }
        .gutter.h::after { width: 40px; height: 3px; }

        .gutter:hover::after,
        .desk.resizing .gutter::after {
            background: var(--pl);
        }

        .empty {
            position: absolute;
            inset: 0;
            display: grid;
            place-content: center;
            justify-items: center;
            gap: 14px;
            text-align: center;
            color: var(--tm);
            font-size: 13px;
        }

        .empty-logo {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, var(--p), var(--pl));
            box-shadow: 0 10px 40px rgba(195, 33, 120, .35);
        }

        .empty[hidden] {
            display: none;
        }

        .empty b {
            color: var(--tt);
            font-size: 16px;
        }

        kbd {
            font: 600 11px 'Inter', sans-serif;
            color: var(--ts);
            background: var(--elev);
            border: 1px solid var(--bd);
            border-bottom-width: 2px;
            border-radius: 5px;
            padding: 1px 6px;
        }

        /* ── Док ── */
        .dock {
            position: fixed;
            z-index: 10;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            height: 70px;
            display: flex;
            align-items: flex-end;
            gap: 8px;
            padding: 10px 12px;
            background: rgba(26, 10, 36, .55);
            -webkit-backdrop-filter: blur(18px) saturate(1.5);
            backdrop-filter: blur(18px) saturate(1.5);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, .5), inset 0 1px 0 rgba(255, 255, 255, .06);
        }

        .dock-item {
            position: relative;
            flex: none;
            width: 48px;
            aspect-ratio: 1;
            border-radius: 13px;
            display: grid;
            place-items: center;
            color: var(--tt);
            text-decoration: none;
            font-weight: 700;
            background: linear-gradient(160deg, #2f1742, #1b0a27);
            border: 1px solid rgba(255, 255, 255, .08);
            transition: width .16s ease, background .15s;
            /* размер иконки следует за шириной плитки */
            container-type: inline-size;
        }

        .dock-ico {
            font-size: 50cqw !important;
            transition: color .15s;
        }

        .dock-item.on {
            background: linear-gradient(160deg, #4a1340, #24082c);
            border-color: rgba(195, 33, 120, .45);
        }

        .dock-item.on .dock-ico {
            color: var(--pl);
        }

        /* индикатор запущенного приложения */
        .dock-item.run::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -8px;
            width: 4px;
            height: 4px;
            margin-left: -2px;
            border-radius: 50%;
            background: var(--tt);
            opacity: .75;
        }

        .dock-ava {
            overflow: hidden;
        }

        .dock-ini {
            font-size: 34cqw;
        }

        .dock-ava img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dock-user {
            border-radius: 50%;
        }

        .dock-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background: var(--p);
            border: 2px solid #1d0b28;
            color: #fff;
            font: 700 10px/14px 'Inter', sans-serif;
            text-align: center;
        }

        .dock-tip {
            position: absolute;
            bottom: calc(100% + 14px);
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 9px;
            border-radius: 7px;
            background: rgba(20, 4, 29, .94);
            border: 1px solid var(--bd);
            color: var(--tt);
            font: 500 12px 'Inter', sans-serif;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity .12s;
        }

        .dock-sep {
            align-self: stretch;
            width: 1px;
            margin: 2px 2px;
            background: rgba(255, 255, 255, .12);
        }

        @media (hover: hover) {
            .dock-item:hover .dock-tip {
                opacity: 1;
            }

            /* увеличение как в macOS: наведённая плитка и её соседи */
            .dock-item:hover {
                width: 70px;
            }

            .dock-item:hover + .dock-item,
            .dock-item:has(+ .dock-item:hover) {
                width: 58px;
            }
        }

        /* ── Сохранение (слева от дока) и полноэкранный режим (справа в углу) ── */
        .dock-saver,
        .fs-btn {
            display: grid;
            place-items: center;
            width: 58px;
            border-radius: 20px;
            background: rgba(26, 10, 36, .55);
            -webkit-backdrop-filter: blur(18px) saturate(1.5);
            backdrop-filter: blur(18px) saturate(1.5);
            border: 1px solid rgba(255, 255, 255, .09);
            box-shadow: 0 10px 40px rgba(0, 0, 0, .5), inset 0 1px 0 rgba(255, 255, 255, .06);
            color: var(--tm);
            transition: color .2s, border-color .2s;
        }

        .dock-saver {
            position: absolute;
            top: -1px;
            bottom: -1px;
            right: calc(100% + 10px);
        }

        .fs-btn {
            position: fixed;
            z-index: 10;
            right: 10px;
            bottom: 10px;
            height: 70px;
        }

        .dock-saver[hidden],
        .fs-btn[hidden] {
            display: none;
        }

        .dock-saver:hover,
        .fs-btn:hover {
            color: var(--tt);
        }

        .dock-saver:hover .dock-tip,
        .fs-btn:hover .dock-tip {
            opacity: 1;
        }

        .fs-btn .dock-tip {
            left: auto;
            right: 0;
            transform: none;
        }

        .dock-saver .material-icons,
        .fs-btn .material-icons {
            font-size: 26px;
        }

        .dock-saver[data-state="saved"] { color: rgba(0, 214, 143, .7); }
        .dock-saver[data-state="dirty"],
        .dock-saver[data-state="manual"] { color: var(--warn); border-color: rgba(255, 170, 0, .35); }
        .dock-saver[data-state="error"] { color: var(--err); border-color: rgba(255, 61, 113, .4); }
        .dock-saver[data-state="saving"] .material-icons { animation: spin 1s linear infinite; }

        @keyframes spin {
            to { transform: rotate(-360deg); }
        }

        /* несохранённое окно: точка перед заголовком */
        .win.dirty .win-title::before {
            content: '● ';
            color: var(--warn);
        }

        /* ── Поиск (Ctrl+K) ── */
        .spot {
            position: fixed;
            inset: 0;
            z-index: 20;
            background: rgba(10, 2, 16, .5);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }

        .spot[hidden] {
            display: none;
        }

        .spot-box {
            width: min(560px, calc(100vw - 32px));
            margin: 16vh auto 0;
            background: rgba(26, 10, 36, .96);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 16px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .6);
            overflow: hidden;
        }

        .spot-in {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 16px;
            border-bottom: 1px solid var(--bd);
            color: var(--tm);
        }

        .spot-in input {
            flex: 1;
            height: 52px;
            background: none;
            border: 0;
            outline: 0;
            color: var(--tt);
            font: 16px 'Inter', sans-serif;
        }

        .spot-list {
            max-height: 50vh;
            overflow-y: auto;
            padding: 6px;
        }

        .spot-row {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 9px 10px;
            border-radius: 9px;
            text-align: left;
            font-size: 13px;
            color: var(--ts);
        }

        .spot-row > .material-icons {
            font-size: 18px;
            color: var(--tm);
        }

        .spot-row.sel {
            background: rgba(195, 33, 120, .16);
            color: #fff;
        }

        .spot-row.sel > .material-icons {
            color: var(--pl);
        }

        .spot-label {
            flex: 1;
        }

        /* галочка «закрепить в доке» */
        .spot-pin {
            flex: none;
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 1.5px solid rgba(255, 255, 255, .22);
            display: grid;
            place-items: center;
            color: transparent;
            transition: background .12s, border-color .12s;
        }

        .spot-pin .material-icons {
            font-size: 14px;
        }

        .spot-pin:hover {
            border-color: var(--pl);
        }

        .spot-pin.on {
            background: var(--p);
            border-color: var(--p);
            color: #fff;
        }

        .spot-grp {
            font-size: 11px;
            color: var(--tm);
        }

        .spot-cnt {
            background: var(--p);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 9px;
            padding: 1px 6px;
        }

        .spot-foot {
            padding: 9px 16px;
            border-top: 1px solid var(--bd);
            font-size: 11px;
            color: var(--tm);
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        /* ── Телефон: одно окно на экран, док прокручивается ── */
        @media (max-width: 760px) {
            .desk {
                inset: 0 0 74px;
            }

            .win {
                border-radius: 0;
                border-width: 0;
            }

            .dock {
                left: 0;
                right: 0;
                bottom: 0;
                transform: none;
                height: auto;
                padding-bottom: max(10px, env(safe-area-inset-bottom));
                border-radius: 18px 18px 0 0;
                overflow-x: auto;
                scrollbar-width: none;
            }

            .dock-item {
                width: 44px;
            }

            .dock-tip,
            .gutter,
            .l-max,
            .fs-btn {
                display: none;
            }

            .dock-saver {
                position: fixed;
                top: auto;
                right: 10px;
                bottom: 84px;
                width: 48px;
                height: 48px;
                border-radius: 14px;
            }
        }
    </style>
</head>

<body>
    <div class="desk" id="desk">
        <div class="empty" id="empty" hidden>
            <div class="empty-logo">D</div>
            <b>Dustore.Devs</b>
            <div>Открой раздел в доке или найди его: <kbd>Ctrl</kbd> + <kbd>K</kbd></div>
            <div><kbd>Alt</kbd>+<kbd>1…9</kbd> раздел · <kbd>Alt</kbd>+<kbd>J</kbd>/<kbd>K</kbd> окна · <kbd>Alt</kbd>+<kbd>Enter</kbd> в мастер · <kbd>Alt</kbd>+<kbd>F</kbd> развернуть · <kbd>Alt</kbd>+<kbd>W</kbd> закрыть</div>
        </div>
    </div>

    <nav class="dock" id="dock">
        <!-- сохранение активного окна: страница регистрирует себя через dsWM.saver() -->
        <button class="dock-saver" id="saver" hidden>
            <span class="material-icons">cloud_done</span>
            <span class="dock-tip"></span>
        </button>
        <a class="dock-item dock-ava" href="/devs/select">
            <?php if ($studio_avatar): ?><img src="<?= htmlspecialchars($studio_avatar) ?>" alt=""><?php else: ?><span class="dock-ini"><?= $studio_initials ?></span><?php endif; ?>
            <span class="dock-tip"><?= $studio_name ?> · <?= htmlspecialchars($role_name) ?> — сменить студию</span>
        </a>
        <span class="dock-sep"></span>
        <!-- закреплённые разделы вставляет renderDock() перед #dock-end -->
        <span class="dock-sep" id="dock-end"></span>
        <button class="dock-item" id="launch">
            <span class="dock-ico material-icons">apps</span>
            <span class="dock-tip">Все разделы · Ctrl+K</span>
        </button>
        <a class="dock-item dock-ava dock-user" href="/player/<?= htmlspecialchars($ds_uname) ?>">
            <?php if ($ds_upic): ?><img src="<?= htmlspecialchars($ds_upic) ?>" alt=""><?php else: ?><span class="dock-ini"><?= htmlspecialchars(mb_strtoupper(mb_substr($ds_uname ?: 'U', 0, 2))) ?></span><?php endif; ?>
            <span class="dock-tip"><?= htmlspecialchars($ds_uname) ?> — назад в профиль</span>
        </a>
    </nav>

    <button class="fs-btn" id="fs">
        <span class="material-icons">fullscreen</span>
        <span class="dock-tip">Во весь экран</span>
    </button>

    <div class="spot" id="spot" hidden>
        <div class="spot-box">
            <label class="spot-in"><span class="material-icons">search</span><input id="spot-q" placeholder="Найти раздел…" autocomplete="off"></label>
            <div class="spot-list" id="spot-list"></div>
            <div class="spot-foot"><span><kbd>↑</kbd><kbd>↓</kbd> выбрать</span><span><kbd>Enter</kbd> открыть</span><span><kbd>Shift</kbd>+<kbd>Enter</kbd> новое окно</span><span><kbd>Tab</kbd> в док</span><span><kbd>Esc</kbd> закрыть</span></div>
        </div>
    </div>

    <template id="win-tpl">
        <section class="win">
            <header class="win-bar">
                <span class="lights">
                    <button class="l-close" data-act="close" title="Закрыть (Alt+W)"></button>
                    <button class="l-min" data-act="min" title="Свернуть (Alt+M)"></button>
                    <button class="l-max" data-act="max" title="Развернуть (Alt+F)"></button>
                </span>
                <span class="win-ico material-icons"></span>
                <span class="win-title"></span>
                <button class="win-tool" data-act="back" title="Назад" disabled><span class="material-icons">arrow_back</span></button>
                <button class="win-tool" data-act="reload" title="Обновить"><span class="material-icons">refresh</span></button>
            </header>
            <iframe></iframe>
        </section>
    </template>

    <script>
        (() => {
            const APPS = <?= json_encode(array_map(fn($a) => [
                                'id' => $a['id'], 'href' => $a['href'], 'icon' => $a['icon'], 'label' => $a['label'],
                                'group' => $a['group'], 'dock' => $a['dock'], 'top' => !empty($a['top']), 'badge' => $a['badge'],
                            ], $apps), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const KEY = 'dsWM:<?= (int)$studio_id ?>';
            const GAP = 8;
            const byId = Object.fromEntries(APPS.map(a => [a.id, a]));
            const PIN_KEY = 'dsDock';
            let pins = loadPins();
            const $ = s => document.querySelector(s);
            const desk = $('#desk'), tpl = $('#win-tpl');

            let wins = [], focused = null, maxed = null, ratio = .55, seq = 0;

            const clean = url => {
                const u = new URL(url, location.origin);
                u.searchParams.delete('embed');
                return u.pathname + u.search;
            };
            const appFor = url => {
                const p = new URL(url, location.origin).pathname.replace(/\.php$/, '').replace(/\/$/, '') || '/';
                return APPS.find(a => a.href.replace(/\/$/, '') === p);
            };
            const isMono = () => innerWidth <= 760;

            // ── окна ──────────────────────────────────────────────────────
            function open(url, o = {}) {
                const el = tpl.content.firstElementChild.cloneNode(true);
                const w = {
                    id: ++seq, el, url: clean(url), stack: [], back: false,
                    frame: el.querySelector('iframe'),
                    app: o.app || appFor(url)?.id || 'dashboard',
                    title: '', min: !!o.min, t: 0,
                };
                w.title = byId[w.app]?.label || '';
                if (!w.min) w.frame.src = w.url; // свёрнутые при восстановлении грузим только когда развернут
                w.frame.addEventListener('load', () => onLoad(w));
                bindBar(w);
                desk.append(el);
                wins.push(w);
                paint(w);
                if (o.focus !== false) focus(w);
                else layout();
                return w;
            }

            function paint(w) {
                w.el.querySelector('.win-ico').textContent = byId[w.app]?.icon || 'web_asset';
                w.el.querySelector('.win-title').textContent = w.title;
                w.el.querySelector('[data-act=back]').disabled = w.stack.length < 2;
            }

            function onLoad(w) {
                try {
                    const win = w.frame.contentWindow;
                    if (win.location.href === 'about:blank') return;
                    w.url = clean(win.location.href);
                    w.title = win.document.title.replace(/\s+—\s+Dustore\.Devs$/, '') || w.title;
                } catch (e) { return; } // чужой origin — оставляем как есть
                if (w.back) w.back = false;
                else if (w.stack.at(-1) !== w.url) w.stack.push(w.url);
                paint(w);
                if (w === focused) syncUrl();
                paintSaver();
                save();
            }

            function focus(w) {
                if (!w) return;
                if (w.min) restore(w);
                focused = w;
                w.t = Date.now();
                wins.forEach(x => x.el.classList.toggle('on', x === w));
                layout();
                syncUrl();
                paintSaver();
            }

            function restore(w) {
                w.min = false;
                if (!w.frame.src) w.frame.src = w.url;
            }

            function lastVisible(except) {
                return wins.filter(x => !x.min && x !== except).sort((a, b) => b.t - a.t)[0] || null;
            }

            function close(w) {
                if (!w) return;
                // удаление iframe не вызывает beforeunload — спрашиваем сами
                const s = saverOf(w);
                if (s && s.state !== 'saved' && !confirm('В окне «' + w.title + '» есть несохранённые изменения. Закрыть?')) return;
                w.el.remove();
                wins = wins.filter(x => x !== w);
                if (maxed === w) maxed = null;
                if (focused === w) focused = null;
                const next = lastVisible();
                next ? focus(next) : (layout(), syncUrl(), paintSaver());
            }

            function minimize(w) {
                if (!w || isMono() && wins.length === 1) return;
                w.min = true;
                if (maxed === w) maxed = null;
                if (focused === w) {
                    focused = null;
                    w.el.classList.remove('on');
                    focus(lastVisible(w));
                }
                layout();
                syncUrl();
            }

            function toggleMax(w) {
                if (!w) return;
                maxed = maxed === w ? null : w;
                focus(w);
            }

            function zoom(w) { // dwm-style: окно в мастер-область
                if (!w) return;
                wins = [w, ...wins.filter(x => x !== w)];
                desk.append(...wins.map(x => x.el)); // порядок DOM = порядок фокуса по Tab
                focus(w);
            }

            function cycle(d) {
                const vis = wins.filter(x => !x.min);
                if (!vis.length) return;
                const i = vis.indexOf(focused);
                focus(vis[(i + d + vis.length) % vis.length]);
            }

            // ── раскладка: dwindle — каждое следующее окно делит остаток по длинной стороне ──
            let gutter = null;

            function layout() {
                const W = desk.clientWidth, H = desk.clientHeight;
                const vis = wins.filter(x => !x.min);
                const solo = isMono() ? (focused && !focused.min ? focused : vis.at(-1)) : (maxed && !maxed.min ? maxed : null);
                const place = (w, r) => Object.assign(w.el.style, {
                    left: r.x + 'px', top: r.y + 'px', width: Math.max(r.w, 0) + 'px', height: Math.max(r.h, 0) + 'px',
                });
                let split = null;

                wins.forEach(w => {
                    w.el.classList.toggle('min', w.min);
                    w.el.classList.toggle('solo', w === solo);
                    w.el.classList.toggle('behind', !!solo && w !== solo && !w.min);
                });

                if (solo) {
                    place(solo, { x: 0, y: 0, w: W, h: H });
                } else {
                    let r = { x: 0, y: 0, w: W, h: H };
                    vis.forEach((w, i) => {
                        if (i === vis.length - 1) return place(w, r);
                        const v = r.w >= r.h, k = i === 0 ? ratio : .5;
                        const s = (v ? r.w : r.h) * k;
                        if (i === 0) split = { v, at: s, size: v ? W : H };
                        place(w, v ? { x: r.x, y: r.y, w: s - GAP / 2, h: r.h } : { x: r.x, y: r.y, w: r.w, h: s - GAP / 2 });
                        r = v ? { x: r.x + s + GAP / 2, y: r.y, w: r.w - s - GAP / 2, h: r.h } : { x: r.x, y: r.y + s + GAP / 2, w: r.w, h: r.h - s - GAP / 2 };
                    });
                }
                drawGutter(split);
                $('#empty').hidden = vis.length > 0;
                dockState();
                save();
            }

            function drawGutter(split) {
                if (!split) return gutter && (gutter.remove(), gutter = null);
                if (!gutter) {
                    gutter = document.createElement('div');
                    gutter.addEventListener('pointerdown', startResize);
                    desk.append(gutter);
                }
                gutter.className = 'gutter ' + (split.v ? 'v' : 'h');
                gutter.split = split;
                Object.assign(gutter.style, split.v ?
                    { left: split.at - GAP / 2 + 'px', top: 0, height: '100%', width: '' } :
                    { top: split.at - GAP / 2 + 'px', left: 0, width: '100%', height: '' });
            }

            function startResize(e) {
                const { v, size } = gutter.split, box = desk.getBoundingClientRect();
                gutter.setPointerCapture(e.pointerId);
                desk.classList.add('busy', 'resizing');
                const move = ev => {
                    ratio = Math.min(.8, Math.max(.2, ((v ? ev.clientX - box.left : ev.clientY - box.top)) / size));
                    layout();
                };
                const up = () => {
                    desk.classList.remove('busy', 'resizing');
                    gutter.removeEventListener('pointermove', move);
                    save();
                };
                gutter.addEventListener('pointermove', move);
                gutter.addEventListener('pointerup', up, { once: true });
            }

            // ── заголовок окна: кнопки, двойной клик, перетаскивание = обмен местами ──
            function bindBar(w) {
                const bar = w.el.querySelector('.win-bar');
                bar.addEventListener('click', e => {
                    const act = e.target.closest('[data-act]')?.dataset.act;
                    if (act === 'close') close(w);
                    else if (act === 'min') minimize(w);
                    else if (act === 'max') toggleMax(w);
                    else if (act === 'reload') w.frame.contentWindow.location.reload();
                    else if (act === 'back' && w.stack.length > 1) {
                        w.stack.pop();
                        w.back = true;
                        w.frame.contentWindow.location.replace(w.stack.at(-1));
                    }
                });
                bar.addEventListener('dblclick', e => !e.target.closest('button') && toggleMax(w));
                bar.addEventListener('pointerdown', e => {
                    if (e.target.closest('button') || e.button !== 0) return;
                    focus(w);
                    if (isMono() || maxed) return;
                    const x0 = e.clientX, y0 = e.clientY;
                    let target = null, dragging = false;
                    bar.setPointerCapture(e.pointerId);
                    const move = ev => {
                        if (!dragging && Math.hypot(ev.clientX - x0, ev.clientY - y0) < 6) return;
                        if (!dragging) {
                            dragging = true;
                            desk.classList.add('busy');
                            w.el.classList.add('lift');
                        }
                        const el = document.elementFromPoint(ev.clientX, ev.clientY)?.closest('.win');
                        const t = el && el !== w.el ? wins.find(x => x.el === el) : null;
                        if (t !== target) {
                            target?.el.classList.remove('drop');
                            t?.el.classList.add('drop');
                            target = t;
                        }
                    };
                    const up = () => {
                        bar.removeEventListener('pointermove', move);
                        desk.classList.remove('busy');
                        w.el.classList.remove('lift');
                        if (target) {
                            target.el.classList.remove('drop');
                            const a = wins.indexOf(w), b = wins.indexOf(target);
                            [wins[a], wins[b]] = [wins[b], wins[a]];
                            layout();
                        }
                    };
                    bar.addEventListener('pointermove', move);
                    bar.addEventListener('pointerup', up, { once: true });
                });
            }

            // ── док ───────────────────────────────────────────────────────
            function launch(app, fresh) {
                if (!app) return;
                if (app.top) return void(location.href = app.href);
                const mine = wins.filter(w => w.app === app.id).sort((a, b) => b.t - a.t);
                if (fresh || !mine.length) return open(app.href);
                const w = mine[0];
                if (w !== focused || w.min) return focus(w);
                // окно уже активно: на главной раздела — свернуть, на вложенной (редактор) — открыть раздел рядом
                if (w.url !== clean(app.href)) open(app.href);
                else if (!isMono()) minimize(w);
            }

            function dockState() {
                document.querySelectorAll('.dock-item[data-app]').forEach(b => {
                    const id = b.dataset.app;
                    b.classList.toggle('run', wins.some(w => w.app === id));
                    b.classList.toggle('on', focused?.app === id && !focused.min);
                });
                // незакреплённые разделы, открытые через поиск, отмечаем точкой у кнопки «Все разделы»
                $('#launch').classList.toggle('run', wins.some(w => !pins.includes(w.app)));
            }

            // ── закрепление: набор по умолчанию — dock => true в $apps, дальше выбор хранится в браузере ──
            function loadPins() {
                try {
                    const p = JSON.parse(localStorage.getItem(PIN_KEY));
                    if (Array.isArray(p)) return p;
                } catch (e) {}
                return APPS.filter(a => a.dock).map(a => a.id);
            }

            const pinned = () => APPS.filter(a => pins.includes(a.id)); // порядок — как в $apps

            function togglePin(id) {
                pins = pins.includes(id) ? pins.filter(x => x !== id) : [...pins, id];
                try { localStorage.setItem(PIN_KEY, JSON.stringify(pins)); } catch (e) {}
                renderDock();
            }

            const badge = n => n > 0 ? '<span class="dock-badge">' + Math.min(n, 99) + (n > 99 ? '+' : '') + '</span>' : '';

            function renderDock() {
                const end = $('#dock-end');
                document.querySelectorAll('#dock .pin').forEach(n => n.remove());
                let grp = null;
                pinned().forEach(a => {
                    if (grp !== null && grp !== a.group) {
                        const sep = document.createElement('span');
                        sep.className = 'dock-sep pin';
                        end.before(sep);
                    }
                    grp = a.group;
                    const b = document.createElement('button');
                    b.className = 'dock-item pin';
                    b.dataset.app = a.id;
                    b.innerHTML = '<span class="dock-ico material-icons"></span><span class="dock-tip"></span>' + badge(a.badge);
                    b.children[0].textContent = a.icon;
                    b.children[1].textContent = a.label;
                    end.before(b);
                });
                end.hidden = !pins.length;
                // бейджи незакреплённых разделов суммируются на «Все разделы»
                const launchBtn = $('#launch');
                launchBtn.querySelector('.dock-badge')?.remove();
                launchBtn.insertAdjacentHTML('beforeend', badge(APPS.reduce((s, a) => s + (pins.includes(a.id) ? 0 : a.badge), 0)));
                dockState();
            }

            const dockBtn = e => e.target.closest('.dock-item[data-app]');
            $('#dock').addEventListener('click', e => dockBtn(e) && launch(byId[dockBtn(e).dataset.app], e.shiftKey));
            $('#dock').addEventListener('auxclick', e => e.button === 1 && dockBtn(e) && launch(byId[dockBtn(e).dataset.app], true));
            $('#launch').addEventListener('click', () => spot());

            // ── поиск разделов ────────────────────────────────────────────
            const spotEl = $('#spot'), q = $('#spot-q'), list = $('#spot-list');
            let hits = [], sel = 0;

            function spot() {
                spotEl.hidden = false;
                q.value = '';
                renderSpot();
                q.focus();
            }

            function renderSpot() {
                const s = q.value.trim().toLowerCase();
                hits = APPS.filter(a => !s || (a.label + ' ' + a.group).toLowerCase().includes(s));
                sel = Math.min(sel, Math.max(hits.length - 1, 0));
                list.innerHTML = '';
                hits.forEach((a, i) => {
                    const row = document.createElement('div');
                    const on = pins.includes(a.id);
                    row.className = 'spot-row' + (i === sel ? ' sel' : '');
                    row.innerHTML = '<button class="spot-pin' + (on ? ' on' : '') + '" title="' + (on ? 'Открепить от дока' : 'Закрепить в доке') + '">' +
                        '<span class="material-icons">check</span></button>' +
                        '<span class="material-icons"></span><span class="spot-label"></span>' +
                        (a.badge ? '<span class="spot-cnt">' + a.badge + '</span>' : '') + '<span class="spot-grp"></span>';
                    row.children[1].textContent = a.icon;
                    row.children[2].textContent = a.label;
                    row.lastChild.textContent = a.group;
                    const pin = row.firstChild;
                    pin.addEventListener('pointerdown', ev => ev.preventDefault()); // фокус остаётся в поле поиска
                    pin.addEventListener('click', ev => {
                        ev.stopPropagation();
                        sel = i;
                        togglePin(a.id);
                        renderSpot();
                    });
                    row.addEventListener('click', ev => pick(a, ev.shiftKey));
                    list.append(row);
                });
            }

            function pick(a, fresh) {
                spotEl.hidden = true;
                launch(a, fresh);
            }

            q.addEventListener('input', () => { sel = 0; renderSpot(); });
            q.addEventListener('keydown', e => {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
                    renderSpot();
                    list.querySelector('.sel')?.scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'Tab' && hits[sel]) {
                    e.preventDefault();
                    togglePin(hits[sel].id);
                    renderSpot();
                } else if (e.key === 'Enter' && hits[sel]) pick(hits[sel], e.shiftKey);
                else if (e.key === 'Escape') spotEl.hidden = true;
            });
            spotEl.addEventListener('pointerdown', e => e.target === spotEl && (spotEl.hidden = true));

            // ── клавиши (e.code, чтобы работало и в русской раскладке) ──
            function key(e) {
                if ((e.ctrlKey || e.metaKey) && !e.altKey && e.code === 'KeyS') {
                    e.preventDefault();
                    const s = saverOf(focused);
                    if (s) s.api.save();
                    return;
                }
                if ((e.ctrlKey || e.metaKey) && e.code === 'KeyK') {
                    e.preventDefault();
                    return spotEl.hidden ? spot() : (spotEl.hidden = true);
                }
                if (!e.altKey || e.ctrlKey || e.metaKey) return;
                const c = e.code;
                if (/^Digit[1-9]$/.test(c)) launch(pinned()[+c.slice(5) - 1]);
                else if (c === 'KeyJ') cycle(1);
                else if (c === 'KeyK') cycle(-1);
                else if (c === 'Enter') zoom(focused);
                else if (c === 'KeyF') toggleMax(focused);
                else if (c === 'KeyM') minimize(focused);
                else if (c === 'KeyW') close(focused);
                else return;
                e.preventDefault();
            }
            document.addEventListener('keydown', key);

            // ── состояние ─────────────────────────────────────────────────
            function syncUrl() {
                const url = focused ? focused.url : '/devs/';
                if (clean(location.href) !== url) history.replaceState(null, '', url);
                document.title = (focused?.title || 'Консоль') + ' — Dustore.Devs';
            }

            function save() {
                try {
                    localStorage.setItem(KEY, JSON.stringify({
                        ratio,
                        f: wins.indexOf(focused),
                        wins: wins.map(w => ({ url: w.url, app: w.app, min: w.min })),
                    }));
                } catch (e) {}
            }

            function load() {
                try { return JSON.parse(localStorage.getItem(KEY)) || null; } catch (e) { return null; }
            }

            // ── сохранение: индикатор слева от дока, клик или Ctrl+S — сохранить ──
            const saverEl = $('#saver');
            const SAVER = {
                saved: ['cloud_done', 'Всё сохранено'],
                dirty: ['cloud_upload', 'Есть изменения — сохраню через секунду'],
                manual: ['save', 'Есть изменения — нажми, чтобы сохранить'],
                saving: ['sync', 'Сохраняю…'],
                error: ['cloud_off', 'Не сохранено — нажми, чтобы повторить'],
            };
            // регистрация живёт, пока в окне тот же документ: после перехода страница регистрируется заново
            const saverOf = w => {
                try { return w && w.saver && w.frame.contentDocument === w.saver.doc ? w.saver : null; } catch (e) { return null; }
            };

            function paintSaver() {
                wins.forEach(w => {
                    const s = saverOf(w);
                    w.el.classList.toggle('dirty', !!s && s.state !== 'saved' && s.state !== 'saving');
                });
                const s = saverOf(focused);
                saverEl.hidden = !s;
                if (!s) return;
                const [ico, txt] = SAVER[s.state] || SAVER.saved;
                saverEl.dataset.state = s.state;
                saverEl.querySelector('.material-icons').textContent = ico;
                saverEl.querySelector('.dock-tip').textContent = (s.msg || txt) + ' · Ctrl+S';
            }
            saverEl.addEventListener('click', () => {
                const s = saverOf(focused);
                if (s) s.api.save();
            });

            // ── полноэкранный режим ──
            const fsBtn = $('#fs');
            fsBtn.hidden = !document.fullscreenEnabled;
            fsBtn.addEventListener('click', () => {
                if (document.fullscreenElement) document.exitFullscreen();
                else document.documentElement.requestFullscreen().catch(() => {});
            });
            document.addEventListener('fullscreenchange', () => {
                const on = !!document.fullscreenElement;
                fsBtn.querySelector('.material-icons').textContent = on ? 'fullscreen_exit' : 'fullscreen';
                fsBtn.querySelector('.dock-tip').textContent = on ? 'Выйти из полноэкранного режима' : 'Во весь экран';
            });

            // API для окон (footer.php)
            window.dsWM = {
                key,
                saver: (win, api) => {
                    const w = wins.find(x => x.frame.contentWindow === win);
                    if (!w) return;
                    w.saver = { doc: win.document, api, state: 'saved', msg: '' };
                    paintSaver();
                },
                saverState: (win, state, msg) => {
                    const w = wins.find(x => x.frame.contentWindow === win);
                    if (!w || !w.saver || w.saver.doc !== win.document) return;
                    w.saver.state = state;
                    w.saver.msg = msg || '';
                    paintSaver();
                },
                focusFrame: win => {
                    const w = wins.find(x => x.frame.contentWindow === win);
                    if (w && w !== focused) focus(w);
                },
                loaded: (win, meta) => {
                    const w = wins.find(x => x.frame.contentWindow === win);
                    if (!w) return;
                    if (byId[meta.app]) w.app = meta.app;
                    if (meta.title) w.title = meta.title;
                    paint(w);
                    dockState();
                },
            };

            // ── старт: собрать док, восстановить окна, открыть текущий адрес ──
            renderDock();
            const start = clean(location.href);
            const isRoot = /^\/devs\/?(index(\.php)?)?$/.test(location.pathname);
            const saved = load();
            if (saved?.wins?.length) {
                ratio = saved.ratio || ratio;
                saved.wins.forEach(s => open(s.url, { app: s.app, min: s.min, focus: false }));
            }
            if (isRoot && wins.length) focus(wins[saved.f] || lastVisible() || wins[0]);
            else focus(wins.find(w => w.url === start) || open(start, { focus: false }));

            addEventListener('resize', layout);
        })();
    </script>
</body>

</html>
