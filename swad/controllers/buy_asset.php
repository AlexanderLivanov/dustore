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
    // Получаем данные ассета
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        reply(['success' => false, 'error' => 'Ассет не найден']);
    }

    // Уже куплен?
    $exists = $pdo->prepare("SELECT id FROM asset_library WHERE player_id = ? AND asset_id = ? LIMIT 1");
    $exists->execute([$user_id, $asset_id]);
    if ($exists->fetch()) {
        reply(['success' => true, 'already_owned' => true]);
    }

    $price = (float)$asset['price'];

    // Бесплатный — просто добавляем
    if ($price <= 0) {
        $pdo->prepare("INSERT INTO asset_library (player_id, asset_id, date) VALUES (?, ?, NOW())")
            ->execute([$user_id, $asset_id]);
        $pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?")
            ->execute([$asset_id]);
        reply(['success' => true]);
    }

    // ── ЮКасса интеграция ─────────────────────────────────────────────
    // Загрузи .env или config с ключами
    $shop_id    = defined('YOOKASSA_SHOP_ID')    ? YOOKASSA_SHOP_ID    : ($_ENV['YOOKASSA_SHOP_ID']    ?? '');
    $secret_key = defined('YOOKASSA_SECRET_KEY') ? YOOKASSA_SECRET_KEY : ($_ENV['YOOKASSA_SECRET_KEY'] ?? '');

    if (!$shop_id || !$secret_key) {
        /* БЫЛО: при отсутствии ключей ЮKassa платный ассет просто клался
           в библиотеку бесплатно, с пометкой dev_mode. На бою это значит,
           что стоит слететь конфигу — и весь платный контент раздаётся
           даром, молча, без единой записи в лог.
           Разрешаем такое поведение только явным флагом в конфиге. */
        if (defined('ASSETSTORE_DEV_FREE') && ASSETSTORE_DEV_FREE === true) {
            $pdo->prepare("INSERT INTO asset_library (player_id, asset_id, date) VALUES (?, ?, NOW())")
                ->execute([$user_id, $asset_id]);
            $pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?")
                ->execute([$asset_id]);
            reply(['success' => true, 'dev_mode' => true]);
        }
        error_log('[assetstore/buy_asset] YooKassa keys are not configured');
        reply(['success' => false, 'error' => 'Оплата временно недоступна']);
    }

    $idempotency_key = uniqid('asset_', true);
    $return_url      = "https://{$_SERVER['HTTP_HOST']}/assetstore/payment_success.php?asset_id={$asset_id}";

    $payload = [
        'amount'      => ['value' => number_format($price, 2, '.', ''), 'currency' => 'RUB'],
        'confirmation'=> ['type' => 'redirect', 'return_url' => $return_url],
        'capture'     => true,
        'description' => "Ассет: {$asset['name']} (ID {$asset_id})",
        'metadata'    => ['asset_id' => $asset_id, 'user_id' => $user_id],
    ];

    $ch = curl_init('https://api.yookassa.ru/v3/payments');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_USERPWD        => "{$shop_id}:{$secret_key}",
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Idempotence-Key: ' . $idempotency_key,
        ],
        // без таймаута запрос висел до умолчания curl, а пользователь
        // всё это время смотрел на кнопку «Обработка…»
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 && $http_code !== 201) {
        reply(['success' => false, 'error' => 'Ошибка платёжного сервиса']);
    }

    $data = json_decode($response, true);
    $payment_url = $data['confirmation']['confirmation_url'] ?? null;

    if (!$payment_url) {
        reply(['success' => false, 'error' => 'Не удалось получить ссылку оплаты']);
    }

    // Сохраняем pending-платёж
    $pdo->prepare("
        INSERT INTO asset_payments (asset_id, user_id, payment_id, amount, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
        ON DUPLICATE KEY UPDATE payment_id = VALUES(payment_id), status = 'pending'
    ")->execute([$asset_id, $user_id, $data['id'], $price]);

    reply(['success' => true, 'payment_url' => $payment_url]);

} catch (Throwable $e) {
    // Текст исключения PDO содержит куски запроса — наружу его нельзя
    error_log('[assetstore/buy_asset.php] ' . $e->getMessage());
    reply(['success' => false, 'error' => 'Внутренняя ошибка']);
}