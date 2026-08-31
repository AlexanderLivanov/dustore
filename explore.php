<?php session_start(); ?>
<?php
require_once('swad/config.php');
require_once('swad/controllers/game.php');

$gameController = new Game();
$games = $gameController->getLatestGames();

// Серверная фильтрация нужна для первой отрисовки и для поисковиков.
$games = array_filter($games, function ($game) {
    return isset($game['status']) && strtolower($game['status']) === 'published'
        && empty($game['hidden']);
});

$adultSection = isset($_GET['adult']) && $_GET['adult'] == 1;

if ($adultSection) {
    $games = array_filter($games, function ($game) {
        return isset($game['age_rating']) && intval($game['age_rating']) >= 18;
    });
} else {
    $games = array_filter($games, function ($game) {
        return !isset($game['age_rating']) || intval($game['age_rating']) < 18;
    });
}

// Сбор жанров для начального состояния
$allGenres = [];
foreach ($games as $game) {
    if (!empty($game['genre'])) {
        $genres = array_map('trim', explode(',', $game['genre']));
        foreach ($genres as $g) {
            if ($g !== '' && !in_array($g, $allGenres)) {
                $allGenres[] = $g;
            }
        }
    }
}
sort($allGenres);

$selectedGenre = isset($_GET['genre']) ? trim(urldecode($_GET['genre'])) : null;
if ($selectedGenre) {
    $games = array_filter($games, function ($game) use ($selectedGenre) {
        if (empty($game['genre'])) return false;
        $genres = array_map('trim', explode(',', $game['genre']));
        return in_array(mb_strtolower($selectedGenre), array_map('mb_strtolower', $genres));
    });
}

/* Заглушка обложки. Раньше здесь был via.placeholder.com — сервис закрыт
   в 2024-м, поэтому у каждой игры без обложки висела битая картинка. */
