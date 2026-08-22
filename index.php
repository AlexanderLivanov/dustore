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
                    <h1 class="pixel-title">DUSTORE — открытая open-source платформа для игр и джемов</h1>
                    <p style="font-weight: 300; opacity: 0.8;">
    Мы верим, что творчество не зависит от того, где вы находитесь и кто вы есть. Здесь можно создавать, делиться и находить единомышленников.
</p>
                    <div class="hero-buttons">
                        <a href="/devs" class="btn">Хочу опубликовать свои игры!</a>
                        <a href="/explore" class="btn btn-secondary">Хочу играть в игры!</a>
                        <a href="https://t.me/dustore_devs" target="_blank" class="btn btn-secondary">Чатик для разработчиков</a>
                        <a href="https://t.me/dustore_official" target="_blank" class="btn btn-tg">
                            <svg style="vertical-align: middle;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-brand-telegram"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M15 10l-4 4l6 6l4 -16l-18 7l4 2l2 6l3 -4" /></svg>
                            Telegram-канал Платформы</a>
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


<!-- Что это? -->
<section class="stats">
    <div class="container">
        <h2>Что входит в экосистему Dustore?</h2>
        <div class="platform-grid">
            <!-- ===== РАБОТАЮЩИЕ СЕРВИСЫ (кликабельны) ===== -->

            <!-- 1. Платформа DUSTORE.ru — ссылка на главную -->
            <div class="platform-card" onclick="location.href='/'">
                <div class="platform-icon">💼</div>
                <h3>Платформа DUSTORE.ru</h3>
                <p>Главный узел в экосистеме. Это центр, где связываются все части Платформы. Преимущественно здесь находится каталог игр.</p>
            </div>

            <!-- 2. Dustore.L4T — ссылка на /l4t -->
            <div class="platform-card" onclick="location.href='/l4t'">
                <div class="platform-icon">🍀</div>
                <h3>Dustore.L4T</h3>
                <p>Looking For a Team — наше решение для поиска команд на джемы, партнёров в проекты и исполнителей для решения своих задач.</p>
            </div>

            <!-- 3. Dustore.Devs — ссылка на /devs -->
            <div class="platform-card" onclick="location.href='/devs'">
                <div class="platform-icon">👩‍💻</div>
                <h3>Dustore.Devs</h3>
                <p>Портал для разработчиков из студий. Публикация проектов, аналитика, монетизация.</p>
            </div>

            <!-- 4. Джемы — ссылка на /jams -->
            <div class="platform-card" onclick="location.href='/jams'">
                <div class="platform-icon">🕹</div>
                <h3>Джемы</h3>
                <p>Тут можно проводить джемы.</p>
            </div>

            <!-- 5. DustAsset — ссылка на /assetstore -->
            <div class="platform-card" onclick="location.href='/assetstore'">
                <div class="platform-icon">🛠</div>
                <h3>DustAsset</h3>
                <p>Собственный ассетстор для разработчиков. Думаем, тут скоро каждый найдет что ищет.</p>
            </div>

            <!-- ===== СЕРВИСЫ В РАЗРАБОТКЕ (неактивны, с меткой) ===== -->

            <!-- 6. HidL — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">🚀</div>
                <h3>HidL</h3>
                <p>(в разработке) Собственный лаунчер, который вы даже не увидите. Можно будет связать со Steam, чтобы игры добавлялись в библиотеку Steam.</p>
            </div>

            <!-- 7. FinV2 — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">💵</div>
                <h3>FinV2</h3>
                <p>Площадка для приёма платежей. Через неё разработчики монетизируют свои проекты, можно продавать свои ассеты, а также оплатить услуги исполнителя на L4T.</p>
            </div>

            <!-- 8. GDDB — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">👨‍🎓</div>
                <h3>GDDB</h3>
                <p>(в разработке) Gamedev Database — единая база данных со всеми ресурсами по геймдеву. Каждый может добавить свою статью или ссылку.</p>
            </div>

            <!-- 9. Dustore.Media — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">📸</div>
                <h3>Dustore.Media</h3>
                <p>(в разработке) Свой информационный ресурс, который управляется пользователями. Здесь можно выложить анонс своей игры или рассказать о новостях в мире геймдева.</p>
            </div>

            <!-- 10. Dustore.GIB — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">🌐</div>
                <h3>Dustore.GIB</h3>
                <p>(в разработке) Games In Browser — помогаем портировать игры в браузере, чтобы игрокам не приходилось их скачивать. Что-то общее между WebGL и Instant Play.</p>
            </div>

            <!-- 11. "Битый Пиксель" — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">💔</div>
                <h3>"Битый Пиксель"</h3>
                <p>(В разработке) Наш сервис для отправки отчётов об уязвимостях и багрепортов.</p>
            </div>

            <!-- 12. Dustore.Mobile — в разработке -->
            <div class="platform-card in-development">
                <div class="platform-icon">Ⓜ</div>
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

            // Функция для переключения слайдов
            function goToSlide(index) {
                if (index < 0) index = slideCount - 1;
                if (index >= slideCount) index = 0;

                sliderTrack.style.transform = `translateX(-${index * 100}%)`;
                currentIndex = index;

                // Обновление активной точки
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
            }

            // Переключение по точкам
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    goToSlide(index);
                    resetAutoSlide();
                });
            });

            // Кнопки навигации
            prevBtn.addEventListener('click', () => {
                goToSlide(currentIndex - 1);
                resetAutoSlide();
            });

            nextBtn.addEventListener('click', () => {
                goToSlide(currentIndex + 1);
                resetAutoSlide();
            });

            // Автоматическое переключение слайдов
            function startAutoSlide() {
                autoSlideInterval = setInterval(() => {
                    goToSlide(currentIndex + 1);
                }, 5000); // Меняем слайд каждые 5 секунд
            }

            function resetAutoSlide() {
                clearInterval(autoSlideInterval);
                startAutoSlide();
            }

            // Запуск автоматического слайдера
            startAutoSlide();

            // Остановка автоматического переключения при наведении
            sliderTrack.addEventListener('mouseenter', () => {
                clearInterval(autoSlideInterval);
            });

            sliderTrack.addEventListener('mouseleave', () => {
                startAutoSlide();
            });
        });

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

        (function() {
            const hero = document.querySelector('.hero');
            if (!hero) return;

            // Максимальное смещение в пикселях (вправо)
            const MAX_OFFSET = 300;

            function updateHeroBgOffset() {
                const scrollY = window.scrollY;
                // Рассчитываем смещение: чем больше скролл, тем больше offset (но не больше MAX_OFFSET)
                let offset = Math.min(scrollY * 0.5, MAX_OFFSET);
                hero.style.setProperty('--hero-bg-offset', offset + 'px');
            }

            // Запускаем при загрузке
            updateHeroBgOffset();

            // Оптимизированный обработчик скролла с requestAnimationFrame
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
            const posY = 35 + currentY;   // 35% — твой сдвиг вверх
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
// Эффект наклона и масштаба для всех кнопок .btn на странице (как в хедере)
(function() {
    const btns = document.querySelectorAll('.btn');
    if (!btns.length) return;

    function resetTilt(btn) {
        btn.style.transform = '';
    }

    function handleMouseMove(e) {
        const btn = e.currentTarget;
        const rect = btn.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        const nx = (x / rect.width) * 2 - 1;
        const ny = (y / rect.height) * 2 - 1;

        const maxAngle = 15;          // максимальный угол наклона
        const rotateY = maxAngle * nx;
        const rotateX = -maxAngle * ny;

        const translateY = -3;        // подъём вверх при наведении
        const scale = 1.1;           // небольшое увеличение

        btn.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
    }

    function handleMouseLeave(e) {
        resetTilt(e.currentTarget);
    }

    btns.forEach(btn => {
        btn.addEventListener('mousemove', handleMouseMove);
        btn.addEventListener('mouseleave', handleMouseLeave);
    });
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

    // ── состояние ──
    let isDragging = false;
    let startX = 0, startY = 0;
    let startLeft = 0, startTop = 0;
    let currentLeft = 0, currentTop = 0;
    let hasSavedPosition = false;
    let isCollapsed = false;

    const STORAGE_KEY = 'dustore_vote_banner_position';
    const EDGE_OFFSET = 20;
    const DRAG_THRESHOLD = 5;

    // ── определяем высоту хедера ──
    function getHeaderHeight() {
        const header = document.querySelector('.header');
        if (header) {
            return header.offsetHeight + 10; // +10px дополнительный отступ
        }
        return 80; // запасное значение
    }

    // ── загружаем сохранённую позицию ──
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

    // ── применяем позицию к баннеру ──
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

    // ── инициализация позиции ──
    function initPosition() {
        const headerHeight = getHeaderHeight();
        if (loadPosition()) {
            // Проверяем, не перекрывает ли баннер хедер (особенно если верхняя позиция)
            if (currentTop < headerHeight + EDGE_OFFSET) {
                currentTop = headerHeight + EDGE_OFFSET;
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ left: currentLeft, top: currentTop }));
            }
            applyPosition(currentLeft, currentTop, false);
            return;
        }

        // Позиция по умолчанию: правый верхний угол с учётом хедера
        const rect = banner.getBoundingClientRect();
        const defaultLeft = window.innerWidth - rect.width - EDGE_OFFSET;
        const defaultTop = headerHeight + EDGE_OFFSET;
        currentLeft = defaultLeft;
        currentTop = defaultTop;
        applyPosition(defaultLeft, defaultTop, false);
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ left: currentLeft, top: currentTop }));
    }

    // ── прилипание к ближайшему углу (с учётом хедера) ──
    function snapToEdge() {
        const rect = banner.getBoundingClientRect();
        const winW = window.innerWidth;
        const winH = window.innerHeight;
        const bw = rect.width;
        const bh = rect.height;
        const headerHeight = getHeaderHeight();

        // Центр баннера
        const cx = rect.left + bw / 2;
        const cy = rect.top + bh / 2;

        let targetLeft, targetTop;

        // По горизонтали
        const leftDist = cx;
        const rightDist = winW - cx;
        if (leftDist < rightDist) {
            targetLeft = EDGE_OFFSET;
        } else {
            targetLeft = winW - bw - EDGE_OFFSET;
        }

        // По вертикали (с учётом хедера сверху)
        const topDist = cy - headerHeight;
        const bottomDist = winH - cy;
        if (topDist < bottomDist) {
            targetTop = headerHeight + EDGE_OFFSET;
        } else {
            targetTop = winH - bh - EDGE_OFFSET;
        }

        // Гарантируем, что баннер не вылезет за пределы
        targetLeft = Math.max(EDGE_OFFSET, Math.min(targetLeft, winW - bw - EDGE_OFFSET));
        targetTop = Math.max(headerHeight + EDGE_OFFSET, Math.min(targetTop, winH - bh - EDGE_OFFSET));

        applyPosition(targetLeft, targetTop, true);
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ left: targetLeft, top: targetTop }));
    }

    // ── обработчики перетаскивания ──
    function onPointerDown(e) {
        // Проверяем, что клик не по кнопке и не по крестику
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

        let hasMoved = false;
        let isDraggingNow = false;

        function onPointerMove(ev) {
            const cx = ev.clientX || ev.touches?.[0]?.clientX || 0;
            const cy = ev.clientY || ev.touches?.[0]?.clientY || 0;
            const dx = cx - startX;
            const dy = cy - startY;

            if (!isDraggingNow && (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD)) {
                isDraggingNow = true;
                banner.style.cursor = 'grabbing';
                // Если баннер был свёрнут, он остаётся свёрнутым, но мы начинаем перетаскивание
            }

            if (isDraggingNow) {
                ev.preventDefault();
                let newLeft = startLeft + dx;
                let newTop = startTop + dy;
                const headerHeight = getHeaderHeight();

                // Ограничиваем, чтобы не вылезал за экран
                const maxL = window.innerWidth - banner.offsetWidth - EDGE_OFFSET;
                const maxT = window.innerHeight - banner.offsetHeight - EDGE_OFFSET;
                newLeft = Math.max(EDGE_OFFSET, Math.min(newLeft, maxL));
                newTop = Math.max(headerHeight + EDGE_OFFSET, Math.min(newTop, maxT));

                applyPosition(newLeft, newTop, false);
                hasMoved = true;
            }
        }

        function onPointerUp(ev) {
            document.removeEventListener('mousemove', onPointerMove);
            document.removeEventListener('mouseup', onPointerUp);
            document.removeEventListener('touchmove', onPointerMove);
            document.removeEventListener('touchend', onPointerUp);

            if (isDraggingNow) {
                banner.style.cursor = '';
                // Если было перетаскивание – прилипаем
                snapToEdge();
                // Не разворачиваем, даже если был свёрнут
            } else {
                // Это был клик без перетаскивания – обрабатываем разворачивание/сворачивание
                const isCollapsed = banner.dataset.collapsed === 'true';
                if (isCollapsed) {
                    // Разворачиваем
                    banner.classList.remove('collapsed');
                    banner.dataset.collapsed = 'false';
                    localStorage.setItem('dustore_vote_banner_collapsed', 'false');
                    // После разворачивания позиция не меняется
                }
            }
            isDraggingNow = false;
        }

        document.addEventListener('mousemove', onPointerMove);
        document.addEventListener('mouseup', onPointerUp);
        document.addEventListener('touchmove', onPointerMove, { passive: false });
        document.addEventListener('touchend', onPointerUp);
    }

    // ── установка курсора при наведении ──
    function updateCursor() {
        const isCollapsed = banner.dataset.collapsed === 'true';
        banner.style.cursor = isCollapsed ? 'pointer' : 'grab';
    }

    // ── наблюдаем за изменением состояния свёрнутости ──
    const observer = new MutationObserver(() => {
        updateCursor();
    });
    observer.observe(banner, { attributes: true, attributeFilter: ['data-collapsed'] });

    // ── инициализация ──
    // Загружаем состояние свёрнутости (если было сохранено)
    const collapsedState = localStorage.getItem('dustore_vote_banner_collapsed') === 'true';
    if (collapsedState) {
        banner.classList.add('collapsed');
        banner.dataset.collapsed = 'true';
    } else {
        banner.dataset.collapsed = 'false';
    }

    initPosition();
    updateCursor();

    // Обработчик начала перетаскивания
    banner.addEventListener('mousedown', onPointerDown);
    banner.addEventListener('touchstart', onPointerDown, { passive: false });

    // При изменении размера окна — проверяем, не вышел ли баннер за границы
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            const rect = banner.getBoundingClientRect();
            const winW = window.innerWidth;
            const winH = window.innerHeight;
            let needSnap = false;
            if (rect.left < 0 || rect.top < 0 || rect.right > winW || rect.bottom > winH) {
                needSnap = true;
            }
            if (needSnap) {
                setTimeout(snapToEdge, 100);
            }
        }, 300);
    });

    // ── также обновляем при скролле, если хедер меняет высоту ──
    // Но обычно хедер фиксированной высоты, поэтому не обязательно.
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

