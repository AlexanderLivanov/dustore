<?php 
session_start(); 

require_once __DIR__ . '/swad/controllers/mobile_redirect.php';
mobile_redirect_if_needed(); 
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Игровая платформа для разработчиков и игроков</title>
    <link rel="manifest" crossorigin="use-credentials" href="manifest.json">
    <link rel="stylesheet" href="swad/css/pages.css">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">

    <?php require_once('swad/controllers/ymcounter.php'); ?>
</head>

<body>


    <?php require_once('swad/static/elements/header.php'); ?>
    <main>
        <section class="hero">
            <div class="hero-bg">

            </div>
            <div class="container">
                <div class="hero-content">
                    <h1 class="pixel-title">DUSTORE — свободная open-source платформа для игр и джемов</h1>
                    <p style="font-weight: 300; opacity: 0.8;">
    Мы верим, что творчество не зависит от того, где вы находитесь и кто вы есть. Здесь можно создавать, делиться и находить единомышленников.
</p>
                    <div class="hero-buttons">
                        <a href="/devs" class="btn">Хочу опубликовать свои игры!</a>
                        <a href="/explore" class="btn btn-secondary">Хочу играть в игры!</a>
                        <a href="https://t.me/dustore_devs" target="_blank" class="btn btn-secondary">Чатик для разработчиков</a>
                    </div>
                </div>
            </div>
        </section>

        <?php
        require_once('swad/config.php');
        $db = new Database();
        $conn = $db->connect();
        $sql = "SELECT
            (SELECT COUNT(*) FROM studios) AS count_user_organization,
            (SELECT COUNT(*) FROM users) AS count_users,
            (SELECT COUNT(*) FROM games) AS count_games,
            (SELECT COUNT(*) FROM games where status = 'published') AS published_games";

        $result = $conn->query($sql);
        $row = $result->fetchAll();

        $count_user_organization = $row[0]['count_user_organization'];
        $count_users = $row[0]['count_users'];
        $count_games = $row[0]['count_games'];
        $published_games = $row[0]['published_games'];
        ?>

        <section class="slider-section" style="padding: 0">
            <div class="slider-container">
                <div class="slider-track">
                    <div class="slider-slide" style="background-image: url('/swad/static/img/KNTR_X_DSTR2.jpg');">
                        <div class="slide-content">
                            <h2>Джем DUSTORE X К.О.Н.Т.У.Р</h2>
                            <p>Регистрация на джем с 20 июня до 5 июля, сроки джема - с 5 июля по 5 августа, оценивание и финал - с 15 августа по 15 сентября.</p>
                            <a href="https://t.me/+T5CajyXvgvpmMjRi" target="_blank" class="btn">Группа для участников джема</a>
                            <a href="https://dustore.ru/jams/vote" target="_blank" class="btn">Оценить игры</a>
                        </div>
                    </div>

                    <!-- <div class="slider-slide" style="background-image: url('https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=1351&q=80');">
                        <div class="slide-overlay"></div>
                        <div class="slide-content">
                            <h2>С 1 августа проходит первое бета-тестирование платформы</h2>
                            <p>Загрузите свои проекты до 3 сентября и получите уникальные бейджи!</p>
                            <a href="https://github.com/AlexanderLivanov/dustore-docs/wiki/Программа-Предварительной-Оценки" target="_blank" class="btn">Подробнее</a>
                        </div>
                    </div>

                    <div class="slider-slide" style="background-image: url('https://images.unsplash.com/photo-1552820728-8b83bb6b773f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');">
                        <div class="slide-overlay"></div>
                        <div class="slide-content">
                            <h2>Dustore Premium подписка</h2>
                            <p>Доступ ко всем играм по меньшей цене</p>
                            <a href="/finance" class="btn">Исследовать цены</a>
                        </div>
                    </div>

                    <div class="slider-slide" style="background-image: url('https://images.unsplash.com/photo-1542751110-97427bbecf20?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');">
                        <div class="slide-overlay"></div>
                        <div class="slide-content">
                            <h2>shaurMA - консоль для разработчиков</h2>
                            <p>Новые инструменты для управления играми и аналитики будут доступны всем разработчикам.</p>
                            <a href="/devs" class="btn">Начать разработку</a>
                        </div>
                    </div>
                </div> -->

                <div class="slider-arrows">
                    <!-- <div class="slider-arrow prev">❮</div>
                    <div class="slider-arrow next">❯</div> -->
                </div>

                <div class="slider-nav">
                    <div class="slider-dot active"></div>
                    <!-- <div class="slider-dot"></div>
                    <div class="slider-dot"></div>
                    <div class="slider-dot"></div> -->
                </div>
            </div>
        </section>

        <!-- Статистика -->
        <section class="stats">
            <div class="container">
                <h2>DUSTORE в цифрах</h2>
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-number"><?= $count_user_organization ?></div>
                        <div class="stat-label">Зарегистрированых студий</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $count_games ?></div>
                        <div class="stat-label">Всего игр</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $published_games ?></div>
                        <div class="stat-label">Опубликованных игр</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $count_users ?></div>
                        <div class="stat-label">Регистраций игроков</div>
                    </div>
                </div>
                <div class="hero-buttons">
                    <a href="/stat" class="btn">Подробная статистика</a>
                </div>
            </div>
        </section>


