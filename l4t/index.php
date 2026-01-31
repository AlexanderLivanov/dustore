<?php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');

$curr_user = new User();

// bid structure:
// id, title, author_id, path_to_cover, person_seek, needed_exp, salary_condition

$bids_array = [
    [1, "Howl-Growl", 1, "/path_to_cover", "CGI художник", 1, "non-free"],
    [2, "Pigeon of Sorrow", 2, "/path_to_cover", "Unity программист", 1, "non-free"],
    [3, "Solder Simulator", 3, "/path_to_cover", "Физик-ядерщик", 1, "non-free"],
    [4, "Dustore", 4, "/path_to_cover", "Деньги", 1, "non-free"]
];

$user_orgs = $curr_user->getUO($_SESSION['USERDATA']['id']);
// print_r($user_orgs);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore L4T</title>
    <link rel="stylesheet" href="css/main.css">
</head>

<body>
    <div class="main-container">
        <div class="header-container">
            <img class="logo" src="/swad/static/img/logo_new.png" alt="">
        </div>
        <div class="view-container">
            <div class="left-side-menu">
                <div class="avatar-canvas" id="btn-profile">
                    <div class="profile-image-container"
                        style="width: 100%;
                        height: 400px;
                        border-radius: 10px;
                        /* border: 1px solid red; */

                        background-image: url('<?= $_SESSION['USERDATA']['profile_picture'] ?>');
                        background-size: cover;
                        background-position: center;

                        -webkit-mask-image: linear-gradient(
                            to bottom,
                            rgba(0,0,0,0) 0%,
                            rgba(0,0,0,1) 40%
                        );
                        mask-image: linear-gradient(
                        to bottom,
                        rgba(0,0,0,1) 0%,
                        rgba(0,0,0,0) 80%
                    );">
                    </div>
                    <div class="image-subtitle">
                        Профиль L4T
                    </div>
                </div>
                <div class="buttons-container">
                    <div class="left-side-button active">
                        Биржа
                    </div>
                    <hr style="width: 50%; margin-right: 25px; margin-left: 25%; opacity: 20%">
                    <div class="left-side-button1">
                        Создать заявку
                    </div>
                </div>
            </div>
            <div class="right-content-view">
                <div class="content-background">

                    <!-- ПРОФИЛЬ -->

                    <div id="view-profile" class="content-view">

                        <div style="color:white; padding:30px;">
                            <h2>Профиль</h2>
                            <p>Ник: <?= $_SESSION['USERDATA']['username'] ?? 'L4T' ?></p>
                            <p>Email: <?= $_SESSION['USERDATA']['email'] ?? '-' ?></p>
                        </div>

                    </div>

                    <!-- БИРЖА -->
                    <div id="view-market" class="content-view active">

                        <div class="content-filter">
                            <div class="filter-item active" data-filter="projects">Проекты</div>
                            <div class="filter-item" data-filter="people">Люди</div>
                        </div>

                        <!-- проекты -->
                        <div id="market-projects" class="market-view active">
                            <?php foreach ($bids_array as $bid): ?>
                                <div class="bid-container"></div>
                            <?php endforeach; ?>
                        </div>

                        <!-- люди -->
                        <div id="market-people" class="market-view">
                            <div class="bid-container"></div>
                            <div class="bid-container"></div>
                        </div>

                    </div>

                    <!-- СОЗДАТЬ ЗАЯВКУ -->
                    <div id="view-create" class="content-view">

                        <div class="content-filter">
                            <div class="filter-item active" data-filter="new_reqs">Новые заявки</div>
                            <div class="filter-item" data-filter="my_reqs">Созданные заявки</div>
                        </div>

                        <!-- НОВАЯ ЗАЯВКА -->
                        <div id="tab-new" class="req-view active">

                            <!-- переключатель -->
                            <div class="switch-row">
                                <span>Студия (<?= $user_orgs[0]['name'] ?>)</span>

                                <label class="switch">
                                    <input type="checkbox" id="typeToggle">
                                    <span class="slider"></span>
                                </label>


                                <span>Пользователь (<?= $_SESSION['USERDATA']['username'] ?>)</span>
                            </div>

                            <!-- сетка 2 на 2 -->
                            <div class="grid-2x2">

                                <div class="form-row">
                                    <label>Я хочу найти:</label>
                                    <select></select>
                                </div>

                                <div class="form-row">
                                    <label>Уточнение:</label>
                                    <select></select>
                                </div>

                                <div class="form-row">
                                    <label>Опыт:</label>
                                    <select></select>
                                </div>

                                <div class="form-row">
                                    <label>Условия:</label>
                                    <select></select>
                                </div>

                            </div>

                            <div class="form-row full">
                                <label>Цель:</label>
                                <select style="width: 94%;"></select>
                            </div>

                            <div class="desc-row">
                                <label style="text-align: right;">Детальное <br> описание:</label>

                                <div class="desc-wrap">
                                    <textarea></textarea>

                                    <button class="ok-btn" id="ok-btn">
                                        ✓
                                    </button>
                                </div>
                            </div>

                        </div>


                        <!-- МОИ ЗАЯВКИ -->
                        <div id="tab-my" class="req-view">

                            <div class="my-bid">
                                <div class="my-bid-main">
                                    <div>
                                        <strong>Unity программист</strong>
                                        <div class="bid-date">25.01.2026 18:40</div>
                                    </div>
                                    <button class="submit-btn edit-btn"
                                        data-title="Unity программист"
                                        data-goal="Найти разработчика"
                                        data-desc="Нужен Unity dev с опытом 3+ года">
                                        Редактировать
                                    </button>
                                </div>
                            </div>

                            <div class="my-bid">
                                <div class="my-bid-main">
                                    <div>
                                        <strong>CGI художник</strong>
                                        <div class="bid-date">24.01.2026 12:10</div>
                                    </div>
                                    <button class="submit-btn edit-btn"
                                        data-title="Unity программист"
                                        data-goal="Найти разработчика"
                                        data-desc="Нужен Unity dev с опытом 3+ года">
                                        Редактировать
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // ===== ГЛАВНЫЕ ВКЛАДКИ =====
        const views = {
            market: document.getElementById("view-market"),
            create: document.getElementById("view-create"),
            profile: document.getElementById("view-profile"),
        };

        const buttons = {
            market: document.querySelector(".left-side-button"),
            create: document.querySelector(".left-side-button1"),
        };

        function showView(name) {
            Object.values(views).forEach(v => v.classList.remove("active"));
            Object.values(buttons).forEach(b => b && b.classList.remove("active"));

            views[name].classList.add("active");
            if (buttons[name]) buttons[name].classList.add("active");

            localStorage.setItem("activeView", name);
        }

        buttons.market.onclick = () => showView("market");
        buttons.create.onclick = () => showView("create");
        document.getElementById("btn-profile").onclick = () => showView("profile");


        // ===== ПОДВКЛАДКИ В СОЗДАНИИ ЗАЯВКИ =====
        const createTabBtns = document.querySelectorAll("#view-create .filter-item");
        const tabNew = document.getElementById("tab-new");
        const tabMy = document.getElementById("tab-my");

        createTabBtns.forEach(btn => {
            btn.onclick = () => {
                createTabBtns.forEach(b => b.classList.remove("active"));
                btn.classList.add("active");

                const isNew = btn.dataset.filter === "new_reqs";

                tabNew.classList.toggle("active", isNew);
                tabMy.classList.toggle("active", !isNew);

                localStorage.setItem("createSubTab", btn.dataset.filter);
            };
        });


        // ===== ВОССТАНОВЛЕНИЕ ПОСЛЕ F5 =====
        window.addEventListener("DOMContentLoaded", () => {

            // 1. Главная вкладка
            const savedView = localStorage.getItem("activeView");
            if (savedView && views[savedView]) {
                showView(savedView);
            }

            // 2. Подвкладка в "Создать заявку"
            const savedSub = localStorage.getItem("createSubTab");
            if (savedSub) {
                const btn = document.querySelector(
                    `#view-create .filter-item[data-filter="${savedSub}"]`
                );
                if (btn) btn.click();
            }
        });
    </script>
    <script>
        // вкладки "Новые заявки" / "Созданные заявки"
        const createTabBtns = document.querySelectorAll("#view-create .filter-item");
        const tabNew = document.getElementById("tab-new");
        const tabMy = document.getElementById("tab-my");

        createTabBtns.forEach(btn => {
            btn.onclick = () => {

                createTabBtns.forEach(b => b.classList.remove("active"));
                btn.classList.add("active");

                const isNew = btn.dataset.filter === "new_reqs";

                tabNew.classList.toggle("active", isNew);
                tabMy.classList.toggle("active", !isNew);

                localStorage.setItem("createSubTab", btn.dataset.filter);
            };
        });

        // восстановление после обновления страницы
        window.addEventListener("DOMContentLoaded", () => {

            const saved = localStorage.getItem("createSubTab");

            if (saved) {
                const btn = document.querySelector(
                    `#view-create .filter-item[data-filter="${saved}"]`
                );

                if (btn) btn.click();
            }
        });
    </script>
    <script>
        const editButtons = document.querySelectorAll(".edit-btn");
        const createForm = document.getElementById("create-card");
        const submitBtn = document.getElementById("ok-btn");

        const goalSelect = document.querySelector(".form-row.full select");
        const descTextarea = document.querySelector(".desc-row textarea");

        /* переключение в режим редактирования */
        editButtons.forEach(btn => {
            btn.onclick = () => {

                /* открыть вкладку "Новые заявки" */
                document.querySelector('[data-create-tab="new"]').click();

                /* режим edit */
                createForm.dataset.mode = "edit";
                submitBtn.textContent = "Сохранить";

                /* заполнение */
                goalSelect.value = btn.dataset.goal;
                descTextarea.value = btn.dataset.desc;

                /* скролл к форме (по-человечески) */
                createForm.scrollIntoView({
                    behavior: "smooth"
                });
            };
        });

        /* сабмит */
        submitBtn.onclick = (e) => {
            e.preventDefault();

            if (createForm.dataset.mode === "edit") {
                console.log("Сохраняем изменения");
                // тут будет UPDATE в БД
            } else {
                console.log("Создаём новую заявку");
                // тут INSERT
            }
        };
    </script>

</body>

</html>