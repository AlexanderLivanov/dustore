<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../config.php');
require __DIR__ . '/../../vendor/autoload.php';

class NotificationCenter
{
    public $db;
    public $table = 'notifications';

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    // Получение уведомлений пользователя
    public function getUserNotifications($user_id, $limit = 50)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * 
                FROM {$this->table} 
                WHERE user_id = :user_id 
                ORDER BY date DESC 
                LIMIT :limit
            ");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }

    // Отправка уведомлений массиву пользователей
    public function sendNotifications($user_ids, $title, $message, $action = null)
    {
        if (empty($user_ids) || empty($title) || empty($message)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (user_id, title, message, action, status, date)
                VALUES (:user_id, :title, :message, :action, 'unread', NOW())
            ");

            foreach ($user_ids as $user_id) {
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':title' => $title,
                    ':message' => $message,
                    ':action' => $action
                ]);
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error sending notifications: " . $e->getMessage());
            return false;
        }
    }

    // Отметить уведомление как прочитанное
    public function markAsRead($notification_id)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET status = 'read' 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $notification_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    public function sendEmail($send_to, $subject, $body, $params = "")
    {
        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = 'sm21.hosting.reg.ru';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'dusty@dustore.ru';
            $mail->Password   = EMAIL_PASSWD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('dusty@dustore.ru', 'Менеджер Дасти');
            $mail->addAddress($send_to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            // $mail->SMTPDebug = 2;
            // $mail->Debugoutput = 'html';

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Ошибка отправки email: {$mail->ErrorInfo}");
            return false;
        }
    }
}