<<!-- Что это? -->
<section class="stats">
    <div class="container">
        <h2>Что входит в экосистему Dustore?</h2>
        <div class="platform-grid">
            <!-- ===== РАБОТАЮЩИЕ СЕРВИСЫ (кликабельны) ===== -->

            <!-- 1. Платформа DUSTORE.ru — ссылка на главную -->
            <div class="platform-card" onclick="location.href='/'">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                </div>
                <h3>Платформа DUSTORE.ru</h3>
                <p>Главный узел в экосистеме. Это центр, где связываются все части Платформы. Преимущественно здесь находится каталог игр.</p>
            </div>

            <!-- 2. Dustore.L4T — ссылка на /l4t -->
            <div class="platform-card" onclick="location.href='/l4t'">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="3" />
                        <circle cx="6" cy="18" r="3" />
                        <circle cx="18" cy="18" r="3" />
                        <line x1="8.21" y1="13.89" x2="10" y2="17" />
                        <line x1="15.79" y1="13.89" x2="14" y2="17" />
                    </svg>
                </div>
                <h3>Dustore.L4T</h3>
                <p>Looking For a Team — наше решение для поиска команд на джемы, партнёров в проекты и исполнителей для решения своих задач.</p>
            </div>

            <!-- 3. Dustore.Devs — ссылка на /devs -->
            <div class="platform-card" onclick="location.href='/devs'">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 18 22 12 16 6" />
                        <polyline points="8 6 2 12 8 18" />
                    </svg>
                </div>
                <h3>Dustore.Devs</h3>
                <p>Портал для разработчиков из студий. Публикация проектов, аналитика, монетизация.</p>
            </div>

            <!-- 4. Джемы — ссылка на /jams -->
            <div class="platform-card" onclick="location.href='/jams'">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="6" width="20" height="12" rx="2" />
                        <path d="M6 12h4" />
                        <path d="M15 12h3" />
                        <path d="M6 9h2" />
                        <path d="M17 9h1" />
                    </svg>
                </div>
                <h3>Джемы</h3>
                <p>Тут можно проводить джемы.</p>
            </div>

            <!-- 5. DustAsset — ссылка на /assetstore -->
            <div class="platform-card" onclick="location.href='/assetstore'">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v20" />
                        <path d="M2 12h20" />
                        <circle cx="12" cy="12" r="9" />
                    </svg>
                </div>
                <h3>DustAsset</h3>
                <p>Собственный ассетстор для разработчиков. Думаем, тут скоро каждый найдет что ищет.</p>
            </div>

            <!-- ===== СЕРВИСЫ В РАЗРАБОТКЕ (неактивны, с меткой) ===== -->

            <!-- 6. HidL — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z" />
                        <path d="M2 17l10 5 10-5" />
                        <path d="M2 12l10 5 10-5" />
                    </svg>
                </div>
                <h3>HidL</h3>
                <p>(в разработке) Собственный лаунчер, который вы даже не увидите. Можно будет связать со Steam, чтобы игры добавлялись в библиотеку Steam.</p>
            </div>

            <!-- 7. FinV2 — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v10" />
                        <path d="M9 10h3.5a1.5 1.5 0 0 1 0 3H10" />
                    </svg>
                </div>
                <h3>FinV2</h3>
                <p>Площадка для приёма платежей. Через неё разработчики монетизируют свои проекты, можно продавать свои ассеты, а также оплатить услуги исполнителя на L4T.</p>
            </div>

            <!-- 8. GDDB — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
                    </svg>
                </div>
                <h3>GDDB</h3>
                <p>(в разработке) Gamedev Database — единая база данных со всеми ресурсами по геймдеву. Каждый может добавить свою статью или ссылку.</p>
            </div>

            <!-- 9. Dustore.Media — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2" />
                        <path d="M8 12h8" />
                        <path d="M8 8h4" />
                        <path d="M8 16h6" />
                    </svg>
                </div>
                <h3>Dustore.Media</h3>
                <p>(в разработке) Свой информационный ресурс, который управляется пользователями. Здесь можно выложить анонс своей игры или рассказать о новостях в мире геймдева.</p>
            </div>

            <!-- 10. Dustore.GIB — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <line x1="2" y1="12" x2="22" y2="12" />
                        <path d="M12 3a15 15 0 0 0 0 18 15 15 0 0 0 0-18z" />
                    </svg>
                </div>
                <h3>Dustore.GIB</h3>
                <p>(в разработке) Games In Browser — помогаем портировать игры в браузере, чтобы игрокам не приходилось их скачивать. Что-то общее между WebGL и Instant Play.</p>
            </div>

            <!-- 11. "Битый Пиксель" — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        <path d="M9 9l6 6" />
                        <path d="M15 9l-6 6" />
                    </svg>
                </div>
                <h3>"Битый Пиксель"</h3>
                <p>(В разработке) Наш сервис для отправки отчётов об уязвимостях и багрепортов.</p>
            </div>

            <!-- 12. Dustore.Mobile — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
                        <line x1="12" y1="18" x2="12.01" y2="18" />
                    </svg>
                </div>
                <h3>Dustore.Mobile</h3>
                <p>Здесь можно выложить игру или приложение (в т.ч. платное) для мобильных устройств.</p>
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
                        <p>Чтобы играть в игры было выгодно&nbsp;- вы можете приобрести подписку. Подписка состоит из платных игр, выбранных нашей редакцией, а также из игр, за которые проголосовали большинство игроков</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🎮</div>
                        <h3>Эксклюзивные игры</h3>
                        <p>Доступ к уникальным проектам инди-разработчиков, которые вы не найдёте в других магазинах. Открывайте новые игровые миры первыми!</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">💰</div>
                        <h3>Лучшие цены</h3>
                        <p>Платформа берёт комиссию 0% за покупку игр. При этом вы получаете специальные предложения и скидки!</p>
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
                        <p>Комиссия платформы 0%. Вы получаете всю прибыль от каждой продажи.</p>
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
                        <p>Аналитика, продвижение, загрузка игр, управление сотрудниками в студии и многое другое в нашей системе мониторинга и управления shaurMA.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🆓</div>
                        <h3>Регистрация - бесплатно</h3>
                        <p>Вы можете зарегистрировать свою игру совершенно бесплатно</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🌐</div>
                        <h3>Стираем границы</h3>
                        <p>В будущем планируется выход на мировой рынок. Ваши игры смогут увидеть миллионы людей по всему миру!</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Зачем делать? -->
        <section class="hero">
            <div class="container">
                <h2>Зачем мы разрабатываем такую платформу?</h2>
                <h3>Вот несколько причин, почему мы взялись за такой проект:</h3>
                <div class="platform-grid">
                    <div class="platform-card">
                        <h3>Отсутствие монетизации</h3>
                        <p>Steam, Epic Games, Play Market, App Store, GOG - все эти платформы ушли из России, либо отключили монетизацию.
                            Мы хотим решить этот вопрос, так как сами являемся игровой студией.
                        </p>
                    </div>
                    <div class="platform-card">
                        <h3>Нет единого сообщества</h3>
                        <p>Да, есть куча каналов и пабликов в соцсетях, но мы хотим чего-то большего. Мы хотим сделать классное место, где захочется быть каждому.
                        </p>
                    </div>
                    <div class="platform-card">
                        <h3>Высокие комиссии</h3>
                        <p>Как для разработчиков, так и для игроков. Мы стремимся снизить нашу комиссию до нуля, причем предоставить больше возможностей. <u>Наше кредо: "сделай это доступным для всех, тогда это все будут покупать"</u>.
                        </p>
                    </div>
                    <div class="platform-card">
                        <h3>Желание создать "своё"</h3>
                        <p>Уже есть VK Play, но мы нацелены в первую очередь на инди-разработчиков и небольшие студии, так как им нужна наибольшая помощь.
                            Мы не пытаемся конкурировать с VK, так как у них попросту другая философия.
                        </p>
                    </div>
                    <div class="platform-card">
                        <h3>Это просто необходимо</h3>
                        <p>Лишний ресурс, где можно выложить свою игру не помешал бы, правда?
                        </p>
                    </div>
                    <div class="platform-card">
                        <h3>Демократия, прозрачность, гласность.</h3>
                        <p>Мы создаём сообщество, где каждый сможет проявить себя. А ещё, мы не ставим деньги выше честности.
                        </p>
                    </div>
                </div>
                <br>
                <br>
                <!-- <h3>А вот, какие фичи мы планируем внедрить:</h3>
                <div class="platform-grid">
                    <div class="platform-card" onclick="window.location.replace('https:/\/github.com/AlexanderLivanov/dustore-docs');" style="cursor: pointer;">
                        <h3>Полный список</h3>
                        <p>С полным списком фич вы можете ознакомиться на специальной странице...
                        </p>
                    </div>

                </div> -->
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
                        <p>Создайте бесплатный аккаунт игрока за секунду, авторизовавшись на Платформе...</p>
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
        // Анимация для слайдера
        document.addEventListener('DOMContentLoaded', function() {
            const sliderTrack = document.querySelector('.slider-track');
            const slides = document.querySelectorAll('.slider-slide');
            const dots = document.querySelectorAll('.slider-dot');
            const prevBtn = document.querySelector('.slider-arrow.prev');
            const nextBtn = document.querySelector('.slider-arrow.next');

            let currentIndex = 0;
            let slideCount = slides.length;
            let autoSlideInterval;

            function goToSlide(index) {
                if (index < 0) index = slideCount - 1;
                if (index >= slideCount) index = 0;
                sliderTrack.style.transform = `translateX(-${index * 100}%)`;
                currentIndex = index;
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
            }

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    goToSlide(index);
                    resetAutoSlide();
                });
            });

            prevBtn.addEventListener('click', () => {
                goToSlide(currentIndex - 1);
                resetAutoSlide();
            });

            nextBtn.addEventListener('click', () => {
                goToSlide(currentIndex + 1);
                resetAutoSlide();
            });

            function startAutoSlide() {
                autoSlideInterval = setInterval(() => {
                    goToSlide(currentIndex + 1);
                }, 5000);
            }

            function resetAutoSlide() {
                clearInterval(autoSlideInterval);
                startAutoSlide();
            }

            startAutoSlide();

            sliderTrack.addEventListener('mouseenter', () => {
                clearInterval(autoSlideInterval);
            });

            sliderTrack.addEventListener('mouseleave', () => {
                startAutoSlide();
            });
        });

        // Анимация для карточек платформы
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.platform-card').forEach(card => {
                observer.observe(card);
            });

            document.querySelectorAll('.step').forEach(step => {
                observer.observe(step);
            });
        });
    </script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => {
                    reg.onupdatefound = () => {
                        const installingWorker = reg.installing;
                        installingWorker.onstatechange = () => {
                            if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                console.log('New service worker version available.');
                            }
                        };
                    };
                })
                .catch(err => console.log('service worker not registered', err));
        }

        (function() {
            const hero = document.querySelector('.hero');
            if (!hero) return;
            const MAX_OFFSET = 300;
            function updateHeroBgOffset() {
                const scrollY = window.scrollY;
                let offset = Math.min(scrollY * 0.5, MAX_OFFSET);
                hero.style.setProperty('--hero-bg-offset', offset + 'px');
            }
            updateHeroBgOffset();
            let ticking = false;
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        updateHeroBgOffset();
                        ticking = false;
                    });
                    ticking = true;
                }
            });
        })();
    </script>


