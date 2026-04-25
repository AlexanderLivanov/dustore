<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Центр уведомлений</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
    <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
</head>

<body>
    <?php require_once('../swad/static/elements/sidebar.php'); ?>
    <?php
    require_once('../swad/config.php');
    require_once('../swad/controllers/NotificationCenter.php');

    $nc = new NotificationCenter();

    if (!isset($_SESSION['USERDATA']) || ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] < 3)) {
        echo ("<script>alert('У вас нет прав на использование этой функции');</script>");
        exit();
    }

    $db = new Database();
    $pdo = $db->connect();

    $message = '';
    $error = '';

    // Получаем все достижения
    $badgesList = $pdo->query("SELECT * FROM badges ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Получаем всех пользователей
    $usersList = $pdo->query("SELECT id, telegram_username, username, email FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selectedBadge = intval($_POST['badge'] ?? 0);
        $sendToAll = isset($_POST['sendtoall']);
        $selectedUsers = $_POST['users'] ?? [];

        if ($selectedBadge <= 0) {
            $error = 'Выберите достижение';
        } else {
            $user_ids = $sendToAll ? array_column($usersList, 'id') : array_map('intval', $selectedUsers);

            if (!empty($user_ids)) {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO given_user_badges (user_id, badge_id, awarded_at) VALUES (:user_id, :badge_id, NOW())"
                );
                $checkStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM given_user_badges WHERE user_id = :user_id AND badge_id = :badge_id"
                );

                $countInserted = 0;
                $notifiedUsers = [];

                foreach ($user_ids as $uid) {
                    // Проверяем, есть ли уже такое достижение у пользователя
                    $checkStmt->execute([':user_id' => $uid, ':badge_id' => $selectedBadge]);
                    $exists = $checkStmt->fetchColumn();

                    if (!$exists) {
                        $insertStmt->execute([':user_id' => $uid, ':badge_id' => $selectedBadge]);
                        $countInserted++;
                        $notifiedUsers[] = $uid; // собираем пользователей для уведомления
                    }
                }

                if ($countInserted > 0) {
                    // Получаем название достижения
                    $badgeName = '';
                    foreach ($badgesList as $b) {
                        if ($b['id'] == $selectedBadge) {
                            $badgeName = $b['name'];
                            break;
                        }
                    }

                    $title = "Новое достижение!";
                    $messageText = "Вам выдано достижение: {$badgeName}";

                    if (!empty($notifiedUsers)) {
                        // Отправка уведомлений в базе
                        $nc->sendNotifications($notifiedUsers, $title, $messageText);
                        $badgeStmt = $pdo->prepare("SELECT * FROM badges WHERE id = :id");
                        $badgeStmt->execute([':id' => $selectedBadge]);
                        $badgeData = $badgeStmt->fetch(PDO::FETCH_ASSOC);

                        $badgeName = $badgeData['name'];
                        // Отправка email каждому пользователю
                        foreach ($usersList as $user) {
                            if (in_array($user['id'], $notifiedUsers) && !empty($user['email'])) {
                                $mail_body = '
                                    <!DOCTYPE html>
                                    <html lang="ru">
                                    <head>
                                    <meta charset="UTF-8">
                                    <title>Новое достижение</title>
                                    </head>
                                    <body style="margin:0;padding:0;background-color:#0e0e12;font-family:Arial,Helvetica,sans-serif;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                    <td align="center" style="padding:40px 15px;">

                                    <table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
                                    <tr>
                                    <td style="padding:30px;text-align:center;">

                                    <h1 style="color:#ffffff;margin:0 0 10px;font-size:26px;">
                                    Поздравляем, ' . htmlspecialchars($user['username']) . '!
                                    </h1>

                                    <p style="color:#b8b8c6;font-size:15px;margin:0 0 25px;">
                                    Вам выдано новое достижение на Dustore:
                                    </p>

                                    ' . (!empty($badgeData['icon_url']) ? '<img src="' . htmlspecialchars($badgeData['icon_url']) . '" alt="Иконка достижения" style="width:100px;height:100px;margin-bottom:20px;border-radius:12px;">' : '') . '

                                    <h2 style="color:#c32178;margin:10px 0 25px;font-size:22px;">' . htmlspecialchars($badgeName) . '</h2>

                                    <p style="color:#b8b8c6;font-size:15px;margin:0 0 25px;">
                                    Поздравляем! Смотрите достижения в личном кабинете.
                                    </p>

                                    <p style="color:#9a9ab0;font-size:13px;margin:30px 0 0;">
                                    Если вы не регистрировались на Dustore, проигнорируйте это письмо.
                                    </p>

                                    </td>
                                    </tr>

                                    <tr>
                                    <td style="background:#0f0f15;padding:20px;text-align:center;">
                                    <p style="color:#6f6f85;font-size:12px;margin:0;">
                                    © 2024-' . date('Y') . ' Dustore · Все права защищены · <a href="https://t.me/dustore_official" style="color:#c32178;">Наш Telegram</a>
                                    </p>
                                    </td>
                                    </tr>

                                    </table>

                                    </td>
                                    </tr>
                                    </table>
                                    </body>
                                    </html>';


                                $nc->sendEmail($user['email'], $title, $mail_body);
                            }
                        }
                    }

                    $message = "Достижение успешно выдано $countInserted пользователю(ям)";
                } else {
                    $error = "Ни одному пользователю не удалось выдать достижение (возможно, у всех оно уже есть)";
                }
            } else {
                $error = 'Не выбраны пользователи для выдачи достижения';
            }
        }
    }

    ?>


    <main>
        <section class="content">
            <div class="page-announce valign-wrapper">
                <a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only">
                    <i class="material-icons">menu</i>
                </a>
                <h1 class="page-announce-text valign">Выдать достижение</h1>
            </div>

            <div class="container">
                <?php if ($error): ?>
                    <div class="card-panel red lighten-2 white-text"><?= htmlspecialchars($error) ?></div>
                <?php elseif ($message): ?>
                    <div class="card-panel green lighten-2 white-text"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form class="col s12" method="POST">
                    <!-- Выбор достижения -->
                    <div class="row">
                        <div class="input-field col s12">
                            <select name="badge" id="badge">
                                <option value="" disabled selected>Выберите достижение</option>
                                <?php foreach ($badgesList as $b): ?>
                                    <option value="<?= $b['id'] ?>" data-icon="<?= htmlspecialchars($b['icon_url']) ?>" class="left circle">
                                        <?= htmlspecialchars($b['name']) ?> (<?= htmlspecialchars($b['description']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label>Достижение</label>

                        </div>
                    </div>

                    <!-- Отправить всем -->
                    <div class="row">
                        <label>
                            <input type="checkbox" name="sendtoall" id="sendtoall">
                            <span style="cursor: pointer;">➡ <u>Выдать всем пользователям?</u> ⬅</span>
                        </label>
                    </div>

                    <!-- Выбор пользователей -->
                    <div class="row">
                        <div class="input-field col s12">
                            <select multiple name="users[]" id="users">
                                <?php foreach ($usersList as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (@<?= htmlspecialchars($u['telegram_username']) ?>), <?= $u['email'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label>Выберите пользователей</label>
                        </div>
                    </div>

                    <div class="center-align">
                        <button class="btn green" type="submit">Выдать достижение</button>
                    </div>
                </form>
            </div>
        </section>
    </main>


    <?php require_once('footer.php'); ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/js/materialize.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.button-collapse').sideNav({
                menuWidth: 300,
                edge: 'left',
                closeOnClick: false,
                draggable: true
            });

            $('select').material_select();

            $('#sendtoall').change(function() {
                $('#users').prop('disabled', $(this).is(':checked'));
                $('select').material_select();
            });

            $('.tooltipped').tooltip({
                delay: 50
            });
            $('.collapsible').collapsible();
        });
    </script>


</body>

</html>