<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';
require_once(__DIR__ . '/../../swad/controllers/dev_contacts.php');

$db  = new Database();
$pdo = $db->connect();

$userId     = $_SESSION['USERDATA']['id'] ?? 0;
$globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
$isAdmin    = ($globalRole === -1);

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$userId]);
$expertRow = $stmt->fetch();
if (!$expertRow && !$isAdmin) die('Доступ запрещён');
$expertId = $expertRow['id'] ?? null;

$gameId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$gameId) die('Игра не найдена');

$stmt = $pdo->prepare("
    SELECT g.*, s.name AS studio_name, s.tiker, s.avatar_link,
           s.website AS studio_website, s.country, s.city, s.team_size, s.specialization
    FROM games g LEFT JOIN studios s ON g.developer = s.id WHERE g.id = ?
");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) die('Игра не найдена');

$features     = json_decode($game['features']     ?? '[]', true) ?: [];
$screenshots  = json_decode($game['screenshots']  ?? '[]', true) ?: [];
$requirements = json_decode($game['requirements'] ?? '[]', true) ?: [];

// Моя рецензия
$myReview = null; $hasMyReview = false;
if ($expertId) {
    $stmt = $pdo->prepare("SELECT score, comment, verdict FROM moderation_reviews WHERE game_id=? AND expert_id=?");
    $stmt->execute([$gameId, $expertId]);
    $myReview = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasMyReview = (bool)$myReview;
}

// Все рецензии
$stmt = $pdo->prepare("
    SELECT mr.id, mr.score, mr.comment AS review, mr.verdict,
           u.username, e.rating AS expert_weight, e.id AS eid
    FROM moderation_reviews mr
    JOIN experts e ON e.id = mr.expert_id
    JOIN users u ON u.id = e.user_id
    WHERE mr.game_id = ? ORDER BY mr.id DESC
");
$stmt->execute([$gameId]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT expert_id) FROM moderation_reviews WHERE game_id=?");
$stmt->execute([$gameId]);
$reviewCount = (int)$stmt->fetchColumn();
$needVotes = max(1, (int)ceil($totalExperts * 0.51));
$progress  = min(100, round($reviewCount / $needVotes * 100));

$stmt = $pdo->prepare("SELECT ROUND(AVG(score),1) AS avg_score, SUM(score>51) AS positive, SUM(score<=51) AS negative FROM moderation_reviews WHERE game_id=?");
$stmt->execute([$gameId]);
$avgScores = $stmt->fetch(PDO::FETCH_ASSOC);

$pendingExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
$pendingGames   = (int)$pdo->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();

// Билд: вес + дата загрузки + VT
$hasBuild   = !empty($game['game_zip_url']);
$isChunked  = $hasBuild && str_ends_with((string)$game['game_zip_url'], 'manifest.json');
$buildSize  = !empty($game['game_zip_size']) ? round($game['game_zip_size'] / 1048576, 1) . ' МБ' : '';
$uploadedAt = $game['game_zip_uploaded_at'] ?? $game['updated_at'] ?? null;
$vtStatus   = $game['vt_status'] ?? null;
$vtReport   = $game['vt_report_url'] ?? null;
$vtMap = [
    'clean' => ['✓ Вирусов не обнаружено', '#4ade80'], 'flagged' => ['⚠ Есть детекты', '#f87171'],
    'queued' => ['⏳ В очереди на проверку', '#fbbf24'], 'scanning' => ['⏳ Сканируется', '#fbbf24'],
    'error' => ['⚠ Ошибка сканирования', '#fbbf24'], 'skipped_oversize' => ['↷ Пропущено (размер)', '#6b7a99'],
];
[$vtLbl, $vtCol] = $vtMap[$vtStatus] ?? ['— ещё не проверялось', '#6b7a99'];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }
$active_page = 'moderation';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($game['name']) ?> — Экспертная модерация</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root{
            --bg:#0b0e13;--surface:#131720;--surface2:#1a2030;--border:#232b3a;
            --brand:#8b5cf6;--brand2:#a78bfa;--accent:#4ade80;--accent2:#22d3ee;
            --warn:#fbbf24;--danger:#f87171;--text:#e8edf5;--muted:#6b7a99;
        }
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}
        main{flex:1;overflow:auto;}
        .mg-wrap{max-width:1240px;margin:0 auto;padding:28px 34px 60px;}
        ::-webkit-scrollbar{width:8px;height:8px;} ::-webkit-scrollbar-thumb{background:var(--border);border-radius:8px;}

        /* topbar */
        .mg-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;gap:12px;flex-wrap:wrap;}
        .mg-back{display:inline-flex;align-items:center;gap:7px;color:var(--muted);text-decoration:none;font-size:.9rem;transition:.15s;}
        .mg-back:hover{color:var(--text);}
        .mg-help{display:inline-flex;align-items:center;gap:7px;background:var(--surface);border:1px solid var(--border);
            border-radius:10px;padding:8px 14px;color:var(--muted);font-size:.85rem;cursor:pointer;}
        .mg-help:hover{color:var(--text);border-color:var(--brand);}

        .card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;margin-bottom:18px;}
        .card-h{font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
        .muted{color:var(--muted);}

        /* hero */
        .mg-hero{display:grid;grid-template-columns:220px 1fr;gap:0;overflow:hidden;padding:0;}
        .mg-cover{position:relative;cursor:zoom-in;background:#0d1117;min-height:210px;}
        .mg-cover img{width:100%;height:100%;object-fit:cover;display:block;}
        .mg-cover .zoom{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
            background:rgba(0,0,0,0);color:#fff;font-size:1.6rem;opacity:0;transition:.2s;}
        .mg-cover:hover .zoom{background:rgba(0,0,0,.4);opacity:1;}
        .mg-hero-info{padding:24px;border-left:1px solid var(--border);}
        .mg-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.5rem;line-height:1.1;}
        .mg-studio{font-size:.9rem;color:var(--muted);margin:4px 0 16px;}
        .mg-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:12px 20px;margin-bottom:16px;}
        .mg-meta .lbl{font-size:.66rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:3px;}
        .mg-meta .val{font-size:.9rem;}
        .chip{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:.78rem;font-weight:700;}
        .chip-pending{background:rgba(251,191,36,.12);color:var(--warn);border:1px solid rgba(251,191,36,.25);}
        .chip-link{background:rgba(34,211,238,.1);color:var(--accent2);border:1px solid rgba(34,211,238,.2);text-decoration:none;}
        .mg-short{margin-top:14px;font-size:.85rem;color:var(--muted);line-height:1.6;border-top:1px solid var(--border);padding-top:14px;}

        /* two-col */
        .mg-grid{display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:start;}
        @media(max-width:980px){.mg-grid{grid-template-columns:1fr;} .mg-hero{grid-template-columns:1fr;} .mg-hero-info{border-left:none;border-top:1px solid var(--border);}}
        .mg-aside{position:sticky;top:20px;}

        /* screenshots */
        .scr-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
        .scr-thumb{aspect-ratio:16/9;border-radius:8px;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:.15s;background:var(--surface2);}
        .scr-thumb:hover{border-color:var(--accent2);transform:translateY(-2px);}
        .scr-thumb img{width:100%;height:100%;object-fit:cover;}

        /* build */
        .build-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
        .build-meta{display:flex;gap:18px;flex-wrap:wrap;font-size:.82rem;color:var(--muted);margin-top:6px;}
        .build-meta b{color:var(--text);font-weight:600;}
        .dl-btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,rgba(139,92,246,.2),rgba(34,211,238,.12));
            border:1px solid rgba(139,92,246,.35);border-radius:10px;padding:10px 18px;color:var(--brand2);text-decoration:none;font-weight:700;font-size:.88rem;}
        .vt-line{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:.85rem;font-weight:600;}

        /* progress */
        .prog-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
        .prog-bar{height:8px;background:var(--surface2);border-radius:5px;overflow:hidden;}
        .prog-fill{height:100%;background:linear-gradient(90deg,var(--brand),var(--accent2));border-radius:5px;transition:width .8s;}
        .prog-stats{display:flex;gap:16px;border-top:1px solid var(--border);margin-top:14px;padding-top:14px;}
        .prog-stat{text-align:center;flex:1;}
        .prog-stat b{font-family:'Syne',sans-serif;font-size:1.5rem;display:block;}
        .prog-stat span{font-size:.68rem;color:var(--muted);}

        /* review form */
        .lbl-req{font-size:.68rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);}
        .lbl-req span{color:var(--danger);}
        details.cl{background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:10px;margin-bottom:18px;}
        details.cl>summary{padding:12px 14px;cursor:pointer;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:8px;list-style:none;user-select:none;}
        #cl-counter{margin-left:auto;font-size:.72rem;font-weight:700;background:rgba(139,92,246,.15);color:var(--brand2);padding:2px 10px;border-radius:10px;}
        .cl-row{display:flex;align-items:flex-start;gap:10px;padding:9px 12px;border-radius:8px;cursor:pointer;}
        .cl-row+.cl-row{border-top:1px solid rgba(255,255,255,.04);}
        .cl-box{width:19px;height:19px;border-radius:5px;flex-shrink:0;margin-top:1px;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;transition:.15s;}
        .cl-mark{font-size:12px;color:var(--accent);display:none;}
        .score-num{font-family:'Syne',sans-serif;font-size:2.2rem;font-weight:800;line-height:1;color:var(--muted);}
        .presets{display:flex;justify-content:space-between;margin-top:6px;}
        .preset{text-align:center;cursor:pointer;padding:4px 6px;border-radius:6px;}
        .preset:hover{background:var(--surface2);}
        .ta{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px 14px;color:var(--text);
            font-family:'DM Sans',sans-serif;font-size:.9rem;line-height:1.6;resize:vertical;min-height:110px;outline:none;}
        .ta:focus{border-color:var(--brand);}
        .vbtn{width:100%;padding:13px 8px;border-radius:10px;border:2px solid var(--border);background:transparent;color:var(--muted);
            cursor:pointer;font-family:'DM Sans',sans-serif;font-weight:600;font-size:.82rem;text-align:center;transition:.2s;line-height:1.3;}
        .submit-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;border-radius:11px;border:none;
            background:var(--muted);color:#0b0e13;font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;cursor:not-allowed;transition:.2s;opacity:.5;}

        /* reviews */
        .rev{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:10px;}
        .rev.mine{border-color:var(--accent2);background:rgba(34,211,238,.04);}

        /* lightbox */
        .lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);align-items:center;justify-content:center;backdrop-filter:blur(6px);}
        .lightbox.open{display:flex;}
        .lightbox-img{max-width:90vw;max-height:88vh;border-radius:10px;}
        .lb-x{position:fixed;top:20px;right:24px;color:#fff;font-size:2rem;cursor:pointer;background:none;border:none;opacity:.75;}
        .lb-nav{position:fixed;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.1);border:none;color:#fff;font-size:1.8rem;cursor:pointer;padding:12px 16px;border-radius:8px;}
        .lb-prev{left:20px;} .lb-next{right:20px;}
        .lb-cnt{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.6);font-size:.85rem;}

        .help-panel{display:none;background:rgba(139,92,246,.06);border:1px solid rgba(139,92,246,.25);border-radius:14px;padding:18px 20px;margin-bottom:18px;font-size:.85rem;color:var(--muted);line-height:1.7;}
        .help-panel.open{display:block;}
    </style>
