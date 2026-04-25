<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Создать достижение</title>
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
    require_once('../swad/controllers/s3.php');
    $s3Uploader = new S3Uploader();

    if (!isset($_SESSION['USERDATA']) || ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] < 3)) {
        echo ("<script>alert('У вас нет прав на использование этой функции');</script>");
        exit();
    }

    $db = new Database();
    $pdo = $db->connect();

    $message = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? '';
        $coefficient = floatval($_POST['coefficient'] ?? 1.0);
        $creator_id = $_SESSION['USERDATA']['id'];

        $icon_url = null;
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['icon'];

            // Проверка MIME
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (in_array($mime, $allowed_mime)) {
                // Генерация уникального пути в S3
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safe_name = preg_replace('/[^a-z0-9]/i', '-', $name);
                $s3_path = "badges/{$safe_name}-" . uniqid() . ".{$file_ext}";

                // Загружаем на S3
                $uploaded_url = $s3Uploader->uploadFile($file['tmp_name'], $s3_path);
                if ($uploaded_url) {
                    $icon_url = $uploaded_url;
                } else {
                    $error = "Не удалось загрузить иконку на S3";
                }
            } else {
                $error = "Недопустимый тип файла для иконки";
            }
        }


        if (empty($name) || empty($description) || empty($type)) {
            $error = "Заполните все обязательные поля!";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO badges (name, description, created_at, created_by, multiplier, type, icon_url)
                VALUES (:name, :description, NOW(), :creator_id, :coefficient, :type, :icon_url)
            ");

            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':creator_id' => $creator_id,
                ':coefficient' => $coefficient,
                ':type' => $type,
                ':icon_url' => $icon_url
            ]);

            $message = "Достижение '$name' успешно создано!";
        }
    }
    ?>

    <main>
        <section class="content">
            <div class="page-announce valign-wrapper">
                <a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only">
                    <i class="material-icons">menu</i>
                </a>
                <h1 class="page-announce-text valign">Создать достижение</h1>
            </div>

            <div class="container">
                <?php if ($error): ?>
                    <div class="card-panel red lighten-2 white-text"><?= htmlspecialchars($error) ?></div>
                <?php elseif ($message): ?>
                    <div class="card-panel green lighten-2 white-text"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form class="col s12" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="input-field col s12">
                            <input type="text" name="name" id="name" required>
                            <label for="name">Название достижения</label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="input-field col s12">
                            <textarea name="description" id="description" class="materialize-textarea" required></textarea>
                            <label for="description">Описание</label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="input-field col s6">
                            <input type="number" name="coefficient" id="coefficient" step="0.01" value="1.0" required>
                            <label for="coefficient">Коэффициент</label>
                        </div>

                        <div class="input-field col s6">
                            <select name="type" required>
                                <option value="" disabled selected>Выберите тип</option>
                                <option value="platform">Выдаётся платформой</option>
                                <option value="community">Народная</option>
                                <option value="other">Выдаётся другой организацией</option>
                            </select>
                            <label>Тип достижения</label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="file-field input-field col s12">
                            <div class="btn">
                                <span>Иконка</span>
                                <input type="file" name="icon" accept="image/*">
                            </div>
                            <div class="file-path-wrapper">
                                <input class="file-path validate" type="text" placeholder="Выберите иконку достижения">
                            </div>
                        </div>
                        <div class="preview-container" id="icon-preview">
                        </div>
                        <script>
                            $('input[name="icon"]').change(function() {
                                const file = this.files[0];
                                if (file) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        $('#icon-preview').html('<img src="' + e.target.result + '" class="preview-image">');
                                    }
                                    reader.readAsDataURL(file);
                                }
                            });
                        </script>
                    </div>

                    <div class="center-align">
                        <button class="btn green" type="submit">Создать достижение</button>
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
            $('select').material_select();
            $('.button-collapse').sideNav({
                menuWidth: 300,
                edge: 'left',
                closeOnClick: false,
                draggable: true
            });
        });
    </script>

</body>

</html>