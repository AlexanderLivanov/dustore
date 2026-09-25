<?php
declare(strict_types=1);
/**
 * devs/broadcast_lib.php — почтовая часть рассылок из /devs/notifications.
 *
 * Как устроена отправка писем:
 *   • при создании рассылки замораживается ОЧЕРЕДЬ адресатов (email_queue):
 *     только с почтой, при желании только подтверждённые, без отписавшихся,
 *     самые активные — первыми (они откроют письмо, а это главный сигнал
 *     почтовикам, что мы не спамеры);
 *   • bc_mail_run() идёт по очереди с курсора, по одному SMTP-соединению,
 *     с паузой между письмами. Дневной лимит (email_day_limit): набрали —
 *     пауза до завтра 10:00 (email_next_at). С «прогревом» лимит каждый новый
 *     день удваивается: 50 → 100 → 200 → …;
 *   • продолжение на следующий день — bc_mail_tick(): его дёргает
 *     chat/push_outbox.php, который воркер пушей и так опрашивает каждые 2 с.
 *     Для установок без воркера — cron: php devs/broadcast_mail.php;
 *   • от двойного запуска — захват email_running + пульс email_beat: если
 *     процесс убили, через 5 минут без пульса рассылку подхватят заново
 *     с того же места;
 *   • в каждом письме ссылка «Отписаться» и заголовки List-Unsubscribe
 *     (отписка в один клик в Gmail/Яндексе). Отписка — users.email_optout,
 *     действует только на рассылки: служебные письма (сброс пароля) идут.
 */

require_once __DIR__ . '/../chat/_bridge.php';

const BC_MAIL_DELAY_MS   = 1000;   // пауза между письмами; лимиты своего SMTP уточни у хостинга
const BC_MAIL_MAX_FAILS  = 10;     // ошибок подряд — это уже отказ сервера, а не плохой адрес
const BC_MAIL_CHECK_EACH = 10;     // как часто смотреть на кнопку «Остановить»
const BC_MAIL_RESUME_AT  = '10:00';// во сколько продолжать на следующий день
const BC_WARMUP_START    = 50;     // прогрев без явного лимита начинаем с этого

/* ── Схема ─────────────────────────────────────────────────────────────── */

function bc_add_column(PDO $db, string $table, string $col, string $def): void {
    if ($db->query("SHOW COLUMNS FROM {$table} LIKE '{$col}'")->fetch()) return;
    try { $db->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$def}"); } catch (Throwable $e) { error_log("[broadcast] {$table}.{$col}: " . $e->getMessage()); }
}

/** Создаёт/дополняет таблицы. Зовётся со страницы админки (не из горячих путей). */
function bc_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS broadcasts (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        created_by  INT NOT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        title       VARCHAR(120) NOT NULL,
        body        TEXT NOT NULL,
        url         VARCHAR(255) NULL,
        icon        VARCHAR(255) NULL,
        channels    VARCHAR(32) NOT NULL,
        audience    VARCHAR(16) NOT NULL,
        recipients  MEDIUMTEXT NOT NULL,
        n_users     INT NOT NULL DEFAULT 0,
        n_site      INT NOT NULL DEFAULT 0,
        n_push      INT NOT NULL DEFAULT 0,
        n_email     INT NOT NULL DEFAULT 0,
        email_sent  INT NOT NULL DEFAULT 0,
        email_fail  INT NOT NULL DEFAULT 0,
        email_done  TINYINT NOT NULL DEFAULT 0
    ) DEFAULT CHARSET=utf8mb4");
    foreach ([
        'email_stop'          => 'TINYINT NOT NULL DEFAULT 0',
        'email_error'         => 'VARCHAR(255) NULL',
        'email_queue'         => 'MEDIUMTEXT NULL',
        'email_cursor'        => 'INT NOT NULL DEFAULT 0',
        'email_skip'          => 'INT NOT NULL DEFAULT 0',
        'email_verified_only' => 'TINYINT NOT NULL DEFAULT 0',
        'email_day_limit'     => 'INT NOT NULL DEFAULT 0',
        'email_warmup'        => 'TINYINT NOT NULL DEFAULT 0',
        'email_day'           => 'DATE NULL',
        'email_day_n'         => 'INT NOT NULL DEFAULT 0',
        'email_day_sent'      => 'INT NOT NULL DEFAULT 0',
        'email_next_at'       => 'DATETIME NULL',
        'email_running'       => 'TINYINT NOT NULL DEFAULT 0',
        'email_beat'          => 'DATETIME NULL',
    ] as $col => $def) bc_add_column($db, 'broadcasts', $col, $def);
    // MariaDB 10.3+/MySQL 8: добавление колонки с DEFAULT — мгновенное, таблицу не перестраивает
    bc_add_column($db, 'users', 'email_optout', 'TINYINT NOT NULL DEFAULT 0');
}

