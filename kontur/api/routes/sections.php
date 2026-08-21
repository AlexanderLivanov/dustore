<?php
// ============================================================
// ROUTES/SECTIONS.PHP — разделы сайдбара
// ============================================================

function handle_sections(string $method, array $parts): void
{
    $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;

    if ($id === null) {
        match ($method) {
            'GET'  => sections_list(),
            'POST' => section_create(),
            default => json_error('Метод не поддерживается', 405),
        };
        return;
    }
    if ($method === 'DELETE') {
        section_delete($id); return;
    }
    json_error('Маршрут не найден', 404);
}

// ── Список (все могут читать) ────────────────────────────
function sections_list(): void
{
    $stmt = db()->query(
        'SELECT id, name, kind, position FROM sections ORDER BY kind, position, id'
    );
    $rows = $stmt->fetchAll();

    // Группируем на main / fan для удобства фронта
    $out = ['main' => [], 'fan' => []];
    foreach ($rows as $r) {
        $out[$r['kind']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
    }
    json_response(['sections' => $out]);
}

// ── Добавить раздел (только админ) ───────────────────────
function section_create(): void
{
    require_admin();
    $data = read_json_body();
    $name = require_field($data, 'name', 64);
    $kind = $data['kind'] ?? 'main';
    if (!in_array($kind, ['main', 'fan'], true)) {
        json_error('kind должно быть main или fan', 422);
    }

    // Ставим в конец
    $pos = db()->prepare('SELECT COALESCE(MAX(position),0)+1 AS p FROM sections WHERE kind = ?');
    $pos->execute([$kind]);
    $position = (int) $pos->fetch()['p'];

    $stmt = db()->prepare('INSERT INTO sections (name, kind, position) VALUES (?, ?, ?)');
    $stmt->execute([$name, $kind, $position]);

    json_response(['id' => (int) db()->lastInsertId(), 'name' => $name, 'kind' => $kind], 201);
}

// ── Удалить раздел (только админ) ────────────────────────
function section_delete(int $id): void
{
    require_admin();
    $stmt = db()->prepare('DELETE FROM sections WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}
