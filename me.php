<?php
session_start();
require_once('swad/static/elements/header.php');
require_once('swad/controllers/time.php');
require_once('swad/controllers/user.php');
require_once('swad/controllers/get_user_activity.php');
require_once('swad/controllers/organization.php');

$org = new Organization();
$user_id = $_SESSION['USERDATA']['id'];
// Получение обновленных данных пользователя
$user_data = $_SESSION['USERDATA'];
$firstName        = $user_data['first_name'];
$lastName         = $user_data['last_name'];
$profilePicture   = $user_data['profile_picture'];
$telegramID       = $user_data['telegram_id'];
$telegramUsername = $user_data['telegram_username'];
$userID           = $user_data['id'];
$added            = $user_data['added'];
$updated          = $user_data['updated'];
$username         = isset($user_data['username']) ? $user_data['username'] : '';

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_username'])) {
        $new_username = trim($_POST['username']);
        $current_username = $user_data['username'] ?? '';
        $errors = [];

        // Валидация имени пользователя
        if (empty($new_username)) {
            $errors[] = "Имя пользователя обязательно для заполнения";
        } elseif (strlen($new_username) < 3) {
            $errors[] = "Имя пользователя должно содержать минимум 3 символа";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
            $errors[] = "Имя пользователя может содержать только латинские буквы, цифры и символ подчеркивания";
        } else {
            if ($new_username !== $current_username) {
                $is_username_taken = $curr_user->checkUsernameExists($new_username);
                if ($is_username_taken) {
                    $errors[] = "Это имя пользователя уже занято";
                }
            }
        }

        if (empty($errors)) {
            $update_success = $curr_user->updateUsername($user_id, $new_username);
            if ($update_success) {
                $_SESSION['USERDATA']['username'] = $new_username;
                $_SESSION['success_message'] = "Имя пользователя успешно обновлено";
                // Перенаправляем чтобы избежать повторной отправки формы
                echo ("<script>window.location.replace('/me');</script>");
                exit;
            } else {
                $errors[] = "Ошибка при обновлении имени пользователя";
                $_SESSION['errors'] = $errors;
            }
        } else {
            $_SESSION['errors'] = $errors;
        }
    }
    if (isset($_POST['update_profile_picture'])) {
        $errors_pp = [];

        $url = trim($_POST['profile_picture_url'] ?? '');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors_pp[] = "Некорректная ссылка на изображение";
        }

        if (empty($errors_pp)) {
            $ok = $curr_user->updateProfilePicture($user_id, $url);
            if ($ok) {
                $_SESSION['USERDATA']['profile_picture'] = $url;
                $_SESSION['success_message_pp'] = "Аватарка обновлена ✅";
                echo ("<script>window.location.href='/me'</script>");
                exit;
            } else {
                $errors_pp[] = "Ошибка при обновлении аватарки";
            }
        }

        $_SESSION['errors_pp'] = $errors_pp;
        echo ("<script>window.location.href='/me'</script>");
        exit;
    }
}

// Получаем сообщения из сессии и очищаем их
$success_message = $_SESSION['success_message'] ?? '';
$errors = $_SESSION['errors'] ?? [];
$success_message_pp = $_SESSION['success_message_pp'] ?? '';
$errors_pp = $_SESSION['errors_pp'] ?? [];

unset(
    $_SESSION['success_message'],
    $_SESSION['errors'],
    $_SESSION['success_message_pp'],
    $_SESSION['errors_pp']
);

if (isset($_POST['bind_email'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некорректный email";
    }

    if ($password !== $confirm || strlen($password) < 8) {
        $errors[] = "Пароль минимум 8 символов и должен совпадать";
    }

    if ($curr_user->emailExists($email, $_SESSION['USERDATA']['id'])) {
        $errors[] = "Этот email уже привязан к другому аккаунту";
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(16));

        $curr_user->updateEmailAndPassword($_SESSION['USERDATA']['id'], $email, $hash, $token);

        require_once('swad/controllers/send_email.php');
        sendMail($email, "Сброс пароля", "Для сброса пароля вооспользуйтесь ссылкой: <a href='https://dustore.ru/recovery?token=" . $token . "'>https://dustore.ru/recovery?token=" . $token . "</a>");

        $_SESSION['success_message_sec'] =
            "📩 Почта привязана. Подтвердите email для входа по паролю.";

        echo ("<script>window.location.href = '/me'</script>");
        exit;
    }
}

// echo $curr_user->updateEmailAndPassword(1, "a.livanov@.com", "1233", "hello");

