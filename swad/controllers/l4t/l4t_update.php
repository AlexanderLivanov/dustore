<?php
session_start();
require_once('../../config.php');

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->connect();

$uid = $_SESSION['USERDATA']['id'] ?? 0;
if (!$uid) die(json_encode(["error" => "no auth"]));

$d = json_decode(file_get_contents("php://input"), true);

switch ($d['type']) {

    case "exp":
        $clean = [];
        foreach ($d['data'] as $e) {
            $clean[] = [
                "role" => htmlspecialchars(mb_substr($e['role'], 0, 30)),
                "years" => (int)$e['years']
            ];
        }

        $stmt = $pdo->prepare(
            "UPDATE users SET l4t_exp=? WHERE id=?"
        );
        $stmt->execute([
            json_encode($clean, JSON_UNESCAPED_UNICODE),
            $uid
        ]);
        break;

    case "files":
        $clean = [];
        foreach ($d['data'] as $f) {
            $clean[] = [
                "type" => $f['type'] == "file" ? "file" : "link",
                "value" => htmlspecialchars(
                    mb_substr($f['value'], 0, 200)
                )
            ];
        }

        $stmt = $pdo->prepare(
            "UPDATE users SET l4t_files=? WHERE id=?"
        );
        $stmt->execute([
            json_encode($clean, JSON_UNESCAPED_UNICODE),
            $uid
        ]);
        break;

    case "projects":
        $clean = [];
        foreach ($d['data'] as $p) {

            $url = filter_var($p['url'], FILTER_SANITIZE_URL);

            $clean[] = [
                "url" => $url,
                "cover" => htmlspecialchars($p['cover'])
            ];
        }

        $stmt = $pdo->prepare(
            "UPDATE users SET l4t_projects=? WHERE id=?"
        );
        $stmt->execute([
            json_encode($clean, JSON_UNESCAPED_UNICODE),
            $uid
        ]);
        break;

    case "about":

        $txt = htmlspecialchars(
            mb_substr($d['data'], 0, 1000)
        );

        $stmt = $pdo->prepare(
            "UPDATE users SET l4t_about=? WHERE id=?"
        );
        $stmt->execute([$txt, $uid]);
        break;
}

echo json_encode(["success" => true]);
