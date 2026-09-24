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
 * ИСТОРИЯ АНИМАЦИИ (v3, текущая):
 * v1 держалась на перехвате wheel (preventDefault) и ручной стейт-машине —
 * классический scroll-jacking: ломался на трекпадах (разный deltaY на тик),
 * на тач-устройствах эффект просто отключали, блокировал нативный скролл.
 * v2 — паттерн "pin & progress": sticky-подставка + непрерывный прогресс
 * 0..1 в CSS-переменную --p. Чинил v1, но давал побочный текстовый блок
 * (.pb-title), который проявлялся на месте улетевшей карточки, и требовал
 * лишних vh скролл-разгона под sticky-трюк.
 *
 * v3 — по просьбе Лео: только баннер, без второго текстового экрана и без
 * лишнего пространства под него. Пробовали отдать переключение нативному
 * CSS scroll-snap (порог = пройденная доля дистанции между точками) — по
 * ощущениям на реальном железе получилось не то, что описывал Лео: три
 * чётких попытки, а не «система сама решает». Вернулись к явному счётчику,
 * но не «считать сырые wheel-события» как в v1 (ломается на трекпаде — там
 * один свайп = десятки мелких deltaY), а накапливать СУММУ |deltaY| до
 * порога ~260px (примерно три щелчка колеса мыши). Пока не набрали порог —
 * страница залочена (html.pb-locked, скролла физически нет), сама попытка
 * только чуть подталкивает карточку (--pbNudge, пропорционально прогрессу
 * к порогу — не резкий дискретный «тик 1/тик 2», а плавное нарастание,
 * одинаково читается что от одного крупного скролла мышью, что от пары
 * мелких трекпадных). Не набрали и отпустили колесо — прогресс гаснет
 * через паузу, следующая попытка начинается с нуля (это и даёт «первые
 * два скролла просто трясут» — они не суммируются в вечный счётчик).
 * Набрали порог — коммит: карточка улетает существующим CSS-переходом,
 * И СИНХРОННО с ним страницу САМУ ПРОНОСИТ вниз, на верх <main> (rAF,
 * ~550ms, тот же тайминг, что transform у карточки — animateScrollTo()
 * ниже). Первая версия коммита прыгала на scrollTop мгновенно — на
 * реальном железе Лео это ощущалось как рандомный скачок без эффекта
 * (сам полёт карточки просто не успевал быть увиденным, а инерция того
 * же жеста колеса/трекпада докручивала ПОСЛЕ прыжка мимо точки стыковки —
 * оттуда и «не доставить чётко, выровняться сложно»). Теперь на время
 * carry-анимации `busy=true`: весь wheel/touch глотается без исключений,
 * так что докатывается ровно один раз, без дребезга от хвоста жеста;
 * html.pb-locked остаётся включён все ~550ms и снимается только когда
 * анимация реально доехала. Обратно у самой границы — тот же накопитель,
 * только на deltaY<0 и только пока scrollY≈0: слабый рывок вверх гасится,
 * решительный — тот же carry в обратную сторону, карточка проявляется
 * тем же переходом задом наперёд. Клавиатурный скролл (PageDown/Space/
 * стрелки) через порог не гоняем — сразу пропускаем, блокировать его
 * ради красивости было бы просто вредно.
 *
 * ВАЖНО про прыжок к <main> (баг, на который наткнулись при первом деплое v3):
 * страница у нас (и, похоже, не только этот файл) не в полном соответствии
 * со standards mode для скролла — прокручивается фактически <body>, а не
 * window/<html>. window.scrollTo()/window.scrollY в такой раскладке —
 * тихий no-op: JS думает, что прыгнул, страница на самом деле стоит на
 * месте. Поэтому весь скролл здесь идёт через document.scrollingElement
 * (см. scrollRoot() ниже) — это то же самое, что использует сам браузер
 * для Home/End/колеса, так что мы гарантированно двигаем ТОТ элемент,
 * который реально скроллится, а не гадаем между html и body.
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
        /* Пока не набран порог накопленного скролла — страница физически залочена.
           html и body оба на случай браузерных различий в том, какой из них
           реальный scrolling box. Класс вешается/снимается из JS ниже. */
        html.pb-locked,
        html.pb-locked body {
            overflow: hidden;
            height: 100%;
        }

        .pb-wrap {
            position: relative;
            background: #14041d;
            /* Ровно один вьюпорт, без max/min-height кэпов: это snap-секция, и маркер
               #pbMainTopMarker сразу после неё обязан начинаться НИЖЕ первого экрана —
               иначе на высоких вьюпортах (там, где раньше 880px-кэп срабатывал раньше,
               чем кончался вьюпорт) IntersectionObserver видел маркер уже на первом
               кадре, и баннер оказывался «улетевшим» без единого скролла. Карточка
               внутри (.pb-inner) сама ограничена по размеру и просто центрируется —
               визуально она не станет больше, даже когда секция выше её на 4K-мониторе. */
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1700px;
            perspective-origin: 50% 50%;
            padding: 24px;
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

        /* Пустой маркер ровно на границе с <main> — читаем его позицию в JS
           (getBoundingClientRect), чтобы прыгнуть туда ровно, без нативного
           snap: сами решаем момент и точку прыжка, а не полагаемся на то,
           как конкретный браузер трактует scroll-snap-stop. */
        .pb-main-marker {
            display: block;
        }

        .pb-inner {
            position: relative;
            width: min(1100px, 100%);
            height: min(72vh, 620px);
            min-height: 360px;
            transform-style: preserve-3d;
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
            /* --pbNudge — плавный "потряхивание": JS двигает его пропорционально
               накопленному, ещё не докоммиченному скроллу (0 → -14px к порогу),
               используя уже существующий .12s transition — отдельная @keyframes
               анимация тут не нужна. */
            transform: translateY(var(--pbNudge, 0px)) rotateY(var(--tiltY, 0deg)) rotateX(var(--tiltX, 0deg));
            opacity: 1;
            filter: blur(0);
            transition: transform .12s ease-out, opacity .3s ease, filter .3s ease, visibility 0s linear .3s;
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

        /* Маленькая кнопка «оставить отзыв» — акцентный розовый с подчёркиванием,
           внутри строки статистики: так это читается как «вот рейтинг игры —
           а вот тут можно на него повлиять», а не как отдельный большой CTA,
           спорящий с play-кнопкой. */
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

    <div class="pb-wrap" id="pbWrap">
        <div class="pb-inner">

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

    <!-- Вторая snap-точка: пустой маркер строго на границе с <main>, который index.php
         подключает сразу после этого файла (см. докблок вверху). Сам по себе невидим —
         IntersectionObserver в JS ниже следит именно за ним, а не считает пиксели скролла. -->
    <div class="pb-main-marker" id="pbMainTopMarker" aria-hidden="true"></div>

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

            var wrap = document.getElementById('pbWrap');
            var card = document.getElementById('pbCard');
            var marker = document.getElementById('pbMainTopMarker');
            var mini = document.getElementById('pbMini');
            var miniClose = document.getElementById('pbMiniClose');
            var html = document.documentElement;

            var dismissed = false;
            try {
                dismissed = sessionStorage.getItem(DISMISS_KEY) === '1';
            } catch (e) {}

            // ── Явный накопитель вместо нативного scroll-snap (см. докблок вверху файла —
            // там же объяснение, почему не сырой подсчёт wheel-событий, как в самой первой
            // версии). THRESHOLD подобран так, чтобы три обычных щелчка колеса мыши гарантированно
            // коммитили, а один случайный шевеление трекпада — нет. ──
            var THRESHOLD = 260;
            var DECAY_MS = 260;
            var CARRY_MS = 550; // держим в шаге с .pb-card.pb-flown transform .55s ниже в CSS
            var flown = false;
            var busy = false; // идёт carry-анимация — весь ввод глотаем, не трогаем accum
            var accum = 0;
            var decayTimer = null;

            function scrollRoot() {
                return document.scrollingElement || document.documentElement;
            }

            function markerDocTop() {
                return marker.getBoundingClientRect().top + scrollRoot().scrollTop;
            }

            function setNudge(px) {
                card.style.setProperty('--pbNudge', px.toFixed(1) + 'px');
            }

            function scheduleDecay() {
                clearTimeout(decayTimer);
                decayTimer = setTimeout(function() {
                    accum = 0;
                    setNudge(0);
                }, DECAY_MS);
            }

            function easeOutCubic(t) {
                return 1 - Math.pow(1 - t, 3);
            }

            // Скролл едет сам, рукой (rAF), синхронно с CSS-переходом карточки —
            // это и есть «нас проносит вниз/наверх», а не невидимый мгновенный прыжок.
            // Пока едет — html.pb-locked остаётся включён весь клип, так что инерция
            // трекпада/колеса от того же жеста не проскакивает мимо точки стыковки.
            function animateScrollTo(target, onDone) {
                var root = scrollRoot();
                var start = root.scrollTop;
                var dist = target - start;
                var t0 = null;
                function step(ts) {
                    if (t0 === null) t0 = ts;
                    var t = Math.min(1, (ts - t0) / CARRY_MS);
                    root.scrollTop = start + dist * easeOutCubic(t);
                    if (t < 1) {
                        requestAnimationFrame(step);
                    } else {
                        root.scrollTop = target;
                        onDone();
                    }
                }
                requestAnimationFrame(step);
            }

            function commitDown() {
                busy = true;
                accum = 0;
                setNudge(0);
                // Снимаем замок ДО анимации, не после: пока html.pb-locked висит
                // (overflow:hidden), у элемента физически нет скроллируемой области,
                // и scrollTop, который дальше двигает animateScrollTo, тупо клэмпится
                // к 0 — страница «едет» только в переменных JS, а не на экране.
                // Реальная защита от инерции жеста — не overflow:hidden, а сам busy
                // (см. onWheel/touchmove: пока busy — событие проглатывается целиком).
                html.classList.remove('pb-locked');
                card.classList.add('pb-flown'); // карточка улетает существующим CSS-переходом
                if (!dismissed) mini.classList.add('pb-visible');
                var target = markerDocTop();
                animateScrollTo(target, function() {
                    flown = true;
                    busy = false;
                });
            }

            function commitUp() {
                busy = true;
                accum = 0;
                card.classList.remove('pb-flown'); // карточка появляется тем же переходом, в обратную сторону
                mini.classList.remove('pb-visible');
                animateScrollTo(0, function() {
                    html.classList.add('pb-locked'); // запираем обратно только когда реально доехали до баннера
                    flown = false;
                    busy = false;
                });
            }

            function onWheel(e) {
                if (e.ctrlKey) return; // pinch-zoom/ctrl+колесо — не наше дело
                if (busy) {
                    e.preventDefault(); // carry уже едет — глотаем хвост того же жеста
                    return;
                }
                if (!flown) {
                    if (e.deltaY <= 0) return; // скролл вверх, когда и так на баннере — игнор
                    e.preventDefault();
                    accum += Math.abs(e.deltaY);
                    if (accum >= THRESHOLD) {
                        commitDown();
                    } else {
                        setNudge(-22 * (accum / THRESHOLD));
                        scheduleDecay();
                    }
                    return;
                }
                // На главной гейтим только у самой верхней границы и только скролл вверх —
                // это и есть «сопротивление»: чуть ниже по странице колесо работает как обычно.
                if (scrollRoot().scrollTop > 4 || e.deltaY >= 0) return;
                e.preventDefault();
                accum += Math.abs(e.deltaY);
                if (accum >= THRESHOLD) commitUp();
                else scheduleDecay();
            }
            window.addEventListener('wheel', onWheel, {
                passive: false
            });

            // ── Тач: тот же накопитель, по вертикальному свайпу ──
            var touchStartY = null;
            window.addEventListener('touchstart', function(e) {
                touchStartY = e.touches[0].clientY;
            }, {
                passive: true
            });
            window.addEventListener('touchmove', function(e) {
                if (touchStartY === null) return;
                if (busy) {
                    e.preventDefault();
                    return;
                }
                var dy = touchStartY - e.touches[0].clientY; // >0 — свайп вверх (скролл вниз)
                if (!flown) {
                    if (dy <= 0) return;
                    e.preventDefault();
                    accum = dy;
                    if (accum >= THRESHOLD) {
                        commitDown();
                        touchStartY = null;
                    } else {
                        setNudge(-22 * (accum / THRESHOLD));
                    }
                    return;
                }
                if (scrollRoot().scrollTop > 4 || dy >= 0) return;
                e.preventDefault();
                accum = -dy;
                if (accum >= THRESHOLD) {
                    commitUp();
                    touchStartY = null;
                }
            }, {
                passive: false
            });
            window.addEventListener('touchend', function() {
                touchStartY = null;
                accum = 0;
                scheduleDecay();
            }, {
                passive: true
            });

            // ── Клавиатура намеренно НЕ гейтим накопителем — Page Down/Space/стрелки
            // сразу пропускают вниз. Ловить три отдельных keydown ради красивого
            // потряхивания того не стоит: это чужой, некликовый способ навигации,
            // и держать его взаперти было бы просто вредно для доступности. ──
            window.addEventListener('keydown', function(e) {
                if (flown || busy) return;
                if (['PageDown', 'ArrowDown', 'End', ' '].indexOf(e.key) !== -1) {
                    commitDown();
                }
            });

            html.classList.add('pb-locked');

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
                wrap.addEventListener('mousemove', function(e) {
                    if (pbRaf) return;
                    pbRaf = requestAnimationFrame(function() {
                        pbRaf = null;
                        if (flown) return; // не мешаем параллаксом, когда карточка уже летит
                        var r = wrap.getBoundingClientRect();
                        var mx = (e.clientX - r.left) / r.width - 0.5;
                        var my = (e.clientY - r.top) / r.height - 0.5;
                        card.style.setProperty('--tiltX', (-my * 6).toFixed(2) + 'deg');
                        card.style.setProperty('--tiltY', (mx * 8).toFixed(2) + 'deg');
                    });
                });
                wrap.addEventListener('mouseleave', function() {
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