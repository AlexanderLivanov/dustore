<?php
// Dustore — Public Statistics Page
session_start();
require_once('swad/config.php');

$db = new Database();
$pdo = $db->connect();

/* =========================
   ОСНОВНЫЕ СЧЁТЧИКИ
========================= */

$users_total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$users_online = (int)$pdo->query("
    SELECT COUNT(*) FROM users
    WHERE last_activity >= NOW() - INTERVAL 10 MINUTE
")->fetchColumn();

$games_total = (int)$pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
$games_published = (int)$pdo->query("SELECT COUNT(*) FROM games WHERE status='published'")->fetchColumn();

$studios_total = (int)$pdo->query("SELECT COUNT(*) FROM studios")->fetchColumn();
$reviews_total = (int)$pdo->query("SELECT COUNT(*) FROM game_reviews")->fetchColumn();

$avg_rating = round(
    (float)$pdo->query("SELECT AVG(rating) FROM ratings")->fetchColumn(),
    2
);

$avg_gqi = round(
    (float)$pdo->query("SELECT AVG(GQI) FROM games WHERE status='published'")->fetchColumn(),
    1
);

/* =========================
   АКТИВНОСТЬ ЗА 24 ЧАСА
========================= */

$posts_today = (int)$pdo->query("
    SELECT COUNT(*) FROM posts
    WHERE created_at >= NOW() - INTERVAL 1 DAY
")->fetchColumn();

$comments_today = (int)$pdo->query("
    SELECT COUNT(*) FROM comments
    WHERE created_at >= NOW() - INTERVAL 1 DAY
")->fetchColumn();

$likes_today = (int)$pdo->query("
    SELECT COUNT(*) FROM likes
    WHERE created_at >= NOW() - INTERVAL 1 DAY
")->fetchColumn();

/* =========================
   ГРАФИКИ
========================= */

// Пользователи — 30 дней
$users_growth = $pdo->query("
    SELECT DATE(added) AS d, COUNT(*) AS c
    FROM users
    GROUP BY DATE(added)
    ORDER BY d DESC
    LIMIT 365
")->fetchAll(PDO::FETCH_ASSOC);

// Игры — 30 дней
$games_growth = $pdo->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS c
    FROM games
    GROUP BY DATE(created_at)
    ORDER BY d DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

// Жанры
$genres = $pdo->query("
    SELECT genre, COUNT(*) AS c
    FROM games
    WHERE status='published'
    GROUP BY genre
")->fetchAll(PDO::FETCH_ASSOC);

// Топ игр
$top_games = $pdo->query("
    SELECT g.name, COUNT(l.id) AS installs
    FROM library l
    JOIN games g ON g.id = l.game_id
    GROUP BY g.id
    ORDER BY installs DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Dustore — Статистика платформы</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/swad/css/pages.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: #0f0f0f;
            padding: 20px;
            border-radius: 14px;
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
        }

        .stat-label {
            opacity: .7;
            margin-top: 6px;
        }

        .section {
            margin: 60px 0;
        }

        .platform-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .platform-card {
            background: #0f0f0f;
            padding: 25px;
            border-radius: 16px;
        }
    </style>
</head>

<body>

    <?php require_once('swad/static/elements/header.php'); ?>

    <div class="container">

        <h1>📊 Статистика Dustore</h1>
        <p style="opacity:.7">Данные обновляются в реальном времени</p>

        <!-- ОСНОВНЫЕ СЧЁТЧИКИ -->
        <div class="section">
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
        </div>

        <!-- АКТИВНОСТЬ -->
        <div class="section">
            <h2>Активность за 24 часа</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?= $posts_today ?></div>
                    <div class="stat-label">Постов</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $comments_today ?></div>
                    <div class="stat-label">Комментариев</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $likes_today ?></div>
                    <div class="stat-label">Лайков</div>
                </div>
            </div>
        </div>

        <!-- ГРАФИКИ -->
        <div class="section platform-grid">
            <div class="platform-card">
                <h3>Рост пользователей</h3>
                <canvas id="usersChart"></canvas>
            </div>

            <div class="platform-card">
                <h3>Рост игр</h3>
                <canvas id="gamesChart"></canvas>
            </div>

            <div class="platform-card">
                <h3>Жанры</h3>
                <canvas id="genresChart"></canvas>
            </div>
        </div>

        <!-- ТОП ИГР -->
        <div class="section">
            <h2>Топ игр</h2>
            <ol>
                <?php foreach ($top_games as $g): ?>
                    <li><?= htmlspecialchars($g['name']) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>

    </div>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        new Chart(usersChart, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_reverse(array_column($users_growth, 'd'))) ?>,
                datasets: [{
                    label: 'Пользователи',
                    data: <?= json_encode(array_reverse(array_column($users_growth, 'c'))) ?>
                }]
            }
        });

        new Chart(gamesChart, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_reverse(array_column($games_growth, 'd'))) ?>,
                datasets: [{
                    label: 'Игры',
                    data: <?= json_encode(array_reverse(array_column($games_growth, 'c'))) ?>
                }]
            }
        });

        new Chart(genresChart, {
            type: 'pie',
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