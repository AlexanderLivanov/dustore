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
 * ВАЖНО (из-за этого не работало натяжение):
 * header.php заканчивается тегами </body></html> — то есть после его инклюда
 * <body> уже закрыт. <main> из index.php оказывается вне <body> в глазах
 * HTML-парсера (потом его перекидывает, но document.querySelector('body > main')
 * в момент выполнения скрипта возвращает null). Поэтому:
 *   - main ищем через querySelector('main') БЕЗ обёртки по body;
 *   - в CSS используем `body.pb-flown main`, а не `body.pb-flown > main`;
 *   - все движения main/wrap идут inline-стилями из JS.
 *
<<<<<<< HEAD
 * ИСТОРИЯ АНИМАЦИИ (v6, текущая):
 * v1 — перехват wheel (preventDefault) и ручная стейт-машина: классический
 * scroll-jacking, ломался на трекпадах, на тач просто отключали.
 * v2 — «pin & progress»: sticky-подставка + прогресс 0..1 в --p, но давал
 * побочный текстовый блок (.pb-title) на месте улетевшей карточки.
 * v3/v4 — только баннер, явный накопитель СУММЫ |deltaY| до порога (не сырые
 * wheel-события — ломается на трекпаде), но коммит был мгновенным прыжком
 * scrollTop — на реальном железе не читался как эффект, инерция жеста
 * докручивала мимо точки стыковки.
 *
 * v5/v6 — Лео переписал механику коммита с нуля на «pull-to-refresh»,
 * без единого scrollTop-прыжка вообще. Идея: вниз с баннера карточка
 * улетает своим 3D-переходом (.pb-flying/.pb-flown), а сразу за этим wrap
 * «паркуется» (position:fixed, обрезан по высоте 220px, translateY(-100%) —
 * полностью за кадром сверху) — подмена происходит, когда карточка уже
 * невидима, так что её физически не видно. <main> в момент подмены УЖЕ
 * в кадре без единого пикселя скролла — сам факт «страницы» не сдвигается,
 * сдвигается только состояние DOM.
 *
 * Обратно вверх — тот же самый накопитель |deltaY|, но работает только пока
 * scrollTop ≈ 0 (UP_GATE), и вместо «отдельного эффекта» тянет ЖИВУЮ пару
 * переменных --pb-pull/--pb-tension (@property, интерполируемые): main
 * едет вниз на --pb-pull, запаркованный wrap выезжает из-под кромки на ту
 * же величину — визуально это одно резиновое движение на двух половинах.
 * Не дотянули — пружинный отскок (.pb-springing, cubic-bezier с
 * перехлёстом). Дотянули до THRESHOLD_UP — commitUp(): pull уже физически
 * доехал до высоты wrap (peekHeight(), см. ниже) точно в момент коммита,
 * то есть полоска УЖЕ раскрыта вплотную, без щели. Дальше — микро-кроссфейд
 * (~650ms: 170ms гасим, меняем DOM-состояние в темноте, 480ms проявляем
 * с лёгким pop через cubic-bezier с перехлёстом): так подмена «обрезанная
 * полоска 220px, fixed» → «полная сцена 100vh, в потоке» не требует
 * анимировать сам box-model (transition на height/position — дорого и
 * дёргано), а выглядит как единый вдох-выдох, а не хлопок.
 *
 * v6 добавил: (1) main теперь не просто едет вниз на pull — вместе с этим
 * чуть уменьшается и темнеет (--pb-tension driven scale+filter), иначе на
 * фоне статичной страницы 180px сдвига читались слабо; (2) датчик натяжения
 * (#pbTension) — кольцевой индикатор с стрелкой, показывает прогресс к
 * порогу и направление в обе стороны (для «вниз» — с учётом того, что
 * коммит не с первой попытки, а с третьей: прогресс = (attemptsDown +
 * accum/THRESHOLD_DOWN) / ATTEMPTS_NEEDED, честно показывает «ещё 2
 * попытки»); (3) .pb-wrap.pb-parked .pb-inner получил явную высоту вместо
 * height:auto — с единственным абсолютно спозиционированным ребёнком внутри
 * (.pb-card{position:absolute;inset:0}) auto-высота считается пустой (абсолютные
 * дети не участвуют в auto-высоте родителя), так что раньше пик мог
 * схлопываться в 0 — теперь высота явно равна высоте самого wrap.
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
=======
 * Прокрутка: у нас фактически скроллится document.scrollingElement (обычно
 * body), а не window/html. window.scrollTo()/scrollY — тихий no-op, поэтому
 * весь скролл идёт через scrollRoot().
 *
 * ФЛАГ «БАННЕР УЖЕ ПОКАЗЫВАЛСЯ» (sessionStorage):
 * Ключ pbBannerShown:<PROMO_ID> ставится при первом commitDown — то есть
 * когда пользователь первый раз прорвался с баннера вниз на главную. При
 * следующих заходах на главную в этой же сессии баннер не показывается
 * с нуля: wrap сразу паркуется за верхнюю кромку, pb-locked не ставится,
 * main рендерится сразу. Баннер остаётся доступен через натяжение вверх
 * у верхней границы main — commitUp возвращает его как обычно.
 * sessionStorage (не localStorage) — потому что сценарий «первый раз»,
 * а не «навсегда»: новая вкладка = снова первый раз.
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../controllers/analytics.php');

$__pb_conn = (new Database())->connect();

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
    $__pb_analytics = new Analytics($__pb_conn);
    $__pb_analytics->trackOncePerSession('promotion', (int)$promo['promo_id'], 'impression');

    $__pb_launches = $__pb_analytics->summarize('game', (int)$promo['game_id'])['launch']['count'] ?? 0;

    $pbImage = $promo['banner_url'] ?: $promo['path_to_cover'] ?: '';
    $pbDesc  = trim((string)($promo['short_description'] ?? ''));
    if ($pbDesc === '') $pbDesc = trim(strip_tags((string)($promo['description'] ?? '')));
    if (mb_strlen($pbDesc) > 140) $pbDesc = mb_substr($pbDesc, 0, 140) . '…';

    $pbRating = $promo['avg_rating'] !== null ? (float)$promo['avg_rating'] : null;
    $pbStars  = $pbRating !== null ? max(0, min(5, (int)round($pbRating / 2))) : 0;

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
        html.pb-locked,
        html.pb-locked body {
            overflow: hidden;
            height: 100%;
        }

        .pb-wrap {
            position: relative;
            background: #0f0a20;
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

        body.moonlight-theme .pb-wrap {
            background: transparent;
        }

        @media (max-width:640px) {
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

        .pb-main-marker {
            display: block;
        }

        @keyframes pbIntro {
            from { opacity: 0; filter: blur(20px); }
            to   { opacity: 1; filter: blur(0); }
        }

        .pb-inner {
            position: relative;
            width: min(1100px, 100%);
            height: min(72vh, 620px);
            min-height: 360px;
            transform-style: preserve-3d;
            transform: translateY(var(--pbNudge, 0px));
            transition: transform .001s;
            will-change: transform;
<<<<<<< HEAD
        }

        .pb-inner.pb-decaying {
            transition: transform .5s cubic-bezier(.34, 1.56, .64, 1);
        }


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
    transition: transform .001s, opacity .3s ease, filter .3s ease;
    will-change: transform, opacity, filter;
}

.pb-card.pb-flying {
    transition:
        transform .55s cubic-bezier(.22, .61, .36, 1),
        opacity .45s ease,
        filter .45s ease;
}

.pb-card.pb-flown {
    transform:
        translate3d(var(--pb-flyX), var(--pb-flyY), var(--pb-flyZ))
        rotateY(var(--pb-rotY)) rotateX(var(--pb-rotX)) rotateZ(var(--pb-rotZ))
        scale(var(--pb-scaleEnd));
    opacity: 0;
    filter: blur(6px);
    pointer-events: none;
}

/* Тряска — резкая, 380мс, с первым тяжёлым ударом (12px за 50мс). */
@keyframes pbShake {
    0%   { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)); }
    14%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d(-12px, 4px, 0) rotateZ(-2.2deg) scale(.972); }
    32%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d( 12px, 4px, 0) rotateZ( 2.2deg) scale(.972); }
    52%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d(-7px,  0,   0) rotateZ(-1.3deg) scale(1.010); }
    70%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d( 7px,  0,   0) rotateZ( 1.3deg) scale(1.010); }
    86%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d(-2px,  0,   0) rotateZ(-0.4deg) scale(1); }
    100% { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)); }
}
.pb-card.pb-shake {
    animation: pbShake .38s cubic-bezier(.36, .07, .19, .97) both;
}

