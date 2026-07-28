<?php
/**
 * conference.php — запись на слоты онлайн-конференции джема.
 *
 * Хранилище: плоский JSON-файл + flock(LOCK_EX).
 * Все мутации (book / cancel / move) выполняются атомарно под одной блокировкой.
 * Идентичность участника (команда/соло, капитанство) вычисляется ТОЛЬКО на сервере.
 *
 * POST-API обрабатывается ДО require header.php (правило "headers already sent").
 */

/* ══════════════ БУТСТРАП ══════════════
 * config.php подключаем ДО session_start(): если он сам настраивает или
 * стартует сессию (имя, cookie-параметры), наш поздний session_start()
 * открыл бы ДРУГУЮ сессию — и логин "не считывался" бы.
 * HTTP_HOST-фоллбэк — для CLI/loopback-запусков, где Database::connect()
 * иначе вернёт null PDO (маршрутизация кредов по хосту).
 */
if (empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'dustore.ru';
}
require_once __DIR__ . '/swad/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ══════════════ КОНФИГ (правь здесь) ══════════════ */

const SPRINT_ID  = 12; // ⚠️ id ТЕКУЩЕГО джема — проверь, что это реальный sprint_id!
const SLOTS_FILE = __DIR__ . '/swad/data/conf_slots.json';

$CONF = [
    'title'   => 'Онлайн-конференция джема: питчи проектов',
    'desc'    => 'Каждая команда получает 5 минут: 3 минуты на питч проекта, 2 минуты на вопросы экспертов. Подключайтесь за 10 минут до своего слота.',
    'link'    => 'https://discord.gg/YYayA7FPx',
    'date'    => '16 июля 2026',
    'windows' => '12:00–15:00 (МСК) и 18:00–21:00 (МСК)',
    'experts' => [
        ['name' => 'Александр Ливанов', 'role' => 'Dustore'],
        ['name' => 'Eshward Williams', 'role' => 'Dustore'],
        ['name' => 'Организаторы', 'role' => 'К.О.Н.Т.У.Р.'],
    ],
];

