<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - О платформе</title>
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
            background-color: var(--gradient);
            color: var(--light);
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        section {
            padding: 80px 0;
        }

        h1,
        h2,
        h3 {
            font-family: 'PixelizerBold', 'Gill Sans', sans-serif;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        h1 {
            font-size: 3.5rem;
        }

        h2 {
            font-size: 2.5rem;
            text-align: center;
            position: relative;
            margin-bottom: 60px;
        }

        h2:after {
            content: "";
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }

        p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 20px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #e62e8a;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(195, 33, 120, 0.3);
        }

        .about-hero {
            background: var(--gradient);
            padding: 180px 0 100px;
            text-align: center;
        }

        .about-hero h1 {
            font-size: 4rem;
            margin-bottom: 30px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .about-hero p {
            font-size: 1.3rem;
            margin-bottom: 40px;
        }

        .mission {
            background: var(--dark);
            text-align: center;
        }

        .mission-statement {
            font-size: 1.5rem;
            font-style: italic;
            color: var(--primary);
            margin-bottom: 40px;
        }

        .team {
            background: var(--dark);
            text-align: center;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }

        .team-member {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            transition: transform 0.3s ease;
        }

        .team-member:hover {
            transform: translateY(-10px);
            background: rgba(195, 33, 120, 0.1);
        }

        .team-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 3px solid var(--primary);
        }

        .team-name {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .team-role {
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .values {
            background: var(--dark);
            text-align: center;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }

        .value-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 40px 30px;
            transition: transform 0.3s ease;
        }

        .value-card:hover {
            transform: translateY(-10px);
            background: rgba(195, 33, 120, 0.1);
        }

        .value-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .value-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .timeline {
            background: var(--gradient);
            position: relative;
            padding: 80px 0;
        }

        .timeline-item {
            display: flex;
            margin-bottom: 50px;
            position: relative;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-date {
            flex: 0 0 150px;
            text-align: right;
            padding-right: 30px;
            position: relative;
        }

        .timeline-date:after {
            content: "";
            position: absolute;
            right: -10px;
            top: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid var(--dark);
        }

        .timeline-content {
            flex: 1;
            padding-left: 30px;
            border-left: 2px solid var(--primary);
            padding-bottom: 30px;
        }

        .timeline-title {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            h1 {
                font-size: 2.5rem;
            }

            h2 {
                font-size: 2rem;
            }

            .about-hero {
                padding: 120px 0 60px;
            }

            .about-hero h1 {
                font-size: 3rem;
            }

            .timeline-item {
                flex-direction: column;
            }

            .timeline-date {
                text-align: left;
                padding-right: 0;
                padding-bottom: 10px;
                margin-bottom: 20px;
                border-bottom: 2px solid var(--primary);
            }

            .timeline-date:after {
                right: auto;
                left: -10px;
                top: auto;
                bottom: -11px;
            }

            .timeline-content {
                border-left: none;
                padding-left: 0;
            }
        }
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
                <p class="mission-statement">"Создать пространство, где талантливые разработчики могут реализовать свои идеи, а игроки — открывать для себя уникальные игровые миры"</p>
                <p>Dustore — это не просто магазин игр, а полноценная экосистема для инди-разработчиков и ценителей качественного геймдева. Мы стремимся разрушить барьеры между создателями и игроками, предоставляя инструменты для прямого взаимодействия и совместного творчества.</p>
            </div>
        </section>

        <section class="team">
            <div class="container">
                <h2>Команда Dustore</h2>
                <p>Наша команда состоит из энтузиастов игровой индустрии, которые верят в потенциал независимой разработки</p>

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

                    <div class="team-member">
                        <img src="https://via.placeholder.com/150/400c4a/ffffff?text=Дмитрий" alt="Дмитрий" class="team-avatar">
                        <h3 class="team-name">Дмитрий</h3>
                        <p class="team-role">Lead Developer</p>
                        <p>Отвечает за архитектуру платформы и ключевые технические решения</p>
                    </div>

                    <div class="team-member">
                        <img src="https://via.placeholder.com/150/14041d/ffffff?text=Екатерина" alt="Екатерина" class="team-avatar">
                        <h3 class="team-name">Екатерина</h3>
                        <p class="team-role">Дизайн & UX</p>
                        <p>Создает удобные интерфейсы и запоминающийся визуальный стиль платформы</p>
                    </div>
                </div>
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