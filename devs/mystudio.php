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
  <title>Dustore.Devs - Управление студией</title>
  <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
  <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
  <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
  <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
  <style>
    .preview-container {
      margin-top: 15px;
      max-width: 300px;
      border: 1px dashed #ddd;
      padding: 10px;
      border-radius: 4px;
      text-align: center;
    }

    .preview-image {
      max-width: 100%;
      max-height: 200px;
      margin-bottom: 10px;
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

    .row .col.s12.m6 {
      margin-bottom: 20px;
    }

    .token-warning {
      background-color: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 12px;
      margin: 10px 0;
      border-radius: 4px;
    }
  </style>
  <!-- <script src="assets/js/tinymce.min.js"></script> -->
</head>

<body>
  <!-- <script>
    tinymce.init({
      selector: 'textarea',
      plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
      toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
    });
  </script> -->
  <?php
  require_once('../swad/static/elements/sidebar.php');

  // Проверка прав пользователя
  $curr_user->checkAuth();

  // Получаем информацию о студии
  $studio_id = $_SESSION['studio_id'];
  $stmt = $db->connect()->prepare("SELECT * FROM studios WHERE id = ?");
  $stmt->execute([$studio_id]);
  $studio_info = $stmt->fetch(PDO::FETCH_ASSOC);

  if (empty($studio_info)) {
    die("Студия не найдена");
  }

  // Обработка формы редактирования
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studio_name = preg_replace("/[^A-Za-zА-Яа-яёЁ0-9-_! ]/", '', $_POST['studio-name']);
    $description = $_POST['description'];
    $website = $_POST['website'];
    $avatar_link = filter_var($_POST['avatar_link'], FILTER_SANITIZE_URL);
    $banner_link = filter_var($_POST['banner_link'], FILTER_SANITIZE_URL);
    $vk_public_id = preg_replace("/[^0-9_]/", '', $_POST['vk_public_id']);
    $tg_studio_id = preg_replace("/[^0-9_-]/", '', $_POST['tg_studio_id']);
    $tiker = substr(preg_replace("/[^A-Za-z]/", '', $_POST['tiker']), 0, 10);
    $country = substr($_POST['country'], 0, 16);
    $city = substr($_POST['city'], 0, 32);
    $contact_email = filter_var($_POST['contact_email'], FILTER_SANITIZE_EMAIL);
    $foundation_date = $_POST['foundation_date'];
    $team_size = $_POST['team_size'];
    $specialization = $_POST['specialization'];
    $pre_alpha_program = isset($_POST['pre_alpha_program']) ? 1 : 0;

    // Обработка API токена
    $api_token = $_POST['api_token'] ?? '';
    $update_api_token = false;
    $hashed_token = '';

    if (!empty($api_token)) {
      $hashed_token = hash('sha256', $api_token);
      $update_api_token = true;
    }

    $sql = "UPDATE studios SET 
            name = :name, 
            description = :description, 
            website = :website, 
            avatar_link = :avatar_link,
            banner_link = :banner_link,
            vk_public_id = :vk_public_id,
            tg_studio_id = :tg_studio_id,
            tiker = :tiker,
            country = :country,
            city = :city,
            contact_email = :contact_email,
            foundation_date = :foundation_date,
            team_size = :team_size,
            specialization = :specialization,
            pre_alpha_program = :pre_alpha_program";

    // Добавляем обновление токена, если он был введен
    if ($update_api_token) {
      $sql .= ", api_token = :api_token";
    }

    $sql .= " WHERE id = :id";

    try {
      $stmt = $db->connect()->prepare($sql);
      $stmt->bindParam(':name', $studio_name);
      $stmt->bindParam(':description', $description);
      $stmt->bindParam(':website', $website);
      $stmt->bindParam(':avatar_link', $avatar_link);
      $stmt->bindParam(':banner_link', $banner_link);
      $stmt->bindParam(':vk_public_id', $vk_public_id);
      $stmt->bindParam(':tg_studio_id', $tg_studio_id);
      $stmt->bindParam(':tiker', $tiker);
      $stmt->bindParam(':country', $country);
      $stmt->bindParam(':city', $city);
      $stmt->bindParam(':contact_email', $contact_email);
      $stmt->bindParam(':foundation_date', $foundation_date);
      $stmt->bindParam(':team_size', $team_size);
      $stmt->bindParam(':specialization', $specialization);
      $stmt->bindParam(':pre_alpha_program', $pre_alpha_program);

      // Привязываем хешированный токен, если он был введен
      if ($update_api_token) {
        $stmt->bindParam(':api_token', $hashed_token);
      }

      $stmt->bindParam(':id', $studio_id);
      $stmt->execute();

      echo ("<script>window.location.replace('mystudio?success=1');</script>");
      exit();
    } catch (PDOException $e) {
      $error_message = "Ошибка при обновлении студии: " . $e->getMessage();
    }
  }
  ?>
  <main>
    <section class="content">
      <div class="page-announce valign-wrapper">
        <a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only">
          <i class="material-icons">menu</i>
        </a>
        <h1 class="page-announce-text valign">// Редактирование студии</h1>
      </div>

      <div class="container">
        <h5 style="text-align: center; border: 2px dotted coral;">Ознакомьтесь с правилами создания профиля студии: <a href="https://github.com/AlexanderLivanov/dustore-docs/wiki/Создание-профиля-студии" target="_blank">*КЛАЦ*</a></h5>
        <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success">
            Информация о студии успешно обновлена!
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
                <input type="text" name="studio-name" id="studio-name" minlength="4" maxlength="32"
                  value="<?= htmlspecialchars($studio_info['name']) ?>" required>
                <label for="studio-name">Название студии</label>
              </div>

              <div class="input-field">
                <input type="text" name="tiker" id="tiker"
                  value="<?= htmlspecialchars($studio_info['tiker']) ?>" minlength="4" maxlength="5">
                <label for="tiker">Тикер (краткое название)</label>
              </div>

              <div class="input-field">
                <input type="url" name="website"
                  value="<?= htmlspecialchars($studio_info['website']) ?>" minlength="14" maxlength="32">
                <label for="website">Вебсайт студии</label>
              </div>

              <div class="input-field">
                <input type="text" name="vk_public_id"
                  value="<?= htmlspecialchars($studio_info['vk_public_id']) ?>" minlength="7" maxlength="16">
                <label for="vk_public_id">ID VK сообщества</label>
              </div>

              <div class="input-field">
                <input type="text" name="tg_studio_id"
                  value="<?= htmlspecialchars($studio_info['tg_studio_id']) ?>" minlength="7" maxlength="16">
                <label for="tg_studio_id">ID Telegram чата студии</label>
              </div>

              <div class="input-field">
                <input type="email" name="contact_email"
                  value="<?= htmlspecialchars($studio_info['contact_email']) ?>" minlength="6" maxlength="32">
                <label for="contact_email">Контактный Email</label>
              </div>

              <div class="input-field">
                <input type="text" name="country"
                  value="<?= htmlspecialchars($studio_info['country']) ?>" minlength="3" maxlength="16">
                <label for="country">Страна</label>
              </div>

              <div class="input-field">
                <input type="text" name="city" minlength="3" maxlength="16"
                  value="<?= htmlspecialchars($studio_info['city']) ?>">
                <label for="city">Город</label>
              </div>

              <label for="foundation_date">Дата основания</label>
              <div class="input-field">
                <input type="date" name="foundation_date"
                  value="<?= htmlspecialchars($studio_info['foundation_date']) ?>">
              </div>
            </div>

            <div class="col s12 m6">
              <div class="input-field">
                <input type="url" name="avatar_link" id="avatar_link"
                  value="<?= htmlspecialchars($studio_info['avatar_link']) ?>">
                <label for="avatar_link">Ссылка на аватар</label>
              </div>

              <div class="preview-container">
                <?php if (!empty($studio_info['avatar_link'])): ?>
                  <img src="<?= $studio_info['avatar_link'] ?>" class="preview-image" id="avatar_preview">
                <?php else: ?>
                  <p>Аватар не установлен</p>
                  <img src="" class="preview-image" id="avatar_preview" style="display:none;">
                <?php endif; ?>
              </div>

              <div class="input-field">
                <input type="url" name="banner_link" id="banner_link"
                  value="<?= htmlspecialchars($studio_info['banner_link']) ?>">
                <label for="banner_link">Ссылка на баннер (экспериментальная функция)</label>
              </div>

              <div class="preview-container">
                <?php if (!empty($studio_info['banner_link'])): ?>
                  <img src="<?= $studio_info['banner_link'] ?>" class="preview-image" id="banner_preview">
                <?php else: ?>
                  <p>Баннер не установлен</p>
                  <img src="" class="preview-image" id="banner_preview" style="display:none;">
                <?php endif; ?>
              </div>

              <div class="input-field">
                <select name="team_size">
                  <option value="" disabled>Размер команды</option>
                  <option value="1" <?= $studio_info['team_size'] == '1' ? 'selected' : '' ?>>1 человек</option>
                  <option value="2-5" <?= $studio_info['team_size'] == '2-5' ? 'selected' : '' ?>>2-5 человек</option>
                  <option value="6-10" <?= $studio_info['team_size'] == '6-10' ? 'selected' : '' ?>>6-10 человек</option>
                  <option value="11-20" <?= $studio_info['team_size'] == '11-20' ? 'selected' : '' ?>>11-20 человек</option>
                  <option value="20+" <?= $studio_info['team_size'] == '20+' ? 'selected' : '' ?>>20+ человек</option>
                </select>
                <label>Размер команды</label>
              </div>

              <div class="input-field">
                <select name="specialization">
                  <option value="" disabled>Специализация</option>
                  <option value="mobile" <?= $studio_info['specialization'] == 'mobile' ? 'selected' : '' ?>>Мобильные игры</option>
                  <option value="pc" <?= $studio_info['specialization'] == 'pc' ? 'selected' : '' ?>>ПК игры</option>
                  <option value="console" <?= $studio_info['specialization'] == 'console' ? 'selected' : '' ?>>Консольные игры</option>
                  <option value="vr" <?= $studio_info['specialization'] == 'vr' ? 'selected' : '' ?>>VR игры</option>
                  <option value="software" <?= $studio_info['specialization'] == 'software' ? 'selected' : '' ?>>ПО и утилиты</option>
                </select>
                <label>Специализация</label>
              </div>

              <!-- Поле для ввода API токена -->
              <div class="input-field">
                <input type="password" name="api_token" id="api_token"
                  placeholder="<?= !empty($studio_info['api_token']) ? 'Токен установлен. Введите новый токен для изменения' : 'Введите API токен' ?>">
                <label for="api_token">API Токен</label>
                <div class="token-warning">
                  <i class="material-icons left">warning</i>
                  <span>Перед сохранением обязательно сохраните токен в надежном месте, так как после сохранения он будет скрыт.</span>
                </div>
              </div>

              <p>
                <input type="checkbox" name="pre_alpha_program" id="pre_alpha_program"
                  <?= $studio_info['pre_alpha_program'] ? 'checked' : '' ?>>
                <label for="pre_alpha_program">Хочу участвовать в Программе Предварительной Оценки</label>
              </p>
            </div>
          </div>

          <div class="row">
            <div class="col s12">
              <label>Описание студии</label>

              <div class="input-field">
                <div id="editor"><?= $studio_info['description'] ?></div>
                <input type="hidden" name="description" id="description">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col s12 center-align">
              <button class="btn waves-effect waves-light" type="submit">
                <i class="material-icons left">save</i> Сохранить изменения
              </button>
              <a href="studio.php?id=<?= $studio_id ?>" class="btn waves-effect grey">
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

      // Функция для предпросмотра изображений
      function updatePreview(inputId, previewId) {
        const input = $(`#${inputId}`);
        const preview = $(`#${previewId}`);

        input.on('input', function() {
          const url = input.val().trim();

          if (url) {
            preview.attr('src', url);
            preview.show();
          } else {
            preview.hide();
          }
        });
      }

      // Инициализация предпросмотра
      updatePreview('avatar_link', 'avatar_preview');
      updatePreview('banner_link', 'banner_preview');
    });
  </script>
  <script>
    var quill = new Quill('#editor', {
      theme: 'snow'
    });

    // Копируем HTML в скрытое поле при отправке
    document.querySelector('form').onsubmit = function() {
      document.querySelector('#description').value = quill.root.innerHTML;
    };
  </script>
</body>

</html>