<script>
    (function() {
        let targetX = 0, targetY = 0;
        let currentX = 0, currentY = 0;
        let animationFrame = null;
        let isMoonlight = document.body.classList.contains('moonlight-theme');

        const MAX_OFFSET_X = 4;
        const MAX_OFFSET_Y = 3;

        function updateBackgroundPosition() {
            if (!isMoonlight) return;
            currentX += (targetX - currentX) * 0.1;
            currentY += (targetY - currentY) * 0.1;
            const posX = 50 + currentX;
            const posY = 35 + currentY;
            document.body.style.backgroundPosition = `${posX}% ${posY}%`;
            animationFrame = requestAnimationFrame(updateBackgroundPosition);
        }

        function onMouseMove(e) {
            if (!isMoonlight) return;
            const wx = window.innerWidth;
            const wy = window.innerHeight;
            const nx = (e.clientX / wx - 0.5) * 2;
            const ny = (e.clientY / wy - 0.5) * 2;
            targetX = nx * MAX_OFFSET_X;
            targetY = ny * MAX_OFFSET_Y;
        }

        const observer = new MutationObserver(() => {
            isMoonlight = document.body.classList.contains('moonlight-theme');
            if (!isMoonlight) {
                document.body.style.backgroundPosition = '';
                targetX = targetY = 0;
                currentX = currentY = 0;
            }
        });
        observer.observe(document.body, { attributes: true });

        window.addEventListener('mousemove', onMouseMove);
        updateBackgroundPosition();
    })();
