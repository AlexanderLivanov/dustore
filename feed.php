<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лента разработчиков - Dustore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- WYSIWYG Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .blog-page * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .blog-page {
            background: #0f0316;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            padding-top: 80px;
            min-height: 100vh;
        }

        .blog-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Форма создания поста */
        .create-post-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(var(--brand-rgb, 195, 33, 120), 0.3);
        }

        .create-post-form h2 {
            color: white;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: bold;
            font-size: 1rem;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 1px solid #333;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1.1rem;
            font-family: inherit;
        }

        .submit-btn {
            background: rgb(var(--brand-rgb, 195, 33, 120));
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s ease;
            font-family: inherit;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: rgb(var(--brand-hi-rgb, 230, 46, 138));
        }

        /* Лента постов */
        .feed {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .post-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(var(--brand-rgb, 195, 33, 120), 0.3);
        }

        .post-media {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            display: block;
        }

        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }

        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .post-content {
            padding: 20px;
        }

        .post-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, rgb(var(--brand-rgb, 195, 33, 120)), rgb(var(--brand-deep-rgb, 116, 21, 93)));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .author-info {
            flex: 1;
        }

        .author-name {
            font-weight: bold;
            font-size: 1.1rem;
            color: white;
        }

        .post-date {
            color: #aaa;
            font-size: 0.9rem;
        }

        .post-title {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: white;
            font-weight: bold;
        }

        .post-text {
            line-height: 1.6;
            margin-bottom: 15px;
            color: #ddd;
            font-size: 1rem;
        }

        .post-text h1,
        .post-text h2,
        .post-text h3 {
            color: white;
            margin: 20px 0 10px 0;
        }

        .post-text p {
            margin-bottom: 10px;
        }

        .post-text ul,
        .post-text ol {
            margin: 10px 0;
            padding-left: 20px;
        }

        .post-text li {
            margin-bottom: 5px;
        }

        .post-text blockquote {
            border-left: 4px solid rgb(var(--brand-rgb, 195, 33, 120));
            padding-left: 15px;
            margin: 15px 0;
            color: #aaa;
            font-style: italic;
        }

        .post-text code {
            background: rgba(255, 255, 255, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }

        .post-text pre {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 15px 0;
        }

        .post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .tag {
            background: rgba(var(--brand-rgb, 195, 33, 120), 0.2);
            color: rgb(var(--brand-rgb, 195, 33, 120));
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            border: none;
        }

        .post-actions {
            display: flex;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .action-btn {
            background: none;
            border: none;
            color: #aaa;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: color 0.3s ease;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .action-btn:hover {
            color: rgb(var(--brand-rgb, 195, 33, 120));
        }

        .empty-feed {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }

        .empty-feed i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: rgb(var(--brand-rgb, 195, 33, 120));
        }

        .empty-feed h3 {
            color: #aaa;
            margin-bottom: 10px;
        }

        /* WYSIWYG Editor Styles */
        .ql-toolbar.ql-snow {
            border: 1px solid #333;
            border-radius: 8px 8px 0 0;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: none;
        }

        .ql-container.ql-snow {
            border: 1px solid #333;
            border-radius: 0 0 8px 8px;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid #333;
            font-size: 1rem;
            min-height: 200px;
        }

        .ql-editor {
            color: white;
            min-height: 200px;
            font-family: 'Segoe UI', sans-serif;
        }

        .ql-editor.ql-blank::before {
            color: #aaa;
            font-style: italic;
        }

        .ql-snow .ql-stroke {
            stroke: #aaa;
        }

        .ql-snow .ql-fill {
            fill: #aaa;
        }

        .ql-snow .ql-picker-label {
            color: #aaa;
        }

        .ql-snow .ql-picker-options {
            background: #1a0a24;
            border: 1px solid #333;
        }

        .ql-snow .ql-picker-item {
            color: white;
        }

        .ql-snow .ql-picker-item:hover {
            color: rgb(var(--brand-rgb, 195, 33, 120));
        }

        .ql-snow .ql-tooltip {
            background: #1a0a24;
            border: 1px solid #333;
            color: white;
        }

        .ql-snow .ql-tooltip input[type=text] {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid #333;
            color: white;
        }

        /* Анимации */
        .post-card {
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="blog-page">
    <?php require_once('swad/static/elements/header.php'); ?>

    <div class="blog-container">
        <?php if (!empty($_SESSION['USERDATA']['telegram_id'])): ?>
            <!-- Форма создания поста -->
            <div class="create-post-form">
                <h2><i class="fas fa-edit"></i> Создать новый пост</h2>
                <form id="postForm">
                    <div class="form-group">
                        <label for="postTitle">Тема поста</label>
                        <input type="text" id="postTitle" placeholder="О чём хотите рассказать?" required>
                    </div>

                    <div class="form-group">
                        <label>Содержание</label>
                        <div id="editor" style="height: 300px;"></div>
                        <textarea id="postText" style="display: none;" required></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Опубликовать
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin-bottom: 30px;">
                <button class="submit-btn" onclick="window.location.href='/login'">
                    <i class="fas fa-sign-in-alt"></i> Войдите, чтобы публиковать
                </button>
            </div>
        <?php endif; ?>

        <!-- Лента постов -->
        <div class="feed" id="feed">
            <!-- Посты загружаются через JS -->
        </div>
    </div>

    <!-- WYSIWYG Editor -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // Инициализация WYSIWYG редактора
        const quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{
                        'header': [1, 2, 3, false]
                    }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{
                        'list': 'ordered'
                    }, {
                        'list': 'bullet'
                    }],
                    [{
                        'script': 'sub'
                    }, {
                        'script': 'super'
                    }],
                    [{
                        'indent': '-1'
                    }, {
                        'indent': '+1'
                    }],
                    [{
                        'direction': 'rtl'
                    }],
                    [{
                        'color': []
                    }, {
                        'background': []
                    }],
                    [{
                        'align': []
                    }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            },
            placeholder: 'Расскажите о своей игре, поделитесь новостями, покажите скриншоты...'
        });

        // Данные постов
        let posts = [{
                id: 1,
                author: "CyberGames",
                avatar: "CG",
                title: "Вышло обновление 2.0 для CyberRun!",
                text: `<h2>Что нового в обновлении 2.0?</h2>
                      <p>Мы добавили <strong>3 новых уровня</strong> с уникальными механиками:</p>
                      <ul>
                        <li>Неоновый город с паркуром</li>
                        <li>Подземные тоннели с головоломками</li>
                        <li>Босс-битва на крыше небоскреба</li>
                      </ul>
                      <blockquote>Это наше самое масштабное обновление за всю историю игры!</blockquote>
                      <p>Также улучшили графику и исправили <code>баги</code> с физикой.</p>`,
                date: "2024-01-15 14:30",
                tags: ["обновление", "киберпанк", "патч"],
                likes: 24,
                comments: 8
            },
            {
                id: 2,
                author: "IndieDev",
                avatar: "ID",
                title: "Скриншоты новой локации",
                text: `<h3>Работаем над лесной локацией</h3>
                      <p>Вот как выглядит <em>новый лесной биом</em> в нашей RPG:</p>
                      <p>Особенности локации:</p>
                      <ol>
                        <li>Динамическая погода</li>
                        <li>Случайные события</li>
                        <li>Секретные пещеры</li>
                      </ol>
                      <p>Что думаете о визуальном стиле?</p>`,
                date: "2024-01-14 11:20",
                tags: ["скриншоты", "rpg", "разработка"],
                likes: 42,
                comments: 15
            }
        ];

        function loadFeed() {
            const feed = document.getElementById('feed');

            if (posts.length === 0) {
                feed.innerHTML = `
                    <div class="empty-feed">
                        <i class="fas fa-gamepad"></i>
                        <h3>Пока нет постов</h3>
                        <p>Будьте первым, кто поделится новостями!</p>
                    </div>
                `;
                return;
            }

            feed.innerHTML = '';

            posts.forEach(post => {
                const postHTML = createPostHTML(post);
                feed.innerHTML += postHTML;
            });
        }

        function createPostHTML(post) {
            const tagsHTML = post.tags.map(tag => `<span class="tag">#${tag}</span>`).join('');

            return `
                <div class="post-card">
                    <div class="post-content">
                        <div class="post-header">
                            <div class="author-avatar">${post.avatar}</div>
                            <div class="author-info">
                                <div class="author-name">${post.author}</div>
                                <div class="post-date">${formatDate(post.date)}</div>
                            </div>
                        </div>
                        
                        <h3 class="post-title">${post.title}</h3>
                        <div class="post-text">${post.text}</div>
                        
                        <div class="post-tags">
                            ${tagsHTML}
                        </div>
                        
                        <div class="post-actions">
                            <button class="action-btn" onclick="likePost(${post.id})">
                                <i class="fas fa-heart"></i> ${post.likes}
                            </button>
                            <button class="action-btn">
                                <i class="fas fa-comment"></i> ${post.comments}
                            </button>
                            <button class="action-btn">
                                <i class="fas fa-share"></i> Поделиться
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('ru-RU') + ' в ' + date.toLocaleTimeString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function likePost(postId) {
            const post = posts.find(p => p.id === postId);
            if (post) {
                post.likes++;
                loadFeed();
            }
        }

        document.getElementById('postForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const title = document.getElementById('postTitle').value;
            const text = quill.root.innerHTML;

            // Очищаем форму
            document.getElementById('postTitle').value = '';
            quill.setContents([]);

            const newPost = {
                id: posts.length + 1,
                author: "Вы",
                avatar: "Я",
                title: title,
                text: text,
                date: new Date().toISOString(),
                tags: ["новый", "пост"],
                likes: 0,
                comments: 0
            };

            posts.unshift(newPost);
            loadFeed();

            // Прокрутка к новому посту
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        document.addEventListener('DOMContentLoaded', loadFeed);
    </script>
</body>

</html>