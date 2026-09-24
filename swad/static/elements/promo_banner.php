<?php

/**
 * swad/static/elements/promo_banner.php
 *
 * Промо-баннер продвигаемой игры. Инклюдить в index.php СРАЗУ ПОСЛЕ header.php,
 * до вашего текущего <main>:
 *
 *     <?php require_once('swad/static/elements/header.php'); ?>
 *     <?php require_once('swad/static/elements/promo_banner.php'); ?>
 *     <main> ... ваш текущий контент ... </main>
 *
 * Файл самодостаточен: сам ищет активный слот, сам решает — рисовать себя или нет.
 * Если на сейчас нет оплаченного слота — выводит пустую строку и не задевает
 * остальную вёрстку главной вообще никак.
 *
 * ЧТО ПОЧИНЕНО ПО СРАВНЕНИЮ С ИСХОДНЫМ HTML:
 * Раньше вся анимация держалась на перехвате wheel (preventDefault) и ручном
 * стейт-машине idle→shaking→flying→done→returning. Это классический
 * scroll-jacking: ломается на трекпадах (разный deltaY на тик), на тач-устройствах
 * его просто выключили (state='done' сразу, юзер эффект вообще не видел),
 * и блокирует нативный скролл — плохо для доступности и просто неприятно.
 *
 * Сделано иначе — паттерн "pin & progress": контейнер выше вьюпорта, карточка
 * внутри держится через position:sticky, а JS на обычном passive-скролле
 * (БЕЗ preventDefault) читает прогресс прохождения контейнера 0..1 и пишет
 * его в CSS-переменную --p. Вся анимация — чистый CSS calc() от --p.
 * Работает одинаково на мыши, трекпаде и тач-скролле, потому что это просто
 * обычный скролл страницы, а не отдельная логика поверх него.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../controllers/analytics.php');

$__pb_conn = (new Database())->connect();

/* + рейтинг (game_reviews) — тот же JOIN-паттерн, что в Game::queryGames()
   и на самой странице игры: 10-балльная шкала, 5 звёзд в разметке. */