</script>

<script>
/* ─────────────────────────────────────────────────────────────────────────────
   Наклон за курсором — общий модуль.

   Раньше этот блок ловил только `.btn`. Теперь он параметризован и покрывает
   ещё баннер голосования, кнопку внутри него и карточки сервисов, чтобы вся
   страница реагировала на мышь одинаково.

   Регистрация отложена до DOMContentLoaded: #vote-banner объявлен в разметке
   ниже этого скрипта, при немедленном запуске querySelectorAll его бы не нашёл.
   ───────────────────────────────────────────────────────────────────────────── */
(function () {
    // Только мышь. На тач-устройствах mousemove либо не приходит вовсе, либо
    // синтезируется при тапе — но без парного mouseleave, и элемент залипает
    // в наклоне навсегда.
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    var GROUPS = [
        /* селектор                                  угол  подъём  масштаб  перспектива */
        { sel: '.btn',                                a: 15, lift: -3,  s: 1.10, p: 400 },
        { sel: '.vote-btn',                           a: 15, lift: -3,  s: 1.10, p: 400 },
        { sel: '#vote-banner',                        a: 6,  lift: 0,   s: 1.00, p: 900 },
        { sel: '.platform-card:not(.in-development)', a: 8,  lift: -10, s: 1.02, p: 900 }
    ];

    // Баннер голосования можно таскать мышью, и драг считает позицию через
    // getBoundingClientRect(). Этот метод возвращает габариты УЖЕ
    // трансформированного элемента, поэтому с активным наклоном баннер прыгал бы
    // в момент захвата и неправильно прилипал к краю. Гасим наклон на время драга.
    var dragSuspended = false;

    function tilt(el, cfg, e) {
        // offsetWidth/offsetHeight — размеры ДО трансформации, они не «плывут»
        // от собственного scale. Исходный код нормировал по getBoundingClientRect(),
        // из-за чего наклон считался от уже увеличенного бокса и слегка
        // подрагивал. Центр берём из rect: transform-origin по умолчанию 50% 50%,
        // значит он смещается только на translateY, который мы и вычитаем.
        var hw = el.offsetWidth / 2;
        var hh = el.offsetHeight / 2;
        if (!hw || !hh) return;

        var r = el.getBoundingClientRect();
        var lift = el.style.transform ? cfg.lift : 0;
        var cx = r.left + r.width / 2;
        var cy = r.top + r.height / 2 - lift;

        var nx = Math.max(-1, Math.min(1, (e.clientX - cx) / hw));
        var ny = Math.max(-1, Math.min(1, (e.clientY - cy) / hh));

        el.style.transform =
            'perspective(' + cfg.p + 'px) ' +
            'rotateX(' + (-cfg.a * ny).toFixed(2) + 'deg) ' +
            'rotateY(' + (cfg.a * nx).toFixed(2) + 'deg) ' +
            'translateY(' + cfg.lift + 'px) ' +
            'scale(' + cfg.s + ')';
    }

    function bind(el, cfg) {
        if (el.dataset.tiltBound) return;   // защита от двойной привязки
        el.dataset.tiltBound = '1';

        var isBanner = el.id === 'vote-banner';

        el.addEventListener('mouseenter', function () {
            if (isBanner && dragSuspended) return;
            el.classList.add('is-tilting');
        });

        el.addEventListener('mousemove', function (e) {
            if (isBanner && dragSuspended) return;
            tilt(el, cfg, e);
        });

        el.addEventListener('mouseleave', function () {
            // Сначала снимаем is-tilting — возвращается «долгий» transition из CSS,
            // и только потом сбрасываем transform. Поэтому карточки уезжают назад
            // плавно (как и раньше, transform 0.3s ease), а кнопки — мгновенно.
            el.classList.remove('is-tilting');
            el.style.transform = '';
        });
    }

    function init() {
        GROUPS.forEach(function (cfg) {
            document.querySelectorAll(cfg.sel).forEach(function (el) { bind(el, cfg); });
        });

        var banner = document.getElementById('vote-banner');
        if (!banner) return;

        // capture-фаза на document: слушатель на предке в capture гарантированно
        // отрабатывает раньше слушателей самого баннера, независимо от порядка
        // регистрации. Успеваем сбросить transform до того, как драг прочитает rect.
        document.addEventListener('mousedown', function (e) {
            if (!e.target.closest || !e.target.closest('#vote-banner')) return;
            dragSuspended = true;
            banner.classList.remove('is-tilting');
            banner.style.transform = '';
        }, true);

        document.addEventListener('mouseup', function () { dragSuspended = false; });
        document.addEventListener('touchstart', function (e) {
            if (e.target.closest && e.target.closest('#vote-banner')) dragSuspended = true;
        }, { passive: true, capture: true });
        document.addEventListener('touchend', function () { dragSuspended = false; });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

<div id="vote-banner" data-collapsed="false">
    <button id="vote-toggle-btn" class="vote-toggle" aria-label="Свернуть">✕</button>
    <div class="vote-label">Идёт голосование:</div>
    <div class="vote-title pixel-title">Джем: DUSTORE X К.О.Н.Т.У.Р.</div>
    <img src="/swad/static/img/KNTR_X_DSTRmini.jpg" alt="Джем" class="vote-image" loading="lazy">
    <a href="/jams/vote" class="vote-btn pixel-title">Оценить билды</a>
</div>

<script>
(function() {
    const banner = document.getElementById('vote-banner');
    const toggleBtn = document.getElementById('vote-toggle-btn');
    if (!banner) return;

    const STORAGE_KEY = 'dustore_vote_banner_collapsed';

    const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
    if (isCollapsed) {
        banner.classList.add('collapsed');
        banner.dataset.collapsed = 'true';
    }

    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        collapseBanner();
    });

    banner.addEventListener('click', function() {
        if (banner.dataset.collapsed === 'true') {
            expandBanner();
        }
    });

    function collapseBanner() {
        banner.classList.add('collapsed');
        banner.dataset.collapsed = 'true';
        localStorage.setItem(STORAGE_KEY, 'true');
    }

    function expandBanner() {
        banner.classList.remove('collapsed');
        banner.dataset.collapsed = 'false';
        localStorage.setItem(STORAGE_KEY, 'false');
    }
})();
</script>

<script>
(function() {
    const banner = document.getElementById('vote-banner');
    if (!banner) return;

    let isDragging = false;
    let startX = 0, startY = 0;
    let startLeft = 0, startTop = 0;
    let currentLeft = 0, currentTop = 0;
    let hasSavedPosition = false;

    const STORAGE_KEY = 'dustore_vote_banner_position';
    const EDGE_OFFSET = 20;
    const DRAG_THRESHOLD = 5;

    function getHeaderHeight() {
        const header = document.querySelector('.header');
        if (header) return header.offsetHeight + 10;
        return 80;
    }

    function loadPosition() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const pos = JSON.parse(saved);
                if (pos.left !== undefined && pos.top !== undefined) {
                    currentLeft = pos.left;
                    currentTop = pos.top;
                    hasSavedPosition = true;
                    return true;
                }
            }
        } catch (e) {}
        return false;
    }

    function applyPosition(left, top, animate = true) {
        if (!animate) banner.style.transition = 'none';
        else banner.style.transition = 'left 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), top 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        banner.style.left = left + 'px';
        banner.style.top = top + 'px';
        banner.style.right = 'auto';
        banner.style.bottom = 'auto';
        if (!animate) {
            void banner.offsetWidth;
            banner.style.transition = '';
        }
        currentLeft = left;
        currentTop = top;
    }

    function initPosition() {
        const headerHeight = getHeaderHeight();
        if (loadPosition()) {
            if (currentTop < headerHeight + EDGE_OFFSET) {
                currentTop = headerHeight + EDGE_OFFSET;
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ left: currentLeft, top: currentTop }));
            }
            applyPosition(currentLeft, currentTop, false);
            return;
        }
        const rect = banner.getBoundingClientRect();
        const defaultLeft = window.innerWidth - rect.width - EDGE_OFFSET;
        const defaultTop = headerHeight + EDGE_OFFSET;
        currentLeft = defaultLeft;
        currentTop = defaultTop;
        applyPosition(defaultLeft, defaultTop, false);
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ left: currentLeft, top: currentTop }));
    }

    function snapToEdge() {
        const rect = banner.getBoundingClientRect();
        const winW = window.innerWidth;
        const winH = window.innerHeight;
        const bw = rect.width;
        const bh = rect.height;
        const headerHeight = getHeaderHeight();

        const cx = rect.left + bw / 2;
        const cy = rect.top + bh / 2;

        let targetLeft, targetTop;
        if (cx < winW / 2) targetLeft = EDGE_OFFSET;
        else targetLeft = winW - bw - EDGE_OFFSET;

        const topDist = cy - headerHeight;
        const bottomDist = winH - cy;
        if (topDist < bottomDist) targetTop = headerHeight + EDGE_OFFSET;
        else targetTop = winH - bh - EDGE_OFFSET;

        targetLeft = Math.max(EDGE_OFFSET, Math.min(targetLeft, winW - bw - EDGE_OFFSET));
        targetTop = Math.max(headerHeight + EDGE_OFFSET, Math.min(targetTop, winH - bh - EDGE_OFFSET));

        applyPosition(targetLeft, targetTop, true);
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ left: targetLeft, top: targetTop }));
    }

    function onPointerDown(e) {
        const target = e.target.closest('.vote-toggle, .vote-btn, a, button');
        if (target) return;
        e.preventDefault();

        const clientX = e.clientX || e.touches?.[0]?.clientX || 0;
        const clientY = e.clientY || e.touches?.[0]?.clientY || 0;
        const rect = banner.getBoundingClientRect();
        startX = clientX;
        startY = clientY;
        startLeft = rect.left;
        startTop = rect.top;

        let isDraggingNow = false;

        function onPointerMove(ev) {
            const cx = ev.clientX || ev.touches?.[0]?.clientX || 0;
            const cy = ev.clientY || ev.touches?.[0]?.clientY || 0;
            const dx = cx - startX;
            const dy = cy - startY;
            if (!isDraggingNow && (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD)) {
                isDraggingNow = true;
                banner.style.cursor = 'grabbing';
            }
            if (isDraggingNow) {
                ev.preventDefault();
                let newLeft = startLeft + dx;
                let newTop = startTop + dy;
                const headerHeight = getHeaderHeight();
                const maxL = window.innerWidth - banner.offsetWidth - EDGE_OFFSET;
                const maxT = window.innerHeight - banner.offsetHeight - EDGE_OFFSET;
                newLeft = Math.max(EDGE_OFFSET, Math.min(newLeft, maxL));
                newTop = Math.max(headerHeight + EDGE_OFFSET, Math.min(newTop, maxT));
                applyPosition(newLeft, newTop, false);
            }
        }

        function onPointerUp() {
            document.removeEventListener('mousemove', onPointerMove);
            document.removeEventListener('mouseup', onPointerUp);
            document.removeEventListener('touchmove', onPointerMove);
            document.removeEventListener('touchend', onPointerUp);
            if (isDraggingNow) {
                banner.style.cursor = '';
                snapToEdge();
            } else {
                const isCollapsed = banner.dataset.collapsed === 'true';
                if (isCollapsed) {
                    banner.classList.remove('collapsed');
                    banner.dataset.collapsed = 'false';
                    localStorage.setItem('dustore_vote_banner_collapsed', 'false');
                }
            }
            isDraggingNow = false;
        }

        document.addEventListener('mousemove', onPointerMove);
        document.addEventListener('mouseup', onPointerUp);
        document.addEventListener('touchmove', onPointerMove, { passive: false });
        document.addEventListener('touchend', onPointerUp);
    }

    function updateCursor() {
        const isCollapsed = banner.dataset.collapsed === 'true';
        banner.style.cursor = isCollapsed ? 'pointer' : 'grab';
    }

    const observer = new MutationObserver(() => { updateCursor(); });
    observer.observe(banner, { attributes: true, attributeFilter: ['data-collapsed'] });

    const collapsedState = localStorage.getItem('dustore_vote_banner_collapsed') === 'true';
    if (collapsedState) {
        banner.classList.add('collapsed');
        banner.dataset.collapsed = 'true';
    } else {
        banner.dataset.collapsed = 'false';
    }

    initPosition();
    updateCursor();

    banner.addEventListener('mousedown', onPointerDown);
    banner.addEventListener('touchstart', onPointerDown, { passive: false });

    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            const rect = banner.getBoundingClientRect();
            const winW = window.innerWidth;
            const winH = window.innerHeight;
            if (rect.left < 0 || rect.top < 0 || rect.right > winW || rect.bottom > winH) {
                setTimeout(snapToEdge, 100);
            }
        }, 300);
    });
})();
</script>

