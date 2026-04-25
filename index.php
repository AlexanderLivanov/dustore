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
    <main >
        <section class="hero">
            <div class="hero-bg">

               </div>
            <div class="container">
                <div class="hero-content">
                    <h1 class="pixel-title">DUSTORE — открытое пространство для инди-разработчиков</h1>
                    <p>Делаем платформу, где разработчики смогут свободно выложить проекты, а игроки - открыть новые игровые миры. Присоединяйтесь к сообществу!</p>
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

        <!-- <section class="slider-section" style="padding: 0">
            <div class="slider-container">
                <div class="slider-track">
                    <div class="slider-slide" style="background-image: url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');">
                        <div class="slide-overlay"></div>
                        <div class="slide-content">
                            <h2>3 сентября состоится презентация проекта!</h2>
                            <p>Мы расскажем обо всех наших преимуществах и сделаем полный обзор Платформы</p>
                            <a href="https://t.me/dustore_official" target="_blank" class="btn">Следить за новостями</a>
                        </div>
                    </div>

                    <div class="slider-slide" style="background-image: url('https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=1351&q=80');">
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
                </div>

                <div class="slider-arrows">
                    <div class="slider-arrow prev">❮</div>
                    <div class="slider-arrow next">❯</div>
                </div>

                <div class="slider-nav">
                    <div class="slider-dot active"></div>
                    <div class="slider-dot"></div>
                    <div class="slider-dot"></div>
                    <div class="slider-dot"></div>
                </div>
            </div>
        </section> -->

        <!-- Что это? -->
        <section class="stats">
            <div class="container">
                <h2>Что входит в экосистему Dustore?</h2>
                <div class="platform-grid">
                    <div class="platform-card">
                        <div class="platform-icon">💼</div>
                        <h3>Платформа DUSTORE.ru</h3>
                        <p>Главный узел в экосистеме. Это центр, где связываются все части Платформы. Преимущественно здесь находится каталог игр.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🚀</div>
                        <h3>Dustore.Launcher</h3>
                        <p>(в разработке) Собственный лаунчер, через который можно скачивать игры.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">💵</div>
                        <h3>Dustore.Finance</h3>
                        <p>(в разработке) Площадка для приёма платежей. Через неё разработчики могут монетизировать свои проекты.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">✅</div>
                        <h3>Dustore.Connect</h3>
                        <p>(в разработке) Единый вход для всех сервисов в экосистеме. Используйте свой аккаунт в Telegram или ключевую фразу для входа. Это быстро, современно и безопасно.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">👨‍🎓</div>
                        <h3>Dustore.Edu</h3>
                        <p>(в разработке) Форум, посвящённый разработке игр и публикации их на Платформе. Тут происходит обмен знаниями.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">📸</div>
                        <h3>Dustore.Media</h3>
                        <p>(в разработке) Свой информационный ресурс, который управляется пользователями. Здесь можно выложить анонс своей игры или рассказать о новостях в мире геймдева.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🌐</div>
                        <h3>Dustore.GIB (games in browser)</h3>
                        <p>(в разработке) Помогаем портировать игры в браузере, чтобы игрокам не приходилось их скачивать.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">👩‍💻</div>
                        <h3>Dustore.Devs</h3>
                        <p>Портал для разработчиков из студий. Публикация проектов, аналитика, монетизация.</p>
                    </div>
                    <div class="platform-card">
                        <div class="platform-icon">🥙</div>
                        <h3>shaurMA</h3>
                        <p>Собственная консоль для разработчиков, через которую вы можете полностью управлять своей студией.</p>
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
                <h3>А вот, какие фичи мы планируем внедрить:</h3>
                <div class="platform-grid">
                    <div class="platform-card" onclick="window.location.replace('https:/\/github.com/AlexanderLivanov/dustore-docs');" style="cursor: pointer;">
                        <h3>Полный список</h3>
                        <p>С полным списком фич вы можете ознакомиться на специальной странице...
                        </p>
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
</body>

</html>