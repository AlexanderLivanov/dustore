<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Dustore L4T</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Yandex.RTB -->
    <script>
        window.yaContextCb = window.yaContextCb || []
    </script>
    <script src="https://yandex.ru/ads/system/context.js" async></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Inter, system-ui;
            background: #efe9dc;
            color: #2a2a2a;
        }

        header {
            height: 64px;
            display: flex;
            align-items: center;
            padding: 0 32px;
            background: #d8cfbd;
            border-bottom: 2px solid #b8ad99;
            font-weight: 700;
        }

        main {
            display: flex;
            height: calc(100vh - 64px);
        }

        aside {
            width: 240px;
            background: #e2d8c4;
            border-right: 2px solid #b8ad99;
            padding: 20px;
        }

        aside a {
            display: block;
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 8px;
            color: #2a2a2a;
            text-decoration: none;
        }

        aside a.active,
        aside a:hover {
            background: #cbbfa9;
        }

        section {
            flex: 1;
            padding: 32px;
            overflow: auto;
        }

        .card {
            background: #fffaf0;
            border: 1px solid #c8bfae;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            transition: transform .2s;
        }

        .letter {
            font-family: "Courier New", monospace;
            background: #fffdf8;
            border: 2px dashed #b8ad99;
            padding: 16px;
            cursor: pointer;
            transition: transform .2s;
        }

        .letter:hover {
            transform: scale(1.03);
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #b8ad99;
            background: #fff;
            margin-bottom: 12px;
        }

        button {
            background: #8b5e3c;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 8px;
        }

        .profile-header {
            display: flex;
            gap: 24px;
            margin-bottom: 16px;
        }

        .avatar {
            width: 96px;
            height: 96px;
            border-radius: 12px;
            background: #ccc;
        }

        .hidden {
            display: none;
        }

        .meta {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }

        #previewModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .7);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        #previewModal .modal-card {
            background: #fffaf0;
            border: 2px dashed #b8ad99;
            padding: 32px;
            border-radius: 12px;
            width: 80%;
            max-width: 800px;
            position: relative;
        }

        #previewModal h2 {
            margin-bottom: 12px;
            font-family: "Courier New", monospace;
        }

        #previewModal .meta {
            margin-bottom: 16px;
        }

        #previewModal .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        #previewModal button {
            flex: 1;
        }

        #previewModal .close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #8b5e3c;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
        }

        .preview-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
        }

        .preview-letter {
            background: #fffaf0;
            border: 2px dashed #b8ad99;
            padding: 32px;
            width: 520px;
            font-family: "Courier New", monospace;
            position: relative;
        }

        .preview-letter .close {
            position: absolute;
            top: 10px;
            right: 14px;
            border: none;
            background: none;
            font-size: 22px;
            cursor: pointer;
        }

        .ad-block {
            margin-top: 24px;
            padding: 12px;
            background: #fffaf0;
            border: 2px dashed #b8ad99;
            border-radius: 12px;
            font-family: "Courier New", monospace;
        }

        .ad-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #6b4a2d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .ad-block::before {
            content: "POST";
            display: block;
            font-size: 11px;
            color: #999;
            margin-bottom: 6px;
        }
    </style>
</head>

