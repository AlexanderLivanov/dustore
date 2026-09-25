<?php
declare(strict_types=1);
/**
 * m/api/auth.php — вход в мобильной версии: почта+пароль и passkey (WebAuthn).
 *
 * Та же БД и тот же итог, что у десктопного /login: $_SESSION['USERDATA'] +
 * кука auth_token (через authUser из jwt.php) — после входа с телефона
 * десктопные страницы тоже видят человека вошедшим.
 *
 * WebAuthn без библиотек и без CBOR. Хитрость: при регистрации браузер сам
 * отдаёт открытый ключ в готовом виде (response.getPublicKey() — SPKI/DER) и
 * authenticatorData (getAuthenticatorData()). Значит, attestationObject разбирать
 * не нужно, а подпись при входе проверяет обычный openssl_verify:
 *     sig = Sign(privKey, authenticatorData || SHA256(clientDataJSON))
 * Алгоритмы: ES256 (-7) и RS256 (-257) — их понимает openssl и отдают все
 * платформенные ключи (Face ID, Touch ID, Android, Windows Hello).
 *
 * POST action:
 *   login            email, password
 *   wa_login_options                          → challenge
 *   wa_login         id, clientDataJSON, authenticatorData, signature, userHandle
 *   wa_reg_options   (нужен вход)             → challenge, user, exclude
 *   wa_register      id, clientDataJSON, authenticatorData, publicKey, alg, transports, name
 *   wa_list / wa_delete id
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../swad/config.php';
require_once __DIR__ . '/../lib.php';
require_once __DIR__ . '/../../swad/controllers/jwt.php';
m_restore_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function b64u_enc(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function b64u_dec(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(['ok' => false], 405);

/* Защита от CSRF: запрос обязан прийти с нашей же страницы. Браузер ставит
   Origin на любой POST из fetch — подделать его со стороннего сайта нельзя. */
$host   = (string)($_SERVER['HTTP_HOST'] ?? '');
$https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$origin = ($https ? 'https://' : 'http://') . $host;
if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $origin) out(['ok' => false, 'error' => 'origin'], 403);

$db = (new Database())->connect();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rpId   = preg_replace('/:\d+$/', '', $host);          // rpId — домен без порта
$action = (string)($_POST['action'] ?? '');
$uid    = (int)($_SESSION['USERDATA']['id'] ?? 0);

/** Итог любого успешного входа — ровно то, что делает десктоп. */
function m_login_user(array $user): void {
    session_regenerate_id(true);                         // защита от фиксации сессии
    $_SESSION['logged-in']   = true;
    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['telegram_id'] = $user['telegram_id'];
    $_SESSION['USERDATA']    = $user;
    $_SESSION['auth_token']  = authUser($user['telegram_id']);
}

/** Лимит попыток: в сессии по IP (как в email_auth.php) — от перебора с одного устройства. */
function m_rate(string $key, int $max, int $window): bool {
    $k = 'rl_m_' . $key . '_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $s = $_SESSION[$k] ?? ['n' => 0, 'reset' => time() + $window];
    if (time() > $s['reset']) $s = ['n' => 0, 'reset' => time() + $window];
    $s['n']++; $_SESSION[$k] = $s;
    return $s['n'] <= $max;
}

/* ════════════════════ почта + пароль ════════════════════ */
if ($action === 'login') {
    if (!m_rate('login', 10, 300)) out(['ok' => false, 'error' => 'limit'], 429);
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    if ($email === '' || $pass === '') out(['ok' => false, 'error' => 'empty']);
    $st = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user || empty($user['password']) || !password_verify($pass, $user['password'])) {
        usleep(random_int(100000, 300000));              // не даём по времени отличить «нет такого email»
        out(['ok' => false, 'error' => 'bad']);
    }
    if (empty($user['email_verified'])) out(['ok' => false, 'error' => 'unverified']);
    m_login_user($user);
    out(['ok' => true]);
}

