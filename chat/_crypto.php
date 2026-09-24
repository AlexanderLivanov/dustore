<?php
/**
 * chat/_crypto.php — шифрование тела сообщений при хранении (at rest).
 *
 * Модель: НЕ end-to-end — ключ живёт на сервере (swad/pass.php), сервер
 * технически может расшифровать. Задача — защитить дамп БД/бэкап/утечку
 * файла, а не переписку от самой платформы. Настоящий E2E (клиентские
 * ключи) — см. заготовку swad/js/e2e.js, отдельная и более тяжёлая задача.
 *
 * Конверт: "enc1:<nonce_b64>:<tag_b64>:<ciphertext_b64>", AES-256-GCM
 * (аутентифицированное шифрование — подмена/порча шифротекста детектится
 * при расшифровке, а не проходит молча, как было бы с CBC).
 *
 * Обратная совместимость: строки без префикса "enc1:" — старые
 * незашифрованные сообщения; msg_decrypt() возвращает их как есть.
 * Бэкфилл истории — отдельный разовый скрипт, не часть этого файла.
 */

if (!defined('MESSAGE_ENC_KEY')) {
    throw new RuntimeException('MESSAGE_ENC_KEY не задан — см. swad/pass.php');
}

/** Шифрует текст сообщения перед INSERT. */
function msg_encrypt(string $plaintext): string {
    $key = base64_decode(MESSAGE_ENC_KEY, true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('MESSAGE_ENC_KEY должен быть 32 байта в base64');
    }
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Не удалось зашифровать сообщение');
    }
    return 'enc1:' . base64_encode($nonce) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
}

/**
 * Расшифровывает значение из колонки messages.body.
 * null проходит насквозь (удалённые сообщения не читаем).
 * Старые незашифрованные строки (без "enc1:") возвращаются как есть.
 */
function msg_decrypt(?string $stored): ?string {
    if ($stored === null || $stored === '') return $stored;
    if (!str_starts_with($stored, 'enc1:')) return $stored; // легаси, не зашифровано

    $parts = explode(':', $stored, 4);
    if (count($parts) !== 4) return $stored; // не наш формат — не трогаем

    [, $nonceB64, $tagB64, $ctB64] = $parts;
    $key   = base64_decode(MESSAGE_ENC_KEY, true);
    $nonce = base64_decode($nonceB64, true);
    $tag   = base64_decode($tagB64, true);
    $ct    = base64_decode($ctB64, true);
    if ($key === false || $nonce === false || $tag === false || $ct === false) {
        return '[ошибка расшифровки]';
    }
    $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    return $pt === false ? '[ошибка расшифровки]' : $pt;
}
