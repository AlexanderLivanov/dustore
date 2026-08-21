<?php
/**
 * assetstore/_acl.php
 * ---------------------------------------------------------------------------
 * Единая точка правды по правам на ассеты.
 *
 * Принцип: код НИКОГДА не спрашивает "какая у тебя роль".
 * Код спрашивает "можно ли тебе это действие" -> acl_can($ctx, $asset, 'publish').
 *
 * Модель ролей: ordered scalar, МЕНЬШЕ = СИЛЬНЕЕ.
 *   -1  root      (всё, включая hard delete)
 *    0..2         (зарезервировано под будущие уровни staff)
 *    3  moderator (всё, кроме hard delete)
 *  100  обычный пользователь (дефолт)
 *
 * (c) Dustore
 */

declare(strict_types=1);

/* ── Уровни ──────────────────────────────────────────────────────────────── */
if (!defined('ACL_ROLE_ROOT')) {
    define('ACL_ROLE_ROOT', -1);   // высшая роль
    define('ACL_ROLE_MOD',   3);   // модератор
    define('ACL_STAFF_MAX',  3);   // role <= ACL_STAFF_MAX  =>  staff
    define('ACL_ROLE_USER', 100);  // дефолт, если роли нет / NULL
}

/* ── Статусы ─────────────────────────────────────────────────────────────── */
function asset_statuses(): array
{
    return ['draft', 'pending', 'published', 'rejected', 'hidden', 'deleted'];
}

function asset_status_meta(string $s): array
{
    static $m = [
        'draft'     => ['Черновик',    '#8b8b9e', '✎'],
        'pending'   => ['На проверке', '#f59e0b', '⏳'],
        'published' => ['Опубликован', '#00e887', '✔'],
        'rejected'  => ['Отклонён',    '#f44336', '✕'],
        'hidden'    => ['Скрыт',       '#6b7280', '⌀'],
        'deleted'   => ['Удалён',      '#ef4444', '🗑'],
    ];
    return $m[$s] ?? [$s, '#8b8b9e', '?'];
}

/** Человекочитаемое имя перехода — для кнопок и лога. */
function asset_transition_label(string $from, string $to): string
{
    static $m = [
        'draft:pending'      => 'Отправить на проверку',
        'pending:draft'      => 'Вернуть в черновики',
        'pending:published'  => 'Одобрить',
        'pending:rejected'   => 'Отклонить',
        'published:hidden'   => 'Снять с витрины',
        'published:rejected' => 'Отозвать (нарушение)',
        'hidden:published'   => 'Вернуть на витрину',
        'hidden:pending'     => 'Отправить на проверку',
        'rejected:pending'   => 'Отправить на перепроверку',
        'deleted:draft'      => 'Восстановить',
    ];
    $k = $from . ':' . $to;
    if (isset($m[$k])) return $m[$k];
    if ($to === 'deleted') return 'Удалить';
    return asset_status_meta($to)[0];
}

/* ── Роль ────────────────────────────────────────────────────────────────── */
/**
 * ⚠️ ЕДИНСТВЕННОЕ МЕСТО, где читается роль из БД.
 *    Если у тебя роль лежит не в users.role, а, например, в staff
 *    (join по telegram_id, НЕ по users.id) — правится только эта функция.
 */
function acl_role(PDO $pdo, ?int $uid): int
{
    static $cache = [];
    if (!$uid) return ACL_ROLE_USER;
    if (array_key_exists($uid, $cache)) return $cache[$uid];

    $role = ACL_ROLE_USER;
    try {
        $st = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $v = $st->fetchColumn();
        // fail-closed: NULL / '' / отсутствие строки => обычный пользователь
        if ($v !== false && $v !== null && $v !== '') {
            $role = (int)$v;
        }
    } catch (PDOException $e) {
        error_log('[acl] role lookup failed: ' . $e->getMessage());
    }
    return $cache[$uid] = $role;
}

