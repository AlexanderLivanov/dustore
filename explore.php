<?php session_start(); ?>
<?php
require_once('swad/config.php');
require_once('swad/controllers/game.php');

$gameController = new Game();

/* Размер порции. 48, а не 49: делится на 2, 3, 4 и 6 — при любом числе
   колонок последний ряд получается полным, без «хвоста» из одной карточки. */
const EXPLORE_PAGE = 48;

$adultSection  = isset($_GET['adult']) && $_GET['adult'] == 1;
$selectedGenre = isset($_GET['genre']) ? trim(urldecode($_GET['genre'])) : null;

/* Первая порция считается в SQL — ровно тем же запросом, которым потом
   ходит подгрузка. Раньше здесь вызывался getLatestGames(99999): вся
   таблица приезжала в PHP и фильтровалась через array_filter.
   С постраничностью это было бы не просто медленно, а бессмысленно —
   тот же объём работы на каждую порцию. */
$page = $gameController->queryGames([
    'genre'  => $selectedGenre,
    'adult'  => $adultSection,
    'sort'   => 'popularity',
    'dir'    => 'desc',
    'limit'  => EXPLORE_PAGE,
    'offset' => 0,
]);

$games      = $page['items'];
$gamesTotal = $page['total'];
$hasMore    = count($games) < $gamesTotal;

/* Список жанров строится по всему разделу, а не по загруженной порции:
   иначе после первой страницы фильтр показывал бы жанры только сорока
   восьми игр. */
$allGenres = $gameController->collectGenres($adultSection);

/* Заглушка обложки. Раньше здесь был via.placeholder.com — сервис закрыт
   в 2024-м, поэтому у каждой игры без обложки висела битая картинка. */
/**
 * Пути скриншотов из games.screenshots (JSON вида [{"path":"..."}]).
 * Нужны для превью при наведении: карточка проигрывает их по очереди.
 */
