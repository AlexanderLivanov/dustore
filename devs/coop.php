<?php
/**
 * devs/coop.php — «Соучастники проектов».
 *   • DustHunt игры — охота с датами, условиями, наградой и лимитом участников (в баннере игры и в /fid);
 *   • модераторы игры — люди, которые следят за стеной и обсуждениями ЭТОЙ игры (не модераторы платформы);
 *   • подписи сотрудников — что написано под их постами в девлоге студии.
 * Менять всё это может владелец/администратор студии; остальным раздел доступен для чтения.
 */
$page_title = 'Соучастники проектов';
$active_nav = 'coop';
require_once(__DIR__ . '/includes/header.php');
require_once(__DIR__ . '/../swad/controllers/csrf.php');
require_once(__DIR__ . '/../swad/fx/boot.php');

$conn = $db->connect();
Fx::use($conn);

$uid      = (int)$user_id;
$canEdit  = FxAuth::isStudioOwner($studio_id, $uid);
$msg = $err = '';

/* игры студии */
$gs = $conn->prepare("SELECT id, name, icon_url, path_to_cover, status FROM games WHERE developer = ? ORDER BY id DESC");
$gs->execute([$studio_id]);
$games = $gs->fetchAll(PDO::FETCH_ASSOC);
$gameId = (int)($_GET['game'] ?? $_POST['game'] ?? ($games[0]['id'] ?? 0));
$game = null;
foreach ($games as $g) if ((int)$g['id'] === $gameId) $game = $g;
if (!$game && $games) { $game = $games[0]; $gameId = (int)$game['id']; }

$errText = [
    'forbidden' => 'Недостаточно прав: менять это может владелец или администратор студии.',
    'title_required' => 'Назовите охоту.', 'bad_dates' => 'Проверьте даты: конец должен быть позже начала.',
    'not_found' => 'Запись не найдена.', 'empty' => 'Укажите имя пользователя.', 'no_user' => 'Такого игрока нет на платформе.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $err = 'Сессия устарела — обновите страницу.';
    } else {
        $act = (string)($_POST['action'] ?? '');
        $r = ['ok' => false, 'error' => 'forbidden'];
        if ($act === 'hunt_save' && $gameId)        { $r = FxHunt::save($uid, $gameId, $_POST);                            $msg = 'DustHunt сохранён.'; }
        elseif ($act === 'hunt_end' && $gameId)     { $r = FxHunt::end($uid, $gameId, (int)($_POST['id'] ?? 0));           $msg = 'Охота завершена.'; }
        elseif ($act === 'mod_add' && $gameId)      { $r = FxMods::add($uid, $gameId, (string)($_POST['who'] ?? ''));      $msg = 'Модератор назначен.'; }
        elseif ($act === 'mod_del' && $gameId)      { $r = FxMods::remove($uid, $gameId, (int)($_POST['user_id'] ?? 0));   $msg = 'Модератор снят.'; }
        elseif ($act === 'title_save' && $canEdit)  { FxMonet::setTitle($studio_id, (int)($_POST['user_id'] ?? 0), (string)($_POST['title'] ?? '')); $r = ['ok' => true]; $msg = 'Подпись сохранена.'; }
        if (empty($r['ok'])) { $err = $errText[$r['error'] ?? ''] ?? 'Не удалось сохранить.'; $msg = ''; }
    }
}

$hunts = $gameId ? FxHunt::listForGame($gameId) : [];
$mods  = $gameId ? FxMods::list($gameId) : [];
$team  = FxStats::team($studio_id);
$edit  = null;
if (isset($_GET['edit'])) foreach ($hunts as $h) if ($h['id'] === (int)$_GET['edit']) $edit = $h;
$dt = static fn(?string $s): string => $s ? date('Y-m-d\TH:i', strtotime($s)) : '';
$stateLbl = ['live' => ['идёт', 'var(--ok)'], 'soon' => ['скоро', 'var(--warn)'], 'ended' => ['завершён', 'var(--tm)']];
$e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>

