<?php
session_start();
require_once('swad/config.php');

$db = new Database();
$pdo = $db->connect();

/* ===== ОСНОВНЫЕ МЕТРИКИ ===== */

$users_total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$users_online = (int)$pdo->query("
    SELECT COUNT(*) FROM users
    WHERE last_activity >= NOW() - INTERVAL 10 MINUTE
")->fetchColumn();

$games_total = (int)$pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
$games_published = (int)$pdo->query("
    SELECT COUNT(*) FROM games WHERE status='published'
")->fetchColumn();

$studios_total = (int)$pdo->query("SELECT COUNT(*) FROM studios")->fetchColumn();
$reviews_total = (int)$pdo->query("SELECT COUNT(*) FROM game_reviews")->fetchColumn();

$avg_rating = round(
    (float)$pdo->query("SELECT AVG(rating) FROM ratings")->fetchColumn(),
    1
);

$avg_gqi = round(
    (float)$pdo->query("SELECT AVG(gqi) FROM games WHERE gqi IS NOT NULL")->fetchColumn(),
    1
);

/* ===== АКТИВНОСТЬ 24 ЧАСА ===== */

$posts_24h = (int)$pdo->query("
    SELECT COUNT(*) FROM posts
    WHERE created_at >= NOW() - INTERVAL 1 DAY
")->fetchColumn();

$comments_24h = (int)$pdo->query("
    SELECT COUNT(*) FROM comments
    WHERE created_at >= NOW() - INTERVAL 1 DAY
")->fetchColumn();

$likes_24h = (int)$pdo->query("
    SELECT COUNT(*) FROM likes
    WHERE created_at >= NOW() - INTERVAL 1 DAY
")->fetchColumn();

/* ===== РОСТ (30 ДНЕЙ) ===== */

$users_growth = $pdo->query("
    SELECT DATE(added) d, COUNT(*) c
    FROM users
    GROUP BY d
    ORDER BY d DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

$games_growth = $pdo->query("
    SELECT DATE(created_at) d, COUNT(*) c
    FROM games
    GROUP BY d
    ORDER BY d DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

/* ===== НАКОПИТЕЛЬНО ЗА ВСЁ ВРЕМЯ ===== */

$users_all_time = $pdo->query("
    SELECT DATE(added) d, COUNT(*) c
    FROM users
    GROUP BY d
    ORDER BY d
")->fetchAll(PDO::FETCH_ASSOC);

$games_all_time = $pdo->query("
    SELECT DATE(created_at) d, COUNT(*) c
    FROM games
    GROUP BY d
    ORDER BY d
")->fetchAll(PDO::FETCH_ASSOC);

/* ===== ЖАНРЫ ===== */

$genres = $pdo->query("
    SELECT genre, COUNT(*) c
    FROM games
    WHERE status='published'
    GROUP BY genre
")->fetchAll(PDO::FETCH_ASSOC);

/* ===== ТОП ИГР ===== */

$top_games = $pdo->query("
    SELECT g.name, COUNT(l.id) installs
    FROM library l
    JOIN games g ON g.id = l.game_id
    GROUP BY g.id
    ORDER BY installs DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

/* ===== ПОДГОТОВКА НАКОПИТЕЛЬНЫХ ДАННЫХ ===== */

function cumulative(array $rows)
{
    $sum = 0;
    $out = [];
    foreach ($rows as $r) {
        $sum += $r['c'];
        $out[] = ['d' => $r['d'], 'c' => $sum];
    }
    return $out;
}

$users_all_time = cumulative($users_all_time);
$games_all_time = cumulative($games_all_time);
?>
<?php require_once('swad/static/elements/header.php'); ?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Dustore — Статистика</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #c32178;
            --secondary: #74155d;
            --dark: #14041d;
            --light: #f8f9fa;
        }

        body {
            margin: 0;
            background: linear-gradient(var(--dark), var(--secondary));
            color: var(--light);
            font-family: 'Segoe UI', sans-serif;
        }

        .stats-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 20px 60px;
        }

        h1 {
            font-family: 'Tiny5', sans-serif;
            font-size: 48px;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            opacity: .7;
            margin-bottom: 50px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 18px;
        }

        .stat-item {
            background: rgba(255, 255, 255, .07);
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, .12);
        }

        .stat-number {
            font-family: 'Tiny5', sans-serif;
            font-size: 34px;
        }

        .stat-label {
            font-size: 13px;
            opacity: .7;
        }

        .section-2 {
            margin-top: 70px;
        }

        .section-2 h2 {
            margin-bottom: 25px;
        }

        .card {
            background: rgba(255, 255, 255, .05);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, .1);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media(max-width:900px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .top-games {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .top-games li {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .1);
        }
    </style>
</head>

<body>
    <div class="stats-page">
        <h1>📊 Статистика Dustore</h1>
        <div class="subtitle">Данные обновляются в реальном времени</div>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?= $users_total ?></div>
                <div class="stat-label">Пользователей</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $users_online ?></div>
                <div class="stat-label">Онлайн</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $games_total ?></div>
                <div class="stat-label">Игр</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $games_published ?></div>
                <div class="stat-label">Опубликовано</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $studios_total ?></div>
                <div class="stat-label">Студий</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $reviews_total ?></div>
                <div class="stat-label">Отзывов</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $avg_rating ?></div>
                <div class="stat-label">Средний рейтинг</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $avg_gqi ?></div>
                <div class="stat-label">Средний GQI</div>
            </div>
        </div>

        <div class="section-2">
            <h2>Активность за 24 часа</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?= $posts_24h ?></div>
                    <div class="stat-label">Постов</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $comments_24h ?></div>
                    <div class="stat-label">Комментариев</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $likes_24h ?></div>
                    <div class="stat-label">Лайков</div>
                </div>
            </div>
        </div>

        <div class="section-2 grid-2">
            <div class="card">
                <h2>Рост пользователей</h2><canvas id="usersGrowth"></canvas>
            </div>
            <div class="card">
                <h2>Рост игр</h2><canvas id="gamesGrowth"></canvas>
            </div>
        </div>

        <div class="section-2 grid-2">
            <div class="card">
                <h2>Пользователи за всё время</h2><canvas id="usersAll"></canvas>
            </div>
            <div class="card">
                <h2>Игры за всё время</h2><canvas id="gamesAll"></canvas>
            </div>
        </div>

        <div class="section-2 grid-2">
            <div class="card">
                <h2>Жанры</h2><canvas id="genresChart"></canvas>
            </div>
            <div class="card">
                <h2>Топ игр</h2>
                <ul class="top-games">
                    <?php foreach ($top_games as $g): ?>
                        <li><span><?= htmlspecialchars($g['name']) ?></span><strong><?= $g['installs'] ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

    </div>
    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        new Chart(usersGrowth, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column(array_reverse($users_growth), 'd')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column(array_reverse($users_growth), 'c')) ?>
                }]
            }
        });
        new Chart(gamesGrowth, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column(array_reverse($games_growth), 'd')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column(array_reverse($games_growth), 'c')) ?>
                }]
            }
        });
        new Chart(usersAll, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($users_all_time, 'd')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($users_all_time, 'c')) ?>
                }]
            }
        });
        new Chart(gamesAll, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($games_all_time, 'd')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($games_all_time, 'c')) ?>
                }]
            }
        });
        new Chart(genresChart, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($genres, 'genre')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($genres, 'c')) ?>
                }]
            }
        });
    </script>

</body>

</html>