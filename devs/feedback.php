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
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
</head>

<body>
  <?php require_once('../swad/static/elements/sidebar.php'); ?>

  <main>
    <section class="content">
      <div class="page-announce valign-wrapper"><a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only"><i class="material-icons">menu</i></a>
        <h1 class="page-announce-text valign">// Comment Approvals </h1>
      </div>
      <div id="posttable" class="container">
        <div class="custom-responsive">
          <table class="striped hover centered">
            <thead>
              <tr>
                <th>Username:</th>
                <th>Date Posted:</th>
                <th>Comment Posted:</th>
                <th>Post Commented On</th>
                <th>Comment Actions:</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><a>CreedBratton</a></td>
                <td><a>2017-03-30</a></td>
                <td><a>"What is wrong with this woman? She's asking about stuff that's nobody's business. 'What do I do?'... Really, what do I do here? I should've written it down. "Qua" something, uh... qua... quar... quibo, qual...quir-quabity. Quabity assuance! No. No, no, no, no, but I'm getting close."</a></td>
                <td><a>New Update blog</a></td>
                <td>
                  <div class="btn-toolbar">
                    <a href="#">
                      <button class="btn green" type="submit" value="Accept">
                        <i class="material-icons">done</i>
                      </button>
                    </a>
                    <a href="#">
                      <button class="btn red" type="submit" value="Reject">
                        <i class="material-icons">remove</i>
                      </button>
                    </a>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a>AngelaMartin</a></td>
                <td><a>2017-03-30</a></td>
                <td><a>"I heard a joke today."</a></td>
                <td><a>New Update blog</a></td>
                <td>
                  <div class="btn-toolbar">
                    <a href="#">
                      <button class="btn green" type="submit" value="Accept">
                        <i class="material-icons">done</i>
                      </button>
                    </a>
                    <a href="#">
                      <button class="btn red" type="submit" value="Reject">
                        <i class="material-icons">remove</i>
                      </button>
                    </a>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a>DwightSchrute</a></td>
                <td><a>2017-03-30</a></td>
                <td><a>"That's funny."</a></td>
                <td><a>New Update blog</a></td>
                <td>
                  <div class="btn-toolbar">
                    <a href="#">
                      <button class="btn green" type="submit" value="Accept">
                        <i class="material-icons">done</i>
                      </button>
                    </a>
                    <a href="#">
                      <button class="btn red" type="submit" value="Reject">
                        <i class="material-icons">remove</i>
                      </button>
                    </a>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a>AngelaMartin</a></td>
                <td><a>2017-03-30</a></td>
                <td><a>"Yes, it was."</a></td>
                <td><a>New Update blog</a></td>
                <td>
                  <div class="btn-toolbar">
                    <a href="#">
                      <button class="btn green" type="submit" value="Accept">
                        <i class="material-icons">done</i>
                      </button>
                    </a>
                    <a href="#">
                      <button class="btn red" type="submit" value="Reject">
                        <i class="material-icons">remove</i>
                      </button>
                    </a>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
  <?php require_once('footer.php'); ?>

  <!-- So this is basically a hack, until I come up with a better solution. autocomplete is overridden
    in the materialize js file & I don't want that.
    -->
  <!-- Yo dawg, I heard you like hacks. So I hacked your hack. (moved the sidenav js up so it actually works) -->
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