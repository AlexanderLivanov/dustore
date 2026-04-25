<?php session_start(); ?>
<?php
require_once('swad/config.php');

$db = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("SELECT id, name, description, avatar_link, website, created_at, tiker FROM studios ORDER BY id DESC");
$stmt->execute();
$studios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — Каталог студий</title>
    <link rel="stylesheet" href="swad/css/explore.css">

    <style>
        /* Улучшенная сетка под студии */
        .studios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            padding-top: 20px;
        }

        .studio-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 18px;
            cursor: pointer;
            transition: transform .25s ease, box-shadow .3s ease;
            border: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            overflow: hidden;
        }

        .studio-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        }

        .studio-logo {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 12px;
            background: #1d1d1d;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .studio-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .studio-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .studio-desc {
            opacity: .7;
            font-size: 14px;
            margin-bottom: 10px;
            height: 40px;
            overflow: hidden;
        }

        .studio-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            opacity: .8;
            font-size: 14px;
        }

        .studio-date {
            font-size: 13px;
            opacity: 0.6;
        }

        /* Поиск */
        .search-studios {
            margin: 15px 0 25px;
            display: flex;
            justify-content: center;
        }

        .search-studios input {
            width: 100%;
            max-width: 420px;
            padding: 12px 15px;
            border-radius: 10px;
            border: none;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            outline: none;
            font-size: 15px;
        }

        .search-studios input::placeholder {
            color: #bbb;
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>

        <section class="games-header">
            <div class="container">
                <h1>Каталог студий</h1>
                <p>Найдите разработчиков, создавших любимые игры</p>
            </div>
        </section>

        <div class="container">

            <div class="search-studios">
                <input type="text" id="studioSearch" placeholder="Поиск студий...">
            </div>

            <div class="studios-grid" id="studiosGrid">
                <?php if (empty($studios)): ?>
                    <div class="no-games-message">
                        <p>Студий пока нет</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($studios as $s): ?>
                        <div class="studio-card" onclick="window.location.href='/d/<?= $s['tiker'] ?>'">
                            <div class="studio-logo">
                                <img src="<?= !empty($s['avatar_link']) ? htmlspecialchars($s['avatar_link']) : '/swad/static/img/logo.svg' ?>">
                            </div>

                            <div class="studio-title"><?= htmlspecialchars($s['name']) ?></div>

                            <div class="studio-desc">
                                <?= htmlspecialchars(mb_strimwidth($s['description'] ?: 'Описание отсутствует', 0, 120, '...')) ?>
                            </div>

                            <div class="studio-footer">
                                <span><?= $s['website'] ? htmlspecialchars($s['website']) : '' ?></span>
                                <span class="studio-date">
                                    Основана: <?= date('d.m.Y', strtotime($s['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <br>

        </div>

    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        // Поиск среди карточек
        document.getElementById('studioSearch').addEventListener('input', function() {
            let term = this.value.toLowerCase();
            let cards = document.querySelectorAll('.studio-card');

            cards.forEach(card => {
                let title = card.querySelector('.studio-title').textContent.toLowerCase();
                let desc = card.querySelector('.studio-desc').textContent.toLowerCase();

                card.style.display = (title.includes(term) || desc.includes(term)) ? "block" : "none";
            });
        });

        // Анимация появления
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.studio-card').forEach((el, index) => {
                el.style.opacity = "0";
                el.style.transform = "translateY(20px)";
                setTimeout(() => {
                    el.style.transition = "0.4s ease";
                    el.style.opacity = "1";
                    el.style.transform = "translateY(0)";
                }, index * 80);
            });
        });
    </script>

</body>

</html>