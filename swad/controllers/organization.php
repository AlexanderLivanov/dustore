<?php
class Organization
{
    private $id;
    private $name;
    private $ownerId;
    private $members;
    private $configPath;

    public function __construct($name, $ownerId, $members = [])
    {
        $this->name = $this->sanitizeName($name);
        $this->ownerId = $ownerId;
        $this->members = $members;
    }

    private function sanitizeName($name)
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);
        return substr($cleaned, 0, 50);
    }

    public function save($pdo)
    {
        try {
            // Создаем папку и файл
            $this->createOrganizationFolder();

            // Сохраняем в БД
            $stmt = $pdo->prepare("
                INSERT INTO organizations 
                (name, owner_id, members, config_path) 
                VALUES (:name, :owner_id, :members, :config_path)
            ");

            $stmt->execute([
                ':name' => $this->name,
                ':owner_id' => $this->ownerId,
                ':members' => json_encode($this->members),
                ':config_path' => $this->configPath
            ]);

            $this->id = $pdo->lastInsertId();
            return true;
        } catch (PDOException $e) {
            $this->rollbackCreation();
            throw new Exception("Ошибка создания организации: " . $e->getMessage());
        }
    }

    private function createOrganizationFolder()
    {
        $basePath = $_SERVER['DOCUMENT_ROOT'] . '/swad/usercontent/';
        $folderPath = $basePath . $this->name;

        if (!file_exists($folderPath)) {
            if (!mkdir($folderPath, 0755, true)) {
                throw new Exception("Не удалось создать директорию");
            }
        }

        $this->configPath = $folderPath . '/org.json';
        $this->saveConfigFile();
    }

    private function saveConfigFile()
    {
        $configData = [
            'created_at' => date('Y-m-d H:i:s'),
            'id' => $this->ownerId,
            'members' => $this->members
        ];

        if (file_put_contents(
            $this->configPath,
            json_encode($configData, JSON_PRETTY_PRINT)
        ) === false) {
            throw new Exception("Ошибка создания конфигурационного файла");
        }
    }

    private function rollbackCreation()
    {
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
            rmdir(dirname($this->configPath));
        }
    }

    // Getters
    public function getId()
    {
        return $this->id;
    }
    public function getName()
    {
        return $this->name;
    }
    public function getConfigPath()
    {
        return $this->configPath;
    }
}
