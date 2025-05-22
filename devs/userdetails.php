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
        <h1 class="page-announce-text valign">// User Details </h1>
      </div>
      <div class="container">
        <h3>Account Information</h3>
        <br>
        <form id="user">
          <table class="table table-hover">
            <tbody>
              <tr>
                <td><label for="usrname">Account ID: </label></td>
                <td><a>DwightSchrute</a></td>
              </tr>
              <tr>
                <td><label for="djoined">Date Joined: </label></td>
                <td><a>01-11-2005</a></td>
              </tr>
              <tr>
                <td><label for="ipaddress">Last IP Address: </label></td>
                <td><a>127.0.0.1</a></td>
              </tr>
              <tr>
                <td><label for="econfirm">Email Confirmed: </label></td>
                <td><i class="material-icons">check</i></a></td>
              </tr>
              <tr>
                <td><label for="guidelines">Guidelines Approved: </label></td>
                <td><i class="material-icons">check</i></a></td>
              </tr>
              <tr>
                <input type="hidden" name="pastdata" value="{{ usr.id }}" />
                <td><label for="usrname">Username: </label></td>
                <td><input type="text" name="usrname" value="DwightSchrute" /></td>
              </tr>
              <tr>
                <td><label for="email">Email: </label></td>
                <td><input type="text" name="email" value="dwight@dundermifflin.com" /></td>
              </tr>
              <tr>
                <td><label for="accesslevel">Access Level: </label></td>
                <td><input type="text" name="accesslevel" value="Assistant To The Regional Manager" /></td>
              </tr>
              <tr>
                <td><label for="email">Account Actions: </label></td>
                <td>
                  <div class="btn-toolbar">
                    <a href="#" class="btn btn-danger">Kick</a>
                    <a href="#" class="btn btn-warning">Message</a>
                    <a href="#" class="btn btn-success">Chat Logs</a>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <br>
          <div class="center-align"><input class="btn btn-success" type="submit" value="Submit" /></div>
        </form>
        <br><br>
        <h2>DwightKSchrute's Account History</h2><br>
        <table class="striped hover">
          <thead>
            <tr>
              <th>Action</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Got into work</td>
              <td>6:40AM</td>
            </tr>
            <tr>
              <td>Watered plant</td>
              <td>6:51AM</td>
            </tr>
            <tr>
              <td>Rearranged Michael's toys</td>
              <td>7:14AM</td>
            </tr>
            <tr>
              <td>Made coffee</td>
              <td>9:20AM</td>
            </tr>
            <tr>
              <td>Made sale - $512 worth of premium letterstock</td>
              <td>12:31PM</td>
            </tr>
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