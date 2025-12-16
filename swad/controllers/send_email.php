<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';

function sendMail($send_to, $subject, $data, $params=""){
    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';

        $mail->isSMTP();
        $mail->Host       = 'smtp.mail.ru';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'dusty@dustore.ru';
        $mail->Password   = 'YOnwzU1TeLuLiIt69ffL'; // пароль приложения
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

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