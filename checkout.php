<?php
session_start();
require_once('swad/config.php');
require_once('swad/controllers/user.php');
require_once('swad/controllers/game.php');


// Получаем корзину из cookie
$cart = [];
if (isset($_COOKIE['USERCART'])) {
    $cart = json_decode($_COOKIE['USERCART'], true);
    if ($cart === null) {
        $cart = [];
    }
}

// Получаем информацию о играх в корзине
$cartItems = [];
$cartTotal = 0;
$cartDiscount = 0;
$gameController = new Game();

foreach ($cart as $gameId => $cartItem) {
    $game = $gameController->getGameById($gameId);
    if ($game) {
        // Здесь можно добавить логику для скидок
        $originalPrice = $game['price'];
        $finalPrice = $game['price']; // Базовая цена

        $cartItems[] = [
            'id' => $gameId,
            'title' => $game['name'],
            'image' => $game['path_to_cover'] ?? '',
            'description' => $game['description'] ?? '',
            'price' => $finalPrice,
            'original_price' => $originalPrice,
            'quantity' => $cartItem['quantity'],
            'tags' => explode(',', $game['genre'] ?? ''),
            'studio' => $game['studio_name'] ?? 'Неизвестно'
        ];

        $cartTotal += $finalPrice * $cartItem['quantity'];
        if ($originalPrice > $finalPrice) {
            $cartDiscount += ($originalPrice - $finalPrice) * $cartItem['quantity'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Корзина</title>
    <link rel="stylesheet" href="swad/css/pages.css">
    <link rel="stylesheet" href="swad/css/checkout.css">
    <?php require_once('swad/controllers/ymcounter.php'); ?>
    <style>
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .quantity-btn:hover {
            background: var(--primary);
        }

        .quantity-display {
            min-width: 30px;
            text-align: center;
            font-weight: bold;
        }

        .item-total {
            font-weight: bold;
            color: var(--light);
            margin-top: 5px;
        }

        .loading-spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 5px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="cart-hero">
            <div class="container">
                <h1 style="font-family: 'PixelizerBold', 'Gill Sans', sans-serif;">Корзина</h1>
                <h2 style="color: red">Это демо страница, она не показывает реальные данные!</h2>
                <p>Ваши выбранные игры готовы к покупке</p>
            </div>
        </section>

        <div class="cart-container">
            <?php if (!empty($cartItems)): ?>
                <div class="cart-grid">
                    <div class="left-column">
                        <div class="card animate-in">
                            <h2 class="card-title">🛒 Ваши игры (<?php echo count($cartItems); ?>)</h2>
                            <ul class="cart-items">
                                <?php foreach ($cartItems as $item): ?>
                                    <li class="cart-item" id="cart-item-<?php echo $item['id']; ?>">
                                        <img src="<?php echo $item['image'] ?: '/swad/static/img/hg-icon.jpg'; ?>"
                                            alt="<?php echo $item['title']; ?>"
                                            class="cart-item-image">
                                        <div class="cart-item-details">
                                            <h3 class="cart-item-title"><?php echo $item['title']; ?></h3>
                                            <p class="cart-item-description"><?php echo mb_substr($item['description'], 0, 100) . '...'; ?></p>
                                            <div class="cart-item-meta">
                                                <span class="cart-item-tag"><?php echo $item['studio']; ?></span>
                                                <?php foreach (array_slice($item['tags'], 0, 3) as $tag): ?>
                                                    <span class="cart-item-tag"><?php echo trim($tag); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="cart-item-actions">
                                                <div class="cart-item-pricing">
                                                    <?php if ($item['original_price'] > $item['price']): ?>
                                                        <span class="cart-item-original-price"><?php echo number_format($item['original_price'], 0, ',', ' '); ?> ₽</span>
                                                        <span class="cart-item-discount">-<?php echo number_format($item['original_price'] - $item['price'], 0, ',', ' '); ?> ₽</span>
                                                    <?php endif; ?>
                                                    <div class="cart-item-price"><?php echo number_format($item['price'], 0, ',', ' '); ?> ₽/шт.</div>
                                                    <div class="item-total">
                                                        Итого: <?php echo number_format($item['price'] * $item['quantity'], 0, ',', ' '); ?> ₽
                                                    </div>
                                                </div>
                                                <div class="quantity-controls">
                                                    <button class="quantity-btn decrease-btn"
                                                        data-game-id="<?php echo $item['id']; ?>">-</button>
                                                    <span class="quantity-display" id="quantity-<?php echo $item['id']; ?>">
                                                        <?php echo $item['quantity']; ?>
                                                    </span>
                                                    <button class="quantity-btn increase-btn"
                                                        data-game-id="<?php echo $item['id']; ?>">+</button>
                                                </div>
                                                <button class="remove-btn" data-game-id="<?php echo $item['id']; ?>">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                                                    </svg>
                                                    Удалить
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="right-column">
                        <div class="card summary-card animate-in delay-1">
                            <h2 class="card-title">📋 Итоги заказа</h2>

                            <div class="summary-line">
                                <span>Товары (<?php echo array_sum(array_column($cartItems, 'quantity')); ?>)</span>
                                <span><?php echo number_format($cartTotal + $cartDiscount, 0, ',', ' '); ?> ₽</span>
                            </div>

                            <?php if ($cartDiscount > 0): ?>
                                <div class="summary-line summary-discount">
                                    <span>Скидка</span>
                                    <span>-<?php echo number_format($cartDiscount, 0, ',', ' '); ?> ₽</span>
                                </div>
                            <?php endif; ?>

                            <div class="summary-line">
                                <span>Итого</span>
                                <span class="summary-total"><?php echo number_format($cartTotal, 0, ',', ' '); ?> ₽</span>
                            </div>

                            <button class="btn btn-primary" id="checkout-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                </svg>
                                Перейти к оплате
                            </button>

                            <button class="btn btn-outline" onclick="location.href='/explore'">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11 9h2V6h3V4h-3V1h-2v3H8v2h3v3zm-4 9c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zm-9.83-3.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.86-7.01L19.42 4h-.01l-1.1 2-2.76 5H8.53l-.13-.27L6.16 6l-.95-2-.94-2H1v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.13 0-.25-.11-.25-.25z" />
                                </svg>
                                Продолжить покупки
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h2 class="empty-cart-text">Ваша корзина пуста</h2>
                    <p>Добавьте игры, чтобы они отобразились здесь</p>
                    <button class="btn btn-primary" style="max-width: 250px; margin: 20px auto;" onclick="location.href='/explore'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                        </svg>
                        Перейти к каталогу
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        class CartManager {
            constructor() {
                this.bindEvents();
            }

            bindEvents() {
                // Удаление товаров
                document.querySelectorAll('.remove-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        this.removeFromCart(e.target.closest('.remove-btn'));
                    });
                });

                // Увеличение количества
                document.querySelectorAll('.increase-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        this.updateQuantity(e.target.closest('.increase-btn'), 'ADD');
                    });
                });

                // Уменьшение количества
                document.querySelectorAll('.decrease-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        this.updateQuantity(e.target.closest('.decrease-btn'), 'DECREASE');
                    });
                });

                // Оформление заказа
                document.getElementById('checkout-btn')?.addEventListener('click', () => {
                    this.checkout();
                });
            }

            async updateQuantity(button, method) {
                const gameId = button.dataset.gameId;
                const quantityDisplay = document.getElementById(`quantity-${gameId}`);

                button.disabled = true;
                const originalHtml = button.innerHTML;
                button.innerHTML = '<span class="loading-spinner"></span>';

                try {
                    const response = await fetch('/swad/controllers/cart_ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `game_id=${gameId}&method=${method}`
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Обновляем отображение количества
                        const cartItem = result.cart[gameId];
                        if (cartItem) {
                            quantityDisplay.textContent = cartItem.quantity;
                            this.updateItemTotal(gameId, cartItem.quantity);
                        } else {
                            // Товар удален (количество стало 0)
                            document.getElementById(`cart-item-${gameId}`).remove();
                        }

                        // Обновляем общую сумму
                        this.updateCartSummary(result);

                        // Обновляем счетчик в хедере
                        if (window.cartManager) {
                            window.cartManager.updateCartCount(result.count);
                        }
                    } else {
                        this.showNotification('Ошибка при обновлении корзины', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    this.showNotification('Ошибка соединения', 'error');
                } finally {
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
            }

            async removeFromCart(button) {
                const gameId = button.dataset.gameId;
                const cartItem = document.getElementById(`cart-item-${gameId}`);

                button.disabled = true;
                const originalHtml = button.innerHTML;
                button.innerHTML = '<span class="loading-spinner"></span>Удаление...';

                try {
                    const response = await fetch('/swad/controllers/cart_ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `game_id=${gameId}&method=REMOVE`
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Плавное удаление элемента
                        cartItem.style.opacity = '0';
                        cartItem.style.transform = 'translateX(-100px)';
                        setTimeout(() => {
                            cartItem.remove();
                            this.updateCartSummary(result);

                            // Если корзина пуста, показываем сообщение
                            if (result.count === 0) {
                                location.reload();
                            }
                        }, 300);

                        // Обновляем счетчик в хедере
                        if (window.cartManager) {
                            window.cartManager.updateCartCount(result.count);
                        }

                        this.showNotification('Товар удален из корзины', 'info');
                    } else {
                        this.showNotification('Ошибка при удалении из корзины', 'error');
                        button.innerHTML = originalHtml;
                        button.disabled = false;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    this.showNotification('Ошибка соединения', 'error');
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
            }

            updateItemTotal(gameId, quantity) {
                // Здесь можно добавить логику для пересчета стоимости позиции
                // если нужно отображать обновленную стоимость в реальном времени
                console.log(`Item ${gameId} quantity updated to ${quantity}`);
            }

            updateCartSummary(result) {
                // Здесь можно добавить логику для обновления итоговой суммы
                // на основе данных из result
                console.log('Cart updated:', result);

                // Для полного обновления данных лучше перезагрузить страницу
                // или сделать дополнительный AJAX запрос для получения актуальных цен
                setTimeout(() => {
                    location.reload();
                }, 500);
            }

            checkout() {
                // Переход к оформлению заказа
                window.location.href = '/checkout';
            }

            showNotification(message, type = 'info') {
                // Простая реализация уведомлений
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'success' ? '#00b894' : type === 'error' ? '#d63031' : '#6c5ce7'};
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                `;
                notification.textContent = message;
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
        }

        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', () => {
            new CartManager();

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