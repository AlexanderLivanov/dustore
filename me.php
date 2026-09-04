<?php
session_start();
require_once('swad/static/elements/header.php');
require_once('swad/controllers/time.php');
require_once('swad/controllers/user.php');
require_once('swad/controllers/csrf.php');
require_once('swad/controllers/get_user_activity.php');
require_once('swad/controllers/organization.php');

$org = new Organization();

/* Проверка авторизации ПЕРЕД обработкой POST.
   Раньше checkAuth() стоял внутри <body>, то есть уже после всех
   обработчиков форм. Гость, отправивший POST, доходил до
   updateUsername(null, ...) и updatePassword(null, ...) — ничего не
   ломалось только потому, что WHERE id = NULL не находит строк. */
if ($curr_user->checkAuth() > 0) {
    echo "<script>window.location.replace('/login');</script>";
    exit;
}

$user_id = (int)($_SESSION['USERDATA']['id'] ?? 0);
$user_data = $_SESSION['USERDATA'];
$firstName        = $user_data['first_name'];
$lastName         = $user_data['last_name'];
$profilePicture   = $user_data['profile_picture'];
$telegramID       = $user_data['telegram_id'];
$telegramUsername = $user_data['telegram_username'];
$userID           = $user_data['id'];
$added            = $user_data['added'];
$updated          = $user_data['updated'];
$username         = $user_data['username'] ?? '';

$errors     = [];
$errors_pp  = [];
$errors_sec = [];   // раньше не инициализировался: empty() на неопределённой
                    // переменной возвращает true, и ветка проходила «сама собой»

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ─────────────────────────────────────────────────────────────────────────
   Обработка форм.
   Каждая проверяет CSRF-токен. До этого их не проверял никто, и это давало
   прямой путь к захвату аккаунта: сторонняя страница с автосабмитом POST
   на /me с полем change_password меняла пароль залогиненному посетителю
   на выбранный атакующим. Дальше — обычный вход по email и паролю.
   ───────────────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valid()) {
        $_SESSION['errors'] = ['Сессия устарела, обновите страницу и повторите'];
        echo "<script>window.location.replace('/me');</script>";
        exit;
    }

    /* ── Имя пользователя ── */
    if (isset($_POST['update_username'])) {
        $new_username = trim((string)($_POST['username'] ?? ''));
        $current_username = $user_data['username'] ?? '';

        if ($new_username === '') {
            $errors[] = "Имя пользователя обязательно для заполнения";
        } elseif (mb_strlen($new_username) < 3) {
            $errors[] = "Имя пользователя должно содержать минимум 3 символа";
        } elseif (mb_strlen($new_username) > 32) {
            $errors[] = "Имя пользователя не длиннее 32 символов";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
            $errors[] = "Имя пользователя может содержать только латинские буквы, цифры и символ подчеркивания";
        } elseif ($new_username !== $current_username && $curr_user->checkUsernameExists($new_username)) {
            $errors[] = "Это имя пользователя уже занято";
        }

        if (!$errors) {
            if ($curr_user->updateUsername($user_id, $new_username)) {
                $_SESSION['USERDATA']['username'] = $new_username;
                $_SESSION['success_message'] = "Имя пользователя успешно обновлено";
                echo "<script>window.location.replace('/me');</script>";
                exit;
            }
            $errors[] = "Ошибка при обновлении имени пользователя";
        }
        $_SESSION['errors'] = $errors;
        echo "<script>window.location.replace('/me');</script>";
        exit;
    }

    /* ── Аватар по ссылке ── */
    if (isset($_POST['update_profile_picture'])) {
        $url = trim((string)($_POST['profile_picture_url'] ?? ''));

        /* FILTER_VALIDATE_URL пропускает и javascript:, и data: — схему
           проверяем отдельно, иначе ссылка окажется в src аватарки. */
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)
            || !preg_match('~^https?://~i', $url) || mb_strlen($url) > 500) {
            $errors_pp[] = "Некорректная ссылка на изображение";
        }

        if (!$errors_pp) {
            if ($curr_user->updateProfilePicture($user_id, $url)) {
                $_SESSION['USERDATA']['profile_picture'] = $url;
                $_SESSION['success_message_pp'] = "Аватарка обновлена";
            } else {
                $errors_pp[] = "Ошибка при обновлении аватарки";
            }
        }
        $_SESSION['errors_pp'] = $errors_pp;
        echo "<script>window.location.replace('/me');</script>";
        exit;
    }

    /* ── Привязка почты ── */
    if (isset($_POST['bind_email'])) {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors_sec[] = "Некорректный email";
        }
        if ($password !== $confirm || strlen($password) < 8) {
            $errors_sec[] = "Пароль минимум 8 символов и должен совпадать";
        }
        if (!$errors_sec && $curr_user->emailExists($email, $user_id)) {
            $errors_sec[] = "Этот email уже привязан к другому аккаунту";
        }

        if (!$errors_sec) {
            $hash  = password_hash($password, PASSWORD_BCRYPT);
            $token = bin2hex(random_bytes(16));

            $curr_user->updateEmailAndPassword($user_id, $email, $hash, $token);

            require_once('swad/controllers/send_email.php');
            sendMail(
                $email,
                "Подтверждение почты — Dustore",
                "Подтвердите привязку почты по ссылке: "
                . "<a href='https://dustore.ru/recovery?token=" . $token . "'>"
                . "https://dustore.ru/recovery?token=" . $token . "</a>"
            );

            $_SESSION['success_message_sec'] = "Почта привязана. Подтвердите email для входа по паролю.";
        } else {
            $_SESSION['errors_sec'] = $errors_sec;
        }
        echo "<script>window.location.replace('/me');</script>";
        exit;
    }

    /* ── Смена пароля ── */
    if (isset($_POST['change_password'])) {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        /* Текущий пароль раньше не спрашивали вообще. Даже с CSRF-токеном
           это плохо: любой, кто добрался до открытой сессии — чужой ноутбук,
           незакрытая вкладка, угнанная кука — менял пароль не зная старого
           и запирал владельца снаружи. */
        $st = $curr_user->db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $st->execute([$user_id]);
        $stored = (string)$st->fetchColumn();

        if ($stored !== '' && !password_verify($current, $stored)) {
            $errors_sec[] = "Текущий пароль указан неверно";
        }
        if ($new !== $confirm || strlen($new) < 8) {
            $errors_sec[] = "Пароль минимум 8 символов и должен совпадать";
        }

        if (!$errors_sec) {
            $curr_user->updatePassword($user_id, password_hash($new, PASSWORD_BCRYPT));
            $_SESSION['success_message_sec'] = "Пароль успешно обновлён";
        } else {
            $_SESSION['errors_sec'] = $errors_sec;
        }
        echo "<script>window.location.replace('/me');</script>";
        exit;
    }
}

