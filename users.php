<?php
declare(strict_types=1);
session_start();
require_once('swad/config.php');

$db  = new Database();
$pdo = $db->connect();

const USERS_PAGE = 40;

$q      = trim((string)($_GET['q'] ?? ''));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$isAjax = !empty($_GET['ajax']);

/* Поле email из выборки убрано. Оно не печаталось в разметке, но тянуть
   персональные данные «на всякий случай» не стоит: достаточно кому-нибудь
   поставить сюда print_r($users) для отладки — и почты всей базы уедут
   в HTML. Берём ровно то, что рисуем. */
$cols = "id, first_name, last_name, telegram_username, username,
         profile_picture, added, country, city, website";

$where  = ["username IS NOT NULL", "username <> ''"];
$params = [];

/* Поиск ушёл на сервер. Раньше он фильтровал уже отрисованные карточки
   через card.style.display, а SQL брал ORDER BY id DESC LIMIT 100 — то есть
   искать можно было только среди ста самых новых участников. Для всех
   остальных страница отвечала «никого не нашлось», хотя человек в базе есть. */
if ($q !== '' && mb_strlen($q) >= 2) {
    // % и _ — метасимволы LIKE; без экранирования запрос «%» выдал бы всех
    $like = '%' . addcslashes(ltrim($q, '@'), '%_\\') . '%';
    $where[] = "(username LIKE ? OR first_name LIKE ? OR last_name LIKE ?
                 OR city LIKE ? OR country LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$lim = USERS_PAGE;
$st  = $pdo->prepare("SELECT $cols FROM users $whereSql ORDER BY id DESC LIMIT $lim OFFSET $offset");
$st->execute($params);
$users = $st->fetchAll(PDO::FETCH_ASSOC);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function user_card(array $u): string
{
    $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    $display  = $fullName !== '' ? $fullName : ($u['username'] ?: ($u['telegram_username'] ?: 'Пользователь'));

    $loc = array_filter([$u['city'] ?? '', $u['country'] ?? '']);
    $loc = implode(', ', $loc);

    $avatar = !empty($u['profile_picture']) ? $u['profile_picture'] : '/swad/static/img/logo.svg';
    $site   = !empty($u['website']) ? (parse_url($u['website'], PHP_URL_HOST) ?: $u['website']) : '';
    $since  = !empty($u['added']) ? date('d.m.Y', strtotime($u['added'])) : '';

    /* Карточка — ссылка, а не <div onclick>. Работает средний клик,
       «открыть в новой вкладке», Tab с клавиатуры, и поисковик видит ссылки
       на профили. Заодно уходит XSS: раньше username подставлялся в
       onclick="...'/player/<?= $user['username'] ?>'" вообще без экранирования,
       и ник с кавычкой ломал атрибут. */
    $ho = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    return '<a class="user-card" href="/player/' . rawurlencode((string)$u['username']) . '"'
        . ' data-search="' . $ho(mb_strtolower($display . ' ' . ($u['username'] ?? '') . ' ' . $loc)) . '">'
        . '<div class="user-avatar"><img src="' . $ho($avatar) . '" alt="" loading="lazy" decoding="async"></div>'
        . '<div class="user-name">' . $ho($display) . '</div>'
        . (!empty($u['username']) ? '<div class="user-username">@' . $ho($u['username']) . '</div>' : '')
        . ($loc !== '' ? '<div class="user-location">📍 ' . $ho($loc) . '</div>' : '')
        . '<div class="user-footer"><span>' . $ho($site) . '</span>'
        . '<span class="user-date">' . ($since !== '' ? 'С ' . $ho($since) : '') . '</span></div>'
        . '</a>';
}

/* Ajax-ветка: тот же файл отдаёт готовый кусок разметки.
   Карточку рисует одна функция, поэтому дублировать её в JS не нужно. */
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok'       => true,
        'html'     => implode('', array_map('user_card', $users)),
        'total'    => $total,
        'shown'    => count($users),
        'has_more' => ($offset + count($users)) < $total,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function plural_users(int $n): string
{
    $d = $n % 10; $h = $n % 100;
    if ($d === 1 && $h !== 11) return 'участник';
    if ($d >= 2 && $d <= 4 && ($h < 12 || $h > 14)) return 'участника';
    return 'участников';
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Участники — Dustore</title>
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <!-- explore.css нужен не только ради вёрстки: в нём объявлены токены
         --ex-* и блок body.moonlight-theme, так что страница получает обе
         темы бесплатно, без собственной палитры. -->
    <link rel="stylesheet" href="/swad/css/explore.css">
    <style>
        .users-wrap { max-width: 1560px; margin: 0 auto; padding: 16px 20px 48px; }

        .users-head { margin-bottom: 16px; }
        .users-head h1 { font-size: 1.5rem; font-weight: 700; color: var(--ex-text); }
        .users-head p { font-size: .88rem; color: var(--ex-muted); margin-top: 4px; }

        .users-search { position: relative; max-width: 460px; margin-bottom: 12px; }
        .users-search input {
            width: 100%;
            padding: 10px 34px 10px 40px;
            border-radius: var(--ex-radius-sm);
            background: var(--ex-chip);
            border: 1px solid var(--ex-chip-line);
            color: var(--ex-text);
            font: inherit; font-size: .92rem; outline: none;
            transition: border-color .18s ease, background .18s ease;
        }
        .users-search input:focus { border-color: var(--ex-accent); background: var(--ex-accent-soft); }
        .users-search input::placeholder { color: var(--ex-dim); }
        .users-search .si { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--ex-muted); display: flex; pointer-events: none; }
        .users-search .clr {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            display: none; width: 20px; height: 20px; align-items: center; justify-content: center;
            background: none; border: 0; color: var(--ex-dim); font-size: 15px; cursor: pointer; border-radius: 4px;
        }
        .users-search.has-value .clr { display: flex; }
        .users-search .clr:hover { color: var(--ex-text); background: var(--ex-chip); }

        .users-count { font-size: .82rem; color: var(--ex-muted); margin-bottom: 12px; min-height: 18px; }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 14px;
        }

        .user-card {
            display: block;
            padding: 16px;
            border-radius: var(--ex-radius);
            background: var(--ex-card);
            box-shadow: inset 0 0 0 1px var(--ex-card-line);
            color: inherit; text-decoration: none;
            transition: box-shadow .18s ease, transform .001s ease-out;
        }
        .user-card:hover { box-shadow: inset 0 0 0 1px var(--ex-card-line-hi), 0 10px 26px rgba(0,0,0,.4); }
        .user-card:focus-visible { outline: 2px solid var(--ex-accent-hi); outline-offset: 2px; }
        .user-card.is-tilting { will-change: transform; }
        .user-card[hidden] { display: none; }

        .user-avatar {
            width: 68px; height: 68px; border-radius: 50%;
            overflow: hidden; margin-bottom: 12px; background: var(--ex-cover);
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }

        .user-name { font-size: 1rem; font-weight: 600; color: var(--ex-text); }
        .user-username { font-size: .82rem; color: var(--ex-accent-hi); margin-top: 2px; }
        .user-location { font-size: .8rem; color: var(--ex-muted); margin-top: 6px; }

        .user-footer {
            display: flex; justify-content: space-between; gap: 8px;
            margin-top: 12px; padding-top: 10px;
            border-top: 1px solid var(--ex-chip-line);
            font-size: .76rem; color: var(--ex-muted);
        }

        .users-more-wrap { display: flex; justify-content: center; padding: 18px 0 4px; }
        .users-more {
            padding: 9px 22px; border-radius: var(--ex-radius-sm);
            background: var(--ex-accent-soft); border: 1px solid var(--ex-accent);
            color: var(--ex-text); font: inherit; font-size: .86rem; cursor: pointer;
            transition: background .16s ease, opacity .16s ease;
        }
        .users-more:hover:not(:disabled) { background: var(--ex-accent); }
        .users-more:disabled { opacity: .5; cursor: default; }
        .users-more[hidden] { display: none; }

        .users-empty { grid-column: 1 / -1; padding: 52px 20px; text-align: center; color: var(--ex-muted); font-size: .92rem; }
        .users-empty strong { display: block; color: var(--ex-text); font-weight: 600; margin-bottom: 5px; }

        @media (max-width: 600px) {
            .users-wrap { padding: 10px 10px 32px; }
            .users-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
            .user-avatar { width: 54px; height: 54px; }
        }
        @media (prefers-reduced-motion: reduce) { .user-card { transition: none; } }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main class="users-wrap">
        <div class="users-head">
            <h1>Участники</h1>
            <p>Найдите других участников сообщества</p>
        </div>

        <div class="users-search" id="searchWrap">
            <span class="si"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
            <input type="text" id="userSearch" value="<?= h($q) ?>" placeholder="Имя, ник или город…" autocomplete="off">
            <button type="button" class="clr" id="searchClear" aria-label="Очистить">&times;</button>
        </div>

        <div class="users-count" id="usersCount"><?= $total ?> <?= plural_users($total) ?></div>

        <div class="users-grid" id="usersGrid">
            <?php if (empty($users)): ?>
                <div class="users-empty"><strong>Никого не нашлось</strong>Попробуйте другой запрос</div>
            <?php else: ?>
                <?= implode('', array_map('user_card', $users)) ?>
            <?php endif; ?>
        </div>

        <div class="users-more-wrap">
            <button class="users-more" id="usersMore"<?= ($total > count($users)) ? '' : ' hidden' ?>>Показать ещё</button>
        </div>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
    (function () {
        'use strict';
        const grid   = document.getElementById('usersGrid');
        const input  = document.getElementById('userSearch');
        const wrap   = document.getElementById('searchWrap');
        const clear  = document.getElementById('searchClear');
        const more   = document.getElementById('usersMore');
        const countEl = document.getElementById('usersCount');

        const state = { q: input.value.trim(), offset: grid.querySelectorAll('.user-card').length, busy: false };

        const plural = n => {
            const d = n % 10, h = n % 100;
            if (d === 1 && h !== 11) return 'участник';
            if (d >= 2 && d <= 4 && (h < 12 || h > 14)) return 'участника';
            return 'участников';
        };

        async function load(append) {
            if (state.busy) return;
            state.busy = true;
            if (more) more.disabled = true;

            const p = new URLSearchParams({ ajax: '1', q: state.q, offset: append ? state.offset : 0 });
            try {
                const r = await fetch('/users.php?' + p).then(r => r.json());
                if (!r.ok) throw new Error('bad');

                if (append) grid.insertAdjacentHTML('beforeend', r.html);
                else        grid.innerHTML = r.html || '<div class="users-empty"><strong>Никого не нашлось</strong>Попробуйте другой запрос</div>';

                state.offset = (append ? state.offset : 0) + r.shown;
                countEl.textContent = r.total + ' ' + plural(r.total);
                if (more) more.hidden = !r.has_more;

                // держим ?q= в адресе, чтобы ссылкой на поиск можно было поделиться
                const url = new URL(location);
                state.q ? url.searchParams.set('q', state.q) : url.searchParams.delete('q');
                history.replaceState({}, '', url);
            } catch (e) {
                if (!append) grid.innerHTML = '<div class="users-empty"><strong>Не удалось загрузить</strong>Обновите страницу</div>';
            } finally {
                state.busy = false;
                if (more) more.disabled = false;
            }
        }

        let t;
        input.addEventListener('input', () => {
            wrap.classList.toggle('has-value', input.value !== '');
            clearTimeout(t);
            // дебаунс: иначе каждая буква — запрос в БД
            t = setTimeout(() => { state.q = input.value.trim(); load(false); }, 250);
        });
        input.addEventListener('keydown', e => { if (e.key === 'Escape') clear.click(); });
        clear.addEventListener('click', () => {
            input.value = ''; wrap.classList.remove('has-value');
            state.q = ''; load(false); input.focus();
        });
        more?.addEventListener('click', () => load(true));
        wrap.classList.toggle('has-value', input.value !== '');

        /* Наклон карточек — тот же приём, что на витрине. Каскадную анимацию
           появления убрал: при 100 карточках index * 80ms растягивало её
           на восемь секунд. */
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            let active = null;
            document.addEventListener('mousemove', e => {
                const el = e.target.closest('.user-card');
                if (el !== active && active) { active.classList.remove('is-tilting'); active.style.transform = ''; active = null; }
                if (!el) return;
                if (active !== el) { active = el; el.classList.add('is-tilting'); }
                const hw = el.offsetWidth / 2, hh = el.offsetHeight / 2;
                if (!hw || !hh) return;
                const r = el.getBoundingClientRect();
                const lift = el.style.transform ? -6 : 0;
                const nx = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / hw));
                const ny = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2 - lift)) / hh));
                el.style.transform = 'perspective(800px) rotateX(' + (-8 * ny).toFixed(2) + 'deg) rotateY('
                    + (8 * nx).toFixed(2) + 'deg) translateY(-6px) scale(1.02)';
            });
        }
    })();
    </script>
</body>

</html>