</head>
<body>

<?php require __DIR__ . '/_sidebar.php'; ?>

<main>
    <div class="mg-wrap">

        <div class="mg-top">
            <a href="/expert/admin/moderation" class="mg-back">← Назад к списку</a>
            <button class="mg-help" onclick="document.getElementById('help').classList.toggle('open')">
                <span class="material-icons" style="font-size:16px;">help_outline</span> Как проходит оценка?
            </button>
        </div>

        <div class="help-panel" id="help">
            Игра одобряется, когда «за» (👍 Рекомендую) набирает <b style="color:var(--text)"><?= $needVotes ?></b> из <?= $totalExperts ?> экспертов (порог 51%).
            «На доработку» и «Не рекомендую» считаются голосами против; при «на доработку» разработчику даётся 12 часов на исправление билда.
            Общая оценка ниже 51 = голос против. Рецензия анонимна до завершения голосования.
        </div>

        <!-- HERO -->
        <div class="card mg-hero">
            <div class="mg-cover" onclick="openLightboxSingle('<?= h($game['path_to_cover'] ?? '') ?>')"
                 style="<?= empty($game['path_to_cover']) ? 'display:flex;align-items:center;justify-content:center;font-size:3rem;' : '' ?>">
                <?php if (!empty($game['path_to_cover'])): ?>
                    <img src="<?= h($game['path_to_cover']) ?>" alt=""><span class="zoom">⤢</span>
                <?php else: ?>🎮<?php endif; ?>
            </div>
            <div class="mg-hero-info">
                <div class="mg-title"><?= h($game['name']) ?></div>
                <div class="mg-studio"><?= h($game['studio_name'] ?? 'Студия не указана') ?><?= !empty($game['tiker']) ? ' · ' . h($game['tiker']) : '' ?></div>
                
                <div class="mg-meta">
                    <div><div class="lbl">Жанр</div><div class="val"><?= h($game['genre'] ?? '—') ?></div></div>
                    <div><div class="lbl">Платформы</div><div class="val"><?= h($game['platforms'] ?? '—') ?></div></div>
                    <div><div class="lbl">Дата релиза</div><div class="val"><?= h($game['release_date'] ?? '—') ?></div></div>
                    <div><div class="lbl">Возраст</div><div class="val"><?= h($game['age_rating'] ?? '—') ?></div></div>
                    <div><div class="lbl">Языки</div><div class="val"><?= h($game['languages'] ?? '—') ?></div></div>
                    <div><div class="lbl">Цена</div><div class="val"><?= !empty($game['price']) ? h($game['price']) . ' ₽' : 'Бесплатно' ?></div></div>
                </div>
                
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <?php if (($game['moderation_status'] ?? '') === 'pending'): ?><span class="chip chip-pending">⏳ На модерации</span><?php endif; ?>
                    <?php if (!empty($game['trailer_url'])): ?><a class="chip chip-link" href="<?= h($game['trailer_url']) ?>" target="_blank">▶ Трейлер</a><?php endif; ?>
                    <?php if (!empty($game['GQI'])): ?><span class="chip" style="background:rgba(74,222,128,.1);color:var(--accent);border:1px solid rgba(74,222,128,.2);">GQI <?= (int)$game['GQI'] ?></span><?php endif; ?>
                </div>
                <?php if (!empty($game['short_description'])): ?><div class="mg-short"><?= h($game['short_description']) ?></div><?php endif; ?>
            </div>

        </div>

        <div class="mg-grid">
            <!-- LEFT -->
            <div class="mg-main">
