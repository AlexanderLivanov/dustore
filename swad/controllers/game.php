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

    public function getLatestGames($limit = 10)
    {
        $stmt = $this->db->connect()->prepare("
        SELECT 
            g.id,
            g.name,
            g.path_to_cover,
            g.price,
            g.GQI,
            g.status,
            g.release_date,
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

    public function getRating($gameId)
    {
        $stmt = $this->db->connect()->prepare("
        SELECT AVG(rating) as avg_rating 
        FROM reviews 
        WHERE game_id = ?
    ");
        $stmt->execute([$gameId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
    }
}
