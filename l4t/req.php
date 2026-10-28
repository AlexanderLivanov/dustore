<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Request #1024</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Source+Code+Pro&display=swap');

        body {
            margin: 0;
            background: #e5e5e5;
            font-family: 'Playfair Display', serif;
        }

        /* ЛИСТ */
        .page {
            width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 60px 70px;
            position: relative;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .2);
        }

        /* декоративная рамка */
        .page::before {
            content: '';
            position: absolute;
            inset: 20px;
            border: 2px solid #ddd;
            pointer-events: none;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .logo {
            font-weight: 700;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .meta {
            font-family: 'Source Code Pro', monospace;
            font-size: 12px;
            color: #555;
            text-align: right;
        }

        /* TITLE */
        h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .subtitle {
            font-style: italic;
            color: #555;
            margin-bottom: 30px;
        }

        /* CONTENT */
        .section {
            margin-bottom: 28px;
        }

        .section h3 {
            font-size: 14px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .section p {
            font-size: 17px;
            line-height: 1.6;
        }

        /* STAMP */
        .stamp {
            position: absolute;
            right: 60px;
            bottom: 80px;
            border: 3px solid #7c7cff;
            color: #7c7cff;
            padding: 14px 18px;
            transform: rotate(-8deg);
            font-family: 'Source Code Pro', monospace;
            font-weight: 700;
        }

        /* FOOTER */
        .footer {
            margin-top: 60px;
            font-size: 12px;
            color: #777;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                box-shadow: none;
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <div class="page">

        <div class="header">
            <div class="logo">DUSTORE</div>
            <div class="meta">
                REQUEST ID: #1024<br>
                CREATED: 12.01.2026
            </div>
        </div>

        <h1>Ищу программиста</h1>
        <div class="subtitle">Заявка на участие в проекте</div>

        <div class="section">
            <h3>Описание проекта</h3>
            <p>
                Ищу разработчика для совместной работы над инди-игрой.
                Проект в стадии прототипа, жанр — 2D action.
                Нужен человек с опытом Unity / C#.
            </p>
        </div>

        <div class="section">
            <h3>Ожидания</h3>
            <p>
                Частичная занятость, долгосрочное сотрудничество,
                возможна доля в проекте.
            </p>
        </div>

        <div class="section">
            <h3>Автор заявки</h3>
            <p>@nickname (Game Developer)</p>
        </div>

        <div class="stamp">
            VERIFIED<br>DUSTORE
        </div>

        <div class="footer">
            <div>dustore.ru</div>
            <div>Page 1 / 1</div>
        </div>

    </div>

</body>

</html>