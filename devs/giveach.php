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

    if (!isset($_SESSION['USERDATA']) || ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] < 3)) {
        echo ("<script>alert('У вас нет прав на использование этой функции');</script>");
        exit();
    }

    $db = new Database();
    $pdo = $db->connect();

    $nc = new NotificationCenter();
    $message = '';
    $error = '';

    $badgesList = $pdo->query("SELECT * FROM badges ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $usersList = $pdo->query("SELECT id, telegram_username, username, email FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    // print_r($badgesList);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $sendToAll = isset($_POST['sendtoall']);
        $selectedUsers = $_POST['users'] ?? [];

        if ($title === '' || $text === '') {
            $error = 'Введите заголовок и текст уведомления';
        } else {
            $user_ids = $sendToAll ? array_column($usersList, 'id') : array_map('intval', $selectedUsers);

            if (!empty($user_ids)) {
                $nc->sendNotifications($user_ids, $title, $text, null);
                $message = 'Уведомление успешно создано';
            } else {
                $error = 'Не выбраны пользователи для уведомления';
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
                    <div class="row">
                        <div class="input-field col s12">
                            <select multiple name="badges[]" id="badges">
                                <?php foreach ($badgesList as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?> (<?= htmlspecialchars($b['description']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <label>Выберите достижение</label>
                        </div>
                    </div>

                    <div class="row">
                        <label>
                            <input type="checkbox" name="sendtoall" id="sendtoall">
                            <span style="cursor: pointer;">➡ <u>Отправить всем пользователям?</u> ⬅</span>
                        </label>
                    </div>

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
                        <button class="btn green" type="submit">Создать уведомление</button>
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