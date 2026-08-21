<?php
// ============================================================
// HELPERS.PHP — утилиты: JSON-ответы, чтение входных данных
// ============================================================

/**
 * Загрузить конфиг (с поддержкой переопределения через env для тестов).
 */
function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = getenv('KONTUR_CONFIG') ?: (__DIR__ . '/config.php');
        $cfg = require $path;
    }
    return $cfg;
}

/**
 * Отправить JSON-ответ и завершить выполнение.
 */
function json_response($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Ошибка в едином формате.
 */
function json_error(string $message, int $status = 400, $detail = null): never
{
    json_response(['error' => $message, 'detail' => $detail], $status);
}

/**
 * Прочитать JSON-тело запроса как ассоциативный массив.
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_error('Некорректный JSON в теле запроса', 400);
    }
    return is_array($data) ? $data : [];
}

/**
 * Достать обязательное строковое поле с обрезкой пробелов.
 */
function require_field(array $data, string $key, ?int $maxLen = null): string
{
    if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
        json_error("Поле «{$key}» обязательно", 422);
    }
    $val = trim($data[$key]);
    if ($maxLen !== null && mb_strlen($val) > $maxLen) {
        json_error("Поле «{$key}» слишком длинное (макс. {$maxLen})", 422);
    }
    return $val;
}

/**
 * Настроить CORS и обработать preflight OPTIONS.
 */
function setup_cors(): void
{
    $config = app_config();
    $origin = $config['cors_origin'] ?? '';

    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
