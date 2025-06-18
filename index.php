<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Игровая платформа для разработчиков и игроков</title>
    <link rel="manifest" crossorigin="use-credentials" href="manifest.json">
    <link rel="stylesheet" href="swad/css/pages.css">

    <?php require_once('swad/controllers/ymcounter.php'); ?>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>
    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>DUSTORE — ваша игровая вселенная</h1>
                    <p>Платформа, где разработчики создают, а игроки открывают для себя уникальные игровые миры. Присоединяйтесь к сообществу инноваторов и геймеров!</p>
                    <div class="hero-buttons">
                        <a href="/devs" class="btn">Хочу опубликовать свои игры!</a>
                        <a href="/games" class="btn btn-secondary">Хочу играть в игры!</a>
                    </div>
                    <div class="hero-image floating">
                        <svg viewBox="0 0 800 400" xmlns="http://www.w3.org/2000/svg">
                            <!-- Основной контейнер магазина -->
                            <rect x="50" y="50" width="700" height="300" rx="20" fill="#1e0a28" stroke="#c32178" stroke-width="3" />

                            <!-- Шапка магазина -->
                            <rect x="60" y="60" width="680" height="50" rx="10" fill="#0f0316" />
                            <text x="80" y="90" font-family="Arial" font-size="20" fill="white" font-weight="bold">DUSTORE - ИГРОВАЯ ПЛАТФОРМА</text>

                            <!-- Панель поиска и фильтров -->
                            <rect x="80" y="120" width="500" height="40" rx="20" fill="#400c4a" />
                            <text x="100" y="145" font-family="Arial" font-size="16" fill="#aaaaaa">Поиск игр...</text>
                            <rect x="600" y="120" width="120" height="40" rx="20" fill="#74155d" />
                            <text x="620" y="145" font-family="Arial" font-size="16" fill="white">Фильтры</text>

                            <!-- Категории -->
                            <rect x="80" y="180" width="640" height="30" fill="transparent" />
                            <rect x="80" y="180" width="100" height="30" rx="15" fill="#c32178" />
                            <text x="130" y="200" font-family="Arial" font-size="14" fill="white" text-anchor="middle">Все</text>

                            <rect x="190" y="180" width="100" height="30" rx="15" fill="#400c4a" />
                            <text x="240" y="200" font-family="Arial" font-size="14" fill="#aaaaaa" text-anchor="middle">Экшен</text>

                            <rect x="300" y="180" width="100" height="30" rx="15" fill="#400c4a" />
                            <text x="350" y="200" font-family="Arial" font-size="14" fill="#aaaaaa" text-anchor="middle">RPG</text>

                            <rect x="410" y="180" width="100" height="30" rx="15" fill="#400c4a" />
                            <text x="460" y="200" font-family="Arial" font-size="14" fill="#aaaaaa" text-anchor="middle">Стратегии</text>

                            <rect x="520" y="180" width="100" height="30" rx="15" fill="#400c4a" />
                            <text x="570" y="200" font-family="Arial" font-size="14" fill="#aaaaaa" text-anchor="middle">Инди</text>

                            <!-- Карточки игр -->
                            <!-- Игра 1 -->
                            <g transform="translate(80, 220)">
                                <rect x="0" y="0" width="150" height="100" rx="10" fill="#400c4a" />
                                <rect x="10" y="10" width="130" height="50" rx="5" fill="#74155d" />
                                <text x="75" y="75" font-family="Arial" font-size="12" fill="white" text-anchor="middle">Super Game</text>
                                <rect x="10" y="85" width="50" height="20" rx="3" fill="#0f0316" />
                                <text x="35" y="100" font-family="Arial" font-size="12" fill="#c32178" text-anchor="middle">★ 4.7</text>
                                <rect x="110" y="85" width="30" height="20" rx="3" fill="#c32178" />
                                <text x="125" y="100" font-family="Arial" font-size="12" fill="white" text-anchor="middle">149 ₽</text>
                            </g>

                            <!-- Игра 2 -->
                            <g transform="translate(240, 220)">
                                <rect x="0" y="0" width="150" height="100" rx="10" fill="#400c4a" />
                                <rect x="10" y="10" width="130" height="50" rx="5" fill="#74155d" />
                                <text x="75" y="75" font-family="Arial" font-size="12" fill="white" text-anchor="middle">Great Game</text>
                                <rect x="10" y="85" width="50" height="20" rx="3" fill="#0f0316" />
                                <text x="35" y="100" font-family="Arial" font-size="12" fill="#c32178" text-anchor="middle">★ 4.9</text>
                                <rect x="110" y="85" width="30" height="20" rx="3" fill="#c32178" />
                                <text x="125" y="100" font-family="Arial" font-size="12" fill="white" text-anchor="middle">349 ₽</text>
                            </g>

                            <!-- Игра 3 -->
                            <g transform="translate(400, 220)">
                                <rect x="0" y="0" width="150" height="100" rx="10" fill="#400c4a" />
                                <rect x="10" y="10" width="130" height="50" rx="5" fill="#74155d" />
                                <text x="75" y="75" font-family="Arial" font-size="12" fill="white" text-anchor="middle">Mega Game</text>
                                <rect x="10" y="85" width="50" height="20" rx="3" fill="#0f0316" />
                                <text x="35" y="100" font-family="Arial" font-size="12" fill="#c32178" text-anchor="middle">★ 4.5</text>
                                <rect x="110" y="85" width="30" height="20" rx="3" fill="#c32178" />
                                <text x="125" y="100" font-family="Arial" font-size="12" fill="white" text-anchor="middle">49 ₽</text>
                            </g>

                            <!-- Игра 4 -->
                            <g transform="translate(560, 220)">
                                <rect x="0" y="0" width="150" height="100" rx="10" fill="#400c4a" />
                                <rect x="10" y="10" width="130" height="50" rx="5" fill="#74155d" />
                                <text x="75" y="75" font-family="Arial" font-size="12" fill="white" text-anchor="middle">New Game</text>
                                <rect x="10" y="85" width="50" height="20" rx="3" fill="#0f0316" />
                                <text x="35" y="100" font-family="Arial" font-size="12" fill="#c32178" text-anchor="middle">★ 4.8</text>
                                <rect x="110" y="85" width="30" height="20" rx="3" fill="#c32178" />
                                <text x="125" y="100" font-family="Arial" font-size="12" fill="white" text-anchor="middle">109 ₽</text>
                            </g>

                            <!-- Иконка корзины -->
                            <g transform="translate(700, 75)">
                                <rect x="0" y="0" width="25" height="25" rx="5" fill="transparent" stroke="#c32178" stroke-width="2" />
                                <circle cx="12.5" cy="10" r="2" fill="#c32178" />
                                <circle cx="5" cy="20" r="1" fill="#c32178" />
                                <circle cx="12.5" cy="20" r="1" fill="#c32178" />
                                <circle cx="20" cy="20" r="1" fill="#c32178" />
                            </g>

                            <!-- Иконка пользователя -->
                            <g transform="translate(650, 75)">
                                <circle cx="12.5" cy="10" r="7" fill="transparent" stroke="#c32178" stroke-width="2" />
                                <rect x="5" y="20" width="15" height="10" rx="2" fill="transparent" stroke="#c32178" stroke-width="2" />
                            </g>

                            <!-- Иконка избранного -->
                            <g transform="translate(600, 75)">
                                <path d="M12.5,5 L15,10 L20,11 L16,15 L17,20 L12.5,17.5 L8,20 L9,15 L5,11 L10,10 Z"
                                    fill="transparent" stroke="#c32178" stroke-width="2" />
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        <!-- О платформе -->
        <section class="platform">
            <div class="container">
                <h2>Перспективы Dustore:</h2>
                <h3>Для игроков ⬇</h3>
                <div class="platform-grid">
                    <div class="platform-card">
                        <div class="platform-icon">💌</div>
                        <h3>Система подписок</h3>
                        <p>Чтобы играть в игры было выгодно&nbsp;- вы можете приобрести подписку. Алгоритмы составят список игр, которые вам интересны и включат в вашу подписку.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🎮</div>
                        <h3>Эксклюзивные игры</h3>
                        <p>Доступ к уникальным проектам инди-разработчиков, которые вы не найдёте в других магазинах. Открывайте новые игровые миры первыми!</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">💰</div>
                        <h3>Лучшие цены</h3>
                        <p>Платформа не берёт комиссию за покупку игр*. При этом вы получаете специальные предложения и скидки!</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">⏳</div>
                        <h3>Ранний доступ</h3>
                        <p>Станьте бета-тестером и играйте в новые проекты до официального релиза. Влияйте на развитие игр и получайте награды.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">👥</div>
                        <h3>Прямая связь с разработчиками</h3>
                        <p>Общайтесь напрямую с создателями игр, предлагайте идеи и участвуйте в формировании контента. Ваше мнение действительно важно!</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🏆</div>
                        <h3>Система достижений</h3>
                        <p>Зарабатывайте уникальные значки и награды, повышайте свой статус в сообществе и получайте специальные привилегии за активность.</p>
                    </div>
                </div>
                <br>
                <br>
                <br>
                <br>
                <h3>Для разработчиков ⬇</h3>
                <div class="platform-grid">
                    <div class="platform-card">
                        <div class="platform-icon">💸</div>
                        <h3>Выгодные условия монетизации</h3>
                        <p>Комиссия платформы всего 15% против стандартных 30% на других площадках. Получайте больше прибыли от каждой продажи.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🔁</div>
                        <h3>Прямой контакт с аудиторией</h3>
                        <p>Общайтесь напрямую с игроками, получайте фидбек и создавайте игры, которые по-настоящему любят.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">📢</div>
                        <h3>Продвижение игр</h3>
                        <p>Используйте наши инструменты продвижения, участвуйте в специальных акциях и получайте больше продаж.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🛠️</div>
                        <h3>Панель управления</h3>
                        <p>Аналитика, продвижение, загрузка игр, управление сотрудниками в студии и многое другое в нашей системе мониторинга и управления HuRMa.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🆓</div>
                        <h3>Первая игра - бесплатно</h3>
                        <p>Вы можете Зарегистрировать первую игру совершенно бесплатно&nbsp;- так вы "проверите" свой проект. Регистрация каждой следующей игры - 1000 ₽**</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🌐</div>
                        <h3>Стираем границы</h3>
                        <p>В будущем планируется выход на мировой рынок. Ваши игры смогут увидеть миллионы людей по всему миру!</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Статистика -->
        <section class="stats">
            <div class="container">
                <h2>DUSTORE в цифрах</h2>
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-number">*СКОРО*</div>
                        <div class="stat-label">Активных разработчиков</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">*СКОРО*</div>
                        <div class="stat-label">Опубликованных игр</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">*СКОРО*</div>
                        <div class="stat-label">Регистраций игроков</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">*СКОРО*</div>
                        <div class="stat-label">Средняя цена подписки</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Как это работает -->
        <section class="how-it-works">
            <div class="container">
                <h2>Как присоединиться? Просто, как 2x2</h2>
                <h3>Если вы игрок ⬇</h3>
                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3>Регистрация</h3>
                        <p>Создайте бесплатный аккаунт игрока за секунду, авторизовавшись через Telegram...</p>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3>Изучение</h3>
                        <p>...Затем загляните на страницу игр и исследуйте каталог...</p>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3>Взаимодействие</h3>
                        <p>...Где вы можете выбрать и купить/скачать игру...</p>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <h3>Развитие</h3>
                        <p>...Чтобы потом оставить отзыв, получить опыт и награды!</p>
                    </div>
                </div>
                <br>
                <br>
                <br>
                <br>
                <h3>Если вы разработчик ⬇</h3>
                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3>Регистрация</h3>
                        <p>Создайте бесплатный аккаунт разработчика и зарегистрируйте свою студию в консоли...</p>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3>Создание</h3>
                        <p>...Где вы можете создать проект игры, загрузить файлы...</p>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3>Управление</h3>
                        <p>...При этом вы можете распределять задачи между своими сотрудниками...</p>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <h3>Публикация</h3>
                        <p>...Чтобы потом опубликовать игру, которую увидят все!</p>
                    </div>
                </div>
            </div>
        </section>
        <section class="cta">
            <div class="container">
                <h2>Готовы начать своё игровое приключение?</h2>
                <p>Присоединяйтесь к DUSTORE сегодня и помогите нам совершить революцию в игровой индустрии!</p>
                <a href="/login" class="btn">Я ГОТОВ!</a>
            </div>
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        // Анимация для карточек платформы
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация при прокрутке
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate');
                    }
                });
            }, {
                threshold: 0.1
            });

            // Наблюдаем за карточками платформы
            document.querySelectorAll('.platform-card').forEach(card => {
                observer.observe(card);
            });

            // Наблюдаем за шагами
            document.querySelectorAll('.step').forEach(step => {
                observer.observe(step);
            });
        });
    </script>
    <script>
        if ('serviceWorker' in navigator) {
            // регистрация сервис-воркера 
            navigator.serviceWorker.register('/sw.js')
                .then(reg => {
                    reg.onupdatefound = () => {
                        const installingWorker = reg.installing;

                        installingWorker.onstatechange = () => {
                            if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // Новая версия сервис-воркера доступна
                                console.log('New service worker version available.');

                                // Опционально: показать уведомление пользователю
                                showUpdateNotification();
                            }
                        };
                    };
                })
                .catch(err => console.log('service worker not registered', err));
        }
    </script>
</body>

</html>