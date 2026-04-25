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
                g.path_to_cover,
                g.price,
                g.GQI,
                g.status,
                g.age_rating,
                g.release_date,
                g.genre,                     
                s.name AS studio_name
            FROM games g
            JOIN studios s ON g.developer = s.id
            ORDER BY g.release_date DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function getReviews($game_id)
    {
        // 22.01.2026 (c) Alexander Livanov
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

                rr.text AS developer_reply,
                rr.created_at AS developer_reply_created_at

                FROM game_reviews r
                LEFT JOIN users u ON u.id = r.user_id

                -- подтягиваем игру, чтобы понять студию (developer)
                JOIN games g ON g.id = r.game_id

                LEFT JOIN review_replies rr 
                ON rr.review_id = r.id 
                AND rr.studio_id = g.developer

                WHERE r.game_id = ?
                ORDER BY r.created_at DESC
                ";
        $stmt = $this->db->connect()->prepare($sql);
        $stmt->execute([$game_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'reviews' => $reviews]);
    }

    public function getReviewsArray($game_id)
    {
        $db = new Database();
        $pdo = $db->connect();
        $stmt = $pdo->prepare("SELECT r.*, u.username, u.profile_picture 
                           FROM game_reviews r 
                           LEFT JOIN users u ON r.user_id = u.id 
                           WHERE r.game_id = ? 
                           ORDER BY r.created_at DESC");
        $stmt->execute([$game_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