/* ── Контекст текущего пользователя ──────────────────────────────────────── */
function acl_ctx(PDO $pdo): array
{
    static $ctx = null;
    if ($ctx !== null) return $ctx;

    $uid  = isset($_SESSION['USERDATA']['id']) ? (int)$_SESSION['USERDATA']['id'] : 0;
    $role = $uid ? acl_role($pdo, $uid) : ACL_ROLE_USER;

    $studios = [];
    if ($uid) {
        $st = $pdo->prepare("SELECT id, name, display_name FROM studios WHERE owner_id = ?");
        $st->execute([$uid]);
        $studios = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return $ctx = [
        'uid'          => $uid,
        'role'         => $role,
        'is_staff'     => $uid > 0 && $role <= ACL_STAFF_MAX,
        'is_root'      => $uid > 0 && $role <= ACL_ROLE_ROOT,
        'studios'      => $studios,
        'studio_ids'   => array_map(static fn($s) => (int)$s['id'],      $studios),
        'studio_names' => array_map(static fn($s) => (string)$s['name'], $studios),
    ];
}

/* ── Владение ────────────────────────────────────────────────────────────── */
/**
 * Двойная проверка: studio_id (после миграции) + studio_name (легаси-хвост).
 * Убрать второй ветку можно, когда `SELECT ... WHERE studio_id IS NULL` пуст.
 */
function acl_owns(array $ctx, array $asset): bool
{
    if (empty($ctx['uid'])) return false;
    if (!empty($asset['studio_id']) && in_array((int)$asset['studio_id'], $ctx['studio_ids'], true)) {
        return true;
    }
    if (!empty($asset['studio_name']) && in_array((string)$asset['studio_name'], $ctx['studio_names'], true)) {
        return true;
    }
    return false;
}

/* ── Capabilities ────────────────────────────────────────────────────────── */
/**
 * Список действий:
 *   view        — видеть в админке
 *   edit        — править нематериальные поля (цена, теги, версия, dev_share)
 *   edit_full   — править материальные поля (имя, описание, файл, обложка)
 *   submit      — отправить на модерацию
 *   hide        — снять с витрины
 *   delete      — мягкое удаление
 *   restore     — вернуть из deleted
 *   publish     — одобрить
 *   reject      — отклонить/отозвать
 *   feature     — вывести в подборку
 *   purge       — необратимое удаление из БД
 *   view_log    — читать аудит-лог
 */
function acl_can(array $ctx, array $asset, string $action): bool
{
    if (empty($ctx['uid'])) return false;

    // Staff: все права. Кроме purge — он только для root.
    if (!empty($ctx['is_staff'])) {
        if ($action === 'purge') return !empty($ctx['is_root']);
        return true;
    }

    $own = acl_owns($ctx, $asset);
    if (!$own) return false;

    switch ($action) {
        case 'view':
        case 'edit':
        case 'edit_full':
        case 'submit':
        case 'hide':
        case 'delete':
        case 'restore':
        case 'view_log':
            return true;

        case 'publish':   // разработчик НЕ публикует сам
        case 'reject':
        case 'feature':
        case 'purge':
            return false;
    }
    return false;
}

/* ── Машина состояний ────────────────────────────────────────────────────── */
/**
 * Возвращает список допустимых целевых статусов для этого юзера и ассета.
 * Ключевая тонкость: hidden -> published разработчик может сам,
 * ТОЛЬКО если ассет уже когда-то проходил модерацию (published_at != NULL).
 * Иначе — на pending.
 */
function acl_transitions(array $ctx, array $asset): array
{
    $from  = (string)($asset['status'] ?? 'draft');
    $staff = !empty($ctx['is_staff']);
    $own   = acl_owns($ctx, $asset);
    if (!$staff && !$own) return [];

    $wasApproved = !empty($asset['published_at']);

    switch ($from) {
        case 'draft':
            return ['pending', 'deleted'];
        case 'pending':
            return $staff
                ? ['published', 'rejected', 'draft', 'deleted']
                : ['draft', 'deleted'];
        case 'published':
            return $staff
                ? ['hidden', 'rejected', 'deleted']
                : ['hidden', 'deleted'];
        case 'hidden':
            return ($staff || $wasApproved)
                ? ['published', 'deleted']
                : ['pending', 'deleted'];
        case 'rejected':
            return ['pending', 'deleted'];
        case 'deleted':
            return ['draft'];
    }
    return [];
}

/** Поля, правка которых сбрасывает опубликованный ассет обратно на модерацию. */
function asset_material_fields(): array
{
    return ['name', 'description', 'category', 'path_to_cover', 'previews',
            'file_size_bytes', 'contents', 'license', 'model_3d_url'];
}

/* ── Аудит ───────────────────────────────────────────────────────────────── */
function acl_log(
    PDO $pdo,
    array $ctx,
    int $assetId,
    string $action,
    ?string $from = null,
    ?string $to = null,
    ?string $note = null,
    ?array $payload = null
): void {
    try {
        $st = $pdo->prepare(
            "INSERT INTO asset_moderation_log
                (asset_id, actor_id, actor_role, as_staff, action,
                 status_from, status_to, note, payload, ip, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
        );
        $st->execute([
            $assetId,
            (int)$ctx['uid'],
            (int)$ctx['role'],
            !empty($ctx['is_staff']) ? 1 : 0,
            $action,
            $from,
            $to,
            $note,
            $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (PDOException $e) {
        // Лог не должен ронять действие, но должен быть виден.
        error_log('[acl] audit write failed: ' . $e->getMessage());
    }
}

/* ── CSRF ────────────────────────────────────────────────────────────────── */
function acl_csrf_token(): string
{
    if (empty($_SESSION['csrf_assets'])) {
        $_SESSION['csrf_assets'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_assets'];
}

function acl_csrf_check(?string $token): bool
{
    return !empty($_SESSION['csrf_assets'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_assets'], $token);
}