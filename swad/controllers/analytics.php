<?php

/**
 * swad/controllers/analytics.php
 *
 * Общий модуль аналитики. Не привязан к продвижению — пишет и читает события
 * для ЛЮБОГО объекта платформы (subject_type/subject_id), поэтому его можно
 * дальше переиспользовать для джемов, ассетов, статей и т.д. без новых миграций.
 *
 * Класс намеренно НЕ делает require config.php и не открывает своё соединение —
 * PDO передаётся снаружи, чтобы не плодить лишние коннекты на страницах, где
 * Database уже подключена.
 */

class Analytics
{
    private PDO $pdo;

    /** Через сколько считать pending-бронь слота протухшей (см. game_promotions). */
    public const PENDING_TTL_MINUTES = 20;

    private const SUBJECT_TYPES = ['promotion', 'game'];
    private const EVENT_TYPES   = ['impression', 'view', 'click', 'download', 'launch', 'playtime'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Анонимный идентификатор из cookie. Если куки нет — создаёт новую (1 год).
     * Вызывать ДО любого вывода в браузер (до <html>), иначе setcookie не сработает —
     * та же оговорка, что и для остальных POST-обработчиков в проекте.
     */
    public static function anonId(): string
    {
        if (!empty($_COOKIE['dstr_aid']) && preg_match('/^[0-9a-f-]{36}$/i', $_COOKIE['dstr_aid'])) {
            return $_COOKIE['dstr_aid'];
        }
        $id = self::uuid4();
        setcookie('dstr_aid', $id, [
            'expires'  => time() + 365 * 86400,
            'path'     => '/',
            'secure'   => true,
            'httponly' => false, // читается из JS-трекера
            'samesite' => 'Lax',
        ]);
        $_COOKIE['dstr_aid'] = $id;
        return $id;
    }

    private static function uuid4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    private static function detectPlatform(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/tablet|ipad/i', $ua))            return 'tablet';
        if (preg_match('/mobi|android|iphone/i', $ua))    return 'mobile';
        return 'desktop';
    }

    /**
     * Записать событие. user_id берётся из сессии автоматически, если он есть —
     * иначе пишем anon_id. Один посетитель — либо то, либо другое, никогда оба.
     */
    public function track(string $subjectType, int $subjectId, string $eventType, ?int $value = null, ?array $meta = null): bool
    {
        if (!in_array($subjectType, self::SUBJECT_TYPES, true)) return false;
        if (!in_array($eventType, self::EVENT_TYPES, true))     return false;
        if ($subjectId <= 0) return false;

        $userId = !empty($_SESSION['USERDATA']['id']) ? (int)$_SESSION['USERDATA']['id'] : null;
        $anonId = $userId ? null : self::anonId();

        $stmt = $this->pdo->prepare("
            INSERT INTO analytics_events
                (subject_type, subject_id, event_type, event_value, user_id, anon_id, platform, meta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $subjectType,
            $subjectId,
            $eventType,
            $value,
            $userId,
            $anonId,
            self::detectPlatform(),
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * То же, что track(), но не чаще одного раза за $windowSeconds на ОДНУ сессию.
     * Для impression/view — пассивных событий, которые иначе плодятся на каждый
     * F5: одна и та же вкладка/сессия не должна накручивать счётчик простым
     * обновлением страницы. Клики и скачивания — осознанное действие юзера,
     * их дедуплицировать не нужно, дедуп только для impression/view.
     */
    public function trackOncePerSession(string $subjectType, int $subjectId, string $eventType, int $windowSeconds = 1800, ?int $value = null, ?array $meta = null): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $key = 'az_' . $subjectType . '_' . $subjectId . '_' . $eventType;
            $now = time();
            if (!empty($_SESSION[$key]) && ($now - (int)$_SESSION[$key]) < $windowSeconds) {
                return true; // уже засчитано недавно в этой сессии — тихо пропускаем, это не ошибка
            }
            $_SESSION[$key] = $now;
        }
        return $this->track($subjectType, $subjectId, $eventType, $value, $meta);
    }

    /**
     * Агрегат по одному объекту за период: count + сумма event_value на каждый event_type.
     * Возвращает ['impression' => ['count'=>N,'value'=>N], 'view' => [...], ...] —
     * ключей, которых не было, в ответе просто не будет (проверяйте через ??).
     *
     * $from/$to — 'Y-m-d H:i:s' или null (без ограничения).
     */
    public function summarize(string $subjectType, int $subjectId, ?string $from = null, ?string $to = null): array
    {
        $params = [$subjectType, $subjectId];
        $sql = "SELECT event_type, COUNT(*) AS cnt, COALESCE(SUM(event_value), 0) AS total_value,
                       COUNT(DISTINCT COALESCE(user_id, anon_id)) AS uniq_visitors
                FROM analytics_events
                WHERE subject_type = ? AND subject_id = ?";
        if ($from !== null) {
            $sql .= " AND created_at >= ?";
            $params[] = $from;
        }
        if ($to   !== null) {
            $sql .= " AND created_at <  ?";
            $params[] = $to;
        }
        $sql .= " GROUP BY event_type";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['event_type']] = [
                'count'  => (int)$row['cnt'],
                'value'  => (int)$row['total_value'],
                'unique' => (int)$row['uniq_visitors'],
            ];
        }
        return $out;
    }

    /**
     * То же самое, но сразу для нескольких subject_id одного типа (список слотов
     * продвижения студии, например) — один запрос вместо N.
     * Возвращает [subject_id => ['impression'=>[...], ...]].
     */
    public function summarizeMany(string $subjectType, array $subjectIds, ?string $from = null, ?string $to = null): array
    {
        $subjectIds = array_values(array_unique(array_map('intval', $subjectIds)));
        if (!$subjectIds) return [];

        $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
        $params = array_merge([$subjectType], $subjectIds);
        $sql = "SELECT subject_id, event_type, COUNT(*) AS cnt, COALESCE(SUM(event_value), 0) AS total_value
                FROM analytics_events
                WHERE subject_type = ? AND subject_id IN ($placeholders)";
        if ($from !== null) {
            $sql .= " AND created_at >= ?";
            $params[] = $from;
        }
        if ($to   !== null) {
            $sql .= " AND created_at <  ?";
            $params[] = $to;
        }
        $sql .= " GROUP BY subject_id, event_type";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['subject_id']][$row['event_type']] = [
                'count' => (int)$row['cnt'],
                'value' => (int)$row['total_value'],
            ];
        }
        return $out;
    }
}
