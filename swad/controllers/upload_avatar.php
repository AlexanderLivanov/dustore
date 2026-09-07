<?php
declare(strict_types=1);

/**
 * swad/controllers/upload_avatar.php
 *
 * Что было не так:
 *
 * 1. НЕТ ПРОВЕРКИ АВТОРИЗАЦИИ. $user_id брался из сессии без единого if.
 *    У гостя это null, дальше файл всё равно уходил в S3 с ключом
 *    "avatars/_1788475698.ext", а UPDATE ... WHERE id = NULL не менял ничего.
 *    То есть любой желающий мог заливать произвольные файлы в ваш бакет
 *    без регистрации — и они оставались там навсегда.
 *
 * 2. Тип файла проверялся по $file['type']. Это заголовок из запроса,
 *    его подставляет клиент, подделывается одной строкой в curl.
 *
 * 3. Расширение бралось из имени файла пользователя. В связке с п.2
 *    в публичный бакет заливался .svg или .html — а SVG со скриптом,
 *    отданный с вашего домена, это хранимая XSS.
 *
 * 4. Если запрос приходил не POST'ом или без файла, скрипт не выводил
 *    ВООБЩЕ НИЧЕГО. Клиент делал res.json() на пустом теле и падал в catch
 *    с «Ошибка загрузки файла» — без всякого объяснения. Это одна из причин
 *    жалобы «нельзя поменять аватарку».
 */

/* Ответ ВСЕГДА должен быть JSON.
   Клиент делает res.json(); если PHP успел напечатать warning или упал
   фаталом, тело перестаёт быть JSON, res.json() бросает исключение, и
   пользователь видит «Ошибка загрузки файла» без единой подробности.
   Именно так этот эндпоинт и падал. Буфер + shutdown-обработчик
   превращают любой сбой в читаемый JSON, а причина уходит в лог. */
ob_start();

function reply(array $d): void
{
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[upload_avatar] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        reply(['success' => false, 'error' => 'Внутренняя ошибка сервера (код: fatal)']);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

/* Пути через __DIR__: относительный require резолвится от рабочей
   директории процесса, а не от файла. Работает по совпадению и ломается
   при первом же изменении точки входа. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/csrf.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!is_readable($autoload)) {
    error_log('[upload_avatar] autoload not found at ' . $autoload);
    reply(['success' => false, 'error' => 'Хранилище недоступно (код: autoload)']);
}
require_once $autoload;

if (!class_exists('Aws\\S3\\S3Client')) {
    error_log('[upload_avatar] Aws\\S3\\S3Client missing after autoload');
    reply(['success' => false, 'error' => 'Хранилище недоступно (код: sdk)']);
}

foreach (['AWS_S3_REGION','AWS_S3_ENDPOINT','AWS_S3_KEY','AWS_S3_SECRET','AWS_S3_BUCKET_USERCONTENT'] as $c) {
    if (!defined($c)) {
        error_log('[upload_avatar] missing constant ' . $c);
        reply(['success' => false, 'error' => 'Хранилище не настроено (код: ' . $c . ')']);
    }
}

if (empty($_SESSION['USERDATA']['id'])) {
    http_response_code(401);
    reply(['success' => false, 'error' => 'Требуется авторизация']);
}
$user_id = (int)$_SESSION['USERDATA']['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    reply(['success' => false, 'error' => 'Неверный метод']);
}

if (!csrf_valid()) {
    http_response_code(403);
    reply(['success' => false, 'error' => 'Сессия устарела, обновите страницу']);
}

if (empty($_FILES['avatar'])) {
    reply(['success' => false, 'error' => 'Файл не получен']);
}

$file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $why = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
        ? 'Файл слишком большой' : 'Ошибка загрузки';
    reply(['success' => false, 'error' => $why]);
}

// Защита от подсовывания пути вместо загруженного файла
if (!is_uploaded_file($file['tmp_name'])) {
    reply(['success' => false, 'error' => 'Некорректный файл']);
}

if ($file['size'] > 2 * 1024 * 1024) {
    reply(['success' => false, 'error' => 'Максимальный размер 2 МБ']);
}

/* Тип определяем по СОДЕРЖИМОМУ, а не по заголовку из запроса.
   Расширение берём из нашей таблицы, а не из имени файла пользователя. */
if (!function_exists('finfo_open')) {
    // расширение fileinfo выключено — молча пропускать нельзя,
    // иначе проверка типа превращается в фикцию
    error_log('[upload_avatar] ext fileinfo disabled');
    reply(['success' => false, 'error' => 'Сервер не может проверить тип файла (код: finfo)']);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    reply(['success' => false, 'error' => 'Только JPEG, PNG или WebP']);
}

// Дополнительная проверка: это действительно картинка, а не файл с нужными
// первыми байтами
if (@getimagesize($file['tmp_name']) === false) {
    reply(['success' => false, 'error' => 'Файл повреждён или не является изображением']);
}

// Случайный суффикс: без него ключ предсказуем и старый аватар можно
// перезаписать, зная id и время
$key = 'avatars/' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];

try {
    $s3 = new Aws\S3\S3Client([
        'version'     => 'latest',
        'region'      => AWS_S3_REGION,
        'endpoint'    => AWS_S3_ENDPOINT,
        'credentials' => ['key' => AWS_S3_KEY, 'secret' => AWS_S3_SECRET],
    ]);

    $result = $s3->putObject([
        'Bucket'       => AWS_S3_BUCKET_USERCONTENT,
        'Key'          => $key,
        'SourceFile'   => $file['tmp_name'],
        'ACL'          => 'public-read',
        'ContentType'  => $mime,
        'CacheControl' => 'public, max-age=31536000',
    ]);

    $url = (string)$result['ObjectURL'];
    if ($url === '') throw new RuntimeException('empty ObjectURL');

    $pdo = (new Database())->connect();
    $pdo->prepare("UPDATE users SET profile_picture = ?, updated = NOW() WHERE id = ?")
        ->execute([$url, $user_id]);

    $_SESSION['USERDATA']['profile_picture'] = $url;

    reply(['success' => true, 'url' => $url]);

} catch (Throwable $e) {
    // Текст исключения AWS содержит эндпоинт, имя бакета и иногда ключи —
    // наружу его отдавать нельзя, раньше он уезжал прямо в ответ
    error_log('[upload_avatar] ' . get_class($e) . ': ' . $e->getMessage());
    reply(['success' => false, 'error' => 'Не удалось сохранить изображение (код: s3)']);
}