<!-- Приветственное окошко Дасти (показывается один раз за сессию) -->
<div id="dusty-greeting" class="dusty-greeting" style="display: none;">
    <button class="greeting-close" aria-label="Закрыть">✕</button>
    <div class="greeting-content">
        <img class="greeting-avatar" src="/swad/static/img/dastyframe1.png" alt="Дасти">
        <div class="greeting-text">
            <p>Привет! Я Дасти — твой проводник по Dustore.</p>
            <p class="greeting-hint">Нажми на лапку 🐾 в правом верхнем углу, если понадоблюсь.</p>
        </div>
    </div>
</div>

<div id="telegram-banner" class="telegram-banner" data-collapsed="false">
    <div class="tg-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-telegram"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M15 10l-4 4l6 6l4 -16l-18 7l4 2l2 6l3 -4" /></svg></div>
    <div class="tg-content">
        <div class="tg-text">
            Наш <a href="https://t.me/dustore_official" target="_blank">Telegram</a>
        </div>
        <button class="tg-toggle" aria-label="Свернуть">✕</button>
    </div>
</div>

<script>
/// Приветственное окошко Дасти на главной + сдвиг vote-banner
(function() {
    if (window.location.pathname !== '/') return;

    const popup = document.getElementById('dusty-greeting');
    const voteBanner = document.getElementById('vote-banner');
    if (!popup || !voteBanner) {
        console.warn('Окошко или баннер не найдены');
        return;
    }

    const SESSION_KEY = 'dusty_greeting_shown';
    if (sessionStorage.getItem(SESSION_KEY) === 'true') {
        popup.style.display = 'none';
        return;
    }

    function getCurrentTop(el) {
        const styleTop = el.style.top;
        if (styleTop && styleTop !== 'auto' && styleTop !== '') {
            return parseFloat(styleTop);
        }
        return el.getBoundingClientRect().top;
    }

    function isInTopRight(el) {
        const rect = el.getBoundingClientRect();
        const winW = window.innerWidth;
        const isRight = rect.left + rect.width/2 > winW * 0.55;
        const isTop = rect.top + rect.height/2 < window.innerHeight * 0.4;
        return isRight && isTop;
    }

    let originalTopVote = null;

    function shiftVoteDown() {
        if (!isInTopRight(voteBanner)) return;
        const currentTop = getCurrentTop(voteBanner);
        if (currentTop === null || isNaN(currentTop)) return;
        originalTopVote = currentTop;
        voteBanner.style.transition = 'top 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        voteBanner.style.top = (currentTop + 170) + 'px';
    }

    function shiftVoteUp() {
        if (originalTopVote === null) return;
        voteBanner.style.transition = 'top 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        voteBanner.style.top = originalTopVote + 'px';
        setTimeout(() => { voteBanner.style.transition = ''; }, 450);
        originalTopVote = null;
    }

    function showPopup() {
        popup.style.display = 'block';
        void popup.offsetWidth;
        popup.classList.add('show');

        shiftVoteDown();

        const autoCloseTimer = setTimeout(() => {
            closePopup();
        }, 7000);

        const closeBtn = popup.querySelector('.greeting-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                clearTimeout(autoCloseTimer);
                closePopup();
            });
        }

        function closePopup() {
            popup.classList.remove('show');
            shiftVoteUp();
            setTimeout(() => {
                popup.style.display = 'none';
            }, 450);
            sessionStorage.setItem(SESSION_KEY, 'true');
        }

        sessionStorage.setItem(SESSION_KEY, 'true');
    }

    setTimeout(showPopup, 500);
})();
</script>

