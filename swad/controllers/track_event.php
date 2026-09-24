<?php

/**
 * swad/controllers/track_event.php
 * Публичный приёмник событий аналитики — вызывается из swad/js/analytics.js
 * через navigator.sendBeacon (без CSRF-токена: beacon не может его приложить,
 * поэтому защищаемся белым списком subject_type/event_type + разумными лимитами
 * на event_value, а не токеном).
 *
 * POST body: JSON { subject_type, subject_id, event_type, value?, meta? }
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/analytics.php');

header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'bad json']);
    exit();
}

$subjectType = (string)($body['subject_type'] ?? '');
$subjectId   = (int)($body['subject_id'] ?? 0);
$eventType   = (string)($body['event_type'] ?? '');
$value       = isset($body['value']) ? (int)$body['value'] : null;
$metaIn      = is_array($body['meta'] ?? null) ? $body['meta'] : null;

// playtime — тик раз в ~20с на клиенте, но защищаемся от накрутки одним запросом:
// зажимаем в разумные рамки, а не доверяем присланному значению как есть.
if ($eventType === 'playtime') {
    $value = max(1, min(300, $value ?? 0));
}

$db  = new Database();
$pdo = $db->connect();

$analytics = new Analytics($pdo);

// impression/view — пассивные события, дедуплицируем на сессию (см. Analytics::trackOncePerSession),
// иначе простой F5 на странице с баннером плодит показы. Клик/скачивание/запуск/плейтайм — как есть.
$ok = in_array($eventType, ['impression', 'view'], true)
    ? $analytics->trackOncePerSession($subjectType, $subjectId, $eventType)
    : $analytics->track($subjectType, $subjectId, $eventType, $value, $metaIn);

if (!$ok) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'invalid subject_type/event_type']);
    exit();
}

echo json_encode(['ok' => true]);
