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
                        rgba(0,0,0,1) 60%,
                        rgba(0,0,0,0) 100%
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

                    <div class="profile-page">

                        <!-- ВЕРХНИЙ БЛОК: ЮЗЕР -->
                        <div class="card user-card">
                            <div class="card-header">
                                <div>
                                    <div class="label">Имя пользователя:</div>
                                    <h2 class="username">Eshward_Williams <span class="copy" style="font-size: .9rem; color: #ffffff3b;">⧉</span></h2>
                                </div>

                                <div class="since"><br><br>На платформе с: 23.05.2025</div>
                            </div>

                            <div class="card-body">
                                <div class="data-for">
                                    Данные для L4T
                                </div>
                                <div class="card-body-main">
                                    <div class="left">
                                        <div class="row">
                                            <span class="label">Роль:</span><br>
                                            Руководитель Dust Game Studio, CEO Dustore
                                        </div>

                                        <div class="row">
                                            <span class="label">Опыт:</span>

                                            <div class="tags">
                                                <div class="tag">Программист RenPy 3г.</div>
                                                <div class="tag">Дизайн UI 1г.</div>
                                                <div class="tag">Диктор 5л.</div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <span class="label">Доп. данные:</span>

                                            <div class="files">
                                                <div class="file">
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        width="25"
                                                        height="25"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="#ffffff3b"
                                                        stroke-width="3"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path d="M5 4h4l3 3h7a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-11a2 2 0 0 1 2 -2" />
                                                    </svg>
                                                </div>
                                                <div class="file">
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        width="25"
                                                        height="25"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="#ffffff3b"
                                                        stroke-width="3"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path d="M5 4h4l3 3h7a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-11a2 2 0 0 1 2 -2" />
                                                    </svg>
                                                </div>
                                                <div class="file">
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        width="25"
                                                        height="25"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="#ffffff3b"
                                                        stroke-width="3"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path d="M9 15l6 -6" />
                                                        <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" />
                                                        <path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" />
                                                    </svg>

                                                </div>
                                                <div class="file add" style="font-weight: bold;">+</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="right">
                                        <div class="projects-right">
                                            <div class="label" style="vertical-align:top">Проекты:</div>

                                            <div class="projects">
                                                <div class="proj"></div>
                                                <div class="proj"></div>
                                                <div class="proj add">+</div>
                                                <div class="proj add">+</div>
                                            </div>
                                        </div>

                                        <div class="projects-right">
                                            <div class="label">О себе:   </div>
                                            <textarea class="about"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- БЛОК СТУДИИ -->
                        <div class="card user-card">
                            <div class="card-header">
                                <div>
                                    <div class="label">Студия:</div>
                                    <h2 class="username">Dust Game Studio<span class="copy" style="font-size: .9rem; color: #ffffff3b;"> ⧉</span></h2>
                                </div>

                                <div class="since"><br><br>Студия на платформе с: 15.02.2025</div>
                            </div>

                            <div class="card-body">
                                <div class="data-for">
                                    Данные для L4T
                                </div>
                                <div class="card-body-main">
                                    <div class="left">
                                        <div class="row">
                                            <span class="label">Участники:</span>
                                            <div class="projects-right">
                                                <div class="users-total">
                                                    7
                                                </div>

                                                <div class="users">
                                                    <div class="user">
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            width="32"
                                                            height="32"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="#ffffff3b"
                                                            stroke-width="1"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                                        </svg>
                                                    </div>
                                                    <div class="user">
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            width="32"
                                                            height="32"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="#ffffff3b"
                                                            stroke-width="1"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                                        </svg>


                                                    </div>
                                                    <div class="user">
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            width="32"
                                                            height="32"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="#ffffff3b"
                                                            stroke-width="1"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                                        </svg>

                                                    </div>

                                                    <div class="user">
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            width="32"
                                                            height="32"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="#ffffff3b"
                                                            stroke-width="1"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                                        </svg>
                                                    </div>
                                                    <div class="user">
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            width="32"
                                                            height="32"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="#ffffff3b"
                                                            stroke-width="1"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                                        </svg>
                                                    </div>
                                                    <div class="user more">Ещё</div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="right">
                                        <div class="info-block">
                                            Скоро
                                        </div>
                                    </div>
                                </div>
                            </div>
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
        document.addEventListener("DOMContentLoaded", () => {

            // ===== ОСНОВНЫЕ СТРАНИЦЫ =====
            const views = {
                market: document.getElementById("view-market"),
                create: document.getElementById("view-create"),
                profile: document.querySelector(".profile-page")
            };

            const buttons = {
                market: document.querySelector(".left-side-button"),
                create: document.querySelector(".left-side-button1"),
                profile: document.getElementById("btn-profile")
            };

            function showView(name) {
                Object.values(views).forEach(v => v.style.display = "none");
                Object.values(buttons).forEach(b => b && b.classList.remove("active"));

                views[name].style.display = "block";

                if (buttons[name]) {
                    buttons[name].classList.add("active");
                }

                localStorage.setItem("activeView", name);
            }

            buttons.market.onclick = () => showView("market");
            buttons.create.onclick = () => showView("create");
            buttons.profile.onclick = () => showView("profile");

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
            const savedView = localStorage.getItem("activeView") || "market";
            showView(savedView);

            const savedSub = localStorage.getItem("createSubTab");
            if (savedSub) {
                const btn = document.querySelector(
                    `#view-create .filter-item[data-filter="${savedSub}"]`
                );
                if (btn) btn.click();
            }

            
        });
    </script>
</body>

</html>