<!-- Telegram-баннер (простой, в правом нижнем углу, сворачивание) -->
<script>
(function() {
    const banner = document.getElementById('telegram-banner');
    if (!banner) return;

    const STORAGE_KEY_COLL = 'dustore_tg_banner_collapsed';
    const toggleBtn = banner.querySelector('.tg-toggle');

    // Восстановление состояния свёрнутости
    const savedCollapsed = localStorage.getItem(STORAGE_KEY_COLL) === 'true';
    if (savedCollapsed) {
        banner.classList.add('collapsed');
        banner.dataset.collapsed = 'true';
    } else {
        banner.classList.remove('collapsed');
        banner.dataset.collapsed = 'false';
    }

    // Обработчик сворачивания/разворачивания
    function toggleBanner(e) {
        e.stopPropagation();
        const isCollapsed = banner.dataset.collapsed === 'true';
        if (isCollapsed) {
            banner.classList.remove('collapsed');
            banner.dataset.collapsed = 'false';
            localStorage.setItem(STORAGE_KEY_COLL, 'false');
        } else {
            banner.classList.add('collapsed');
            banner.dataset.collapsed = 'true';
            localStorage.setItem(STORAGE_KEY_COLL, 'true');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleBanner);
    }

    // Клик по самому баннеру (кроме крестика) — если свёрнут, разворачиваем
    banner.addEventListener('click', function(e) {
        if (e.target.closest('.tg-toggle')) return;
        const isCollapsed = banner.dataset.collapsed === 'true';
        if (isCollapsed) {
            banner.classList.remove('collapsed');
            banner.dataset.collapsed = 'false';
            localStorage.setItem(STORAGE_KEY_COLL, 'false');
        }
    });

    // Анимация разворачивания справа налево: задаём transform-origin
    banner.style.transformOrigin = 'right center';
})();
</script>

