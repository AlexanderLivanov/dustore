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
        $stmt = $this->db->prepare($query);
        $stmt->execute([$studio_id]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getOwnersID($studio_id){
        $query = 'SELECT owner_id FROM studios WHERE id = ?';
        $stmt = $this->db->prepare($query);
        $stmt->execute([$studio_id]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getAllProjects($studio_id)
    {
        $query = 'SELECT * FROM games WHERE developer = ?';
        $stmt = $this->db->prepare($query);
        $stmt->execute([$studio_id]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getPaymentData($studio_id){
        $query = 'SELECT bank_name, BIC, acc_num, INN FROM studios WHERE id = ?';
        $stmt = $this->db->prepare($query);
        $stmt->execute([$studio_id]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
}