<body>

    <header>looking4team</header>

    <main>
        <aside>
            <a href="#" class="active" onclick="show('feed',this)">Лента</a>
            <a href="#" onclick="show('my',this)">Мои заявки</a>
            <a href="#" onclick="show('create',this)">Создать заявку</a>
            <a href="#" onclick="show('fav',this)">Избранное</a>
            <a href="#" onclick="show('profile',this)">Профиль</a>
            <div class="ad-block">
                <div class="ad-title">📢 Объявление</div>

                <!-- Yandex.RTB R-A-18474572-1 -->
                <div id="yandex_rtb_R-A-18474572-1"></div>
            </div>

        </aside>

        <section>
            <div id="feed">
                <h2>Лента новых заявок</h2>
                <div id="feedList"></div>
            </div>

            <div id="my" class="hidden">
                <h2>Мои заявки</h2>
                <div id="myStats" class="card"></div>
                <div id="myList"></div>
            </div>

            <div id="create" class="hidden">
                <h2>Новая заявка</h2>
                <input id="newTitle" placeholder="Кого вы ищете">
                <input id="newRole" placeholder="Формат участия">
                <select id="newStage">
                    <option>Стадия проекта</option>
                    <option>Идея</option>
                    <option>Прототип</option>
                    <option>Продакшн</option>
                </select>
                <textarea id="newDesc" placeholder="Описание проекта"></textarea>
                <textarea id="newDone" placeholder="Что уже сделано"></textarea>
                <textarea id="newWhy" placeholder="Зачем вам этот человек"></textarea>
                <button onclick="createRequest()">Опубликовать</button>
            </div>

            <div id="fav" class="hidden">
                <h2>Избранное</h2>
                <div id="favList"></div>
            </div>

            <div id="profile" class="hidden">
                <h2>Кто вы?</h2>
                <h4>Это ваш публичный профиль. Заполните небольшую анкету (по желанию), чтобы сообщество с вами познакомилось!</h4>
                <br>
                <?php if (!empty($_SESSION['USERDATA'])): ?>
                    <div class="profile-header">
                        <div class="avatar"></div>
                        <div>
                            <span>Имя пользователя</span>
                            <input id="profileNick" placeholder="@a.livanov" value="@<?= $_SESSION['USERDATA']['username'] ?>" disabled>
                            <span>Ваша роль</span>
                            <input id="profileRole" placeholder="Программист">
                            <span>Ваш город</span>
                            <input id="profileCity" placeholder="Москва">
                        </div>
                    </div>
                    <span>Расскажите о себе</span>
                    <textarea id="profileBio" placeholder="Делаю безумные проекты"></textarea>

                    <span>Ваш статус работы</span>
                    <select name="jobStatuses" id="jobStatus">
                        <option value=""></option>
                        <option value="selfemployed">Работаю на себя</option>
                        <option value="unemployed_and_looking_for">Не работаю и активно ищу работу</option>
                        <option value="unemployed_and_not_looking_for">Не работаю и не ищу работу</option>
                        <option value="employed_and_looking_for">Работаю и активно ищу работу</option>
                        <option value="employed_and_not_looking_for">Работаю и не ищу работу</option>
                        <option value="just_looking_for">Могу поучаствовать в проекте</option>
                        <option value="just_here">Я здесь по приколу зарегался</option>
                    </select>
                    <span>В каких проектах вы участвовали? Чем там занимались?</span>
                    <textarea id="profileProjects" placeholder="- 'Dustore.Ru - Российская игровая платформа', ведущий программист&#10;- 'l4t.ru', ведущий программист"></textarea>

                    <span>Любые формы связи с вами</span>
                    <textarea id="profileContacts" placeholder="Москва, ул. Арбат, д. 1, кв. 1, можно отправить почтового голубя, а ещё в тг: t.me/crazya11my1if3"></textarea>
                    <button onclick="saveProfile()">Сохранить профиль</button>
                <?php else: ?>
                    <button onclick="window.location.href='/login?backUrl=/l4t'"><img src="/swad/static/img/logo_new_neon.png" alt="" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 10px; border-radius: 15px;">Войдите или зарегистрируйтесь через Dustore</button>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="previewModal">
        <div class="modal-card">
            <button class="close-btn" onclick="closePreview()">✖</button>
            <h2 id="modalTitle"></h2>
            <div class="meta" id="modalAuthor"></div>
            <div id="modalDesc" style="margin-top:12px;"></div>
            <div class="modal-actions">
                <button onclick="respondRequest()">Откликнуться</button>
                <button onclick="openPaper(modalCurrentId)">Печатная версия</button>
            </div>
        </div>
    </div>

    <div id="respondModal" class="preview-modal" style="display:none">
        <div class="preview-letter">
            <button class="close" onclick="closeRespond()">×</button>

            <h2>✉️ Отклик на заявку</h2>

            <p class="meta">
                Заявка #<span id="respondRequestId"></span>
            </p>

            <textarea id="respondText" placeholder="Напишите ваше письмо..." rows="8"></textarea>

            <button onclick="sendRespond()">📮 Отправить письмо</button>
        </div>
    </div>


    <script>
        let requests = [];
        let favs = [];
        let modalCurrentId = null;

        const views = ['feed', 'my', 'create', 'fav', 'profile'];

        function show(id, el) {
            localStorage.setItem('activeView', id);
            views.forEach(v =>
                document.getElementById(v).classList.add('hidden')
            );
            document.getElementById(id).classList.remove('hidden');
            document.querySelectorAll('aside a').forEach(a =>
                a.classList.remove('active')
            );
            if (el) el.classList.add('active');
        }

        fetch('./core/getall.php')
            .then(res => {
                if (!res.ok) throw new Error('API error');
                return res.json();
            })
            .then(data => {
                requests = data;
                renderFeed();
                renderMy();
                renderFav();
            })
            .catch(err => {
                console.error('Fetch error:', err);
            });

        function renderFeed() {
            const el = document.getElementById('feedList');
            if (!el) return;
            el.innerHTML = '';
            requests.forEach(r => {
                const div = document.createElement('div');
                div.className = 'card letter';
                div.innerHTML = `<h3>${r.title}</h3><div class="meta">От ${r.author} · ${r.type}</div>`;
                div.onclick = () => openPreview(r.id);
                el.appendChild(div);
            });
        }

        function renderMy() {
            const stats = document.getElementById('myStats');
            if (stats) {
                stats.innerHTML = `👁️ ${requests.length * 50} · 💬 ${requests.length * 3} · ⭐ ${requests.length}`;
            }
            const list = document.getElementById('myList');
            if (!list) return;
            list.innerHTML = '';
            requests.forEach(r => {
                const div = document.createElement('div');
                div.className = 'card letter';
                div.innerHTML = `<h3>${r.title}</h3><div class="meta">Статус: активна</div>`;
                div.onclick = () => openPreview(r.id);
                list.appendChild(div);
            });
        }

        function renderFav() {
            const el = document.getElementById('favList');
            if (!el) return;
            el.innerHTML = '';
            favs.forEach(r => {
                const div = document.createElement('div');
                div.className = 'card letter';
                div.innerHTML = `<h3>${r.title}</h3><div class="meta">От ${r.author}</div>`;
                div.onclick = () => openPreview(r.id);
                el.appendChild(div);
            });
        }

        function openPreview(id) {
            modalCurrentId = id;

            const r = requests.find(x => x.id === id);
            if (!r) return;

            document.getElementById('modalTitle').textContent = r.title;
            document.getElementById('modalAuthor').textContent = `От ${r.author} · ${r.type}`;
            document.getElementById('modalDesc').textContent = r.desc || 'Описание отсутствует';

            document.getElementById('previewModal').style.display = 'flex';
        }

        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function respondRequest() {
            if (!modalCurrentId) return;
            closePreview();
            document.getElementById('respondRequestId').textContent = modalCurrentId;
            document.getElementById('respondText').value = '';

            document.getElementById('respondModal').style.display = 'flex';
        }

        function closeRespond() {
            document.getElementById('respondModal').style.display = 'none';
        }

        function sendRespond() {
            const text = document.getElementById('respondText').value.trim();
            if (!text) {
                alert('Письмо не может быть пустым');
                return;
            }

            fetch('/core/respond.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        request_id: modalCurrentId,
                        text: text
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('Письмо отправлено 📮');
                        closeRespond();
                    } else {
                        alert('Ошибка отправки');
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const saved = localStorage.getItem('activeView') || 'feed';
            const link = document.querySelector(
                `aside a[onclick*="'${saved}'"]`
            );

            show(saved, link);
        });
    </script>
    <script>
        window.yaContextCb = window.yaContextCb || [];
    </script>

    <script src="https://yandex.ru/ads/system/context.js" async></script>

    <script>
        window.yaContextCb.push(() => {
            Ya.Context.AdvManager.render({
                blockId: "R-A-18474572-1",
                renderTo: "yandex_rtb_R-A-18474572-1"
            });
        });
    </script>

</body>

</html>