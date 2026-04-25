<?php session_start(); ?>
<?php
require_once('swad/config.php');

$db = new Database();
$pdo = $db->connect();

// Получаем студии
$stmt = $pdo->prepare("SELECT id, name, description, avatar_link, website, created_at, tiker FROM studios ORDER BY id DESC");
$stmt->execute();
$studios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, first_name, last_name, telegram_username, username, profile_picture, added, country, city, website, email FROM users ORDER BY id DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — Каталог студий и пользователей</title>
    <link rel="stylesheet" href="swad/css/explore.css">
    <link rel="shortcut icon" href="swad/static/img/logo.svg" type="image/x-icon">
    <style>
        /* --------------------------------------------- */
        /*  Переменные (можно синхронизировать с explore) */
        /* --------------------------------------------- */
        :root {
            --primary: #c32178;
            --primary-dark: #9e1a66;
            --primary-light: #e6399e;
            --bg-dark: #14041d;
            --bg-card: rgba(255, 255, 255, 0.05);
            --border-light: rgba(255, 255, 255, 0.08);
            --text-primary: #fff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-muted: rgba(255, 255, 255, 0.5);
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 16px 40px rgba(0, 0, 0, 0.4);
            --radius-sm: 8px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --transition: 0.2s ease;
        }

        /* --------------------------------------------- */
        /*  Глобальные стили (переопределение/дополнение) */
        /* --------------------------------------------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(180deg, #28112b, #61114e);
            color: var(--text-primary);
            line-height: 1.5;
        }

        main {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 70px - 40px); /* хедер + футер */
            overflow: hidden;
        }

        .games-header {
            flex-shrink: 0;
            padding: 20px 0 0;
        }

        .container1 {
            text-align: center;
            font-size: 15px;
            background: transparent;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
        }

        .container1 h1 {
            font-size: 2.5rem;
            font-weight: 600;
            background: linear-gradient(135deg, #fff, #ffb6e0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .container1 p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Главный контейнер контента */
        .container {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            padding: 20px;
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(8px);
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            max-width: 100%;
            min-width: 95.5%;
            margin: 0 auto;
            box-shadow: var(--shadow-sm);
        }

        /* --------------------------------------------- */
        /*  Фильтры + поиск                              */
        /* --------------------------------------------- */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 24px;
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: blur(4px);
        }

        .filter-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(195, 33, 120, 0.3);
        }

        /* Поисковое поле */
        .search-wrapper {
            flex: 1 1 320px;
            max-width: 500px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px 12px 48px;
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(0, 0, 0, 0.3);
            color: white;
            font-size: 1rem;
            outline: none;
            transition: var(--transition);
            backdrop-filter: blur(4px);
        }

        .search-input:focus {
            border-color: var(--primary);
            background: rgba(0, 0, 0, 0.5);
            box-shadow: 0 0 0 3px rgba(195, 33, 120, 0.2);
        }

        .search-input::placeholder {
            color: var(--text-muted);
            font-weight: 300;
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        .search-clear {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 18px;
            cursor: pointer;
            display: none;
            padding: 4px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .search-clear:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .search-wrapper.filled .search-clear {
            display: block;
        }

        /* Счётчик результатов */
        .results-count {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-left: auto;
            white-space: nowrap;
        }

        /* --------------------------------------------- */
        /*  Сетка карточек                                */
        /* --------------------------------------------- */
        .grid-container {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            padding: 8px 4px 20px 4px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        .grid-container::-webkit-scrollbar {
            width: 4px;
        }
        .grid-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .grid-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
        }
        .grid-container::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* --------------------------------------------- */
        /*  Карточка (современный glassmorphism)         */
        /* --------------------------------------------- */
        .card {
            backdrop-filter: blur(15px);
            border-radius: var(--radius-md);
            padding: 20px;
            cursor: pointer;
            transition: transform 0.25s cubic-bezier(0.2, 0, 0, 1), box-shadow 0.3s, border-color 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-sm);
            transform: translateY(0);
        }

        .card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(195, 33, 120, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(195, 33, 120, 0.2);
        }

        /* Псевдоэлемент для красивого свечения */
        .card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(145deg, rgba(255,255,255,0.1), rgba(195,33,120,0.2));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .card:hover::after {
            opacity: 1;
        }

        /* Шапка карточки с аватаром и типом */
        .card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(195, 33, 120, 0.4);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255,255,255,0.1);
            color: var(--text-secondary);
            border: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 6px;
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 6px;
            color: #fff;
        }

        .card-desc {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .card-desc-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-desc-item i {
            opacity: 0.6;
            font-size: 14px;
            width: 18px;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .card-footer .website {
            color: var(--primary-light);
            text-decoration: none;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card-footer .website:hover {
            text-decoration: underline;
        }

        /* --------------------------------------------- */
        /*  Пустое состояние                              */
        /* --------------------------------------------- */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            background: rgba(0,0,0,0.2);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(4px);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            font-weight: 400;
            margin-bottom: 10px;
            color: var(--text-secondary);
        }

        .empty-state p {
            font-size: 1.1rem;
        }

        /* --------------------------------------------- */
        /*  Адаптивность                                  */
        /* --------------------------------------------- */
        @media (max-width: 700px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            .filters {
                justify-content: center;
            }
            .search-wrapper {
                max-width: 100%;
            }
            .grid-container {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
                gap: 16px;
            }
            .container1 h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .card-header {
                flex-direction: column;
                text-align: center;
            }
        }

        /* --------------------------------------------- */
        /*  Анимация появления карточек                  */
        /* --------------------------------------------- */
        .card {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.4s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Задержки для карточек (задаются через JS) */
    </style>
</head>
<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="games-header">
            <div class="container1">
                <h1>Каталог студий и пользователей</h1>
                <p>Найдите разработчиков, художников, геймдизайнеров и единомышленников</p>
            </div>
        </section>

        <div class="container">
            <!-- Панель фильтров и поиска -->
            <div class="filter-row">
                <div class="filters" role="tablist" aria-label="Тип контента">
                    <button class="filter-btn active" data-type="all" role="tab" aria-selected="true">Все</button>
                    <button class="filter-btn" data-type="studios" role="tab" aria-selected="false">Студии</button>
                    <button class="filter-btn" data-type="users" role="tab" aria-selected="false">Пользователи</button>
                </div>

                <div class="search-wrapper" id="searchWrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" id="searchInput" placeholder="Искать по имени, описанию, локации..." aria-label="Поиск">
                    <button class="search-clear" id="searchClear" aria-label="Очистить поиск">✕</button>
                </div>

                <div class="results-count" id="resultsCount"></div>
            </div>

            <!-- Сетка карточек -->
            <div class="grid-container" id="cardsGrid">
                <!-- Студии -->
                <?php foreach ($studios as $s): ?>
                    <article class="card studio-card" data-type="studios" data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>" data-desc="<?= htmlspecialchars(strtolower($s['description'] ?? '')) ?>" data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="<?= !empty($s['avatar_link']) ? htmlspecialchars($s['avatar_link']) : '/swad/static/img/logo.svg' ?>" alt="<?= htmlspecialchars($s['name']) ?>" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">🏢 Студия</span>
                                <h2 class="card-title"><?= htmlspecialchars($s['name']) ?></h2>
                            </div>
                        </div>
                        <div class="card-desc">
                            <div class="card-desc-item"><?= htmlspecialchars(mb_strimwidth($s['description'] ?: 'Описание отсутствует', 0, 120, '...')) ?></div>
                        </div>
                        <div class="card-footer">
                            <?php if (!empty($s['website'])): ?>
                                <a href="<?= htmlspecialchars($s['website']) ?>" class="website" target="_blank" rel="noopener" onclick="event.stopPropagation();"><?= htmlspecialchars(parse_url($s['website'], PHP_URL_HOST) ?: $s['website']) ?></a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <time datetime="<?= $s['created_at'] ?>" class="card-date">📅 <?= date('d.m.Y', strtotime($s['created_at'])) ?></time>
                        </div>
                        <!-- Ссылка для клика по всей карточке -->
                        <a href="/d/<?= $s['tiker'] ?>" class="card-link" aria-label="Перейти к студии <?= htmlspecialchars($s['name']) ?>" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                <?php endforeach; ?>

                <!-- Пользователи -->
                <?php foreach ($users as $user): ?>
                    <?php
                    $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
                    $displayName = !empty($fullName) ? $fullName : ($user['username'] ?? $user['telegram_username'] ?? 'Пользователь');
                    $username = $user['username'] ?? '';
                    $location = '';
                    if (!empty($user['city']) && !empty($user['country'])) $location = $user['city'] . ', ' . $user['country'];
                    elseif (!empty($user['city'])) $location = $user['city'];
                    elseif (!empty($user['country'])) $location = $user['country'];
                    $avatar = !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : '/swad/static/img/logo.svg';
                    ?>
                    <article class="card user-card" data-type="users" data-name="<?= htmlspecialchars(strtolower($displayName.' '.$username)) ?>" data-desc="<?= htmlspecialchars(strtolower($location)) ?>" data-location="<?= htmlspecialchars(strtolower($location)) ?>">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="<?= $avatar ?>" alt="<?= htmlspecialchars($displayName) ?>" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">👤 Пользователь</span>
                                <h2 class="card-title"><?= htmlspecialchars($displayName) ?></h2>
                            </div>
                        </div>
                        <div class="card-desc">
                            <?php if (!empty($username)): ?>
                                <div class="card-desc-item">@<?= htmlspecialchars($username) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($location)): ?>
                                <div class="card-desc-item">📍 <?= htmlspecialchars($location) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <?php if (!empty($user['website'])): ?>
                                <a href="<?= htmlspecialchars($user['website']) ?>" class="website" target="_blank" rel="noopener" onclick="event.stopPropagation();"><?= htmlspecialchars(parse_url($user['website'], PHP_URL_HOST) ?: $user['website']) ?></a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <time datetime="<?= $user['added'] ?>" class="card-date">📅 <?= date('d.m.Y', strtotime($user['added'])) ?></time>
                        </div>
                        <a href="/player/<?= $user['username'] ?>" class="card-link" aria-label="Перейти к профилю <?= htmlspecialchars($displayName) ?>" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        (function() {
            const cards = Array.from(document.querySelectorAll('.grid-container .card'));
            const filterButtons = document.querySelectorAll('.filters .filter-btn');
            const searchInput = document.getElementById('searchInput');
            const searchClear = document.getElementById('searchClear');
            const searchWrapper = document.getElementById('searchWrapper');
            const resultsCount = document.getElementById('resultsCount');
            const gridContainer = document.getElementById('cardsGrid');

            let currentFilter = 'all';
            let searchTerm = '';

            // Debounce для поиска
            let searchTimeout;
            const DEBOUNCE_DELAY = 250;

            // Функция фильтрации
            function filterCards() {
                const term = searchTerm.toLowerCase().trim();
                let visibleCount = 0;

                cards.forEach(card => {
                    const type = card.dataset.type;
                    const name = (card.dataset.name || '').toLowerCase();
                    const desc = (card.dataset.desc || '').toLowerCase();
                    const location = (card.dataset.location || '').toLowerCase();

                    const matchesType = currentFilter === 'all' || type === currentFilter;
                    const matchesSearch = term === '' || name.includes(term) || desc.includes(term) || location.includes(term);

                    const visible = matchesType && matchesSearch;
                    card.style.display = visible ? 'flex' : 'none';
                    if (visible) visibleCount++;
                });

                // Обновление счётчика результатов
                resultsCount.textContent = visibleCount ? `Найдено: ${visibleCount}` : '';

                // Показываем пустое состояние, если нет карточек
                let emptyEl = document.getElementById('empty-state');
                if (visibleCount === 0) {
                    if (!emptyEl) {
                        emptyEl = document.createElement('div');
                        emptyEl.id = 'empty-state';
                        emptyEl.className = 'empty-state';
                        emptyEl.innerHTML = `
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 6v6l4 2" />
                            </svg>
                            <h3>Ничего не найдено</h3>
                            <p>Попробуйте изменить параметры поиска</p>
                        `;
                        gridContainer.appendChild(emptyEl);
                    }
                } else {
                    if (emptyEl) emptyEl.remove();
                }

                // Обновление aria-selected для кнопок фильтров
                filterButtons.forEach(btn => {
                    const isActive = btn.dataset.type === currentFilter;
                    btn.classList.toggle('active', isActive);
                    btn.setAttribute('aria-selected', isActive);
                });
            }

            // Обработчики фильтров
            filterButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    currentFilter = btn.dataset.type;
                    filterCards();
                });
            });

            // Обработчик поиска с debounce
            searchInput.addEventListener('input', () => {
                searchTerm = searchInput.value;
                searchWrapper.classList.toggle('filled', searchTerm.length > 0);

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterCards();
                }, DEBOUNCE_DELAY);
            });

            // Очистка поиска
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchTerm = '';
                searchWrapper.classList.remove('filled');
                filterCards();
                searchInput.focus();
            });

            // Закрытие кликом вне (необязательно)
            document.addEventListener('click', (e) => {
                if (!searchWrapper.contains(e.target)) {
                    // можно убрать, если не нужно
                }
            });

            // Предотвращаем всплытие клика по ссылкам внутри карточки
            document.querySelectorAll('.card .website, .card .card-link').forEach(el => {
                el.addEventListener('click', (e) => e.stopPropagation());
            });

            // Начальная фильтрация (устанавливаем active классы и счётчик)
            filterCards();

            // Анимация появления с задержкой (альтернатива CSS-анимации)
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 30}ms`;
            });

            // Доступность: поддержка клавиши Enter на карточках
            cards.forEach(card => {
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const link = card.querySelector('.card-link');
                        if (link) link.click();
                    }
                });
                card.setAttribute('tabindex', '0');
                card.setAttribute('role', 'article');
            });
        })();
    </script>
</body>
</html>