/* Появление главной снизу — с лёгким расфокусом, чтобы шва вообще не читалось. */
@keyframes pbMainEnter {
    from { transform: translateY(80px); opacity: 0; filter: blur(8px); }
    to   { transform: translateY(0);    opacity: 1; filter: blur(0);   }
}

main.pb-main-enter {
    animation: pbMainEnter .62s cubic-bezier(.22, .61, .36, 1);
}

/* ── Анимируемые переменные натяжения ──────────────────────────────────
   @property делает их интерполируемыми. --pb-pull — основная (сколько main
   съехал вниз / сколько wrap выехало сверху), --pb-tension — 0..1 для
   градиента и кромки (обновляется пропорционально pull). */
@property --pb-tension {
    syntax: '<number>';
    inherits: true;
    initial-value: 0;
}
@property --pb-pull {
    syntax: '<length>';
    inherits: true;
    initial-value: 0px;
}

main {
    /* ВАЖНО: это НЕ body > main. header.php оборачивает и хедер, и
       промо-баннер, и main, и футер в один сквозной .header-wrapper —
       так что <main> на самом деле body > .header-wrapper > main, а не
       прямой ребёнок body. Строгий дочерний комбинатор (>) здесь молча
       НЕ СРАБАТЫВАЛ вообще никогда: main никогда не двигался, не тускнел,
       кромка-свечение никогда не загоралась — это и была настоящая причина
       «нет ощущения натяжения» (main физически не мог отреагировать, а не
       просто слабо реагировал). Везде ниже — просто `main` / `body.pb-flown
       main` (потомок, без >), без привязки к глубине вложенности. */
    position: relative;
    will-change: transform, filter;
    transform-origin: 50% 0;
}

/* ── Pull-to-refresh: main сдвигается вниз ────────────────────────────
   Пока --pb-pull = 0, ничего не меняется. Когда юзер тянет вверх,
   --pb-pull растёт → main уезжает вниз, все его элементы едут вместе
   с ним (это одна box-модель, не десять отдельных элементов). */
body.pb-flown main {
    z-index: 2;
    /* Раньше был только translateY(pull) — на статичной странице 180px сдвига
       читались слабо, «как будто ничего не тянется». Добавили лёгкое сжатие
       и затемнение, оба от --pb-tension (0..1, растёт нелинейно тем же
       Math.pow(p,.75), что и сам pull) — вместе с кромкой-свечением
       (main::after ниже, тоже на --pb-tension) это уже читается однозначно
       как «страницу тянут», а не «страница чуть дрогнула». */
    transform: translateY(var(--pb-pull, 0px)) scale(calc(1 - var(--pb-tension, 0) * 0.035));
    filter: brightness(calc(1 - var(--pb-tension, 0) * 0.22)) saturate(calc(1 - var(--pb-tension, 0) * 0.3));
    transition: transform .08s linear, filter .08s linear;
}
body.pb-flown main.pb-springing,
html.pb-springing body.pb-flown main {
    transition: transform .55s cubic-bezier(.34, 1.56, .64, 1), filter .55s ease;
}

/* ── Припаркованный баннер ────────────────────────────────────────────
   Пока мы «на главной», wrap сидит fixed в потоке z-index 1 (под main,
   у main z-index 2), вырезан по высоте, и весь сдвинут за верхнюю кромку
   через translateY(-100%). При --pb-pull > 0 он выезжает вниз — и в
   открывшуюся сверху щель видно ВЕРХНЮЮ часть карточки. Это симметрично
   тому, как main уезжает вниз: две половины одного движения.

   Высота 220px = ровно тот же predел, что peekHeight() считает в JS —
   при полном натяжении (--pb-pull == высота wrap) полоска раскрывается
   ВПЛОТНУЮ, без остаточной щели: момент коммита застаёт её уже раскрытой. */
.pb-wrap.pb-parked {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: min(220px, 40vh);
    z-index: 1;
    padding: 0;
    align-items: flex-start;
    overflow: hidden;
    pointer-events: none;
    transform: translateY(calc(-100% + var(--pb-pull, 0px)));
    transition: transform .08s linear;
    will-change: transform;
    perspective: none;
}
html.pb-springing .pb-wrap.pb-parked {
    transition: transform .55s cubic-bezier(.34, 1.56, .64, 1);
}
/* Внутри припаркованного wrap карточка больше не центрируется и не
   растягивается на 100vh — она просто лежит сверху своей обычной высоты,
   а обрезка идёт по wrap-у. height:auto тут был бы багом: единственный
   ребёнок (.pb-card) position:absolute, а абсолютные дети не участвуют
   в подсчёте auto-высоты родителя — родитель посчитал бы себя пустым, и
   пик мог схлопнуться в 0. Поэтому высота явно = высоте самого wrap. */
