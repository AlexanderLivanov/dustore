<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Game Project Creation</title>
  <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
  <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
  <style>
    .gqi-fixed-container {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      background: #fff;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
      z-index: 10;
      padding: 10px 0;
      margin-left: 300px;
    }

    .gqi-wrapper {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .gqi-label {
      font-weight: bold;
      min-width: 100px;
    }

    .progress {
      flex-grow: 1;
      height: 20px;
      margin: 0;
    }

    main {
      padding-bottom: 100px !important;
    }

    @media (max-width: 900px) {
      .gqi-wrapper {
        flex-direction: column;
      }

      .gqi-label {
        width: 100%;
        text-align: center;
      }

      .gqi-fixed-container {
        margin-left: 0;
      }
    }
  </style>
</head>

<body>
  <?php require_once('../swad/static/elements/sidebar.php');
  if ($curr_user->getUserRole($_SESSION['id'], "global") != -1) {
    header('Location: select');
    exit();
  }
  ?>
  <main>
    <section class="content">
      <div class="page-announce valign-wrapper"><a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only"><i class="material-icons">menu</i></a>
        <h1 class="page-announce-text valign">// Create Game Project </h1>
      </div>
      <div class="container">
        <h3>Project Details</h3>
        <br>
        <form id="game-project">
          <table class="table table-hover">
            <tbody>
              <tr>
                <td><label for="project-name">Project Name: </label></td>
                <td><input type="text" name="project-name" placeholder="Enter project name" required /></td>
              </tr>
              <tr>
                <td><label for="genre">Genre: </label></td>
                <td>
                  <select name="genre" class="browser-default">
                    <option value="" disabled selected>Select genre</option>
                    <option value="action">Action</option>
                    <option value="rpg">RPG</option>
                    <option value="strategy">Strategy</option>
                    <option value="adventure">Adventure</option>
                  </select>
                </td>
              </tr>
              <tr>
                <td><label for="description">Description: </label></td>
                <td><textarea name="description" class="materialize-textarea" placeholder="Game description"></textarea></td>
              </tr>
              <tr>
                <td><label for="platform">Platform: </label></td>
                <td>
                  <p>
                    <input type="checkbox" id="pc" name="platform[]" />
                    <label for="pc">PC</label>
                  </p>
                  <p>
                    <input type="checkbox" id="console" name="platform[]" />
                    <label for="console">Console</label>
                  </p>
                  <p>
                    <input type="checkbox" id="mobile" name="platform[]" />
                    <label for="mobile">Mobile</label>
                  </p>
                </td>
              </tr>
              <tr>
                <td><label for="release-date">Release Date: </label></td>
                <td><input type="date" name="release-date" class="datepicker" /></td>
              </tr>
              <tr>
                <td><label for="cover-art">Cover Art: </label></td>
                <td><input type="file" name="cover-art" accept="image/*" /></td>
              </tr>
              <tr>
                <td><label for="website">Website: </label></td>
                <td><input type="url" name="website" placeholder="https://yourgame.com" /></td>
              </tr>
              <tr>
                <td colspan="2">
                  <div class="center-align">
                    <input class="btn btn-success" type="submit" value="Create Project" />
                    <a href="#" class="btn btn-flat">Cancel</a>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </form>

        <br><br>
        <h2>Project Documentation</h2><br>
        <table class="striped hover">
          <thead>
            <tr>
              <th>File Name</th>
              <th>Upload Date</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Design Document.pdf</td>
              <td>Not uploaded</td>
            </tr>
            <tr>
              <td>Technical Specs.docx</td>
              <td>Not uploaded</td>
            </tr>
            <tr>
              <td>Art Concepts.zip</td>
              <td>Not uploaded</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="gqi-fixed-container">
        <div class="container">
          <div class="gqi-wrapper">
            <span class="gqi-label">GQI: <span id="gqi-value">0%</span></span>
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
      $('.datepicker').pickadate();
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
  </script>
  <script>
    const fieldWeights = {
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
            // Для группы чекбоксов
            isFilled = document.querySelectorAll(`[name="${fieldId}"]:checked`).length > 0;
            break;
          case 'file':
            isFilled = !!field.files.length;
            break;
          case 'select-one':
            isFilled = field.value !== '';
            break;
          default:
            // Безопасная проверка для текстовых полей
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

    // Инициализация слушателей с проверкой
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