<?php
class Game
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getGameById($id)
    {
        $stmt = $this->db->connect()->prepare("
        SELECT 
            g.*,
            s.name AS studio_name,
            s.created_at AS studio_founded,
            s.tiker AS studio_slug
        FROM games g
        JOIN studios s ON g.developer = s.id
        WHERE g.id = ?
    ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    

    public function getLatestGames($limit = 99999)
    {
        $stmt = $this->db->connect()->prepare("
            SELECT
                g.id,
                g.name,
                g.description,
                g.short_description,
                g.path_to_cover,
                g.screenshots,
                g.price,
                g.GQI,
                g.status,
                g.age_rating,
                g.release_date,
                g.created_at,
                g.genre,
                g.updated_at,
                g.hidden,
                COALESCE(dl.n, 0)        AS downloads,
                ROUND(rv.avg_rating, 1)  AS avg_rating,
                COALESCE(rv.n, 0)        AS reviews_count,
                s.name AS studio_name
            FROM games g
            JOIN studios s ON g.developer = s.id
            /* Было: (SELECT COUNT(*) FROM library l WHERE l.game_id = g.id)
               — коррелированный подзапрос выполнялся ДЛЯ КАЖДОЙ игры.
               Сгруппированный LEFT JOIN считает всё за один проход. */
            LEFT JOIN (
                SELECT game_id, COUNT(*) AS n
                FROM library GROUP BY game_id
            ) dl ON dl.game_id = g.id
            LEFT JOIN (
                SELECT game_id, AVG(rating) AS avg_rating, COUNT(*) AS n
                FROM game_reviews GROUP BY game_id
            ) rv ON rv.game_id = g.id
            ORDER BY g.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Выборка для витрины: фильтры, сортировка и постраничность — в SQL.
     *
     * Раньше витрина звала getLatestGames(99999): вся таблица уезжала в PHP,
     * там же фильтровалась через array_filter и сортировалась через usort.
     * Пагинация на клиенте это бы не вылечила — база и PHP всё равно
     * перемалывали бы всё целиком на КАЖДУЮ подгрузку.
     *
     * $f: genre, adult, sort, dir, price_type, price_max, q, limit, offset
     * Возвращает ['items' => [...], 'total' => N].
     */
    public function queryGames(array $f): array
    {
        $db = $this->db->connect();

        $where  = ["g.status = 'published'", "(g.hidden IS NULL OR g.hidden = 0)"];
        $params = [];

        // 18+
        if (!empty($f['adult'])) {
            $where[] = "g.age_rating >= 18";
        } else {
            $where[] = "(g.age_rating IS NULL OR g.age_rating < 18)";
        }

        /* Жанры лежат строкой через запятую («Экшен, Инди»). Нормализуем
           разделители и ищем точное вхождение, а не LIKE '%Инди%' —
           иначе «Инди» матчило бы «Инди-хоррор». Сравнение регистронезависимо
           за счёт коллации utf8mb4_general_ci. */
        if (!empty($f['genre'])) {
            /* TRIM обязателен: без него жанр, записанный с пробелами по краям
               (" Инди "), нормализуется в ", Инди ," и не совпадает с ",Инди,".
               Проверено на живой БД — такие строки в каталоге встречаются. */
            $where[] = "CONCAT(',', TRIM(REPLACE(REPLACE(g.genre, ', ', ','), ' ,', ',')), ',')
                        LIKE CONCAT('%,', ?, ',%')";
            $params[] = $f['genre'];
        }

        // Цена
        if (($f['price_type'] ?? 'all') === 'free') {
            $where[] = "COALESCE(g.price, 0) = 0";
        } elseif (($f['price_type'] ?? 'all') === 'paid') {
            $where[] = "g.price > 0 AND g.price <= ?";
            $params[] = (float)($f['price_max'] ?? 5000);
        }

        /* Поиск.
           Порог был >= 2 символов, и это тихо ломало ожидания: на запрос из
           одной буквы условие не добавлялось вообще, сервер возвращал ВЕСЬ
           каталог, а человек видел «поиск не работает». Раз фильтрует база,
           а не перебор в PHP, экономить на одном символе незачем. */
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            // % и _ — метасимволы LIKE, без экранирования «%» вернул бы всё
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = "(g.name LIKE ? OR g.genre LIKE ? OR g.short_description LIKE ?)";
            array_push($params, $like, $like, $like);
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // Сортировка — только из белого списка, значения в SQL не подставляются
        $sortMap = [
            'popularity' => 'downloads',
            'price'      => 'g.price',
            'date'       => 'g.release_date',
            'updated'    => 'g.updated_at',
        ];
        $sortCol = $sortMap[$f['sort'] ?? 'popularity'] ?? 'downloads';
        $dir     = (($f['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

        $limit  = max(1, min(96, (int)($f['limit'] ?? 48)));
        $offset = max(0, (int)($f['offset'] ?? 0));

        $joins = "
            JOIN studios s ON g.developer = s.id
            LEFT JOIN (SELECT game_id, COUNT(*) AS n FROM library GROUP BY game_id) dl
                   ON dl.game_id = g.id
            LEFT JOIN (SELECT game_id, AVG(rating) AS avg_rating, COUNT(*) AS n
                         FROM game_reviews GROUP BY game_id) rv
                   ON rv.game_id = g.id
        ";

        $cnt = $db->prepare("SELECT COUNT(*) FROM games g JOIN studios s ON g.developer = s.id $whereSql");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        /* LIMIT/OFFSET подставляем числами: они приведены к int и зажаты
           диапазоном выше, а MySQL не принимает их плейсхолдерами при
           выключенной эмуляции подготовленных запросов. */
        $sql = "
            SELECT
                g.id, g.name, g.description, g.short_description,
                g.path_to_cover, g.screenshots, g.price, g.status,
                g.age_rating, g.release_date, g.created_at, g.genre,
                g.updated_at, g.hidden,
                COALESCE(dl.n, 0)       AS downloads,
                ROUND(rv.avg_rating, 1) AS avg_rating,
                COALESCE(rv.n, 0)       AS reviews_count,
                s.name AS studio_name
            FROM games g
            $joins
            $whereSql
            ORDER BY $sortCol $dir, g.id DESC
            LIMIT $limit OFFSET $offset
        ";
        $st = $db->prepare($sql);
        $st->execute($params);

        return ['items' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Список жанров для панели фильтров.
     * Считается по всей выдаче раздела (с учётом 18+), но БЕЗ учёта
     * выбранного жанра — иначе выбор одного жанра стирал бы остальные.
     */
    public function collectGenres(bool $adult = false): array
    {
        $db = $this->db->connect();
        $cond = $adult ? "g.age_rating >= 18" : "(g.age_rating IS NULL OR g.age_rating < 18)";

        $rows = $db->query("
            SELECT DISTINCT g.genre FROM games g
             WHERE g.status = 'published' AND (g.hidden IS NULL OR g.hidden = 0)
               AND g.genre IS NOT NULL AND g.genre <> '' AND $cond
        ")->fetchAll(PDO::FETCH_COLUMN);

        $out = [];
        foreach ($rows as $raw) {
            foreach (array_map('trim', explode(',', (string)$raw)) as $g) {
                if ($g !== '' && !in_array($g, $out, true)) $out[] = $g;
            }
        }
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    // 09.11.2025 (с) Alexander Livanov
    public function addRating($gameId, $userId, $rating)
    {
        $db = $this->db->connect();

        // Проверяем, ставил ли пользователь уже оценку
        $stmt = $db->prepare("SELECT id FROM ratings WHERE game_id = ? AND user_id = ?");
        $stmt->execute([$gameId, $userId]);

        if ($stmt->fetch()) {
            // Обновляем существующую
            $update = $db->prepare("UPDATE ratings SET rating = ?, created_at = NOW() WHERE game_id = ? AND user_id = ?");
            return $update->execute([$rating, $gameId, $userId]);
        } else {
            // Добавляем новую
            $insert = $db->prepare("INSERT INTO ratings (game_id, user_id, rating) VALUES (?, ?, ?)");
            return $insert->execute([$gameId, $userId, $rating]);
        }
    }

    public function getAverageRating($gameId)
    {
        $stmt = $this->db->connect()->prepare("
        SELECT AVG(rating) AS avg_rating, COUNT(*) AS total
        FROM game_reviews
        WHERE game_id = ?
    ");
        $stmt->execute([$gameId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'avg' => $row['avg_rating'] ? round($row['avg_rating'], 1) : 0,
            'count' => $row['total'] ?? 0
        ];
    }

    public function userHasRated($gameId, $userId)
    {
        $stmt = $this->db->connect()->prepare("SELECT 1 FROM ratings WHERE game_id = ? AND user_id = ?");
        $stmt->execute([$gameId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    // Тонкая обёртка над getReviewsArray(): один источник правды по SQL.
    // 22.01.2026 (c) Alexander Livanov / рефакторинг 14.08.2026
    public function getReviews($game_id)
    {
        echo json_encode(
            ['success' => true, 'reviews' => $this->getReviewsArray($game_id)],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Единственное место, где формируется отзыв для выдачи.
     * ВАЖНО: ответ студии (developer_reply) обязан приезжать отсюда —
     * фронт (game.php, assetstore/asset.php) рисует блок по этому полю.
     */
    public function getReviewsArray($game_id)
    {
        $sql = "
            SELECT
                r.id,
                r.user_id,
                r.game_id,
                r.rating,
                r.text,
                r.created_at,
                u.username,
                u.profile_picture,
                rr.text       AS developer_reply,
                rr.created_at AS developer_reply_created_at
            FROM game_reviews r
            LEFT JOIN users u ON u.id = r.user_id
            -- LEFT, а не JOIN: битая ссылка на игру не должна прятать отзыв
            LEFT JOIN games g ON g.id = r.game_id
            -- на пару (review_id, studio_id) стоит уникальный ключ
            -- (см. ON DUPLICATE KEY в devs/replies.php), строки не дублируются
            LEFT JOIN review_replies rr
                   ON rr.review_id = r.id
                  AND rr.studio_id = g.developer
            WHERE r.game_id = ?
            ORDER BY r.created_at DESC
        ";
        $stmt = $this->db->connect()->prepare($sql);
        $stmt->execute([$game_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function userHasReview($gameId, $userId)
    {
        $stmt = $this->db->connect()->prepare("SELECT 1 FROM game_reviews WHERE game_id = ? AND user_id = ?");
        $stmt->execute([$gameId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function submitReview($gameId, $userId, $rating, $text)
    {
        $stmt = $this->db->connect()->prepare("
            INSERT INTO game_reviews (game_id, user_id, rating, text, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$gameId, $userId, $rating, $text]);
    }

    // 13.12.2025 (c) Alexander Livanov
    public function getTotalDownloads($gameId){
        $stmt = $this->db->connect()->prepare("
            SELECT * FROM library WHERE game_id = ?
        ");
        $stmt->execute([$gameId]);
        return count($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}