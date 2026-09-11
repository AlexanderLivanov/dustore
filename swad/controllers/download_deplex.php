<?php
/**
 * download_deplex.php — точка входа для скачивания deplex-установщика.
 *
 * Зачем: кнопка «Скачать (установщик)» на game.php вела напрямую на
 * api.dustore.ru, минуя сайт. Из-за этого строка в `library` не создавалась,
 * а вместе с ней ломалось всё, что на неё завязано:
 *   - $userCanReview в game.php   → отзыв нельзя было оставить вообще никому
 *   - Game::getTotalDownloads()   → deplex-скачивания не попадали в статистику
 *   - $isOwned / «в библиотеке»   → игра не считалась полученной
 *
 * Поведение намеренно 1-в-1 повторяет download_game.php: та же проверка
 * сессии, тот же updateUserItems(), тот же 302. Отличается только адрес,
 * на который уводим. Никаких дополнительных проверок доступа здесь НЕТ —
 * иначе два пути скачивания разъедутся по правилам (см. заметку в ответе).
 */

session_start();
require_once('../config.php');
require_once('user.php');
require_once('deplex_web.php');

/* Аноним библиотеки не имеет — писать нечего, но файл получить может,
 * ровно как и на zip-пути. */
$game_id = (int)($_GET['game_id'] ?? 0);
$os      = (string)($_GET['os'] ?? 'windows');
$user_id = (int)($_SESSION['USERDATA']['id'] ?? 0);

downloadDeplex($game_id, $os, $user_id > 0 ? $user_id : null);

function downloadDeplex(int $game_id, string $os, ?int $user_id): void
{
    $db  = new Database();
    $pdo = $db->connect();

    if ($game_id <= 0) {
        header('Location: /explore');
        exit();
    }

    /* Белый список: $os уходит в URL внешнего API, склеивать его
     * с сырым GET-параметром нельзя. */
    if (!in_array($os, ['windows', 'linux', 'macos'], true)) {
        $os = 'windows';
    }

    /* Билд должен существовать. Без этой проверки мы бы писали игру
     * в библиотеку пользователю, которому потом нечего скачивать. */
    if (deplex_latest_build_id($pdo, $game_id) === null) {
        header("Location: /g/$game_id");
        exit();
    }

    if ($user_id !== null) {
        $curr_user = new User();
        $curr_user->updateUserItems($user_id, $game_id);

        /* Отметка play-to-vote.
           Право голосовать за работу джема даёт строка в jam_plays, и
           ставить её должен сервер, отдавший файл. Здесь этого не было:
           человек скачивал deplex-билд и получал «Сначала скачайте игру,
           чтобы за неё голосовать». За такие работы не мог проголосовать
           никто — ровно та же дыра, что была с отзывами. */
        $sp = $pdo->prepare("SELECT sprint_id FROM games WHERE id = ? LIMIT 1");
        $sp->execute([$game_id]);
        $sprint_id = (int)$sp->fetchColumn();

        if ($sprint_id > 0) {
            // подключаем только функцию, без HTTP-обвязки
            if (!defined('JAM_PLAY_LIB_ONLY')) define('JAM_PLAY_LIB_ONLY', true);
            require_once(__DIR__ . '/jams/jam_play.php');
            jam_play_record($pdo, $sprint_id, $game_id, $user_id, 'download');
        }
    }

    header('Location: ' . DEPLEX_API_BASE . "/v1/games/$game_id/installer?os=" . urlencode($os));
    exit();
}