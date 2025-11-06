<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выпуски журнала RE:START</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0e27;
            min-height: 100vh;
            padding: 40px 20px;
            color: #fff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            font-size: 3em;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ff006e, #00f5ff, #ffbe0b, #ff006e);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleGlow 4s ease-in-out infinite, gradientShift 6s ease-in-out infinite;
            filter: drop-shadow(0 0 20px rgba(255, 0, 110, 0.5));
        }

        @keyframes titleGlow {

            0%,
            100% {
                filter: drop-shadow(0 0 20px rgba(255, 0, 110, 0.5));
            }

            50% {
                filter: drop-shadow(0 0 40px rgba(0, 245, 255, 0.8));
            }
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .subtitle {
            color: #00f5ff;
            margin-bottom: 60px;
            font-size: 1.1em;
            opacity: 0.9;
            text-shadow: 0 0 10px rgba(0, 245, 255, 0.5);
        }

        .effect-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .effect-btn {
            padding: 10px 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .effect-btn:hover {
            border-color: #00f5ff;
            background: rgba(0, 245, 255, 0.1);
        }

        .effect-btn.active {
            background: linear-gradient(135deg, #ff006e, #00f5ff);
            border-color: #ffbe0b;
            box-shadow: 0 0 20px rgba(0, 245, 255, 0.6);
        }

        .shelves-container {
            perspective: 1200px;
        }

        .shelf {
            margin-bottom: 120px;
            position: relative;
        }

        .shelf-bar {
            height: 12px;
            background: linear-gradient(180deg, #3d4a5c 0%, #2a3344 50%, #1a1f2e 100%);
            border-radius: 6px;
            margin-bottom: 50px;
            position: relative;
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.8),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                inset 0 -1px 2px rgba(0, 0, 0, 0.5);
        }

        .books {
            display: flex;
            gap: 15px;
            padding-left: 30px;
            perspective: 1200px;
            flex-wrap: wrap;
        }

        .book {
            width: 200px;
            height: 285px;
            cursor: pointer;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7);
            filter: drop-shadow(0 15px 35px rgba(0, 0, 0, 0.7));
        }

        .book:hover {
            filter: drop-shadow(0 15px 10px rgba(255, 0, 110, 0.));
            transform: rotateX(10deg) rotateY(-10deg) translateY(-5px) scale(1.05);
            box-shadow: 0 20px 45px rgba(255, 0, 110, 0.4);
        }

        .book::before {
            content: '';
            position: absolute;
            top: 0;
            right: -10px;
            width: 10px;
            height: 100%;
            background: linear-gradient(180deg, #444, #222);
            transform: rotateY(90deg) translateZ(5px);
            transform-origin: left;
            box-shadow: inset -2px 0 4px rgba(0, 0, 0, 0.5);
        }

        .book::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, #222, #444);
            transform: rotateX(90deg);
            transform-origin: top;
            box-shadow: inset 0 -2px 4px rgba(0, 0, 0, 0.5);
        }

        .book-cover {
            width: 100%;
            height: 100%;
            border-radius: 6px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            overflow: hidden;
            transform: translateZ(10px);
            transform-style: preserve-3d;
            font-size: 1.1em;
            line-height: 1.4;
            background-size: cover;
            background-position: center;
        }

        .book-cover::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(100, 50, 200, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(0, 200, 255, 0.15) 0%, transparent 50%),
                repeating-linear-gradient(45deg,
                    rgba(255, 255, 255, 0.02) 0px,
                    rgba(255, 255, 255, 0.02) 2px,
                    transparent 2px,
                    transparent 4px),
                url('/swad/static/img/Slice\ 6.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center center;
            z-index: 1;
            border-radius: 0;
            pointer-events: none;
        }



        /* ===== ПОЛИХРОМНЫЙ ЭФФЕКТ ===== */
        .book-cover.polychrome {
            background: linear-gradient(135deg, #ff006e 0%, #00f5ff 25%, #ffbe0b 50%, #8338ec 75%, #ff006e 100%);
            background-size: 400% 400%;
            animation: polyShift 4s ease infinite;
            position: relative;
        }

        .book-cover.polychrome::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: inherit;
            border-radius: 4px;
            mix-blend-mode: overlay;
            opacity: 1;
            z-index: 2;
            pointer-events: none;
        }

        @keyframes polyShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .book-cover.polychrome {
            background-size: 400% 400%, 200% 200%;
            position: relative;
        }

        /* ===== ГОЛОГРАФИЧЕСКИЙ ЭФФЕКТ ===== */
        .book-cover.holographic {
            background: linear-gradient(135deg, #1a1a3e 0%, #2a2a5e 100%);
            position: relative;
        }

        .book-cover.holographic {
            position: relative;
            overflow: hidden;
        }

        .book-cover.holographic::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                linear-gradient(115deg,
                    rgba(255, 0, 150, 0.3),
                    rgba(0, 200, 255, 0.3),
                    rgba(255, 255, 0, 0.25),
                    rgba(0, 255, 200, 0.3),
                    rgba(255, 0, 150, 0.3)),
                repeating-linear-gradient(45deg,
                    rgba(255, 255, 255, 0.2) 0px,
                    rgba(255, 255, 255, 0.2) 2px,
                    transparent 3px,
                    transparent 6px);
            background-size: 400% 400%;
            mix-blend-mode: screen;
            border-radius: 6px;
            animation: holoFlow 8s ease-in-out infinite, hueShift 10s linear infinite;
            pointer-events: none;
            z-index: 3;
        }

        @keyframes holoFlow {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        @keyframes hueShift {
            0% {
                filter: hue-rotate(0deg);
            }

            100% {
                filter: hue-rotate(360deg);
            }
        }


        /* ===== НЕГАТИВНЫЙ ЭФФЕКТ ===== */
        .book-cover.negative {
            background: linear-gradient(135deg, #ffff00 0%, #ff00ff 100%);
            filter: invert(1);
            position: relative;
        }

        .book-cover.negative::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 30%, rgba(0, 0, 0, 0.3) 50%, transparent 70%);
            animation: shine 2s infinite;
            mix-blend-mode: darken;
            z-index: 2;
            border-radius: 4px;
            pointer-events: none;
        }

        .book-cover.negative::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.2), transparent 30%, transparent 70%, rgba(0, 0, 0, 0.3));
            border-radius: 4px;
            filter: invert(1);
        }

        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }

            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }

        .book-content {
            position: relative;
            z-index: 4;
            pointer-events: none;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
        }

        .book-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
            display: block;
            animation: bobbing 2s ease-in-out infinite;
        }

        @keyframes bobbing {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .book-title {
            position: relative;
            z-index: 3;
        }

        /* Цветовые варианты для стандартного режима */
        .book.default .book-cover {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%);
        }

        .book.default:nth-child(2) .book-cover {
            background: linear-gradient(135deg, #4B0082 0%, #9932CC 100%);
        }

        .book.default:nth-child(3) .book-cover {
            background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%);
        }

        .book.default:nth-child(1) .book-cover {
            background: linear-gradient(135deg, #2F4F4F 0%, #5F9EA0 100%);
        }

        .book.default:nth-child(2) .book-cover {
            background: linear-gradient(135deg, #8B4513 0%, #CD853F 100%);
        }

        .book.default:nth-child(3) .book-cover {
            background: linear-gradient(135deg, #1a1a3e 0%, #16213e 100%);
        }

        .book-cover::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.2), transparent 30%, transparent 70%, rgba(0, 0, 0, 0.3));
            border-radius: 4px;
        }

        /* Модальное окно */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        }

        .modal.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                backdrop-filter: blur(0);
            }

            to {
                opacity: 1;
                backdrop-filter: blur(5px);
            }
        }

        .modal-content {
            background: linear-gradient(135deg, #2a3344 0%, #1a1f2e 100%);
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            position: relative;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9);
            animation: slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 2px solid rgba(0, 245, 255, 0.3);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 0, 110, 0.2);
            border: 2px solid #ff006e;
            color: #ff006e;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: #ff006e;
            color: #fff;
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 0 20px rgba(255, 0, 110, 0.6);
        }

        .modal-book {
            width: 120px;
            height: 200px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 15px 40px rgba(255, 0, 110, 0.3);
            animation: bookBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes bookBounce {
            0% {
                transform: scale(0) rotateY(-90deg);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1) rotateY(0);
            }
        }

        .modal h2 {
            font-size: 2em;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #ff006e, #00f5ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .modal p {
            color: #b0b8c1;
            font-size: 1.05em;
            line-height: 1.8;
            margin-bottom: 25px;
        }

        .modal-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #ff006e, #00f5ff);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.05em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 0, 110, 0.3);
        }

        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 245, 255, 0.6);
        }

        .modal-btn:active {
            transform: translateY(0);
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- <h1>📚 Книжная полка</h1>
        <p class="subtitle">Выбери эффект и наведи на книгу</p> -->

        <!-- <div class="effect-selector">
            <button class="effect-btn active" data-effect="default">Стандарт</button>
            <button class="effect-btn" data-effect="polychrome">Полихромный</button>
            <button class="effect-btn" data-effect="holographic">Голографический</button>
            <button class="effect-btn" data-effect="negative">Негативный</button>
        </div> -->

        <div class="shelves-container">
            <!-- Первая полка -->
            <div class="shelf">
                <div class="shelf-bar"></div>
                <div class="books">
                    <div class="book polychrome" data-id="1" data-title="RE:START" data-text="1-й выпуск" style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%238B0000%22 width=%22200%22 height=%22300%22/><text x=%2250%25%22 y=%2250%25%22 font-size=%2240%22 fill=%22%23fff%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>⚔️</text></svg>')">
                        <div class="book-cover polychrome">
                            <div class="book-content">
                                <!-- <span class="book-icon">📖</span>
                                <div class="book-title">Война<br>и мир</div> -->
                            </div>
                        </div>
                    </div>
                    <!-- <div class="book default" data-id="2" data-title="Преступление и наказание" data-text="Психологический роман Достоевского о моральных дилеммах и духовном возрождении." style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%234B0082%22 width=%22200%22 height=%22300%22/><text x=%2250%25%22 y=%2250%25%22 font-size=%2240%22 fill=%22%23fff%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>🔮</text></svg>')">
                        <div class="book-cover">
                            <div class="book-content">
                            </div>
                        </div>
                    </div>
                    <div class="book default" data-id="3" data-title="Мастер и Маргарита" data-text="Шедевр Булгакова, сочетающий фантастику, сатиру и реальность советской эпохи." style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%23DAA520%22 width=%22200%22 height=%22300%22/><text x=%2250%25%22 y=%2250%25%22 font-size=%2240%22 fill=%22%23fff%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>✨</text></svg>')">
                        <div class="book-cover">
                            <div class="book-content">
                                <span class="book-icon"></span>
                                <div class="book-title"><br><br><br><br><br><br></div>
                            </div>
                        </div>
                    </div> -->
                </div>
            </div>

            <!-- Вторая полка
            <div class="shelf">
                <div class="shelf-bar"></div>
                <div class="books">
                    <div class="book default" data-id="4" data-title="Вишневый сад" data-text="Драма Чехова о конце эпохи, переменах в обществе и неизбежности прогресса." style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%232F4F4F%22 width=%22200%22 height=%22300%22/><text x=%2250%25%22 y=%2250%25%22 font-size=%2240%22 fill=%22%23fff%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>🌸</text></svg>')">
                        <div class="book-cover">
                            <div class="book-content">
                                <span class="book-icon">🌸</span>
                                <div class="book-title">Вишневый<br>сад</div>
                            </div>
                        </div>
                    </div>
                    <div class="book default" data-id="5" data-title="Граф Монте-Кристо" data-text="Приключенческий роман Дюма о мести, справедливости и верности." style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%238B4513%22 width=%22200%22 height=%22300%22/><text x=%2250%25%22 y=%2250%25%22 font-size=%2240%22 fill=%22%23fff%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>⚔️</text></svg>')">
                        <div class="book-cover">
                            <div class="book-content">
                                <span class="book-icon">⚔️</span>
                                <div class="book-title">Граф<br>Монте-Кристо</div>
                            </div>
                        </div>
                    </div>
                    <div class="book default" data-id="6" data-title="1984" data-text="Антиутопия Оруэлла о тоталитарном обществе и борьбе за свободу мысли." style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%231a1a3e%22 width=%22200%22 height=%22300%22/><text x=%2250%25%22 y=%2250%25%22 font-size=%2240%22 fill=%22%23fff%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22>👁️</text></svg>')">
                        <div class="book-cover">
                            <div class="book-content">
                                <span class="book-icon">👁️</span>
                                <div class="book-title">1984</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->
        </div>
    </div>

    <!-- Модальное окно -->
    <div class="modal" id="modal">
        <div class="modal-content">
            <button class="close-btn" id="closeBtn">&times;</button>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        let currentEffect = 'default';
        const books = document.querySelectorAll('.book');
        const modal = document.getElementById('modal');
        const modalBody = document.getElementById('modalBody');
        const closeBtn = document.getElementById('closeBtn');
        const effectBtns = document.querySelectorAll('.effect-btn');

        // Переключение эффектов
        effectBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                effectBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentEffect = btn.dataset.effect;

                books.forEach(book => {
                    book.classList.remove('default', 'polychrome', 'holographic', 'negative');
                    book.querySelector('.book-cover').classList.remove('default', 'polychrome', 'holographic', 'negative');

                    if (currentEffect !== 'default') {
                        book.querySelector('.book-cover').classList.add(currentEffect);
                    } else {
                        book.classList.add('default');
                    }
                });
            });
        });

        // Эффект следления за курсором
        books.forEach(book => {
            book.addEventListener('mousemove', (e) => {
                const rect = book.getBoundingClientRect();
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const rotateX = (centerY - y) / 8;
                const rotateY = (x - centerX) / 8;

                // Вычисляем угол градиента (от -180 до 180 градусов)
                const angle = Math.atan2(rotateY, rotateX) * (180 / Math.PI);

                // Вычисляем расстояние для интенсивности
                const distance = Math.hypot(rotateX, rotateY);

                const cover = book.querySelector('.book-cover');

                // Применяем трансформацию градиента через rotate
                cover.style.backgroundImage = `linear-gradient(${angle}deg, #ff006e 0%, #00f5ff 25%, #ffbe0b 50%, #8338ec 75%, #ff006e 100%)`;

                book.style.transform = `
                    rotateX(${rotateX}deg) 
                    rotateY(${rotateY}deg) 
                    scale(1.08)
                    translateZ(50px)
                `;
            });

            book.addEventListener('mouseleave', () => {
                book.style.transform = 'rotateX(0) rotateY(0) scale(1) translateZ(0)';
                const cover = book.querySelector('.book-cover');
                if (cover.classList.contains('polychrome')) {
                    cover.style.backgroundImage = 'linear-gradient(135deg, #ff006e 0%, #00f5ff 25%, #ffbe0b 50%, #8338ec 75%, #ff006e 100%)';
                }
            });

            // Открытие модального окна
            book.addEventListener('click', () => {
                const title = book.dataset.title;
                const text = book.dataset.text;
                const cover = book.querySelector('.book-cover');
                const computedStyle = window.getComputedStyle(cover);
                const background = computedStyle.background || computedStyle.backgroundColor;

                modalBody.innerHTML = `
                    <div class="modal-book" style="background: ${background}"></div>
                    <h2>${title}</h2>
                    <p>${text}</p>
                    <button class="modal-btn">Закрыть</button>
                `;

                modal.classList.add('active');

                // Закрытие из кнопки в модале
                modalBody.querySelector('.modal-btn').addEventListener('click', () => {
                    modal.classList.remove('active');
                });
            });
        });

        // Закрытие модального окна
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('active');
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    </script>
</body>

</html>