<?php
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');

$db = new Database();
$curr_user = new User();
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Dustore.Devs - Настройки монетизации</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
    <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet" />
    <style>
        .token-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <?php
    require_once('../swad/static/elements/sidebar.php');

    $curr_user->checkAuth();
    $studio_id = $_SESSION['studio_id'];

    $stmt = $db->connect()->prepare("SELECT merchant_login, merchant_password, culture, encoding, customer_email, Shp_item FROM studios WHERE id = ?");
    $stmt->execute([$studio_id]);
    $pay = $stmt->fetch(PDO::FETCH_ASSOC);

    // Обработка формы
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $merchant_login      = preg_replace("/[^A-Za-z0-9_-]/", '', $_POST['merchant_login']);
        $merchant_password   = preg_replace("/[^A-Za-z0-9_-]/", '', $_POST['merchant_password']);
        $culture             = preg_replace("/[^A-Za-z]/", '', $_POST['culture']);
        $encoding            = preg_replace("/[^A-Za-z0-9-]/", '', $_POST['encoding']);
        $customer_email      = filter_var($_POST['customer_email'], FILTER_SANITIZE_EMAIL);
        $Shp_item            = substr($_POST['Shp_item'], 0, 60);

        $sql = "UPDATE studios SET 
            merchant_login = :merchant_login,
            merchant_password = :merchant_password,
            culture = :culture,
            encoding = :encoding,
            customer_email = :customer_email,
            Shp_item = :Shp_item
            WHERE id = :id";

        try {
            $stmt = $db->connect()->prepare($sql);
            $stmt->execute([
                ':merchant_login' => $merchant_login,
                ':merchant_password' => $merchant_password,
                ':culture' => $culture,
                ':encoding' => $encoding,
                ':customer_email' => $customer_email,
                ':Shp_item' => $Shp_item,
                ':id' => $studio_id
            ]);

            echo ("<script>window.location.replace('monetization?success=1');</script>");
            exit();
        } catch (PDOException $e) {
            $error_message = "Ошибка при сохранении настроек: " . $e->getMessage();
        }
    }
    ?>
    <main>
        <section class="content">
            <div class="page-announce valign-wrapper">
                <a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only">
                    <i class="material-icons">menu</i>
                </a>
                <h1 class="page-announce-text valign">// Настройки монетизации</h1>
            </div>

            <div class="container">

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        Настройки успешно сохранены!
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row">

                        <div class="col s12 m6">
                            <div class="input-field">
                                <input type="text" name="merchant_login" minlength="3" maxlength="32"
                                    value="<?= htmlspecialchars($pay['merchant_login']) ?>" required>
                                <label>Merchant Login (ваш идентификатор магазина в Robokassa)</label>
                            </div>

                            <div class="input-field">
                                <input type="password" name="merchant_password" minlength="6" maxlength="64"
                                    placeholder="<?= !empty($pay['merchant_password']) ? 'Установлен' : 'Введите пароль' ?>">
                                <label>Пароль №1 (password_1)</label>

                                <div class="token-warning">
                                    <i class="material-icons left">warning</i>
                                    <span>Сохраните пароль в надежном месте. После сохранения он будет скрыт.</span>
                                </div>
                            </div>

                            <div class="input-field">
                                <input type="email" name="customer_email"
                                    value="<?= htmlspecialchars($pay['customer_email']) ?>" maxlength="32">
                                <label>Email покупателя по умолчанию (оставьте пустым)</label>
                            </div>
                        </div>

                        <div class="col s12 m6">
                            <div class="input-field">
                                <input type="text" name="culture" value="<?= htmlspecialchars($pay['culture']) ?>" maxlength="6">
                                <label>Язык (culture), по умолчанию ru</label>
                            </div>

                            <div class="input-field">
                                <input type="text" name="encoding" value="<?= htmlspecialchars($pay['encoding']) ?>" maxlength="12">
                                <label>Кодировка (encoding), по умолчанию UTF-8</label>
                            </div>

                            <div class="input-field">
                                <input type="text" name="Shp_item" value="<?= htmlspecialchars($pay['Shp_item']) ?>" maxlength="60">
                                <label>Параметр Shp_item (оставьте пустым)</label>
                            </div>

                        </div>
                    </div>

                    <div class="row">
                        <div class="col s12 center-align">
                            <button class="btn waves-effect waves-light" type="submit">
                                <i class="material-icons left">save</i> Сохранить настройки
                            </button>
                            <a href="dashboard" class="btn grey">
                                <i class="material-icons left">cancel</i> Назад
                            </a>
                        </div>
                    </div>

                </form>
            </div>
        </section>
    </main>

    <?php require_once('footer.php'); ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/js/materialize.min.js"></script>
    <script>
        $(document).ready(() => $('select').material_select());
    </script>
</body>

</html>