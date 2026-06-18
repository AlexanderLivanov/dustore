<?php session_start(); ?>
<?php
require_once('swad/config.php');
require_once('swad/controllers/game.php');

$gameController = new Game();
$games = $gameController->getLatestGames();

// Оставляем серверную фильтрацию только для начального отображения (SEO)
$games = array_filter($games, function ($game) {
    return isset($game['status']) && strtolower($game['status']) === 'published';
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
            if (!in_array($g, $allGenres)) {
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
        return in_array(strtolower($selectedGenre), array_map('strtolower', $genres));
    });
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Каталог игр</title>
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
                <div id="adult-warning" class="warning-adult" style="display: <?= $adultSection ? 'block' : 'none' ?>">
                    Внимание! Данный раздел содержит игры, предназначенные только для пользователей старше 18 лет
                    в соответствии с законодательством РФ.
                </div>

                <div class="search-wrapper">
                    <div class="sort-buttons" id="sortButtons">
                        <button class="sort-btn" data-sort="popularity" data-dir="desc">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            Популярные
                        </button>
                        <button class="sort-btn" data-sort="price" data-dir="asc">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            Цена
                        </button>
                        <button class="sort-btn" data-sort="date" data-dir="desc">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Новые
                        </button>
                    </div>
                        <div class="price-switch" id="priceSwitch">
                            <button class="price-btn active" data-price-type="all">Все</button>
                            <button class="price-btn" data-price-type="free">Бесплатные</button>
                            <button class="price-btn" data-price-type="paid">Платные</button>
                        </div>
                    <div class="search-bar">
                        <span class="search-icon"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 26 26" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg></span>
                        <input type="text" id="searchInput" placeholder="Введите название игры или тикер разработчика...">
                    </div>
                </div>

                <div class="games-body">
                    <div class="games-controls">
                        <button class="mobile-filter-toggle" id="filterToggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
                            </svg>
                            <span class="filter-label"><?= $selectedGenre ? htmlspecialchars($selectedGenre) : 'Все игры' ?></span>
                            <?php if ($selectedGenre): ?>
                                   <span class="active-badge">активен</span>
                            <?php endif; ?>
                            <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>
                        <div class="price-filters" id="priceFilters">
                            <div class="price-slider-container" id="priceSliderContainer" style="display: flex; margin-bottom: 10px; margin-top: 10px; flex-flow: wrap; place-content: space-around center; align-items: center; ">
                                <input type="range" id="priceSlider" min="100" max="5000" value="5000" step="100">
                                <span id="priceSliderValue">до 5000 ₽</span>
                            </div>
                        </div>
                        <div class="controls-left" id="filterPanel">
                            <a href="#" class="btn-filter <?= !$selectedGenre && !$adultSection ? 'active' : '' ?>" data-genre="">Все игры</a>
                            <a href="#" class="btn-filter <?= $adultSection ? 'active' : '' ?>" data-adult-toggle>18+</a>
                            <?php foreach ($allGenres as $genre): ?>
                                <a href="#" class="btn-filter <?= ($selectedGenre === $genre) ? 'active' : '' ?>" data-genre="<?= htmlspecialchars($genre) ?>">
                                    <?= htmlspecialchars($genre) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="content-row">
                        <div class="games-grid" id="gamesGrid">
                            <?php if (empty($games)): ?>
                                <div class="no-games-message"><p>Игры еще не добавлены в каталог</p></div>
                            <?php else: ?>
                                <?php foreach ($games as $game):
                                    $price = ($game['price'] == 0) ? 'Бесплатно' : number_format($game['price'], 0, ',', ' ') . ' ₽';
                                ?>
                                <div class="game-card"
                                     data-price="<?= (float)$game['price'] ?>"
                                     data-popularity="<?= (float)($game['rating'] ?? 0) ?>"
                                     data-date="<?= strtotime($game['release_date'] ?? '2000-01-01') ?>"
                                     data-id="<?= $game['id'] ?>"
                                     onclick="window.location.href='/g/<?= $game['id'] ?>';">
                                    <div class="game-image <?= ($adultSection && $game['age_rating'] >= 18) ? 'blur-adult' : '' ?>">
                                        <img src="<?= !empty($game['path_to_cover']) ? htmlspecialchars($game['path_to_cover']) : 'https://via.placeholder.com/400x225/74155d/ffffff?text=No+Image' ?>"
                                             alt="<?= htmlspecialchars($game['name']) ?>"
                                             style="mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 1) 75%, rgba(0, 0, 0, 0) 100%);">
                                        <?php if ($game['price'] == 0): ?>
                                            <div class="game-badge free">Бесплатно</div>
                                        <?php elseif ((time() - strtotime($game['release_date'])) < 30*24*60*60): ?>
                                            <div class="game-badge">Новинка</div>
                                        <?php elseif ((time() - strtotime($game['release_date'])) > 1): ?>
                                            <div class="game-badge">Скоро выйдет</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="game-info">
                                        <h3 class="game-title"><?= htmlspecialchars($game['name']) ?></h3>
                                        <div class="game-footer">
                                            <div class="game-price <?= ($game['price'] == 0) ? 'free' : '' ?>">
                                                <?= $price ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
            <p>Данный раздел содержит материалы только для пользователей старше 18 лет. Также игры в этом разделе могут содержать контент, который запрещён законодательством РФ. Платформа ни в коем случае такое не пропагандирует.</p>
            <button id="adultConfirmBtn">Мне есть 18 лет</button>
        </div>
    </div>

    <script>
    (function() {
    const GENRE_MAP = {
        'strategy':      'Стратегия',
        'rpg':           'РПГ',
        'action':        'Экшен',
        'adventure':     'Приключения',
        'puzzle':        'Головоломка',
        'racing':        'Гонки',
        'simulation':    'Симулятор',
        'horror':        'Хоррор',
        'indie':         'Инди',
        'platformer':    'Платформер',
        'visual novel':  'Визуальная новелла',
    };
        const API_URL = '/swad/controllers/explore_games.php';
        const grid = document.getElementById('gamesGrid');
        const filterPanel = document.getElementById('filterPanel');
        const filterToggle = document.getElementById('filterToggle');
        const adultModal = document.getElementById('adultModal');
        const adultWarning = document.getElementById('adult-warning');
        const searchInput = document.getElementById('searchInput');

        let state = {
            adult: <?= $adultSection ? 1 : 0 ?>,
            genre: <?= $selectedGenre ? json_encode($selectedGenre) : 'null' ?>,
            sort: 'popularity',
            dir: 'desc',
            priceType: 'all',   // 'free', 'paid'
            priceMax: 5000
        };

        // Восстановление из localStorage
        try {
            const saved = JSON.parse(localStorage.getItem('explore_filter') || '{}');
            if (saved.priceType) state.priceType = saved.priceType;
            if (saved.priceMax) state.priceMax = saved.priceMax;
        } catch(e) {}

        try {
            const saved = JSON.parse(localStorage.getItem('explore_sort') || '{}');
            if (saved.sort) state.sort = saved.sort;
            if (saved.dir) state.dir = saved.dir;
        } catch(e) {}

        async function fetchGames() {
            const params = new URLSearchParams();
            params.set('adult', state.adult);
            if (state.genre) params.set('genre', state.genre);
            params.set('sort', state.sort);
            params.set('dir', state.dir);
            params.set('price_type', state.priceType);
            if (state.priceType === 'paid') {
                params.set('price_max', state.priceMax);
            }

            const res = await fetch(`${API_URL}?${params.toString()}`);
            if (!res.ok) throw new Error('Network response was not ok');
            const data = await res.json();
            return data;
        }

        function renderGames(games) {
            if (!games.length) {
                grid.innerHTML = '<div class="no-games-message"><p>Игры еще не добавлены в каталог</p></div>';
                return;
            }

            grid.innerHTML = games.map(game => {
                const now = new Date();
                const releaseDate = new Date(game.release_date);
                const isNew = (now - releaseDate) < 30 * 24 * 60 * 60 * 1000;
                const priceStr = game.price == 0 ? 'Бесплатно' : Math.round(game.price).toLocaleString('ru-RU') + ' ₽';
                return `
                <div class="game-card hidden-card"
                     data-price="${game.price}"
                     data-popularity="${game.rating}"
                     data-date="${Math.floor(releaseDate.getTime() / 1000)}"
                     data-id="${game.id}"
                     onclick="window.location.href='/g/${game.id}'">
                    <div class="game-image ${state.adult && game.age_rating >= 18 ? 'blur-adult' : ''}">
                        <img src="${game.path_to_cover || 'https://via.placeholder.com/400x225/74155d/ffffff?text=No+Image'}"
                             alt="${game.name.replace(/"/g, '&quot;')}"
                             style="mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 1) 75%, rgba(0, 0, 0, 0) 100%);">
                        ${game.price == 0 ? '<div class="game-badge free">Бесплатно</div>' : ''}
                        ${isNew && game.price > 0 ? '<div class="game-badge">Новинка</div>' : ''}
                    </div>
                    <div class="game-info">
                        <h3 class="game-title">${game.name}</h3>
                        <div class="game-footer">
                            <div class="game-price ${game.price == 0 ? 'free' : ''}">
                                ${priceStr}
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');

            const cards = grid.querySelectorAll('.game-card.hidden-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';

                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';

                    // Убираем класс hidden-card через 400 мс (время анимации)
                    setTimeout(() => {
                        card.style.transition = '';
                        card.style.opacity = '';
                        card.style.transform = '';
                        card.classList.remove('hidden-card');
                    }, 400);
                }, index * 30);
            });
        }

        function renderFilters(genres) {
            const normalized = [];
            genres.forEach(g => {
                const lower = g.trim().toLowerCase();
                const ru = GENRE_MAP[lower] || g.trim();
                if (!normalized.includes(ru)) normalized.push(ru);
            });
            normalized.sort((a, b) => a.localeCompare(b, 'ru'));

            let html = `
                <a href="#" class="btn-filter ${!state.genre && !state.adult ? 'active' : ''}" data-genre="">Все игры</a>
                <a href="#" class="btn-filter ${state.adult ? 'active' : ''}" data-adult-toggle>18+</a>
            `;
            normalized.forEach(genre => {
                html += `<a href="#" class="btn-filter ${state.genre === genre ? 'active' : ''}" data-genre="${genre}">${genre}</a>`;
            });
            filterPanel.innerHTML = html;

            const label = filterToggle.querySelector('.filter-label');
            if (label) label.textContent = state.genre || 'Все игры';
            const badge = filterToggle.querySelector('.active-badge');
            if (badge) badge.style.display = state.genre ? '' : 'none';
        }

        async function updateUI() {
            try {
                const data = await fetchGames();
                renderGames(data.games);
                renderFilters(data.genres);

                if (state.adult) {
                    adultWarning.style.display = 'block';
                } else {
                    adultWarning.style.display = 'none';
                }

                localStorage.setItem('explore_sort', JSON.stringify({ sort: state.sort, dir: state.dir }));
                localStorage.setItem('explore_filter', JSON.stringify({
                    priceType: state.priceType,
                    priceMax: state.priceMax
                }));

                if (searchInput && searchInput.value.trim()) {
                    applySearch();
                }
            } catch (err) {
                console.error(err);
                grid.innerHTML = '<p style="color:red;">Ошибка загрузки игр</p>';
            }
        }

        function updateURL() {
            const url = new URL(window.location);
            url.searchParams.set('adult', state.adult);
            if (state.genre) {
                url.searchParams.set('genre', state.genre);
            } else {
                url.searchParams.delete('genre');
            }
            window.history.pushState({}, '', url);
        }

        filterPanel.addEventListener('click', async (e) => {
            e.preventDefault();
            const target = e.target.closest('.btn-filter');
            if (!target) return;

            if (target.hasAttribute('data-adult-toggle')) {
                if (!state.adult && !sessionStorage.getItem('adultConfirmed')) {
                    adultModal.style.display = 'flex';
                    return;
                }
                state.adult = state.adult ? 0 : 1;
                state.genre = null;
            } else {
                const genre = target.dataset.genre;
                state.genre = genre || null;
            }

            updateURL();
            await updateUI();

            filterPanel.classList.remove('open');
            filterToggle.classList.remove('open');
        });

        document.getElementById('adultConfirmBtn').addEventListener('click', async () => {
            sessionStorage.setItem('adultConfirmed', 'true');
            adultModal.style.display = 'none';
            state.adult = 1;
            state.genre = null;
            updateURL();
            await updateUI();
            document.querySelectorAll('.blur-adult').forEach(el => el.classList.remove('blur-adult'));
        });

        const sortButtons = document.querySelectorAll('#sortButtons .sort-btn');
        sortButtons.forEach(btn => {
            btn.addEventListener('click', async function() {
                const sort = this.dataset.sort;
                if (state.sort === sort) {
                    state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sort = sort;
                    state.dir = sort === 'price' ? 'asc' : 'desc';
                }
                updateURL();
                await updateUI();
                updateSortButtonsUI();
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

        updateSortButtonsUI();

        function applySearch() {
            const term = searchInput.value.toLowerCase().trim();
            document.querySelectorAll('#gamesGrid .game-card').forEach(card => {
                const title = card.querySelector('.game-title')?.textContent.toLowerCase() || '';
                card.style.display = title.includes(term) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', applySearch);

        window.addEventListener('popstate', async () => {
            const params = new URLSearchParams(window.location.search);
            state.adult = params.get('adult') == 1 ? 1 : 0;
            state.genre = params.get('genre') || null;
            await updateUI();
            updateSortButtonsUI();
        });

        filterToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = filterPanel.classList.toggle('open');
            filterToggle.classList.toggle('open', isOpen);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.games-controls')) {
                filterPanel.classList.remove('open');
                filterToggle.classList.remove('open');
            }
        });

        (async function() {
            await updateUI();
            updateSortButtonsUI();
        })();

        // Эффекты наклона
        (function() {
            const grid = document.getElementById('gamesGrid');
            if (!grid) return;
            let activeCard = null;

            function resetTilt(card) {
                card.style.transform = '';
            }

            grid.addEventListener('mousemove', (e) => {
                const card = e.target.closest('.game-card');
                if (!card || card.classList.contains('ad-card')) {
                    if (activeCard) {
                        resetTilt(activeCard);
                        activeCard = null;
                    }
                    return;
                }

                if (activeCard !== card) {
                    if (activeCard) resetTilt(activeCard);
                    activeCard = card;
                }

                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const nx = (x / rect.width) * 2 - 1;
                const ny = (y / rect.height) * 2 - 1;
                const maxAngle = 12;
                const rotateY = maxAngle * nx;
                const rotateX = -maxAngle * ny;
                const dx = nx * 50;
                card.style.setProperty('--dx', `${dx}%`);
                card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.02)`;
            });

            grid.addEventListener('mouseleave', () => {
                if (activeCard) {
                    resetTilt(activeCard);
                    activeCard = null;
                }
            });
        })();

        // --- Ценовые фильтры ---
        const priceButtons = document.querySelectorAll('#priceSwitch .price-btn');
        const priceSliderContainer = document.getElementById('priceSliderContainer');
        const priceSlider = document.getElementById('priceSlider');
        const priceSliderValue = document.getElementById('priceSliderValue');

        function updatePriceUI() {
            priceButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.priceType === state.priceType);
            });
            priceSliderContainer.style.display = state.priceType === 'paid' ? 'flex' : 'none';
            if (state.priceType === 'paid') {
                priceSlider.value = state.priceMax;
                priceSliderValue.textContent = `до ${state.priceMax} ₽`;
            }
        }

        priceButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                state.priceType = btn.dataset.priceType;
                if (state.priceType !== 'paid') state.priceMax = 5000;
                updatePriceUI();
                updateURL();
                await updateUI();
            });
        });

        priceSlider.addEventListener('input', function() {
            // Обновляем значение
            state.priceMax = parseInt(this.value, 10);
            priceSliderValue.textContent = `до ${state.priceMax} ₽`;

            // Наклон в зависимости от положения ползунка (0 = лево, 100 = право)
            const rect = this.getBoundingClientRect();
            const percent = (this.value - this.min) / (this.max - this.min);
            const nx = percent * 9 - 4; // от -1 (лево) до 1 (право)
            const maxAngle = -10; // угол наклона
            const rotateY = maxAngle * nx; // наклон вправо/влево
            const translateY = -2; // небольшой подъём
            this.style.transform = `perspective(400px) rotateY(${rotateY}deg) translateY(${translateY}px)`;

            // Дебаунс запроса
            clearTimeout(window.priceSliderTimer);
            window.priceSliderTimer = setTimeout(async () => {
                updateURL();
                await updateUI();
            }, 400);
        });