/* ── Отписка ───────────────────────────────────────────────────────────── */

/** Ключ подписи ссылок: SECRET_KEY из swad/pass.php (им же подписан вход), иначе секрет моста. */
function bc_secret(): string {
    return defined('SECRET_KEY') ? (string)SECRET_KEY : bridge_secret();
}
function bc_unsub_token(int $uid): string {
    return substr(hash_hmac('sha256', 'unsub:' . $uid, bc_secret()), 0, 32);
}
function bc_unsub_url(int $uid): string {
    return 'https://dustore.ru/unsubscribe?u=' . $uid . '&t=' . bc_unsub_token($uid);
}

/* ── Очередь ───────────────────────────────────────────────────────────── */

/**
 * Кому из $ids реально уйдёт письмо, в порядке отправки: с адресом, без
 * отписки, при $verifiedOnly — только подтверждённые; активные — первыми.
 */
function bc_mail_queue(PDO $db, array $ids, bool $verifiedOnly): array {
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id FROM users WHERE id IN ($ph) AND email LIKE '%@%' AND email_optout = 0"
         . ($verifiedOnly ? ' AND email_verified = 1' : '')
         . ' ORDER BY last_activity IS NULL, last_activity DESC, id';
    $q = $db->prepare($sql);
    $q->execute(array_values($ids));
    return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

/* ── Отправка ──────────────────────────────────────────────────────────── */

/**
 * Отправить очередную порцию рассылки. Возвращает статус для лога:
 * busy (уже идёт / не пора), paused (дневной лимит), stopped, failed, done.
 */
function bc_mail_run(PDO $db, int $bid): string {
    // Захват: одна рассылка — один процесс. Упавший процесс перестаёт
    // обновлять пульс, и через 5 минут захват снова возможен.
    $lock = $db->prepare("UPDATE broadcasts SET email_running = 1, email_beat = NOW(), email_next_at = NULL
        WHERE id = ? AND email_done = 0 AND email_stop = 0
          AND (email_next_at IS NULL OR email_next_at <= NOW())
          AND (email_running = 0 OR email_beat IS NULL OR email_beat < NOW() - INTERVAL 5 MINUTE)");
    $lock->execute([$bid]);
    if ($lock->rowCount() !== 1) return 'busy';

    $st = $db->prepare("SELECT * FROM broadcasts WHERE id = ?");
    $st->execute([$bid]);
    $b = $st->fetch(PDO::FETCH_ASSOC);
    $set = function (string $sql, array $args) use ($db, $bid): void {
        $db->prepare("UPDATE broadcasts SET {$sql} WHERE id = ?")->execute([...$args, $bid]);
    };
    $finish = function (string $status, ?string $error = null) use ($set): string {
        $set('email_done = 1, email_running = 0, email_error = COALESCE(?, email_error)', [$error !== null ? mb_substr($error, 0, 250) : null]);
        return $status;
    };

    // рассылки, созданные до очереди, — собираем её сейчас из списка получателей
    $queue = json_decode((string)($b['email_queue'] ?? ''), true);
    if (!is_array($queue)) {
        $queue = bc_mail_queue($db, array_map('intval', json_decode((string)$b['recipients'], true) ?: []), (bool)$b['email_verified_only']);
        $set('email_queue = ?, n_email = ?', [json_encode($queue), count($queue)]);
    }

    // новый день: счётчик дня с нуля, при прогреве лимит ×2 (кроме самого первого дня)
    $today = date('Y-m-d');
    $limit = (int)$b['email_day_limit'];
    $dayN  = (int)$b['email_day_n'];
    $daySent = (int)$b['email_day_sent'];
    if ((string)$b['email_day'] !== $today) {
        if ($b['email_day'] !== null && (int)$b['email_warmup'] && $limit > 0) $limit *= 2;
        $dayN++; $daySent = 0;
        $set('email_day = ?, email_day_n = ?, email_day_sent = 0, email_day_limit = ?', [$today, $dayN, $limit]);
    }

    $cursor = (int)$b['email_cursor'];
    if ($cursor >= count($queue)) return $finish('done');

    try {
        $mail = mailer_batch();
    } catch (Throwable $e) {
        return $finish('failed', 'Почта не настроена: ' . $e->getMessage());
    }

    $url  = (string)($b['url'] ?? '');
    $link = $url === '' ? '' : ($url[0] === '/' ? 'https://dustore.ru' . $url : $url);
    $tpl  = buildEmail(
        htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'),
        '<p style="color:#cfcfe0;font-size:15px;line-height:1.6;margin:0 0 16px;">' . nl2br(htmlspecialchars($b['body'], ENT_QUOTES, 'UTF-8')) . '</p>',
        $link !== '' ? 'Открыть' : '',
        htmlspecialchars($link, ENT_QUOTES, 'UTF-8')
    );
    // «почему вы получили письмо» + отписка — последней строкой, перед подвалом
    $tpl = str_replace('Это письмо отправлено автоматически',
        'Вы получили это письмо как пользователь Dustore. <a href="%UNSUB%" style="color:#9a9ab0;">Отписаться от рассылок</a><br>Это письмо отправлено автоматически', $tpl);

    $user  = $db->prepare("SELECT email, email_optout FROM users WHERE id = ?");
    $stopQ = $db->prepare("SELECT email_stop FROM broadcasts WHERE id = ?");
    $step  = $db->prepare("UPDATE broadcasts SET email_sent = email_sent + ?, email_fail = email_fail + ?, email_skip = email_skip + ?,
                              email_cursor = ?, email_day_sent = ?, email_beat = NOW(), email_error = COALESCE(?, email_error) WHERE id = ?");
    $streak = 0; $n = 0;
    while ($cursor < count($queue)) {
        if ($limit > 0 && $daySent >= $limit) {
            $mail->smtpClose();
            $next = (new DateTime('tomorrow ' . BC_MAIL_RESUME_AT))->format('Y-m-d H:i:s');
            $set('email_running = 0, email_next_at = ?', [$next]);
            return 'paused';
        }
        if ($n++ % BC_MAIL_CHECK_EACH === 0) {
            $stopQ->execute([$bid]);
            if ((int)$stopQ->fetchColumn() === 1) { $mail->smtpClose(); return $finish('stopped'); }
        }
        $uid = $queue[$cursor];
        $user->execute([$uid]);
        $u = $user->fetch(PDO::FETCH_ASSOC);
        $cursor++;
        if (!$u || (int)$u['email_optout'] === 1 || !str_contains((string)$u['email'], '@')) {
            $step->execute([0, 0, 1, $cursor, $daySent, null, $bid]);      // отписался или сменил почту после создания
            continue;
        }
        $unsub = bc_unsub_url($uid);
        $err = mailer_send($mail, (string)$u['email'], (string)$b['title'], str_replace('%UNSUB%', htmlspecialchars($unsub, ENT_QUOTES, 'UTF-8'), $tpl), [
            'List-Unsubscribe'      => '<' . $unsub . '>, <mailto:dusty@dustore.ru?subject=unsubscribe>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
        if ($err !== null) error_log("[broadcast #{$bid}] {$u['email']}: {$err}");
        $daySent++;
        $step->execute([$err === null ? 1 : 0, $err === null ? 0 : 1, 0, $cursor, $daySent, $err, $bid]);

        $streak = $err === null ? 0 : $streak + 1;
        if ($streak >= BC_MAIL_MAX_FAILS) {
            $mail->smtpClose();
            return $finish('failed', "Остановлено: {$streak} ошибок подряд. Последняя: {$err}");
        }
        usleep(BC_MAIL_DELAY_MS * 1000);
    }
    $mail->smtpClose();
    return $finish('done');
}

/**
 * «Пинок»: запустить рассылки, которым пора (наступил следующий день или
 * процесс умер). Не чаще раза в минуту — вызывается из горячего пути.
 */
function bc_mail_tick(PDO $db, callable $launch, bool $throttle = true): int {
    if ($throttle) {
        $mark = sys_get_temp_dir() . '/dustore_bc_tick';
        if (is_file($mark) && time() - (int)@filemtime($mark) < 60) return 0;
        @touch($mark);
    }
    try {
        $ids = $db->query("SELECT id FROM broadcasts
            WHERE email_done = 0 AND email_stop = 0
              AND ((email_running = 0 AND (email_next_at IS NULL OR email_next_at <= NOW()))
                OR (email_running = 1 AND (email_beat IS NULL OR email_beat < NOW() - INTERVAL 5 MINUTE)))
            ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return 0;                  // таблицы ещё нет или старая схема — рассылок нет
    }
    foreach ($ids as $id) $launch((int)$id);
    return count($ids);
}