function shot_paths($raw, int $max = 5): array
{
    $arr = json_decode((string)$raw, true);
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $s) {
        $p = trim((string)(is_array($s) ? ($s['path'] ?? '') : $s));
        if ($p !== '') $out[] = $p;
        if (count($out) >= $max) break;
    }
    return $out;
}

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
                            <span id="resultsCount"><?= $gamesTotal ?> <?= (($gamesTotal % 10 === 1 && $gamesTotal % 100 !== 11) ? 'игра' : 'игр') ?></span>
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
                                    $shots     = shot_paths($game['screenshots'] ?? '');
                                    $avgRating = isset($game['avg_rating']) && $game['avg_rating'] !== null
                                                 ? round((float)$game['avg_rating'], 1) : null;
                                    // рейтинг десятибалльный, звёзд пять — как на странице игры
                                    $stars     = $avgRating !== null ? max(0, min(5, (int)round($avgRating / 2))) : 0;
                                    $descShort = trim((string)($game['short_description'] ?? ''));
                                    if ($descShort === '') $descShort = trim(strip_tags((string)($game['description'] ?? '')));
                                    $descShort = mb_substr($descShort, 0, 220);
                                    $isBlur  = $adultSection && (int)($game['age_rating'] ?? 0) >= 18;
                                ?>
                                <a class="game-card"
                                   href="/g/<?= (int)$game['id'] ?>"
                                   data-price="<?= (float)$game['price'] ?>"
                                   data-popularity="<?= (int)($game['downloads'] ?? 0) ?>"
                                   data-date="<?= $rel ?: 0 ?>"
                                   data-genre="<?= htmlspecialchars(mb_strtolower((string)($game['genre'] ?? ''))) ?>"
                                   data-id="<?= (int)$game['id'] ?>"
                                   data-shots="<?= htmlspecialchars(json_encode($shots), ENT_QUOTES) ?>">
                                    <div class="gc-inner">
                                    <div class="game-image<?= $isBlur ? ' blur-adult' : '' ?>">
                                        <img class="gc-cover" src="<?= htmlspecialchars(!empty($game['path_to_cover']) ? $game['path_to_cover'] : $COVER_FALLBACK) ?>"
                                             alt="<?= htmlspecialchars($game['name']) ?>" loading="lazy" decoding="async">
                                        <div class="gc-shots" aria-hidden="true"></div>
                                        <?php if ($isSoon): ?>
                                            <span class="game-badge soon">Скоро</span>
                                        <?php elseif ($isNew): ?>
                                            <span class="game-badge">Новинка</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="game-info">
                                        <h3 class="game-title"><?= htmlspecialchars($game['name']) ?></h3>

                                        <?php /* Показывается только у раскрытой карточки. Держим в разметке,
                                                 а не подставляем скриптом: так текст есть в исходном коде
                                                 страницы и доступен поисковику и screen reader'у. */ ?>
                                        <div class="gc-more">
                                          <?php /* один общий потомок обязателен:
                                                   анимация grid-template-rows 0fr -> 1fr
                                                   работает только с единственной строкой */ ?>
                                          <div class="gc-block">
                                            <div class="gc-line">
                                                <?php if (!empty($game['studio_name'])): ?>
                                                    <span class="gc-studio"><?= htmlspecialchars($game['studio_name']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($avgRating !== null): ?>
                                                    <span class="gc-rating" title="<?= $avgRating ?> из 10">
                                                        <span class="gc-stars"><?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?></span>
                                                        <span class="gc-rnum"><?= $avgRating ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($descShort !== ''): ?>
                                                <p class="gc-desc"><?= htmlspecialchars($descShort) ?></p>
                                            <?php endif; ?>
                                          </div>
                                        </div>

                                        <div class="game-meta">
                                            <span class="game-genre"><?= htmlspecialchars($genre) ?></span>
                                            <span class="game-price <?= ($game['price'] == 0) ? 'free' : '' ?>"><?= $price ?></span>
                                        </div>
                                    </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php /* Маячок для IntersectionObserver: когда он попадает
                                 в зону видимости, подгружается следующая порция.
                                 Наблюдатель, в отличие от обработчика scroll,
                                 срабатывает один раз при пересечении и не жжёт
                                 кадры на каждом движении колеса. */ ?>
                        <div class="grid-sentinel" id="gridSentinel"<?= $hasMore ? '' : ' hidden' ?>>
                            <span class="gs-spinner" aria-hidden="true"></span>
                            <span class="gs-text">Загружаем ещё…</span>
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

        const SERVER_TOTAL = <?= (int)$gamesTotal ?>;
        const sentinel = document.getElementById('gridSentinel');

        const DEFAULTS = { adult: 0, genre: null, sort: 'popularity', dir: 'desc', priceType: 'all', priceMax: 5000, q: '', offset: 0 };
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
            if (state.q) p.set('q', state.q);
            p.set('offset', state.offset);

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

            const shots  = Array.isArray(game.screenshots) ? game.screenshots : [];
            const rating = (game.avg_rating !== null && game.avg_rating !== undefined)
                         ? Math.round(game.avg_rating * 10) / 10 : null;
            // рейтинг десятибалльный, звёзд пять — как на странице игры
            const stars  = rating !== null ? Math.max(0, Math.min(5, Math.round(rating / 2))) : 0;
            const starStr = rating !== null ? '★'.repeat(stars) + '☆'.repeat(5 - stars) : '';

            return '<a class="game-card" href="/g/' + encodeURIComponent(game.id) + '"'
                 + ' data-price="' + esc(game.price) + '"'
                 + ' data-popularity="' + esc(game.downloads) + '"'
                 + ' data-date="' + (isFinite(rel) ? Math.floor(rel / 1000) : 0) + '"'
                 + ' data-genre="' + esc(String(game.genre || '').toLowerCase()) + '"'
                 + ' data-id="' + esc(game.id) + '"'
                 + ' data-shots="' + esc(JSON.stringify(shots)) + '">'
                 +   '<div class="gc-inner">'
                 +   '<div class="game-image' + blur + '">'
                 +     '<img class="gc-cover" src="' + esc(game.path_to_cover || COVER_FALLBACK) + '"'
                 +          ' alt="' + esc(game.name) + '" loading="lazy" decoding="async">'
                 +     '<div class="gc-shots" aria-hidden="true"></div>'
                 +     badge
                 +   '</div>'
                 +   '<div class="game-info">'
                 +     '<h3 class="game-title">' + esc(game.name) + '</h3>'
                 +     '<div class="gc-more"><div class="gc-block">'
                 +       '<div class="gc-line">'
                 +         (game.studio_name ? '<span class="gc-studio">' + esc(game.studio_name) + '</span>' : '')
                 +         (rating !== null
                            ? '<span class="gc-rating" title="' + rating + ' из 10">'
                              + '<span class="gc-stars">' + starStr + '</span>'
                              + '<span class="gc-rnum">' + rating + '</span></span>'
                            : '')
                 +       '</div>'
                 +       (game.description ? '<p class="gc-desc">' + esc(game.description) + '</p>' : '')
                 +     '</div></div>'
                 +     '<div class="game-meta">'
                 +       '<span class="game-genre">' + esc(genre) + '</span>'
                 +       '<span class="game-price' + (game.price == 0 ? ' free' : '') + '">' + price + '</span>'
                 +     '</div>'
                 +   '</div>'
                 +   '</div>'
                 + '</a>';
        }

        const renderCards = games => games.map(cardHTML).join('');

        function renderGames(games) {
            if (!games.length) {
                grid.innerHTML = '<div class="no-games-message">'
                    + '<strong>Ничего не нашлось</strong>Попробуйте снять часть фильтров</div>';
                return;
            }
            grid.innerHTML = renderCards(games);
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
            /* Показываем СКОЛЬКО НАЙДЕНО, а не сколько подгружено:
               «48 игр» при трёхстах в каталоге вводило бы в заблуждение. */
            const loaded = grid.querySelectorAll('.game-card').length;
            const total  = Number.isFinite(state.total) ? state.total : loaded;
            resultsCount.textContent = total + ' ' + plural(total)
                + (loaded < total ? ' · показано ' + loaded : '');

            const dirty = state.genre || state.adult
                || state.priceType !== DEFAULTS.priceType
                || state.q !== '';
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

        /* ── Загрузка порциями ───────────────────────────────────────────
           Первая порция уже отрисована сервером, поэтому при открытии
           страницы запроса нет вообще — каталог виден сразу, без «прыжка»
           из скелетонов в карточки.

           loadPage(false) — заново с нулевого смещения (сменили фильтр,
           сортировку или поиск). loadPage(true) — дописать следующую порцию.
           Флаг busy обязателен: наблюдатель может сработать дважды подряд,
           пока первый запрос ещё в пути, и порция задвоится. */
        /* hoverPreview объявлен ниже по файлу через const, а loadPage его
           вызывает. К моменту первого вызова он уже инициализирован (скрипт
           к тому времени дочитан), но полагаться на это неудобно — держим
           безопасную обёртку. */
        const closeExpanded = () => { try { hoverPreview.closeNow(); } catch (e) {} };

        let inFlight = false;      // запрос сейчас в пути
        let reqId    = 0;          // номер последнего запроса, для отсева устаревших ответов
        let seenBottom = false;    // маячок в зоне видимости прямо сейчас

        async function loadPage(append) {
            /* Догрузку при занятой линии просто пропускаем — её перезапустит
               maybeLoadMore() после завершения текущего запроса.
               А вот СБРОС (смена фильтра, сортировки, поиска) пропускать
               нельзя: раньше стоял общий `if (busy) return`, и второй клик
               по фильтру молча терялся. Кнопка переключалась, выдача
               оставалась от предыдущего фильтра, состояние разъезжалось. */
            if (append && inFlight) return;

            const my = ++reqId;
            inFlight = true;

            if (!append) {
                state.offset = 0;
                closeExpanded();                  // раскрытая карточка сейчас исчезнет из DOM
                grid.setAttribute('aria-busy', 'true');
                grid.innerHTML = skeletons(12);
            }
            sentinel.classList.toggle('is-loading', append);

            try {
                const data = await fetchGames();

                /* Ответ устарел: пока он ехал, человек успел сменить фильтр.
                   Раньше такого отсева не было, и медленный ответ мог
                   перерисовать грид поверх более свежего. */
                if (my !== reqId) return;

                const games = Array.isArray(data.games) ? data.games : [];
                // shown/total появились вместе с постраничностью; если их нет,
                // значит на сервере лежит старая версия explore_games.php
                const shown = Number.isFinite(data.shown) ? data.shown : games.length;

                /* Страховка от бесконечного цикла: если сервер вернул пустую
                   порцию, но всё ещё говорит has_more, смещение не сдвинется
                   и maybeLoadMore() будет дёргать API до посинения.
                   Такое возможно при рассинхроне total и фактической выдачи. */
                if (append && shown === 0) {
                    sentinel.hidden = true;
                    return;
                }

                if (append) grid.insertAdjacentHTML('beforeend', renderCards(games));
                else        renderGames(games);

                state.offset = (append ? state.offset : 0) + shown;

                if (Number.isFinite(data.total)) {
                    state.total = data.total;
                } else {
                    console.warn('[explore] в ответе нет поля total — обновите swad/controllers/explore_games.php');
                    state.total = state.offset;
                }

                // жанры приходят только на нулевом смещении
                if (data.genres) renderFilters(data.genres);

                sentinel.hidden = (data.has_more === undefined)
                    ? (state.offset >= state.total)
                    : !data.has_more;

                adultWarning.classList.toggle('visible', !!state.adult);

                localStorage.setItem('explore_sort', JSON.stringify({ sort: state.sort, dir: state.dir }));
                localStorage.setItem('explore_filter', JSON.stringify({ priceType: state.priceType, priceMax: state.priceMax }));

                updateResultsBar();
            } catch (err) {
                console.error('[explore]', err);
                if (my === reqId) {
                    sentinel.hidden = true;
                    if (!append) {
                        grid.innerHTML = '<div class="no-games-message">'
                            + '<strong>Не удалось загрузить каталог</strong>Проверьте соединение и обновите страницу</div>';
                    }
                }
            } finally {
                if (my === reqId) {
                    inFlight = false;
                    grid.removeAttribute('aria-busy');
                    sentinel.classList.remove('is-loading');
                    maybeLoadMore();
                }
            }
        }

        /* Ключевая мелочь. После смены фильтра новая выдача короткая, и маячок
           сразу оказывается в зоне видимости — но наблюдатель уже сработал,
           пока шёл запрос, и его вызов был отброшен. Нового пересечения не
           происходит, потому что маячок и не уходил с экрана: подгрузка
           замирала до тех пор, пока человек не проскроллит туда-обратно.
           Поэтому после каждой загрузки проверяем видимость сами. */
        function maybeLoadMore() {
            if (!inFlight && seenBottom && !sentinel.hidden) loadPage(true);
        }

        const updateUI = () => loadPage(false);

        /* rootMargin 700px — подгружаем ЗАРАНЕЕ, за экран до низа.
           Если ждать фактического пересечения, человек успевает упереться
           в пустоту и увидеть паузу. */
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(entries => {
                seenBottom = entries[0].isIntersecting;
                maybeLoadMore();
            }, { rootMargin: '700px 0px' }).observe(sentinel);
        } else {
            // очень старый браузер — даём кнопку вместо наблюдателя
            sentinel.addEventListener('click', () => loadPage(true));
            sentinel.classList.add('is-manual');
        }

        function updateURL() {
            const url = new URL(window.location);
            state.genre ? url.searchParams.set('genre', state.genre) : url.searchParams.delete('genre');
            state.adult ? url.searchParams.set('adult', '1')         : url.searchParams.delete('adult');
            state.q     ? url.searchParams.set('q', state.q)         : url.searchParams.delete('q');
            /* replaceState, а не pushState: каждый клик по фильтру добавлял
               запись в историю, и кнопка «назад» вместо возврата на прошлую
               страницу отматывала фильтры по одному. */
            window.history.replaceState({}, '', url);
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
            } else if (target.dataset.genre === '') {
                /* «Все игры» сбрасывает и жанр, И раздел 18+.
                   Раньше сбрасывался только жанр, adult оставался включённым —
                   и список жанров продолжал показывать лишь те, что есть у
                   игр 18+. Со стороны это выглядело так, будто жанры пропали
                   навсегда и вернуть их можно только повторным кликом по 18+. */
                state.genre = null;
                state.adult = 0;
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
        /* Поиск ушёл на сервер. Раньше он прятал уже отрисованные карточки
           через card.style.display — с постраничностью это ломается:
           нужная игра лежит на третьей порции, которая ещё не загружена,
           и человек видит «ничего не найдено» там, где совпадение есть. */
        function applySearch() {
            state.q = searchInput.value.trim();
            searchBar.classList.toggle('has-value', state.q !== '');
            updateURL();
            return loadPage(false);
        }

        let searchTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            // 300 мс, а не 120: каждый вызов теперь идёт запросом в базу
            searchTimer = setTimeout(applySearch, 300);
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
            state.q = '';
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
                /* Отмена ДО раннего выхода. Раньше track(null) стоял ниже, и при
                   уходе курсора не на другую карточку, а в пустое место
                   выполнение обрывалось здесь — таймер продолжал тикать,
                   и через секунду карточка раскрывалась под уже уехавшей мышью. */
                if (!el) { hoverPreview.track(null); return; }
                if (active !== el) { active = el; el.classList.add('is-tilting'); }

                const cfg = GROUPS.find(g => el.matches(g.sel));
                if (!cfg) return;

                /* Мерим ВИДИМУЮ поверхность, а не сам грид-элемент.
                   У карточки это .gc-inner: при раскрытии он вырастает, а
                   .game-card остаётся прежнего размера (иначе поехала бы сетка).
                   Если считать от .game-card, то на раскрытой карточке курсор
                   уходит за её коробку, nx/ny упираются в ±1 и наклон
                   «залипает» у краёв вместо того, чтобы следовать за мышью. */
                const surf = el.querySelector(':scope > .gc-inner') || el;
                const hw = surf.offsetWidth / 2, hh = surf.offsetHeight / 2;
                if (!hw || !hh) return;

                /* offsetWidth — размеры ДО трансформации, они не «плывут» от
                   собственного scale. Центр берём из rect: transform-origin
                   по умолчанию 50% 50%, значит он смещается только на translateY. */
                const r = surf.getBoundingClientRect();
                const lift = el.style.transform ? cfg.lift : 0;
                const nx = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / hw));
                const ny = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2 - lift)) / hh));

                // --dx двигает блик за курсором (используется в .game-card::after)
                if (cfg.sel === '.game-card') el.style.setProperty('--dx', (nx * 50) + '%');

                el.style.transform = 'perspective(' + cfg.p + 'px) '
                    + 'rotateX(' + (-cfg.a * ny).toFixed(2) + 'deg) '
                    + 'rotateY(' + (cfg.a * nx).toFixed(2) + 'deg) '
                    + 'translateY(' + cfg.lift + 'px) scale(' + cfg.s + ')';

                // раскрытие карточки завязано на тот же «активный элемент»
                if (cfg.sel === '.game-card') hoverPreview.track(el);
                else hoverPreview.track(null);
            });

            document.addEventListener('mouseleave', () => hoverPreview.track(null));
        }

        /* ── Раскрытие карточки при задержке курсора ─────────────────────────
           Карточка не меняет своих размеров — растёт только внутренний слой
           .gc-inner через отрицательные inset. Поэтому грид не пересчитывается
           и соседи не двигаются, что бы ни происходило с раскрытой карточкой.

           Наклон при этом не сбрасывается: transform живёт на .game-card,
           а вырастает её потомок — состояние поворота сохраняется как есть. */
        const hoverPreview = (function () {
            const OPEN_DELAY = 450;   // сколько держать курсор до раскрытия
            const SHOT_EVERY = 2000;   // как часто листать скриншоты

            let timer = null, current = null, shotTimer = null;

            function close() {
                clearTimeout(timer); timer = null;
                clearInterval(shotTimer); shotTimer = null;
                if (!current) return;
                current.classList.remove('gc-open');
                const box = current.querySelector('.gc-shots');
                if (box) box.innerHTML = '';
                current.style.removeProperty('--gc-l');
                current.style.removeProperty('--gc-r');
                current = null;
            }

            /* Карточка у края сетки не должна вылезать за контейнер: growth
               перекидываем в ту сторону, где есть место. */
            function placeSides(card) {
                const grid = card.parentElement;
                if (!grid) return;
                const g = grid.getBoundingClientRect();
                const c = card.getBoundingClientRect();
                const grow = c.width * 0.5;              // суммарный прирост по ширине

                let left = grow / 2, right = grow / 2;
                if (c.left - left < g.left)   { right += left - Math.max(0, c.left - g.left);  left = Math.max(0, c.left - g.left); }
                if (c.right + right > g.right) { left += right - Math.max(0, g.right - c.right); right = Math.max(0, g.right - c.right); }

                card.style.setProperty('--gc-l', (-left).toFixed(1) + 'px');
                card.style.setProperty('--gc-r', (-right).toFixed(1) + 'px');
            }

            function startShots(card) {
                let shots = [];
                try { shots = JSON.parse(card.dataset.shots || '[]'); } catch (e) {}
                if (!Array.isArray(shots) || shots.length === 0) return;

                const box = card.querySelector('.gc-shots');
                if (!box) return;

                box.innerHTML = shots.map((src, i) =>
                    '<img src="' + String(src).replace(/"/g, '&quot;') + '" alt="" loading="lazy"'
                    + (i === 0 ? ' class="on"' : '') + '>').join('');

                const imgs = box.querySelectorAll('img');
                if (imgs.length < 2) return;

                let i = 0;
                shotTimer = setInterval(() => {
                    imgs[i].classList.remove('on');
                    i = (i + 1) % imgs.length;
                    imgs[i].classList.add('on');
                }, SHOT_EVERY);
            }

            function open(card) {
                current = card;
                placeSides(card);
                card.classList.add('gc-open');
                startShots(card);
            }

            return {
                track(card) {
                    if (card === current) return;          // уже раскрыта — ничего не делаем
                    close();
                    if (!card) return;
                    timer = setTimeout(() => open(card), OPEN_DELAY);
                },
                closeNow: close
            };
        })();

        // при перерисовке грида раскрытая карточка исчезает из DOM
        grid.addEventListener('scroll', () => hoverPreview.closeNow(), { passive: true });
        window.addEventListener('scroll', () => hoverPreview.closeNow(), { passive: true });

        /* ── Старт ─────────────────────────────────────────────────────────
           Первая порция уже отрисована сервером, поэтому запроса при
           открытии страницы НЕТ. Раньше здесь безусловно вызывался
           updateUI(): каталог рисовался дважды — сначала серверной
           разметкой, потом поверх неё ответом API. */
        state.offset = grid.querySelectorAll('.game-card').length;
        state.total  = SERVER_TOTAL;

        updateSortButtonsUI();
        updatePriceUI();
        updateResultsBar();

        /* Если состояние восстановилось из localStorage и отличается от
           того, что отрисовал сервер, — перезапрашиваем. */
        if (state.sort !== DEFAULTS.sort || state.dir !== DEFAULTS.dir
            || state.priceType !== DEFAULTS.priceType) {
            updateUI();
        }
    })();
    </script>
</body>

</html>