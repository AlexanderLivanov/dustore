<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Dustore.Devs - Мои проекты</title>
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
  require_once('../swad/controllers/pm.php');
  ?>

  <main>
    <section class="content">
      <div class="page-announce valign-wrapper">
        <a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only">
          <i class="material-icons">menu</i>
        </a>
        <h1 class="page-announce-text valign">// Мои проекты</h1>
      </div>
      <?php
      $projmanage = new ProjectManagment();
      $all_projects = $projmanage->getAllStudioGames($_SESSION['studio_id']);
      ?>
      <div id="projects-table" class="container">
        <button class="btn blue waves-effect waves-light" onclick="location.href='new'">
          Новый проект
          <i class="material-icons">task</i>
        </button>
        <table class="responsive-table striped hover centered">
          <thead>
            <tr>
              <th>Название</th>
              <th>Дата создания</th>
              <th>Статус</th>
              <th>Статус модерации</th>
              <th>Управление</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($all_projects as $project): ?>
              <tr>
                <td><?= $project['name']; ?></td>
                <td><?= date('d.m.Y', strtotime($project['release_date'])); ?></td>
                <td><?= $project['status']; ?></td>
                <td><?= $project['moderation_status']; ?></td>
                <td>
                  <button class="btn blue waves-effect waves-light edit-project" onclick="location.href='edit?id=<?= $project['id']; ?>'">
                    <i class="material-icons">settings</i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
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
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
</body>

</html>