.pb-wrap.pb-parked .pb-inner {
    height: min(220px, 40vh);
    min-height: 0;
    flex-shrink: 0;
}

/* ── Кроссфейд-подмена в commitUp() ──────────────────────────────────
   Вместо анимации height/position (дорого, дёргано при смене box-модели)
   wrap на мгновение гасится, DOM-состояние меняется «в темноте», и тут же
   проявляется обратно с лёгким pop — единый вдох-выдох вместо хлопка. */
.pb-wrap.pb-swap-out {
    transition: opacity .16s ease, filter .16s ease;
    opacity: 0;
    filter: blur(10px);
}
.pb-wrap.pb-swap-in {
    animation: pbWrapSwapIn .48s cubic-bezier(.22, .9, .32, 1.24) both;
}
@keyframes pbWrapSwapIn {
    from { opacity: 0; filter: blur(10px); transform: scale(.96); }
    to   { opacity: 1; filter: blur(0);     transform: scale(1); }
}

/* ── Градиент в щели ─────────────────────────────────────────────────
   Косметика поверх открывшейся полосы. Не обязателен — но пусть будет,
   он делает шов мягче. */
body.pb-flown::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 100vh;
    pointer-events: none;
    z-index: 1;
    background: linear-gradient(180deg,
        rgba(0, 0, 0, 1) 0%,
        rgba(20, 4, 29, 0.9) 5%,
        rgba(60, 10, 80, 0.55) 15%,
        rgba(120, 20, 130, 0.28) 28%,
        transparent 50%);
    opacity: calc(var(--pb-tension, 0) * 1.4);
    transition: opacity .08s linear;
}

/* Светящаяся кромка по верху main: горит, когда main отъехал. */
body.pb-flown main::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    pointer-events: none;
    z-index: 100;
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(255, 90, 170, calc(var(--pb-tension, 0) * 1)) 50%,
        transparent 100%);
    box-shadow:
        0 0 calc(var(--pb-tension, 0) * 50px) rgba(230, 46, 138, calc(var(--pb-tension, 0) * 1)),
        0 0 calc(var(--pb-tension, 0) * 120px) rgba(195, 33, 120, calc(var(--pb-tension, 0) * 0.5));
}

body.pb-flown main {
    box-shadow: 0 calc(var(--pb-tension, 0) * -36px) calc(var(--pb-tension, 0) * 70px) rgba(0, 0, 0, calc(var(--pb-tension, 0) * 0.85));
}