// Сброс наклона, когда отпускаем ползунок или уводим мышь
priceSlider.addEventListener('mouseleave', function() {
    this.style.transform = '';
});
priceSlider.addEventListener('mouseup', function() {
    this.style.transform = '';
});

        updatePriceUI();
    })();

// --- Эффект наклона (как в хедере) для кнопок сортировки, цен и фильтров ---
(function() {
    const selector = '.sort-btn, .price-btn, .btn-filter';
    let activeBtn = null;

    function resetTilt(btn) {
        btn.style.transform = '';
    }

    document.addEventListener('mousemove', function(e) {
        const btn = e.target.closest(selector);
        if (!btn) {
            if (activeBtn) {
                resetTilt(activeBtn);
                activeBtn = null;
            }
            return;
        }
        if (activeBtn !== btn) {
            if (activeBtn) resetTilt(activeBtn);
            activeBtn = btn;
        }
        const rect = btn.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const nx = (x / rect.width) * 2 - 1;
        const ny = (y / rect.height) * 2 - 1;
        const maxAngle = 15;
        const rotateY = maxAngle * nx;
        const rotateX = -maxAngle * ny;
        const translateY = -3;
        const scale = 1.04;
        btn.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
    });

    // При уходе курсора с кнопки (сброс произойдёт на следующем mousemove, когда курсор не над кнопкой)
})();

// --- Наклон и тряска поля поиска ---
(function() {
    const searchBar = document.querySelector('.search-bar');
    const searchInput = document.getElementById('searchInput');
    if (!searchBar || !searchInput) return;

    // Наклон обёртки при движении мыши над ней (как у кнопок)
    searchBar.addEventListener('mousemove', function(e) {
        const rect = searchBar.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const nx = (x / rect.width) * 2 - 1;
        const ny = (y / rect.height) * 10 - 5;
        const maxAngle = 5;   // лёгкий наклон, чтобы не мешать вводу
        const rotateY = maxAngle * nx;
        const rotateX = -maxAngle * ny;
        searchBar.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-2px)`;
    });

    searchBar.addEventListener('mouseleave', function() {
        searchBar.style.transform = '';
    });

    // Тряска при вводе / удалении символов
    searchInput.addEventListener('input', function() {
        searchInput.classList.remove('shake-it');
        void searchInput.offsetWidth; // форсируем reflow
        searchInput.classList.add('shake-it');
        searchInput.addEventListener('animationend', function onAnimEnd() {
            searchInput.classList.remove('shake-it');
            searchInput.removeEventListener('animationend', onAnimEnd);
        });
    });
})();
    </script>
</body>
</html>