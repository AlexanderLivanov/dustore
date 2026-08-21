<?php
/* ВРЕМЕННЫЙ ДИАГНОСТ. Удалить после проверки.
   Открыть: /swad/controllers/_check_replies.php?game_id=<id>
   Доступ: только global_role = -1 (root). */

session_start();
require_once('../config.php');

$role = $_SESSION['USERDATA']['global_role'] ?? null;
if ((int)$role !== -1) {
    http_response_code(403);
    exit('403');
}

header('Content-Type: text/plain; charset=utf-8');
$pdo = (new Database())->connect();

function h(string $t): void
{
    echo "\n" . str_repeat('─', 60) . "\n$t\n" . str_repeat('─', 60) . "\n";
}
function safe(callable $f)
{
    try {
        return $f();
    } catch (Throwable $e) {
        return 'ОШИБКА: ' . $e->getMessage();
    }
}

h('1. Таблица review_replies');
$cols = safe(fn() => $pdo->query("SHOW COLUMNS FROM review_replies")->fetchAll(PDO::FETCH_COLUMN));
print_r($cols);
echo "Всего ответов: " . safe(fn() => $pdo->query("SELECT COUNT(*) FROM review_replies")->fetchColumn()) . "\n";

h('2. Уникальный ключ (нужен для ON DUPLICATE KEY и от дублей в JOIN)');
print_r(safe(fn() => $pdo->query("SHOW INDEX FROM review_replies")->fetchAll(PDO::FETCH_ASSOC)));

h('3. Сходятся ли studio_id ответов с games.developer');
print_r(safe(fn() => $pdo->query("
    SELECT rr.id reply_id, rr.review_id, rr.studio_id, g.developer AS game_developer,
           (rr.studio_id = g.developer) AS matches
    FROM review_replies rr
    JOIN game_reviews r ON r.id = rr.review_id
    LEFT JOIN games g   ON g.id = r.game_id
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC)));

h('4. Что реально вернёт getReviewsArray()');
$gid = (int)($_GET['game_id'] ?? 0);
if ($gid <= 0) {
    echo "Передай ?game_id=<id> игры, у которой студия отвечала.\n";
} else {
    require_once('game.php');
    $rows = (new Game())->getReviewsArray($gid);
    echo "Отзывов: " . count($rows) . "\n";
    foreach ($rows as $r) {
        printf("#%-5s %-18s reply=%s\n", $r['id'], mb_substr((string)$r['username'], 0, 18),
            ($r['developer_reply'] ?? null) === null ? 'NULL' : '«' . mb_substr($r['developer_reply'], 0, 40) . '»');
    }
}

h('5. asset_reviews — есть ли там вообще колонка ответа');
print_r(safe(fn() => $pdo->query("SHOW COLUMNS FROM asset_reviews")->fetchAll(PDO::FETCH_COLUMN)));

echo "\n\n>>> Удали этот файл после проверки.\n";
