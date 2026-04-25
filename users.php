<?php session_start(); ?>
<?php
require_once('swad/config.php');

$db = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("SELECT id, first_name, last_name, telegram_username, username, profile_picture, added, country, city, website, email FROM users ORDER BY id DESC LIMIT 100");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore —  пользователей</title>
    <link rel="stylesheet" href="swad/css/explore.css">
    <link rel="shortcut icon" href="swad/static/img/logo.svg" type="image/x-icon">
    <style>
        /* Улучшенная сетка под пользователей */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            padding-top: 20px;
        }

        .user-card {
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

        .user-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 12px;
            background: #1d1d1d;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .user-username {
            opacity: .7;
            font-size: 14px;
            margin-bottom: 10px;
            color: #aaa;
        }

        .user-location {
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-location i {
            opacity: 0.7;
        }

        .user-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            opacity: .8;
            font-size: 14px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-date {
            font-size: 13px;
            opacity: 0.6;
        }

        .user-platforms {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .platform-tag {
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
        }

        /* Поиск */
        .search-users {
            margin: 15px 0 25px;
            display: flex;
            justify-content: center;
        }

        .search-users input {
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

        .search-users input::placeholder {
            color: #bbb;
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>

        <section class="games-header">
            <div class="container">
                <h1>Поиск пользователя</h1>
                <p>Найдите других участников сообщества</p>
            </div>
        </section>

        <div class="container">

            <div class="search-users">
                <input type="text" id="userSearch" placeholder="Начние вводить имя пользователя">
            </div>

            <div class="users-grid" id="usersGrid">
                <?php if (empty($users)): ?>
                    <div class="no-users-message">
                        <p>Пользователей пока нет</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
                        $displayName = !empty($fullName) ? $fullName : ($user['username'] ?? $user['telegram_username'] ?? 'Пользователь');

                        $username = $user['username'] ?? '';

                        $location = '';
                        if (!empty($user['city']) && !empty($user['country'])) {
                            $location = $user['city'] . ', ' . $user['country'];
                        } elseif (!empty($user['city'])) {
                            $location = $user['city'];
                        } elseif (!empty($user['country'])) {
                            $location = $user['country'];
                        }

                        // Аватарка
                        $avatar = !empty($user['profile_picture'])
                            ? htmlspecialchars($user['profile_picture'])
                            : '/swad/static/img/logo.svg';
                        ?>
                        <div class="user-card" onclick="window.location.href='/player/<?= $user['username'] ?>'">
                            <div class="user-avatar">
                                <img src="<?= $avatar ?>" alt="<?= htmlspecialchars($displayName) ?>">
                            </div>

                            <div class="user-name"><?= htmlspecialchars($displayName) ?></div>

                            <?php if (!empty($username)): ?>
                                <div class="user-username">@<?= htmlspecialchars($username) ?></div>
                            <?php endif; ?>

                            <?php if (!empty($location)): ?>
                                <div class="user-location">
                                    <i>📍</i> <?= htmlspecialchars($location) ?>
                                </div>
                            <?php endif; ?>

                            <div class="user-footer">
                                <span>
                                    <?php if (!empty($user['website'])): ?>
                                        <?= htmlspecialchars(parse_url($user['website'], PHP_URL_HOST) ?? $user['website']) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="user-date">
                                    С <?= date('d.m.Y', strtotime($user['added'])) ?>
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
        // Поиск среди карточек пользователей
        document.getElementById('userSearch').addEventListener('input', function() {
            let term = this.value.toLowerCase();
            let cards = document.querySelectorAll('.user-card');

            cards.forEach(card => {
                let name = card.querySelector('.user-name').textContent.toLowerCase();
                let username = card.querySelector('.user-username')?.textContent.toLowerCase() || '';
                let location = card.querySelector('.user-location')?.textContent.toLowerCase() || '';

                card.style.display = (name.includes(term) || username.includes(term) || location.includes(term)) ?
                    "block" :
                    "none";
            });
        });

        // Анимация появления
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.user-card').forEach((el, index) => {
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