<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - О платформе</title>
    <link rel="stylesheet" href="swad/css/pages.css">
    <?php require_once('swad/controllers/ymcounter.php'); ?>
    <style>

    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="about-hero">
            <div class="container">
                <h1>О платформе Dustore</h1>
                <p>Инновационная игровая экосистема, созданная для объединения разработчиков и игроков</p>
            </div>
        </section>

        <section class="mission">
            <div class="container">
                <h2>Наша миссия</h2>
                <p class="mission-statement">"Создать пространство, где талантливые разработчики могут реализовать свои идеи, а игроки — открывать для себя уникальные проекты"</p>
                <p>Dustore — это не просто магазин игр, а полноценная экосистема для инди-разработчиков и ценителей качественного геймдева. Мы стремимся разрушить барьеры между создателями и игроками, предоставляя инструменты для прямого взаимодействия и совместного творчества.</p>
            </div>
        </section>

        <section class="team">
            <div class="container">
                <h2>Команда DUSTORE</h2>
                <p>Наша команда состоит из энтузиастов игровой индустрии, которые верят в потенциал независимой разработки</p>

                <h1>Dust Games</h1>
                <div class="team-grid">
                    <div class="team-member">
                        <img src="https://via.placeholder.com/150/c32178/ffffff?text=Алексей" alt="Алексей" class="team-avatar">
                        <h3 class="team-name">Алексей</h3>
                        <p class="team-role">Основатель & CEO</p>
                        <p>Идейный вдохновитель проекта, отвечает за стратегическое развитие платформы</p>
                    </div>

                    <div class="team-member">
                        <img src="https://via.placeholder.com/150/74155d/ffffff?text=Мария" alt="Мария" class="team-avatar">
                        <h3 class="team-name">Мария</h3>
                        <p class="team-role">CTO</p>
                        <p>Руководит технической реализацией проекта и развитием инфраструктуры</p>
                    </div>
                </div>
                <div style="margin-top: 50px;"></div>
                <h1>Crazy Projects Lab Russia
                    <a href="https://vk.com/crazyprojectslab" class="team-link">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="32"
                            height="32"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#ffffff"
                            stroke-width="1"
                            stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M14 19h-4a8 8 0 0 1 -8 -8v-5h4v5a4 4 0 0 0 4 4h0v-9h4v4.5l.03 0a4.531 4.531 0 0 0 3.97 -4.496h4l-.342 1.711a6.858 6.858 0 0 1 -3.658 4.789h0a5.34 5.34 0 0 1 3.566 4.111l.434 2.389h0h-4a4.531 4.531 0 0 0 -3.97 -4.496v4.5z" />
                        </svg>
                    </a>
                </h1>
                <div class="team-grid">
                    <div class="team-member">
                        <img src="/swad/static/img/team/alexanderlivanov_cplrus.webp" alt="Санечка I :)" class="team-avatar">
                        <h3 class="team-name">Александр Ливанов</h3>
                        <p class="team-role">Ведущий программист</p>
                        <p>Архитектура платформы, базовый дизайн и разработка</p>
                        <a class="team-link" href="https://t.me/indepcode">https://t.me/indepcode</a>
                    </div>

                    <div class="team-member">
                        <img src="/swad/static/img/team/alexanderpartikevich_cplrus.webp" alt="Санечка II :)" class="team-avatar">
                        <h3 class="team-name">Александр Партикевич</h3>
                        <p class="team-role">Арт-директор</p>
                        <p>Логотипы и визуальная концепция проекта</p>
                        <a class="team-link" href="https://t.me/Aleksandr_MotionGraphics">https://t.me/Aleksandr_MotionGraphics</a>
                    </div>
                </div>
                <div style="margin-top: 50px;"></div>
            </div>
        </section>

        <section class="values">
            <div class="container">
                <h2>Наши ценности</h2>
                <p>В основе Dustore лежат принципы, которые направляют нашу работу</p>

                <div class="values-grid">
                    <div class="value-card">
                        <div class="value-icon">💡</div>
                        <h3 class="value-title">Инновации</h3>
                        <p>Мы постоянно ищем новые подходы и технологии, чтобы сделать платформу лучше для всех участников</p>
                    </div>

                    <div class="value-card">
                        <div class="value-icon">🤝</div>
                        <h3 class="value-title">Сообщество</h3>
                        <p>Верим в силу сообщества и создаем инструменты для взаимодействия разработчиков и игроков</p>
                    </div>

                    <div class="value-card">
                        <div class="value-icon">⚖️</div>
                        <h3 class="value-title">Справедливость</h3>
                        <p>Строим прозрачную систему с честными условиями для всех участников экосистемы</p>
                    </div>

                    <div class="value-card">
                        <div class="value-icon">🎮</div>
                        <h3 class="value-title">Страсть к играм</h3>
                        <p>Мы сами большие фанаты игр и создаем платформу, которую хотели бы видеть как игроки</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="timeline">
            <div class="container">
                <h2>Наша история</h2>
                <p>Ключевые этапы развития платформы</p>

                <div class="timeline-item">
                    <div class="timeline-date">
                        <h3>2023</h3>
                    </div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Идея и начало</h3>
                        <p>Формирование концепции платформы, первые наброски архитектуры и дизайна. Сбор команды единомышленников.</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-date">
                        <h3>2024</h3>
                    </div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Разработка</h3>
                        <p>Создание первой рабочей версии платформы, привлечение первых разработчиков-партнеров, закрытое тестирование.</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-date">
                        <h3>2025</h3>
                    </div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Запуск</h3>
                        <p>Официальный релиз Dustore для всех пользователей. Первые 100 игр на платформе, 10 000 зарегистрированных игроков.</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-date">
                        <h3>2026</h3>
                    </div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Планы</h3>
                        <p>Запуск системы подписок, выход на международный рынок, интеграция с социальными функциями.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        // Анимация для элементов страницы
        document.addEventListener('DOMContentLoaded', function() {
            const animateOnScroll = (elements) => {
                elements.forEach(element => {
                    const elementPosition = element.getBoundingClientRect().top;
                    const screenPosition = window.innerHeight / 1.3;

                    if (elementPosition < screenPosition) {
                        element.style.opacity = '1';
                        element.style.transform = 'translateY(0)';
                    }
                });
            };

            // Инициализация анимаций
            const teamMembers = document.querySelectorAll('.team-member');
            const valueCards = document.querySelectorAll('.value-card');
            const timelineItems = document.querySelectorAll('.timeline-item');

            // Установка начального состояния
            teamMembers.forEach(member => {
                member.style.opacity = '0';
                member.style.transform = 'translateY(30px)';
                member.style.transition = 'all 0.6s ease';
            });

            valueCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'all 0.6s ease';
            });

            timelineItems.forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-30px)';
                item.style.transition = 'all 0.6s ease';
            });

            // Первая анимация при загрузке
            setTimeout(() => {
                animateOnScroll(teamMembers);
                animateOnScroll(valueCards);
                animateOnScroll(timelineItems);
            }, 300);

            // Анимация при скролле
            window.addEventListener('scroll', () => {
                animateOnScroll(teamMembers);
                animateOnScroll(valueCards);
                animateOnScroll(timelineItems);
            });
        });
    </script>
</body>

</html>