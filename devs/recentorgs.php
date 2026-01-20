<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Control Panel</title>
  <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
  <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
</head>

<body>
  <?php
  require_once('../swad/static/elements/sidebar.php');
  require_once('../swad/config.php');
  require_once('../swad/controllers/tg_bot.php');

  $db = new Database();

  if ($_SESSION['USERDATA']['global_role'] != -1 && $_SESSION['USERDATA']['global_role'] < 3) {
    echo ("<script>alert('У вас нет прав на использование этой функции');</script>");
    exit();
  }

  if (isset($_GET['approve'])) {
    $org_id = (int)$_GET['approve'];
    $tg_id = $_GET['tg_id'];
    $org_name = $_GET['name'];

    $stmt = $db->connect()->prepare("UPDATE studios SET status = 'active' WHERE id = ?");
    $stmt->execute([$org_id]);

    $stmt = $db->connect()->prepare("UPDATE users SET global_role = 2 WHERE id = (SELECT owner_id FROM studios WHERE id = ?)");
    $stmt->execute([$org_id]);

    send_private_message($tg_id, "Ваша студия была подтверждена. Теперь вы можете создать свой первый проект!\n\nБлагодарим за регистрацию на нашей платформе! ❤");
    send_group_message(-1002916906978, "<i>❗ #подтверждение</i>\n@" . $_SESSION['USERDATA']['telegram_username'] . " (" . $curr_user->getRoleName($_SESSION['USERDATA']['global_role']) . ") подтвердил студию " . $org_name . " (" . $org_id . ").", false, "");
    echo ("<script>alert('Студия успешно подтверждена!'); window.location.href = 'recentorgs';</script>");
    exit();
  }

  if (isset($_GET['reject'])) {
    $org_id = (int)$_GET['reject'];
    $tg_id = $_GET['tg_id'];
    $org_name = $_GET['name'];
    $reason = isset($_GET['reject_reason']) ? trim($_GET['reject_reason']) : '';

    $stmt = $db->connect()->prepare("UPDATE studios SET status = 'suspended', ban_reason = ? WHERE id = ?");
    $stmt->execute([$reason, $org_id]);

    send_group_message(-1002916906978, "<i>❗ #отказ</i>\n@" . $_SESSION['USERDATA']['telegram_username'] . "(" . $curr_user->getRoleName($_SESSION['USERDATA']['global_role']) . ") отклонил студию " . $org_name . " (" . $org_id . ") по причине: " . $reason . ".", false, "");
    send_private_message($tg_id, "Ваша заявка на регистрацию студии была отклонена. Причина отклонения: <i>" . trim($_GET['reject_reason']) . "</i>. Вы можете изменить вашу заявку на странице https://dustore.ru/devs/regorg и отправить её заново!");
    echo ("<script>alert('Студия отклонена!'); window.location.href = 'recentorgs';</script>");
    exit();
  }
  ?>

  <main>
    <section class="content">
      <div class="page-announce valign-wrapper">
        <a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only">
          <i class="material-icons">menu</i>
        </a>
        <h1 class="page-announce-text valign">// Управление студиями</h1>
      </div>

      <div class="container">
        <!-- Вкладки -->
        <ul class="tabs">
          <li class="tab col s6"><a class="active" href="#pending">Новые студии</a></li>
          <li class="tab col s6"><a href="#active">Активные студии</a></li>
        </ul>
      </div>

      <!-- Таблица pending -->
      <div id="pending" class="container">
        <h5>Новые студии (ожидают подтверждения)</h5>
        <table class="responsive-table striped hover centered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Название</th>
              <th>Дата регистрации</th>
              <th>Владелец (telegram_id, @username)</th>
              <th>Ссылка VK</th>
              <th>Город</th>
              <th>Email</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $stmt = $db->connect()->prepare("
            SELECT s.*, u.telegram_id, u.telegram_username 
            FROM studios s
            JOIN users u ON s.owner_id = u.id
            WHERE s.status = 'pending'
          ");
            $stmt->execute();
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($pending):
              foreach ($pending as $org): ?>
                <tr>
                  <td><?= $org['id'] ?></td>
                  <td><?= htmlspecialchars($org['name']) ?></td>
                  <td><?= date('Y-m-d', strtotime($org['created_at'])) ?></td>
                  <td><?= $org['telegram_id'] ?>, @<?= htmlspecialchars($org['telegram_username']) ?></td>
                  <td>
                    <?php if (!empty($org['vk_link'])): ?>
                      <a href="<?= htmlspecialchars($org['vk_link']) ?>" target="_blank">Ссылка</a>
                    <?php else: ?>
                      Не указана
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($org['city'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($org['contact_email'] ?? '-') ?></td>
                  <td>
                    <a href="?approve=<?= $org['id'] ?>&tg_id=<?= $org['telegram_id'] ?>&name=<?= $org['name'] ?>" class="btn green"><i class="material-icons">done</i></a>
                    <a href="?reject=<?= $org['id'] ?>&tg_id=<?= $org['telegram_id'] ?>&name=<?= $org['name'] ?>" class="btn red btn-reject"><i class="material-icons">remove</i></a>
                  </td>
                </tr>
              <?php endforeach;
            else: ?>
              <tr>
                <td colspan="8">Нет новых студий</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Таблица active -->
      <div id="active" class="container">
        <h5>Активные студии</h5>
        <table class="responsive-table striped hover centered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Название</th>
              <th>Дата регистрации</th>
              <th>Владелец (telegram_id, @username)</th>
              <th>Ссылка VK</th>
              <th>Город</th>
              <th>Email</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $stmt = $db->connect()->prepare("
            SELECT s.*, u.telegram_id, u.telegram_username 
            FROM studios s
            JOIN users u ON s.owner_id = u.id
            WHERE s.status = 'active'
          ");
            $stmt->execute();
            $active = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($active):
              foreach ($active as $org): ?>
                <tr>
                  <td><?= $org['id'] ?></td>
                  <td><?= htmlspecialchars($org['name']) ?></td>
                  <td><?= date('Y-m-d', strtotime($org['created_at'])) ?></td>
                  <td><?= $org['telegram_id'] ?>, @<?= htmlspecialchars($org['telegram_username']) ?></td>
                  <td>
                    <?php if (!empty($org['vk_link'])): ?>
                      <a href="<?= htmlspecialchars($org['vk_link']) ?>" target="_blank">Ссылка</a>
                    <?php else: ?>
                      Не указана
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($org['city'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($org['contact_email'] ?? '-') ?></td>
                </tr>
              <?php endforeach;
            else: ?>
              <tr>
                <td colspan="7">Нет активных студий</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Ниже таблицы active -->
      <div id="map" style="width: 100%; height: 500px; margin-top: 40px;"></div>

      <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" type="text/javascript"></script>
      <script>
        ymaps.ready(initMap);

        function initMap() {
          var map = new ymaps.Map("map", {
            center: [55.76, 37.64], // Москва как центр
            zoom: 4,
            controls: ['zoomControl', 'fullscreenControl']
          });

          // Данные студий из PHP
          var studios = [
            <?php foreach ($active as $org):
              if (!empty($org['city'])):
                $name = htmlspecialchars($org['name'], ENT_QUOTES);
                $city = htmlspecialchars($org['city'], ENT_QUOTES);
                $email = htmlspecialchars($org['contact_email'], ENT_QUOTES);
                echo "{name: '{$name}', city: '{$city}', email: '{$email}'},";
              endif;
            endforeach; ?>
          ];

          studios.forEach(function(studio) {
            ymaps.geocode(studio.city, {
              results: 1
            }).then(function(res) {
              var coord = res.geoObjects.get(0).geometry.getCoordinates();
              var placemark = new ymaps.Placemark(coord, {
                balloonContent: `<strong>${studio.name}</strong><br>${studio.city}<br>${studio.email}`
              }, {
                preset: 'islands#blueDotIcon'
              });
              map.geoObjects.add(placemark);
            });
          });
        }
      </script>

    </section>
  </main>
  <?php require_once('footer.php'); ?>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/js/materialize.min.js"></script>
  <script>
    // Инициализация компонентов
    $(document).ready(function() {
      $('.button-collapse').sideNav({
        menuWidth: 300,
        edge: 'left',
        closeOnClick: false,
        draggable: true
      });

      $('.tooltipped').tooltip({
        delay: 50
      });

      $('select').material_select();
      $('.collapsible').collapsible();
    });
  </script>

  <script>
    $(document).ready(function() {
      $('ul.tabs').tabs();

      $('.btn-reject').click(function(e) {
        e.preventDefault();
        let url = $(this).attr('href');
        let reason = prompt('Укажите причину отклонения студии:');
        if (reason !== null && reason.trim() !== '') {
          window.location.href = url + '&reject_reason=' + encodeURIComponent(reason);
        }
      });
    });
  </script>
</body>

</html>