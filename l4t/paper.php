<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Request #1024</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Source+Code+Pro&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            background: #e5e5e5;
            font-family: 'Playfair Display'
        }

        .page {
            width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 60px 70px;
            position: relative;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .2);
        }

        .page::before {
            content: '';
            position: absolute;
            inset: 20px;
            border: 2px solid #ddd;
        }

        .header {
            display: flex;
            justify-content: space-between;
        }

        .meta {
            font-family: 'Source Code Pro';
            font-size: 12px;
        }

        h1 {
            margin-top: 40px
        }

        .section {
            margin-bottom: 28px
        }

        .section h3 {
            font-size: 14px;
            text-transform: uppercase;
        }

        .stamp {
            position: absolute;
            right: 60px;
            bottom: 80px;
            border: 3px solid #4fa3ff;
            color: #4fa3ff;
            padding: 14px 18px;
            transform: rotate(-8deg);
            font-family: 'Source Code Pro';
        }
    </style>
</head>

<body>

    <div class="page">

        <div class="header">
            <div>DUSTORE</div>
            <div class="meta">
                REQUEST #1024<br>
                STATUS: OPEN
            </div>
        </div>

        <h1>Ищу программиста</h1>

        <div class="section">
            <h3>Описание проекта</h3>
            <p>Ищу Unity-разработчика для инди-игры.</p>
        </div>

        <div class="section">
            <h3>Ожидания</h3>
            <p>Частичная занятость, доля в проекте.</p>
        </div>

        <div class="section">
            <h3>Автор</h3>
            <p><a href="index.php#profile">@nickname</a></p>
        </div>

        <a href="response.php?id=1024">→ Написать отклик</a>

        <div class="stamp">OPEN<br>DUSTORE</div>

    </div>

</body>

</html>