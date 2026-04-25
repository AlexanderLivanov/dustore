<?php
// (c) Dustore WebPlayer
session_start();

require_once('swad/config.php');
require_once('swad/controllers/game.php');

$db = new Database();
$pdo = $db->connect();

$game_id = intval($_GET['id'] ?? 0);
if ($game_id <= 0) {
    exit("Invalid game ID");
}

// Получаем игру
$gameController = new Game();
$game = $gameController->getGameById($game_id);

if (!$game) exit("Game not found");
if (empty($game["game_zip_url"])) exit("Game archive missing");

// --- ПАПКИ ---
$baseDir = __DIR__ . "/webplayerdata";
if (!is_dir($baseDir)) mkdir($baseDir);

$gameDir = "$baseDir/$game_id";
$zipPath = "$gameDir/game.zip";

// Создаём папку игры
if (!is_dir($gameDir)) mkdir($gameDir);

// ---- ФУНКЦИИ ----

function downloadFile($url, $dest)
{
    $fp = fopen($dest, 'w+');
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

function unzip($zipFile, $to)
{
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo($to);
        $zip->close();
        return true;
    }
    return false;
}

// ---- СКАЧИВАЕМ ZIP ЕСЛИ НЕТ ----
if (!file_exists($zipPath)) {
    downloadFile($game["game_zip_url"], $zipPath);
}

// ---- РАСПАКОВЫВАЕМ ЕСЛИ НЕТ ----
$execFile = trim($game["game_exec"]); // например "index.html"
$execFullPath = "$gameDir/$execFile";

if (!file_exists($execFullPath)) {
    unzip($zipPath, $gameDir);
}

// ---- ОБНОВЛЯЕМ "ПОСЛЕДНЕЕ ОБРАЩЕНИЕ" ----
file_put_contents("$gameDir/.last_access", time());

// ---- АВТООЧИСТКА СТАРЫХ ИГР (>6 часов) ----
$dirs = scandir($baseDir);
foreach ($dirs as $dir) {
    if ($dir === "." || $dir === "..") continue;

    $full = "$baseDir/$dir";
    if (!is_dir($full)) continue;

    $last_access_file = "$full/.last_access";
    if (!file_exists($last_access_file)) continue;

    $last = intval(file_get_contents($last_access_file));
    if ($last < time() - 6 * 3600) {
        exec("rm -rf " . escapeshellarg($full));
    }
}

// ---- ОТКРЫВАЕМ ИГРУ ----
$execUrl = "/webplayerdata/$game_id/" . $execFile;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>WebPlayer – <?= htmlspecialchars($game["name"]) ?></title>
    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: black;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>

<body>
    <iframe src="<?= $execUrl ?>"></iframe>
</body>

</html>