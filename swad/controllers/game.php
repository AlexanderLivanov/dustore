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
}
