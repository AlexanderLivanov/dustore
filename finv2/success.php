<?php

require_once('../swad/config.php');
session_start();

$db = new Database();
$pdo = $db->connect();

$subId = $_GET['sub'] ?? null;

if (!$subId) {
    header('Location: fail?reason=invalid');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id=?");
$stmt->execute([$subId]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    header('Location: fail?reason=not_found');
    exit;
}

if ($subscription['status'] !== 'active') {
    header('Location: fail?reason=not_confirmed');
    exit;
}

$id = $subscription['id'];
$planName = $subscription['plan_code'];
$amount = $subscription['amount'];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплата успешна - Dustore</title>
    <link rel="stylesheet" href="/swad/css/pages.css">
    <style>
        :root {
            --primary: #c32178;
            --secondary: #74155d;
            --dark: #14041d;
            --light: #f8f9fa;
            --success: #00b894;
            --danger: #d63031;
        }

        body {
            background: linear-gradient(#14041d, #400c4a, #74155d, #c32178);
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .payment-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            max-width: 500px;
            width: 90%;
            margin: 20px;
        }

        .success-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease-out;
        }

        h1 {
            font-family: 'PixelizerBold', 'Gill Sans', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--success);
        }

        p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .order-details {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            margin: 10px;
            border: none;
            cursor: pointer;
            font-family: 'PixelizerBold', 'Gill Sans', sans-serif;
        }

        .btn:hover {
            background: #e62e8a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(195, 33, 120, 0.4);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid #fff;
            color: #fff;
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeIn 0.6s ease forwards;
        }

        .delay-1 {
            animation-delay: 0.2s;
        }

        .delay-2 {
            animation-delay: 0.4s;
        }

        /* Адаптивность */
        @media (max-width: 600px) {
            .payment-container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 2rem;
            }

            .success-icon {
                font-size: 4rem;
            }
        }
    </style>
</head>

<body>
    <div class="payment-container">
        <div class="success-icon animate-in">🎉</div>
        <h1 class="animate-in delay-1">Оплата успешна!</h1>
        <?php echo $tg_is_empty_warning ?>
        <p class="animate-in delay-1">Спасибо за покупку!</p>

        <div class="order-details animate-in delay-2">
            <div class="detail-row">
                <span>Номер заказа:</span>
                <span>#<?php echo $id; ?></span>
            </div>
            <div class="detail-row">
                <span>Товар:</span>
                <span><?php echo htmlspecialchars($planName); ?></span>
            </div>
            <div class="detail-row">
                <span>Дата оплаты:</span>
                <span><?php echo date('d.m.Y H:i'); ?></span>
            </div>
            <div class="detail-row">
                <span>Сумма:</span>
                <span><?php echo number_format($amount, 0, ',', ' '); ?> ₽</span>
            </div>
            <div class="detail-row">
                <span>Статус:</span>
                <span style="color: var(--success);">✅ Оплачено</span>
            </div>
        </div>

        <div class="animate-in delay-2">
            <a href="/swad/controllers/download_game.php?game_id=<?= $item['game_zip_url'] ?>'" class="btn">Скачать прямо сейчас!</a>
            <a href="/library" class="btn btn-secondary">Перейти к библиотеке</a>
            <a href="/explore" class="btn btn-secondary">Посмотреть ещё игры</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация появления элементов
            const animatedElements = document.querySelectorAll('.animate-in');
            animatedElements.forEach((element, index) => {
                element.style.opacity = '0';
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>

</html>