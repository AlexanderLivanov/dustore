<?php
declare(strict_types=1);

/**
 * swad/controllers/jams/jam_play.php
 * Фиксирует, что игрок реально запустил работу джема (play-to-vote).
 *
 * ─────────────────────────────────────────────────────────────────────────
 * ЧТО БЫЛО НЕ ТАК
 *
 * В save_vote.php написано, ради чего этот механизм существует:
 *
 *     "Право голоса даёт факт скачивания билда: jam_plays пишется на сервере
 *      (download_game.php / deplex-установщик / webplayer), а не по клику
 *      в браузере."
 *
 * А этот контроллер делал ровно противоположное: принимал POST от клиента
 * и создавал строку в jam_plays по одному клику. Причём клика даже не
 * требовалось — эндпоинт принимал любой запрос с sprint_id и game_id.
 * Скрипт в консоли на десять строк отмечал ВСЕ работы как «сыгранные»,
 * после чего можно было раздать голоса, не открыв ни одной игры.
 * Гейт был декоративным.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * КАК СТАЛО
 *
 * Разделили два случая, потому что «сыграть» для них значит разное:
 *
 *   source = 'download'  игру надо скачать. Строку пишет ТОЛЬКО сервер,
 *                        отдавший файл (download_game.php,
 *                        download_deplex.php). Отсюда — никогда.
 *
 *   source = 'browser'   игра запускается прямо на странице. Скачивать
 *                        нечего, открытие и ЕСТЬ запуск, поэтому запись
 *                        по клику здесь законна.
 *
 * Этот файл обслуживает только второй случай и сам проверяет по БД, что
 * работа действительно веб-сборка. Заявить source извне нельзя: параметр
 * из запроса больше не читается.
 *
 * Серверные обработчики скачивания зовут jam_play_record() напрямую,
 * минуя HTTP.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../csrf.php';

/**
 * Единственное место, где появляется строка в jam_plays.
 * Вызывается и отсюда, и из контроллеров скачивания.
 *
 * @param string $source 'download' — сервер отдал файл; 'browser' — веб-сборка
 * @return bool записано ли (false — игра не подходит под джем/модерацию)
 */
function jam_play_record(PDO $pdo, int $sprintId, int $gameId, int $userId, string $source): bool
{
    if ($sprintId <= 0 || $gameId <= 0 || $userId <= 0) return false;
    $source = $source === 'browser' ? 'browser' : 'download';

    $g = $pdo->prepare("
        SELECT 1 FROM games
         WHERE id = ? AND sprint_id = ?
           AND (moderation_status = 'approved' OR status = 'published')
         LIMIT 1");
    $g->execute([$gameId, $sprintId]);
    if (!$g->fetchColumn()) return false;

    $ip = isset($_SERVER['REMOTE_ADDR']) ? @inet_pton($_SERVER['REMOTE_ADDR']) : null;

    /* Первое открытие фиксируем, повторные не трогаем — иначе
       first_download_at съезжал бы вперёд и обнулял выдержку
       MIN_PLAY_SECONDS в save_vote.php.
       Работает при наличии уникального ключа (sprint_id, user_id, game_id);
       он добавлен миграцией 006. */
    $pdo->prepare("
        INSERT INTO jam_plays (sprint_id, game_id, user_id, source, first_download_at, ip)
        VALUES (:sid, :gid, :uid, :src, NOW(), :ip)
        ON DUPLICATE KEY UPDATE play_seconds = play_seconds
    ")->execute([
        'sid' => $sprintId, 'gid' => $gameId, 'uid' => $userId,
        'src' => $source,   'ip'  => $ip,
    ]);

    return true;
}

/* Файл может быть подключён ради функции — тогда HTTP-часть не нужна. */
if (defined('JAM_PLAY_LIB_ONLY')) return;

header('Content-Type: application/json; charset=utf-8');

function jp_out(array $d, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jp_out(['success' => false, 'message' => 'Только POST'], 405);

$userId = (int)($_SESSION['USERDATA']['id'] ?? 0);
if (!$userId) jp_out(['success' => false, 'message' => 'Нужна авторизация'], 401);

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!csrf_valid($in)) jp_out(['success' => false, 'message' => 'Сессия устарела, обновите страницу'], 403);

$sprint_id = (int)($in['sprint_id'] ?? 0);
$game_id   = (int)($in['game_id'] ?? 0);
if (!$sprint_id || !$game_id) jp_out(['success' => false, 'message' => 'Неполный запрос'], 400);

$pdo = (new Database())->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    /* Веб-сборка или нет — решает БД, а не клиент.
       Логика совпадает с $isWeb на странице игры: платформа ровно одна и
       это 'web'. Если в списке есть что-то ещё, игру надо скачивать,
       и отметку поставит контроллер скачивания. */
    $p = $pdo->prepare("SELECT platforms FROM games WHERE id = ? LIMIT 1");
    $p->execute([$game_id]);
    $platformsRaw = (string)$p->fetchColumn();

    $platforms = array_filter(array_map(
        fn($x) => strtolower(trim($x)),
        explode(',', $platformsRaw)
    ), fn($x) => $x !== '');

    $isWeb = $platforms && count($platforms) === 1 && reset($platforms) === 'web';

    if (!$isWeb) {
        /* Не ошибка: страница голосования дёргает этот эндпоинт при клике
           по любой работе и знать заранее не обязана. Просто сообщаем,
           что отметка тут не ставится. */
        jp_out(['success' => false, 'skipped' => true,
                'message' => 'Отметка о запуске ставится при скачивании игры']);
    }

    $ok = jam_play_record($pdo, $sprint_id, $game_id, $userId, 'browser');
    if (!$ok) jp_out(['success' => false, 'message' => 'Игра недоступна'], 404);

    jp_out(['success' => true]);

} catch (PDOException $e) {
    error_log('[jams/jam_play] ' . $e->getMessage());
    jp_out(['success' => false, 'message' => 'Не удалось записать запуск'], 500);
}