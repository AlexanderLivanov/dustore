<?php
session_start();
header('Content-Type: application/json');

$user_id = $_SESSION['USERDATA']['id'];

// редирект на локальный HidL callback
header("Location: http://127.0.0.1:5000/callback?user_id=$user_id");
exit;