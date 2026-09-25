<?php
// OBSOLETE: MOVED TO NotificationCenter sendMail() method;
// buildEmail() added here for backward compatibility (email_auth.php dependency)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../config.php');
require __DIR__ . '/../../vendor/autoload.php';

/**
 * Отправляет письмо через SMTP.
 * Возвращает true при успехе, false при ошибке.
 */
function sendMail(string $send_to, string $subject, string $data, string $params = ''): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->CharSet    = 'UTF-8';
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
        $mail->Body    = $data;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('[sendMail] SMTP error to ' . $send_to . ': ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Почтальон для рассылок: ОДНО SMTP-соединение на все письма (SMTPKeepAlive).
 *
 * sendMail() на каждое письмо заново соединяется, делает STARTTLS и AUTH —
 * на тысячах писем это медленно и для хостинга выглядит как подбор пароля:
 * он начинает отказывать. А таймаут PHPMailer по умолчанию — 300 секунд,
 * так что каждое отвергнутое письмо висело по пять минут.
 * Креды те же, что у sendMail(): EMAIL_PASSWD из swad/pass.php.
 *
 *   $m = mailer_batch();  foreach (...) { mailer_send($m, $to, $subj, $html); }  $m->smtpClose();
 */
function mailer_batch(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->CharSet       = 'UTF-8';
    $mail->isSMTP();
    $mail->Host          = 'sm21.hosting.reg.ru';
    $mail->SMTPAuth      = true;
    $mail->Username      = 'dusty@dustore.ru';
    $mail->Password      = EMAIL_PASSWD;
    $mail->SMTPSecure    = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port          = 587;
    $mail->SMTPKeepAlive = true;
    $mail->Timeout       = 15;
    $mail->setFrom('dusty@dustore.ru', 'Менеджер Дасти');
    $mail->isHTML(true);
    return $mail;
}

/**
 * Одно письмо через открытое соединение. null — отправлено, иначе текст ошибки SMTP.
 * $headers — доп. заголовки письма, например List-Unsubscribe: ['List-Unsubscribe' => '<https://…>'].
 */
function mailer_send(PHPMailer $mail, string $to, string $subject, string $html, array $headers = []): ?string
{
    try {
        $mail->clearAddresses();
        $mail->clearCustomHeaders();
        foreach ($headers as $name => $value) $mail->addCustomHeader($name, $value);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->send();
        return null;
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        // после отказа сервер ждёт новую транзакцию; если соединение умерло,
        // следующий send() откроет его заново
        try { $mail->getSMTPInstance()->reset(); } catch (Throwable $t) { $mail->smtpClose(); }
        return $err;
    }
}

if (!function_exists('buildEmail')) {
    function buildEmail(string $title, string $bodyHtml, string $btnText = '', string $btnUrl = ''): string
    {
        $btn  = $btnText
            ? "<a href=\"{$btnUrl}\" style=\"display:inline-block;padding:14px 28px;background:#c32178;color:#fff;text-decoration:none;border-radius:12px;font-weight:bold;font-size:16px;margin-top:8px;\">{$btnText}</a>"
            : '';
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#0e0e12;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center" style="padding:40px 15px;">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
    <tr><td style="padding:36px 30px;text-align:center;">
        <h1 style="color:#fff;margin:0 0 10px;font-size:22px;">Du<span style="color:#c32178;">store</span></h1>
        <h2 style="color:#fff;margin:0 0 16px;font-size:18px;">{$title}</h2>
        {$bodyHtml}
        {$btn}
        <p style="color:#9a9ab0;font-size:12px;margin:28px 0 0;">Это письмо отправлено автоматически · Не отвечайте на него</p>
    </td></tr>
    <tr><td style="background:#0f0f15;padding:16px;text-align:center;">
        <p style="color:#6f6f85;font-size:12px;margin:0;">© 2024–{$year} Dustore · <a href="https://t.me/dustore_official" style="color:#c32178;">Telegram</a></p>
    </td></tr>
    </table>
</td></tr>
</table>
</body></html>
HTML;
    }
}