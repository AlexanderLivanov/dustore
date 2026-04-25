<?php
// OBSOLETE: MOVED TO NotificationCenter sendMail() method;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../config.php');
require __DIR__ . '/../../vendor/autoload.php';

function sendMail($send_to, $subject, $data, $params = "")
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

        $mail->Body = $data;

        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = 'html';
        $mail->send();
        // echo 'OK';
    } catch (Exception $e) {
        echo "Ошибка: {$mail->ErrorInfo}";
    }
}