$COVER_FALLBACK = 'data:image/svg+xml;utf8,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 180">'
    . '<rect width="320" height="180" fill="#1b0a26"/>'
    . '<path d="M136 74h48v40h-48z" fill="none" stroke="#6b5478" stroke-width="3"/>'
    . '<circle cx="150" cy="88" r="6" fill="#6b5478"/>'
    . '<path d="M136 108l18-16 14 12 12-8 4 4v14h-48z" fill="#6b5478"/>'
    . '</svg>'
);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — Каталог игр</title>
    <link rel="stylesheet" href="swad/css/explore.css">
    <?php require_once('swad/controllers/ymcounter.php'); ?>
    <script>
        window.yaContextCb = window.yaContextCb || []
    </script>
    <script src="https://yandex.ru/ads/system/context.js" async></script>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="games-list">
            <div class="container">

                <div id="adult-warning" class="warning-adult<?= $adultSection ? ' visible' : '' ?>">
                    Внимание! Данный раздел содержит игры, предназначенные только для пользователей старше 18 лет
                    в соответствии с законодательством РФ.
                </div>

                <div class="search-wrapper">
                    <div class="sort-buttons" id="sortButtons">
                        <button type="button" class="sort-btn" data-sort="popularity" data-dir="desc">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            Популярные
                        </button>
                        <button type="button" class="sort-btn" data-sort="price" data-dir="asc">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            Цена
                        </button>
                        <button type="button" class="sort-btn" data-sort="date" data-dir="desc">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Новые
                        </button>
                        <button type="button" class="sort-btn" data-sort="updated" data-dir="desc">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                            Обновлённые
                        </button>
                    </div>

                    <div class="price-switch" id="priceSwitch">
                        <button type="button" class="price-btn active" data-price-type="all">Все</button>
                        <button type="button" class="price-btn" data-price-type="free">Бесплатные</button>
                        <button type="button" class="price-btn" data-price-type="paid">Платные</button>
                    </div>

                    <div class="search-bar" id="searchBar">
                        <span class="search-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg></span>
                        <input type="text" id="searchInput" placeholder="Название игры или жанр..." autocomplete="off">
                        <button type="button" class="search-clear" id="searchClear" aria-label="Очистить поиск">&times;</button>
                    </div>
                </div>

                <div class="games-body">
                    <div class="games-controls">
                        <button type="button" class="mobile-filter-toggle" id="filterToggle" aria-expanded="false">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
                            </svg>
                            <span class="filter-label"><?= $selectedGenre ? htmlspecialchars($selectedGenre) : 'Все игры' ?></span>
                            <span class="active-badge" style="<?= $selectedGenre ? '' : 'display:none' ?>">активен</span>
                            <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>

                        <div class="controls-left" id="filterPanel">
                            <a href="#" class="btn-filter <?= !$selectedGenre && !$adultSection ? 'active' : '' ?>" data-genre="">Все игры</a>
                            <a href="#" class="btn-filter <?= $adultSection ? 'active' : '' ?>" data-adult-toggle>18+</a>
                            <?php foreach ($allGenres as $genre): ?>
                                <a href="#" class="btn-filter <?= ($selectedGenre === $genre) ? 'active' : '' ?>" data-genre="<?= htmlspecialchars($genre) ?>">
                                    <?= htmlspecialchars($genre) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="price-filters" id="priceFilters">
                            <div class="price-slider-container" id="priceSliderContainer">
                                <input type="range" id="priceSlider" min="100" max="5000" value="5000" step="100">
                                <span id="priceSliderValue">до 5000 ₽</span>
                            </div>
                        </div>
                    </div>

                    <div class="content-row">
                        <div class="results-bar">
                            <span id="resultsCount"><?= count($games) ?> <?= ((count($games) % 10 === 1 && count($games) % 100 !== 11) ? 'игра' : 'игр') ?></span>
                            <button type="button" class="results-reset" id="resetFilters" style="display:none">Сбросить фильтры</button>
                        </div>

                        <div class="games-grid" id="gamesGrid">
                            <?php if (empty($games)): ?>
                                <div class="no-games-message"><strong>Пока пусто</strong>Игры ещё не добавлены в каталог</div>
                            <?php else: ?>
                                <?php foreach ($games as $game):
                                    $price   = ($game['price'] == 0) ? 'Бесплатно' : number_format($game['price'], 0, ',', ' ') . ' ₽';
                                    $rel     = strtotime($game['release_date'] ?? '');
                                    $isSoon  = $rel && $rel > time();
                                    $isNew   = $rel && !$isSoon && (time() - $rel) < 30 * 24 * 60 * 60;
                                    $genre   = trim(explode(',', (string)($game['genre'] ?? ''))[0]);
                                    $isBlur  = $adultSection && (int)($game['age_rating'] ?? 0) >= 18;
                                ?>
                                <a class="game-card"
                                   href="/g/<?= (int)$game['id'] ?>"
                                   data-price="<?= (float)$game['price'] ?>"
                                   data-popularity="<?= (int)($game['downloads'] ?? 0) ?>"
                                   data-date="<?= $rel ?: 0 ?>"
                                   data-genre="<?= htmlspecialchars(mb_strtolower((string)($game['genre'] ?? ''))) ?>"
                                   data-id="<?= (int)$game['id'] ?>">
                                    <div class="game-image<?= $isBlur ? ' blur-adult' : '' ?>">
                                        <img src="<?= htmlspecialchars(!empty($game['path_to_cover']) ? $game['path_to_cover'] : $COVER_FALLBACK) ?>"
                                             alt="<?= htmlspecialchars($game['name']) ?>" loading="lazy" decoding="async">
                                        <?php if ($isSoon): ?>
                                            <span class="game-badge soon">Скоро</span>
                                        <?php elseif ($isNew): ?>
                                            <span class="game-badge">Новинка</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="game-info">
                                        <h3 class="game-title"><?= htmlspecialchars($game['name']) ?></h3>
                                        <div class="game-meta">
                                            <span class="game-genre"><?= htmlspecialchars($genre) ?></span>
                                            <span class="game-price <?= ($game['price'] == 0) ? 'free' : '' ?>"><?= $price ?></span>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <div id="adultModal" class="adult-modal">
        <div class="adult-modal-content">
            <h2>Подтверждение возраста</h2>
            <p>Данный раздел содержит материалы только для пользователей старше 18 лет. Также игры в этом разделе
               могут содержать контент, который запрещён законодательством РФ. Платформа ни в коем случае такое
               не пропагандирует.</p>
            <button type="button" id="adultConfirmBtn">Мне есть 18 лет</button>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        const COVER_FALLBACK = <?= json_encode($COVER_FALLBACK, JSON_UNESCAPED_SLASHES) ?>;
        const API_URL = '/swad/controllers/explore_games.php';

        const GENRE_MAP = {
            'strategy': 'Стратегия', 'rpg': 'РПГ', 'action': 'Экшен',
            'adventure': 'Приключения', 'puzzle': 'Головоломка', 'racing': 'Гонки',
            'simulation': 'Симулятор', 'horror': 'Хоррор', 'indie': 'Инди',
            'platformer': 'Платформер', 'visual novel': 'Визуальная новелла'
        };

        const grid         = document.getElementById('gamesGrid');
        const filterPanel  = document.getElementById('filterPanel');
        const filterToggle = document.getElementById('filterToggle');
        const adultModal   = document.getElementById('adultModal');
        const adultWarning = document.getElementById('adult-warning');
        const searchBar    = document.getElementById('searchBar');
        const searchInput  = document.getElementById('searchInput');
        const searchClear  = document.getElementById('searchClear');
        const resultsCount = document.getElementById('resultsCount');
        const resetBtn     = document.getElementById('resetFilters');

        const DEFAULTS = { adult: 0, genre: null, sort: 'popularity', dir: 'desc', priceType: 'all', priceMax: 5000 };
        const state = Object.assign({}, DEFAULTS, {
            genre: <?= $selectedGenre ? json_encode($selectedGenre) : 'null' ?>,
            adult: <?= $adultSection ? 1 : 0 ?>
        });

        try {
            const s = JSON.parse(localStorage.getItem('explore_filter') || '{}');
            if (s.priceType) state.priceType = s.priceType;
            if (s.priceMax)  state.priceMax  = s.priceMax;
        } catch (e) {}
        try {
            const s = JSON.parse(localStorage.getItem('explore_sort') || '{}');
            if (s.sort) state.sort = s.sort;
            if (s.dir)  state.dir  = s.dir;
        } catch (e) {}

        /* Экранирование. Раньше название игры подставлялось в innerHTML как есть —
           разработчик мог назвать игру тегом, и он выполнялся у всех на витрине. */
        const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        const plural = n => {
            const d = n % 10, h = n % 100;
            if (d === 1 && h !== 11) return 'игра';
            if (d >= 2 && d <= 4 && (h < 12 || h > 14)) return 'игры';
            return 'игр';
        };

        /* ── Загрузка ────────────────────────────────────────────────────── */
        async function fetchGames() {
            const p = new URLSearchParams();
            p.set('adult', state.adult);
            if (state.genre) p.set('genre', state.genre);
            p.set('sort', state.sort);
            p.set('dir', state.dir);
            p.set('price_type', state.priceType);
            if (state.priceType === 'paid') p.set('price_max', state.priceMax);

            const res = await fetch(API_URL + '?' + p.toString());
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }

        function cardHTML(game) {
            const rel    = new Date(game.release_date).getTime();
            const now    = Date.now();
            const isSoon = rel > now;
            const isNew  = !isSoon && (now - rel) < 30 * 24 * 60 * 60 * 1000;
            const price  = game.price == 0
                ? 'Бесплатно'
                : Math.round(game.price).toLocaleString('ru-RU') + ' ₽';
            const genre  = String(game.genre || '').split(',')[0].trim();
            const blur   = state.adult && game.age_rating >= 18 ? ' blur-adult' : '';
            const badge  = isSoon ? '<span class="game-badge soon">Скоро</span>'
                         : isNew  ? '<span class="game-badge">Новинка</span>' : '';

            return '<a class="game-card" href="/g/' + encodeURIComponent(game.id) + '"'
                 + ' data-price="' + esc(game.price) + '"'
                 + ' data-popularity="' + esc(game.downloads) + '"'
                 + ' data-date="' + (isFinite(rel) ? Math.floor(rel / 1000) : 0) + '"'
                 + ' data-genre="' + esc(String(game.genre || '').toLowerCase()) + '"'
                 + ' data-id="' + esc(game.id) + '">'
                 +   '<div class="game-image' + blur + '">'
                 +     '<img src="' + esc(game.path_to_cover || COVER_FALLBACK) + '"'
                 +          ' alt="' + esc(game.name) + '" loading="lazy" decoding="async">'
                 +     badge
                 +   '</div>'
                 +   '<div class="game-info">'
                 +     '<h3 class="game-title">' + esc(game.name) + '</h3>'
                 +     '<div class="game-meta">'
                 +       '<span class="game-genre">' + esc(genre) + '</span>'
                 +       '<span class="game-price' + (game.price == 0 ? ' free' : '') + '">' + price + '</span>'
                 +     '</div>'
                 +   '</div>'
                 + '</a>';
        }

        function renderGames(games) {
            if (!games.length) {
                grid.innerHTML = '<div class="no-games-message">'
                    + '<strong>Ничего не нашлось</strong>Попробуйте снять часть фильтров</div>';
                return;
            }
            grid.innerHTML = games.map(cardHTML).join('');
        }

        function renderFilters(genres) {
            const seen = [];
            (genres || []).forEach(g => {
                const ru = GENRE_MAP[g.trim().toLowerCase()] || g.trim();
                if (ru && seen.indexOf(ru) === -1) seen.push(ru);
            });
            seen.sort((a, b) => a.localeCompare(b, 'ru'));

            let html = '<a href="#" class="btn-filter ' + (!state.genre && !state.adult ? 'active' : '') + '" data-genre="">Все игры</a>'
                     + '<a href="#" class="btn-filter ' + (state.adult ? 'active' : '') + '" data-adult-toggle>18+</a>';
            seen.forEach(g => {
                html += '<a href="#" class="btn-filter ' + (state.genre === g ? 'active' : '') + '" data-genre="' + esc(g) + '">' + esc(g) + '</a>';
            });
            filterPanel.innerHTML = html;

            const label = filterToggle.querySelector('.filter-label');
            const badge = filterToggle.querySelector('.active-badge');
            if (label) label.textContent = state.genre || 'Все игры';
            if (badge) badge.style.display = state.genre ? '' : 'none';
        }

        function updateResultsBar() {
            const visible = Array.from(grid.querySelectorAll('.game-card'))
                .filter(c => c.style.display !== 'none').length;
            resultsCount.textContent = visible + ' ' + plural(visible);
            const dirty = state.genre || state.adult
                || state.priceType !== DEFAULTS.priceType
                || (searchInput.value.trim() !== '');
            resetBtn.style.display = dirty ? '' : 'none';
        }

        function skeletons(n) {
            let html = '';
            for (let i = 0; i < n; i++) {
                html += '<div class="skeleton-card"><div class="sk-cover"></div>'
                     +  '<div class="sk-line"></div><div class="sk-line short"></div></div>';
            }
            return html;
        }

        let firstLoad = true;
        async function updateUI() {
            grid.setAttribute('aria-busy', 'true');
            // На первой загрузке в гриде уже лежит серверная разметка — не мигаем.
            if (!firstLoad) grid.innerHTML = skeletons(8);

            try {
                const data = await fetchGames();
                renderGames(data.games);
                renderFilters(data.genres);

                adultWarning.classList.toggle('visible', !!state.adult);

                localStorage.setItem('explore_sort', JSON.stringify({ sort: state.sort, dir: state.dir }));
                localStorage.setItem('explore_filter', JSON.stringify({ priceType: state.priceType, priceMax: state.priceMax }));

                if (searchInput.value.trim()) applySearch();
                updateResultsBar();
            } catch (err) {
                console.error(err);
                grid.innerHTML = '<div class="no-games-message">'
                    + '<strong>Не удалось загрузить каталог</strong>Проверьте соединение и обновите страницу</div>';
            } finally {
                grid.removeAttribute('aria-busy');
                firstLoad = false;
            }
        }

        function updateURL() {
            const url = new URL(window.location);
            state.genre ? url.searchParams.set('genre', state.genre) : url.searchParams.delete('genre');
            state.adult ? url.searchParams.set('adult', '1')         : url.searchParams.delete('adult');
            window.history.pushState({}, '', url);
        }

        /* ── Фильтры по жанру и 18+ ──────────────────────────────────────── */
        filterPanel.addEventListener('click', async e => {
            const target = e.target.closest('.btn-filter');
            if (!target) return;
            e.preventDefault();

            if (target.hasAttribute('data-adult-toggle')) {
                if (!state.adult && !sessionStorage.getItem('adultConfirmed')) {
                    adultModal.classList.add('visible');
                    return;
                }
                state.adult = state.adult ? 0 : 1;
                state.genre = null;
            } else {
                state.genre = target.dataset.genre || null;
            }

            updateURL();
            await updateUI();
            filterPanel.classList.remove('open');
            filterToggle.classList.remove('open');
            filterToggle.setAttribute('aria-expanded', 'false');
        });

        document.getElementById('adultConfirmBtn').addEventListener('click', async () => {
            sessionStorage.setItem('adultConfirmed', 'true');
            adultModal.classList.remove('visible');
            state.adult = 1;
            state.genre = null;
            updateURL();
            await updateUI();
            document.querySelectorAll('.blur-adult').forEach(el => el.classList.remove('blur-adult'));
        });

        adultModal.addEventListener('click', e => {
            if (e.target === adultModal) adultModal.classList.remove('visible');
        });

        /* ── Сортировка ──────────────────────────────────────────────────── */
        const sortButtons = document.querySelectorAll('#sortButtons .sort-btn');
        sortButtons.forEach(btn => {
            btn.addEventListener('click', async function () {
                const sort = this.dataset.sort;
                if (state.sort === sort) {
                    state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sort = sort;
                    state.dir = sort === 'price' ? 'asc' : 'desc';
                }
                updateSortButtonsUI();
                await updateUI();
            });
        });

        function updateSortButtonsUI() {
            sortButtons.forEach(btn => {
                const isActive = btn.dataset.sort === state.sort;
                btn.classList.toggle('active', isActive);
                let arrow = btn.querySelector('.sort-arrow');
                if (!arrow) {
                    arrow = document.createElement('span');
                    arrow.className = 'sort-arrow';
                    btn.appendChild(arrow);
                }
                arrow.textContent = state.dir === 'asc' ? '↑' : '↓';
                arrow.style.display = isActive ? '' : 'none';
            });
        }

        /* ── Поиск ───────────────────────────────────────────────────────── */
        function applySearch() {
            const term = searchInput.value.toLowerCase().trim();
            grid.querySelectorAll('.game-card').forEach(card => {
                // Ищем по названию И по жанру — плейсхолдер обещает и то и другое,
                // а сравнивалось только название, поэтому запрос вроде «хоррор»
                // не находил ничего.
                const title = (card.querySelector('.game-title')?.textContent || '').toLowerCase();
                const genre = (card.dataset.genre || '').toLowerCase();
                const hit = title.includes(term) || genre.includes(term);
                card.style.display = (!term || hit) ? '' : 'none';
            });
            searchBar.classList.toggle('has-value', term !== '');
            updateResultsBar();
        }

        let searchTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applySearch, 120);
        });
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'Escape') { searchInput.value = ''; applySearch(); }
        });
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            applySearch();
            searchInput.focus();
        });

        resetBtn.addEventListener('click', async () => {
            state.genre = null;
            state.adult = 0;
            state.priceType = DEFAULTS.priceType;
            state.priceMax = DEFAULTS.priceMax;
            searchInput.value = '';
            searchBar.classList.remove('has-value');
            updatePriceUI();
            updateURL();
            await updateUI();
        });

        window.addEventListener('popstate', async () => {
            const p = new URLSearchParams(window.location.search);
            state.genre = p.get('genre') || null;
            state.adult = p.get('adult') === '1' ? 1 : 0;
            await updateUI();
            updateSortButtonsUI();
        });

        /* ── Мобильная панель фильтров ───────────────────────────────────── */
        filterToggle.addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = filterPanel.classList.toggle('open');
            filterToggle.classList.toggle('open', isOpen);
            filterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', e => {
            if (!e.target.closest('.games-controls')) {
                filterPanel.classList.remove('open');
                filterToggle.classList.remove('open');
                filterToggle.setAttribute('aria-expanded', 'false');
            }
        });

        /* ── Цена ────────────────────────────────────────────────────────── */
        const priceButtons  = document.querySelectorAll('#priceSwitch .price-btn');
        const sliderWrap    = document.getElementById('priceSliderContainer');
        const priceSlider   = document.getElementById('priceSlider');
        const priceValue    = document.getElementById('priceSliderValue');

        function updatePriceUI() {
            priceButtons.forEach(b => b.classList.toggle('active', b.dataset.priceType === state.priceType));
            sliderWrap.classList.toggle('visible', state.priceType === 'paid');
            priceSlider.value = state.priceMax;
            priceValue.textContent = 'до ' + state.priceMax + ' ₽';
        }

        priceButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                state.priceType = btn.dataset.priceType;
                if (state.priceType !== 'paid') state.priceMax = DEFAULTS.priceMax;
                updatePriceUI();
                await updateUI();
            });
        });

        let sliderTimer;
        priceSlider.addEventListener('input', function () {
            state.priceMax = parseInt(this.value, 10);
            priceValue.textContent = 'до ' + state.priceMax + ' ₽';
            clearTimeout(sliderTimer);
            sliderTimer = setTimeout(updateUI, 400);
        });

        /* ── Наклон за курсором ──────────────────────────────────────────── */
        /* Был отдельный обработчик на грид, ещё один на весь document для кнопок
           и третий на поиск — три mousemove одновременно. Теперь один делегат. */
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            // Наклон поиска
            const sbEl = document.querySelector('.search-bar');
            if (sbEl) {
                sbEl.addEventListener('mousemove', function(e) {
                    const rect = sbEl.getBoundingClientRect();
                    // Вертикальная составляющая намеренно резче горизонтальной —
                    // так строка «клюёт» при подходе сверху и снизу, как было раньше.
                    const nx = ((e.clientX - rect.left) / rect.width)  * 2  - 1;
                    const ny = ((e.clientY - rect.top)  / rect.height) * 10 - 5;
                    sbEl.style.transform = 'perspective(400px) rotateX(' + (-5 * ny).toFixed(2)
                        + 'deg) rotateY(' + (5 * nx).toFixed(2) + 'deg) translateY(-2px)';
                });
                sbEl.addEventListener('mouseleave', () => { sbEl.style.transform = ''; });
            }

            // Тряска при вводе
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    searchInput.classList.remove('shake-it');
                    void searchInput.offsetWidth;
                    searchInput.classList.add('shake-it');
                    searchInput.addEventListener('animationend', function onEnd() {
                        searchInput.classList.remove('shake-it');
                        searchInput.removeEventListener('animationend', onEnd);
                    });
                });
            }

            const GROUPS = [
                { sel: '.game-card',                a: 12, lift: -8, s: 1.03,  p: 800 },
                { sel: '.sort-btn, .price-btn',     a: 15, lift: -3, s: 1.06,  p: 400 },
                { sel: '.btn-filter',               a: 10, lift: -2, s: 1.03,  p: 400 }
            ];
            const SEL = GROUPS.map(g => g.sel).join(', ');
            let active = null;

            document.addEventListener('mousemove', e => {
                const el = e.target.closest(SEL);
                if (el !== active && active) {
                    active.classList.remove('is-tilting');
                    active.style.transform = '';
                    active = null;
                }
                if (!el) return;
                if (active !== el) { active = el; el.classList.add('is-tilting'); }

                const cfg = GROUPS.find(g => el.matches(g.sel));
                const hw = el.offsetWidth / 2, hh = el.offsetHeight / 2;
                if (!cfg || !hw || !hh) return;

                const r = el.getBoundingClientRect();
                const lift = el.style.transform ? cfg.lift : 0;
                const nx = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / hw));
                const ny = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2 - lift)) / hh));

                // --dx двигает блик за курсором (используется в .game-card::after)
                if (cfg.sel === '.game-card') el.style.setProperty('--dx', (nx * 50) + '%');

                el.style.transform = 'perspective(' + cfg.p + 'px) '
                    + 'rotateX(' + (-cfg.a * ny).toFixed(2) + 'deg) '
                    + 'rotateY(' + (cfg.a * nx).toFixed(2) + 'deg) '
                    + 'translateY(' + cfg.lift + 'px) scale(' + cfg.s + ')';
            });
        }

        /* ── Старт ───────────────────────────────────────────────────────── */
        updateSortButtonsUI();
        updatePriceUI();
        updateResultsBar();
        updateUI();
    })();
    </script>
</body>

</html>