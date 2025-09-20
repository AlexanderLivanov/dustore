<?php
session_start();
$error_message = $_GET['reason'] ?? 'Неизвестная ошибка';
$error_messages = [
    'insufficient_funds' => 'Недостаточно средств на счете',
    'card_declined' => 'Карта отклонена банком',
    'timeout' => 'Время операции истекло',
    'technical' => 'Техническая ошибка',
    'cancelled' => 'Операция отменена'
];

$display_message = $error_messages[$error_message] ?? $error_message;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ошибка оплаты - Dustore</title>
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

        .error-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: shake 0.5s ease-out;
        }

        h1 {
            font-family: 'PixelizerBold', 'Gill Sans', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--danger);
        }

        p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .error-details {
            background: rgba(214, 48, 49, 0.1);
            border: 1px solid rgba(214, 48, 49, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
        }

        .error-code {
            font-family: 'Courier New', monospace;
            background: rgba(0, 0, 0, 0.2);
            padding: 5px 10px;
            border-radius: 5px;
            margin-top: 10px;
            display: inline-block;
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

        .btn-danger {
            background: var(--danger);
        }

        .btn-danger:hover {
            background: #ff4757;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
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

            .error-icon {
                font-size: 4rem;
            }
        }
    </style>
</head>

<body>
    <div class="payment-container">
        <div class="error-icon animate-in">❌</div>
        <h1 class="animate-in delay-1">Ошибка оплаты</h1>
        <p class="animate-in delay-1">К сожалению, произошла ошибка при обработке платежа.</p>

        <div class="error-details animate-in delay-2">
            <strong>Причина:</strong><br>
            <?php echo htmlspecialchars($display_message); ?>
            <!-- <div class="error-code">Код ошибки: <?php echo strtoupper(substr(md5(rand()), 0, 8)); ?></div> -->
        </div>

        <div class="animate-in delay-2">
            <a href="/checkout" class="btn">Вернуться в корзину</a>
            <a href="https://vk.com/im?entrypoint=website&media=&sel=-208261651" class="btn btn-secondary">Техподдержка</a>
            <a href="/explore" class="btn btn-danger">К играм</a>
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