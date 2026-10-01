<?php
// swad/controllers/jams/presence.php
// «Сейчас ждут итогов»: счётчик посетителей страницы голосования.
// Принцип: страница раз в ~20 с шлёт «я тут» (heartbeat, только пока вкладка видна);
// онлайн = те, кого видели за последние 50 с. Один браузер = один sid, сколько бы вкладок ни было.
// POST JSON: { sprint_id, sid }  ->  { ok, online }

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../csrf.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pr_out(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d); exit; }

const PRESENCE_TTL = 50;   // сек: после последнего сигнала считаем «ушёл»
const PRESENCE_IP_CAP = 10; // макс. разных sid с одного IP — защита от накрутки

$in  = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_valid($in)) pr_out(['ok' => false], 403);
$sid = (string)($in['sid'] ?? '');
$spr = (int)($in['sprint_id'] ?? 0);
if (!$spr || !preg_match('/^[a-f0-9]{32}$/', $sid)) pr_out(['ok' => false], 400);

$pdo = (new Database())->connect();
if (!$pdo) pr_out(['ok' => false], 500);

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS jam_presence (
        sprint_id INT NOT NULL,
        sid       CHAR(32) NOT NULL,
        ip_hash   CHAR(16) NOT NULL,
        seen_at   INT UNSIGNED NOT NULL,
        PRIMARY KEY (sprint_id, sid),
        KEY idx_seen (sprint_id, seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $now = time();
    $ip  = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|dustore-presence'), 0, 16);
    $alive = $now - PRESENCE_TTL;

    $c = $pdo->prepare("SELECT COUNT(*) FROM jam_presence WHERE sprint_id=? AND ip_hash=? AND seen_at>? AND sid<>?");
    $c->execute([$spr, $ip, $alive, $sid]);
    if ((int)$c->fetchColumn() < PRESENCE_IP_CAP) {
        $pdo->prepare("INSERT INTO jam_presence (sprint_id, sid, ip_hash, seen_at) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE ip_hash=VALUES(ip_hash), seen_at=VALUES(seen_at)")
            ->execute([$spr, $sid, $ip, $now]);
    }
    if (mt_rand(1, 50) === 1) $pdo->prepare("DELETE FROM jam_presence WHERE seen_at < ?")->execute([$now - 3600]);

    $n = $pdo->prepare("SELECT COUNT(*) FROM jam_presence WHERE sprint_id=? AND seen_at>?");
    $n->execute([$spr, $alive]);
    pr_out(['ok' => true, 'online' => (int)$n->fetchColumn()]);
} catch (Throwable $e) {
    pr_out(['ok' => false], 500);
}
