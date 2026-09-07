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
$user_id  = (int)$_SESSION['USERDATA']['id'];

if ($asset_id <= 0) {
    reply(['success' => false, 'error' => 'Invalid asset_id']);
}

try {
    $exists = $pdo->prepare("SELECT id FROM asset_wishlist WHERE player_id = ? AND asset_id = ? LIMIT 1");
    $exists->execute([$user_id, $asset_id]);

    if ($exists->fetch()) {
        // Удаляем из вишлиста
        $pdo->prepare("DELETE FROM asset_wishlist WHERE player_id = ? AND asset_id = ?")
            ->execute([$user_id, $asset_id]);
        reply(['success' => true, 'action' => 'removed']);
    } else {
        // Добавляем в вишлист
        $pdo->prepare("INSERT INTO asset_wishlist (player_id, asset_id, added_at) VALUES (?, ?, NOW())")
            ->execute([$user_id, $asset_id]);
        reply(['success' => true, 'action' => 'added']);
    }
} catch (Throwable $e) {
    // Текст исключения PDO содержит куски запроса — наружу его нельзя
    error_log('[assetstore/wishlist.php] ' . $e->getMessage());
    reply(['success' => false, 'error' => 'Внутренняя ошибка']);
}
