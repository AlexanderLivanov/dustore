<?php
session_start();
require_once('swad/config.php');
require_once('swad/controllers/NotificationCenter.php');

if (!isset($_SESSION['USERDATA'])) {
    header('Location: /login'); // редирект, если не авторизован
    exit;
}

$nc = new NotificationCenter();
$user_id = $_SESSION['USERDATA']['id'];

// Обработка AJAX POST запроса для отметки уведомления как прочитанного
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mark_read_id'])) {
    $note_id = (int)$_POST['mark_read_id'];
    $nc->markAsRead($note_id);
    echo json_encode(['success' => true]);
    exit;
}

// Получаем уведомления
$notifications = $nc->getUserNotifications($user_id, 50);
// print_r($notifications);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Уведомления</title>
    <link rel="stylesheet" href="swad/css/explore.css">
    <style>
        .notifications-container {
            max-width: 900px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }

        .notification-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px 25px;
            margin-bottom: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-card.unread {
            border-left: 4px solid var(--primary);
            border-left-color: var(--secondary);
            background: rgba(195, 33, 120, 0.3);
        }

        .notification-card:hover {
            background: rgba(195, 33, 120, 0.15);
        }

        .notification-title {
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .notification-message {
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .notification-action {
            display: inline-block;
            font-size: 0.9rem;
            color: var(--primary);
            text-decoration: underline;
        }

        /* Модальное окно */
        .notification-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .notification-modal-content {
            background: var(--dark);
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            position: relative;
        }

        .notification-modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--primary);
        }
    </style>

</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <div class="notifications-container">
            <h2>Уведомления</h2>

            <?php if (empty($notifications)): ?>
                <p>У вас пока нет уведомлений</p>
            <?php else: ?>
                <?php foreach ($notifications as $note): ?>
                    <div class="notification-card <?= ($note['status'] === 'unread') ? 'unread' : '' ?>"
                        data-id="<?= $note['id'] ?>"
                        data-title="<?= htmlspecialchars($note['title'], ENT_QUOTES) ?>"
                        data-message="<?= htmlspecialchars($note['message'], ENT_QUOTES) ?>"
                        data-action="<?= htmlspecialchars($note['action'], ENT_QUOTES) ?>">
                        <div class="notification-title"><?= htmlspecialchars($note['title']) ?></div>
                        <div class="notification-message"><?= htmlspecialchars(mb_strimwidth($note['message'], 0, 80, '...')) ?></div>
                        <?php if ($note['action']): ?>
                            <div class="notification-action"><?= htmlspecialchars($note['action']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Модальное окно -->
    <div class="notification-modal" id="notificationModal">
        <div class="notification-modal-content">
            <span class="notification-modal-close" id="modalClose">&times;</span>
            <h3 id="modalTitle"></h3>
            <p id="modalMessage"></p>
            <a href="#" id="modalAction" class="btn" style="display:none;">Перейти</a>
        </div>
    </div>

    <?php require_once('swad/static/elements/footer.php'); ?>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.notification-card');
            const modal = document.getElementById('notificationModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalAction = document.getElementById('modalAction');
            const modalClose = document.getElementById('modalClose');

            if (!modal || !modalTitle || !modalMessage || !modalClose) return;

            cards.forEach(card => {
                card.addEventListener('click', () => {
                    const id = card.dataset.id;
                    const title = card.dataset.title;
                    const message = card.dataset.message;
                    const action = card.dataset.action;

                    modalTitle.textContent = title;
                    modalMessage.textContent = message;

                    if (action) {
                        modalAction.style.display = 'inline-block';
                        modalAction.href = action;
                    } else {
                        modalAction.style.display = 'none';
                        modalAction.href = '#';
                    }

                    modal.style.display = 'flex';

                    // Отмечаем как прочитанное через POST на этой же странице
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'mark_read_id=' + encodeURIComponent(id)
                    }).then(() => {
                        card.classList.remove('unread');
                    }).catch(err => console.error(err));
                });
            });

            modalClose.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.style.display = 'none';
            });
        });
    </script>

</body>

</html>