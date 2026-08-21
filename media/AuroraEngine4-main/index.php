<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stayinspired</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullPage.js/4.0.24/fullpage.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="app-header">
    <div class="logo">
      <!-- <img src="img/logo_sym.png" alt=""> -->
       Stayinspired
    </div>
    <input class="search" placeholder="Поиск по людям и постам" />
    <input class="search-btn" type="submit" value="Поиск">
  </header>

  <div id="fullpage">
    <!-- Post 1 -->
    <section class="section">
      <article class="post-card">
        <div class="post-head">
          <img class="avatar" src="https://picsum.photos/seed/u1/80" alt="avatar">
          <div class="author-name">Иван Иванов</div>
          <button class="follow-btn">Подписаться</button>
        </div>
        <div class="post-body">
          <div class="post-text" data-full="Это полный текст первого поста. Здесь может быть длинный-длинный абзац...">
            Это короткий анонс первого поста. Нажмите «Читать полностью», чтобы увидеть весь текст.
          </div>
          <button class="read-more" data-read-more>Читать полностью</button>
        </div>
        <div class="post-foot">
          <div class="actions">
            <button class="icon-btn">👍</button>
            <button class="icon-btn">👎</button>
            <button class="icon-btn">💬</button>
            <button class="icon-btn">↗</button>
          </div>
          <div class="meta">12.08.2025 • 320 просмотров</div>
        </div>
      </article>
    </section>

    <!-- Post 2 with image left -->
    <section class="section">
      <article class="post-card">
        <div class="post-head">
          <img class="avatar" src="https://picsum.photos/seed/u2/80" alt="avatar">
          <div class="author-name">Анна Петрова</div>
          <button class="follow-btn">Подписаться</button>
        </div>
        <div class="post-body with-image">
          <img class="post-image" src="https://picsum.photos/seed/p1/600/400" alt="photo">
          <div>
            <div class="post-text">
              Фотоотчёт о путешествии. Красивые виды, полезные советы и маршруты...
            </div>
            <button class="read-more" data-read-more>Читать полностью</button>
          </div>
        </div>
        <div class="post-foot">
          <div class="actions">
            <button class="icon-btn">👍</button>
            <button class="icon-btn">👎</button>
            <button class="icon-btn">💬</button>
            <button class="icon-btn">↗</button>
          </div>
          <div class="meta">13.08.2025 • 1.2K просмотров</div>
        </div>
      </article>
    </section>

    <!-- Post 3 -->
    <section class="section">
      <article class="post-card">
        <div class="post-head">
          <img class="avatar" src="https://picsum.photos/seed/u3/80" alt="avatar">
          <div class="author-name">Sergey Dev</div>
          <button class="follow-btn">Подписаться</button>
        </div>
        <div class="post-body">
          <div class="post-text" data-full="Полный текст про технологии, фреймворки и оптимизацию фронтенда...">
            Короткая заметка про новые фичи в вашем проекте. Легко расширить API, добавить бесконечную ленту и т. д.
          </div>
          <button class="read-more" data-read-more>Читать полностью</button>
        </div>
        <div class="post-foot">
          <div class="actions">
            <button class="icon-btn">👍</button>
            <button class="icon-btn">👎</button>
            <button class="icon-btn">💬</button>
            <button class="icon-btn">↗</button>
          </div>
          <div class="meta">14.08.2025 • 540 просмотров</div>
        </div>
      </article>
    </section>
  </div>

  <nav class="app-footer">
    <button class="active" data-nav="index.html">🏠 Лента</button>
    <button data-nav="subscriptions.html">⭐ Подписки</button>
    <button data-nav="news.html">📰 Новости</button>
    <button data-nav="profile.html">👤 Профиль</button>
  </nav>

  <script src="js/fullpage.min.js"></script>
  <script src="js/script.js"></script>
</body>
</html>
