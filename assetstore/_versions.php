<?php
/**
 * assetstore/_versions.php
 * ---------------------------------------------------------------------------
 * История версий ассета + уведомление владельцам библиотеки об обновлении.
 *
 * Осознанно НЕ часть _acl.php: там про права, здесь — про историю и оповещение,
 * разные причины меняться. Подключается из edit_asset.php и api_admin.php,
 * то есть из обоих мест, где можно поменять assets.version.
 */

declare(strict_types=1);

require_once __DIR__ . '/_schema.php';

/**
 * Пишет запись в историю версий. Не пишет, если версия не изменилась
 * относительно последней сохранённой записи (защита от дублей при
 * повторной отправке формы с тем же значением).
 */
function asset_record_version(PDO $pdo, int $assetId, string $version, ?string $changelog, int $actorId): void
{
    AssetSchema::ensure($pdo);

    $version = trim($version);
    if ($version === '') return;

    $last = $pdo->prepare("SELECT version FROM asset_versions WHERE asset_id = ? ORDER BY id DESC LIMIT 1");
    $last->execute([$assetId]);
    $prev = $last->fetchColumn();
    if ($prev !== false && (string)$prev === $version) return;

    $pdo->prepare(
        "INSERT INTO asset_versions (asset_id, version, changelog, actor_id, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    )->execute([$assetId, $version, $changelog, $actorId]);
}

/**
 * Уведомляет всех, у кого ассет уже в библиотеке (asset_library), о новой
 * версии. Вызывать ТОЛЬКО когда ассет уже был published до этой правки —
 * до первой публикации у него по определению ещё нет владельцев,
 * а черновики не должны никого дёргать.
 */
function asset_notify_update(PDO $pdo, int $assetId, string $assetName, string $version, ?string $changelog): void
{
    // asset_library: (id, player_id, asset_id, date) — колонка называется
    // player_id, не user_id (проверено по buy_asset.php/get_asset_free.php).
    $st = $pdo->prepare("SELECT DISTINCT player_id FROM asset_library WHERE asset_id = ?");
    $st->execute([$assetId]);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) return;

    require_once __DIR__ . '/../swad/fx/core.php';
    Fx::use($pdo);

    $title = $assetName . ' обновлён до v' . $version;
    $body  = $changelog !== null && $changelog !== ''
        ? mb_substr($changelog, 0, 140)
        : 'Вышла новая версия ассета из вашей библиотеки.';

    Fx::notifyMany($ids, $title, $body, '/assetstore/asset.php?id=' . $assetId);
}

/** Для карточки ассета / модалки истории — последние N версий, новые сверху. */
function asset_version_history(PDO $pdo, int $assetId, int $limit = 20): array
{
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare(
        "SELECT v.*, u.username, u.first_name
           FROM asset_versions v
      LEFT JOIN users u ON u.id = v.actor_id
          WHERE v.asset_id = ?
       ORDER BY v.id DESC
          LIMIT " . max(1, min(100, $limit))
    );
    $st->execute([$assetId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
