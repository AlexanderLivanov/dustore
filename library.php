<?php

/**
 * library.php — «Моя коллекция», steam-подобная библиотека.
 *
 * Та же ЛИЧНАЯ коллекция, что и в profile.php (тот же запрос к library/games —
 * см. комментарий у SQL ниже), но другая витрина: слева список игр, справа —
 * сама страница выбранной игры.
 *
 * Правая панель — это буквально /g/{id} в <iframe> с флагом ?embed=1
 * (game.php сам решает не подключать хедер/футер сайта в этом режиме —
 * вся остальная логика владения/цены/скачивания та же, что на обычной
 * странице игры). Сознательно не переписан второй раз здесь: любое поле
 * или кнопку, которую позже добавят на страницу игры, библиотека получит
 * бесплатно, без отдельной синхронизации двух шаблонов.
 */

session_start();
require_once('swad/config.php');

$db  = new Database();
$pdo = $db->connect();

$user_id = (int)($_SESSION['USERDATA']['id'] ?? 0);
if ($user_id <= 0) {
    header('Location: /login?backUrl=/library');
    exit;
}

$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt_user->execute([':user_id' => $user_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC) ?: [
    'id' => $user_id,
    'first_name' => 'Игрок',
    'last_name' => '',
    'username' => 'player',
];

/* Тот же LEFT JOIN одним запросом, что и в profile.php — не гоняем
   отдельный SELECT ... FROM games на каждую строку библиотеки. */
$stmt_items = $pdo->prepare("
    SELECT l.*,
           g.name        AS g_name,
           g.path_to_cover AS g_cover,
           g.platforms   AS g_platforms
      FROM library l
      LEFT JOIN games g ON g.id = l.game_id
     WHERE l.player_id = :user_id
     ORDER BY l.date DESC
     LIMIT 500
");
$stmt_items->execute([':user_id' => $user_id]);
$all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$games = [];
$collectibles = [];

foreach ($all_items as $item) {
    if (!empty($item['game_id']) && $item['game_id'] > 0) {
        // Игра могла быть удалена — JOIN тогда отдаёт NULL, такую строку пропускаем
        // (то же поведение, что и в profile.php).
        if ($item['g_name'] !== null) {
            $item['title']       = $item['g_name'];
            $item['cover_image'] = $item['g_cover'];
            $item['is_web']      = in_array('Web', array_map('trim', explode(',', (string)($item['g_platforms'] ?? ''))), true);
            $item['item_type']   = 'game';
            $item['is_liked_only'] = false;
            $games[] = $item;
        }
    } else {
        $item['item_type'] = 'collectible';
        if (empty($item['title']))       $item['title']       = 'Коллекционный предмет #' . $item['id'];
        if (empty($item['description'])) $item['description'] = 'Особый коллекционный предмет';
        $collectibles[] = $item;
    }
}

/* Лайкнутые, но не купленные/не скачанные игры — сохранены «в коллекцию»
   сердечком на странице игры (game.php, api/wishlist/toggle.php), без
   владения. Та же правая панель (реальная /g/{id}?embed=1) показывает их
   ровно как обычную страницу игры с кнопкой «Купить»/«Скачать» — ничего
   специального дорисовывать не нужно, честно показываем как есть. */
$stmt_liked = $pdo->prepare("
    SELECT w.game_id, g.name AS g_name, g.path_to_cover AS g_cover, g.platforms AS g_platforms
      FROM wishlists w
      JOIN games g ON g.id = w.game_id
     WHERE w.user_id = :user_id
       AND w.game_id NOT IN (SELECT game_id FROM library WHERE player_id = :user_id)
     ORDER BY w.created_at DESC
     LIMIT 500
");
$stmt_liked->execute([':user_id' => $user_id]);
foreach ($stmt_liked->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $item['title']         = $item['g_name'];
    $item['cover_image']   = $item['g_cover'];
    $item['is_web']        = in_array('Web', array_map('trim', explode(',', (string)($item['g_platforms'] ?? ''))), true);
    $item['item_type']     = 'game';
    $item['is_liked_only'] = true;
    $games[] = $item;
}

/* Какую игру открыть в правой панели при загрузке: то, что попросили через
   ?g=, иначе первая в списке. Переключение между играми дальше — просто
   смена src у iframe в JS, сюда сервер уже не привлекается. */
$selectedGameId = isset($_GET['g']) ? (int)$_GET['g'] : 0;
$selected = null;
foreach ($games as $g) {
    if ((int)$g['game_id'] === $selectedGameId) { $selected = $g; break; }
}
if (!$selected && $games) $selected = $games[0];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Моя коллекция — Dustore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0e0416;
            min-height: 100vh;
            color: #f8f9fa;
        }

        main { padding: 28px 20px 60px; }

        .lib-container {
            max-width: 1440px;
            margin: 0 auto;
        }

        .lib-heading {
            display: flex;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 16px;
        }

        .lib-heading h1 {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .lib-heading .count {
            font-size: .8rem;
            color: #b79ab0;
        }

        /* ── Steam-подобная раскладка: список слева, сама игра справа.
           Список чуть шире и с более крупными обложками, чем раньше — раньше
           колонка была тесной и подпись «Моя коллекция» переносилась. Панель
           игры — на весь оставшийся простор и заметно выше: это теперь не
           карточка-заглушка, а полноценная страница игры внутри iframe. ── */
        .lib-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .lib-layout { grid-template-columns: 1fr; }
        }

        .lib-list {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 18px;
            overflow: hidden;
            max-height: 80vh;
            overflow-y: auto;
        }

        .lib-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            text-decoration: none;
            color: #e9e3ea;
            cursor: pointer;
            border-left: 3px solid transparent;
            transition: background .15s, border-color .15s;
        }

        .lib-row:hover { background: rgba(255, 255, 255, .05); }

        .lib-row.active {
            background: rgba(195, 33, 120, .16);
            border-left-color: #c32178;
        }

        .lib-row-cover {
            width: 64px;
            height: 64px;
            flex: none;
            border-radius: 12px;
            background-size: cover;
            background-position: center;
            background-color: #1a1233;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .1);
        }

        .lib-row-text { min-width: 0; }

        .lib-row-title {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .lib-row-sub {
            font-size: 10.5px;
            color: #9c8a9a;
            margin-top: 2px;
        }

        .lib-empty-list {
            padding: 26px 18px;
            text-align: center;
            font-size: 12.5px;
            color: #a98fa5;
        }

        /* ── Правая панель: реальная страница игры в iframe ── */
        .lib-detail {
            position: relative;
            min-height: 640px;
            height: 80vh;
            border-radius: 18px;
            overflow: hidden;
            background: linear-gradient(160deg, #14041d 0%, #400c4a 45%, #74155d 78%, #c32178 100%);
            box-shadow: 0 30px 80px -30px rgba(0, 0, 0, .8), 0 0 0 1px rgba(255, 255, 255, .06) inset;
        }

        .lib-frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        .lib-empty-detail {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            text-align: center;
            padding: 30px;
        }

        .lib-empty-detail p {
            max-width: 360px;
            color: #cdb9c9;
            font-size: 13px;
            line-height: 1.55;
        }

        .lib-btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 11px 20px;
            border-radius: 999px;
            border: 0;
            background: #c32178;
            color: #fff;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, transform .15s;
        }

        .lib-btn:hover { background: #e62e8a; }
        .lib-btn:active { transform: scale(.97); }

        /* ── Заготовка запуска через лаунчер ── живёт под панелью игры,
           а не внутри неё: она не про конкретную игру, а про способ её
           запустить вообще — ничего не запускает, честная заглушка на
           месте будущей кнопки, пока фанатский лаунчер не интегрирован. */
        .lib-launcher {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .04);
            border: 1px dashed rgba(255, 255, 255, .18);
        }

        .lib-launcher__icon {
            flex: none;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
            font-size: 16px;
        }

        .lib-launcher__title {
            font-size: 12.5px;
            font-weight: 700;
        }

        .lib-launcher__sub {
            font-size: 11.5px;
            color: #b8a8b6;
            line-height: 1.4;
        }

        /* ── Коллекционные предметы — та же полка, что на /profile, просто ниже ── */
        .lib-collectibles {
            margin-top: 44px;
        }

        .lib-collectibles h2 {
            font-size: 1.1rem;
            margin-bottom: 14px;
            color: #ffbe0b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lib-coll-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 18px;
        }

        .lib-coll-card {
            border-radius: 14px;
            overflow: hidden;
            height: 230px;
            position: relative;
            background: rgba(255, 255, 255, .04);
            border: 2px solid #a0a0a0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 16px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .6);
        }

        .lib-coll-card[data-rarity="1"] { border-color: #00ff00; box-shadow: 0 0 20px rgba(0, 255, 0, .25); }
        .lib-coll-card[data-rarity="2"] { border-color: #007bff; box-shadow: 0 0 20px rgba(0, 123, 255, .3); }
        .lib-coll-card[data-rarity="3"] { border-color: #800080; box-shadow: 0 0 24px rgba(128, 0, 128, .35); }
        .lib-coll-card[data-rarity="4"] { border-color: #ffd700; box-shadow: 0 0 28px rgba(255, 215, 0, .45); }

        .lib-coll-title { font-weight: 700; font-size: .88em; margin-bottom: 8px; }
        .lib-coll-rarity {
            font-size: .7em;
            padding: 3px 10px;
            border-radius: 12px;
            background: rgba(0, 0, 0, .5);
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <div class="lib-container">
            <div class="lib-heading">
                <h1>Моя коллекция</h1>
                <span class="count"><?= count($games) ?> <?= count($games) === 1 ? 'игра' : 'игр' ?><?php if ($collectibles): ?> · <?= count($collectibles) ?> предметов<?php endif; ?></span>
            </div>

            <div class="lib-layout">
                <div class="lib-list" id="libList">
                    <?php if (empty($games)): ?>
                        <div class="lib-empty-list">
                            Пока нет ни одной игры в коллекции.<br>
                            <a href="/explore" style="color:#e88fc0;">Загляните в каталог →</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($games as $g): ?>
                            <a class="lib-row<?= $selected && (int)$selected['game_id'] === (int)$g['game_id'] ? ' active' : '' ?>"
                               href="?g=<?= (int)$g['game_id'] ?>" data-id="<?= (int)$g['game_id'] ?>">
                                <span class="lib-row-cover" style="<?= $g['cover_image'] ? "background-image:url('" . htmlspecialchars($g['cover_image']) . "')" : '' ?>"></span>
                                <span class="lib-row-text">
                                    <span class="lib-row-title"><?= htmlspecialchars($g['title']) ?></span>
                                    <span class="lib-row-sub"><?= $g['is_liked_only'] ? '❤ Понравилось' : ($g['is_web'] ? 'Веб-игра' : 'Установлено') ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="lib-detail" id="libDetail">
                    <?php if ($selected): ?>
                        <iframe class="lib-frame" id="libFrame"
                                src="/g/<?= (int)$selected['game_id'] ?>?embed=1"
                                title="<?= htmlspecialchars($selected['title']) ?>"></iframe>
                    <?php else: ?>
                        <div class="lib-empty-detail">
                            <p>В коллекции пока нет ни одной игры. Всё, что вы добавите на Dustore, появится здесь — с полной страницей игры и быстрым запуском.</p>
                            <a class="lib-btn" href="/explore">Перейти в каталог</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selected && empty($selected['is_liked_only'])): ?>
                <a class="lib-launcher" href="/launcher" style="text-decoration:none;color:inherit;cursor:pointer;">
                    <span class="lib-launcher__icon">🚀</span>
                    <div>
                        <div class="lib-launcher__title">DustoreX уже можно скачать</div>
                        <div class="lib-launcher__sub">Лаунчер от eXepc — библиотека, время в игре и отзывы в отдельном приложении. Прямой запуск отсюда, со страницы коллекции, добавим позже.</div>
                    </div>
                </a>
            <?php endif; ?>

            <?php if ($collectibles): ?>
                <div class="lib-collectibles">
                    <h2>🏆 Коллекционные предметы</h2>
                    <div class="lib-coll-grid">
                        <?php foreach ($collectibles as $item): ?>
                            <?php $rarity = (int)($item['rarity'] ?? 0); ?>
                            <div class="lib-coll-card" data-rarity="<?= $rarity ?>">
                                <div class="lib-coll-title"><?= htmlspecialchars(mb_strimwidth($item['title'], 0, 30, '…')) ?></div>
                                <div class="lib-coll-rarity">
                                    <?= match ($rarity) {
                                        0 => 'Обычный', 1 => 'Необычный', 2 => 'Редкий', 3 => 'Эпический', 4 => 'Легендарный', default => 'Обычный',
                                    } ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        (function() {
            var list  = document.getElementById('libList');
            var frame = document.getElementById('libFrame');
            if (!list || !frame) return;

            list.addEventListener('click', function(e) {
                var row = e.target.closest('.lib-row');
                if (!row) return;
                var id = parseInt(row.getAttribute('data-id'), 10);
                if (!id) return;

                e.preventDefault();
                list.querySelectorAll('.lib-row.active').forEach(function(r) { r.classList.remove('active'); });
                row.classList.add('active');
                frame.src = '/g/' + id + '?embed=1';

                var url = new URL(window.location.href);
                url.searchParams.set('g', id);
                window.history.replaceState({}, '', url);
            });
        })();
    </script>
</body>

</html>
