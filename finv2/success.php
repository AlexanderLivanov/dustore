<?php
require_once('../../vendor/autoload.php');
require_once('../../swad/config.php');

use YooKassa\Client;

$db = new Database();
$pdo = $db->connect();

session_start();

$subId = $_GET['sub'] ?? null;
$paymentId = $_GET['payment'] ?? null;

// 1. Try to find subscription by ID or payment_id
$subscription = null;
if ($subId) {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $stmt->execute([$subId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$subscription && $paymentId) {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE payment_id = ?");
    $stmt->execute([$paymentId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. Verify payment with YooKassa if we have payment_id
$paymentVerified = false;
$paymentAmount = null;
$paymentPlan = null;

if ($paymentId) {
    try {
        $client = new Client();
        $client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);

        $paymentInfo = $client->getPaymentInfo($paymentId);
        if ($paymentInfo->getStatus() === 'succeeded') {
            $paymentVerified = true;
            $paymentAmount = $paymentInfo->getAmount()->getValue();
            // Get plan from metadata if available
            $metadata = $paymentInfo->getMetadata();
            $paymentPlan = $metadata['plan_code'] ?? 'Подписка';
        } else {
            // Payment not succeeded – still show success page but log
            error_log("Payment $paymentId returned with status: " . $paymentInfo->getStatus());
        }
    } catch (Exception $e) {
        error_log("YooKassa API error in success.php: " . $e->getMessage());
    }
}

// 3. If payment succeeded but subscription missing – create it on the fly
if ($paymentVerified && !$subscription) {
    $userId = $_SESSION['USERDATA']['id'] ?? null;
    if ($userId && $paymentPlan) {
        $stmt = $pdo->prepare("
            INSERT INTO subscriptions (user_id, plan_code, amount, payment_id, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$userId, $paymentPlan, $paymentAmount, $paymentId]);
        $subscription = [
            'id' => $pdo->lastInsertId(),
            'plan_code' => $paymentPlan,
            'amount' => $paymentAmount,
            'status' => 'active'
        ];
    }
}

// 4. If subscription exists and payment succeeded, update status to active
if ($subscription && $paymentVerified && $subscription['status'] !== 'active') {
    $pdo->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ?")
        ->execute([$subscription['id']]);
    $subscription['status'] = 'active';
}

// 5. Prepare display variables
$hasSubscription = !empty($subscription);
$displayStatus = $subscription['status'] ?? null;
$statusText = ($displayStatus === 'active') ? '✅ Оплачено' : '⏳ Ожидает подтверждения';
$statusColor = ($displayStatus === 'active') ? 'var(--success)' : '#f39c12';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
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
            --warning: #f39c12;
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
        <p class="animate-in delay-1">Спасибо за покупку!</p>

        <?php if ($hasSubscription): ?>
            <div class="order-details animate-in delay-2">
                <div class="detail-row">
                    <span>Номер заказа:</span>
                    <span>#<?php echo $subscription['id']; ?></span>
                </div>
                <div class="detail-row">
                    <span>Товар:</span>
                    <span><?php echo htmlspecialchars($subscription['plan_code']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Дата оплаты:</span>
                    <span><?php echo date('d.m.Y H:i'); ?></span>
                </div>
                <div class="detail-row">
                    <span>Сумма:</span>
                    <span><?php echo number_format($subscription['amount'], 0, ',', ' '); ?> ₽</span>
                </div>
                <div class="detail-row">
                    <span>Статус:</span>
                    <span style="color: <?php echo $statusColor; ?>;"><?php echo $statusText; ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="order-details animate-in delay-2">
                <p>Ваш платёж получен, но информация о подписке ещё обновляется.<br>Она появится в личном кабинете в течение нескольких минут.</p>
                <?php if ($paymentId): ?>
                    <p style="font-size:0.9rem; opacity:0.7;">ID платежа: <?php echo htmlspecialchars($paymentId); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="animate-in delay-2">
            <a href="/finv2" class="btn btn-secondary">Перейти к подписке</a>
            <a href="https://vk.com/im?entrypoint=website&media=&sel=-208261651" class="btn btn-secondary">Техподдержка</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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