<?php
require_once('../swad/static/elements/sidebar.php');
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');
require_once('../swad/controllers/organization.php');
require_once('../swad/controllers/s3.php');

$db = new Database();
$curr_user = new User();
$curr_user->checkAuth();

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
  // Обработка основных полей
  $project_name = preg_replace("/[^A-Za-zА-Яа-яёЁ0-9-_! ]/", '', $_POST['project-name']);
  $genre = $_POST['genre'];
  $description = $_POST['description'];
  $platforms = implode(',', $_POST['platform'] ?? []);
  $release_date = $_POST['release-date'];
  $game_website = $_POST['website'];
  $trailer_url = $_POST['trailer'];
  $rating_count = (int)$_POST['rating_count'];
  $languages = $_POST['languages'];
  $age_rating = $_POST['age_rating'];
  $price = (float)$_POST['price'];
  $in_subscription = isset($_POST['in_subscription']) ? 1 : 0;
  $game_exec = $_POST['game-exec'];

  $s3Uploader = new S3Uploader();

  // Обработка особенностей
  $features = [];
  if (isset($_POST['feature_icon'])) {
    for ($i = 0; $i < count($_POST['feature_icon']); $i++) {
      if (!empty($_POST['feature_title'][$i])) {
        $features[] = [
          'icon' => $_POST['feature_icon'][$i],
          'title' => $_POST['feature_title'][$i],
          'description' => $_POST['feature_description'][$i]
        ];
      }
    }
  }
  $features_json = json_encode($features);

  // Обработка системных требований
  $requirements = [];
  if (isset($_POST['req_label'])) {
    for ($i = 0; $i < count($_POST['req_label']); $i++) {
      if (!empty($_POST['req_value'][$i])) {
        $requirements[] = [
          'label' => $_POST['req_label'][$i],
          'value' => $_POST['req_value'][$i]
        ];
      }
    }
  }
  $requirements_json = json_encode($requirements);

  // Обработка изображений
  $cover_path = $project_info['path_to_cover'];
  $banner_url = $project_info['banner_url'];
  $screenshots = json_decode($project_info['screenshots'] ?? '[]', true) ?: [];

  // Функция для обработки загрузки изображений
  function handleImageUpload($file, $name, $project_name, $org_name, $existing_path, $type)
  {
    global $s3Uploader, $project_info;

    if (!empty($file['name']) && $file['error'] == UPLOAD_ERR_OK) {
      // Проверка MIME-типа
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime_type = finfo_file($finfo, $file['tmp_name']);
      finfo_close($finfo);

      $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!in_array($mime_type, $allowed_mime)) {
        error_log("Invalid file type: {$file['name']} | MIME: $mime_type");
        return $existing_path;
      }

      // Удаляем старое изображение из S3
      if (!empty($existing_path) && strpos($existing_path, 'amazonaws.com') !== false) {
        $s3Uploader->deleteFile($existing_path);
      }

      // Генерируем уникальное имя файла
      $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
      $safe_org_name = preg_replace('/[^a-z0-9]/i', '-', $org_name);
      $safe_project_name = preg_replace('/[^a-z0-9]/i', '-', $project_name);
      $s3_path = "{$safe_org_name}/{$safe_project_name}/{$type}-" . uniqid() . ".{$file_extension}";

      // Загружаем в S3
      if ($new_url = $s3Uploader->uploadFile($file['tmp_name'], $s3_path)) {
        return $new_url;
      }
    }
    return $existing_path;
  }

  $org_info = $curr_user->getOrgData($_SESSION['studio_id']);
  $org_name = $org_info['name'];

  // Обработка обложки
  $cover_path = handleImageUpload($_FILES['cover-art'], 'cover', $project_name, $org_name, $cover_path, 'cover');

  // Обработка баннера
  $banner_url = handleImageUpload($_FILES['banner'], 'banner', $project_name, $org_name, $banner_url, 'banner');

  // Обработка скриншотов
  $new_screenshots = [];

  // Сохраняем существующие скриншоты
  foreach ($screenshots as $screenshot) {
    if (isset($_POST['existing_screenshot'][$screenshot['id']])) {
      $new_screenshots[] = $screenshot;
    } elseif (strpos($screenshot['path'], 'amazonaws.com') !== false) {
      // Удаляем удаленные скриншоты из S3
      $s3Uploader->deleteFile($screenshot['path']);
    }
  }

  // Добавляем новые скриншоты
  if (!empty($_FILES['screenshots']['name'][0])) {
    foreach ($_FILES['screenshots']['tmp_name'] as $index => $tmp_name) {
      if ($_FILES['screenshots']['error'][$index] == UPLOAD_ERR_OK) {
        $file = [
          'name' => $_FILES['screenshots']['name'][$index],
          'type' => $_FILES['screenshots']['type'][$index],
          'tmp_name' => $tmp_name,
          'error' => $_FILES['screenshots']['error'][$index],
          'size' => $_FILES['screenshots']['size'][$index]
        ];

        $screenshot_id = uniqid();
        $screenshot_path = handleImageUpload($file, "screenshot_{$screenshot_id}", $project_name, $org_name, '', "screenshot");

        if ($screenshot_path) {
          $new_screenshots[] = [
            'id' => $screenshot_id,
            'path' => $screenshot_path
          ];
        }
      }
    }
  }
  $screenshots_json = json_encode($new_screenshots);

  // Обновление данных в базе
  $sql = "UPDATE games SET 
            name = :name,
            game_exec = :game_exec,
            genre = :genre, 
            description = :description, 
            platforms = :platforms, 
            release_date = :release_date, 
            path_to_cover = :cover_path, 
            game_website = :website,
            banner_url = :banner_url,
            trailer_url = :trailer_url,
            rating_count = :rating_count,
            features = :features,
            screenshots = :screenshots,
            requirements = :requirements,
            languages = :languages,
            age_rating = :age_rating,
            price = :price,
            in_subscription = :in_subscription
            WHERE id = :id";

  try {
    $stmt = $db->connect()->prepare($sql);
    $stmt->bindParam(':name', $project_name);
    $stmt->bindParam(':game_exec', $game_exec);
    $stmt->bindParam(':genre', $genre);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':platforms', $platforms);
    $stmt->bindParam(':release_date', $release_date);
    $stmt->bindParam(':cover_path', $cover_path);
    $stmt->bindParam(':website', $game_website);
    $stmt->bindParam(':banner_url', $banner_url);
    $stmt->bindParam(':trailer_url', $trailer_url);
    $stmt->bindParam(':rating_count', $rating_count);
    $stmt->bindParam(':features', $features_json);
    $stmt->bindParam(':screenshots', $screenshots_json);
    $stmt->bindParam(':requirements', $requirements_json);
    $stmt->bindParam(':languages', $languages);
    $stmt->bindParam(':age_rating', $age_rating);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':in_subscription', $in_subscription);
    $stmt->bindParam(':id', $project_id);
    $stmt->execute();

    echo ("<script>window.location.replace('edit?id=" . $project_id . "&success=1');</script>");
    exit();
  } catch (PDOException $e) {
    $error_message = "Ошибка при обновлении проекта: " . $e->getMessage();
  }
}
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
  <link rel="shortcut icon" href="../swad/static/img/DD.svg" type="image/x-icon">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
  <style>
    .preview-container {
      margin-top: 15px;
      max-width: 300px;
      border: 1px dashed #ddd;
      padding: 10px;
      border-radius: 4px;
      position: relative;
    }

    .preview-image {
      max-width: 100%;
      max-height: 200px;
      display: block;
      margin: 0 auto;
    }

    .screenshots-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 15px;
    }

    .screenshot-preview {
      width: 100px;
      height: 100px;
      background-size: cover;
      background-position: center;
      position: relative;
      border: 1px solid #ddd;
      border-radius: 4px;
    }

    .delete-screenshot {
      position: absolute;
      top: 5px;
      right: 5px;
      background: rgba(0, 0, 0, 0.5);
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .dynamic-section {
      margin-bottom: 20px;
      padding: 15px;
      border: 1px solid #eee;
      border-radius: 4px;
      position: relative;
    }

    .remove-section {
      position: absolute;
      top: 5px;
      right: 5px;
      cursor: pointer;
      color: #f44336;
    }

    .add-section-btn {
      margin-top: 10px;
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
                <input type="text" name="game-exec" id="game-exec"
                  value="<?= htmlspecialchars($project_info['game_exec']) ?>" required>
                <label for="game-exec">Название исполняемого файла, например game.exe</label>
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

              <div class="input-field">
                <input type="url" name="trailer"
                  value="<?= htmlspecialchars($project_info['trailer_url']) ?>">
                <label>Ссылка на трейлер</label>
              </div>

              <div class="input-field">
                <input type="text" name="languages"
                  value="<?= htmlspecialchars($project_info['languages']) ?>">
                <label>Языки (через запятую)</label>
              </div>

              <div class="input-field">
                <input type="text" name="age_rating"
                  value="<?= htmlspecialchars($project_info['age_rating']) ?>">
                <label>Возрастной рейтинг</label>
              </div>

              <div class="input-field">
                <input type="number" name="price" step="0.01"
                  value="<?= htmlspecialchars($project_info['price']) ?>" min="0">
                <label>Цена (₽)</label>
              </div>

              <p>
                <input type="checkbox" name="in_subscription" id="in_subscription"
                  <?= $project_info['in_subscription'] ? 'checked' : '' ?>>
                <label for="in_subscription">Доступен по подписке</label>
              </p>
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
                  <img src="<?= $project_info['path_to_cover'] ?>" class="preview-image">
                <?php else: ?>
                  <p>Текущая обложка не загружена</p>
                <?php endif; ?>
              </div>

              <div class="file-field input-field">
                <div class="btn">
                  <span>Баннер</span>
                  <input type="file" name="banner" accept="image/*">
                </div>
                <div class="file-path-wrapper">
                  <input class="file-path" type="text">
                </div>
              </div>
              <div class="preview-container">
                <?php if (!empty($project_info['banner_url'])): ?>
                  <img src="<?= $project_info['banner_url'] ?>" class="preview-image">
                <?php else: ?>
                  <p>Текущий баннер не загружен</p>
                <?php endif; ?>
              </div>

              <div class="file-field input-field">
                <div class="btn">
                  <span>Скриншоты</span>
                  <input type="file" name="screenshots[]" multiple accept="image/*">
                </div>
                <div class="file-path-wrapper">
                  <input class="file-path" type="text">
                </div>
              </div>
              <div class="screenshots-preview" id="screenshots-preview">
                <?php
                $screenshots = json_decode($project_info['screenshots'] ?? '[]', true) ?: [];
                foreach ($screenshots as $screenshot): ?>
                  <div class="screenshot-preview" style="background-image: url('<?= $screenshot['path'] ?>')">
                    <input type="hidden" name="existing_screenshot[<?= $screenshot['id'] ?>]" value="1">
                    <div class="delete-screenshot" onclick="deleteScreenshot(this)">×</div>
                  </div>
                <?php endforeach; ?>
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
            <div class="col s12">
              <h5>Особенности игры</h5>
              <div id="features-container">
                <?php
                $features = json_decode($project_info['features'] ?? '[]', true) ?: [];
                foreach ($features as $index => $feature): ?>
                  <div class="dynamic-section">
                    <span class="remove-section" onclick="removeSection(this)">×</span>
                    <div class="input-field">
                      <input type="text" name="feature_icon[]" value="<?= htmlspecialchars($feature['icon']) ?>" required>
                      <label>Иконка (эмодзи)</label>
                    </div>
                    <div class="input-field">
                      <input type="text" name="feature_title[]" value="<?= htmlspecialchars($feature['title']) ?>" required>
                      <label>Заголовок</label>
                    </div>
                    <div class="input-field">
                      <textarea name="feature_description[]" class="materialize-textarea"><?= htmlspecialchars($feature['description']) ?></textarea>
                      <label>Описание</label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="btn add-section-btn" onclick="addFeature()">Добавить особенность</button>
            </div>
          </div>

          <div class="row">
            <div class="col s12">
              <h5>Системные требования</h5>
              <div id="requirements-container">
                <?php
                $requirements = json_decode($project_info['requirements'] ?? '[]', true) ?: [];
                foreach ($requirements as $index => $requirement): ?>
                  <div class="dynamic-section">
                    <span class="remove-section" onclick="removeSection(this)">×</span>
                    <div class="input-field">
                      <input type="text" name="req_label[]" value="<?= htmlspecialchars($requirement['label']) ?>" required>
                      <label>Название требования</label>
                    </div>
                    <div class="input-field">
                      <input type="text" name="req_value[]" value="<?= htmlspecialchars($requirement['value']) ?>" required>
                      <label>Значение</label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="btn add-section-btn" onclick="addRequirement()">Добавить требование</button>
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
      $('select').material_select();

      // Предпросмотр изображений
      $('input[type="file"]').change(function(e) {
        const input = this;
        const container = $(input).closest('.file-field').next('.preview-container');

        if (input.files && input.files[0]) {
          const reader = new FileReader();
          reader.onload = function(e) {
            container.html('<img src="' + e.target.result + '" class="preview-image">');
          }
          reader.readAsDataURL(input.files[0]);
        }
      });
    });

    // Управление скриншотами
    function deleteScreenshot(element) {
      $(element).closest('.screenshot-preview').remove();
    }

    // Управление динамическими секциями
    function removeSection(element) {
      $(element).closest('.dynamic-section').remove();
    }

    function addFeature() {
      const container = $('#features-container');
      const newFeature = $(`
        <div class="dynamic-section">
          <span class="remove-section" onclick="removeSection(this)">×</span>
          <div class="input-field">
            <input type="text" name="feature_icon[]" required>
            <label>Иконка (эмодзи)</label>
          </div>
          <div class="input-field">
            <input type="text" name="feature_title[]" required>
            <label>Заголовок</label>
          </div>
          <div class="input-field">
            <textarea name="feature_description[]" class="materialize-textarea"></textarea>
            <label>Описание</label>
          </div>
        </div>
      `);
      container.append(newFeature);
      $('textarea').trigger('autoresize');
    }

    function addRequirement() {
      const container = $('#requirements-container');
      const newRequirement = $(`
        <div class="dynamic-section">
          <span class="remove-section" onclick="removeSection(this)">×</span>
          <div class="input-field">
            <input type="text" name="req_label[]" required>
            <label>Название требования</label>
          </div>
          <div class="input-field">
            <input type="text" name="req_value[]" required>
            <label>Значение</label>
          </div>
        </div>
      `);
      container.append(newRequirement);
    }
  </script>
</body>

</html>