<?php
class ProjectManagment
{
    public $db;
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function getAllStudioGames($studio_id){
        $stmt = $this->db->prepare(
            "SELECT * FROM games WHERE developer = :studio_id"
        );
        $stmt->execute(['studio_id' => $studio_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}