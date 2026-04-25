<?php
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');
require_once('../swad/controllers/organization.php');
require_once('../swad/controllers/s3.php');

$db = new Database();
$curr_user = new User();
?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Dustore.Devs - Создать новый проект</title>
  <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
  <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/newproject.css">
</head>

<body>
  <?php require_once('../swad/static/elements/sidebar.php');

  // Проверка прав пользователя
  if ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] < 2) {
    echo ("<script>alert('У вас нет прав на использование этой функции');</script>");
    exit();
  }

  // Получаем информацию о студии пользователя
  $studio_info = $_SESSION['STUDIODATA'];
  $studio_name = $studio_info['name'];
  $studio_id = $studio_info['id'];

  // Обработка отправки формы
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Сбор данных формы
    $project_name = $_POST['project-name'];
    $genre = $_POST['genre'];
    $description = $_POST['description'];
    $platforms = implode(',', $_POST['platform'] ?? []);
    $release_date = $_POST['release-date'];
    $game_website = $_POST['website'];
    $game_exec = $_POST['game-exec'];
    $trailer_url = $_POST['trailer'];
    $languages = $_POST['languages'];
    $age_rating = $_POST['age_rating'];

    $features = [];

    if (isset($_POST['feature_title'])) {
      for ($i = 0; $i < count($_POST['feature_title']); $i++) {
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

    $game_zip_url = '';
    $game_zip_size = 0;

    if (!empty($_FILES['game_zip']['name'])) {
      $s3Uploader = new S3Uploader();

      $zip_path = "games/" . uniqid() . ".zip";
      $uploaded = $s3Uploader->uploadFile($_FILES['game_zip']['tmp_name'], $zip_path);

      if ($uploaded) {
        $game_zip_url = $uploaded;
        $game_zip_size = $_FILES['game_zip']['size'];
      }
    }

    // Обработка загрузки обложки
    $cover_path = '';
    if (!empty($_FILES['cover-art']['name'])) {
      $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/swad/usercontent/{$studio_name}/{$project_name}/";

      if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
      }

      $cover_filename = "cover.jpg";

      $full_path = $upload_dir . $cover_filename;
      $cover_path = "/swad/usercontent/{$studio_name}/{$project_name}/{$cover_filename}";

      move_uploaded_file($_FILES['cover-art']['tmp_name'], $full_path);
    }

    $sql = "INSERT INTO games (
                              badge,
                              developer,
                              publisher,
                              name,
                              genre,
                              description,
                              platforms,
                              release_date,
                              path_to_cover,
                              banner_url,
                              trailer_url,
                              game_website,
                              features,
                              screenshots,
                              requirements,
                              languages,
                              age_rating,
                              game_exec,
                              game_zip_url,
                              game_zip_size,
                              moderation_status,
                              status,
                              rating_boost
                            ) VALUES (
                              0,
                              :developer,
                              :publisher,
                              :name,
                              :genre,
                              :description,
                              :platforms,
                              :release_date,
                              :cover_path,
                              :banner_url,
                              :trailer_url,
                              :website,
                              :features,
                              :screenshots,
                              :requirements,
                              :languages,
                              :age_rating,
                              :game_exec,
                              :game_zip_url,
                              :game_zip_size,
                              'pending',
                              'draft',
                              0
                            )";

    try {
      $stmt = $db->connect()->prepare($sql);
      $banner_url = null;
      $screenshots_json = json_encode([]);

      $stmt->bindParam(':developer', $studio_id, PDO::PARAM_INT);
      $stmt->bindParam(':publisher', $studio_id, PDO::PARAM_INT);
      $stmt->bindParam(':name', $project_name, PDO::PARAM_STR);
      $stmt->bindParam(':genre', $genre, PDO::PARAM_STR);
      $stmt->bindParam(':description', $description, PDO::PARAM_STR);
      $stmt->bindParam(':platforms', $platforms, PDO::PARAM_STR);
      $stmt->bindParam(':release_date', $release_date, PDO::PARAM_STR);
      $stmt->bindParam(':cover_path', $cover_path, PDO::PARAM_STR);
      $stmt->bindParam(':website', $game_website, PDO::PARAM_STR);
      $stmt->bindParam(':banner_url', $banner_url);
      $stmt->bindParam(':trailer_url', $trailer_url);
      $stmt->bindParam(':features', $features_json);
      $stmt->bindParam(':screenshots', $screenshots_json);
      $stmt->bindParam(':requirements', $requirements_json);
      $stmt->bindParam(':languages', $languages);
      $stmt->bindParam(':age_rating', $age_rating);
      $stmt->bindParam(':game_exec', $game_exec);
      $stmt->bindParam(':game_zip_url', $game_zip_url);
      $stmt->bindParam(':game_zip_size', $game_zip_size);
      $stmt->execute();

      $project_id = $db->connect()->lastInsertId();
      echo "<script>alert('Заявка отправлена на модерацию');</script>";
      echo ("<script>window.location.replace('projects')</script>");
      exit();
    } catch (PDOException $e) {
      $error_message = "Ошибка при создании проекта: " . $e->getMessage();
    }
  }
  ?>
  <main>
    <section class="content">
      <div class="page-announce valign-wrapper"><a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only"><i class="material-icons">menu</i></a>
        <h1 class="page-announce-text valign">// Создать новый проект</h1>
      </div>
      <div class="container">
        <?php if (isset($error_message)): ?>
          <div class="alert alert-error">
            <?= $error_message ?>
          </div>
        <?php endif; ?>

        <h3>Общая информация</h3>
        <p>Создайте черновик проекта для вашей новой игры. После создания вы сможете добавлять файлы, настраивать публикацию и управлять проектом.</p>
        <br>

        <form id="game-project" method="POST" enctype="multipart/form-data">
          <table class="table table-hover">
            <tbody>
              <tr>
                <td><label for="project-name">Название: </label></td>
                <td>
                  <input type="text" name="project-name" placeholder="Введите название" required maxlength="64" />
                  <div class="hint">Максимум 64 символа, только английские и русские буквы и знаки "!", "_", "-"</div>
                </td>
              </tr>
              <tr>
                <td><label for="genre">Жанр: </label></td>
                <td>
                  <select name="genre" class="browser-default" required>
                    <option value="" disabled selected>Выберите жанр</option>
                    <option value="action">Экшен</option>
                    <option value="rpg">RPG</option>
                    <option value="strategy">Стратегия</option>
                    <option value="adventure">Приключение</option>
                    <option value="simulator">Симулятор</option>
                    <option value="visnovel">Визуальная новелла</option>
                    <option value="indie">Инди</option>
                    <option value="other">Другое</option>
                  </select>
                </td>
              </tr>
              <tr>
                <td><label for="description">Описание: </label></td>
                <td>
                  <textarea name="description" class="materialize-textarea" placeholder="Введите описание (50-2000 символов)" minlength="50" maxlength="2000" required></textarea>
                  <div class="hint">Опишите вашу игру: сюжет, геймплей, особенности</div>
                </td>
              </tr>
              <tr>
                <td><label for="platform">Платформа: </label></td>
                <td>
                  <p>
                    <input type="checkbox" id="pc_windows" name="platform[]" value="windows" />
                    <label for="pc_windows">Windows</label>
                  </p>
                  <p>
                    <input type="checkbox" id="pc_linux" name="platform[]" value="linux" />
                    <label for="pc_linux">Linux</label>
                  </p>
                  <p>
                    <input type="checkbox" id="pc_macos" name="platform[]" value="macos" />
                    <label for="pc_macos">MacOS</label>
                  </p>
                  <p>
                    <input type="checkbox" id="android" name="platform[]" value="android" />
                    <label for="android">Android</label>
                  </p>
                  <p>
                    <input type="checkbox" id="web" name="platform[]" value="web" />
                    <label for="web">Web</label>
                  </p>
                </td>
              </tr>
              <tr>
                <td><label for="release-date">Дата выхода: </label></td>
                <td>
                  <input type="date" name="release-date" placeholder="Выберите дату выхода игры" required />
                </td>
              </tr>
              <tr>
                <td><label for="cover-art">Обложка: </label></td>
                <td>
                  <div class="file-field">
                    <div class="btn">
                      <span>Выбрать файл</span>
                      <input type="file" name="cover-art" accept="image/*" id="cover-input" />
                    </div>
                    <div class="file-path-wrapper">
                      <input class="file-path" type="text" placeholder="Загрузите обложку игры">
                    </div>
                  </div>
                  <div class="preview-container">
                    <img src="" alt="Предпросмотр обложки" class="cover-preview" id="cover-preview">
                    <p class="preview-text">Предпросмотр появится здесь</p>
                  </div>
                  <div class="hint">Рекомендуемый размер: 1200×630px, формат JPG/PNG</div>
                </td>
              </tr>
              <tr>
                <td><label for="website">Вебсайт игры: </label></td>
                <td>
                  <input type="url" name="website" placeholder="https://example.com" required />
                  <div class="hint">Это может быть страница в ВК, канал в Telegram или официальный сайт</div>
                </td>
              </tr>

              <tr>
                <td><label>Исполняемый файл:</label></td>
                <td>
                  <input type="text" name="game-exec" placeholder="game.exe" required>
                </td>
              </tr>

              <tr>
                <td><label>Трейлер:</label></td>
                <td>
                  <input type="url" name="trailer" placeholder="https://youtube.com/...">
                </td>
              </tr>

              <tr>
                <td><label>Языки:</label></td>
                <td>
                  <input type="text" name="languages" placeholder="Русский, English">
                </td>
              </tr>

              <tr>
                <td><label>Возрастной рейтинг:</label></td>
                <td>
                  <input type="number" name="age_rating" min="0" max="21">
                </td>
              </tr>

              <tr>
                <td><label>Баннер:</label></td>
                <td>
                  <input type="file" name="banner" accept="image/*">
                </td>
              </tr>

              <tr>
                <td><label>ZIP архив:</label></td>
                <td>
                  <input type="file" name="game_zip" accept=".zip" required>
                </td>
              </tr>

              <tr>
                <td><label>Скриншоты:</label></td>
                <td>
                  <input type="file" name="screenshots[]" multiple accept="image/*">
                </td>
              </tr>

              <tr>
                <td><label>Особенности:</label></td>
                <td>
                  <div id="features-container"></div>
                  <button type="button" onclick="addFeature()">Добавить</button>
                </td>
              </tr>

              <tr>
                <td><label>Системные требования:</label></td>
                <td>
                  <div id="requirements-container"></div>
                  <button type="button" onclick="addRequirement()">Добавить</button>
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <div class="form-footer">
                    <button class="btn btn-large waves-effect waves-light" type="submit">
                      <i class="material-icons left">create</i> Отправить на модерацию
                    </button>
                    <a href="dashboard.php" class="btn btn-large grey lighten-1 waves-effect">
                      <i class="material-icons left">cancel</i> Отмена
                    </a>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </form>
      </div>
    </section>
  </main>
  <?php require_once('footer.php'); ?>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/js/materialize.min.js"></script>
  <script>
    // Инициализация компонентов Materialize
    $(document).ready(function() {
      $('.datepicker').pickadate({
        selectMonths: true,
        selectYears: 15,
        format: 'yyyy-mm-dd',
        // Добавьте эти параметры:
        closeOnSelect: true,
        closeOnClear: false,
        onStart: function() {
          this.$node.removeAttr('type').attr('type', 'text');
        }
      });

      $('select').material_select();
      $('.tooltipped').tooltip({
        delay: 50
      });
    });

    // Инициализация бокового меню
    $('.button-collapse').sideNav({
      menuWidth: 300,
      edge: 'left',
      closeOnClick: false,
      draggable: true
    });

    // Предпросмотр обложки
    document.getElementById('cover-input').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
          const preview = document.getElementById('cover-preview');
          preview.src = event.target.result;
          preview.style.display = 'block';
          document.querySelector('.preview-text').style.display = 'none';
        };
        reader.readAsDataURL(file);
      }
    });

    function addFeature() {
      const container = document.getElementById('features-container');

      const block = document.createElement('div');
      block.innerHTML = `
        <input type="text" name="feature_icon[]" placeholder="🎮">
        <input type="text" name="feature_title[]" placeholder="Название">
        <textarea name="feature_description[]" placeholder="Описание"></textarea>
        <hr>
      `;

      container.appendChild(block);
    }

    function addRequirement() {
      const container = document.getElementById('requirements-container');

      const block = document.createElement('div');
      block.innerHTML = `
        <input type="text" name="req_label[]" placeholder="CPU">
        <input type="text" name="req_value[]" placeholder="Intel i5">
        <hr>
      `;

      container.appendChild(block);
    }
  </script>
</body>

</html>