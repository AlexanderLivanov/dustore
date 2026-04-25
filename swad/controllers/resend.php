<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/send_email.php');

$db = new Database();
$pdo = $db->connect();

// $stmt = $pdo->prepare("
//     SELECT id, email, verification_token
//     FROM users
//     WHERE email IS NOT NULL
//       AND email_verified = 0
//       AND verification_token IS NOT NULL
// ");
$stmt = $pdo->prepare("
    SELECT id, email, verification_token
    FROM users
    WHERE email = 'a.livanov@dustore.ru'
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

$sent = 0;
$failed = 0;

foreach ($users as $user) {
    
    $verifyLink = 'https://dustore.ru/verify?token=' . $user['verification_token'];

    $mail_body = '
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Подтверждение почты</title>
    </head>
    <body style="margin:0;padding:0;background-color:#0e0e12;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center" style="padding:40px 15px;">
                    <table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;">
                        <tr>
                            <td style="padding:30px;text-align:center;">
                                <h1 style="color:#ffffff;">
                                    Подтвердите почту в <span style="color:#c32178;">Dustore</span>
                                </h1>

                                <p style="color:#b8b8c6;">
                                    Для завершения регистрации нажмите кнопку ниже
                                </p>

                                <a href="' . $verifyLink . '"
                                   style="display:inline-block;padding:14px 28px;
                                   background:#c32178;color:#ffffff;
                                   text-decoration:none;border-radius:12px;
                                   font-weight:bold;font-size:16px;">
                                   Подтвердить почту
                                </a>

                                <p style="color:#9a9ab0;font-size:13px;margin-top:25px;">
                                    Если кнопка не работает, скопируйте ссылку:<br>
                                    <a href="' . $verifyLink . '" style="color:#c32178;">
                                        ' . $verifyLink . '
                                    </a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ';

    if (sendMail($user['email'], 'Подтвердите вашу почту в Dustore', $mail_body)) {
        $sent++;
    } else {
        $failed++;
    }

    // 🔴 пауза чтобы Gmail не забанил SMTP
    sleep(1);
}

echo "Готово\n";
echo "Отправлено: $sent\n";
echo "Ошибок: $failed\n";