<!-- ============================================================
     БЫСТРЫЙ ДОСТУП (левый нижний угол) — показывается только при наличии пунктов
     ============================================================ -->
<?php
// ---- Проверяем, есть ли у пользователя студия (владелец) ----
$hasStudio = false;
if (!empty($_SESSION['USERDATA']['id'])) {
    $userId = (int)$_SESSION['USERDATA']['id'];
    try {
        $stmt = $conn->prepare("SELECT id FROM studios WHERE owner_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            $hasStudio = true;
        }
    } catch (Exception $e) {
        // Если таблицы нет или ошибка – просто игнорируем
    }
}

// В будущем сюда добавятся другие проверки:
// $hasMedia = ...;
// $hasSomethingElse = ...;

$hasAnyItem = $hasStudio; // || $hasMedia || $hasSomethingElse;
?>

<?php if ($hasAnyItem): ?>
<style>
/* =============================================================================
   БЫСТРЫЙ ДОСТУП (кнопка-домик внизу слева)
   -----------------------------------------------------------------------------
   Кнопка .quick-menu-toggle не тронута — её стили ниже слово в слово прежние.
   Переделана только панель и то, как она открывается.

   Что было сломано:
   1. .quick-access-container.open { transform: translateY(-100px) }
      поднимал ВЕСЬ контейнер вместе с кнопкой. Домик подпрыгивал на 100px
      при каждом открытии — это и выглядело криво. Подъём был лишним:
      контейнер прибит к bottom, панель и так растёт вверх.
   2. Раскрытие через max-height: 0 → 250px при реальной высоте контента
      ~70px. Видимый рост заканчивался за ~28% времени анимации, остальные
      72% ничего не происходило — на закрытии выходила заметная задержка
      перед тем, как панель вообще начнёт двигаться.

   Стало: панель абсолютно спозиционирована над кнопкой и вообще не влияет
   на размер контейнера — кнопка физически не может сдвинуться. Появление
   через opacity + transform, они анимируются на композиторе и от высоты
   контента не зависят.

   Палитра — те же переменные, что у модалки Дасти, чтобы всплывашки на
   сайте не расходились по цветам. Лунная тема переопределяет только цвета.
   ============================================================================= */

.quick-access-container {
    --qa-top:    #2c1240;
    --qa-bot:    #1f0a2b;
    --qa-line:   rgba(255, 255, 255, 0.13);
    --qa-text:   #f2e9f6;
    --qa-muted:  rgba(242, 233, 246, 0.45);
    --qa-accent: #c32178;
    --qa-hover:  rgba(195, 33, 120, 0.20);

    position: fixed;
    left: 20px;
    bottom: 20px;
    z-index: 10000;
    width: 48px;   /* по кнопке: панель висит абсолютом и ширину не задаёт */
}

body.moonlight-theme .quick-access-container {
    --qa-top:    #182238;
    --qa-bot:    #101829;
    --qa-line:   rgba(184, 200, 255, 0.17);
    --qa-text:   #eaf0ff;
    --qa-muted:  rgba(184, 200, 255, 0.48);
    --qa-accent: #3e7ad9;
    --qa-hover:  rgba(62, 122, 217, 0.24);
}

/* ── Панель: позиционирование, тень, появление ──────────────────────────── */
.quick-menu-panel {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 0;
    width: max-content;
    min-width: 214px;
    max-width: 280px;

    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateY(6px);
    transform-origin: 0 100%;

    /* clip-path режет box-shadow, поэтому тень живёт на обёртке через
       drop-shadow — он повторяет ступенчатый контур, а не прямоугольник. */
    filter: drop-shadow(0 6px 18px rgba(0, 0, 0, 0.55));

    transition: opacity 0.16s ease,
                transform 0.16s ease,
                visibility 0s linear 0.16s;
}

.quick-access-container.open .quick-menu-panel {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: none;
    transition: opacity 0.16s ease,
                transform 0.16s ease,
                visibility 0s;
}