/* Луна — холодный акцент. */
body.moonlight-theme.pb-flown::before {
    background: linear-gradient(180deg,
        rgba(0, 0, 0, 1) 0%,
        rgba(4, 8, 20, 0.9) 5%,
        rgba(12, 30, 60, 0.55) 15%,
        rgba(30, 80, 150, 0.28) 28%,
        transparent 50%);
}
body.moonlight-theme.pb-flown main::after {
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(140, 190, 255, calc(var(--pb-tension, 0) * 1)) 50%,
        transparent 100%);
    box-shadow:
        0 0 calc(var(--pb-tension, 0) * 50px) rgba(80, 130, 220, calc(var(--pb-tension, 0) * 1)),
        0 0 calc(var(--pb-tension, 0) * 120px) rgba(80, 130, 220, calc(var(--pb-tension, 0) * 0.5));
}
=======
            animation: pbIntro .7s ease-out both;
        }

        .pb-inner.pb-decaying {
            transition: transform .5s cubic-bezier(.34, 1.56, .64, 1);
        }

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
            transition: transform .001s, opacity .3s ease, filter .3s ease;
            will-change: transform, opacity, filter;
        }

        .pb-card.pb-flying {
            transition:
                transform .55s cubic-bezier(.22, .61, .36, 1),
                opacity .45s ease,
                filter .45s ease;
        }

        .pb-card.pb-flown {
            transform:
                translate3d(var(--pb-flyX), var(--pb-flyY), var(--pb-flyZ))
                rotateY(var(--pb-rotY)) rotateX(var(--pb-rotX)) rotateZ(var(--pb-rotZ))
                scale(var(--pb-scaleEnd));
            opacity: 0;
            filter: blur(6px);
            pointer-events: none;
        }

        @keyframes pbCardEnter {
            from { transform: translateY(-20vh); opacity: 0; filter: blur(8px); }
            to   { transform: translateY(0);     opacity: 1; filter: blur(0);   }
        }

        .pb-card.pb-entering {
            animation: pbCardEnter .62s cubic-bezier(.22, .61, .36, 1) both;
        }

        @keyframes pbShake {
            0%   { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)); }
            14%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d(-12px, 4px, 0) rotateZ(-2.2deg) scale(.972); }
            32%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d( 12px, 4px, 0) rotateZ( 2.2deg) scale(.972); }
            52%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d(-7px,  0,   0) rotateZ(-1.3deg) scale(1.010); }
            70%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d( 7px,  0,   0) rotateZ( 1.3deg) scale(1.010); }
            86%  { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)) translate3d(-2px,  0,   0) rotateZ(-0.4deg) scale(1); }
            100% { transform: rotateY(var(--tiltY,0deg)) rotateX(var(--tiltX,0deg)); }
        }
        .pb-card.pb-shake {
            animation: pbShake .38s cubic-bezier(.36, .07, .19, .97) both;
        }

        @keyframes pbMainEnter {
            from { transform: translateY(80px); opacity: 0; filter: blur(8px); }
            to   { transform: translateY(0);    opacity: 1; filter: blur(0);   }
        }
        main.pb-main-enter {
            animation: pbMainEnter .62s cubic-bezier(.22, .61, .36, 1);
        }

        .pb-wrap.pb-parked {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 100vh;
            z-index: 1;
            padding: 24px;
            align-items: flex-start;
            overflow: hidden;
            pointer-events: none;
            transform: translateY(-100%);
            will-change: transform;
            perspective: none;
        }

        .pb-wrap.pb-parked .pb-inner {
            height: auto;
            min-height: 0;
            flex-shrink: 0;
        }

        main {
            position: relative;
            z-index: 2;
            transform-origin: 50% 0;
        }

        @property --pb-tension {
            syntax: '<number>';
            inherits: true;
            initial-value: 0;
        }
        @property --pb-tension-down {
            syntax: '<number>';
            inherits: true;
            initial-value: 0;
        }

        body.pb-flown::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 100vh;
            pointer-events: none;
            z-index: 1;
            background: #0f0a20;
            opacity: calc(var(--pb-tension, 0) * 0.95);
            transition: opacity .1s linear;
        }

        body.moonlight-theme.pb-flown::before {
            background: #050a14;
        }

        body.pb-flown main::after {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            height: 2px;
            width: calc(var(--pb-tension, 0) * 100%);
            max-width: 100%;
            transform: translateX(-50%);
            pointer-events: none;
            z-index: 100;
            background: linear-gradient(90deg,
                rgba(230, 46, 138, 0) 0%,
                rgba(230, 46, 138, 0.95) 50%,
                rgba(230, 46, 138, 0) 100%);
            box-shadow: 0 0 12px rgba(230, 46, 138, calc(var(--pb-tension, 0) * 0.7));
        }

        body.moonlight-theme.pb-flown main::after {
            background: linear-gradient(90deg,
                rgba(86, 144, 240, 0) 0%,
                rgba(86, 144, 240, 0.95) 50%,
                rgba(86, 144, 240, 0) 100%);
            box-shadow: 0 0 12px rgba(86, 144, 240, calc(var(--pb-tension, 0) * 0.7));
        }

        body:not(.pb-flown) .pb-card::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            height: 2px;
            width: calc(var(--pb-tension-down, 0) * 100%);
            max-width: 100%;
            transform: translateX(-50%);
            pointer-events: none;
            z-index: 10;
            background: linear-gradient(90deg,
                rgba(230, 46, 138, 0) 0%,
                rgba(230, 46, 138, 0.95) 50%,
                rgba(230, 46, 138, 0) 100%);
            box-shadow: 0 0 12px rgba(230, 46, 138, calc(var(--pb-tension-down, 0) * 0.7));
            transition: width .05s linear;
        }

        body.moonlight-theme:not(.pb-flown) .pb-card::before {
            background: linear-gradient(90deg,
                rgba(86, 144, 240, 0) 0%,
                rgba(86, 144, 240, 0.95) 50%,
                rgba(86, 144, 240, 0) 100%);
            box-shadow: 0 0 12px rgba(86, 144, 240, calc(var(--pb-tension-down, 0) * 0.7));
        }
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90

        .pb-bg {
            position: absolute;
            inset: 0;
            background:
                <?php if ($pbImage): ?> linear-gradient(to top, rgba(20, 4, 29, .94) 0%, rgba(20, 4, 29, .55) 40%, rgba(20, 4, 29, .15) 68%, transparent 82%),
                url('<?= htmlspecialchars($pbImage) ?>') center/cover no-repeat;
            <?php else: ?>
            linear-gradient(160deg, #14041d 0%, #400c4a 45%, #74155d 78%, #c32178 100%);
            <?php endif; ?>
        }

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

        /* ── Датчик натяжения ─────────────────────────────────────────────
           Минималистичный: кольцо-прогресс + стрелка направления, всегда
           внизу по центру (независимо от того, тянем к главной или обратно
           к баннеру — так он не спорит за место с .pb-mini у хедера). Для
           «вниз» прогресс честно учитывает ATTEMPTS_NEEDED — не «набрали
           240px», а «это третья попытка», ровно как просил Лео. */
        .pb-tension {
            position: fixed;
            left: 50%;
            bottom: 30px;
            z-index: 6;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f8f9fa;
            opacity: 0;
            pointer-events: none;
            transform: translate(-50%, 6px) scale(.85);
            transition: opacity .18s ease, transform .18s ease;
        }

        .pb-tension.pb-tension-visible {
            opacity: 1;
            transform: translate(-50%, 0) scale(1);
        }

        .pb-tension-ring {
            position: absolute;
            inset: 0;
            transform: rotate(-90deg);
        }

        .pb-tension-ring-bg {
            fill: rgba(20, 4, 29, .6);
            stroke: rgba(255, 255, 255, .18);
            stroke-width: 2.5;
        }

        .pb-tension-ring-fg {
            fill: none;
            stroke: #e62e8a;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-dasharray: 113.1;
            stroke-dashoffset: 113.1;
            transition: stroke-dashoffset .05s linear, stroke .2s ease;
        }

        .pb-tension.pb-tension-ready .pb-tension-ring-fg {
            stroke: #35d07f;
        }

        .pb-tension-arrow {
            position: relative;
            z-index: 1;
            transition: transform .18s ease;
        }

        .pb-tension.pb-tension-up .pb-tension-arrow {
            transform: rotate(180deg);
        }

        @media (prefers-reduced-motion: reduce) {
            .pb-tension.pb-tension-ready .pb-tension-arrow {
                animation: none;
            }
        }

        .pb-tension.pb-tension-ready .pb-tension-arrow {
            animation: pbTensionPulse .5s ease-in-out infinite;
        }

        @keyframes pbTensionPulse {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }

        .pb-tension.pb-tension-up.pb-tension-ready .pb-tension-arrow {
            animation: pbTensionPulseUp .5s ease-in-out infinite;
        }

        @keyframes pbTensionPulseUp {
            0%, 100% { transform: rotate(180deg) translateY(0); }
            50%      { transform: rotate(180deg) translateY(-2px); }
        }
    </style>

    <div class="pb-wrap" id="pbWrap">
        <div class="pb-inner" id="pbInner">

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

    <div class="pb-tension" id="pbTension" aria-hidden="true">
        <svg class="pb-tension-ring" width="44" height="44" viewBox="0 0 44 44">
            <circle class="pb-tension-ring-bg" cx="22" cy="22" r="18" />
            <circle class="pb-tension-ring-fg" id="pbTensionRing" cx="22" cy="22" r="18" />
        </svg>
        <svg class="pb-tension-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5v14M6 13l6 6 6-6" />
        </svg>
    </div>

    <script src="/swad/js/analytics.js"></script>
<script>
(function () {
    var PROMO_ID = <?= (int)$promo['promo_id'] ?>;
    var GAME_ID = <?= (int)$promo['game_id'] ?>;
    var DISMISS_KEY = 'pbMiniDismissed:' + PROMO_ID;

<<<<<<< HEAD
    var wrap = document.getElementById('pbWrap');
    var inner = document.getElementById('pbInner');
    var card = document.getElementById('pbCard');
    var mini = document.getElementById('pbMini');
    var miniClose = document.getElementById('pbMiniClose');
    var tensionEl = document.getElementById('pbTension');
    var tensionRing = document.getElementById('pbTensionRing');
    var html = document.documentElement;

    /* mainEl НЕ кэшируем на старте: promo_banner.php инклюдится ВЫШЕ <main>,
       и в момент выполнения скрипта элемента в DOM ещё нет. Тянем лениво.
       Селектор — просто 'main', НЕ 'body > main': header.php оборачивает
       весь хедер+баннер+main+футер в сквозной .header-wrapper, так что
       <main> на деле не прямой ребёнок body. С 'body > main' этот querySelector
       молча возвращал null всегда — вся реакция main на натяжение (translate/
       scale/filter/glow) была мертва с самого начала, это и была настоящая
       причина «страница не реагирует». На странице ровно один <main>, так
       что голый элементный селектор безопасен. */
    var mainEl = null;
    function getMainEl() {
        if (!mainEl) mainEl = document.querySelector('main');
        return mainEl;
    }
    if (document.readyState !== 'loading') getMainEl();
    else document.addEventListener('DOMContentLoaded', getMainEl, { once: true });

    var dismissed = false;
    try { dismissed = sessionStorage.getItem(DISMISS_KEY) === '1'; } catch (e) {}

    /* ── Параметры ──────────────────────────────────────────────────────
       THRESHOLD_UP = 420: чтобы на трекпаде один tick (~300) не улетал
       сразу в коммит — нужно минимум 3-4 события. MAX_DELTA_PER_TICK
       ограничивает вклад одного события, чтобы «жирный» tick не проглотил
       весь порог одним махом. Предел самого натяжения — не константа
       здесь, а peekHeight() (см. ниже): высота запаркованного wrap. */
    var THRESHOLD_DOWN = 240;
    var THRESHOLD_UP   = 420;
    var MAX_DELTA_PER_TICK = 140;
    var ATTEMPTS_NEEDED = 3;
    var DECAY_MS  = 220;
    var MIN_ATTEMPT = 40;
    var UP_GATE   = 40;
    var RING_CIRC = 113.1; // 2*PI*18, радиус кольца датчика

    var flown = false;
    var attemptsDown = 0;
=======
    /* Флаг «баннер уже был показан в этой сессии». Ставится при первом
       commitDown — когда пользователь впервые прорвался с баннера на
       главную. При последующих заходах на главную баннер сразу уходит
       за верхнюю кромку, main рендерится без блокировки, pb-locked не
       ставится. Баннер по-прежнему доступен через натяжение вверх. */
    var BANNER_SHOWN_KEY = 'pbBannerShown:' + PROMO_ID;
    var bannerAlreadyShown = false;
    try { bannerAlreadyShown = sessionStorage.getItem(BANNER_SHOWN_KEY) === '1'; } catch (e) {}

    var wrap = document.getElementById('pbWrap');
    var inner = document.getElementById('pbInner');
    var card = document.getElementById('pbCard');
    var mini = document.getElementById('pbMini');
    var miniClose = document.getElementById('pbMiniClose');
    var html = document.documentElement;

    var mainEl = null;
    function getMainEl() {
        if (!mainEl) mainEl = document.querySelector('main');
        return mainEl;
    }
    if (document.readyState !== 'loading') getMainEl();
    else document.addEventListener('DOMContentLoaded', getMainEl, { once: true });

    var dismissed = false;
    try { dismissed = sessionStorage.getItem(DISMISS_KEY) === '1'; } catch (e) {}

    var THRESHOLD_DOWN = 580;
    var THRESHOLD_UP   = 580;
    var MAX_DELTA_PER_TICK = 100;
    var DECAY_MS  = 250;
    var MIN_ATTEMPT = 40;
    var UP_GATE   = 60;
    var MAIN_PULL = 180;
    var SPRING_MS = 550;

    var flown = false;
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
    var accum = 0;
    var decayTimer = null;
    var animating = false;
    var shookThisGesture = false;

    function scrollRoot() {
        return document.scrollingElement || document.documentElement;
    }

<<<<<<< HEAD
    /* Предел --pb-pull = реальная высота запаркованного wrap (та же формула,
       что в CSS: min(220px, 40vh)). Раньше это была отдельная константа
       MAIN_PULL=180, из-за чего натяжение упиралось в потолок РАНЬШЕ, чем
       полоска раскрывалась вплотную — оставался «недотянутый» зазор 40px
       даже на максимуме. Теперь предел один и тот же для CSS и JS: полное
       натяжение = полоска раскрыта вплотную, без остатка. */
    function peekHeight() {
        return Math.min(220, window.innerHeight * 0.4);
    }

    /* ── Датчик натяжения ─────────────────────────────────────────────── */
    function showTension(direction, progress) {
        if (!tensionEl) return;
        var p = Math.max(0, Math.min(progress, 1));
        tensionEl.classList.add('pb-tension-visible');
        tensionEl.classList.toggle('pb-tension-up', direction === 'up');
        tensionEl.classList.toggle('pb-tension-ready', p >= 0.92);
        if (tensionRing) tensionRing.style.strokeDashoffset = (RING_CIRC * (1 - p)).toFixed(1);
    }
    function hideTension() {
        if (!tensionEl) return;
        tensionEl.classList.remove('pb-tension-visible', 'pb-tension-ready', 'pb-tension-up');
    }

    /* ── Пружина баннера (вниз) ──────────────────────────────────────── */
=======
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
    function setNudge(px) {
        inner.style.setProperty('--pbNudge', px.toFixed(1) + 'px');
    }

<<<<<<< HEAD
    /* ── Пружина main (вверх) ──────────────────────────────────────────
       Пишем --pb-pull на :root. По ней одновременно:
       - main едет вниз (CSS body.pb-flown main),
       - припаркованный wrap выезжает сверху (CSS .pb-wrap.pb-parked).
       Никаких inline transform — только одна переменная, которую читают
       оба элемента. Так они физически не могут разъехаться. */
    function setMainNudge(px, live) {
        var m = getMainEl();
        if (!m) return;
        var p = Math.min(Math.abs(px) / peekHeight(), 1);

        html.style.setProperty('--pb-tension', p.toFixed(3));
        html.style.setProperty('--pb-pull', px.toFixed(1) + 'px');

        if (!live) {
            /* Отбой. Медленный переход вешаем классом pb-springing — и на
               main, и на wrap.pb-parked. По нему CSS даёт bounce. */
            html.classList.add('pb-springing');
            html.style.setProperty('--pb-pull', '0px');
            html.style.setProperty('--pb-tension', '0');
            setTimeout(function () { html.classList.remove('pb-springing'); }, 620);
=======
    function updateTensionDown() {
        var p = Math.min(accum / THRESHOLD_DOWN, 1);
        html.style.setProperty('--pb-tension-down', p.toFixed(3));
    }
    function clearTensionDown() {
        html.style.setProperty('--pb-tension-down', '0');
    }

    function setMainNudge(px, live) {
        var m = getMainEl();
        if (!m) return;
        var p = Math.min(Math.abs(px) / MAIN_PULL, 1);

        html.style.setProperty('--pb-tension', p.toFixed(3));

        if (live) {
            m.style.transition = 'transform .07s linear, filter .07s linear';
            m.style.transform = 'translateY(' + px.toFixed(1) + 'px)';
            m.style.filter = px > 1 ? 'blur(' + (p * 1.6).toFixed(2) + 'px)' : '';

            wrap.style.transition = 'transform .07s linear';
            wrap.style.transform = 'translateY(calc(-100% + ' + px.toFixed(1) + 'px))';
        } else {
            m.style.transition = 'transform ' + SPRING_MS + 'ms cubic-bezier(.34, 1.56, .64, 1), filter ' + SPRING_MS + 'ms ease';
            m.style.transform = '';
            m.style.filter = '';

            wrap.style.transition = 'transform ' + SPRING_MS + 'ms cubic-bezier(.34, 1.56, .64, 1)';
            wrap.style.transform = 'translateY(-100%)';

            html.style.setProperty('--pb-tension', '0');

            setTimeout(function () {
                m.style.transition = '';
                m.style.filter = '';
                wrap.style.transition = '';
            }, SPRING_MS + 80);
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
        }
    }

    function springBackBanner() {
        inner.classList.add('pb-decaying');
        setNudge(0);
<<<<<<< HEAD
=======
        clearTensionDown();
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
        setTimeout(function () { inner.classList.remove('pb-decaying'); }, 560);
    }

    function triggerShake() {
        card.classList.remove('pb-shake');
        void card.offsetWidth;
        card.classList.add('pb-shake');
        setTimeout(function () { card.classList.remove('pb-shake'); }, 400);
    }

    function scheduleDecay() {
        clearTimeout(decayTimer);
        decayTimer = setTimeout(function () {
            if (!flown) {
<<<<<<< HEAD
                if (accum > MIN_ATTEMPT) attemptsDown++;
=======
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
                springBackBanner();
            } else {
                setMainNudge(0, false);
            }
            accum = 0;
            shookThisGesture = false;
<<<<<<< HEAD
            hideTension();
        }, DECAY_MS);
    }

    /* ── Полёт карточки ──────────────────────────────────────────────── */
=======
        }, DECAY_MS);
    }

>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
    function flyCardOut() {
        card.classList.add('pb-flying');
        void card.offsetWidth;
        card.classList.add('pb-flown');
        setTimeout(function () { card.classList.remove('pb-flying'); }, 620);
    }
<<<<<<< HEAD
    function flyCardIn() {
        card.classList.add('pb-flying');
        void card.offsetWidth;
        card.classList.remove('pb-flown');
        setTimeout(function () { card.classList.remove('pb-flying'); }, 620);
    }

    /* ── Коммит ВНИЗ ─────────────────────────────────────────────────── */
    function commitDown() {
        if (animating) return;
        animating = true;
        clearTimeout(decayTimer);
        hideTension();

        inner.classList.remove('pb-decaying');
        setNudge(0);
        attemptsDown = 0;
        accum = 0;
        shookThisGesture = false;

        flyCardOut();
        if (!dismissed) setTimeout(function () { mini.classList.add('pb-visible'); }, 380);

        setTimeout(function () {
            /* Натяг обнуляем до подмены. */
            html.style.setProperty('--pb-pull', '0px');
            html.style.setProperty('--pb-tension', '0');

            /* Паркуем wrap: уходит из потока, садится fixed сверху за кадр.
               main сам собой оказывается в кадре — не нужно скроллить. */
            wrap.classList.add('pb-parked');

            /* Карточка уже улетела, но при натяжении вверх нам нужна
               нормальная — сбрасываем «улёт» за кадром. Пользователь не
               увидит, потому что wrap сейчас полностью за верхней кромкой. */
            card.classList.remove('pb-flown', 'pb-flying', 'pb-shake');

            requestAnimationFrame(function () { scrollRoot().scrollTop = 0; });
            html.classList.remove('pb-locked');
            document.body.classList.add('pb-flown');
            flown = true;

            var m = getMainEl();
            if (m) m.classList.add('pb-main-enter');
        }, 420);

        setTimeout(function () {
            var m = getMainEl();
            if (m) {
                m.classList.remove('pb-main-enter');
                /* Защита: если @keyframes-анимация pb-main-enter по какой-то
                   причине не «отпустила» transform у main сама (висящий Animation
                   в getAnimations() — по-хорошему после снятия класса быть не
                   должно, но конкретно на этом стенде transform main иногда
                   оставался «заморожен» на identity даже под inline !important,
                   пока анимация formально не была явно cancel()-нута), сносим
                   ЛЮБЫЕ анимации на main руками, чтобы --pb-pull/--pb-tension
                   transform ниже точно ничем не перебивался. */
                if (m.getAnimations) {
                    m.getAnimations().forEach(function (a) { a.cancel(); });
                }
            }
            animating = false;
        }, 420 + 640);
    }

    /* ── Коммит ВВЕРХ ────────────────────────────────────────────────── */
    function commitUp() {
        if (animating) return;
        animating = true;
        clearTimeout(decayTimer);
        hideTension();

        /* В момент коммита --pb-pull уже физически доехал до peekHeight()
           (полоска раскрыта вплотную, без щели — см. докблок вверху), так
           что резкого рывка тут нет. Резкий был ДАЛЬШЕ: сама подмена
           «обрезанная полоска 220px, fixed» → «полная сцена 100vh, в
           потоке» раньше происходила мгновенно, одним кадром — это и
           читалось как «баннер появляется резко». Теперь — микро-кроссфейд:
           гасим (170ms), меняем DOM-состояние пока wrap невидим, проявляем
           обратно с лёгким pop (480ms, pb-swap-in). */
        html.classList.remove('pb-springing');
        wrap.classList.add('pb-swap-out');

        attemptsDown = 0;
        accum = 0;
        shookThisGesture = false;

        setTimeout(function () {
            html.style.setProperty('--pb-pull', '0px');
            html.style.setProperty('--pb-tension', '0');

            /* Снять парковку: wrap снова в потоке, main уходит под него. */
            wrap.classList.remove('pb-parked', 'pb-swap-out');
=======

    function commitDown() {
        if (animating) return;
        animating = true;
        clearTimeout(decayTimer);

        /* Помечаем баннер как «уже показанный» — при следующих заходах
           на главную в этой сессии он не будет показываться с нуля. */
        try { sessionStorage.setItem(BANNER_SHOWN_KEY, '1'); } catch (e) {}

        inner.classList.remove('pb-decaying');
        setNudge(0);
        clearTensionDown();
        accum = 0;
        shookThisGesture = false;

        flyCardOut();
        if (!dismissed) setTimeout(function () { mini.classList.add('pb-visible'); }, 380);

        setTimeout(function () {
            html.style.setProperty('--pb-tension', '0');
            clearTensionDown();

            wrap.classList.add('pb-parked');
            wrap.style.transition = 'none';
            wrap.style.transform = 'translateY(-100%)';
            void wrap.offsetWidth;
            wrap.style.transition = '';

            card.classList.remove('pb-flown', 'pb-flying', 'pb-shake', 'pb-entering');

            requestAnimationFrame(function () { scrollRoot().scrollTop = 0; });
            html.classList.remove('pb-locked');
            document.body.classList.add('pb-flown');
            flown = true;

            var m = getMainEl();
            if (m) m.classList.add('pb-main-enter');
        }, 420);

        setTimeout(function () {
            var m = getMainEl();
            if (m) m.classList.remove('pb-main-enter');
            animating = false;
        }, 420 + 640);
    }

    function commitUp() {
        if (animating) return;
        animating = true;
        clearTimeout(decayTimer);

        var m = getMainEl();

        wrap.style.transition = 'transform ' + SPRING_MS + 'ms cubic-bezier(.34, 1.56, .64, 1)';
        if (m) {
            m.style.transition = 'transform ' + SPRING_MS + 'ms cubic-bezier(.34, 1.56, .64, 1), filter ' + SPRING_MS + 'ms ease';
        }

        void wrap.offsetWidth;

        wrap.style.transform = 'translateY(0)';
        if (m) {
            m.style.transform = 'translateY(100vh)';
            m.style.filter = '';
        }

        card.classList.remove('pb-entering');
        void card.offsetWidth;
        card.classList.add('pb-entering');

        html.style.setProperty('--pb-tension', '0');
        mini.classList.remove('pb-visible');

        setTimeout(function () {
            if (m) {
                m.style.transition = 'none';
                m.style.transform = '';
                m.style.filter = '';
                void m.offsetWidth;
                m.style.transition = '';
            }
            wrap.style.transition = 'none';
            wrap.style.transform = '';
            void wrap.offsetWidth;
            wrap.style.transition = '';

            wrap.classList.remove('pb-parked');
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90

            requestAnimationFrame(function () { scrollRoot().scrollTop = 0; });
            html.classList.add('pb-locked');
            document.body.classList.remove('pb-flown');
<<<<<<< HEAD
            mini.classList.remove('pb-visible');

            wrap.classList.add('pb-swap-in');
            setTimeout(function () { wrap.classList.remove('pb-swap-in'); }, 480);
        }, 170);

        setTimeout(function () {
            flown = false;
            animating = false;
        }, 170 + 480);
    }

    /* ── Колесо ──────────────────────────────────────────────────────── */
=======

            scrollRoot().scrollTop = 0;

            flown = false;
            animating = false;
            accum = 0;
            shookThisGesture = false;
        }, SPRING_MS + 30);

        setTimeout(function () { card.classList.remove('pb-entering'); }, SPRING_MS + 700);
    }

>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
    function onWheel(e) {
        if (e.ctrlKey) return;
        if (animating) { e.preventDefault(); return; }

        if (!flown) {
<<<<<<< HEAD
            /* ─── ВНИЗ С БАННЕРА ─── */
            if (e.deltaY <= 0) return;
            e.preventDefault();
            accum += Math.abs(e.deltaY);
=======
            if (e.deltaY <= 0) return;
            e.preventDefault();
            accum += Math.min(Math.abs(e.deltaY), MAX_DELTA_PER_TICK);
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90

            if (!shookThisGesture && accum > MIN_ATTEMPT) {
                shookThisGesture = true;
                triggerShake();
            }

<<<<<<< HEAD
            if (accum >= THRESHOLD_DOWN && attemptsDown >= ATTEMPTS_NEEDED - 1) {
                commitDown();
                return;
=======
            updateTensionDown();

            if (accum >= THRESHOLD_DOWN) {
                commitDown();
                return;
            }
            setNudge(-16 * Math.min(accum / THRESHOLD_DOWN, 1));
            scheduleDecay();
            return;
        }

        if (e.deltaY >= 0) {
            if (accum > 0) {
                accum = 0;
                setMainNudge(0, false);
            }
            return;
        }

        var s = scrollRoot();
        if (s.scrollTop > UP_GATE) {
            accum = 0;
            return;
        }

        e.preventDefault();
        accum += Math.min(Math.abs(e.deltaY), MAX_DELTA_PER_TICK);

        if (accum >= THRESHOLD_UP) {
            commitUp();
            return;
        }

        var p = Math.min(accum / THRESHOLD_UP, 1);
        var eased = Math.pow(p, 0.75);
        setMainNudge(MAIN_PULL * eased, true);
        scheduleDecay();
    }
    window.addEventListener('wheel', onWheel, { passive: false });

    var touchStartY = null;
    window.addEventListener('touchstart', function (e) {
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    window.addEventListener('touchmove', function (e) {
        if (touchStartY === null || animating) return;
        var dy = touchStartY - e.touches[0].clientY;

        if (!flown) {
            if (dy <= 0) return;
            e.preventDefault();
            accum = dy;
            if (!shookThisGesture && accum > MIN_ATTEMPT) {
                shookThisGesture = true;
                triggerShake();
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
            }
            setNudge(-16 * Math.min(accum / THRESHOLD_DOWN, 1));
            /* Честный прогресс: не «набрали 240px», а «это N-я попытка из 3» —
               ровно то, что Лео описывал словами («первые две — трясут»). */
            showTension('down', (attemptsDown + accum / THRESHOLD_DOWN) / ATTEMPTS_NEEDED);
            scheduleDecay();
            return;
        }

<<<<<<< HEAD
        /* ─── ВВЕРХ С ГЛАВНОЙ ─── */
        if (e.deltaY >= 0) {
            if (accum > 0) {
                accum = 0;
                setMainNudge(0, false);
                hideTension();
            }
            return;
        }

        var s = scrollRoot();
        if (s.scrollTop > UP_GATE) {
            accum = 0;
            return;
        }

        e.preventDefault();
        accum += Math.min(Math.abs(e.deltaY), MAX_DELTA_PER_TICK);

        if (accum >= THRESHOLD_UP) {
            commitUp();
            return;
        }

        var p = Math.min(accum / THRESHOLD_UP, 1);
        var eased = Math.pow(p, 0.75);
        setMainNudge(peekHeight() * eased, true);
        showTension('up', p);
        scheduleDecay();
    }
    window.addEventListener('wheel', onWheel, { passive: false });

    /* ── Тач ─────────────────────────────────────────────────────────── */
    var touchStartY = null;
    window.addEventListener('touchstart', function (e) {
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    window.addEventListener('touchmove', function (e) {
        if (touchStartY === null || animating) return;
        var dy = touchStartY - e.touches[0].clientY;

        if (!flown) {
            if (dy <= 0) return;
            e.preventDefault();
            accum = dy;
            if (!shookThisGesture && accum > MIN_ATTEMPT) {
                shookThisGesture = true;
                triggerShake();
            }
            if (accum >= THRESHOLD_DOWN && attemptsDown >= ATTEMPTS_NEEDED - 1) {
                commitDown();
                touchStartY = null;
                return;
            }
            setNudge(-16 * Math.min(accum / THRESHOLD_DOWN, 1));
            showTension('down', (attemptsDown + accum / THRESHOLD_DOWN) / ATTEMPTS_NEEDED);
=======
            updateTensionDown();

            if (accum >= THRESHOLD_DOWN) {
                commitDown();
                touchStartY = null;
                return;
            }
            setNudge(-16 * Math.min(accum / THRESHOLD_DOWN, 1));
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
            return;
        }

        if (dy >= 0) {
<<<<<<< HEAD
            if (accum > 0) { accum = 0; setMainNudge(0, false); hideTension(); }
=======
            if (accum > 0) { accum = 0; setMainNudge(0, false); }
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
            return;
        }

        var s = scrollRoot();
        if (s.scrollTop > UP_GATE) { accum = 0; return; }

        e.preventDefault();
        accum = -dy;
        if (accum >= THRESHOLD_UP) {
            commitUp();
            touchStartY = null;
            return;
        }
        var p = Math.min(accum / THRESHOLD_UP, 1);
<<<<<<< HEAD
        setMainNudge(peekHeight() * Math.pow(p, 0.75), true);
        showTension('up', p);
=======
        setMainNudge(MAIN_PULL * Math.pow(p, 0.75), true);
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
    }, { passive: false });

    window.addEventListener('touchend', function () {
        touchStartY = null;
        if (!flown) {
<<<<<<< HEAD
            if (accum > MIN_ATTEMPT) attemptsDown++;
=======
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
            springBackBanner();
        } else {
            setMainNudge(0, false);
        }
        accum = 0;
        shookThisGesture = false;
<<<<<<< HEAD
        hideTension();
    }, { passive: true });

    /* ── Клавиатура ──────────────────────────────────────────────────── */
=======
    }, { passive: true });

>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
    window.addEventListener('keydown', function (e) {
        if (animating || flown) return;
        if (['PageDown', 'ArrowDown', 'End', ' '].indexOf(e.key) !== -1) {
            commitDown();
        }
    });

<<<<<<< HEAD
    /* ── Стартовое состояние ─────────────────────────────────────────── */
    scrollRoot().scrollTop = 0;
    html.classList.add('pb-locked');
=======
    /* ── Инициализация ────────────────────────────────────────────────
       Развилка:
         - bannerAlreadyShown = true  → wrap сразу паркуется за верхнюю
           кромку, main рендерится сразу, pb-locked НЕ ставится. Баннер
           доступен через натяжение вверх у верха главной.
         - bannerAlreadyShown = false → старое поведение: wrap в потоке
           на первом экране, pb-locked стоит, пока не прорвёмся вниз. */
    if (bannerAlreadyShown) {
        /* Мгновенно, без анимации — до того как браузер отрисует первый
           кадр. Скрипт исполняется до того, как в DOM появится <main>
           из index.php, так что подмена контекста не видна. */
        wrap.classList.add('pb-parked');
        wrap.style.transition = 'none';
        wrap.style.transform = 'translateY(-100%)';
        void wrap.offsetWidth;
        wrap.style.transition = '';

        /* Карточка — нормальная, без pb-flown. */
        card.classList.remove('pb-flown', 'pb-flying', 'pb-shake');

        /* main доступен — блокировку не ставим. */
        document.body.classList.add('pb-flown');
        flown = true;

        if (!dismissed) mini.classList.add('pb-visible');
    } else {
        scrollRoot().scrollTop = 0;
        html.classList.add('pb-locked');
    }
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90

    if (miniClose) {
        miniClose.addEventListener('click', function () {
            dismissed = true;
            mini.classList.remove('pb-visible');
            try { sessionStorage.setItem(DISMISS_KEY, '1'); } catch (e) {}
        });
    }

<<<<<<< HEAD
    /* ── Параллакс от мыши ───────────────────────────────────────────── */
=======
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        var pbRaf = null;
        wrap.addEventListener('mousemove', function (e) {
            if (pbRaf) return;
            pbRaf = requestAnimationFrame(function () {
                pbRaf = null;
                if (flown || animating) return;
                var r = wrap.getBoundingClientRect();
                var mx = (e.clientX - r.left) / r.width - 0.5;
                var my = (e.clientY - r.top) / r.height - 0.5;
                card.style.setProperty('--tiltX', (-my * 6).toFixed(2) + 'deg');
                card.style.setProperty('--tiltY', (mx * 8).toFixed(2) + 'deg');
            });
        });
        wrap.addEventListener('mouseleave', function () {
            card.style.setProperty('--tiltX', '0deg');
            card.style.setProperty('--tiltY', '0deg');
        });
    }

<<<<<<< HEAD
    /* ── Аналитика ───────────────────────────────────────────────────── */
=======
>>>>>>> 8c94329b65bc0ef7fb2df84b19b3b7b338308c90
    if (window.DustoreAnalytics) {
        DustoreAnalytics.observeView(card, 'promotion', PROMO_ID);
        var clicked = false;
        function trackClick() {
            if (clicked) return;
            clicked = true;
            DustoreAnalytics.track('promotion', PROMO_ID, 'click');
        }
        ['pbPlayBtn', 'pbBgLink', 'pbMiniBtn', 'pbReviewCta'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('click', trackClick);
        });
    }
})();
</script>
<?php endif; ?>