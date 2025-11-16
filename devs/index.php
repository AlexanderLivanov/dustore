<?php
require_once('../constants.php');
require_once(ROOT_DIR . '/swad/config.php');
require_once(ROOT_DIR . '/swad/controllers/user.php');
require_once('../swad/controllers/organization.php');

$curr_user = new User();
$db = new Database();
$org = new Organization();
?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Dustore.Devs - Консоль</title>
  <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
  <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
  <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
</head>

<body>
  <?php require_once('../swad/static/elements/sidebar.php');
  if ($curr_user->checkAuth() > 0) {
    echo ("window.location.replace('/login');");
    exit;
  }

  $curr_user_org_data = $curr_user->getOrgData($_SESSION['studio_id']);
  $_SESSION['STUDIODATA'] = $curr_user_org_data;

  $db = new Database();
  $conn = $db->connect();
  $studio_id = $_SESSION['studio_id'];

  $sql = "SELECT COUNT(*) AS added
        FROM library l
        INNER JOIN games g ON l.game_id = g.id
        WHERE g.developer = :studio_id";

  $stmt = $conn->prepare($sql);  
  $stmt->execute(['studio_id' => $studio_id]); 
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  ?>
  <main>
    <section class="content">
      <div class="page-announce valign-wrapper"><a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only"><i class="material-icons">menu</i></a>
        <h1 class="page-announce-text valign"><?= 'Студия ' . $curr_user_org_data['name']; ?></h1>
        <h6 id="role" class="page-announce-text valign" style="color: white;"><?= ' ' . $curr_user->printUserPrivileges($curr_user->getRoleName($curr_user->getUserRole($_SESSION['USERDATA'][0], "in_company"))); ?></h6>
      </div>
      <!-- Stat Boxes -->
      <div class="row">
        <div class="col l3 s6">
          <!-- small box -->
          <div class="small-box bg-aqua">
            <div class="inner">
              <h3><?= count($org->getAllStaff($_SESSION['STUDIODATA']['id'])) ?></h3>
              <p>Сотрудники</p>
            </div>
            <div class="icon">
              <i class="ion ion-person-add"></i>
            </div>
            <a href="staff" class="small-box-footer" class="animsition-link">Управление <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div><!-- ./col -->
        <div class="col l3 s6">
          <!-- small box -->
          <div class="small-box bg-green">
            <div class="inner">
              <h3><?= count($org->getAllProjects($_SESSION['STUDIODATA']['id'])) ?></h3>
              <p>Созданные проекты</p>
            </div>
            <div class="icon">
              <i class="ion ion-stats-bars"></i>
            </div>
            <a href="#" class="small-box-footer" class="animsition-link">Управление <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div><!-- ./col -->
        <div class="col l3 s6">
          <!-- small box -->
          <div class="small-box bg-yellow">
            <div class="inner">
              <h3><?= $row['added'] ?></h3>
              <p>Играют в ваши игры</p>
            </div>
            <div class="icon">
              <i class="ion ion-email"></i>
            </div>
            <a href="#" class="small-box-footer" class="animsition-link">Управление <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div><!-- ./col -->
        <div class="col l3 s6">
          <!-- small box -->
          <div class="small-box bg-red">
            <div class="inner">
              <h3><?= 0 . ' ₽' ?></h3>
              <p>Доход за последний месяц</p>
            </div>
            <div class="icon">
              <i class="ion ion-pie-graph"></i>
            </div>
            <a href="#" class="small-box-footer" class="animsition-link">Управление <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div>

        <div class="container">
          <div class="quick-links center-align">
            <h3>Быстрые ссылки</h3>
            <div class="row">
              <div class="col l3 s12 tooltipped" data-position="top" data-delay="50" data-tooltip="Документация для разработчиков"><a class="waves-effect waves-light btn-large" target="_blank" href="https://github.com/AlexanderLivanov/dustore-docs/wiki">Документация</a></div>
              <div class="col l3 s12 tooltipped" data-position="top" data-delay="50" data-tooltip="Правила пользования"><a class="waves-effect waves-light btn-large" href="/legal">Правила</a></div>
              <div class="col l3 s12 tooltipped" data-position="top" data-delay="50" data-tooltip="Рейтинг студий"><a class="waves-effect waves-light btn-large" href="#">Рейтинг</a></div>
              <div class="col l3 s12 tooltipped" data-position="top" data-delay="50" data-tooltip="Отправить отчёт о проблеме"><a class="waves-effect waves-light btn-large" href="mailto:support@dustore.ru">Отчёт&nbsp;о&nbsp;проблеме</a></div>
              <!-- <div class="col l4 offset-l4 s12 tooltipped" data-position="top" data-delay="50" data-tooltip="OTRS Support Site"><a class="waves-effect waves-light btn-large" href="#">Support Site</a></div> -->
            </div>
          </div>

          <h3 class="center-align">Ваши сотрудники</h3>
          <div class="custom-responsive">
            <table class="striped hover centered">
              <thead>
                <tr>
                  <th>Имя пользователя</th>
                  <th>Должность</th>
                  <th>Последний вход</th>
                  <!-- <th>???</th> -->
                </tr>
              </thead>
              <tbody>
                <?php $staff = $org->getAllStaff($_SESSION['STUDIODATA']['id']); ?>
                <?php foreach($staff as $s): ?>
                <tr>
                  <td><?php echo($curr_user->getUsername($s['telegram_id'])); ?></td>
                  <td><?php echo($s['role']); ?></td>
                  <td>-</td>
                  <!-- <td><i class="text-green material-icons">check</i></td> -->
                </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </main>
  <?php require_once('footer.php'); ?>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/js/materialize.min.js"></script>
  <script>
    // Hide sideNav
    $('.button-collapse').sideNav({
      menuWidth: 300, // Default is 300
      edge: 'left', // Choose the horizontal origin
      closeOnClick: false, // Closes side-nav on <a> clicks, useful for Angular/Meteor
      draggable: true // Choose whether you can drag to open on touch screens
    });
    $(document).ready(function() {
      $('.tooltipped').tooltip({
        delay: 50
      });
    });
    $('select').material_select();
    $('.collapsible').collapsible();
  </script>
  <div class="fixed-action-btn horizontal tooltipped" data-position="top" dattooltipped" data-position="top" data-delay="50" data-tooltip="Quick Links">
    <a class="btn-floating btn-large red">
      <i class="large material-icons">mode_edit</i>
    </a>
    <ul>
      <li><a class="btn-floating red tooltipped" data-position="top" data-delay="50" data-tooltip="Handbook" href="#"><i class="material-icons">insert_chart</i></a></li>
      <li><a class="btn-floating yellow darken-1 tooltipped" data-position="top" data-delay="50" data-tooltip="Staff Applications" href="#"><i class="material-icons">format_quote</i></a></li>
      <li><a class="btn-floating green tooltipped" data-position="top" data-delay="50" data-tooltip="Name Guidelines" href="#"><i class="material-icons">publish</i></a></li>"
      <li><a class="btn-floating blue tooltipped" data-position="top" data-delay="50" data-tooltip="Issue Tracker" href="#"><i class="material-icons">attach_file</i></a></li>
      <li><a class="btn-floating orange tooltipped" data-position="top" data-delay="50" data-tooltip="Support" href="#"><i class="material-icons">person</i></a></li>
    </ul>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
</body>

</html>