/* ── Карточка: пиксельная лесенка по углам + кант в 1px ─────────────────── */
/* Фон карточки виден только как кант: ::before лежит с отступом 1px и
   повторяет тот же полигон, поэтому обводка ровно 1px по всему контуру,
   включая ступеньки. box-shadow с clip-path так не умеет. */
.quick-menu-card {
    position: relative;
    padding: 7px;
    background: var(--qa-line);
    clip-path: polygon(
        0 8px, 4px 8px, 4px 4px, 8px 4px, 8px 0, calc(100% - 8px) 0, calc(100% - 8px) 4px,
        calc(100% - 4px) 4px, calc(100% - 4px) 8px, 100% 8px, 100% calc(100% - 8px),
        calc(100% - 4px) calc(100% - 8px), calc(100% - 4px) calc(100% - 4px),
        calc(100% - 8px) calc(100% - 4px), calc(100% - 8px) 100%, 8px 100%, 8px calc(100% - 4px),
        4px calc(100% - 4px), 4px calc(100% - 8px), 0 calc(100% - 8px)
    );
}

.quick-menu-card::before {
    content: "";
    position: absolute;
    inset: 1px;
    background: linear-gradient(180deg, var(--qa-top), var(--qa-bot));
    clip-path: polygon(
        0 8px, 4px 8px, 4px 4px, 8px 4px, 8px 0, calc(100% - 8px) 0, calc(100% - 8px) 4px,
        calc(100% - 4px) 4px, calc(100% - 4px) 8px, 100% 8px, 100% calc(100% - 8px),
        calc(100% - 4px) calc(100% - 8px), calc(100% - 4px) calc(100% - 4px),
        calc(100% - 8px) calc(100% - 4px), calc(100% - 8px) 100%, 8px 100%, 8px calc(100% - 4px),
        4px calc(100% - 4px), 4px calc(100% - 8px), 0 calc(100% - 8px)
    );
}

/* ── Заголовок ──────────────────────────────────────────────────────────── */
.quick-menu-title {
    position: relative;
    z-index: 1;
    display: block;
    padding: 4px 8px 8px;
    font-family: 'Bahnschrift Light', system-ui, sans-serif;
    font-size: 10px;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--qa-muted);
}

/* ── Пункты меню ────────────────────────────────────────────────────────── */
.quick-menu-item {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    color: var(--qa-text);
    text-decoration: none;
    font-family: 'Bahnschrift Light', system-ui, sans-serif;
    font-size: 14px;
    line-height: 1.25;
    border: 1px solid transparent;
    transition: background 0.14s ease, border-color 0.14s ease;
}

.quick-menu-item:hover,
.quick-menu-item:focus-visible {
    background: var(--qa-hover);
    border-color: var(--qa-accent);
    outline: none;
}

/* Иконка — inline-SVG в одну заливку вместо эмодзи: эмодзи рисуется
   цветным глифом системы и не совпадает по весу с домиком на кнопке. */
.quick-menu-icon {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    display: block;
    fill: var(--qa-muted);
    transition: fill 0.14s ease;
}

.quick-menu-item:hover .quick-menu-icon,
.quick-menu-item:focus-visible .quick-menu-icon {
    fill: var(--qa-accent);
}

/* ── Кнопка-домик: без изменений ────────────────────────────────────────── */
.quick-menu-toggle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.4);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s, transform 0.1s;
    flex-shrink: 0;
}

.quick-menu-toggle:hover {
    background: #e62e8a;
    transform: scale(1.05);
}

.quick-menu-toggle:active {
    transform: scale(0.95);
}

.quick-menu-toggle svg {
    fill: white;
    width: 28px;
    height: 28px;
}

body.moonlight-theme .quick-menu-toggle {
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.06);
}

body.moonlight-theme .quick-menu-toggle:hover {
    background: #5690f069;
}

@media (max-width: 600px) {
    .quick-access-container {
        left: 14px;
        bottom: 14px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .quick-menu-panel {
        transition: opacity 0.01s linear, visibility 0s linear 0.01s;
        transform: none;
    }
    .quick-access-container.open .quick-menu-panel {
        transition: opacity 0.01s linear, visibility 0s;
    }
}
</style>

<div class="quick-access-container" id="quickAccess">
    <div class="quick-menu-panel" id="quickPanel">
        <div class="quick-menu-card">
            <span class="quick-menu-title">Быстрый доступ</span>

            <?php if ($hasStudio): ?>
                <a href="/devs/" class="quick-menu-item">
                    <svg class="quick-menu-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 4h18v16H3V4zm2 2v12h14V6H5z"/>
                        <path d="M7 9l3 3-3 3V9zm5 5h5v2h-5v-2z"/>
                    </svg>
                    Консоль разработчика
                </a>
            <?php endif; ?>
            <!-- Здесь позже добавятся другие пункты (медиа, студия и т.д.) -->
        </div>
    </div>

    <button class="quick-menu-toggle" id="quickToggle" aria-label="Быстрый доступ"
            aria-expanded="false" aria-controls="quickPanel">
        <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 3L2 12h3v8h6v-6h2v6h6v-8h3L12 3z"/>
        </svg>
    </button>
</div>

<script>
(function() {
    const container = document.getElementById('quickAccess');
    const toggle = document.getElementById('quickToggle');
    if (!container || !toggle) return;

    let isOpen = false;

    function setOpen(state) {
        isOpen = state;
        container.classList.toggle('open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        setOpen(!isOpen);
    });

    // Закрытие при клике вне меню и кнопки
    document.addEventListener('click', function(e) {
        if (isOpen && !container.contains(e.target)) setOpen(false);
    });

    // Закрытие по Escape — фокус возвращаем на кнопку, иначе он повисает
    // на скрытой ссылке внутри панели
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isOpen) {
            setOpen(false);
            toggle.focus();
        }
    });
})();
</script>
<?php endif; ?>
<!-- ============================================================ -->

</body>

</html>