/* Слоты по 5 минут: 12:00–15:00 и 18:00–21:00 */
function conf_slots(): array {
    $slots = [];
    foreach ([[12, 15], [18, 21]] as [$from, $to]) {
        for ($m = $from * 60; $m < $to * 60; $m += 5) {
            $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
    }
    return $slots;
}

/* ══════════════ ХРАНИЛИЩЕ ══════════════
 * with_slots() — единственная точка доступа к файлу.
 * Захватывает эксклюзивный лок, читает JSON, отдаёт данные в колбэк.
 * Колбэк возвращает ['save' => bool, 'response' => array].
 * Перезапись — только под тем же локом (ftruncate + rewind).
 */
function with_slots(callable $fn): array {
    $dir = dirname(SLOTS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $fp = fopen(SLOTS_FILE, 'c+'); // create if missing, не обрезает
    if (!$fp) return ['ok' => false, 'error' => 'storage_unavailable'];

    try {
        flock($fp, LOCK_EX);
        $raw  = stream_get_contents($fp);
        $data = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];

        $result = $fn($data); // может мутировать $data по ссылке через use — см. ниже

        if (!empty($result['save'])) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
        }
        return $result['response'];
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/* ══════════════ ИДЕНТИЧНОСТЬ УЧАСТНИКА ══════════════
 * ⚠️ ЕДИНСТВЕННОЕ место, зависящее от схемы БД.
 * Перед деплоем: SHOW CREATE TABLE по своим таблицам участников/команд джема
 * и поправь имена таблиц/колонок ниже. Логика остаётся той же.
 *
 * Возвращает null (не участник) или:
 * [
 *   'entity'     => 'team:12' | 'solo:345',  // ключ уникальности брони
 *   'name'       => 'Название команды' | 'username',
 *   'is_captain' => bool,                     // соло всегда true
 * ]
 */
function jam_identity(PDO $pdo, int $userId, int $sprintId): ?array {
    // Раздельные запросы намеренно: sprint_id в таблицах разных типов
    // (bigint unsigned vs int) и разных collation — JOIN шёл бы с неявным кастом.

    // 1) Капитан команды? sprint_teams — источник истины для капитанства.
    $st = $pdo->prepare(
        "SELECT id, team_name
           FROM sprint_teams
          WHERE sprint_id = ? AND captain_id = ?
          ORDER BY id
          LIMIT 1"
    );
    $st->execute([$sprintId, $userId]);
    if ($team = $st->fetch(PDO::FETCH_ASSOC)) {
        return [
            'entity'     => 'team:' . (int)$team['id'],
            'name'       => $team['team_name'],
            'is_captain' => true,
        ];
    }

    // 2) Участник спринта (соло или рядовой тиммейт)
    $st = $pdo->prepare(
        "SELECT participant_type, alias
           FROM sprint_participants
          WHERE sprint_id = ? AND user_id = ?
          LIMIT 1"
    );
    $st->execute([$sprintId, $userId]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) return null; // не зарегистрирован на джем

    $fallbackName = ($p['alias'] !== null && $p['alias'] !== '')
        ? $p['alias']
        : ($_SESSION['USERDATA']['username'] ?? 'user_' . $userId);

    if ($p['participant_type'] === 'solo') {
        return [
            'entity'     => 'solo:' . $userId,
            'name'       => $fallbackName,
            'is_captain' => true, // соло сам себе капитан
        ];
    }

    // participant_type = 'team', но не капитан → видит расписание, бронировать не может
    return [
        'entity'     => 'member:' . $userId,
        'name'       => $fallbackName,
        'is_captain' => false,
    ];
}

/* ══════════════ API (до любого вывода!) ══════════════ */

$isApi = ($_SERVER['REQUEST_METHOD'] === 'POST') || (($_GET['api'] ?? '') === 'state');

if ($isApi) {
    header('Content-Type: application/json; charset=utf-8');

    $userId        = isset($_SESSION['USERDATA']['id']) ? (int)$_SESSION['USERDATA']['id'] : 0;
    $identity      = null;
    $identityError = false;

    if ($userId) {
        try {
            $pdo = (new Database())->connect();
            if (!$pdo) {
                throw new RuntimeException('Database::connect() вернул null — проверь HTTP_HOST-роутинг');
            }
            $identity = jam_identity($pdo, $userId, SPRINT_ID);
        } catch (Throwable $e) {
            $identityError = true;
            error_log('conference identity: ' . $e->getMessage());
        }
    }

    /* --- GET state: публичное расписание + мой контекст --- */
    if (($_GET['api'] ?? '') === 'state') {
        $resp = with_slots(function (array $data) use ($identity, $identityError) {
            $public = [];
            foreach ($data as $slot => $b) {
                $public[$slot] = [
                    'name'      => $b['name'],
                    'project'   => $b['project'],
                    'engine'    => $b['engine'],
                    'readiness' => $b['readiness'],
                ];
            }
            $mySlot = null;
            if ($identity) {
                foreach ($data as $slot => $b) {
                    if ($b['entity'] === $identity['entity']) { $mySlot = $slot; break; }
                }
            }
            return ['save' => false, 'response' => [
                'ok'       => true,
                'slots'    => conf_slots(),
                'bookings' => $public,
                'me'       => $identity ? [
                    'name'       => $identity['name'],
                    'is_captain' => $identity['is_captain'],
                    'my_slot'    => $mySlot,
                ] : null,
                'logged_in'      => isset($_SESSION['USERDATA']),
                'identity_error' => $identityError,
            ]];
        });
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* --- Мутации: только капитан --- */
    $in     = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $in['action'] ?? '';

    if (!hash_equals($_SESSION['conf_csrf'] ?? '', $in['csrf'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'csrf']); exit;
    }
    if (!$userId)                    { echo json_encode(['ok' => false, 'error' => 'auth']); exit; }
    if (!$identity)                  { echo json_encode(['ok' => false, 'error' => 'not_participant']); exit; }
    if (!$identity['is_captain'])    { echo json_encode(['ok' => false, 'error' => 'not_captain']); exit; }

    $validSlots = conf_slots();

    if ($action === 'book') {
        $slot      = $in['slot'] ?? '';
        $project   = mb_substr(trim($in['project'] ?? ''), 0, 100);
        $engine    = mb_substr(trim($in['engine']  ?? ''), 0, 50);
        $readiness = (int)($in['readiness'] ?? 0);

        if (!in_array($slot, $validSlots, true)) { echo json_encode(['ok' => false, 'error' => 'bad_slot']); exit; }
        if ($project === '' || $engine === '')   { echo json_encode(['ok' => false, 'error' => 'fields']); exit; }
        if ($readiness < 1 || $readiness > 10)   { echo json_encode(['ok' => false, 'error' => 'fields']); exit; }

        $resp = with_slots(function (array $data) use ($slot, $identity, $userId, $project, $engine, $readiness) {
            // Обе проверки — строго под локом
            if (isset($data[$slot])) {
                return ['save' => false, 'response' => ['ok' => false, 'error' => 'slot_taken']];
            }
            foreach ($data as $b) {
                if ($b['entity'] === $identity['entity']) {
                    return ['save' => false, 'response' => ['ok' => false, 'error' => 'already_booked']];
                }
            }
            $data[$slot] = [
                'entity'    => $identity['entity'],
                'name'      => $identity['name'],   // из сессии/БД, не из формы
                'project'   => $project,
                'engine'    => $engine,
                'readiness' => $readiness,
                'booked_by' => $userId,
                'booked_at' => date('c'),
            ];
            return ['save' => true, 'data' => $data, 'response' => ['ok' => true]];
        });
        echo json_encode($resp, JSON_UNESCAPED_UNICODE); exit;
    }

    if ($action === 'cancel') {
        $resp = with_slots(function (array $data) use ($identity) {
            foreach ($data as $slot => $b) {
                if ($b['entity'] === $identity['entity']) {
                    unset($data[$slot]);
                    return ['save' => true, 'data' => $data, 'response' => ['ok' => true]];
                }
            }
            return ['save' => false, 'response' => ['ok' => false, 'error' => 'no_booking']];
        });
        echo json_encode($resp, JSON_UNESCAPED_UNICODE); exit;
    }

    if ($action === 'move') {
        $newSlot = $in['slot'] ?? '';
        if (!in_array($newSlot, $validSlots, true)) { echo json_encode(['ok' => false, 'error' => 'bad_slot']); exit; }

        // Атомарный перенос: одна операция под одним локом.
        $resp = with_slots(function (array $data) use ($identity, $newSlot) {
            $current = null;
            foreach ($data as $slot => $b) {
                if ($b['entity'] === $identity['entity']) { $current = $slot; break; }
            }
            if ($current === null)   return ['save' => false, 'response' => ['ok' => false, 'error' => 'no_booking']];
            if (isset($data[$newSlot])) return ['save' => false, 'response' => ['ok' => false, 'error' => 'slot_taken']];

            $data[$newSlot] = $data[$current];
            $data[$newSlot]['booked_at'] = date('c');
            unset($data[$current]);
            return ['save' => true, 'data' => $data, 'response' => ['ok' => true]];
        });
        echo json_encode($resp, JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown_action']);
    exit;
}

/* ══════════════ HTML-СТРАНИЦА ══════════════ */

if (empty($_SESSION['conf_csrf'])) {
    $_SESSION['conf_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['conf_csrf'];

// require_once __DIR__ . '/swad/static/elements/header.php'; // раскомментируй, если нужен общий хедер
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($CONF['title']) ?> — Dustore</title>
<style>
:root {
    --p: #c32178;
    --bg: #14041d;
    --surf: #1f0f2a;
    --border: #3a2a3f;
    --text: #eee;
    --muted: #ffffff70;
}
* { box-sizing: border-box; margin: 0; }
body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', system-ui, sans-serif;
    padding: 24px 16px 80px;
}
.wrap { max-width: 860px; margin: 0 auto; }

/* Шапка конференции */
.conf-head { margin-bottom: 32px; }
.conf-head h1 { font-size: 1.6rem; margin-bottom: 10px; }
.conf-head p  { color: var(--muted); line-height: 1.55; max-width: 640px; }
.conf-meta {
    display: flex; flex-wrap: wrap; gap: 10px;
    margin-top: 16px; font-size: .9rem;
}
.conf-meta .chip {
    background: var(--surf); border: 1px solid var(--border);
    padding: 6px 12px;
}
.conf-meta a.chip { color: var(--p); text-decoration: none; }
.experts { margin-top: 14px; font-size: .9rem; color: var(--muted); }
.experts b { color: var(--text); font-weight: 600; }

/* Статус-бар пользователя */
.me-bar {
    background: var(--surf); border: 1px solid var(--border);
    padding: 12px 16px; margin-bottom: 24px; font-size: .92rem;
    display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center;
}
.me-bar .dot { color: var(--p); }
.me-bar button {
    background: none; border: 1px solid var(--border); color: var(--text);
    padding: 5px 12px; cursor: pointer; font: inherit; font-size: .85rem;
}
.me-bar button:hover { border-color: var(--p); }
.me-bar button.danger:hover { border-color: #d33; color: #f66; }

/* Сетка слотов */
h2.block-title { font-size: 1rem; color: var(--muted); margin: 24px 0 12px; font-weight: 600; }
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
}
.slot {
    border: 1px solid var(--border);
    background: var(--surf);
    padding: 10px 8px;
    cursor: pointer;
    font-size: .85rem;
    min-height: 58px;
    transition: border-color .12s;
}
.slot:hover { border-color: var(--p); }
.slot .t { font-family: 'JetBrains Mono', monospace; font-size: .95rem; }
.slot .who { color: var(--muted); font-size: .75rem; margin-top: 4px;
             overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.slot.taken { cursor: default; opacity: .85; border-color: coral; background: #170a20; }
.slot.taken:hover { border-color: var(--border); }
.slot.mine {
    border-color: var(--p);
    background: #2a0f22;
    cursor: default;
}
.slot.disabled { cursor: default; }
.slot.disabled:hover { border-color: var(--border); }
body.moving .slot:not(.taken):not(.mine) { border-style: dashed; border-color: var(--p); }

/* Модалка записи */
.modal-bg {
    position: fixed; inset: 0; background: rgba(0,0,0,.7);
    display: none; align-items: center; justify-content: center; z-index: 100;
}
.modal-bg.open { display: flex; }
.modal {
    background: var(--surf); border: 1px solid var(--border);
    padding: 24px; width: min(420px, 92vw);
}
.modal h3 { margin-bottom: 16px; font-size: 1.1rem; }
.modal label { display: block; font-size: .82rem; color: var(--muted); margin: 12px 0 4px; }
.modal input[type=text], .modal input[type=range] {
    width: 100%; background: var(--bg); border: 1px solid var(--border);
    color: var(--text); padding: 9px 10px; font: inherit;
}
.modal input[readonly] { color: var(--muted); }
.modal .range-row { display: flex; gap: 12px; align-items: center; }
.modal .range-val { font-family: 'JetBrains Mono', monospace; min-width: 2ch; color: var(--p); }
.modal .actions { display: flex; gap: 10px; margin-top: 20px; }
.modal .actions button {
    flex: 1; padding: 10px; font: inherit; cursor: pointer;
    border: 1px solid var(--border); background: none; color: var(--text);
}
.modal .actions .primary { background: var(--p); border-color: var(--p); color: #fff; }
.err { color: #f66; font-size: .85rem; margin-top: 10px; min-height: 1.2em; }
.hint { color: var(--muted); font-size: .85rem; margin: 12px 0; }
</style>
</head>
<body>
<div class="wrap">
    <div class="conf-head">
        <h1><?= htmlspecialchars($CONF['title']) ?></h1>
        <p><?= htmlspecialchars($CONF['desc']) ?></p>
        <div class="conf-meta">
            <span class="chip">📅 <?= htmlspecialchars($CONF['date']) ?></span>
            <span class="chip">🕐 <?= htmlspecialchars($CONF['windows']) ?></span>
            <a class="chip" href="<?= htmlspecialchars($CONF['link']) ?>" target="_blank" rel="noopener">🔗 Ссылка на встречу</a> (Discord)
        </div>
        <div class="experts">
            Эксперты:
            <?php foreach ($CONF['experts'] as $i => $e): ?>
                <b><?= htmlspecialchars($e['name']) ?></b> (<?= htmlspecialchars($e['role']) ?>)<?= $i < count($CONF['experts']) - 1 ? ',' : '' ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="me-bar" id="meBar">Загрузка…</div>

    <div id="slotsRoot"></div>
</div>

<!-- Модалка записи -->
<div class="modal-bg" id="modalBg">
    <div class="modal">
        <h3 id="modalTitle">Запись на слот</h3>
        <label>Участник / команда</label>
        <input type="text" id="fName" readonly>
        <label>Название проекта</label>
        <input type="text" id="fProject" maxlength="100" placeholder="Мой проект">
        <label>Игровой движок</label>
        <input type="text" id="fEngine" maxlength="50" placeholder="Unity / Godot / UE / свой…" list="engines">
        <datalist id="engines">
            <option>Unity</option><option>Godot</option><option>Unreal Engine</option>
            <option>Defold</option><option>GameMaker</option><option>Construct</option>
            <option>Свой движок</option>
        </datalist>
        <label>Готовность проекта: <span class="range-val" id="fReadyVal">5</span>/10</label>
        <input type="range" id="fReady" min="1" max="10" value="5">
        <div class="err" id="modalErr"></div>
        <div class="actions">
            <button onclick="closeModal()">Отмена</button>
            <button class="primary" onclick="submitBook()">Записаться</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
let STATE = null;
let moving = false;
let pendingSlot = null;

const ERRORS = {
    csrf: 'Сессия устарела, обнови страницу',
    auth: 'Нужно войти в аккаунт',
    not_participant: 'Ты не зарегистрирован на этот джем',
    not_captain: 'Записывать может только капитан команды',
    bad_slot: 'Некорректный слот',
    slot_taken: 'Слот уже занят — обнови и выбери другой',
    already_booked: 'У вас уже есть слот. Используй «Перенести»',
    no_booking: 'Активной записи не найдено',
    fields: 'Заполни все поля (готовность 1–10)',
};

async function api(body) {
    const r = await fetch(location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({...body, csrf: CSRF}),
    });
    return r.json();
}

async function loadState() {
    const r = await fetch(location.pathname + '?api=state');
    STATE = await r.json();
    render();
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

function render() {
    const bar = document.getElementById('meBar');

    if (!STATE || !STATE.ok) {
        bar.innerHTML = 'Сервис записи временно недоступен' +
            (STATE && STATE.error ? ` (${esc(STATE.error)})` : '') + '. Попробуй обновить страницу.';
        document.getElementById('slotsRoot').innerHTML = '';
        return;
    }

    const me = STATE.me;

    if (!STATE.logged_in) {
        bar.innerHTML = 'Чтобы записаться, <a href="/login" style="color:var(--p)">войди в аккаунт</a>.';
    } else if (!me && STATE.identity_error) {
        bar.innerHTML = 'Не удалось определить участие — ошибка на сервере, загляни в error_log.';
    } else if (!me) {
        bar.innerHTML = 'Ты не участвуешь в этом джеме — запись недоступна, но расписание видно всем.';
    } else if (!me.is_captain) {
        bar.innerHTML = `<span>Команда: <b>${esc(me.name)}</b></span> <span class="dot">●</span> <span>Записать команду на слот может только капитан.</span>`;
    } else if (me.my_slot) {
        bar.innerHTML = `<span><b>${esc(me.name)}</b> — слот <b style="color:var(--p)">${me.my_slot}</b></span>
            <button onclick="startMove()">Перенести</button>
            <button class="danger" onclick="doCancel()">Отменить запись</button>`;
    } else {
        bar.innerHTML = `<span><b>${esc(me.name)}</b></span> <span class="dot">●</span> <span>Выбери свободный слот, чтобы записаться.</span>`;
    }

    const canBook = me && me.is_captain;
    const root = document.getElementById('slotsRoot');
    root.innerHTML = '';

    const blocks = [
        {title: 'Дневной блок · 12:00–15:00', test: t => t < '15:00'},
        {title: 'Вечерний блок · 18:00–21:00', test: t => t >= '18:00'},
    ];

    for (const blk of blocks) {
        const h = document.createElement('h2');
        h.className = 'block-title';
        h.textContent = blk.title;
        root.appendChild(h);

        const grid = document.createElement('div');
        grid.className = 'grid';

        for (const slot of STATE.slots.filter(s => blk.test(s))) {
            const b = STATE.bookings[slot];
            const el = document.createElement('div');
            const isMine = me && me.my_slot === slot;

            el.className = 'slot' + (b ? ' taken' : '') + (isMine ? ' mine' : '') + (!canBook && !b ? ' disabled' : '');
            el.innerHTML = `<div class="t">${slot}</div>` +
                (b ? `<div class="who" title="${esc(b.project)} · ${esc(b.engine)} · готовность ${b.readiness}/10">${esc(b.name)}</div>`
                   : `<div class="who">свободно</div>`);

            if (!b && canBook) {
                el.onclick = () => moving ? doMove(slot) : openModal(slot);
            }
            grid.appendChild(el);
        }
        root.appendChild(grid);
    }
}

/* ── Модалка ── */
function openModal(slot) {
    if (STATE.me.my_slot) { alert(ERRORS.already_booked); return; }
    pendingSlot = slot;
    document.getElementById('modalTitle').textContent = `Запись на ${slot}`;
    document.getElementById('fName').value = STATE.me.name;
    document.getElementById('modalErr').textContent = '';
    document.getElementById('modalBg').classList.add('open');
    document.getElementById('fProject').focus();
}
function closeModal() {
    document.getElementById('modalBg').classList.remove('open');
    pendingSlot = null;
}
document.getElementById('fReady').oninput = e =>
    document.getElementById('fReadyVal').textContent = e.target.value;
document.getElementById('modalBg').onclick = e => {
    if (e.target.id === 'modalBg') closeModal();
};

async function submitBook() {
    const r = await api({
        action: 'book',
        slot: pendingSlot,
        project: document.getElementById('fProject').value,
        engine: document.getElementById('fEngine').value,
        readiness: +document.getElementById('fReady').value,
    });
    if (!r.ok) {
        document.getElementById('modalErr').textContent = ERRORS[r.error] || 'Ошибка';
        if (r.error === 'slot_taken') await loadState();
        return;
    }
    closeModal();
    await loadState();
}

/* ── Отмена / перенос ── */
async function doCancel() {
    if (!confirm('Отменить запись на слот?')) return;
    const r = await api({action: 'cancel'});
    if (!r.ok) alert(ERRORS[r.error] || 'Ошибка');
    await loadState();
}

function startMove() {
    moving = true;
    document.body.classList.add('moving');
    document.getElementById('meBar').innerHTML =
        '<span>Выбери новый свободный слот</span> <button onclick="cancelMove()">Не переносить</button>';
}
function cancelMove() {
    moving = false;
    document.body.classList.remove('moving');
    render();
}
async function doMove(slot) {
    const r = await api({action: 'move', slot});
    moving = false;
    document.body.classList.remove('moving');
    if (!r.ok) alert(ERRORS[r.error] || 'Ошибка');
    await loadState();
}

loadState();
setInterval(loadState, 30000); // мягкое авто-обновление расписания
</script>
</body>
</html>