<script>
/// Приветственное окошко Дасти на главной + сдвиг vote-banner через top
(function() {
    if (window.location.pathname !== '/') return;

    const popup = document.getElementById('dusty-greeting');
    const banner = document.getElementById('vote-banner');
    if (!popup || !banner) {
        console.warn('Окошко или баннер не найдены');
        return;
    }

    const SESSION_KEY = 'dusty_greeting_shown';
    if (sessionStorage.getItem(SESSION_KEY) === 'true') {
        popup.style.display = 'none';
        return;
    }

    // Получаем текущее значение top (число в пикселях)
    function getCurrentTop() {
        const styleTop = banner.style.top;
        if (styleTop && styleTop !== 'auto' && styleTop !== '') {
            return parseFloat(styleTop);
        }
        return banner.getBoundingClientRect().top;
    }

    // Проверяем, находится ли баннер в правом верхнем углу
    // (более мягкое условие: он должен быть в правой половине и верхней половине экрана)
    function isBannerInTopRight() {
        const rect = banner.getBoundingClientRect();
        const winW = window.innerWidth;
        const winH = window.innerHeight;
        // Баннер должен находиться в правой половине (центр правее 60% ширины)
        const isRight = rect.left + rect.width/2 > winW * 0.55;
        // Баннер должен находиться в верхней половине (центр выше 40% высоты)
        const isTop = rect.top + rect.height/2 < winH * 0.4;
        console.log('Проверка позиции баннера:', { isRight, isTop, rectTop: rect.top });
        return isRight && isTop;
    }

    let originalTop = null;

    // Сдвиг баннера вниз на 200px
    function shiftBannerDown() {
        if (!isBannerInTopRight()) {
            console.log('Баннер не в правом верхнем углу, сдвиг не выполняется');
            return;
        }

        originalTop = getCurrentTop();
        if (originalTop === null || isNaN(originalTop)) {
            console.warn('Не удалось определить текущий top баннера');
            return;
        }

        banner.style.transition = 'top 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        banner.style.top = (originalTop + 170) + 'px';
        console.log(`Баннер сдвинут вниз на 200px (top: ${originalTop} → ${originalTop + 200}px)`);
    }

    // Возврат баннера на место
    function shiftBannerUp() {
        if (originalTop === null) return;
        banner.style.transition = 'top 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        banner.style.top = originalTop + 'px';
        console.log(`Баннер возвращён (top: ${originalTop}px)`);
        setTimeout(() => {
            banner.style.transition = '';
        }, 450);
        originalTop = null;
    }

    function showPopup() {
        popup.style.display = 'block';
        void popup.offsetWidth;
        popup.classList.add('show');

        shiftBannerDown();

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
            shiftBannerUp();
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
</body>

</html>