<?php   echo dev_contacts_block($pdo, (int)$game['id']); ?>
                <?php if (!empty($screenshots)): ?>
                <div class="card">
                    <div class="card-h">🖼 Скриншоты <span class="muted" style="font-weight:400;font-size:.78rem;">· <?= count($screenshots) ?> шт</span></div>
                    <div class="scr-grid">
                        <?php foreach ($screenshots as $i => $s): ?>
                        <div class="scr-thumb" onclick="openLightbox(<?= $i ?>)"><img src="<?= h($s['path'] ?? '') ?>" alt=""></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($game['description'])): ?>
                <div class="card">
                    <div class="card-h">📖 Описание</div>
                    <div style="font-size:.9rem;color:rgba(232,237,245,.8);line-height:1.75;"><?= nl2br(h($game['description'])) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($features) || !empty($requirements)): ?>
                <div style="display:grid;grid-template-columns:<?= (!empty($features) && !empty($requirements)) ? '1fr 1fr' : '1fr' ?>;gap:16px;">
                    <?php if (!empty($features)): ?>
                    <div class="card">
                        <div class="card-h">✨ Особенности</div>
                        <?php foreach ($features as $f): ?>
                        <div style="display:flex;gap:8px;margin-bottom:8px;font-size:.85rem;">
                            <span style="color:var(--accent);flex-shrink:0;"><?= h($f['icon'] ?? '·') ?></span>
                            <div><b><?= h($f['title'] ?? '') ?></b><?= !empty($f['description']) ? ' <span class="muted">— ' . h($f['description']) . '</span>' : '' ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($requirements)): ?>
                    <div class="card">
                        <div class="card-h">💻 Требования</div>
                        <?php foreach ($requirements as $r): ?>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:.85rem;">
                            <span class="muted"><?= h($r['label'] ?? '') ?></span><span><?= h($r['value'] ?? '') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- BUILD -->
                <div class="card">
                    <div class="card-h">📦 Файл игры</div>
                    <?php if ($hasBuild): ?>
                    <div class="build-row">
                        <div>
                            <div style="font-weight:600;"><?= $isChunked ? 'Chunked-сборка' : 'Архив игры' ?></div>
                            <div class="build-meta">
                                <?php if ($buildSize): ?><span>Вес: <b><?= $buildSize ?></b></span><?php endif; ?>
                                <?php if ($uploadedAt): ?><span>Загружен: <b><?= date('d.m.Y H:i', strtotime($uploadedAt)) ?></b></span><?php endif; ?>
                            </div>
                        </div>
                        <a class="dl-btn" href="<?= h($game['game_zip_url']) ?>" target="_blank" <?= !$isChunked ? 'download' : '' ?>>
                            <span class="material-icons" style="font-size:17px;">download</span><?= $isChunked ? 'manifest.json' : 'Скачать билд' ?>
                        </a>
                    </div>
                    <div class="vt-line" style="color:<?= $vtCol ?>;">
                        <span class="material-icons" style="font-size:18px;">shield</span><?= $vtLbl ?>
                        <?php if ($vtReport): ?><a href="<?= h($vtReport) ?>" target="_blank" style="color:var(--accent2);font-weight:400;margin-left:6px;">отчёт ↗</a><?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div style="color:var(--warn);font-size:.85rem;">Файл игры не загружен.</div>
                    <?php endif; ?>
                </div>

                <?php include __DIR__ . '/../../swad/controllers/game_changelog_timeline.php'; ?>

                <!-- REVIEWS -->
                <?php if ($reviews): ?>
                <div class="card">
                    <div class="card-h">🗣 Рецензии (<?= count($reviews) ?>)</div>
                    <?php foreach ($reviews as $r):
                        $isMe = ($expertId && $r['eid'] == $expertId);
                        $v = $r['verdict'] ?? ($r['score'] > 51 ? 'recommend' : 'reject');
                        $vMap = ['recommend'=>['👍 Рекомендует','#4ade80'],'revision'=>['🔄 На доработку','#fbbf24'],'reject'=>['👎 Не рекомендует','#f87171']];
                        [$vlbl,$vcol] = $vMap[$v] ?? $vMap['reject'];
                    ?>
                    <div class="rev <?= $isMe ? 'mine' : '' ?>">
                        <?php if ($isMe): ?><div style="font-size:.72rem;font-weight:700;background:rgba(34,211,238,.15);color:var(--accent2);padding:2px 8px;border-radius:4px;display:inline-block;margin-bottom:8px;">★ ВАШ ОТЗЫВ · <a href="#review-form-section" style="color:var(--accent2);">изменить ↑</a></div><?php endif; ?>
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                            <div style="font-size:1.2rem;font-weight:800;color:<?= $vcol ?>;font-family:monospace;"><?= (int)$r['score'] ?></div>
                            <div style="font-size:.82rem;color:<?= $vcol ?>;font-weight:600;"><?= $vlbl ?></div>
                        </div>
                        <?php if ($isMe || ($game['moderation_status'] ?? '') !== 'pending'): ?>
                            <div style="font-size:.88rem;color:<?= $isMe ? 'var(--text)' : 'var(--muted)' ?>;line-height:1.6;font-style:italic;border-left:3px solid var(--border);padding-left:12px;">"<?= h($r['review']) ?>"</div>
                        <?php else: ?>
                            <div style="font-size:.82rem;color:var(--muted);font-style:italic;">🔒 Скрыто до завершения голосования</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: sticky action rail -->
            <div class="mg-aside">
                <!-- progress -->
                <div class="card">
                    <div class="prog-head"><span style="font-family:'Syne',sans-serif;font-weight:700;">Прогресс</span><span class="muted" style="font-size:.82rem;"><?= $reviewCount ?> из <?= $needVotes ?> · <?= $progress ?>%</span></div>
                    <div class="prog-bar"><div class="prog-fill" style="width:<?= $progress ?>%"></div></div>
                    <?php if ($reviewCount > 0 && $avgScores): ?>
                    <div class="prog-stats">
                        <div class="prog-stat"><b style="color:var(--brand2);"><?= $avgScores['avg_score'] ?></b><span>средний</span></div>
                        <div class="prog-stat"><b style="color:var(--accent);"><?= (int)$avgScores['positive'] ?></b><span>👍 за</span></div>
                        <div class="prog-stat"><b style="color:var(--danger);"><?= (int)$avgScores['negative'] ?></b><span>👎 против</span></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($expertId && ($game['moderation_status'] ?? '') === 'pending'): ?>
                <div class="card" id="review-form-section">
                    <div class="card-h"><?= $hasMyReview ? '✏️ Редактировать оценку' : '⭐ Оценить игру' ?></div>
                    <form method="post" action="submit-moderation?id=<?= $gameId ?>" id="review-form" novalidate>

                        <details class="cl">
                            <summary><span class="material-icons" style="font-size:16px;color:var(--muted);">checklist</span> Чеклист перед оценкой <span id="cl-counter">0 / 10</span></summary>
                            <div style="padding:0 8px 12px;">
                                <div class="muted" style="font-size:.76rem;padding:8px 6px 10px;line-height:1.5;">Не блокирует отправку — помогает структурировать мнение.</div>
                                <?php
                                $checklist = ['Игра запускается и проходима','Название и описание заполнены, без спама','Загружена корректная обложка','Минимум 3 реальных скриншота','Возрастной рейтинг соответствует','Нет явных нарушений авторских прав','Работает без критических вылетов','Управление понятно или объяснено','Нет незадекларированного 18+','Файл соответствует платформе'];
                                foreach ($checklist as $i => $label): ?>
                                <label class="cl-row">
                                    <input type="checkbox" name="checklist[]" value="<?= $i ?>" class="cl-inp" style="display:none;">
                                    <div class="cl-box"><span class="material-icons cl-mark">check</span></div>
                                    <span style="font-size:.82rem;line-height:1.45;"><?= h($label) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </details>

                        <div style="margin-bottom:22px;">
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;">
                                <span class="lbl-req">Общая оценка <span>*</span></span>
                                <div><span id="score-display" class="score-num">—</span><span class="muted"> / 100</span></div>
                            </div>
                            <input type="hidden" name="score" id="score-value" value="<?= $hasMyReview ? (int)$myReview['score'] : '' ?>">
                            <div style="position:relative;height:40px;">
                                <div id="sl-track" style="position:absolute;top:50%;transform:translateY(-50%);width:100%;height:8px;border-radius:4px;background:var(--border);cursor:pointer;"></div>
                                <div id="sl-fill" style="position:absolute;top:50%;transform:translateY(-50%);height:8px;border-radius:4px;width:0;background:var(--muted);pointer-events:none;"></div>
                                <div id="sl-thumb" style="position:absolute;top:50%;transform:translate(-50%,-50%);width:20px;height:20px;border-radius:50%;background:var(--muted);border:3px solid var(--bg);cursor:grab;display:none;box-shadow:0 2px 8px rgba(0,0,0,.4);"></div>
                            </div>
                            <div class="presets">
                                <?php foreach ([[0,'#f87171','Провал'],[25,'#fb923c','Слабо'],[51,'#fbbf24','Порог'],[75,'#a3e635','Хорошо'],[100,'#4ade80','Отлично']] as [$v,$c,$l]): ?>
                                <div class="preset" onclick="setScore(<?= $v ?>)"><div style="font-size:.7rem;font-weight:700;color:<?= $c ?>;"><?= $v ?></div><div style="font-size:.62rem;color:var(--muted);"><?= $l ?></div></div>
                                <?php endforeach; ?>
                            </div>
                            <div id="score-hint" style="margin-top:10px;padding:8px 12px;border-radius:8px;font-size:.82rem;font-weight:500;display:none;"></div>
                            <div id="score-err" style="margin-top:8px;font-size:.76rem;color:var(--danger);display:none;">⚠ Укажите оценку</div>
                        </div>

                        <div style="margin-bottom:22px;">
                            <label class="lbl-req" style="display:block;margin-bottom:8px;">Рецензия <span>*</span> <span class="muted" style="font-weight:400;text-transform:none;letter-spacing:0;">· анонимна до финала</span></label>
                            <textarea name="review" id="review-text" rows="5" required minlength="40" class="ta" oninput="checkReady()" placeholder="Что сделано хорошо? Что критично улучшить? Есть ли аудитория у этой игры?"><?= h($myReview['comment'] ?? '') ?></textarea>
                            <div style="display:flex;justify-content:space-between;margin-top:4px;"><span class="muted" style="font-size:.72rem;">Минимум 40 символов</span><span id="char-count" class="muted" style="font-size:.72rem;"><?= mb_strlen($myReview['comment'] ?? '') ?> симв.</span></div>
                        </div>

                        <input type="hidden" name="verdict" id="verdict-value" value="<?= h($myReview['verdict'] ?? '') ?>">
                        <div class="lbl-req" style="margin-bottom:10px;">Вердикт <span>*</span></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:6px;">
                            <button type="button" class="vbtn" id="v-recommend" data-v="recommend" onclick="setVerdict('recommend')" style="<?= ($hasMyReview && ($myReview['verdict'] ?? '') === 'recommend') ? 'border-color:#4ade80;background:rgba(74,222,128,.1);color:#4ade80;' : '' ?>">👍<br>Рекомендую</button>
                            <button type="button" class="vbtn" id="v-revision" data-v="revision" onclick="setVerdict('revision')" style="<?= ($hasMyReview && ($myReview['verdict'] ?? '') === 'revision') ? 'border-color:#fbbf24;background:rgba(251,191,36,.1);color:#fbbf24;' : '' ?>">🔄<br>На доработку</button>
                            <button type="button" class="vbtn" id="v-reject" data-v="reject" onclick="setVerdict('reject')" style="<?= ($hasMyReview && ($myReview['verdict'] ?? '') === 'reject') ? 'border-color:#f87171;background:rgba(248,113,113,.1);color:#f87171;' : '' ?>">👎<br>Не рекомендую</button>
                        </div>
                        <div id="verdict-err" style="font-size:.76rem;color:var(--danger);display:none;margin-bottom:12px;">⚠ Выберите вердикт</div>

                        <button type="submit" id="submit-btn" class="submit-btn" disabled>
                            <span class="material-icons" style="font-size:18px;">send</span><?= $hasMyReview ? 'Обновить оценку' : 'Отправить оценку' ?>
                        </button>
                    </form>
                </div>
                <?php elseif ($expertId): ?>
                <div class="card muted" style="text-align:center;padding:24px;font-size:.88rem;">Голосование завершено. Оценки больше не принимаются.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
    <button class="lb-x" onclick="closeLightboxBtn()">×</button>
    <button class="lb-nav lb-prev" onclick="lightboxNav(-1);event.stopPropagation()">‹</button>
    <img class="lightbox-img" id="lightbox-img" src="" alt="">
    <button class="lb-nav lb-next" onclick="lightboxNav(1);event.stopPropagation()">›</button>
    <div class="lb-cnt" id="lightbox-counter"></div>
