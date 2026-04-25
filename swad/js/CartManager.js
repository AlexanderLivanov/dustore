// Добавляем этот код в секцию <script> внизу страницы

// Класс для управления отображением корзины на странице игры
class GameCartManager {
    constructor(gameId) {
        this.gameId = gameId;
        this.cartControls = document.getElementById(`cart-controls-${gameId}`);
        this.init();
    }

    async init() {
        // await this.updateCartDisplay();
        this.bindEvents();
    }

    async updateCartDisplay() {
        try {
            // Получаем текущее состояние корзины
            const cart = this.getCartFromCookie();
            const quantity = cart[this.gameId] ? cart[this.gameId].quantity : 0;

            this.renderCartControls(quantity);
        } catch (error) {
            console.error('Error updating cart display:', error);
        }
    }

    getCartFromCookie() {
        const cartCookie = document.cookie.split('; ')
            .find(row => row.startsWith('USERCART='));
        
        if (cartCookie) {
            try {
                return JSON.parse(decodeURIComponent(cartCookie.split('=')[1]));
            } catch (e) {
                return {};
            }
        }
        return {};
    }

    renderCartControls(quantity) {
        if (quantity > 0) {
            this.cartControls.innerHTML = `
                <div class="quantity-controls">
                    <span style="display: block; margin-bottom: 8px; font-size: 0.9rem; opacity: 0.8;">
                        В корзине: ${quantity} шт.
                    </span>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <button class="decrease-quantity-btn" 
                                data-game-id="${this.gameId}"
                                style="flex: 1; padding: 8px; background: rgba(214,48,49,0.2); color: #d63031; border: none; border-radius: 5px; cursor: pointer;">
                            − Убрать
                        </button>
                        
                    </div>
                </div>
            `;
        } else {
            this.cartControls.innerHTML = `
                <button class="add-to-cart-btn" 
                        data-game-id="${this.gameId}"
                        style="width: 100%; margin-bottom: 15px;">
                    Добавить в корзину
                </button>
            `;
        }
    }

    bindEvents() {
        this.cartControls.addEventListener('click', async (e) => {
            if (e.target.classList.contains('add-to-cart-btn') || 
                e.target.closest('.add-to-cart-btn')) {
                const button = e.target.classList.contains('add-to-cart-btn') 
                    ? e.target 
                    : e.target.closest('.add-to-cart-btn');
                await this.addToCart(button);
            }

            if (e.target.classList.contains('decrease-quantity-btn') || 
                e.target.closest('.decrease-quantity-btn')) {
                const button = e.target.classList.contains('decrease-quantity-btn') 
                    ? e.target 
                    : e.target.closest('.decrease-quantity-btn');
                await this.decreaseQuantity(button);
            }

            if (e.target.classList.contains('remove-from-cart-btn') || 
                e.target.closest('.remove-from-cart-btn')) {
                const button = e.target.classList.contains('remove-from-cart-btn') 
                    ? e.target 
                    : e.target.closest('.remove-from-cart-btn');
                await this.removeFromCart(button);
            }
        });
    }

    async addToCart(button) {
        button.disabled = true;
        button.innerHTML = '<span class="loading-spinner"></span>Добавляем...';

        try {
            const response = await fetch('/swad/controllers/cart_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `game_id=${this.gameId}&method=ADD`
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Игра добавлена в корзину!', 'success');
                await this.updateCartDisplay();
                // Обновляем счетчик корзины в хедере
                if (window.cartManager) {
                    window.cartManager.updateCartCount(result.count);
                }
            } else {
                this.showNotification('Ошибка при добавлении в корзину', 'error');
                button.innerHTML = 'Добавить в корзину';
                button.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Ошибка соединения', 'error');
            button.innerHTML = 'Добавить в корзину';
            button.disabled = false;
        }
    }

    async decreaseQuantity(button) {
        button.disabled = true;
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="loading-spinner"></span>';

        try {
            const response = await fetch('/swad/controllers/cart_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `game_id=${this.gameId}&method=DECREASE`
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Копия удалена', 'info');
                await this.updateCartDisplay();
                // Обновляем счетчик корзины в хедере
                if (window.cartManager) {
                    window.cartManager.updateCartCount(result.count);
                }
            } else {
                this.showNotification('Ошибка при изменении количества', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Ошибка соединения', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }

    async removeFromCart(button) {
        button.disabled = true;
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="loading-spinner"></span>';

        try {
            const response = await fetch('/swad/controllers/cart_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `game_id=${this.gameId}&method=REMOVE`
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Игра удалена из корзины', 'info');
                await this.updateCartDisplay();
                // Обновляем счетчик корзины в хедере
                if (window.cartManager) {
                    window.cartManager.updateCartCount(result.count);
                }
            } else {
                this.showNotification('Ошибка при удалении из корзины', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Ошибка соединения', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }

    showNotification(message, type = 'info') {
        // Используем существующую систему уведомлений или создаем простую
        if (window.cartManager && window.cartManager.showNotification) {
            window.cartManager.showNotification(message, type);
        } else {
            // Простая реализация уведомления
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
}