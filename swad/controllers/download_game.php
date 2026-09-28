<?php
session_start();
require_once('../config.php');
require_once('user.php');

$game_id = (int)($_GET['game_id'] ?? 0);
$user_id = (int)($_SESSION['USERDATA']['id'] ?? 0);   // гость = 0, без кук и без фаталов

downloadGame($game_id, $user_id);

function downloadGame(int $game_id, int $user_id): void
{
    if ($game_id <= 0) { header('Location: /explore'); exit(); }

    $pdo = (new Database())->connect();

    $stmt = $pdo->prepare("
        SELECT id, game_zip_url, vt_status, sprint_id
        FROM games WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    // Игры нет или файл не загружен — на страницу, в библиотеку ничего не пишем.
    if (!$game || empty($game['game_zip_url'])) {
        header("Location: /g/$game_id");
        exit();
    }

    // Детект антивируса блокирует и прямую ссылку, не только кнопку на странице.
    if (($game['vt_status'] ?? '') === 'flagged') {
        header("Location: /g/$game_id?blocked=1");
        exit();
    }

    // Учёт библиотеки — побочный эффект. Он не имеет права мешать выдаче файла
    // и не имеет смысла для гостя: в library.player_id ссылка на users.id.
    if ($user_id > 0) {
        try {
            (new User())->updateUserItems($user_id, $game_id);
        } catch (Throwable $e) {
            error_log('[download_game] library write failed uid=' . $user_id
                    . ' game=' . $game_id . ': ' . $e->getMessage());
        }

        /* Отметка play-to-vote (см. download_deplex.php — тот же паттерн 1-в-1).
           До этого патча zip-игры вообще не могли получить право голоса: клик
           «Открыть работу» на странице голосования её не даёт (см. jam_play.php),
           а этот, единственный реальный путь скачивания, jam_plays не писал. */
        $sprint_id = (int)($game['sprint_id'] ?? 0);
        if ($sprint_id > 0) {
            try {
                if (!defined('JAM_PLAY_LIB_ONLY')) define('JAM_PLAY_LIB_ONLY', true);
                require_once(__DIR__ . '/jams/jam_play.php');
                jam_play_record($pdo, $sprint_id, $game_id, $user_id, 'download');
            } catch (Throwable $e) {
                error_log('[download_game] jam_play_record failed uid=' . $user_id
                        . ' game=' . $game_id . ': ' . $e->getMessage());
            }
        }
    }

    header('Location: ' . $game['game_zip_url']);
    exit();
}