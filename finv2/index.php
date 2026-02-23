<?php
session_start();
require_once('../swad/config.php');

$db = new Database();
$pdo = $db->connect();

$userId = $_SESSION['USERDATA']['id'] ?? null;

$currentPlan = null;

if ($userId) {
    $stmt = $pdo->prepare("
        SELECT plan_code 
        FROM subscriptions 
        WHERE user_id = ?
          AND status = 'active'
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $currentPlan = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Услуги для разработчиков</title>
    <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
    <style>
        :root {
            --bg: #0f1115;
            --card: #171a21;
            --accent: #4da3ff;
            --accent2: #00d4a6;
            --text: #e6e6e6;
            --muted: #9aa4b2;
            --danger: #ff5f56;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Inter, system-ui, sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            width: 1200px;
            max-width: 95%;
            margin: auto;
        }

        header {
            padding: 60px 0;
            text-align: center;
        }

        header h1 {
            font-size: 42px;
            margin-bottom: 20px;
        }

        header p {
            color: var(--muted);
            font-size: 18px;
        }

        .plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 60px 0;
        }

        .plan {
            background: var(--card);
            padding: 30px;
            border-radius: 16px;
            position: relative;
            transition: 0.3s;
            border: 1px solid #222;
        }

        .plan:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
        }

        .plan h2 {
            margin-bottom: 10px;
        }

        .price {
            font-size: 32px;
            margin: 20px 0;
            color: var(--accent2);
        }

        .features {
            list-style: none;
            margin-bottom: 30px;
        }

        .features li {
            margin-bottom: 12px;
            color: var(--muted);
        }

        button {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #3a8ce6;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
        }

        .btn-outline:hover {
            background: var(--accent);
            color: white;
        }

        .section {
            margin: 80px 0;
        }

        .section h2 {
            margin-bottom: 20px;
        }

        .section p {
            color: var(--muted);
            margin-bottom: 20px;
        }

        .api-box {
            background: #111318;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #222;
            font-family: monospace;
            font-size: 14px;
            color: #7dd3fc;
        }

        footer {
            text-align: center;
            padding: 40px 0;
            color: var(--muted);
            border-top: 1px solid #222;
        }

        .plan.active {
            border: 2px solid var(--accent2);
            box-shadow: 0 0 25px rgba(0, 212, 166, 0.25);
        }

        .plan.active::before {
            content: "Текущий тариф";
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--accent2);
            color: #000;
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 6px;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <div class="container">

        <header>
            <a href="/">
                <img src="/swad/static/img/logo_new.png" style="width: 128px;" alt="">
            </a>
            <h1>Подписки для разработчиков</h1>
            <p>Расширенная загрузка билдов, дополнительные слоты под студии и проекты, API-доступ, расширенная статистика, управление командой.</p>
        </header>

        <section class="plans">

            <div class="plan">
                <h2>Инди</h2>
                <div class="price">0 ₽</div>
                <div class="features">Базовые возможности.</div>
                <ul class="features">
                    <li>- 1 студия</li>
                    <li>- До 10 игр</li>
                    <li>- Загрузка ZIP-билдов до 300 МБ</li>
                    <li>- Базовая статистика</li>
                    <li> </li>
                </ul>
                <button class="btn-outline">Включено по умолчанию</button>
            </div>

            <div class="plan <?= $currentPlan === 'indie_pro' ? 'active' : '' ?>">
                <h2>Инди Pro</h2>
                <div class="price">299 ₽ / мес</div>
                <div class="features">Для тех, у кого много проектов.</div>
                <ul class="features">
                    <li>- До 3 студий</li>
                    <li>- До 50 игр (на 1 студию)</li>
                    <li>- Общий объем диска 110 ГБ</li>
                    <li>- Расширенная статистика</li>
                    <li>- Возможность добавлять сотрудников</li>
                </ul>
                <?php if ($currentPlan === 'indie_pro'): ?>
                    <button class="btn-outline" disabled>Активировано</button>
                <?php else: ?>
                    <button class="btn-primary"
                        onclick="location.href='billing/checkout.php?plan=indie_pro'">
                        Купить
                    </button>
                <?php endif; ?>
            </div>

            <div class="plan <?= $currentPlan === 'indie_disk' ? 'active' : '' ?>">
                <h2>Инди Диск</h2>
                <div class="price">99 ₽ / мес</div>
                <div class="features">Для тех, кому нужно только выложить тяжёлые игры.</div>
                <ul class="features">
                    <li>- 1 студия</li>
                    <li>- До 5 игр</li>
                    <li>- Общий объем диска 40 ГБ</li>
                    <li> </li>
                </ul>
                <button class="btn-outline">Купить</button>
            </div>

            <div class="plan">
                <h2>Инди Сервер</h2>
                <div class="price">Индивидуально</div>
                <div class="features">Для тех, кому нужен выделенный сервер для проектов.</div>
                <ul class="features">
                    <li>- Выделенный сервер</li>
                    <li>- От 0.5 ГБ ОЗУ</li>
                    <li>- FTP, SSH, SSL, WSS</li>
                    <li>- От 10 ГБ HDD/SSD</li>
                    <li> </li>
                </ul>
                <button class="btn-outline">Купить</button>
            </div>

        </section>

        <section class="section">
            <h2>API интеграция</h2>
            <p>Валидация токена и управление проектами через REST API.</p>

            <div class="api-box">
                POST /api/validate_token
                {
                "token": "your_studio_token"
                }
            </div>
        </section>

        <section class="section">
            <h2>Почему это нужно студии?</h2>
            <p>
                Платформа позволяет централизованно управлять играми, обновлениями, правами доступа
                и аналитикой. Подходит для независимых разработчиков и крупных команд.
            </p>
        </section>

        <section class="section">
            <h2>Как получить доступ после оплаты</h2>
            <p>
                После успешной оплаты подписка активируется автоматически в течение 1–2 минут.
                Доступ предоставляется в личном кабинете разработчика.
            </p>
            <p>
                Уведомление о подключении тарифа отправляется на электронную почту,
                указанную при регистрации. Управление подпиской доступно в разделе
                «Настройки → Подписка».
            </p>
            <p>
                Все услуги являются цифровыми и не требуют физической доставки.
            </p>
        </section>

        <section class="section">
            <h2>Условия подписки</h2>
            <p>
                Подписка оформляется на ежемесячной основе.
                Оплата списывается автоматически каждый месяц.
            </p>
            <p>
                Отменить подписку можно в любой момент в личном кабинете.
                После отмены доступ сохраняется до конца оплаченного периода.
            </p>
            <p>
                Возврат средств осуществляется в соответствии с действующим законодательством РФ.
            </p>
        </section>

        <section class="section">
            <h2>Правовая информация</h2>
            <p>
                Оплачивая услуги, пользователь принимает условия
                <a href="/oferta.txt" style="color: var(--accent);">Публичной оферты</a>
                и <a href="/privacy" style="color: var(--accent);">Политики конфиденциальности</a>.
            </p>
        </section>

        <footer>
            © 2025-2026 Dustore (Dust Store). Исходный код является <a style="color: var(--accent);" href="https://github.com/AlexanderLivanov/dustore">открытым</a> и распространяется под лицензией Apache 2.0 License.
            <p> </p>
            <h2>Реквизиты Платформы:</h2>
            <p>ИП Ливанов Александр Алексеевич</p>
            <p>ИНН 771392840109</p>
            <p>ОГРНИП 326774600034839</p>
            <p>р/с 40802810400009281106</p>
            <p>АО «ТБанк»</p>
        </footer>

    </div>

</body>

</html>