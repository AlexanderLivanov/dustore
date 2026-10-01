<?php
/**
 * assetstore/_bundles.php — наборы ассетов (Task C, «прогрессивные» фичи).
 * ---------------------------------------------------------------------------
 * Набор — отдельная сущность, не строка в assets: нет файла, версий, тегов.
 * Публикует владелец студии сам, из СВОИХ уже опубликованных ассетов — своей
 * модерации у наборов нет (см. коммит: осознанно урезанный скоуп, чтобы не
 * тащить ACL-машину состояний ради второстепенной фичи).
 */

declare(strict_types=1);

require_once __DIR__ . '/_schema.php';

function asset_bundle_by_id(PDO $pdo, int $id): ?array
{
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare("SELECT b.*, s.name AS studio_name, s.display_name AS studio_display
                            FROM asset_bundles b LEFT JOIN studios s ON s.id = b.studio_id
                           WHERE b.id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Ассеты набора — только реально опубликованные (если что-то потом сняли с витрины, не выпадает из набора видимо-битой строкой). */
function asset_bundle_items(PDO $pdo, int $bundleId): array
{
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare(
        "SELECT a.* FROM asset_bundle_items bi
           JOIN assets a ON a.id = bi.asset_id
          WHERE bi.bundle_id = ? AND a.status = 'published'
       ORDER BY bi.sort ASC"
    );
    $st->execute([$bundleId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Сумма цен ассетов набора по отдельности — для "вы экономите X ₽" на витрине. */
function asset_bundle_items_sum(PDO $pdo, int $bundleId): float
{
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(a.price),0) FROM asset_bundle_items bi
           JOIN assets a ON a.id = bi.asset_id
          WHERE bi.bundle_id = ? AND a.status = 'published'"
    );
    $st->execute([$bundleId]);
    return (float)$st->fetchColumn();
}

function asset_bundles_for_studio(PDO $pdo, int $studioId): array
{
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare("SELECT * FROM asset_bundles WHERE studio_id = ? ORDER BY created_at DESC");
    $st->execute([$studioId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Витрина: опубликованные наборы, у которых реально остался хотя бы 1 ассет. */
function asset_bundles_published(PDO $pdo, int $limit = 24, int $offset = 0): array
{
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare(
        "SELECT b.*, s.name AS studio_name, s.display_name AS studio_display,
                (SELECT COUNT(*) FROM asset_bundle_items bi JOIN assets a ON a.id=bi.asset_id
                  WHERE bi.bundle_id=b.id AND a.status='published') AS items_count
           FROM asset_bundles b LEFT JOIN studios s ON s.id = b.studio_id
          WHERE b.status = 'published'
         HAVING items_count > 0
         ORDER BY b.created_at DESC
          LIMIT " . max(1, min(100, $limit)) . " OFFSET " . max(0, $offset)
    );
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Создать/обновить набор. $assetIds — только те, что реально принадлежат
 * studio_id и опубликованы (иначе можно было бы собрать набор из чужого
 * каталога, минуя владение).
 */
function asset_bundle_save(PDO $pdo, int $studioId, ?int $bundleId, string $name, string $description, float $price, string $status, array $assetIds): int
{
    AssetSchema::ensure($pdo);
    $name  = mb_substr(trim($name), 0, 128);
    $price = max(0, round($price, 2));
    $status = in_array($status, ['draft', 'published'], true) ? $status : 'draft';

    if ($bundleId) {
        $pdo->prepare("UPDATE asset_bundles SET name=?, description=?, price=?, status=?, updated_at=NOW() WHERE id=? AND studio_id=?")
            ->execute([$name, $description, $price, $status, $bundleId, $studioId]);
    } else {
        $pdo->prepare("INSERT INTO asset_bundles (studio_id, name, description, price, status, created_at) VALUES (?,?,?,?,?,NOW())")
            ->execute([$studioId, $name, $description, $price, $status]);
        $bundleId = (int)$pdo->lastInsertId();
    }

    // Только реально свои опубликованные ассеты — replace-all по позициям.
    $valid = [];
    if ($assetIds) {
        $in = implode(',', array_fill(0, count($assetIds), '?'));
        $chk = $pdo->prepare("SELECT id FROM assets WHERE id IN ($in) AND studio_id = ? AND status = 'published'");
        $chk->execute([...$assetIds, $studioId]);
        $valid = array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN));
    }

    $pdo->prepare("DELETE FROM asset_bundle_items WHERE bundle_id = ?")->execute([$bundleId]);
    $ins = $pdo->prepare("INSERT INTO asset_bundle_items (bundle_id, asset_id, sort) VALUES (?,?,?)");
    foreach (array_values($valid) as $i => $aid) $ins->execute([$bundleId, $aid, $i]);

    return $bundleId;
}

/** Уже купленный набор целиком (по строке в asset_bundle_payments), для CTA на витрине. */
function asset_bundle_is_owned(PDO $pdo, int $userId, int $bundleId): bool
{
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare("SELECT 1 FROM asset_bundle_payments WHERE user_id=? AND bundle_id=? AND status='succeeded' LIMIT 1");
    $st->execute([$userId, $bundleId]);
    return (bool)$st->fetchColumn();
}

/**
 * Выдаёт пользователю все ассеты набора, которых у него ещё нет в
 * библиотеке (то, чем он уже владеет — не трогаем и не перезаписываем
 * его tier_id по отдельной покупке). Вызывать ТОЛЬКО после succeeded-оплаты.
 */
function asset_bundle_grant(PDO $pdo, int $userId, int $bundleId): void
{
    $items = asset_bundle_items($pdo, $bundleId);
    if (!$items) return;
    $ins = $pdo->prepare("INSERT IGNORE INTO asset_library (player_id, asset_id, date) VALUES (?, ?, NOW())");
    $bump = $pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?");
    foreach ($items as $a) {
        $ins->execute([$userId, (int)$a['id']]);
        if ($ins->rowCount() > 0) $bump->execute([(int)$a['id']]);
    }
}