/* ════════════════════ WebAuthn ════════════════════ */
function wa_table(PDO $db): void {
    // аддитивно и идемпотентно; создаётся при первой регистрации ключа
    $db->exec("CREATE TABLE IF NOT EXISTS webauthn_credentials (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        cred_id      VARCHAR(255) NOT NULL,
        public_key   TEXT NOT NULL,
        alg          SMALLINT NOT NULL,
        sign_count   INT UNSIGNED NOT NULL DEFAULT 0,
        transports   VARCHAR(100) NULL,
        name         VARCHAR(64) NULL,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL,
        UNIQUE KEY uq_cred (cred_id),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Одноразовый вызов (challenge): живёт 3 минуты, после проверки сгорает. */
function wa_challenge(string $kind): string {
    $c = random_bytes(32);
    $_SESSION['wa_' . $kind] = ['c' => b64u_enc($c), 't' => time()];
    return b64u_enc($c);
}

/**
 * Общие проверки ответа аутентификатора. Возвращает разобранный authenticatorData.
 * Каждая строка — отдельная атака, от которой она закрывает (см. комментарии).
 */
function wa_verify_common(string $kind, string $type, string $cdjRaw, string $authData, string $rpId, string $origin): array {
    $saved = $_SESSION['wa_' . $kind] ?? null;
    unset($_SESSION['wa_' . $kind]);                                        // одноразовость: повтор ответа не пройдёт
    if (!$saved || time() - $saved['t'] > 180) out(['ok' => false, 'error' => 'expired']);

    $cd = json_decode($cdjRaw, true);
    if (!is_array($cd)) out(['ok' => false, 'error' => 'bad_client_data']);
    if (($cd['type'] ?? '') !== $type) out(['ok' => false, 'error' => 'bad_type']);          // create ≠ get
    if (!hash_equals($saved['c'], (string)($cd['challenge'] ?? ''))) out(['ok' => false, 'error' => 'bad_challenge']);
    if (($cd['origin'] ?? '') !== $origin) out(['ok' => false, 'error' => 'bad_origin']);    // фишинговый домен
    if (!empty($cd['crossOrigin'])) out(['ok' => false, 'error' => 'cross_origin']);

    if (strlen($authData) < 37) out(['ok' => false, 'error' => 'bad_auth_data']);
    if (!hash_equals(hash('sha256', $rpId, true), substr($authData, 0, 32))) out(['ok' => false, 'error' => 'bad_rp']);
    $flags = ord($authData[32]);
    if (!($flags & 0x01)) out(['ok' => false, 'error' => 'no_presence']);                   // UP: человек коснулся/посмотрел
    return ['flags' => $flags, 'count' => unpack('N', substr($authData, 33, 4))[1]];
}

function wa_pem(string $der): string {
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

if ($action === 'wa_login_options') {
    out(['ok' => true, 'challenge' => wa_challenge('get'), 'rpId' => $rpId]);
}

if ($action === 'wa_login') {
    if (!m_rate('wa', 20, 300)) out(['ok' => false, 'error' => 'limit'], 429);
    try { wa_table($db); } catch (Throwable $e) { out(['ok' => false, 'error' => 'unknown_key']); }
    $credId   = (string)($_POST['id'] ?? '');
    $cdjRaw   = b64u_dec((string)($_POST['clientDataJSON'] ?? ''));
    $authData = b64u_dec((string)($_POST['authenticatorData'] ?? ''));
    $sig      = b64u_dec((string)($_POST['signature'] ?? ''));

    $st = $db->prepare("SELECT * FROM webauthn_credentials WHERE cred_id = ? LIMIT 1");
    $st->execute([$credId]);
    $cred = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cred) { unset($_SESSION['wa_get']); out(['ok' => false, 'error' => 'unknown_key']); }

    $ad = wa_verify_common('get', 'webauthn.get', $cdjRaw, $authData, $rpId, $origin);
    $ok = openssl_verify($authData . hash('sha256', $cdjRaw, true), $sig, $cred['public_key'], OPENSSL_ALGO_SHA256);
    if ($ok !== 1) out(['ok' => false, 'error' => 'bad_signature']);

    // userHandle, если пришёл, обязан указывать на владельца ключа
    $uh = b64u_dec((string)($_POST['userHandle'] ?? ''));
    if ($uh !== '' && $uh !== 'u' . $cred['user_id']) out(['ok' => false, 'error' => 'bad_user']);
    // счётчик подписей: если ключ его ведёт и он не вырос — это клон ключа
    if ($ad['count'] > 0 || (int)$cred['sign_count'] > 0) {
        if ($ad['count'] <= (int)$cred['sign_count']) out(['ok' => false, 'error' => 'cloned']);
    }
    $db->prepare("UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?")->execute([$ad['count'], $cred['id']]);

    $u = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $u->execute([(int)$cred['user_id']]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
    if (!$user) out(['ok' => false, 'error' => 'unknown_key']);
    m_login_user($user);
    out(['ok' => true]);
}

/* ─── дальше — только для вошедших: управление своими ключами ─── */
if (!$uid) out(['ok' => false, 'error' => 'auth'], 401);

if ($action === 'wa_reg_options') {
    wa_table($db);
    $st = $db->prepare("SELECT cred_id FROM webauthn_credentials WHERE user_id = ?");
    $st->execute([$uid]);
    $me = $_SESSION['USERDATA'];
    $name = (string)(($me['username'] ?? '') ?: ($me['email'] ?? ('user' . $uid)));
    out(['ok' => true, 'challenge' => wa_challenge('create'), 'rp' => ['id' => $rpId, 'name' => 'Dustore'],
         'user' => ['id' => b64u_enc('u' . $uid), 'name' => $name,
                    'displayName' => trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?: $name],
         'exclude' => $st->fetchAll(PDO::FETCH_COLUMN)]);
}

if ($action === 'wa_register') {
    $cdjRaw   = b64u_dec((string)($_POST['clientDataJSON'] ?? ''));
    $authData = b64u_dec((string)($_POST['authenticatorData'] ?? ''));
    $der      = b64u_dec((string)($_POST['publicKey'] ?? ''));
    $alg      = (int)($_POST['alg'] ?? 0);
    $credId   = (string)($_POST['id'] ?? '');
    if (!in_array($alg, [-7, -257], true)) out(['ok' => false, 'error' => 'bad_alg']);
    if ($credId === '' || strlen($credId) > 255 || !preg_match('/^[A-Za-z0-9_-]+$/', $credId)) out(['ok' => false, 'error' => 'bad_id']);
    $ad = wa_verify_common('create', 'webauthn.create', $cdjRaw, $authData, $rpId, $origin);
    $pem = wa_pem($der);
    if (!openssl_pkey_get_public($pem)) out(['ok' => false, 'error' => 'bad_key']);   // ключ должен реально разбираться
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 64) ?: 'Ключ входа';
    $transports = mb_substr(preg_replace('/[^a-z,-]/', '', (string)($_POST['transports'] ?? '')), 0, 100);
    try {
        $db->prepare("INSERT INTO webauthn_credentials(user_id,cred_id,public_key,alg,sign_count,transports,name) VALUES(?,?,?,?,?,?,?)")
           ->execute([$uid, $credId, $pem, $alg, $ad['count'], $transports ?: null, $name]);
    } catch (PDOException $e) { out(['ok' => false, 'error' => 'exists']); }
    out(['ok' => true]);
}

if ($action === 'wa_list') {
    try { wa_table($db); } catch (Throwable $e) { out(['ok' => true, 'keys' => []]); }
    $st = $db->prepare("SELECT id, name, created_at, last_used_at FROM webauthn_credentials WHERE user_id = ? ORDER BY id DESC");
    $st->execute([$uid]);
    out(['ok' => true, 'keys' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'wa_delete') {
    $db->prepare("DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?")->execute([(int)($_POST['id'] ?? 0), $uid]);
    out(['ok' => true]);
}

out(['ok' => false, 'error' => 'unknown_action'], 400);
