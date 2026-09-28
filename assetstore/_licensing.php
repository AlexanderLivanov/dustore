<?php
/**
 * assetstore/_licensing.php — уровни лицензии (персональная/коммерческая/
 * расширенная) как НАДстройка над assets.price/assets.license.
 * ---------------------------------------------------------------------------
 * Идея: не переизобретать модель цены, а положить тарифы поверх неё.
 *   - Нет строк в asset_license_tiers -> ассет продаётся как раньше, одной
 *     ценой (assets.price/license). Ничего нигде не ломается.
 *   - Есть строки -> это и есть источник правды о цене; assets.price
 *     синхронизируется на MIN(price) тарифов, чтобы все места, которые его
 *     просто читают (витрина, manage.php, my_assets.php, аналитика),
 *     продолжали показывать разумное число без единой правки в них.
 *
 * Свободные (price=0) ассеты тарифов не имеют по определению — бесплатно
 * всем один раз, лицензировать тут нечего (get_asset_free.php их и обрабатывает).
 */

declare(strict_types=1);

require_once __DIR__ . '/_schema.php';

function asset_tier_labels(): array
{
    return [
        'personal'   => 'Личная',
        'commercial' => 'Коммерческая',
        'extended'   => 'Расширенная',
    ];
}

/** Тарифы ассета, дешёвый сверху. Пустой массив = тарифов нет, цена — flat. */
function asset_get_license_tiers(PDO $pdo, int $assetId): array
{
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare(
        "SELECT * FROM asset_license_tiers WHERE asset_id = ? ORDER BY sort ASC, price ASC"
    );
    $st->execute([$assetId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Полная замена набора тарифов ассета.
 * $tiers: ['personal' => ['price'=>499.0,'terms'=>'...'], 'commercial' => [...]]
 * Ключи — только включённые дизайнером тарифы; отсутствующие в массиве
 * тарифы удаляются. Пустой $tiers = тарифы выключены полностью (ассет
 * возвращается к обычной flat-цене, assets.price руками правит edit_asset.php
 * как и раньше — здесь его не трогаем).
 */
function asset_save_license_tiers(PDO $pdo, int $assetId, array $tiers): void
{
    AssetSchema::ensure($pdo);
    $labels = asset_tier_labels();
    $sortOrder = array_flip(array_keys($labels)); // personal=0, commercial=1, extended=2

    $pdo->prepare("DELETE FROM asset_license_tiers WHERE asset_id = ?")->execute([$assetId]);

    if (!$tiers) return;

    $ins = $pdo->prepare(
        "INSERT INTO asset_license_tiers (asset_id, tier, label, price, terms, sort, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $minPrice = null;
    foreach ($tiers as $tier => $row) {
        if (!isset($labels[$tier])) continue; // неизвестный ключ тарифа игнорируем
        $price = max(0, round((float)($row['price'] ?? 0), 2));
        $terms = trim((string)($row['terms'] ?? ''));
        $ins->execute([
            $assetId, $tier, $labels[$tier], $price, $terms !== '' ? $terms : null,
            $sortOrder[$tier] ?? 9,
        ]);
        if ($minPrice === null || $price < $minPrice) $minPrice = $price;
    }

    // Синхронизация витринной цены — единственный источник правды для тех,
    // кто просто читает assets.price, не зная о тарифах вообще.
    if ($minPrice !== null) {
        $pdo->prepare("UPDATE assets SET price = ? WHERE id = ?")->execute([$minPrice, $assetId]);
    }
}

/**
 * Разбирает плоские поля формы tier_{personal,commercial,extended}_{on,price,terms}
 * в массив для asset_save_license_tiers(). Общий парсер для upload_asset.php
 * и edit_asset.php, чтобы разметка полей формы не могла разъехаться между ними.
 */
function asset_parse_tier_input(array $post): array
{
    $out = [];
    foreach (array_keys(asset_tier_labels()) as $tier) {
        if (empty($post["tier_{$tier}_on"])) continue;
        $out[$tier] = [
            'price' => (float)($post["tier_{$tier}_price"] ?? 0),
            'terms' => trim((string)($post["tier_{$tier}_terms"] ?? '')),
        ];
    }
    return $out;
}

/** Конкретный тариф по id, с проверкой что он и правда принадлежит этому ассету. */
function asset_tier_by_id(PDO $pdo, int $tierId, int $assetId): ?array
{
    if ($tierId <= 0) return null;
    AssetSchema::ensure($pdo);
    $st = $pdo->prepare("SELECT * FROM asset_license_tiers WHERE id = ? AND asset_id = ? LIMIT 1");
    $st->execute([$tierId, $assetId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