$__pb_stmt = $__pb_conn->prepare("
    SELECT gp.id AS promo_id, g.id AS game_id, g.name, g.short_description, g.description,
           g.path_to_cover, g.banner_url, g.icon_url, g.downloads, g.platforms,
           ROUND(rv.avg_rating, 1) AS avg_rating, COALESCE(rv.n, 0) AS reviews_count
    FROM game_promotions gp
    JOIN games g ON g.id = gp.game_id
    LEFT JOIN (SELECT game_id, AVG(rating) AS avg_rating, COUNT(*) AS n
                 FROM game_reviews GROUP BY game_id) rv ON rv.game_id = g.id
    WHERE gp.status = 'active'
      AND NOW() >= TIMESTAMP(gp.slot_date, '12:00:00')
      AND NOW() <  TIMESTAMP(DATE_ADD(gp.slot_date, INTERVAL 1 DAY), '12:00:00')
    LIMIT 1
");
$__pb_stmt->execute();
$promo = $__pb_stmt->fetch(PDO::FETCH_ASSOC);

if ($promo):
    // Показ считаем на сервере: рендер баннера в разметке = гарантированный impression,
    // в отличие от JS-события, которое можно потерять (блокировщики, медленный JS).
    // trackOncePerSession — иначе каждый F5 страницы плодит новый показ в одной и той же сессии.
    $__pb_analytics = new Analytics($__pb_conn);
    $__pb_analytics->trackOncePerSession('promotion', (int)$promo['promo_id'], 'impression');

    // Запуски — из общего модуля аналитики (event_type=launch), не из games.downloads:
    // это отдельная метрика («сколько раз игру ЗАПУСТИЛИ», актуально для веб-игр и
    // для игр, скачанных раньше), которую и просили вынести отдельно от скачиваний.
    $__pb_launches = $__pb_analytics->summarize('game', (int)$promo['game_id'])['launch']['count'] ?? 0;

    $pbImage = $promo['banner_url'] ?: $promo['path_to_cover'] ?: '';
    $pbDesc  = trim((string)($promo['short_description'] ?? ''));
    if ($pbDesc === '') $pbDesc = trim(strip_tags((string)($promo['description'] ?? '')));
    if (mb_strlen($pbDesc) > 140) $pbDesc = mb_substr($pbDesc, 0, 140) . '…';

    $pbRating = $promo['avg_rating'] !== null ? (float)$promo['avg_rating'] : null;
    $pbStars  = $pbRating !== null ? max(0, min(5, (int)round($pbRating / 2))) : 0;

    /* Можно ли отсюда позвать оставить отзыв — та же логика, что $userCanReview
       в game.php (иначе кнопка вела бы на анкор #review-form-wrap, которого
       для этого посетителя там просто не будет: форма отзыва на странице игры
       рендерится только при владении игрой или для чисто веб-игр). Дублируем
       проверку тут же, а не выносим в общую функцию — она в три строки и
       больше нигде, кроме этих двух файлов, не нужна. */
    $pbCanReview = false;
    if (!empty($_SESSION['USERDATA']['id'])) {
        $__pb_platforms = array_map(fn($p) => strtolower(trim($p)), explode(',', (string)($promo['platforms'] ?? '')));
        $__pb_isWebOnly = in_array('web', $__pb_platforms, true) && count(array_filter($__pb_platforms, fn($p) => $p !== 'web')) === 0;
        if ($__pb_isWebOnly) {
            $pbCanReview = true;
        } else {
            $__pb_owns = $__pb_conn->prepare("SELECT 1 FROM library WHERE player_id = ? AND game_id = ? LIMIT 1");
            $__pb_owns->execute([$_SESSION['USERDATA']['id'], $promo['game_id']]);
            $pbCanReview = (bool)$__pb_owns->fetchColumn();
        }
    }
?>
    <style>
        .pb-wrap {
            position: relative;
            background: #14041d;
            --pb-flyX: 175%;
            --pb-flyY: -4%;
            --pb-flyZ: -380px;
            --pb-rotY: 180deg;
            --pb-rotX: 10deg;
            --pb-rotZ: -6deg;
            --pb-scaleEnd: 0.78;
        }

        @media (max-width:640px) {

            /* Мобильный: та же механика, но без полного 3D-кувырка — короче дистанция
       скролла и мягче углы, иначе на слабых телефонах будет дёргаться. */
            .pb-wrap {
                --pb-flyX: 40%;
                --pb-flyY: 6%;
                --pb-flyZ: -160px;
                --pb-rotY: 55deg;
                --pb-rotX: 4deg;
                --pb-rotZ: -2deg;
                --pb-scaleEnd: 0.86;
            }
        }

        /* Раньше 220vh — под непрерывный scroll-scrub на всю анимацию.
           Теперь анимация не привязана к позиции скролла напрямую (см. JS):
           карточка либо стоит на месте, либо улетает по CSS-переходу, как
           только скролл переваливает за небольшой порог. Запас нужен только
           на то, чтобы sticky-подставка успела «поймать» карточку — не на
           саму анимацию. */
        .pb-outer {
            position: relative;
            height: 130vh;
        }

        @media (max-width:640px) {
            .pb-outer {
                height: 118vh;
            }
        }

        .pb-stage {
            position: sticky;
            top: 0;
            height: 100vh;
            max-height: 880px;
            min-height: 460px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1700px;
            perspective-origin: 50% 50%;
            padding: 24px;
        }

        .pb-inner {
            position: relative;
            width: min(1100px, 100%);
            height: min(72vh, 620px);
            min-height: 360px;
            transform-style: preserve-3d;
        }

        .pb-title {
            position: absolute;
            top: 50%;
            left: 0;
            width: 52%;
            min-width: 280px;
            transform: translate(-32px, -50%);
            opacity: 0;
            pointer-events: none;
            text-align: left;
            transition: transform .4s ease, opacity .4s ease;
        }

        /* Раньше связано с --p непрерывно; теперь — тот же снэп, что у карточки:
           текст появляется одним переходом в момент, когда карточка улетела.
           БАГ (нашли по репорту Лео «баннер и текст под ним не кликабельны»):
           .pb-title изначально pointer-events:none (пока спрятан под карточкой) —
           а здесь сбрасывались только transform/opacity, pointer-events никогда
           не возвращался в auto. Карточка улетает (pointer-events:none у самой
           себя — тоже по дизайну), а текст, который появляется на её месте,
           был навсегда мёртв для кликов, включая ссылку «Как это работает?». */
        .pb-stage.pb-flown .pb-title {
            transform: translate(0, -50%);
            opacity: 1;
            pointer-events: auto;
        }

        @media (max-width:900px) {
            .pb-title {
                display: none;
            }
        }

        /* на узких экранах текст просто мешает */

        .pb-title h2 {
            margin: 0 0 14px;
            font-size: clamp(22px, 3vw, 38px);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -.03em;
            color: #f2f2f8;
        }

        .pb-title p {
            margin: 0;
            max-width: 440px;
            font-size: 14px;
            line-height: 1.6;
            color: #9c9cb6;
        }

        .pb-howlink {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 16px;
            font-size: 12px;
            font-weight: 700;
            color: #e88fc0;
            text-decoration: none;
            border-bottom: 1px solid rgba(232, 143, 192, .4);
            transition: color .2s, border-color .2s;
        }

        .pb-howlink:hover {
            color: #ff6fb8;
            border-color: rgba(255, 111, 184, .7);
        }

        /* Раньше transform считался непрерывно из calc(var(--p,0) * ...) на каждый
           кадр скролла — отсюда и «наполовину открыта», и хитбокс кнопки внутри,
           плывущий вместе с 3D-трансформом на промежуточных значениях --p.
           Теперь ровно два состояния: пристыкована (ниже, без трансформа —
           только наклон от мыши) и .pb-flown (готовая конечная поза, один
           CSS-переход). Никакого читаемого пользователем промежуточного кадра. */
        .pb-card {
            position: absolute;
            inset: 0;
            border-radius: 22px;
            overflow: hidden;
            transform-style: preserve-3d;
            transform-origin: 50% 50%;
            box-shadow: 0 40px 100px -28px rgba(0, 0, 0, .9), 0 0 0 1px rgba(255, 255, 255, .07) inset;
            transform: rotateY(var(--tiltY, 0deg)) rotateX(var(--tiltX, 0deg));
            opacity: 1;
            filter: blur(0);
            transition: transform .001s ease-out, opacity .3s ease, filter .3s ease, visibility 0s linear .3s;
        }

        .pb-card.pb-flown {
            transform:
                translate3d(var(--pb-flyX), var(--pb-flyY), var(--pb-flyZ)) rotateY(var(--pb-rotY)) rotateX(var(--pb-rotX)) rotateZ(var(--pb-rotZ)) scale(var(--pb-scaleEnd));
            opacity: 0;
            filter: blur(6px);
            pointer-events: none;
            visibility: hidden;
            transition: transform .55s cubic-bezier(.22, .61, .36, 1), opacity .4s ease, filter .4s ease, visibility 0s linear .5s;
        }

        .pb-bg {
            position: absolute;
            inset: 0;
            background:
                <?php if ($pbImage): ?> linear-gradient(to top, rgba(20, 4, 29, .94) 0%, rgba(20, 4, 29, .55) 40%, rgba(20, 4, 29, .15) 68%, transparent 82%),
                url('<?= htmlspecialchars($pbImage) ?>') center/cover no-repeat;
            <?php else: ?>
            /* Фирменный градиент Dustore (--gradient2 из pages.css), без цветных орбов —
           баннер должен выглядеть частью платформы, а не отдельным неоновым виджетом. */
            linear-gradient(160deg, #14041d 0%, #400c4a 45%, #74155d 78%, #c32178 100%);
            <?php endif; ?>
        }

        /* Вся пустая площадь карточки — тоже ссылка на игру, поверх .pb-bg,
           но под содержимым (.pb-content) и инфо-ссылкой: у них свой href,
           вложенные <a> в HTML невалидны, поэтому это отдельный слой снизу,
           а не обёртка вокруг остального. */
        .pb-bg-link {
            position: absolute;
            inset: 0;
            z-index: 1;
            outline: none;
        }

        .pb-bg-link:focus-visible {
            box-shadow: inset 0 0 0 3px rgba(195, 33, 120, .8);
        }

        .pb-info-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px 6px 10px;
            border-radius: 999px;
            background: rgba(20, 4, 29, .55);
            border: 1px solid rgba(248, 249, 250, .22);
            backdrop-filter: blur(8px);
            color: #f8f9fa;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .02em;
            text-decoration: none;
            transition: background .2s, border-color .2s;
        }

        .pb-info-btn:hover {
            background: rgba(248, 249, 250, .14);
            border-color: rgba(248, 249, 250, .4);
        }

        .pb-info-btn svg {
            flex: none;
        }

        /* Бейдж — фирменный розовый (var(--primary)), без неонового свечения. */
        .pb-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 12px;
            margin-bottom: 14px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #f8f9fa;
            background: rgba(195, 33, 120, .24);
            border: 1px solid rgba(195, 33, 120, .55);
        }

        .pb-eyebrow::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #c32178;
        }

        .pb-content {
            position: absolute;
            left: 0;
            bottom: 0;
            z-index: 2;
            padding: clamp(20px, 4vw, 44px);
            max-width: 600px;
        }

        .pb-content h1 {
            margin: 0 0 10px;
            font-size: clamp(26px, 4.2vw, 50px);
            line-height: 1.04;
            font-weight: 800;
            letter-spacing: -.03em;
            color: #f8f9fa;
        }

        .pb-content .pb-desc {
            margin: 0 0 22px;
            max-width: 440px;
            font-size: 14px;
            line-height: 1.55;
            color: #e5d9e0;
        }

        /* Кнопка — тот же .btn, что везде на платформе (pages.css), только с флексом под иконку. */
        .pb-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .pb-btn svg {
            flex: none;
        }

        .pb-stats {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            margin-top: 14px;
            font-size: 12.5px;
            color: #c9b8c4;
        }

        .pb-stat {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .pb-stat svg {
            flex: none;
        }

        .pb-stars {
            display: inline-flex;
            gap: 1px;
            color: #f5b400;
        }

        .pb-stat .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #35d07f;
        }

        /* Маленькая кнопка «оставить отзыв» — тот же акцентный розовый и то же
           подчёркивание, что у pb-howlink, только внутри строки статистики:
           так это читается как «вот рейтинг игры — а вот тут можно на него
           повлиять», а не как отдельный большой CTA, спорящий с play-кнопкой. */
        .pb-review-cta {
            color: #e88fc0;
            text-decoration: none;
            font-weight: 700;
            border-bottom: 1px solid rgba(232, 143, 192, .4);
            transition: color .2s, border-color .2s;
        }

        .pb-review-cta:hover {
            color: #ff6fb8;
            border-color: rgba(255, 111, 184, .7);
        }

        /* ── Мини-баннер, появляется, когда карточка уже «улетела» ──
           Раньше top:76px и z-index:90 — при высоте хедера ~80px плашка
           наезжала на его нижний край и по z-index была ВЫШЕ хедера (10),
           поэтому клик по переключателю темы попадал в неё, а не в кнопку —
           открывшееся меню темы тут же теряло клик и схлопывалось.
           Теперь: ниже хедера с запасом и z-index ниже хедера, а не выше —
           плашка больше физически не может перекрыть ничего в хедере. */
        .pb-mini {
            position: fixed;
            top: 96px;
            left: 0;
            right: 0;
            z-index: 5;
            display: flex;
            justify-content: center;
            padding: 0 24px;
            opacity: 0;
            transform: translateY(-70%) scale(.96);
            pointer-events: none;
            transition: opacity .35s ease, transform .4s cubic-bezier(.2, .8, .3, 1);
        }

        .pb-mini.pb-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .pb-mini-inner {
            width: min(760px, 100%);
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 14px 8px 8px;
            border-radius: 16px;
            background: rgba(18, 18, 32, .85);
            backdrop-filter: blur(20px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, .1);
            box-shadow: 0 20px 50px -20px rgba(0, 0, 0, .9);
        }

        .pb-mini-thumb {
            width: 52px;
            height: 36px;
            flex: none;
            border-radius: 9px;
            background-size: cover;
            background-position: center;
            background-color: #1a1233;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .12);
        }

        .pb-mini-title {
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pb-mini-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #e88fc0;
            display: block;
        }

        .pb-mini-btn {
            margin-left: auto;
            flex: none;
            padding: 8px 16px;
            border: 0;
            border-radius: 999px;
            background: #c32178;
            color: #fff;
            font-size: 12.5px;
            font-weight: 800;
            text-decoration: none;
            transition: background .2s;
        }

        .pb-mini-btn:hover {
            background: #e62e8a;
        }

        .pb-mini-close {
            margin-left: 4px;
            flex: none;
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
            color: #c9b8c4;
            cursor: pointer;
            transition: background .2s, color .2s;
        }

        .pb-mini-close:hover {
            background: rgba(255, 255, 255, .18);
            color: #f8f9fa;
        }
    </style>

    <div class="pb-wrap">
        <div class="pb-outer" id="pbOuter">
            <div class="pb-stage" id="pbStage">
                <div class="pb-inner">

                    <div class="pb-title">
                        <h2>Игры, которые<br>стоит заметить</h2>
                        <p>Каждые сутки на этом месте — одна игра, которую продвигает её разработчик.
                            Дальше — обычный каталог Dustore, как всегда.</p>
                        <a class="pb-howlink" href="/promo">Как это работает? →</a>
                    </div>

                    <div class="pb-card" id="pbCard">
                        <div class="pb-bg"></div>
                        <a class="pb-bg-link" id="pbBgLink" href="/g/<?= (int)$promo['game_id'] ?>" aria-label="Открыть игру «<?= htmlspecialchars($promo['name']) ?>»"></a>
                        <a class="pb-info-btn" href="/promo" target="_blank" rel="noopener" title="Что такое продвижение на Dustore">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="11" x2="12" y2="16.5" />
                                <circle cx="12" cy="7.5" r=".6" fill="currentColor" stroke="none" />
                            </svg>
                            промо
                        </a>
                        <div class="pb-content">
                            <div class="pb-eyebrow">Продвигается сейчас</div>
                            <h1><?= htmlspecialchars($promo['name']) ?></h1>
                            <?php if ($pbDesc): ?><p class="pb-desc"><?= htmlspecialchars($pbDesc) ?></p><?php endif; ?>
                            <a class="btn pb-btn" id="pbPlayBtn" href="/g/<?= (int)$promo['game_id'] ?>">
                                <svg width="13" height="15" viewBox="0 0 14 16" fill="currentColor">
                                    <path d="M13.5 7.13 1.5.2A1 1 0 0 0 0 1.06v13.88a1 1 0 0 0 1.5.86l12-6.93a1 1 0 0 0 0-1.74Z" />
                                </svg>
                                Смотреть игру
                            </a>
                            <?php if ($pbRating !== null || $__pb_launches > 0 || !empty($promo['downloads']) || $pbCanReview): ?>
                                <div class="pb-stats">
                                    <?php if ($pbRating !== null): ?>
                                        <span class="pb-stat pb-stat--rating" title="Оценка <?= htmlspecialchars((string)$pbRating) ?> из 10 · <?= (int)$promo['reviews_count'] ?> отзывов">
                                            <span class="pb-stars" aria-hidden="true">
                                                <?php for ($i = 0; $i < 5; $i++): ?>
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="<?= $i < $pbStars ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.6">
                                                        <path d="M12 2.5l2.9 6.05 6.6.77-4.85 4.6 1.28 6.58L12 17.3l-5.93 3.2 1.28-6.58-4.85-4.6 6.6-.77z" />
                                                    </svg>
                                                <?php endfor; ?>
                                            </span>
                                            <?= htmlspecialchars((string)$pbRating) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($__pb_launches > 0): ?>
                                        <span class="pb-stat">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M5 3l14 9-14 9V3z" />
                                            </svg>
                                            <?= number_format((int)$__pb_launches, 0, ',', ' ') ?> запусков
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($promo['downloads'])): ?>
                                        <span class="pb-stat"><span class="dot"></span><?= number_format((int)$promo['downloads'], 0, ',', ' ') ?> скачиваний</span>
                                    <?php endif; ?>
                                    <?php if ($pbCanReview): ?>
                                        <a class="pb-stat pb-review-cta" id="pbReviewCta" href="/g/<?= (int)$promo['game_id'] ?>#review-form-wrap">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M12 2.5l2.9 6.05 6.6.77-4.85 4.6 1.28 6.58L12 17.3l-5.93 3.2 1.28-6.58-4.85-4.6 6.6-.77z" />
                                            </svg>
                                            Оставить отзыв
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="pb-mini" id="pbMini">
        <div class="pb-mini-inner">
            <div class="pb-mini-thumb" style="<?= $pbImage ? "background-image:url('" . htmlspecialchars($pbImage) . "')" : '' ?>"></div>
            <div>
                <span class="pb-mini-label">Продвигается</span>
                <span class="pb-mini-title"><?= htmlspecialchars($promo['name']) ?></span>
            </div>
            <a class="pb-mini-btn" id="pbMiniBtn" href="/g/<?= (int)$promo['game_id'] ?>">Смотреть →</a>
            <button type="button" class="pb-mini-close" id="pbMiniClose" aria-label="Скрыть">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round">
                    <path d="M4 4l16 16M20 4L4 20" />
                </svg>
            </button>
        </div>
    </div>

    <script src="/swad/js/analytics.js"></script>
    <script>
        (function() {
            var PROMO_ID = <?= (int)$promo['promo_id'] ?>;
            var GAME_ID = <?= (int)$promo['game_id'] ?>;
            var DISMISS_KEY = 'pbMiniDismissed:' + PROMO_ID;

            var outer = document.getElementById('pbOuter');
            var stage = document.getElementById('pbStage');
            var card = document.getElementById('pbCard');
            var mini = document.getElementById('pbMini');
            var miniClose = document.getElementById('pbMiniClose');

            var dismissed = false;
            try {
                dismissed = sessionStorage.getItem(DISMISS_KEY) === '1';
            } catch (e) {}

            // ── Два состояния вместо непрерывного прогресса: «на месте» и «улетела».
            // Порог низкий и с гистерезисом (ENTER > EXIT) — чтобы карточка улетала
            // почти сразу же, как начали скроллить (не зависала на полпути), но не
            // дребезжала туда-обратно, если скролл качнулся ровно на границе. ──
            var ENTER = 0.10;
            var EXIT = 0.02;
            var flown = false;
            var raf = null;

            function update() {
                raf = null;
                var rect = outer.getBoundingClientRect();
                var travel = outer.offsetHeight - stage.offsetHeight;
                var scrolled = -rect.top;
                var p = travel > 0 ? Math.min(1, Math.max(0, scrolled / travel)) : 0;

                if (!flown && p >= ENTER) {
                    flown = true;
                } else if (flown && p < EXIT) {
                    flown = false;
                } else {
                    return; // состояние не поменялось — лишний reflow не нужен
                }

                card.classList.toggle('pb-flown', flown);
                stage.classList.toggle('pb-flown', flown);
                if (!dismissed) mini.classList.toggle('pb-visible', flown);
            }

            function onScroll() {
                if (raf) return;
                raf = requestAnimationFrame(update);
            }
            window.addEventListener('scroll', onScroll, {
                passive: true
            });
            window.addEventListener('resize', onScroll, {
                passive: true
            });
            update();

            if (miniClose) {
                miniClose.addEventListener('click', function() {
                    dismissed = true;
                    mini.classList.remove('pb-visible');
                    try {
                        sessionStorage.setItem(DISMISS_KEY, '1');
                    } catch (e) {}
                });
            }

            // ── 3D-параллакс от мыши — только пока карточка стоит на месте ──
            if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                var pbRaf = null;
                stage.addEventListener('mousemove', function(e) {
                    if (pbRaf) return;
                    pbRaf = requestAnimationFrame(function() {
                        pbRaf = null;
                        if (flown) return; // не мешаем параллаксом, когда карточка уже летит
                        var r = stage.getBoundingClientRect();
                        var mx = (e.clientX - r.left) / r.width - 0.5;
                        var my = (e.clientY - r.top) / r.height - 0.5;
                        card.style.setProperty('--tiltX', (-my * 6).toFixed(2) + 'deg');
                        card.style.setProperty('--tiltY', (mx * 8).toFixed(2) + 'deg');
                    });
                });
                stage.addEventListener('mouseleave', function() {
                    card.style.setProperty('--tiltX', '0deg');
                    card.style.setProperty('--tiltY', '0deg');
                });
            }

            // ── Аналитика ──
            if (window.DustoreAnalytics) {
                DustoreAnalytics.observeView(card, 'promotion', PROMO_ID);
                var clicked = false;

                function trackClick() {
                    if (clicked) return;
                    clicked = true;
                    DustoreAnalytics.track('promotion', PROMO_ID, 'click');
                }
                ['pbPlayBtn', 'pbBgLink', 'pbMiniBtn', 'pbReviewCta'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.addEventListener('click', trackClick);
                });
            }
        })();
    </script>
<?php endif; ?>