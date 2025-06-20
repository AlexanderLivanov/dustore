<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore.Finance</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --light: #ecf0f1;
            --dark: #1a2530;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        header {
            background-color: var(--primary);
            color: white;
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo span {
            color: var(--secondary);
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 1.5rem;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
            font-weight: 500;
        }

        nav ul li a:hover {
            color: var(--secondary);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .page-title {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--dark);
        }

        .products {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .product-image {
            height: 200px;
            background-size: cover;
            background-position: center;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-title {
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .product-description {
            color: #666;
            margin-bottom: 1rem;
            min-height: 80px;
        }

        .product-price {
            font-size: 1.6rem;
            font-weight: 700;
            color: #27ae60;
            margin-bottom: 1rem;
        }

        .btn-buy {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background-color: var(--secondary);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-buy:hover {
            background-color: #2980b9;
        }

        footer {
            background-color: var(--dark);
            color: white;
            padding: 3rem 5% 2rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-section h3 {
            margin-bottom: 1.2rem;
            font-size: 1.3rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background-color: var(--secondary);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.8rem;
        }

        .footer-section ul li a {
            color: #bbb;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-section ul li a:hover {
            color: var(--secondary);
        }

        .contact-info {
            color: #bbb;
        }

        .contact-info div {
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
        }

        .contact-info i {
            margin-right: 10px;
            color: var(--secondary);
        }

        .legal-info {
            margin-top: 2rem;
            text-align: center;
            color: #777;
            font-size: 0.9rem;
            padding-top: 1.5rem;
            border-top: 1px solid #34495e;
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                text-align: center;
            }

            nav ul {
                margin-top: 1rem;
                justify-content: center;
            }

            nav ul li {
                margin: 0 0.75rem;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">DUSTORE<span>.FINANCE</span></div>
        <nav>
            <ul>
                <li><a href="/">Главная</a></li>
                <li><a href="mailto:support@dustore.ru">Помощь</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h1 class="page-title">Что вы можете приобрести:</h1>

        <div class="products">
            <div class="product-card">
                <!-- <div class="product-image" style="background: linear-gradient(135deg, #8e44ad, #3498db);"></div> -->
                <div class="product-info">
                    <h2 class="product-title">Подписка для игроков</h2>
                    <p class="product-description">
                        - Доступ к большинству игр,<br>
                        - Бесплатная тех.поддержка. <br>
                    </p>
                    <div class="product-price">Бесплатно</div>
                    <button class="btn-buy" onclick="window.location.replace('/login');">Играем!</button>
                </div>
            </div>
            <div class="product-card">
                <!-- <div class="product-image" style="background: linear-gradient(135deg, #8e44ad, #3498db);"></div> -->
                <div class="product-info">
                    <h2 class="product-title">Подписка для игроков (ПРЕМИУМ 👑)</h2>
                    <p class="product-description">
                        - Эксклюзивный доступ к закрытому игровому контенту,<br>
                        - Ежемесячные бонусы в виде внутренней валюты и скидки,<br>
                        - Больше игр по меньшей цене. <br>
                    </p>
                    <div class="product-price">199 ₽/месяц</div>
                    <button class="btn-buy">Купить подписку</button>
                </div>
            </div>

            <!-- Товар 2: Консоль разработчика -->
            <div class="product-card">
                <!-- <div class="product-image" style="background: linear-gradient(135deg, #e74c3c, #f39c12);"></div> -->
                <div class="product-info">
                    <h2 class="product-title">Консоль разработчика</h2>
                    <p class="product-description">
                        - Полный доступ к инструментам разработки,<br>
                        - API для раазработчиков,<br>
                        - Документация, <br>
                        - Система управления персоналом. <br>
                        <br>
                        Лицензия на 1 год с технической поддержкой и регулярными обновлениями.
                    </p>
                    <div class="product-price">1337 ₽/год</div>
                    <button class="btn-buy">Приобрести доступ</button>
                </div>
            </div>
        </div>

        <h2>Помощь и возврат денежных средств</h2>
        <p>
            Если у вас возникли проблемы, свяжитесь с нами <a href="mailto:support@dustore.ru">по почте</a>, и мы ответим вам в течение одного дня.
            <br>
            ❗ На данный момент заявки на возврат средств от игроков не принимаются (По вопросам возврата средств за игру связывайтесь с разработчиком игры). <br>
            ❗ Заявки на возврат средст от разработчиков рассматриваются только если разработчик передумал регистрировать студию <u>на этапе модерации</u>.
        </p>

        <h2>Порядок предоставления услуг</h2>
        <p>Доступ к консоли разработчика предоставляется только после оплаты и может быть заблокирован по решению Администрации.</p>

        <h2>Отказ от услуги и возврат денежных средств</h2>
        <p>Доступ к консоли разработчика выдаётся только после подтверждения аккаунта разработчика модераторами платформы. Если разработчик после оплаты изменил своё решение, то он может написать соответсвующее письмо в техническую поддержку платформы.
            В этом случае Платформа возвращает денежную сумму (из неё вычитается комиссия) и аккаунт раазработчика больше не будет рассматриваться модератором (аккаунт удаляется). В иных случаях возврат средств не рассматривается.
        </p>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Контакты</h3>
                <div class="contact-info">
                    <div>
                        ✉️ support@dustore.ru
                    </div>
                    <div>
                        📱 Telegram: @dustore_official
                    </div>
                    <div>
                        🕒 Техподдержка: <br>
                        Пн-Пт: 10:00 - 22:00 <br>
                        Сб-Вс: 12:00 - 20:00
                    </div>
                </div>
            </div>

            <div class="footer-section">
                <h3>Информация</h3>
                <ul>
                    <!-- <li><a href="#">Доставка и возврат</a></li> -->
                    <li><a href="/oferta">Публичная оферта</a></li>
                    <li><a href="/privacy">Обработка персональных данных</a></li>
                    <li><a href="/agreement">Пользовательское соглашение</a></li>
                    <!-- <li><a href="#">Юридическая информация</a></li> -->
                </ul>
            </div>

            <div class="footer-section">
                <h3>Юридическая информация</h3>
                <div class="contact-info">
                    <div>Самозанятый Ливанов Александр Алексеевич</div>
                    <div>ИНН: 771392840109</div>
                    <!-- <div>ОГРН: 1187746123456</div> -->
                    <div>г. Москва</div>
                </div>
            </div>
        </div>

        <div class="legal-info">
            &copy; 2025 Dust Studio. Все права защищены. <br>
            Dustore.Finance является частью экосистемы Dustore
        </div>
    </footer>
</body>

</html>