if (isset($_POST['change_password'])) {
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm || strlen($new) < 8) {
        $errors_sec[] = "Пароль минимум 8 символов и должен совпадать";
    }

    if (empty($errors_sec)) {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $curr_user->updatePassword($_SESSION['USERDATA']['id'], $hash);
        $_SESSION['success_message_sec'] = "Пароль успешно обновлён";
        echo ("<script>window.location.href = '/me'</script>");
        exit;
    }
}

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
        .avatar-wrapper {
            position: relative;
            display: inline-block;
            width: 150px;
            height: 150px;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            transition: 0.3s all;
            display: block;
        }

        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: 0.3s all;
            cursor: pointer;
            z-index: 10;
        }

        .avatar-wrapper:hover .avatar-overlay {
            opacity: 1;
        }

        .upload-btn {
            color: white;
            font-size: 14px;
            padding: 10px 15px;
            background: #1976d2;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .upload-btn i {
            font-size: 18px;
        }
    </style>
</head>

<body>
    <?php
    if ($curr_user->checkAuth() > 0) {
        echo ("<script>window.location.replace('/login');</script>");
        exit;
    }
    ?>
    <div class="profile-container">
        <div class="profile-header">
            <?php if (!is_null($profilePicture)): ?>
                <div class="avatar-wrapper">
                    <img id="profileAvatar" src="<?= $profilePicture ?>?v=<?= time() ?>" class="profile-picture" alt="Аватар">
                    <div class="avatar-overlay">
                        <label for="avatarInput" class="upload-btn">
                            <i class="material-icons">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="32"
                                    height="32"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="#fff"
                                    stroke-width="1"
                                    stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M5 7h1a2 2 0 0 0 2 -2a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1a2 2 0 0 0 2 2h1a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9a2 2 0 0 1 2 -2" />
                                    <path d="M9.5 15a3.5 3.5 0 0 0 5 0" />
                                    <path d="M15 11l.01 0" />
                                    <path d="M9 11l.01 0" />
                                </svg>
                            </i> Изменить
                        </label>
                        <input type="file" id="avatarInput" accept="image/png, image/jpeg" style="display:none;">
                    </div>
                </div>

            <?php endif; ?>
            <div>
                <h1><?= $firstName . (!is_null($lastName) ? ' ' . $lastName : '') ?></h1>
                <?php if (!empty($username)): ?>
                    <p>@<?= $username ?></p>
                <?php elseif (!is_null($telegramUsername)): ?>
                    <p>@<?= $telegramUsername ?></p>
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
                    <p>Имя пользователя: <?= $firstName ?>
                        <?php if (!is_null($lastName)): ?>
                            <?= $lastName ?>
                        <?php endif; ?>
                    </p>
                    <p title="<?= $added; ?>">Присоединился к проекту: <?= $added ?></p>
                    <p title="<?= $updated; ?>">Был(а): <?= time_ago($updated); ?></p>

                    <h3>Уникальное имя пользователя</h3>
                    <?php if (!empty($success_message)): ?>
                        <div class="success-message"><?= $success_message ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors)): ?>
                        <div class="error-message">
                            <?php foreach ($errors as $error): ?>
                                <p><?= $error ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">Имя пользователя:</label>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>"
                                required pattern="[a-zA-Z0-9_]{3,}" title="Латинские буквы, цифры и подчеркивание (минимум 3 символа)">
                        </div>
                        <button type="submit" name="update_username" class="btn-primary">Обновить имя пользователя</button>
                    </form>
                </div>

                <div class="info-card">
                    <h3>Информация об аккаунте</h3>
                    <p>Telegram ID: <?= $telegramID ?></p>
                    <?php if (!is_null($telegramUsername)): ?>
                        <p>Telegram Username: <a href="https://t.me/<?= $telegramUsername ?>">@<?= $telegramUsername ?></a></p>
                    <?php endif; ?>
                    <p>Тип учётной записи: <?= $curr_user->printUserPrivileges($curr_user->getRoleName($curr_user->getUserRole($user_data['id'], "global"))); ?></p>
                </div>
            </div>
        </div>

        <div id="security" class="tab-content">
            <div class="info-grid">
                <div class="info-card">
                    <?php if (empty($user_data['email'])): ?>
                        <h3>Привязка почты</h3>
                        <p>Для тех, кто скучает по 2007</p>

                        <form method="POST">
                            <input type="email" name="email" required placeholder="Email">
                            <input type="password" name="password" required placeholder="Пароль">
                            <input type="password" name="confirm_password" required placeholder="Повторите пароль">
                            <button name="bind_email">Привязать почту</button>
                        </form>
                    <?php else: ?>

                        <p>Email: <b><?= htmlspecialchars($user_data['email']) ?></b></p>

                        <?php if (!$user_data['email_verified']): ?>
                            <div class="error-message">⚠️ Почта не подтверждена</div>
                        <?php endif; ?>

                        <h3>Смена пароля</h3>
                        <form method="POST">
                            <input type="password" name="new_password" required placeholder="Новый пароль">
                            <input type="password" name="confirm_password" required placeholder="Повторите пароль">
                            <button name="change_password">Обновить пароль</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h3>Завершение сеанса</h3>
                    <p>Выход из аккаунта прекратит доступ к вашему профилю на этом устройстве. Для повторного входа потребуется снова авторизоваться через Telegram или использовать passphrase.</p>

                    <form action="swad/controllers/logout.php" method="POST" onsubmit="return confirmLogout()">
                        <button type="submit" class="btn-logout">Выйти из аккаунта</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="activity" class="tab-content">
            <div class="info-grid">
                <div class="info-card">
                    <h3>
                        <?php
                        // print_r($user_data);
                        if ($curr_user->getUO($userID)) {
                            echo ("<h1>Студия " . $curr_user->getUO($userID)[0]['name'] . "</h1>");
                            echo ("<p><a href='/devs/select'>Вход в консоль для разработчиков</a></p>");
                        } else {
                            echo ("<h1>У вас ещё нет аккаунта разработчика</h1>");
                            echo ("<p><a href='/devs/regorg'>Зарегистрируйте его бесплатно!</a></p>");
                        }
                        ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>
    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        function switchTab(event, tabName) {
            // Убираем активные классы
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            // Добавляем активные классы
            event.currentTarget.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }

        function toggleSpoiler(element) {
            const content = element.nextElementSibling;
            if (content.style.display === 'block') {
                content.style.display = 'none';
                element.innerHTML = element.innerHTML.replace('▼', '►');
            } else {
                content.style.display = 'block';
                element.innerHTML = element.innerHTML.replace('►', '▼');
            }
        }

        function confirmLogout() {
            return confirm('Вы уверены, что хотите выйти из аккаунта? Для повторного входа потребуется снова авторизоваться.');
        }
    </script>

    <style>
        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        small {
            color: #666;
            font-size: 0.85em;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-primary:hover {
            background-color: #45a049;
        }

        .error-message {
            color: #d9534f;
            background-color: #fdf7f7;
            border: 1px solid #d9534f;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .success-message {
            color: #3c763d;
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .spoiler {
            margin-top: 20px;
        }

        .spoiler-title {
            cursor: pointer;
            font-weight: bold;
            padding: 5px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }

        .spoiler-content {
            display: none;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
            background-color: #f9f9f9;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"] {
            margin-right: 10px;
            width: auto;
        }

        .status-badge {
            background-color: #4CAF50;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 10px;
        }

        .btn-logout {
            background-color: #d9534f;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            margin-top: 15px;
        }

        .btn-logout:hover {
            background-color: #c9302c;
        }
    </style>
    <script>
        const avatarInput = document.getElementById('avatarInput');
        const profileAvatar = document.getElementById('profileAvatar');

        avatarInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Проверка типа и размера
            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                alert('Только JPEG или PNG');
                return;
            }
            if (file.size > 2 * 1024 * 1024) { // 2 МБ
                alert('Максимальный размер файла 2 МБ');
                return;
            }

            // Сжатие изображения через canvas
            const img = new Image();
            img.src = URL.createObjectURL(file);

            img.onload = async () => {
                const canvas = document.createElement('canvas');
                const maxDim = 500; // максимальная ширина/высота
                let {
                    width,
                    height
                } = img;

                if (width > height) {
                    if (width > maxDim) {
                        height *= maxDim / width;
                        width = maxDim;
                    }
                } else {
                    if (height > maxDim) {
                        width *= maxDim / height;
                        height = maxDim;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(async (blob) => {
                    const formData = new FormData();
                    formData.append('avatar', blob, file.name);

                    try {
                        const res = await fetch('/swad/controllers/upload_avatar.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await res.json();

                        if (data.success) {
                            profileAvatar.src = data.url + '?v=' + Date.now(); // обновляем аватар
                        } else {
                            alert('Ошибка при загрузке: ' + data.error);
                        }
                    } catch (err) {
                        console.error(err);
                        alert('Ошибка загрузки файла');
                    }
                }, 'image/jpeg', 0.7); // сжатие JPEG 70%
            };
        });
    </script>

</body>

</html>