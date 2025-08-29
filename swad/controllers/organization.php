<?php
class Organization
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function getAllStaff($studio_id)
    {
        $query = 'SELECT * FROM staff WHERE org_id = ?';
        $stmt = $this->db->prepare($query, [$studio_id]);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
}
