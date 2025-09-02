<?php
session_start();
require_once('swad/static/elements/header.php');
require_once('swad/controllers/time.php');
require_once('swad/controllers/user.php');

// Проверяем, установлена ли passphrase у пользователя
$has_passphrase = $curr_user->hasPassphrase($_SESSION['USERDATA']['telegram_id']);

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
            $update_success = $curr_user->updateUsername($telegramID, $new_username);
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
    } else if (isset($_POST['update_passphrase'])) {
        $passphrase = trim($_POST['passphrase']);
        $confirm_passphrase = trim($_POST['confirm_passphrase']);
        $enable_passphrase = isset($_POST['enable_passphrase']);
        $errors_pp = [];

        // Получаем текущее состояние passphrase из базы
        $current_has_passphrase = $curr_user->hasPassphrase($telegramID);

        if ($enable_passphrase) {
            // Валидация passphrase только если она включена
            if (empty($passphrase)) {
                $errors_pp[] = "Passphrase обязательна для заполнения при включении";
            } elseif (strlen($passphrase) < 8) {
                $errors_pp[] = "Passphrase должна содержать минимум 8 символов";
            } elseif (str_word_count($passphrase) < 2) {
                $errors_pp[] = "Passphrase должна состоять минимум из двух слов";
            } elseif (!preg_match('/\s/', $passphrase)) {
                $errors_pp[] = "Passphrase должна содержать пробелы между словами";
            }

            if ($passphrase !== $confirm_passphrase) {
                $errors_pp[] = "Passphrase и подтверждение не совпадают";
            }
        }

        if (empty($errors_pp)) {
            if ($enable_passphrase) {
                // Хеширование и сохранение passphrase в БД
                $hashed_passphrase = password_hash($passphrase, PASSWORD_DEFAULT);
                $update_success = $curr_user->updatePassphrase($telegramID, $hashed_passphrase);
                if ($update_success) {
                    // Обновляем сессию
                    $_SESSION['USERDATA']['passphrase'] = $hashed_passphrase;
                    $_SESSION['success_message_pp'] = $current_has_passphrase ?
                        "Passphrase успешно обновлена" :
                        "Passphrase успешно установлена и включена";
                    echo ("<script>window.location.replace('/me');</script>");
                    exit;
                } else {
                    $errors_pp[] = "Ошибка при установке passphrase";
                    $_SESSION['errors_pp'] = $errors_pp;
                }
            } else {
                // Отключение passphrase
                $update_success = $curr_user->updatePassphrase($telegramID, null);
                if ($update_success) {
                    // Обновляем сессию
                    unset($_SESSION['USERDATA']['passphrase']);
                    $_SESSION['success_message_pp'] = "Passphrase отключена";
                    // Перенаправляем чтобы избежать повторной отправки формы
                    echo ("<script>window.location.replace('/me');</script>");

                    exit;
                } else {
                    $errors_pp[] = "Ошибка при отключении passphrase";
                    $_SESSION['errors_pp'] = $errors_pp;
                }
            }
        } else {
            $_SESSION['errors_pp'] = $errors_pp;
        }
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

// Проверяем, установлена ли passphrase у пользователя
if (isset($_SESSION['USERDATA']['passphrase'])) {
    $has_passphrase = true;
} else {
    $has_passphrase = $curr_user->hasPassphrase($telegramID);
    if ($has_passphrase) {
        $_SESSION['USERDATA']['passphrase'] = true;
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
                <img src="<?= $profilePicture ?>?v=<?= time() ?>"
                    class="profile-picture"
                    alt="Аватар">
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
                    <p title="<?= $updated; ?>">Последний вход: <?= time_ago($updated); ?></p>

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
                    <p>Тип учётной записи: <?= $curr_user->printUserPrivileges($curr_user->getRoleName($curr_user->getUserRole($user_data['telegram_id'], "global"))); ?></p>
                </div>
            </div>
        </div>

        <div id="security" class="tab-content">
            <div class="info-grid">
                <div class="info-card">
                    <h3>Безопасность</h3>

                    <?php if (!empty($success_message_pp)): ?>
                        <div class="success-message"><?= $success_message_pp ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors_pp)): ?>
                        <div class="error-message">
                            <?php foreach ($errors_pp as $error): ?>
                                <p><?= $error ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_passphrase" id="enable_passphrase"
                                    <?= $has_passphrase ? 'checked' : '' ?> onchange="togglePassphraseFields()">
                                Включить вход по ключевой фразе (passphrase)
                                <?php if ($has_passphrase): ?>
                                    <span class="status-badge">активна</span>
                                <?php endif; ?>
                            </label>
                        </div>

                        <div id="passphrase_fields" style="<?= $has_passphrase ? 'display: block;' : 'display: none;' ?>">
                            <div class="form-group">
                                <label for="passphrase">Passphrase:</label>
                                <input type="password" id="passphrase" name="passphrase"
                                    minlength="8"
                                    title="Минимум 8 символов, состоящих из слов с пробелами">
                                <small>Минимум 8 символов, должна состоять из нескольких слов с пробелами</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_passphrase">Подтверждение passphrase:</label>
                                <input type="password" id="confirm_passphrase" name="confirm_passphrase">
                            </div>
                        </div>

                        <button type="submit" name="update_passphrase" class="btn-primary">
                            <?= $has_passphrase ? 'Обновить настройки' : 'Сохранить настройки' ?>
                        </button>
                    </form>

                    <div class="spoiler">
                        <div class="spoiler-title" onclick="toggleSpoiler(this)">Что такое passphrase и почему это безопаснее? ▼</div>
                        <div class="spoiler-content">
                            <p><strong>Passphrase</strong> - это пароль, состоящий из нескольких слов, разделенных пробелами.</p>
                            <p><strong>Преимущества passphrase:</strong></p>
                            <ul>
                                <li>Легче запомнить: "correct horse battery staple" запомнить проще, чем "Tr0ub4d0r&3"</li>
                                <li>Выше энтропия: больше возможных комбинаций при одинаковой длине</li>
                                <li>Устойчивость к brute-force атакам: из-за большой длины перебор занимает значительно больше времени</li>
                                <li>Устойчивость к словарным атакам: использование нескольких случайных слов делает атаку по словарю неэффективной</li>
                            </ul>
                            <p><strong>Рекомендации по созданию надежной passphrase:</strong></p>
                            <ul>
                                <li>Используйте 4-5 случайных слов</li>
                                <li>Избегайте распространенных фраз или цитат</li>
                                <li>Не используйте личную информацию</li>
                                <li>Рассмотрите добавление цифр или специальных символов между словами</li>
                            </ul>
                            <p>Примеры хороших passphrase: "correct horse battery staple", "blue coffee tree window", "purple monkey dishwasher battery"</p>
                        </div>
                    </div>
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
                    <h3>Если у вас есть аккаунт разработчика:</h3>
                    <p><a href="/devs/select">Вход в консоль для разработчиков</a></p>
                    <h3>Если у вас ещё нет аккаунта разработчика:</h3>
                    <p><a href="/finance">Ознакомьтесь с тарифами</a></p>
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

        function togglePassphraseFields() {
            const enableCheckbox = document.getElementById('enable_passphrase');
            const passphraseFields = document.getElementById('passphrase_fields');

            if (enableCheckbox.checked) {
                passphraseFields.style.display = 'block';
                // Делаем поля обязательными при включении
                document.getElementById('passphrase').setAttribute('required', 'required');
                document.getElementById('confirm_passphrase').setAttribute('required', 'required');
            } else {
                passphraseFields.style.display = 'none';
                // Убираем обязательность полей при выключении
                document.getElementById('passphrase').removeAttribute('required');
                document.getElementById('confirm_passphrase').removeAttribute('required');
                // Очищаем поля
                document.getElementById('passphrase').value = '';
                document.getElementById('confirm_passphrase').value = '';
            }
        }

        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            togglePassphraseFields();

            // Добавляем обработчик для подтверждения отключения passphrase
            const enableCheckbox = document.getElementById('enable_passphrase');
            const passphraseForm = document.querySelector('form[action=""]');

            if (passphraseForm) {
                passphraseForm.addEventListener('submit', function(e) {
                    // Проверяем, отключается ли passphrase
                    if (!enableCheckbox.checked && <?= $has_passphrase ? 'true' : 'false' ?>) {
                        if (!confirm('Вы уверены, что хотите отключить passphrase? Это снизит безопасность вашего аккаунта.')) {
                            e.preventDefault();
                            enableCheckbox.checked = true;
                            togglePassphraseFields();
                        }
                    }
                });
            }
        });

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
</body>

</html>