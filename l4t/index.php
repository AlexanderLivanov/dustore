<?php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');

$db = new Database();
$pdo = $db->connect();

// if (empty($_SESSION['USERDATA'])) {
//     if (empty($_COOKIE['auth_token'])) {
//         echo ("<script>window.location.href='/login?backUrl=" . $_SERVER['REQUEST_URI'] . "'</script>");
//     }
// }

if (empty($_SESSION['USERDATA'])) {
    $userdata = ['user not logged in'];
}

$curr_user = new User();


if (!empty($_GET['username'])) {
    $username = $_GET['username'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? or telegram_username = ?");
    $stmt->execute([$_GET['username'], $_GET['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $userdata = $user;
} elseif (!empty($_SESSION['USERDATA']['id'])) {
    $userdata = $_SESSION['USERDATA'];
    $user_orgs = $curr_user->getUO($_SESSION['USERDATA']['id']);
} elseif (empty($_GET['username'])) {
    $userdata["username"] = "Вы не вошли в аккаунт";
}


// bid structure:
// id, title, author_id, path_to_cover, person_seek, needed_exp, salary_condition

$bids_array = [
    [1, "Howl-Growl", 1, "/path_to_cover", "CGI художник", 1, "non-free"],
    [2, "Pigeon of Sorrow", 2, "/path_to_cover", "Unity программист", 1, "non-free"],
    [3, "Solder Simulator", 3, "/path_to_cover", "Физик-ядерщик", 1, "non-free"],
    [4, "Dustore", 4, "/path_to_cover", "Деньги", 1, "non-free"]
];

// $user_orgs = $curr_user->getUO($_SESSION['USERDATA']['id']);
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

                        background-image: url('<?= $userdata['profile_picture'] ?>');
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
                    <div class="left-side-button">
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
                    <?php if ($userdata['username'] != 'Вы не вошли в аккаунт'): ?>
                        <div class="profile-page">

                            <!-- ВЕРХНИЙ БЛОК: ЮЗЕР -->
                            <div class="card user-card">
                                <div class="card-header">
                                    <div>
                                        <div class="label">Имя пользователя:</div>
                                        <h2 class="username"><?php $userdata['username'] != "" ? print($userdata['username']) : print("@" . $userdata['telegram_username']); ?> <span class="copy" style="font-size: .9rem; color: #ffffff3b;">⧉</span></h2>
                                    </div>
                                    <?php
                                    $dateString = $userdata['added'];
                                    $date = new DateTime($dateString);
                                    $date = $date->format('d.m.Y');
                                    ?>
                                    <div class="since"><br><br>На платформе с: <?= $date ?></div>
                                </div>

                                <div class="card-body">
                                    <div class="data-for">
                                        Данные для L4T
                                    </div>
                                    <div class="card-body-main">
                                        <div class="left">
                                            <span class="label">Роль:</span><br>
                                            <div class="row role" data-userid="<?= $userdata['id'] ?>">

                                                <span class="role-text">
                                                    <?= $userdata['l4t_role'] ?? "Роль не указана" ?>
                                                </span>
                                            </div>

                                            <div class="row">
                                                <span class="label">Опыт:</span>

                                                <div class="tags" id="expTags">
                                                    <?php
                                                    $exp = json_decode($userdata['l4t_exp'] ?? '[]', true);
                                                    foreach ($exp as $i => $e): ?>
                                                        <div class="tag" data-index="<?= $i ?>">
                                                            <?= htmlspecialchars($e['role']) ?> <?= $e['years'] ?>г.
                                                            <span class="del-exp">×</span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div id="experience-container"></div>

                                                <button class="tag" id="add-exp-btn">+</button>
                                            </div>

                                            <div id="expModal" class="modal hidden">
                                                <div class="modal-body">
                                                    <div id="expRows"></div>

                                                    <button id="addRow">Добавить строку</button>
                                                    <button id="saveExp">Сохранить</button>
                                                </div>
                                            </div>

                                            <div id="filesModal" class="modal hidden">
                                                <button id="addLink">Ссылка</button>
                                                <button id="addFile">Файл</button>

                                                <div id="filesRows"></div>
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
                            <?php
                            $user_orgs = $curr_user->getUO($userdata['id']);
                            ?>
                            <?php if (!empty($user_orgs)): ?>
                                <div class="card user-card">
                                    <div class="card-header">
                                        <div>
                                            <div class="label">Студия:</div>
                                            <h2 class="username"><?= $user_orgs[0]['name'] ?><span class="copy" style="font-size: .9rem; color: #ffffff3b;">
                                                    <a href="/d/<?= $user_orgs[0]['tiker'] ?>" target="_blank">
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            width="16"
                                                            height="16"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="#ffffff75"
                                                            stroke-width="1"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
                                                            <path d="M11 13l9 -9" />
                                                            <path d="M15 4h5v5" />
                                                        </svg>
                                                    </a>
                                                </span></h2>
                                        </div>
                                        <?php
                                        $dateString = $user_orgs[0]['foundation_date'];
                                        $date = new DateTime($dateString);
                                        $date = $date->format('d.m.Y');
                                        ?>
                                        <div class="since"><br><br>Студия на платформе с: <?= $date ?></div>
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
                                                            <?php
                                                            $users = [];
                                                            ?>
                                                            <?= count($users); ?>
                                                        </div>

                                                        <div class="users">
                                                            <?php foreach ($users as $u): ?>
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
                                                            <?php endforeach; ?>
                                                            <!-- <div class="user more">Ещё</div> -->
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
                            <?php else: ?>
                                <div class="card user-card">
                                    <div class="card-header">
                                        <div>
                                            <h4 class="username">У пользователя нет ни одной зарегестрированной организации</h4>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <h2 class="username" style="padding: 3rem;">Вы не вошли в аккаунт</h2>
                    <?php endif; ?>


                    <!-- БИРЖА -->
                    <div id="view-market" class="content-view">

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
            const savedView = localStorage.getItem("activeView") || "profile";
            showView(savedView);

            const savedSub = localStorage.getItem("createSubTab");
            if (savedSub) {
                const btn = document.querySelector(
                    `#view-create .filter-item[data-filter="${savedSub}"]`
                );
                if (btn) btn.click();
            }


        });
        const addBtn = document.getElementById('add-exp-btn');
        const container = document.getElementById('experience-container');
        const expTags = document.getElementById('expTags');

        // добавление новой строки
        addBtn.addEventListener('click', () => {
            const row = document.createElement('div');
            row.classList.add('exp-row');
            row.innerHTML = `
        <input type="text" name="exp_title[]" placeholder="Название опыта">
        <input type="number" name="exp_years[]" placeholder="Лет" min="0" max="50">
        <span class="del">×</span>
    `;
            container.appendChild(row);

            row.querySelector('.del').onclick = () => row.remove();
        });

        // функция сохранения
        function saveExp() {
            const rows = [...container.querySelectorAll('.exp-row')];
            const data = rows.map(r => ({
                role: r.querySelector('input[name="exp_title[]"]').value.slice(0, 30),
                years: parseInt(r.querySelector('input[name="exp_years[]"]').value) || 0
            }));

            fetch("/swad/controllers/l4t/update_exp.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        exp: data
                    })
                })
                .then(res => res.json())
                .then(resp => {
                    if (!resp.success) alert("Ошибка при сохранении опыта");
                    else {
                        // очищаем теги и заново рисуем
                        expTags.innerHTML = '';
                        data.forEach((e, i) => {
                            const tag = document.createElement('div');
                            tag.className = 'tag';
                            tag.dataset.index = i;
                            tag.innerHTML = `${e.role} ${e.years}г. <span class="del-exp">×</span>`;
                            expTags.appendChild(tag);
                        });

                        // чистим контейнер после сохранения
                        container.innerHTML = '';
                    }
                });
        }

        // удаление из тегов
        expTags.addEventListener('click', e => {
            if (!e.target.classList.contains('del-exp')) return;

            const tag = e.target.closest('.tag');
            const index = tag.dataset.index;

            const data = [...expTags.querySelectorAll('.tag')]
                .filter(t => t.dataset.index != index)
                .map(t => {
                    const parts = t.textContent.replace("×", "").trim().split(" ");
                    const years = parseInt(parts.pop());
                    const role = parts.join(" ");
                    return {
                        role,
                        years
                    };
                });

            fetch("/swad/controllers/l4t/update_exp.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    exp: data
                })
            }).then(() => tag.remove());
        });

        // сохраняем при потере фокуса в контейнере
        container.addEventListener('focusout', saveExp);
    </script>
</body>

</html>