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


$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_GET['asset_id'] ?? 0);
if ($asset_id <= 0) {
    reply(['success' => false, 'error' => 'Invalid asset_id']);
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.profile_picture
        FROM asset_reviews r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.asset_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$asset_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    reply(['success' => true, 'reviews' => $reviews]);
} catch (Throwable $e) {
    // Текст исключения PDO содержит куски запроса — наружу его нельзя
    error_log('[assetstore/get_asset_reviews.php] ' . $e->getMessage());
    reply(['success' => false, 'error' => 'Внутренняя ошибка']);
}