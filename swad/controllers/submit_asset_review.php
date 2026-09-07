<?php
declare(strict_types=1);

/* Ответ обязан быть JSON при ЛЮБОМ исходе: клиент делает r.json(), и если
   PHP успел напечатать warning или упал фаталом, разбор падает, а кнопка
   «просто не работает» без единого следа в интерфейсе. */
ob_start();

function reply(array $d): void {
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[assetstore] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        reply(['success' => false, 'error' => 'Внутренняя ошибка сервера']);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

/* БЫЛО: require_once('../../swad/config.php');
   Файл лежит в /swad/controllers/, значит ../../ уводит ЗА корень сайта —
   такого пути не существует. Скрипт падал фаталом ещё до первой строки
   логики, отдавал HTML-страницу ошибки вместо JSON, r.json() бросал
   исключение, и на странице ассета «не работала ни одна кнопка».
   Одна лишняя ../ во всех пяти контроллерах. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/csrf.php';

if (empty($_SESSION['USERDATA']['id'])) {
    http_response_code(401);
    reply(['success' => false, 'error' => 'Требуется авторизация']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    reply(['success' => false, 'error' => 'Method not allowed']);
}

/* Токена здесь не было вовсе: сторонний сайт мог от имени залогиненного
   посетителя добавлять ассеты в библиотеку, чистить вишлист и писать отзывы. */
if (!csrf_valid()) {
    http_response_code(403);
    reply(['success' => false, 'error' => 'Сессия устарела, обновите страницу']);
}



$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_POST['asset_id'] ?? 0);
$rating   = max(1, min(10, intval($_POST['rating'] ?? 5)));
$text     = trim((string)($_POST['text'] ?? ''));
$text     = mb_substr($text, 0, 2000);
$user_id  = (int)$_SESSION['USERDATA']['id'];

// strlen считает БАЙТЫ: в UTF-8 два русских слова — уже больше пяти байт,
// а вот «ок» проходило бы как 4 байта. Меряем символами.
if ($asset_id <= 0 || mb_strlen($text) < 5) {
    reply(['success' => false, 'error' => 'Заполните все поля']);
}

try {
    // Upsert — один отзыв на ассет
    $stmt = $pdo->prepare("
        INSERT INTO asset_reviews (asset_id, user_id, rating, text, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), text = VALUES(text), created_at = NOW()
    ");
    $stmt->execute([$asset_id, $user_id, $rating, $text]);

    // Пересчитать avg_rating
    $pdo->prepare("
        UPDATE assets SET avg_rating = (
            SELECT AVG(rating) FROM asset_reviews WHERE asset_id = ?
        ) WHERE id = ?
    ")->execute([$asset_id, $asset_id]);

    reply(['success' => true]);
} catch (Throwable $e) {
    // Текст исключения PDO содержит куски запроса — наружу его нельзя
    error_log('[assetstore/submit_asset_review.php] ' . $e->getMessage());
    reply(['success' => false, 'error' => 'Внутренняя ошибка']);
}