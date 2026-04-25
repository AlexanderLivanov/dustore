<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Игровая лента с меню</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", sans-serif;
            background: #121212;
            color: #f0f0f0;
            display: flex;
        }

        /* Боковое меню */
        .sidebar {
            width: 220px;
            background: #1a1a1a;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .sidebar a {
            color: #f0f0f0;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            transition: background 0.15s;
        }

        .sidebar a:hover {
            background: #2a2a2a;
        }

        /* Основной контент */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            height: 100vh;
        }

        /* Хедер */
        .header {
            background: #1e1e1e;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #2a2a2a;
        }

        .header h1 {
            margin: 0;
            font-size: 1.5em;
        }

        /* Лента */
        .feed {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;

            display: flex;
            align-items: center;
            justify-content: center;

            position: relative;
            height: calc(100vh - 80px);
        }

        .card {
            background: #1e1e1e;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card img,
        .card video {
            width: 100%;
            display: block;
            object-fit: cover;
        }

        .card .post-header {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #2a2a2a;
        }

        .post-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .post-header .author {
            display: flex;
            flex-direction: column;
        }

        .post-header .author .name {
            font-weight: bold;
        }

        .post-header .author .subtitle {
            font-size: 0.8em;
            color: #888;
        }

        .card .content {
            padding: 15px;
        }

        .card .content h3 {
            margin: 0 0 8px;
            font-size: 1.2em;
        }

        .card .content p {
            margin: 0 0 10px;
            font-size: 0.95em;
            line-height: 1.4;
            color: #c0c0c0;
        }

        .tags {
            font-size: 0.8em;
            color: #888;
        }

        .card .actions {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            gap: 20px;
            font-size: 0.9em;
            color: #aaa;
        }

        .card .actions i {
            cursor: pointer;
            transition: color 0.15s;
        }

        .card .actions i:hover {
            color: #fff;
        }

        .swipe-card {
            position: absolute;
            width: 100%;
            max-width: 700px;
            transition: transform 0.3s ease, opacity 0.3s;
            cursor: grab;
        }

        .swipe-card.removed {
            opacity: 0;
        }

        @media (max-width: 900px) {
            .sidebar {
                display: none;
            }

            .feed {
                max-width: 100%;
                padding: 10px;
            }

            .swipe-card {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <a href="#"><i class="fas fa-home"></i> Главная</a>
        <a href="#"><i class="fas fa-gamepad"></i> Мои игры</a>
        <a href="#"><i class="fas fa-newspaper"></i> Новости</a>
        <a href="#"><i class="fas fa-star"></i> Рекомендации</a>
        <a href="#"><i class="fas fa-cog"></i> Настройки</a>
    </div>

    <div class="main">
        <div class="header">
            <h1>GameFeed</h1>
            <i class="fas fa-search"></i>
        </div>

        <div class="feed">

            <div class="card swipe-card">
                <div class="post-header">
                    <img src="https://via.placeholder.com/40">
                    <div class="author">
                        <span class="name">ИгровойЖурнал</span>
                        <span class="subtitle">Студия • 2ч назад</span>
                    </div>
                </div>
                <img src="https://via.placeholder.com/700x300.png?text=Видео+обзор">
                <div class="content">
                    <h3>Видео-обзор: “Секретный мир”</h3>
                    <p>Краткий обзор новой RPG.</p>
                </div>
            </div>

            <div class="card swipe-card">
                <div class="post-header">
                    <img src="https://via.placeholder.com/40">
                    <div class="author">
                        <span class="name">IndieDev</span>
                        <span class="subtitle">Пользователь • 5ч назад</span>
                    </div>
                </div>
                <img src="https://via.placeholder.com/700x250.png?text=Скриншот">
                <div class="content">
                    <h3>Скриншот дня</h3>
                    <p>Инди-платформер.</p>
                </div>
            </div>

        </div>
    </div>
    <script>
        const cards = document.querySelectorAll('.swipe-card');

        cards.forEach((card, index) => {
            let startX = 0;
            let currentX = 0;
            let isDragging = false;

            card.style.zIndex = cards.length - index;

            const onMove = (x) => {
                currentX = x - startX;
                card.style.transform = `translateX(${currentX}px) rotate(${currentX/10}deg)`;
            };

            const onEnd = () => {
                isDragging = false;

                if (currentX > 120) {
                    card.style.transform = "translateX(1000px) rotate(20deg)";
                    card.classList.add('removed');
                } else if (currentX < -120) {
                    card.style.transform = "translateX(-1000px) rotate(-20deg)";
                    card.classList.add('removed');
                } else {
                    card.style.transform = "";
                }
            };

            // мышка
            card.addEventListener('mousedown', (e) => {
                isDragging = true;
                startX = e.clientX;
                card.style.transition = "none";
            });

            window.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                onMove(e.clientX);
            });

            window.addEventListener('mouseup', () => {
                if (!isDragging) return;
                card.style.transition = "";
                onEnd();
            });

            // тач
            card.addEventListener('touchstart', (e) => {
                isDragging = true;
                startX = e.touches[0].clientX;
                card.style.transition = "none";
            });

            window.addEventListener('touchmove', (e) => {
                if (!isDragging) return;
                onMove(e.touches[0].clientX);
            });

            window.addEventListener('touchend', () => {
                if (!isDragging) return;
                card.style.transition = "";
                onEnd();
            });
        });

        // стрелки клавиатуры
        document.addEventListener('keydown', (e) => {
            const card = document.querySelector('.swipe-card:not(.removed)');
            if (!card) return;

            if (e.key === "ArrowRight") {
                card.style.transform = "translateX(1000px) rotate(20deg)";
                card.classList.add('removed');
            }

            if (e.key === "ArrowLeft") {
                card.style.transform = "translateX(-1000px) rotate(-20deg)";
                card.classList.add('removed');
            }
        });
    </script>
</body>

</html>