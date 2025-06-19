<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Детали игры</title>
    <link rel="stylesheet" href="/swad/css/gamepage.css">
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="game-hero">
            <div class="game-banner" style="background-image: url('https://via.placeholder.com/1920x600/74155d/ffffff?text=Dustore+Game+Banner')"></div>
            <div class="container">
                <div class="game-content">
                    <div class="game-main">
                        <div class="game-header">
                            <div class="game-logo">
                                <img class="game-logo" src="/swad/static/img/hg-icon.jpg" alt="">
                            </div>
                            <div class="game-info-header">
                                <h1>Howl-Growl</h1>
                                <div class="game-badges">
                                    <div class="game-badge">Визуальная новелла</div>
                                    <div class="game-badge">Популярное</div>
                                </div>
                                <div class="game-stats">
                                    <div class="stat-item">
                                        <div class="stat-value">98/100</div>
                                        <div class="stat-label">GQI</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">01.01.2025</div>
                                        <div class="stat-label">Дата выпуска</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">9.2 тыс</div>
                                        <div class="stat-label">Оценок</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="game-description">
                            <p>
                                Декабрь 1999 года. Россия. Маленький город Елинск.
                                Уполномоченный участковый Сергей (Серый), работающий в единственном милицейском участке, получил вызов об отравлении собаки. Оказалось, пёс принадлежал местному школьному повару. Вроде бы заурядный случай… но нашлось несколько несостыковок…
                                <br>
                                В городе изредка всплывают слухи о пропаже бездомных. Местные маргиналы, под разными сомнительными предлогами, просят милицию разобраться. Вторые же отклоняют обращения из-за отсутствия улик либо доказательств.
                                За год до этого, в Елинске уже были пропажи, связанные с детьми.
                                <br>
                                Дело так до сих пор и открыто, убранное в долгий ящик.
                                Успеет ли Серый разобраться и раскрыть каждое дело, или все они канут в лету так и оставшись тайной?
                                А может, мигрень выведет участкового из расследований раньше, чем происходящее сведёт его с ума?
                            </p>
                        </div>

                        <div class="game-features">
                            <h2>Особенности игры</h2>
                            <div class="features-list">
                                <div class="feature-item">
                                    <div class="feature-icon">🎮</div>
                                    <div>
                                        <h3>Динамичный геймплей</h3>
                                        <p>Сочетание большого количества механик и нестандартного использования движка</p>
                                    </div>
                                </div>
                                <div class="feature-item">
                                    <div class="feature-icon">🌆</div>
                                    <div>
                                        <h3>Живой мир</h3>
                                        <p>Огромное количество локаций и персонажей</p>
                                    </div>
                                </div>
                                <div class="feature-item">
                                    <div class="feature-icon">💻</div>
                                    <div>
                                        <h3>Уникальный сюжет</h3>
                                        <p>Много нововведений и авторских идей</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h2>Трейлер</h2>
                        <div class="trailer-container">
                            <iframe src="https://leonardo.osnova.io/d45f6fbd-7ac8-5015-aad5-a9a69fe5d0cb/-/format/mp4/#t=0.1" width="640" height="360" frameborder="0" allowfullscreen="1" allow="autoplay; encrypted-media; fullscreen; picture-in-picture"></iframe>
                        </div>

                        <h2>Скриншоты</h2>
                        <div class="screenshots-grid">
                            <div class="screenshot" style="background: url('https://sun9-69.userapi.com/impg/YFzknbC-mwoOQ_iZIUSVcWjoE11PPROLNuaL8Q/WcfDtaNgYDQ.jpg?size=807x452&quality=95&sign=91fe53974d6a45aa1d5d719850cc4947&type=album') no-repeat center center / cover;"></div>
                            <div class="screenshot" style="background: url('https://sun9-4.userapi.com/impg/kpV8mmc-wGDUYluiifbH0dw_hFi1XjL74HgimA/J_OOzM8WXyQ.jpg?size=807x399&quality=95&sign=443cf582e4843132bae0122a8f72fed1&type=album') no-repeat center center / cover;"></div>
                            <div class="screenshot" style="background: url('https://sun9-51.userapi.com/impg/ttg7JaodxHU-1vnaZdTl49eYp4j_WGQ1M6q28Q/-Un5F5izmas.jpg?size=930x1080&quality=95&sign=fe67aa39e596e2e05072124da022563c&type=album') no-repeat center center / cover;"></div>
                            <div class="screenshot" style="background: url('https://sun9-42.userapi.com/s/v1/if2/8sYVHZepdHOUJIkIgDOlYkSjHP2j3v7vktbX9B7Q2QtPSBYu1woXhIJIHT7kH5PtSqosvQs0lf0LzjOjX17k5FDs.jpg?quality=95&as=32x18,48x27,72x40,108x61,160x90,240x135,360x202,480x270,540x304,640x360,720x405,1080x607,1280x720,1440x810,1920x1080&from=bu&u=2rfdMjz55-KxF17_ycZGmXbtyNe-4u4csPuh4SrkJ2I&cs=807x454') no-repeat center center / cover;"></div>
                            <div class="screenshot" style="background: url('https://sun9-21.userapi.com/s/v1/if2/v2aFd8K2r5kjYyAw2-Dc49ao-pVeODPP9xXpt4YScxu4oqPXCGldDmTHOveyq0-TzGvnCO5BhHXHWQSMkazeXJ8B.jpg?quality=95&as=32x18,48x27,72x40,108x61,160x90,240x135,360x202,480x270,540x304,640x360,720x405,1080x607,1280x720&from=bu&u=QLRuodAkX8_2SnRot18akua1ToCfTXTQepBiXqILIUw&cs=807x454') no-repeat center center / cover;"></div>
                            <div class="screenshot" style="background: url('https://sun9-69.userapi.com/s/v1/if2/OIKsoSVOjvZy1La2OaC4j1wNkzs9Az_RLCMCN8evEvO8vBAZEaztCGs8Zw_IHZYo3AyUa7lPd4RP4IbsI9TJvHgD.jpg?quality=95&as=32x18,48x27,72x40,108x61,160x90,240x135,360x202,480x270,540x304,640x360,720x405,1080x607,1280x720&from=bu&u=s-2FQAE379HA1Wo-B18wkDdp49ljTudGfPgHDHaDkIk&cs=807x454') no-repeat center center / cover;"></div>
                        </div>

                        <div class="system-requirements">
                            <h2>Системные требования</h2>
                            <div class="requirements-grid">
                                <div class="requirement-item">
                                    <div class="requirement-label">ОС</div>
                                    <div class="requirement-value">Windows | MacOS | Linux | Android</div>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-label">Процессор</div>
                                    <div class="requirement-value">Любой</div>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-label">Память</div>
                                    <div class="requirement-value">2 GB RAM</div>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-label">Видеокарта</div>
                                    <div class="requirement-value">Любая</div>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-label">DirectX | OpenGL</div>
                                    <div class="requirement-value">11 | 3.0</div>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-label">Место на диске</div>
                                    <div class="requirement-value">1 GB</div>
                                </div>
                            </div>
                        </div>

                        <div class="reviews-section">
                            <h2>Отзывы игроков</h2>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="review-author">
                                        <div class="author-avatar"></div>
                                        <div>
                                            <h3>Игрок123</h3>
                                            <div>★ 10</div>
                                        </div>
                                    </div>
                                    <div class="review-date">19.06.2025</div>
                                </div>
                                <p>Невероятная игра! Сюжет затягивает с первых минут, графика на высоте, а саундтрек просто бомбический. Потратил уже 10 часов и не могу оторваться.</p>
                            </div>

                            <div class="review-card">
                                <div class="review-header">
                                    <div class="review-author">
                                        <div class="author-avatar"></div>
                                        <div>
                                            <h3>CyberFan</h3>
                                            <div>★ 9</div>
                                        </div>
                                    </div>
                                    <div class="review-date">19.06.2025</div>
                                </div>
                                <p>Отличная визуальная новелла! Особенно понравилась механика с компьютером - очень необычное и интересное решение! Единственный минус - иногда встречаются баги, но разработчики обещают исправить в ближайшем обновлении.</p>
                            </div>
                        </div>
                    </div>

                    <div class="game-sidebar">
                        <div class="purchase-section">
                            <div class="game-price">349 ₽</div>
                            <button class="btn" style="width: 100%; margin-bottom: 15px;">Купить сейчас</button>
                            <button class="btn btn-secondary" style="width: 100%;">Добавить в корзину</button>
                            <div style="margin-top: 20px; font-size: 0.9rem; opacity: 0.8;">
                                <p>✔️ Есть в подписке</p>
                                <p>✔️ Высокий рейтинг</p>
                            </div>
                        </div>

                        <div class="developer-info">
                            <div class="developer-logo">🏢</div>
                            <div>
                                <h3>Dust Games Studio</h3>
                                <p>Основана в 2023 году</p>
                            </div>
                        </div>

                        <button class="btn btn-secondary" style="width: 100%; margin-bottom: 20px;" onclick="location.href='/d/dgscorp'">Все игры разработчика</button>

                        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px;">
                            <h3>Информация о игре</h3>
                            <div style="margin-top: 15px;">
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Жанры:</span>
                                    <span>Визуальная новелла</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Платформы:</span>
                                    <span>Windows, MacOS</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Языки:</span>
                                    <span>Русский</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                                    <span>Возрастной рейтинг:</span>
                                    <span>16+</span>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 30px; background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px;">
                            <h3>Достижения</h3>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px;">
                                <div style="text-align: center; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                                    <div style="font-size: 2rem;">🥇</div>
                                    <div style="font-size: 0.9rem;">Лучшая игра 2025</div>
                                </div>
                                <div style="text-align: center; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                                    <div style="font-size: 2rem;">🎮</div>
                                    <div style="font-size: 0.9rem;">Инновация года</div>
                                </div>
                                <div style="text-align: center; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                                    <div style="font-size: 2rem;">🎨</div>
                                    <div style="font-size: 0.9rem;">Лучший дизайн</div>
                                </div>
                                <div style="text-align: center; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                                    <div style="font-size: 2rem;">💡</div>
                                    <div style="font-size: 0.9rem;">Лучший сюжет</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация элементов при загрузке
            const animateElements = [
                ...document.querySelectorAll('.game-logo, .stat-item'),
                ...document.querySelectorAll('.screenshot'),
                ...document.querySelectorAll('.feature-item'),
                ...document.querySelectorAll('.review-card')
            ];

            animateElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `all 0.5s ease ${index * 0.1}s`;

                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100);
            });

            // Обработка кликов на скриншоты (можно добавить лайтбокс)
            const screenshots = document.querySelectorAll('.screenshot');
            screenshots.forEach(screenshot => {
                screenshot.addEventListener('click', function() {
                    alert('Просмотр скриншота в полном размере');
                });
            });
        });
    </script>
</body>

</html>