/* Сообщения из сессии — читаем и сразу гасим */
$success_message    = $_SESSION['success_message'] ?? '';
$errors             = $_SESSION['errors'] ?? [];
$success_message_pp = $_SESSION['success_message_pp'] ?? '';
$errors_pp          = $_SESSION['errors_pp'] ?? [];
$success_message_sec = $_SESSION['success_message_sec'] ?? '';
$errors_sec         = $_SESSION['errors_sec'] ?? [];

unset(
    $_SESSION['success_message'],
    $_SESSION['errors'],
    $_SESSION['success_message_pp'],
    $_SESSION['errors_pp'],
    $_SESSION['success_message_sec'],
    $_SESSION['errors_sec']
);

/* Студия запрашивается один раз, а не дважды подряд, как было в разметке */
$myStudios = $curr_user->getUO($userID);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Мой аккаунт</title>
    <link rel="stylesheet" href="swad/css/userprofile.css">
    <?php require_once('swad/controllers/ymcounter.php'); ?>
    <style>
        .avatar-wrapper { position: relative; display: inline-block; width: 150px; height: 150px; }
        .profile-picture { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; transition: 0.3s all; display: block; }
        .avatar-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            border-radius: 50%; background: rgba(0, 0, 0, 0.4);
            display: flex; justify-content: center; align-items: center;
            opacity: 0; transition: 0.3s all; cursor: pointer; z-index: 10;
        }
        .avatar-wrapper:hover .avatar-overlay { opacity: 1; }
        .upload-btn {
            color: white; font-size: 14px; padding: 10px 15px;
            background: #1976d2; border-radius: 25px;
            display: flex; align-items: center; gap: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        .upload-btn i { font-size: 18px; }
    </style>
</head>

<body>
    <div class="profile-container">
        <div class="profile-header">
            <?php /* Раньше весь блок был обёрнут в if (!is_null($profilePicture)),
                     а внутри лежал <input type="file">. То есть чтобы поставить
                     аватар, его надо было уже иметь. Блок рисуем всегда,
                     при отсутствии картинки показываем заглушку. */ ?>
                <div class="avatar-wrapper">
                    <img id="profileAvatar"
                         src="<?= $profilePicture ? h($profilePicture) . '?v=' . time() : '/swad/static/img/logo.svg' ?>"
                         class="profile-picture" alt="Аватар">
                    <div class="avatar-overlay">
                        <label for="avatarInput" class="upload-btn">
                            <i class="material-icons">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
                                     fill="none" stroke="#fff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 7h1a2 2 0 0 0 2 -2a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1a2 2 0 0 0 2 2h1a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9a2 2 0 0 1 2 -2" />
                                    <path d="M9.5 15a3.5 3.5 0 0 0 5 0" />
                                    <path d="M15 11l.01 0" />
                                    <path d="M9 11l.01 0" />
                                </svg>
                            </i> Изменить
                        </label>
                        <input type="file" id="avatarInput" accept="image/png, image/jpeg, image/webp" style="display:none;">
                    </div>
                </div>
            <div>
                <h1><?= h(trim($firstName . ' ' . (string)$lastName)) ?></h1>
                <?php if (!empty($username)): ?>
                    <p>@<?= h($username) ?></p>
                <?php elseif (!empty($telegramUsername)): ?>
                    <p>@<?= h($telegramUsername) ?></p>
                <?php else: ?>
                    <p>Имя пользователя не предоставлено</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button active" onclick="switchTab(event, 'profile')">Профиль</button>
            <button class="tab-button" onclick="switchTab(event, 'security')">Безопасность</button>
            <button class="tab-button" onclick="switchTab(event, 'activity')">Для разработчиков</button>
        </div>

        <div id="profile" class="tab-content active">
            <div class="info-grid">
                <div class="info-card">
                    <h3>Основная информация</h3>
                    <p>Имя: <?= h(trim($firstName . ' ' . (string)$lastName)) ?></p>
                    <p title="<?= h($added) ?>">Присоединился к проекту: <?= h($added) ?></p>
                    <p title="<?= h($updated) ?>">Был(а): <?= time_ago($updated) ?></p>

                    <h3>Уникальное имя пользователя</h3>
                    <?php if (!empty($success_message)): ?>
                        <div class="success-message"><?= h($success_message) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors)): ?>
                        <div class="error-message">
                            <?php foreach ($errors as $error): ?><p><?= h($error) ?></p><?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="username">Никнейм (латиница, цифры, подчёркивание):</label>
                            <input type="text" id="username" name="username" value="<?= h($username) ?>"
                                required pattern="[a-zA-Z0-9_]{3,32}" title="Латинские буквы, цифры и подчеркивание (минимум 3 символа)">
                        </div>
                        <button type="submit" name="update_username" class="btn-primary">Обновить имя пользователя</button>
                    </form>

                    <?php if (!empty($success_message_pp)): ?>
                        <div class="success-message"><?= h($success_message_pp) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors_pp)): ?>
                        <div class="error-message">
                            <?php foreach ($errors_pp as $error): ?><p><?= h($error) ?></p><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h3>Информация об аккаунте</h3>
                    <?php /* Отрицательный telegram_id — это старый temp_id, который
                             выдавался анонимам. Показывать его как «Telegram ID»
                             бессмысленно: человек регистрировался через почту. */ ?>
                    <?php if (!empty($telegramID) && (int)$telegramID > 0): ?>
                        <p>Telegram ID: <?= h($telegramID) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($telegramUsername)): ?>
                        <p>Telegram Username:
                            <a href="https://t.me/<?= rawurlencode((string)$telegramUsername) ?>">@<?= h($telegramUsername) ?></a>
                        </p>
                    <?php endif; ?>
                    <p>Тип учётной записи:
                        <?php
                        /* printUserPrivileges() на неизвестной роли печатала
                           «Неверный идентификатор» — для обычного пользователя
                           это выглядит как ошибка в его аккаунте. */
                        $roleName = $curr_user->getRoleName($curr_user->getUserRole($userID, "global"));
                        if (in_array($roleName, ['creator','user','employee','owner','moder','admin'], true)) {
                            $curr_user->printUserPrivileges($roleName);
                        } else {
                            echo 'Обычный пользователь';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <div id="security" class="tab-content">
            <div class="info-grid">
                <div class="info-card">
                    <?php if (!empty($success_message_sec)): ?>
                        <div class="success-message"><?= h($success_message_sec) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors_sec)): ?>
                        <div class="error-message">
                            <?php foreach ($errors_sec as $error): ?><p><?= h($error) ?></p><?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($user_data['email'])): ?>
                        <h3>Привязка почты</h3>
                        <p>Для тех, кто скучает по 2007</p>

                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="email" name="email" required placeholder="Email" autocomplete="email">
                            <input type="password" name="password" required placeholder="Пароль" autocomplete="new-password">
                            <input type="password" name="confirm_password" required placeholder="Повторите пароль" autocomplete="new-password">
                            <button name="bind_email">Привязать почту</button>
                        </form>
                    <?php else: ?>
                        <p>Email: <b><?= h($user_data['email']) ?></b></p>

                        <?php if (empty($user_data['email_verified'])): ?>
                            <div class="error-message">Почта не подтверждена</div>
                        <?php endif; ?>

                        <h3>Смена пароля</h3>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="password" name="current_password" required placeholder="Текущий пароль" autocomplete="current-password">
                            <input type="password" name="new_password" required placeholder="Новый пароль" autocomplete="new-password">
                            <input type="password" name="confirm_password" required placeholder="Повторите пароль" autocomplete="new-password">
                            <button name="change_password">Обновить пароль</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h3>Завершение сеанса</h3>
                    <p>Выход прекратит доступ к профилю на этом устройстве. Войти снова можно тем же способом, которым вы регистрировались — через Telegram или по почте с паролем.</p>

                    <form action="swad/controllers/logout.php" method="POST" onsubmit="return confirmLogout()">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-logout">Выйти из аккаунта</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="activity" class="tab-content">
            <div class="info-grid">
                <div class="info-card">
                    <?php if (!empty($myStudios)): ?>
                        <h1>Студия <?= h($myStudios[0]['name']) ?></h1>
                        <p><a href="/devs/select">Вход в консоль для разработчиков</a></p>
                    <?php else: ?>
                        <h1>У вас ещё нет аккаунта разработчика</h1>
                        <p><a href="/devs/regorg">Зарегистрируйте его бесплатно!</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        function switchTab(event, tabName) {
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }

        function confirmLogout() {
            return confirm('Вы уверены, что хотите выйти из аккаунта? Для повторного входа потребуется снова авторизоваться.');
        }
    </script>

    <style>
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%; padding: 8px; border: 1px solid #ddd;
            border-radius: 4px; box-sizing: border-box; margin-bottom: 8px;
        }
        small { color: #666; font-size: 0.85em; }
        .btn-primary {
            background-color: #4CAF50; color: white; padding: 10px 15px;
            border: none; border-radius: 4px; cursor: pointer;
        }
        .btn-primary:hover { background-color: #45a049; }
        .error-message {
            color: #d9534f; background-color: #fdf7f7; border: 1px solid #d9534f;
            padding: 10px; border-radius: 4px; margin-bottom: 15px;
        }
        .success-message {
            color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6;
            padding: 10px; border-radius: 4px; margin-bottom: 15px;
        }
        .btn-logout {
            background-color: #d9534f; color: white; padding: 10px 15px;
            border: none; border-radius: 4px; cursor: pointer;
            width: 100%; font-size: 16px; margin-top: 15px;
        }
        .btn-logout:hover { background-color: #c9302c; }
    </style>

    <script>
        const avatarInput = document.getElementById('avatarInput');
        const profileAvatar = document.getElementById('profileAvatar');

        // Блок аватарки рендерится только при непустом profile_picture,
        // поэтому элементов может не быть — раньше здесь падал TypeError
        if (avatarInput && profileAvatar) {
            avatarInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;

                if (!['image/jpeg', 'image/png'].includes(file.type)) {
                    alert('Только JPEG или PNG');
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    alert('Максимальный размер файла 2 МБ');
                    return;
                }

                const img = new Image();
                img.src = URL.createObjectURL(file);

                img.onload = async () => {
                    const canvas = document.createElement('canvas');
                    const maxDim = 500;
                    let { width, height } = img;

                    if (width > height) {
                        if (width > maxDim) { height *= maxDim / width; width = maxDim; }
                    } else {
                        if (height > maxDim) { width *= maxDim / height; height = maxDim; }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    URL.revokeObjectURL(img.src);

                    canvas.toBlob(async (blob) => {
                        const formData = new FormData();
                        formData.append('avatar', blob, file.name);
                        formData.append('csrf', <?= json_encode(csrf_token()) ?>);

                        try {
                            const res = await fetch('/swad/controllers/upload_avatar.php', {
                                method: 'POST',
                                headers: { 'X-CSRF-Token': <?= json_encode(csrf_token()) ?> },
                                body: formData
                            });
                            const data = await res.json();
                            if (data.success) {
                                profileAvatar.src = data.url + '?v=' + Date.now();
                            } else {
                                alert('Ошибка при загрузке: ' + (data.error || 'неизвестная'));
                            }
                        } catch (err) {
                            console.error(err);
                            alert('Ошибка загрузки файла');
                        }
                    }, 'image/jpeg', 0.7);
                };
            });
        }
    </script>

</body>

</html>