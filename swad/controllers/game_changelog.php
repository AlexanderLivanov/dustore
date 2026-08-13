<?php
// swad/controllers/game_changelog.php
// Хелпер записи изменений проекта в game_change_log.
// Подключать: require_once __DIR__ . '/../swad/controllers/game_changelog.php';

if (!function_exists('log_game_change')) {
    /**
     * Записать одно изменение в журнал.
     * Для action='updated' запись создаётся ТОЛЬКО если old !== new (тихо пропускает «пустые» диффы).
     * Для событий (submitted/resubmitted/added/removed) old/new можно передавать null.
     *
     * Best-effort: любые ошибки логируются в error_log и НЕ роняют основной запрос.
     */
    function log_game_change(
        PDO $pdo,
        int $gameId,
        int $studioId,
        ?int $userId,
        string $field,
        ?string $old,
        ?string $new,
        string $action = 'updated'
    ): void {
        if ($action === 'updated' && (string)$old === (string)$new) {
            return; // ничего не поменялось
        }
        try {
            $pdo->prepare("
                INSERT INTO game_change_log (game_id, studio_id, user_id, field, action, old_value, new_value)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$gameId, $studioId, $userId, $field, $action, $old, $new]);
        } catch (Throwable $e) {
            error_log('[game_changelog] ' . $e->getMessage());
        }
    }

    /**
     * Пакетный дифф ассоциативного набора полей.
     * $fields: ['field' => [$oldValue, $newValue], ...]
     */
    function log_game_diff(
        PDO $pdo,
        int $gameId,
        int $studioId,
        ?int $userId,
        array $fields
    ): void {
        foreach ($fields as $field => [$old, $new]) {
            log_game_change($pdo, $gameId, $studioId, $userId, (string)$field, (string)$old, (string)$new, 'updated');
        }
    }
}