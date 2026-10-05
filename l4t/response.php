<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Response</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Source+Code+Pro&display=swap" rel="stylesheet">

    <style>
        body {
            background: #e5e5e5;
            margin: 0;
            font-family: 'Playfair Display'
        }

        .page {
            width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 60px 70px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .2);
            position: relative;
        }

        .page::before {
            content: '';
            position: absolute;
            inset: 20px;
            border: 2px solid #ddd;
        }

        textarea {
            width: 100%;
            border: none;
            font-family: 'Playfair Display';
            font-size: 17px;
        }

        .stamp {
            position: absolute;
            right: 60px;
            bottom: 80px;
            border: 3px solid #55aa55;
            color: #55aa55;
            padding: 14px 18px;
            transform: rotate(-6deg);
            font-family: 'Source Code Pro';
        }
    </style>
</head>

<body>

    <div class="page">

        <h1>Отклик на заявку</h1>

        <h3>О себе</h3>
        <textarea rows="4"></textarea>

        <h3>Почему проект интересен</h3>
        <textarea rows="4"></textarea>

        <h3>Контакты</h3>
        <textarea rows="2"></textarea>

        <div class="stamp">RESPONSE</div>

    </div>

</body>

</html>