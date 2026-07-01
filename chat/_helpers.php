<?php
declare(strict_types=1);

/**
 * Общие хелперы мессенджера. Подключаются и из api.php, и из index.php.
 *
 * Схема сверена с дампом (02.06.2026):
 *   staff(id, telegram_id BIGINT, uid, org_id INT NOT NULL, role)   -- студия = org_id
 *   studios(id, name, owner_id, avatar_link, status, ...)
 *   users(id, telegram_id VARCHAR(32), username, first_name, last_name, profile_picture, ...)
 *   Правило проекта: staff <-> users по telegram_id (BIGINT vs VARCHAR -> CAST), НЕ по uid.
 */

// База для относительных путей аватарок. CONFIRM: если profile_picture хранит
// S3-ключ — подставь сюда публичный префикс бакета.
if (!defined('AVATAR_BASE')) define('AVATAR_BASE', '/');

function avatar_url($raw): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if (preg_match('~^https?://~i', $raw)) return $raw;   // уже полный URL
    if ($raw[0] === '/') return $raw;                     // путь от корня сайта
    return AVATAR_BASE . $raw;
}

/** id студий, где юзер — staff ИЛИ владелец (для вкладки «Студия»). */
function get_user_studio_ids(PDO $db, int $uid): array {
    $ids = [];

    // 1) студии, которыми владею напрямую
    $o = $db->prepare("SELECT id FROM studios WHERE owner_id = ?");
    $o->execute([$uid]);
    foreach ($o as $r) $ids[] = (int)$r['id'];

    // 2) telegram_id юзера -> студии, где я staff.
    //    staff.telegram_id (BIGINT) сравниваем с параметром — БЕЗ cross-type JOIN
    //    и без CAST: так нет ни проблем с коллациями, ни обрезания типов.
    $t = $db->prepare("SELECT telegram_id FROM users WHERE id = ? LIMIT 1");
    $t->execute([$uid]);
    $tg = $t->fetchColumn();

    if ($tg !== false && $tg !== null && $tg !== '') {
        $s = $db->prepare("SELECT DISTINCT org_id FROM staff WHERE telegram_id = ?");
        $s->execute([$tg]);
        foreach ($s as $r) {
            if ($r['org_id'] !== null) $ids[] = (int)$r['org_id'];
        }
    }

    return array_values(array_unique($ids));
}

/** Мета студий: [id => ['id','name','logo']] */
function get_studios_meta(PDO $db, array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return [];
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $db->prepare("SELECT id, name, avatar_link FROM studios WHERE id IN ($in)");
    $st->execute($ids);
    $out = [];
    foreach ($st as $r) {
        $out[(int)$r['id']] = [
            'id'   => (int)$r['id'],
            'name' => $r['name'],
            'logo' => avatar_url($r['avatar_link']),
        ];
    }
    return $out;
}

/** Мета пользователей: [id => ['id','username'(display),'avatar']] */
function get_users_meta(PDO $db, array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return [];
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $db->prepare("SELECT id, username, first_name, last_name, profile_picture
                           FROM users WHERE id IN ($in)");
    $st->execute($ids);
    $out = [];
    foreach ($st as $r) {
        $name = trim((string)($r['username'] ?? ''));
        if ($name === '') $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        if ($name === '') $name = 'user#' . $r['id'];
        $out[(int)$r['id']] = [
            'id'       => (int)$r['id'],
            'username' => $name,
            'avatar'   => avatar_url($r['profile_picture']),
        ];
    }
    return $out;
}

/** Канонические ключи дедупликации бесед. */
function dm_key(int $a, int $b): string {
    return 'd' . min($a, $b) . ':' . max($a, $b);
}
function studio_key(int $customerId, int $studioId): string {
    return "s{$studioId}:u{$customerId}";
}