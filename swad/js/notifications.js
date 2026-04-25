// Функция показа уведомления
function showNotification(type, message) {
  const container = document.getElementById("notifications-container");

  // Создаем элемент уведомления
  const notification = document.createElement("div");
  notification.className = `notification ${type}`;

  // Добавляем содержимое
  notification.innerHTML = `
<div class="notification-content">${message}</div>
<button class="close-btn">&times;</button>
`;

  // Добавляем в контейнер
  container.prepend(notification);

  // Закрытие по кнопке
  notification.querySelector(".close-btn").addEventListener("click", () => {
    notification.style.animation = "fadeOut 0.3s forwards";
    setTimeout(() => notification.remove(), 300);
  });

  // Автоматическое закрытие через 5 секунд
  setTimeout(() => {
    if (notification.parentNode) {
      notification.style.animation = "fadeOut 0.3s forwards";
      setTimeout(() => notification.remove(), 300);
    }
  }, 10000);
}
