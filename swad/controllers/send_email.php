<?php
function sendVerificationEmail($email, $token)
{
    $link = "https://dustore.ru/verify.php?token=" . $token;

    $subject = "Подтверждение email";
    $message = "Здравствуйте! Перейдите по ссылке для подтверждения почты:\n\n$link";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/plain;charset=UTF-8" . "\r\n";
    $headers .= "From: Dust Games <no-reply@dustgames.local>" . "\r\n";
    $headers .= "Reply-To: no-reply@dustgames.local" . "\r\n";

    mail($email, $subject, $message, $headers);
}
