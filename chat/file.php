<?php
declare(strict_types=1);
/**
 * chat/file.php?id=N[&thumb=1] — шлюз к вложениям чата.
 *
 * Отдаёт файл только тому, у кого есть право: владельцу загрузки (например,
 * свой звук уведомлений или только что загруженная картинка) либо участнику
 * беседы, в которой вложение отправлено или стоит обоями. Сам файл не проксируем — редирект
 * на подписанную ссылку в S3, живущую 10 минут.
 *
 * Редирект кэшируется браузером на 5 минут (private): картинки в треде при
 * каждом поллинге не дёргают PHP заново.
 */

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_files.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function deny(int $code): never { http_response_code($code); header('Cache-Control: no-store'); exit; }

$myId = (int)($_SESSION['USERDATA']['id'] ?? 0);
if ($myId <= 0) deny(401);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) deny(404);

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$st = $db->prepare("SELECT * FROM chat_files WHERE id=? AND status='ready' LIMIT 1");
$st->execute([$id]);
$f = $st->fetch(PDO::FETCH_ASSOC);
if (!$f) deny(404);

$allowed = (int)$f['owner_id'] === $myId;
if (!$allowed) {
    // вложение отправлено в беседу, где я участник или сотрудник студии
    $q = $db->prepare("SELECT DISTINCT m.conversation_id, c.type, c.studio_id
                         FROM messages m JOIN conversations c ON c.id = m.conversation_id
                        WHERE m.file_id = ? AND m.deleted_at IS NULL");
    $q->execute([$id]);
    $convs = $q->fetchAll(PDO::FETCH_ASSOC);
    // …или стоит обоями беседы (таблица появляется миграцией v3)
    try {
        $w = $db->prepare("SELECT DISTINCT w.conversation_id, c.type, c.studio_id
                             FROM conversation_wallpapers w JOIN conversations c ON c.id = w.conversation_id
                            WHERE w.file_id = ?");
        $w->execute([$id]);
        $convs = array_merge($convs, $w->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) { }
    if ($convs) {
        $p = $db->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id=? AND user_id=? LIMIT 1");
        $studios = null;
        foreach ($convs as $c) {
            $p->execute([(int)$c['conversation_id'], $myId]);
            if ($p->fetchColumn()) { $allowed = true; break; }
            if ($c['type'] === 'studio') {
                $studios ??= get_user_studio_ids($db, $myId);
                if (in_array((int)$c['studio_id'], $studios, true)) { $allowed = true; break; }
            }
        }
    }
}
if (!$allowed) deny(403);

header('Cache-Control: private, max-age=300');
header('Location: ' . chat_presign_get($f, !empty($_GET['thumb'])), true, 302);
