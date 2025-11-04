<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Мой баланс</title>
    <link rel="stylesheet" href="swad/css/pages.css">
    <link rel="stylesheet" href="swad/css/wallet.css">
    <?php require_once('swad/controllers/ymcounter.php'); ?>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="balance-hero">
            <div class="container">
                <h1 style="font-family: 'PixelizerBold', 'Gill Sans', sans-serif;">Мой баланс</h1>
                <p>Управляйте своими финансами и подпиской Dustore</p>
            </div>
        </section>

        <div class="balance-container">
            <div class="grid-container">
                <div class="left-column">
                    <div class="card animate-in">
                        <h2 class="card-title">💳 Ваша карта Dustore</h2>
                        <div class="bank-card-wrapper">
                            <div class="bank-card" style="background: url('swad/static/img/logo.svg') right no-repeat; background-size: 70%;">
                                <div class="bank-card-front">
                                    <div class="bank-card-logo">
                                        Dustore
                                    </div>
                                    <div class="bank-card-chip"></div>
                                    <div class="bank-card-number"><?= "001337" ?></div>
                                    <div class="bank-card-details">
                                        <div class="bank-card-holder">
                                            <span class="bank-card-label">ВЛАДЕЛЕЦ</span>
                                            <span class="bank-card-info"><?= "USERNAME" ?></span>
                                        </div>
                                        <div class="bank-card-expiry">
                                            <span class="bank-card-label">ДЕЙСТВУЕТ ДО</span>
                                            <span class="bank-card-info">09/30</span>
                                        </div>
                                    </div>

                                    <!-- <div class="bank-card-flip">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                                        </svg>
                                    </div> -->
                                </div>
                                <!-- <div class="bank-card-back">
                                    <div class="bank-card-strip"></div>
                                    <div class="bank-card-cvv">123</div>
                                    <div class="bank-card-logo">
                                        <svg viewBox="0 0 24 24" fill="white">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" />
                                        </svg>
                                        Dustore
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>
                    <div class="card animate-in delay-1">
                        <h2 class="card-title">Текущая подписка: <?= "Dust Priority" ?></h2>
                        <div class="subscription-status">
                            <span class="subscription-badge"><?= "Активна" ?></span>
                            <span>Действует до: <?= "Бессрочная" ?></span>
                        </div>

                        <h3>Преимущества вашей подписки:</h3>
                        <ul class="benefits-list">
                            <li><span class="benefit-icon">✓</span> Эксклюзивный доступ к ранним версиям игр</li>
                            <li><span class="benefit-icon">✓</span> Скидка 10% на все покупки в магазине</li>
                            <li><span class="benefit-icon">✓</span> Дополнительный контент к играм</li>
                            <li><span class="benefit-icon">✓</span> Приоритетная техническая поддержка</li>
                            <li><span class="benefit-icon">✓</span> Участие в закрытых игровых событиях</li>
                        </ul>

                        <div class="action-buttons">
                            <button class="btn btn-primary">Продлить подписку</button>
                            <button class="btn btn-outline">Изменить тариф</button>
                            <button class="btn btn-cancel">Отменить подписку</button>
                        </div>
                    </div>
                </div>

                <div class="right-column">
                    <div class="card animate-in delay-3">
                        <h2 class="card-title">🛒 Корзина</h2>
                        <button class="btn btn-primary" onclick="window.location = '/checkout'">Перейти в корзину</button>
                    </div>
                    <div class="card animate-in delay-2">
                        <h2 class="card-title">💰 Текущий баланс</h2>
                        <div class="balance-amount"><?= "0 ₽ <br> 0 Ⓓ" ?></div>
                        <p>Доступные средства для покупок в Dustore</p>

                        <div class="action-buttons">
                            <button class="btn btn-primary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z" />
                                </svg>
                                Пополнить баланс
                            </button>
                            <button class="btn btn-outline">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zm-5 0h-2V4h2v4zm-6 1h-2V5h2v4zm-4 0H3V5h2v4zm8.5 7.5h-7v-2h7v2zm-8.5 2c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm9 0c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm1-4.5h-9v-2h9v2z" />
                                </svg>
                                Вывести средства
                            </button>
                        </div>

                        <!-- <div class="qr-container">
                            <div class="qr-code">
                                <svg width="150" height="150" viewBox="0 0 150 150"
                                    <rect x="0" y="0" width="150" height="150" fill="white" />
                                    <rect x="10" y="10" width="20" height="20" fill="#2d3436" />
                                    <rect x="10" y="50" width="20" height="20" fill="#2d3436" />
                                    <rect x="10" y="90" width="20" height="20" fill="#2d3436" />
                                    <rect x="10" y="120" width="20" height="20" fill="#2d3436" />

                                    <rect x="50" y="10" width="20" height="20" fill="#2d3436" />
                                    <rect x="50" y="50" width="20" height="20" fill="#2d3436" />
                                    <rect x="50" y="90" width="20" height="20" fill="#2d3436" />
                                    <rect x="50" y="120" width="20" height="20" fill="#2d3436" />

                                    <rect x="90" y="10" width="20" height="20" fill="#2d3436" />
                                    <rect x="90" y="50" width="20" height="20" fill="#2d3436" />
                                    <rect x="90" y="90" width="20" height="20" fill="#2d3436" />
                                    <rect x="90" y="120" width="20" height="20" fill="#2d3436" />

                                    <rect x="120" y="10" width="20" height="20" fill="#2d3436" />
                                    <rect x="120" y="50" width="20" height="20" fill="#2d3436" />
                                    <rect x="120" y="90" width="20" height="20" fill="#2d3436" />
                                    <rect x="120" y="120" width="20" height="20" fill="#2d3436" />

                                    <rect x="35" y="35" width="5" height="5" fill="#2d3436" />
                                    <rect x="45" y="35" width="5" height="5" fill="#2d3436" />
                                    <rect x="55" y="35" width="5" height="5" fill="#2d3436" />

                                    <rect x="35" y="65" width="5" height="5" fill="#2d3436" />
                                    <rect x="65" y="35" width="5" height="5" fill="#2d3436" />

                                    <rect x="85" y="75" width="5" height="5" fill="#2d3436" />
                                    <rect x="95" y="65" width="5" height="5" fill="#2d3436" />
                                    <rect x="105" y="75" width="5" height="5" fill="#2d3436" />
                                </svg>
                            </div>
                            <p class="qr-info">Отсканируйте QR-код для быстрого пополнения счета</p>
                        </div> -->
                    </div>

                    <!-- Последние операции -->
                    <div class="card animate-in delay-3">
                        <h2 class="card-title">📊 Последние операции</h2>
                        <ul class="transactions-list">
                            <li class="transaction-item">
                                <div class="transaction-info">
                                    <span class="transaction-title">Бонус за регистрацию</span>
                                    <span class="transaction-date">только что</span>
                                </div>
                                <div class="transaction-amount positive">+ 2 000 Ⓓ </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

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

            // Переворот банковской карты
            const bankCard = document.querySelector('.bank-card');
            const flipButton = document.querySelector('.bank-card-flip');

            if (flipButton && bankCard) {
                flipButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    bankCard.classList.toggle('flipped');
                });
            }

            // Имитация работы с QR кодом
            const qrCode = document.querySelector('.qr-code');
            if (qrCode) {
                qrCode.addEventListener('click', function() {
                    alert('QR-код содержит информацию для пополнения баланса. Для сканирования используйте приложение банка.');
                });
            }
        });
    </script>
</body>

</html>