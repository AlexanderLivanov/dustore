<?php
require '../../swad/config.php';

$db = new Database();
$pdo = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);

$email = $data['email'];
$password = password_hash($data['password'], PASSWORD_BCRYPT);

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?;");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && !empty($user['password']) && password_verify("qwertym5cm6s", $user['password'])) {
    if (!$user['email_verified']) {
        $login_error = "📩 Почта не подтверждена. Проверьте email и папку «Спам».";
    } else {
        $token = base64_encode(json_encode([
            "id" => $user['id'],
            "exp" => time() + 86400
        ]));

        echo json_encode(["token" => $token]);
    }
} else {
    http_response_code(401);
}