</div>

<script>
    const LB_IMAGES = <?= json_encode(array_values(array_map(fn($s) => $s['path'] ?? '', $screenshots))) ?>;
    let lbIndex = 0;
    function openLightbox(idx){ lbIndex=idx; document.getElementById('lightbox-img').src=LB_IMAGES[lbIndex]; document.getElementById('lightbox-counter').textContent=(lbIndex+1)+' / '+LB_IMAGES.length; document.querySelector('.lb-prev').style.display=''; document.querySelector('.lb-next').style.display=''; document.getElementById('lightbox').classList.add('open'); document.body.style.overflow='hidden'; }
    function openLightboxSingle(src){ if(!src)return; document.getElementById('lightbox-img').src=src; document.getElementById('lightbox-counter').textContent=''; document.querySelector('.lb-prev').style.display='none'; document.querySelector('.lb-next').style.display='none'; document.getElementById('lightbox').classList.add('open'); document.body.style.overflow='hidden'; }
    function closeLightbox(e){ if(e.target===document.getElementById('lightbox')) closeLightboxBtn(); }
    function closeLightboxBtn(){ document.getElementById('lightbox').classList.remove('open'); document.body.style.overflow=''; }
    function lightboxNav(d){ if(!LB_IMAGES.length)return; lbIndex=(lbIndex+d+LB_IMAGES.length)%LB_IMAGES.length; document.getElementById('lightbox-img').src=LB_IMAGES[lbIndex]; document.getElementById('lightbox-counter').textContent=(lbIndex+1)+' / '+LB_IMAGES.length; }
    document.addEventListener('keydown',e=>{ if(!document.getElementById('lightbox').classList.contains('open'))return; if(e.key==='Escape')closeLightboxBtn(); if(e.key==='ArrowRight')lightboxNav(1); if(e.key==='ArrowLeft')lightboxNav(-1); });

    // Чеклист
    document.querySelectorAll('.cl-row').forEach(row=>{
        const inp=row.querySelector('.cl-inp'),box=row.querySelector('.cl-box'),mark=row.querySelector('.cl-mark');
        const sync=()=>{ box.style.background=inp.checked?'rgba(74,222,128,.15)':'transparent'; box.style.borderColor=inp.checked?'var(--accent)':'var(--border)'; mark.style.display=inp.checked?'block':'none';
            const n=document.querySelectorAll('.cl-inp:checked').length; const ctr=document.getElementById('cl-counter'); ctr.textContent=n+' / 10'; };
        row.addEventListener('click',()=>{ inp.checked=!inp.checked; sync(); });
        if(inp.checked) sync();
    });

    // Слайдер оценки
    const track=document.getElementById('sl-track'),fill=document.getElementById('sl-fill'),thumb=document.getElementById('sl-thumb'),
          disp=document.getElementById('score-display'),hidden=document.getElementById('score-value');
    let dragging=false, scoreSet=<?= $hasMyReview ? 'true' : 'false' ?>;
    function scoreColor(v){ if(v<=25)return '#f87171'; if(v<=51)return '#fbbf24'; if(v<=75)return '#a3e635'; return '#4ade80'; }
    function setScore(v){ v=Math.max(0,Math.min(100,Math.round(v))); scoreSet=true; hidden.value=v; const p=v/100;
        fill.style.width=(p*100)+'%'; fill.style.background=scoreColor(v); thumb.style.left=(p*track.offsetWidth)+'px'; thumb.style.background=scoreColor(v); thumb.style.display='block';
        disp.textContent=v; disp.style.color=scoreColor(v);
        const hints=[[0,'Игра не работает или содержит критические проблемы.'],[26,'Значительные проблемы, мешающие игровому опыту.'],[51,'Граница — ниже этого голос считается «против».'],[66,'Хорошая игра, заслуживает публикации.'],[81,'Выдающееся качество для инди-проекта.']];
        let ht=hints[0][1]; for(const [thr,txt] of hints) if(v>=thr) ht=txt;
        const hint=document.getElementById('score-hint'); const c=scoreColor(v);
        hint.style.cssText='background:'+c+'18;border:1px solid '+c+'44;color:'+c+';display:block;padding:8px 12px;border-radius:8px;font-size:.82rem;font-weight:500;margin-top:10px;';
        hint.textContent=ht; document.getElementById('score-err').style.display='none'; checkReady();
    }
    function posFromEvent(e){ const r=track.getBoundingClientRect(); const cx=e.touches?e.touches[0].clientX:e.clientX; return Math.max(0,Math.min(1,(cx-r.left)/r.width)); }
    if(track){
        track.addEventListener('mousedown',e=>{dragging=true;setScore(Math.round(posFromEvent(e)*100));});
        track.addEventListener('touchstart',e=>{dragging=true;setScore(Math.round(posFromEvent(e)*100));},{passive:true});
        document.addEventListener('mousemove',e=>{if(dragging)setScore(Math.round(posFromEvent(e)*100));});
        document.addEventListener('touchmove',e=>{if(dragging)setScore(Math.round(posFromEvent(e)*100));},{passive:true});
        document.addEventListener('mouseup',()=>dragging=false); document.addEventListener('touchend',()=>dragging=false);
    }
    <?php if ($hasMyReview): ?>setTimeout(()=>setScore(<?= (int)$myReview['score'] ?>),30);<?php endif; ?>

    // Вердикт
    const VCOLORS={recommend:{b:'#4ade80',bg:'rgba(74,222,128,.1)'},revision:{b:'#fbbf24',bg:'rgba(251,191,36,.1)'},reject:{b:'#f87171',bg:'rgba(248,113,113,.1)'}};
    function setVerdict(v){ document.getElementById('verdict-value').value=v;
        document.querySelectorAll('.vbtn').forEach(b=>{ const bv=b.dataset.v; if(bv===v){b.style.borderColor=VCOLORS[bv].b;b.style.background=VCOLORS[bv].bg;b.style.color=VCOLORS[bv].b;}else{b.style.borderColor='var(--border)';b.style.background='transparent';b.style.color='var(--muted)';} });
        document.getElementById('verdict-err').style.display='none'; checkReady();
    }

    function checkReady(){
        const s=scoreSet&&hidden.value!=='';
        const v=document.getElementById('verdict-value').value!=='';
        const tEl=document.getElementById('review-text'); const t=(tEl?.value.length??0)>=40;
        document.getElementById('char-count').textContent=(tEl?.value.length??0)+' симв.';
        const ok=s&&v&&t; const btn=document.getElementById('submit-btn'); if(!btn)return;
        btn.disabled=!ok; btn.style.opacity=ok?'1':'.5'; btn.style.background=ok?'var(--brand)':'var(--muted)'; btn.style.color=ok?'#fff':'#0b0e13'; btn.style.cursor=ok?'pointer':'not-allowed';
    }
    document.getElementById('review-text')?.addEventListener('input',checkReady);
    document.getElementById('review-form')?.addEventListener('submit',e=>{
        let ok=true;
        if(!hidden.value){document.getElementById('score-err').style.display='block';ok=false;}
        if(!document.getElementById('verdict-value').value){document.getElementById('verdict-err').style.display='block';ok=false;}
        if(!ok){e.preventDefault();document.getElementById('score-err').scrollIntoView({behavior:'smooth',block:'center'});}
    });
    checkReady();
</script>
</body>
</html>