<style>
    .cp-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start }
    @media (max-width: 980px) { .cp-grid { grid-template-columns: 1fr } }
    .cp-row { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--bd); border-radius: 12px; background: var(--elev, rgba(255,255,255,.03)) }
    .cp-row + .cp-row { margin-top: 8px }
    .cp-row .grow { flex: 1; min-width: 0 }
    .cp-row b { display: block; font-size: 14px }
    .cp-row small { color: var(--tm); font-size: 12px }
    .cp-pill { font-size: 10px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; border: 1px solid currentColor; border-radius: 999px; padding: 2px 8px }
    .cp-games { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px }
    .cp-games a { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px 6px 6px; border: 1px solid var(--bd); border-radius: 999px; color: var(--ts); text-decoration: none; font-size: 13px; font-weight: 600 }
    .cp-games a img { width: 26px; height: 26px; border-radius: 8px; object-fit: cover }
    .cp-games a.on, .cp-games a:hover { border-color: var(--p); color: #fff }
    .cp-flash { animation: cpflash 1.6s ease-out }
    @keyframes cpflash { 0% { box-shadow: 0 0 0 3px var(--p) } 100% { box-shadow: 0 0 0 3px transparent } }
    .cp-note { font-size: 12.5px; color: var(--tm); line-height: 1.55 }
    .cp-note b { color: var(--ts) }
</style>

<?php if ($msg): ?><div class="alert alert-ok"><?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= $e($err) ?></div><?php endif; ?>

<?php if (!$games): ?>
    <div class="card" style="text-align:center;padding:40px;">
        <span class="material-icons" style="font-size:40px;color:var(--p);display:block;margin-bottom:10px;">videogame_asset</span>
        <p style="color:var(--ts);">У студии пока нет проектов — создайте игру, и здесь появятся охоты и модераторы.</p>
        <a class="btn btn-p" href="/devs/new" style="margin-top:8px;">Новый проект</a>
    </div>
<?php else: ?>

    <div class="cp-games" aria-label="Игра">
        <?php foreach ($games as $g): $img = $g['icon_url'] ?: $g['path_to_cover']; ?>
            <a href="/devs/coop?game=<?= (int)$g['id'] ?>" class="<?= (int)$g['id'] === $gameId ? 'on' : '' ?>">
                <?php if ($img): ?><img src="<?= $e($img) ?>" alt=""><?php endif; ?><?= $e($g['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$canEdit): ?>
        <div class="alert" style="margin-bottom:14px;background:rgba(255,255,255,.04);border:1px solid var(--bd);color:var(--ts);">
            <span class="material-icons" style="vertical-align:-6px;margin-right:6px;">lock</span>Вы можете смотреть, но менять охоты и модераторов может только владелец или администратор студии.
        </div>
    <?php endif; ?>

    <div class="cp-grid">
        <div style="display:flex;flex-direction:column;gap:16px;">

            <!-- DustHunt -->
            <div class="card" id="hunt">
                <div class="card-title"><span class="material-icons">track_changes</span>DustHunt · «<?= $e($game['name']) ?>»</div>
                <p class="cp-note" style="margin:0 0 14px;">Охота — задание для игроков на ограниченное время. Актуальная охота показывается окошком в баннере страницы игры и во вкладке DustHunt в ленте; игроки регистрируются одним нажатием.</p>

                <?php if ($hunts): foreach ($hunts as $h): [$lbl, $col] = $stateLbl[$h['state']]; ?>
                    <div class="cp-row">
                        <div class="grow">
                            <b><?= $e($h['title']) ?></b>
                            <small><?= $e(date('d.m.Y H:i', strtotime($h['starts_at']))) ?> — <?= $e(date('d.m.Y H:i', strtotime($h['ends_at']))) ?> · участников: <?= (int)$h['players'] ?><?= $h['max'] ? ' из ' . (int)$h['max'] : '' ?><?= $h['prize'] !== '' ? ' · награда: ' . $e($h['prize']) : '' ?></small>
                        </div>
                        <span class="cp-pill" style="color:<?= $col ?>"><?= $lbl ?></span>
                        <?php if ($canEdit): ?>
                            <a class="btn btn-g" style="padding:5px 10px;" href="/devs/coop?game=<?= $gameId ?>&edit=<?= (int)$h['id'] ?>#hunt-form" title="Изменить"><span class="material-icons">edit</span></a>
                            <?php if ($h['state'] !== 'ended'): ?>
                                <form method="POST" onsubmit="return confirm('Завершить охоту досрочно?')">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="hunt_end"><input type="hidden" name="game" value="<?= $gameId ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                    <button class="btn btn-d" style="padding:5px 10px;" title="Завершить"><span class="material-icons">stop</span></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; else: ?>
                    <div class="cp-note" style="padding:6px 0 4px;">Охот пока не было. Создайте первую — это самый быстрый способ вернуть игроков в игру.</div>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                    <form method="POST" id="hunt-form" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--bd);">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="hunt_save"><input type="hidden" name="game" value="<?= $gameId ?>">
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
                        <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--tm);margin-bottom:10px;"><?= $edit ? 'Изменить охоту' : 'Новая охота' ?></div>
                        <div class="grid-2">
                            <div class="field col-full"><label>Название *</label><input type="text" name="title" maxlength="120" required value="<?= $e($edit['title'] ?? '') ?>" placeholder="Найти 12 плёнок"></div>
                            <div class="field"><label>Начало *</label><input type="datetime-local" name="starts_at" required value="<?= $e($dt($edit['starts_at'] ?? date('Y-m-d H:i'))) ?>"></div>
                            <div class="field"><label>Конец *</label><input type="datetime-local" name="ends_at" required value="<?= $e($dt($edit['ends_at'] ?? date('Y-m-d H:i', time() + 7 * 86400))) ?>"></div>
                            <div class="field col-full"><label>Условия участия</label><textarea name="rules" maxlength="2000" style="min-height:90px;" placeholder="Что нужно сделать, чтобы выполнить задание"><?= $e($edit['rules'] ?? '') ?></textarea></div>
                            <div class="field"><label>Награда</label><input type="text" name="prize" maxlength="200" value="<?= $e($edit['prize'] ?? '') ?>" placeholder="Рамка профиля, ключ, скидка…"></div>
                            <div class="field"><label>Лимит участников <small style="color:var(--tm)">(пусто — без лимита)</small></label><input type="number" name="max_players" min="1" value="<?= $e($edit['max'] ?? '') ?>"></div>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:6px;">
                            <button class="btn btn-p" type="submit"><span class="material-icons">save</span><?= $edit ? 'Сохранить' : 'Запустить охоту' ?></button>
                            <?php if ($edit): ?><a class="btn btn-g" href="/devs/coop?game=<?= $gameId ?>#hunt">Отмена</a><?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Подписи сотрудников -->
            <div class="card" id="titles">
                <div class="card-title"><span class="material-icons">badge</span>Подписи сотрудников</div>
                <p class="cp-note" style="margin:0 0 14px;">Под каждым постом сотрудника в девлоге студии и на странице игры стоит подпись. По умолчанию — роль в студии; здесь можно задать свою («Ведущий геймдизайнер», «Комьюнити-менеджер»).</p>
                <?php foreach ($team as $m): ?>
                    <div class="cp-row">
                        <div class="fx-mini" style="width:34px;height:34px;border-radius:50%;background:var(--elev) center/cover;flex:none;<?= $m['img'] ? 'background-image:url(\'' . $e($m['img']) . '\')' : '' ?>"></div>
                        <div class="grow"><b><?= $e($m['name']) ?></b><small><?= $e($m['role']) ?></small></div>
                        <?php if ($canEdit): ?>
                            <form method="POST" style="display:flex;gap:6px;align-items:center;">
                                <?= csrf_field() ?><input type="hidden" name="action" value="title_save"><input type="hidden" name="game" value="<?= $gameId ?>"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                <input type="text" name="title" maxlength="60" value="<?= $e($m['title']) ?>" placeholder="<?= $e($m['role']) ?>" style="width:190px;">
                                <button class="btn btn-g" style="padding:6px 10px;" title="Сохранить"><span class="material-icons">check</span></button>
                            </form>
                        <?php else: ?>
                            <small><?= $e($m['title'] ?: '—') ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Модераторы игры -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div class="card" id="mods">
                <div class="card-title"><span class="material-icons">shield</span>Модераторы игры</div>
                <?php if ($mods): foreach ($mods as $m): ?>
                    <div class="cp-row">
                        <div style="width:34px;height:34px;border-radius:50%;background:var(--elev) center/cover;flex:none;<?= $m['img'] ? 'background-image:url(\'' . $e($m['img']) . '\')' : '' ?>"></div>
                        <div class="grow"><b><?= $e($m['name']) ?></b><small>с <?= $e(date('d.m.Y', strtotime($m['since']))) ?></small></div>
                        <?php if ($canEdit): ?>
                            <form method="POST" onsubmit="return confirm('Снять модератора?')">
                                <?= csrf_field() ?><input type="hidden" name="action" value="mod_del"><input type="hidden" name="game" value="<?= $gameId ?>"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                <button class="btn btn-d" style="padding:5px 10px;" title="Снять"><span class="material-icons">person_remove</span></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; else: ?>
                    <div class="cp-note">Модераторов пока нет.</div>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                    <form method="POST" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--bd);">
                        <?= csrf_field() ?><input type="hidden" name="action" value="mod_add"><input type="hidden" name="game" value="<?= $gameId ?>">
                        <div class="field"><label>Username или id игрока</label><input type="text" name="who" required placeholder="username"></div>
                        <button class="btn btn-p" style="width:100%;justify-content:center;"><span class="material-icons">person_add</span>Назначить</button>
                    </form>
                <?php endif; ?>

                <div class="cp-note" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--bd);">
                    <b>Что может модератор игры:</b> удалять сообщения на стене и в обсуждениях этой игры, закреплять посты и ветки, закрывать ветки.<br>
                    <b>Чего не может:</b> ничего за пределами этой игры и ничего на платформе в целом — это не модератор Dustore.
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    /* Переход из хаба игры по ссылке с якорем: подсвечиваем нужную карточку */
    (function () {
        var id = location.hash.replace('#', ''); if (!id) return;
        var el = document.getElementById(id); if (!el) return;
        el.scrollIntoView({ block: 'center' }); el.classList.add('cp-flash');
    })();
</script>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>
