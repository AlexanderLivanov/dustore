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
  if ($_SESSION['USERDATA']['global_role']!= -1 && $_SESSION['USERDATA']['global_role'] < 2) {
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

    // Расчет GQI (Game Quality Index)
    $gqi = 0;
    $filled_fields = 0;
    $total_fields = 8; // Всего полей в форме

    if (!empty($project_name)) $filled_fields++;
    if (!empty($genre)) $filled_fields++;
    if (!empty($description)) $filled_fields++;
    if (!empty($_POST['platform'])) $filled_fields++;
    if (!empty($release_date)) $filled_fields++;
    if (!empty($game_website)) $filled_fields++;
    if (!empty($_FILES['cover-art']['name'])) $filled_fields++;

    $gqi = ($filled_fields / $total_fields) * 100;

    // Обработка загрузки обложки
    $cover_path = '';
    if (!empty($_FILES['cover-art']['name'])) {
      $upload_dir = ROOT_DIR . "/swad/usercontent/{$studio_name}/{$project_name}/";

      // Создаем директории, если не существуют
      if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
      }

      $file_extension = pathinfo($_FILES['cover-art']['name'], PATHINFO_EXTENSION);
      $cover_filename = "cover.jpg";
      $cover_path = "/swad/usercontent/{$studio_name}/{$project_name}/{$cover_filename}";
      $full_path = ROOT_DIR . $cover_path;

      // Перемещаем загруженный файл
      move_uploaded_file($_FILES['cover-art']['tmp_name'], $full_path);
    }

    // Создание записи в базе данных с использованием PDO
    $sql = "INSERT INTO games (badge, developer, publisher, name, genre, description, platforms, release_date, path_to_cover, game_website, status, GQI, rating_boost) 
            VALUES (0, :developer, :publisher, :name, :genre, :description, :platforms, :release_date, :cover_path, :website, 'draft', :gqi, 0)";

    try {
      $stmt = $db->connect()->prepare($sql);

      $stmt->bindParam(':developer', $studio_id, PDO::PARAM_INT);
      $stmt->bindParam(':publisher', $studio_id, PDO::PARAM_INT);
      $stmt->bindParam(':name', $project_name, PDO::PARAM_STR);
      $stmt->bindParam(':genre', $genre, PDO::PARAM_STR);
      $stmt->bindParam(':description', $description, PDO::PARAM_STR);
      $stmt->bindParam(':platforms', $platforms, PDO::PARAM_STR);
      $stmt->bindParam(':release_date', $release_date, PDO::PARAM_STR);
      $stmt->bindParam(':cover_path', $cover_path, PDO::PARAM_STR);
      $stmt->bindParam(':website', $game_website, PDO::PARAM_STR);
      $stmt->bindParam(':gqi', $gqi, PDO::PARAM_INT);

      $stmt->execute();

      $project_id = $db->connect()->lastInsertId();
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
                <td colspan="2">
                  <div class="form-footer">
                    <button class="btn btn-large waves-effect waves-light" type="submit">
                      <i class="material-icons left">create</i> Создать черновик
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
      <div class="gqi-fixed-container">
        <div class="container">
          <div class="gqi-wrapper">
            <span class="gqi-label">Индекс качества: <span id="gqi-value">0</span></span>
            <div class="progress">
              <div class="determinate" id="gqi-progress" style="width: 0%"></div>
            </div>
          </div>
        </div>
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
      updateGQI();
    });
  </script>
  <script>
    const fieldWeights = {
      'badge': 10,
      'project-name': 20,
      'genre': 15,
      'description': 10,
      'platform[]': 15,
      'release-date': 10,
      'cover-art': 15,
      'website': 15
    };

    function updateGQI() {
      let newGQI = 0;

      Object.keys(fieldWeights).forEach(fieldId => {
        const field = document.querySelector(`[name="${fieldId}"]`);
        if (!field) {
          console.error(`Field ${fieldId} not found!`);
          return;
        }

        let isFilled = false;

        switch (field.type) {
          case 'checkbox':
            isFilled = document.querySelectorAll(`[name="${fieldId}"]:checked`).length > 0;
            break;
          case 'file':
            isFilled = !!field.files.length;
            break;
          case 'select-one':
            isFilled = field.value !== '';
            break;
          default:
            isFilled = field.value ? field.value.trim() !== '' : false;
        }

        if (isFilled) newGQI += fieldWeights[fieldId];
      });

      // Обновление интерфейса
      totalGQI = Math.min(newGQI, 100);
      document.getElementById('gqi-value').textContent = `${totalGQI}%`;
      document.getElementById('gqi-progress').style.width = `${totalGQI}%`;

      const progressBar = document.getElementById('gqi-progress');
      progressBar.style.backgroundColor =
        totalGQI >= 80 ? '#4CAF50' :
        totalGQI >= 50 ? '#FFC107' :
        '#F44336';
    }

    // Инициализация слушателей
    document.querySelectorAll('#game-project input, #game-project select, #game-project textarea').forEach(element => {
      if (element) {
        element.addEventListener('input', updateGQI);
        element.addEventListener('change', updateGQI);
      }
    });

    // Задержка инициализации для полной загрузки DOM
    setTimeout(updateGQI, 100);
  </script>
</body>

</html>