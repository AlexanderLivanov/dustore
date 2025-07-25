<?php
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');
require_once('../swad/controllers/organization.php');

$db = new Database();
$curr_user = new User();
?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Dustore.Devs - Редактировать проект</title>
  <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
  <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
  <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
  <style>
    .preview-container {
      margin-top: 15px;
      max-width: 300px;
      border: 1px dashed #ddd;
      padding: 10px;
      border-radius: 4px;
    }

    .cover-preview {
      max-width: 100%;
      max-height: 200px;
    }

    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
    }

    .alert-success {
      background-color: #dff0d8;
      border-color: #d6e9c6;
      color: #3c763d;
    }

    .alert-error {
      background-color: #f2dede;
      border-color: #ebccd1;
      color: #a94442;
    }
  </style>
</head>

<body>
  <?php require_once('../swad/static/elements/sidebar.php');
  $org_info = $curr_user->getOrgData($_SESSION['studio_id']);

  // Проверка прав пользователя
  if ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] < 2) {
    echo ("<script>alert('У вас нет прав на использование этой функции');</script>");
    exit();
  }

  // Получаем ID проекта из URL
  $project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  // Получаем информацию о проекте
  $project_info = [];
  if ($project_id > 0) {
    $stmt = $db->connect()->prepare("SELECT * FROM games WHERE id = ? AND developer = ?");
    $stmt->execute([$project_id, $_SESSION['STUDIODATA']['id']]);
    $project_info = $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Если проект не найден
  if (empty($project_info)) {
    echo ("<script>window.location.replace('projects');</script>");
    exit();
  }

  // Обработка формы редактирования
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = preg_replace("/[^A-Za-zА-Яа-яёЁ0-9-_! ]/", '', $_POST['project-name']);
    $genre = $_POST['genre'];
    $description = $_POST['description'];
    $platforms = implode(',', $_POST['platform'] ?? []);
    $release_date = $_POST['release-date'];
    $game_website = $_POST['website'];

    $cover_path = $project_info['path_to_cover'];
    if (!empty($_FILES['cover-art']['name']) && $_FILES['cover-art']['error'] == UPLOAD_ERR_OK) {
      $upload_dir = "../swad/usercontent/{$org_info['name']}/{$project_name}/";

      if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) {
          error_log("Failed to create directory: $upload_dir");
          die("Ошибка создания директории для обложки");
        }
        chown($upload_dir, 'www-data');
        chgrp($upload_dir, 'www-data');
      }

      $existing_covers = glob(ROOT_DIR . $cover_path . "cover.*");
      foreach ($existing_covers as $old_cover) {
        if (file_exists($old_cover)) {
          unlink($old_cover);
        }
      }

      $file_extension = strtolower(pathinfo($_FILES['cover-art']['name'], PATHINFO_EXTENSION));

      $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      if (!in_array($file_extension, $allowed_extensions)) {
        die("Неподдерживаемый формат изображения: $file_extension");
      }

      $cover_filename = "cover." . $file_extension;
      $cover_path = "/swad/usercontent/{$org_info['name']}/{$project_name}/{$cover_filename}";
      $full_path = ROOT_DIR . $cover_path;

      if (!move_uploaded_file($_FILES['cover-art']['tmp_name'], $full_path)) {
        error_log("Failed to move uploaded file to: $full_path");
        die("Ошибка сохранения обложки");
      }

      chmod($full_path, 0664);
      chown($full_path, 'www-data');
      chgrp($full_path, 'www-data');
    }

    $sql = "UPDATE games SET 
          name = :name, 
          genre = :genre, 
          description = :description, 
          platforms = :platforms, 
          release_date = :release_date, 
          path_to_cover = :cover_path, 
          game_website = :website 
          WHERE id = :id";

    try {
      $stmt = $db->connect()->prepare($sql);
      $stmt->bindParam(':name', $project_name);
      $stmt->bindParam(':genre', $genre);
      $stmt->bindParam(':description', $description);
      $stmt->bindParam(':platforms', $platforms);
      $stmt->bindParam(':release_date', $release_date);
      $stmt->bindParam(':cover_path', $cover_path);
      $stmt->bindParam(':website', $game_website);
      $stmt->bindParam(':id', $project_id);
      $stmt->execute();

      echo ("<script>window.location.replace('edit?id=" . $project_id . "');</script>");
      exit();
    } catch (PDOException $e) {
      $error_message = "Ошибка при обновлении проекта: " . $e->getMessage();
    }
  }
  ?>
  <main>
    <section class="content">
      <div class="page-announce valign-wrapper">
        <a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only">
          <i class="material-icons">menu</i>
        </a>
        <h1 class="page-announce-text valign">// Редактирование проекта</h1>
      </div>

      <div class="container">
        <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success">
            Проект успешно обновлен!
          </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
          <div class="alert alert-error">
            <?= $error_message ?>
          </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <div class="row">
            <div class="col s12 m6">
              <div class="input-field">
                <input type="text" name="project-name" id="project-name"
                  value="<?= htmlspecialchars($project_info['name']) ?>" required>
                <label for="project-name">Название проекта</label>
              </div>

              <div class="input-field">
                <select name="genre" required>
                  <option value="" disabled>Выберите жанр</option>
                  <option value="action" <?= $project_info['genre'] == 'action' ? 'selected' : '' ?>>Экшен</option>
                  <option value="rpg" <?= $project_info['genre'] == 'rpg' ? 'selected' : '' ?>>RPG</option>
                  <option value="strategy" <?= $project_info['genre'] == 'strategy' ? 'selected' : '' ?>>Стратегия</option>
                  <option value="adventure" <?= $project_info['genre'] == 'adventure' ? 'selected' : '' ?>>Приключение</option>
                  <option value="simulator" <?= $project_info['genre'] == 'simulator' ? 'selected' : '' ?>>Симулятор</option>
                  <option value="visnovel" <?= $project_info['genre'] == 'visnovel' ? 'selected' : '' ?>>Визуальная новелла</option>
                  <option value="indie" <?= $project_info['genre'] == 'indie' ? 'selected' : '' ?>>Инди</option>
                  <option value="other" <?= $project_info['genre'] == 'other' ? 'selected' : '' ?>>Другое</option>
                </select>
                <label>Жанр</label>
              </div>

              <label for="release-date">Дата выхода</label>
              <div class="input-field">
                <input type="date" name="release-date" value="<?= $project_info['release_date'] ?>" placeholder="Дата выхода" required>
              </div>

              <div class="input-field">
                <input type="url" name="website"
                  value="<?= htmlspecialchars($project_info['game_website']) ?>" required>
                <label>Вебсайт проекта</label>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="file-field input-field">
                <div class="btn">
                  <span>Обложка</span>
                  <input type="file" name="cover-art" accept="image/*">
                </div>
                <div class="file-path-wrapper">
                  <input class="file-path" type="text">
                </div>
              </div>

              <div class="preview-container">
                <?php if (!empty($project_info['path_to_cover'])): ?>
                  <img src="<?= $project_info['path_to_cover'] ?>" class="cover-preview">
                <?php else: ?>
                  <p>Текущая обложка не загружена</p>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col s12">
              <div class="input-field">
                <textarea name="description" class="materialize-textarea" required><?= htmlspecialchars($project_info['description']) ?></textarea>
                <label>Описание проекта</label>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col s12">
              <h5>Платформы</h5>
              <?php
              $platforms = explode(',', $project_info['platforms']);
              $platform_options = [
                'windows' => 'Windows',
                'linux' => 'Linux',
                'macos' => 'MacOS',
                'android' => 'Android',
                'web' => 'Web'
              ];

              foreach ($platform_options as $value => $label): ?>
                <p>
                  <input type="checkbox" id="platform_<?= $value ?>" name="platform[]"
                    value="<?= $value ?>" <?= in_array($value, $platforms) ? 'checked' : '' ?>>
                  <label for="platform_<?= $value ?>"><?= $label ?></label>
                </p>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="row">
            <div class="col s12 center-align">
              <button class="btn waves-effect waves-light" type="submit">
                <i class="material-icons left">save</i> Сохранить изменения
              </button>
              <a href="project.php?id=<?= $project_id ?>" class="btn waves-effect grey">
                <i class="material-icons left">cancel</i> Отмена
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
    $(document).ready(function() {
      $('.datepicker').pickadate({
        selectMonths: true,
        selectYears: 15,
        format: 'yyyy-mm-dd'
      });

      $('select').material_select();

      // Предпросмотр обложки
      $('input[type="file"]').change(function(e) {
        if (this.files && this.files[0]) {
          var reader = new FileReader();
          reader.onload = function(e) {
            $('.preview-container').html('<img src="' + e.target.result + '" class="cover-preview">');
          }
          reader.readAsDataURL(this.files[0]);
        }
      });
    });
  </script>
</body>

</html>