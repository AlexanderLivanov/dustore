<?php

/**
 * promo.php — публичная страница о продвижении игр на Dustore.
 * На неё ведёт кнопка «промо (i)» с баннера на главной.
 * Корневая страница — require по образцу explore.php/index.php (относительные пути).
 */
session_start();
require_once('swad/config.php');
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Продвижение игр — Dustore</title>
    <?php require_once('swad/static/elements/header.php'); ?>
    <style>
        /* Скоуп страницы — свой namespace, глобальные селекторы не трогаем. */
        .promo-page {
            max-width: 760px;
            margin: 0 auto;
            padding: calc(var(--header-h, 68px) + 48px) 24px 100px;
            color: #f2f2f8;
        }

        .promo-page h1 {
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 800;
            letter-spacing: -.03em;
            margin: 0 0 16px;
        }

        .promo-page .lead {
            font-size: 16px;
            line-height: 1.6;
            color: #b8b8cc;
            margin: 0 0 40px;
        }

        .promo-page h2 {
            font-size: 20px;
            font-weight: 800;
            margin: 40px 0 12px;
            letter-spacing: -.01em;
        }

        .promo-page p {
            font-size: 14.5px;
            line-height: 1.7;
            color: #c3c3d8;
            margin: 0 0 14px;
        }

        .promo-page ul {
            margin: 0 0 14px;
            padding-left: 20px;
            color: #c3c3d8;
            font-size: 14.5px;
            line-height: 1.7;
        }

        .promo-honest {
            border-radius: 16px;
            padding: 20px 22px;
            margin: 24px 0;
            background: rgba(251, 191, 36, .08);
            border: 1px solid rgba(251, 191, 36, .28);
        }

        .promo-honest strong {
            color: #fbbf24;
        }

        .promo-price {
            display: flex;
            align-items: center;
            gap: 16px;
            border-radius: 16px;
            padding: 22px 24px;
            margin: 20px 0;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .09);
        }

        .promo-price .amt {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .promo-price .unit {
            font-size: 13px;
            color: #8f8fa8;
        }

        .promo-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            padding: 13px 26px;
            border-radius: 999px;
            background: linear-gradient(135deg, #9b6cff, #22d3ee);
            color: #08080f;
            font-weight: 800;
            font-size: 14.5px;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <main class="promo-page">
        <h1>Продвижение игр на Dustore</h1>
        <p class="lead">
            Один слот в сутки на главной странице Dustore, который разработчик может выкупить
            для своей игры. Ниже — как это работает и, что важнее, чего это <em>не</em> гарантирует.
        </p>

        <h2>Как это устроено</h2>
        <ul>
            <li>В любой момент времени на баннере главной — ровно одна игра.</li>
            <li>Слот занимает сутки: с 12:00 до 12:00 следующего дня.</li>
            <li>Разработчик выбирает свободную дату в календаре в панели shaurMA и оплачивает её.</li>
            <li>Игра появляется на баннере автоматически, как только наступает её слот.</li>
        </ul>

        <div class="promo-honest">
            <strong>Честно о цифрах.</strong> Мы только запускаем продвижение и пока не готовы обещать
            конкретное число показов или кликов — ни «1000 показов», ни «500 кликов за сутки». Трафик
            на главную у нас есть, но сколько его достанется конкретно вашему слоту — заранее не знает
            никто, в том числе мы. Это не рекламная формулировка «результаты не гарантированы» ради
            юридической подстраховки — это буквально то, что есть: мы продаём место, а не результат.
            По мере роста платформы и данных мы планируем показывать среднюю статистику по прошлым
            слотам прямо в календаре бронирования — чтобы решение опиралось на цифры, а не на веру.
        </div>

        <h2>Что вы увидите в аналитике</h2>
        <p>После покупки слота в разделе «Продвижение» в панели разработчика доступна статистика:
            показы баннера, «досмотры» (наведение курсора или ≥2 секунд в поле зрения), клики на
            страницу игры, а также скачивания/запуски и наигранное время — уже на странице самой игры,
            не только в момент показа на баннере.</p>

        <h2>Стоимость</h2>
        <div class="promo-price">
            <div class="amt">99 ₽</div>
            <div class="unit">за сутки показа<br>одна игра — один активный слот</div>
        </div>

        <h2>Хотите продвинуть свою игру?</h2>
        <p>Зайдите в панель разработчика shaurMA → раздел «Продвижение», выберите игру и свободную
            дату в календаре.</p>
        <a class="promo-cta" href="/devs/promotion">Открыть панель продвижения →</a>
    </main>

</body>

</html>