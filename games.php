<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Каталог игр</title>
    <?php require_once('swad/controllers/ymcounter.php'); ?>
    <style>
        /* Основные стили (те же, что и на главной) */
        :root {
            --primary: #c32178;
            --secondary: #74155d;
            --dark: #14041d;
            --light: #f8f9fa;
            --gradient: linear-gradient(180deg, #14041d, #400c4a, #74155d, #c32178);
            --gradient2: linear-gradient(180deg, #c32178, #14041d, #400c4a, #c32178);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--dark);
            color: var(--light);
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        section {
            padding: 40px 0;
        }

        h1,
        h2,
        h3 {
            font-family: 'PixelizerBold', 'Gill Sans', sans-serif;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        h1 {
            font-size: 2.5rem;
        }

        h2 {
            font-size: 2rem;
            position: relative;
            margin-bottom: 30px;
        }

        h2:after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }

        .btn {
            display: inline-block;
            padding: 10px 25px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #e62e8a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(195, 33, 120, 0.3);
        }

        /* Стили для страницы игр */
        .games-header {
            background: var(--gradient);
            padding: 100px 0 40px;
        }

        .games-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .search-bar {
            flex: 1;
            min-width: 300px;
            max-width: 600px;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 20px;
            padding-left: 45px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            color: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s;
        }

        .search-bar input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
        }

        .filter-btn {
            background: rgba(195, 33, 120, 0.2);
            border: 1px solid var(--primary);
            color: white;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            background: rgba(195, 33, 120, 0.3);
        }

        .categories {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .category {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 20px;
            border-radius: 20px;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.3s;
        }

        .category:hover,
        .category.active {
            background: var(--primary);
        }

        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .game-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .game-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }

        .game-image {
            width: 100%;
            height: 160px;
            background: var(--secondary);
            position: relative;
            overflow: hidden;
        }

        .game-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .game-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .game-info {
            padding: 20px;
        }

        .game-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .game-developer {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 15px;
        }

        .game-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .game-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(0, 0, 0, 0.3);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .game-price {
            background: var(--primary);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }

        .game-price.free {
            background: #2ecc71;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 50px;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .page-btn:hover,
        .page-btn.active {
            background: var(--primary);
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .games-controls {
                flex-direction: column;
            }

            .search-bar {
                min-width: 100%;
            }

            .games-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="games-header">
            <div class="container">
                <h1>Каталог игр</h1>
                <p>Откройте для себя лучшие игры от независимых разработчиков</p>
            </div>
        </section>

        <section class="games-list">
            <div class="container">
                <div class="games-controls">
                    <div class="search-bar">
                        <span class="search-icon">🔍</span>
                        <input type="text" placeholder="Поиск игр...">
                    </div>
                    <!-- <button class="filter-btn">
                        <span>Фильтры</span>
                        <span>▼</span>
                    </button> -->
                </div>
<!-- TODO: сделать фильтры -->
                <!-- <div class="categories">
                    <div class="category active">Все</div>
                    <div class="category">Экшен</div>
                    <div class="category">RPG</div>
                    <div class="category">Стратегии</div>
                    <div class="category">Инди</div>
                    <div class="category">Гонки</div>
                    <div class="category">Симуляторы</div>
                    <div class="category">Хоррор</div>
                    <div class="category">Приключения</div>
                    <div class="category">Казуальные</div>
                </div> -->

                <div class="games-grid">
                    <!-- Игра 1 -->
                    <div class="game-card" onclick="window.location.replace('testgame');">
                        <div class="game-image">
                            <img src="https://via.placeholder.com/400x225/74155d/ffffff?text=Super+Game" alt="Super Game">
                            <div class="game-badge">Новинка</div>
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">Super Game</h3>
                            <p class="game-developer">От Super Studio</p>
                            <div class="game-footer">
                                <div class="game-rating">★ 4.7</div>
                                <div class="game-price">149 ₽</div>
                            </div>
                        </div>
                    </div>

                    <!-- Игра 2 -->
                    <div class="game-card">
                        <div class="game-image">
                            <img src="https://via.placeholder.com/400x225/400c4a/ffffff?text=Great+Game" alt="Great Game">
                            <div class="game-badge">Хит</div>
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">Great Game</h3>
                            <p class="game-developer">От Great Devs</p>
                            <div class="game-footer">
                                <div class="game-rating">★ 4.9</div>
                                <div class="game-price">349 ₽</div>
                            </div>
                        </div>
                    </div>

                    <!-- Игра 3 -->
                    <div class="game-card">
                        <div class="game-image">
                            <img src="https://via.placeholder.com/400x225/c32178/ffffff?text=Mega+Game" alt="Mega Game">
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">Mega Game</h3>
                            <p class="game-developer">От Mega Team</p>
                            <div class="game-footer">
                                <div class="game-rating">★ 4.5</div>
                                <div class="game-price">49 ₽</div>
                            </div>
                        </div>
                    </div>

                    <!-- Игра 4 -->
                    <div class="game-card">
                        <div class="game-image">
                            <img src="https://via.placeholder.com/400x225/74155d/ffffff?text=New+Game" alt="New Game">
                            <div class="game-badge">Ранний доступ</div>
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">New Game</h3>
                            <p class="game-developer">От New Studio</p>
                            <div class="game-footer">
                                <div class="game-rating">★ 4.8</div>
                                <div class="game-price">109 ₽</div>
                            </div>
                        </div>
                    </div>

                    <!-- Игра 5 -->
                    <div class="game-card">
                        <div class="game-image">
                            <img src="https://via.placeholder.com/400x225/400c4a/ffffff?text=Adventure+Time" alt="Adventure Time">
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">Adventure Time</h3>
                            <p class="game-developer">От Adventure Team</p>
                            <div class="game-footer">
                                <div class="game-rating">★ 4.6</div>
                                <div class="game-price">199 ₽</div>
                            </div>
                        </div>
                    </div>

                    <!-- Игра 6 -->
                    <div class="game-card">
                        <div class="game-image">
                            <img src="https://via.placeholder.com/400x225/c32178/ffffff?text=Space+Explorer" alt="Space Explorer">
                            <div class="game-badge">Бесплатно</div>
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">Space Explorer</h3>
                            <p class="game-developer">От Space Devs</p>
                            <div class="game-footer">
                                <div class="game-rating">★ 4.3</div>
                                <div class="game-price free">Бесплатно</div>
                            </div>
                        </div>
                    </div>

                    <!-- Игра 7 -->
                    <div class="game-card">
                        <div class="game-image">
                            <img src="https://via.placeholder.com/400x225/74155d/ffffff?text=Zombie+Survival" alt="Zombie Survival">
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">Zombie Survival</h3>
                            <p class="game-developer">От Horror Games</p>
                            <div class="game-footer">
                                <div class="game-rating">★ 4.2</div>
                                <div class="game-price">249 ₽</div>
                            </div>
                        </div>
                    </div>

                    <!-- Игра 8 -->
                    <div class="game-card">
                        <div class="game-image">
                            <img src="https://via.placeholder.com/400x225/400c4a/ffffff?text=Racing+Extreme" alt="Racing Extreme">
                            <div class="game-badge">Скидка 30%</div>
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">Racing Extreme</h3>
                            <p class="game-developer">От Speed Devs</p>
                            <div class="game-footer">
                                <div class="game-rating">★ 4.7</div>
                                <div class="game-price">349 ₽</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pagination">
                    <div class="page-btn">←</div>
                    <div class="page-btn active">1</div>
                    <div class="page-btn">2</div>
                    <div class="page-btn">3</div>
                    <div class="page-btn">4</div>
                    <div class="page-btn">5</div>
                    <div class="page-btn">→</div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        // Анимация для карточек игр
        document.addEventListener('DOMContentLoaded', function() {
            const gameCards = document.querySelectorAll('.game-card');

            gameCards.forEach((card, index) => {
                // Добавляем небольшую задержку для каждой карточки
                card.style.transitionDelay = `${index * 0.05}s`;
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                // Анимация появления
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });

            // Обработка категорий
            const categories = document.querySelectorAll('.category');
            categories.forEach(category => {
                category.addEventListener('click', function() {
                    categories.forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // Обработка пагинации
            const pageBtns = document.querySelectorAll('.page-btn');
            pageBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!this.classList.contains('active')) {
                        document.querySelector('.page-btn.active').classList.remove('active');
                        this.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>

</html>