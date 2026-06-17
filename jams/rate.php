<?php
require_once('../swad/config.php');
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login');
    exit;
}

$userId = $_SESSION['USERDATA']['id'];
$sprint_id = (int)($_GET['id'] ?? 0);
if (!$sprint_id) {
    die('Не указан ID спринта');
}

$db = (new Database())->connect();
if (!$db) die('Ошибка БД');

// Проверяем спринт
$sprintStmt = $db->prepare("SELECT title, status FROM sprints WHERE id = ?");
$sprintStmt->execute([$sprint_id]);
$sprint = $sprintStmt->fetch(PDO::FETCH_ASSOC);
if (!$sprint) die('Спринт не найден');

// Запрет для хоста: он видит страницу, но не может голосовать
$stmt = $db->prepare("SELECT host_user_id FROM sprints WHERE id = ?");
$stmt->execute([$sprint_id]);
$isHost = ($stmt->fetchColumn() == $userId);
if ($isHost) {
    $canVote = false;
} else {
    $canVote = true;
}

// Инициализация бюджета (только для голосующих, но создадим запись для всех, если нет)
$stmt = $db->prepare("
    INSERT IGNORE INTO sprint_vote_budgets (sprint_id, user_id, total_budget, used_budget)
    VALUES (?, ?, 10, 0)
");
$stmt->execute([$sprint_id, $userId]);

// Получаем used_budget
$stmt = $db->prepare("SELECT used_budget FROM sprint_vote_budgets WHERE sprint_id = ? AND user_id = ?");
$stmt->execute([$sprint_id, $userId]);
$usedBudget = (int)$stmt->fetchColumn();
$remainingBudget = 10 - $usedBudget;

// Создаём таблицу оценок, если нет
$db->exec("
    CREATE TABLE IF NOT EXISTS sprint_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sprint_id INT NOT NULL,
        submission_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT CHECK (rating BETWEEN 0 AND 10),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_rating (sprint_id, submission_id, user_id)
    )
");

// Получаем работы и уже отданные голоса
$subStmt = $db->prepare("
    SELECT 
        s.id,
        COALESCE(s.title, 'Без названия') AS title,
        s.description,
        s.build_url,
        -- s.screenshots,
        -- s.team_name,
        s.engine,
        s.user_id,
        u.username,
        r.rating AS my_rating,
        r.comment AS my_comment,
        (SELECT ROUND(AVG(r2.rating), 1) FROM sprint_ratings r2 WHERE r2.submission_id = s.id) AS avg_rating,
        (SELECT COUNT(*) FROM sprint_ratings r2 WHERE r2.submission_id = s.id) AS votes_count
    FROM sprint_submissions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN sprint_ratings r ON r.submission_id = s.id AND r.user_id = ?
    WHERE s.sprint_id = ?
    ORDER BY s.submitted_at DESC
");
$subStmt->execute([$userId, $sprint_id]);
$submissions = $subStmt->fetchAll(PDO::FETCH_ASSOC);

require_once('../swad/static/elements/header.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Оценка игр — <?= htmlspecialchars($sprint['title']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: #0d0414;
            font-family: 'Manrope', sans-serif;
            color: #e8ddf0;
        }
        .header {
            padding: 13px 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,.07);
        }
        .logo { font-weight:800; font-size:18px; }
        .logo .brand { color:#c32178; }
        .container { max-width:1200px; margin:0 auto; padding:32px 24px; }
        .page-title { font-size:24px; font-weight:800; margin-bottom:8px; }
        .page-sub { color:rgba(255,255,255,.4); margin-bottom:32px; }
        .budget-box {
            background: rgba(195,33,120,.15);
            border:1px solid rgba(195,33,120,.3);
            border-radius:16px;
            padding:12px 20px;
            display:inline-block;
            margin-bottom:24px;
        }
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        .game-preview {
            background: rgba(0,0,0,.4);
            border:1px solid rgba(255,255,255,.08);
            border-radius:16px;
            overflow:hidden;
            cursor:pointer;
            transition: all .2s;
        }
        .game-preview:hover {
            transform:translateY(-4px);
            border-color:rgba(195,33,120,.4);
            box-shadow:0 12px 28px rgba(195,33,120,.15);
        }
        .preview-cover {
            height:160px;
            background:linear-gradient(135deg,#1a0a24,#0d0414);
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
        }
        .preview-cover img { width:100%; height:100%; object-fit:cover; }
        .preview-info { padding:16px; }
        .preview-title { font-size:18px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .preview-author { font-size:12px; color:rgba(255,255,255,.5); margin:4px 0 8px; }
        .preview-meta { display:flex; justify-content:space-between; align-items:center; }
        .preview-rating { font-size:13px; background:rgba(195,33,120,.15); border-radius:20px; padding:4px 10px; }
        .overlay {
            position:fixed; inset:0; background:rgba(0,0,0,.85); backdrop-filter:blur(8px);
            display:flex; align-items:center; justify-content:center;
            opacity:0; visibility:hidden; transition:all .2s; padding:20px; z-index:1000;
        }
        .overlay.active { opacity:1; visibility:visible; }
        .modal {
            max-width:900px; width:100%; max-height:90vh; background:#120a1c;
            border:1px solid rgba(195,33,120,.3); border-radius:24px;
            overflow-y:auto; transform:scale(0.95); transition:transform .2s;
        }
        .overlay.active .modal { transform:scale(1); }
        .modal-header {
            display:flex; justify-content:space-between; align-items:center;
            padding:20px 24px; border-bottom:1px solid rgba(255,255,255,.1);
        }
        .modal-header h2 { font-size:22px; font-weight:800; }
        .close-modal { background:none; border:none; color:rgba(255,255,255,.6); font-size:28px; cursor:pointer; }
        .modal-body { padding:24px; }
        .screenshots { display:flex; gap:12px; overflow-x:auto; margin-bottom:24px; padding-bottom:8px; }
        .screenshots img { height:200px; border-radius:12px; object-fit:cover; }
        .game-description {
            background:rgba(0,0,0,.3); border-radius:16px; padding:16px;
            margin-bottom:20px; font-size:14px; line-height:1.5;
        }
        .game-details { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
        .detail-item { background:rgba(255,255,255,.05); border-radius:12px; padding:8px 14px; font-size:13px; }
        .detail-label { color:rgba(255,255,255,.4); margin-right:6px; }
        .rating-section { background:rgba(0,0,0,.3); border-radius:16px; padding:20px; margin-top:12px; }
        .vote-buttons { display:flex; gap:8px; flex-wrap:wrap; margin:12px 0; }
        .vote-btn {
            background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15);
            border-radius:30px; padding:6px 12px; cursor:pointer; font-size:13px; font-weight:600;
            color:rgba(255,255,255,.6);
        }
        .vote-btn.active { background:#c32178; border-color:#c32178; color:white; }
        .comment-input {
            width:100%; background:rgba(0,0,0,.5); border:1px solid rgba(255,255,255,.12);
            border-radius:12px; padding:12px; color:#e8ddf0; font-size:13px;
            resize:vertical; margin:12px 0;
        }
        .modal-actions { display:flex; gap:12px; margin:16px 0 12px; }
        .download-btn {
            background:rgba(195,33,120,.2); border:1px solid #c32178; border-radius:8px;
            padding:8px 16px; color:#e8ddf0; text-decoration:none; font-size:13px;
            display:inline-flex; align-items:center; gap:6px;
        }
        .save-rating-btn {
            background:#c32178; border:none; border-radius:10px; padding:10px 20px;
            font-weight:700; cursor:pointer; color:white;
        }
        .save-rating-btn:disabled { opacity:0.5; }
        .toast {
            position:fixed; bottom:24px; right:24px; background:#160822;
            border:1px solid #c32178; border-radius:12px; padding:12px 18px;
            opacity:0; transition:.2s; z-index:1100;
        }
        .toast.show { opacity:1; }
    </style>
</head>
<body>
<header class="header">
    <div class="logo"><span class="brand">Dustore</span> / Оценка джема</div>
    <a href="/jams" style="color:rgba(255,255,255,.5); text-decoration:none;">← Назад к спринтам</a>
</header>
<div class="container">
    <a href="participant.php?sprint_id=<?= $sprint_id ?>" style="display:inline-block; margin-bottom:16px; color:#c32178;">← Вернуться к спринту</a>
    <div class="page-title">Оцените игры</div>
    <div class="page-sub"><?= htmlspecialchars($sprint['title']) ?> — у вас 10 голосов. Распределите их между играми (от 0 до 10).</div>
    <div class="budget-box" id="budgetDisplay">Осталось голосов: <strong id="remainingVotes"><?= $remainingBudget ?></strong> / 10</div>

    <div class="games-grid" id="gamesGrid">
        <?php if (empty($submissions)): ?>
            <div style="text-align:center; padding:60px;">Пока нет загруженных работ</div>
        <?php else: ?>
            <?php foreach ($submissions as $sub):
                $screenshots = json_decode($sub['screenshots'] ?? '', true);
                $cover = is_array($screenshots) && !empty($screenshots) ? htmlspecialchars($screenshots[0]) : '';
                $avgRating = $sub['avg_rating'] ? round($sub['avg_rating'],1) : '—';
                $votesCount = $sub['votes_count'] ?? 0;
                $myRating = $sub['my_rating'] ?? null;
            ?>
                <div class="game-preview"
                     data-id="<?= $sub['id'] ?>"
                     data-title="<?= htmlspecialchars($sub['title']) ?>"
                     data-author="<?= htmlspecialchars($sub['username']) ?>"
                     data-description="<?= htmlspecialchars($sub['description'] ?? 'Нет описания') ?>"
                     data-team="<?= htmlspecialchars($sub['team_name'] ?? 'Не указана') ?>"
                     data-engine="<?= htmlspecialchars($sub['engine'] ?? 'Не указан') ?>"
                     data-screenshots='<?= json_encode($screenshots ?: []) ?>'
                     data-build-url="<?= htmlspecialchars($sub['build_url'] ?? '') ?>"
                     data-my-rating="<?= $myRating ?>"
                     data-my-comment="<?= htmlspecialchars($sub['my_comment'] ?? '') ?>"
                     data-avg-rating="<?= $avgRating ?>"
                     data-votes-count="<?= $votesCount ?>">
                    <div class="preview-cover">
                        <?php if ($cover): ?>
                            <img src="<?= $cover ?>" alt="cover">
                        <?php else: ?>
                            <span style="font-size:48px;">🎮</span>
                        <?php endif; ?>
                    </div>
                    <div class="preview-info">
                        <div class="preview-title"><?= htmlspecialchars($sub['title']) ?></div>
                        <div class="preview-author"><?= htmlspecialchars($sub['username']) ?></div>
                        <div class="preview-meta">
                            <span class="preview-rating">⭐ Средняя: <?= $avgRating ?> (<?= $votesCount ?> гол.)</span>
                            <span><?= $myRating !== null ? "Вы: $myRating" : 'Не оценено' ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="gameModal" class="overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle"></h2>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalScreenshots" class="screenshots"></div>
            <div id="modalDescription" class="game-description"></div>
            <div class="game-details">
                <div class="detail-item"><span class="detail-label">Команда:</span> <span id="modalTeam"></span></div>
                <div class="detail-item"><span class="detail-label">Движок:</span> <span id="modalEngine"></span></div>
                <div class="detail-item"><span class="detail-label">Автор:</span> <span id="modalAuthor"></span></div>
            </div>
            <div class="modal-actions">
                <a id="downloadLink" class="download-btn" target="_blank" style="display:none;">⬇ Скачать игру</a>
            </div>
            <div class="rating-section">
                <h4>Ваши голоса (0–10)</h4>
                <div id="modalVoteButtons" class="vote-buttons"></div>
                <textarea id="modalComment" class="comment-input" rows="3" placeholder="Комментарий (необязательно)"></textarea>
                <?php if ($canVote): ?>
                    <button id="modalSaveBtn" class="save-rating-btn" onclick="saveRatingFromModal()">Сохранить голоса</button>
                <?php else: ?>
                    <div style="background:#c3217820; padding:8px; border-radius:8px; margin-top:8px;">Хост спринта не может голосовать, но может просматривать оценки.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
    const sprintId = <?= $sprint_id ?>;
    let currentSubmissionId = null;
    let currentCard = null;
    const canVote = <?= json_encode($canVote) ?>;

    function showToast(msg, isError = false) {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.style.borderColor = isError ? '#f44336' : '#c32178';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function updateRemainingDisplay(newRemaining) {
        const remainingSpan = document.getElementById('remainingVotes');
        if (remainingSpan) {
            remainingSpan.textContent = newRemaining;
            const budgetBox = document.getElementById('budgetDisplay');
            if (newRemaining < 0) {
                budgetBox.style.borderColor = '#f44336';
            } else {
                budgetBox.style.borderColor = 'rgba(195,33,120,.3)';
            }
        }
    }

    function openModal(card) {
        currentCard = card;
        currentSubmissionId = parseInt(card.dataset.id);
        document.getElementById('modalTitle').innerText = card.dataset.title;
        document.getElementById('modalAuthor').innerText = card.dataset.author;
        document.getElementById('modalTeam').innerText = card.dataset.team;
        document.getElementById('modalEngine').innerText = card.dataset.engine;
        document.getElementById('modalDescription').innerText = card.dataset.description;

        let screenshots = [];
        try { screenshots = JSON.parse(card.dataset.screenshots); } catch(e) {}
        const screenshotsDiv = document.getElementById('modalScreenshots');
        screenshotsDiv.innerHTML = screenshots.map(url => `<img src="${escapeHtml(url)}" alt="screenshot">`).join('');
        if (!screenshots.length) screenshotsDiv.innerHTML = '<div style="color:rgba(255,255,255,.4);">Нет скриншотов</div>';

        const buildUrl = card.dataset.buildUrl;
        const downloadLink = document.getElementById('downloadLink');
        if (buildUrl && buildUrl !== '') {
            downloadLink.href = buildUrl;
            downloadLink.style.display = 'inline-flex';
        } else {
            downloadLink.style.display = 'none';
        }

        const myRating = parseInt(card.dataset.myRating) || 0;
        const container = document.getElementById('modalVoteButtons');
        container.innerHTML = '';
        for (let v = 0; v <= 10; v++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vote-btn' + (myRating === v ? ' active' : '');
            btn.textContent = v;
            btn.dataset.value = v;
            if (canVote) {
                btn.onclick = (function(val) {
                    return function() { selectVote(val); };
                })(v);
            } else {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            }
            container.appendChild(btn);
        }

        document.getElementById('modalComment').value = card.dataset.myComment || '';
        if (!canVote) document.getElementById('modalComment').disabled = true;
        document.getElementById('gameModal').classList.add('active');
    }

    function selectVote(value) {
        const btns = document.querySelectorAll('#modalVoteButtons .vote-btn');
        btns.forEach(btn => btn.classList.remove('active'));
        const target = Array.from(btns).find(btn => parseInt(btn.dataset.value) === value);
        if (target) target.classList.add('active');
    }

    function getSelectedVote() {
        const active = document.querySelector('#modalVoteButtons .vote-btn.active');
        return active ? parseInt(active.dataset.value) : 0;
    }

    async function saveRatingFromModal() {
        if (!canVote) {
            showToast('Вы не можете голосовать', true);
            return;
        }
        const newRating = getSelectedVote();
        const comment = document.getElementById('modalComment').value.trim();

        let totalVotes = 0;
        document.querySelectorAll('.game-preview').forEach(card => {
            let val = parseInt(card.dataset.myRating);
            if (isNaN(val)) val = 0;
            if (card === currentCard) val = newRating;
            totalVotes += val;
        });
        if (totalVotes > 10) {
            showToast(`Сумма голосов не может превышать 10. Сейчас ${totalVotes}`, true);
            return;
        }

        const btn = document.getElementById('modalSaveBtn');
        btn.disabled = true;
        btn.textContent = 'Сохранение...';
        try {
            const resp = await fetch('/swad/controllers/jams/save_rating.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sprint_id: sprintId,
                    submission_id: currentSubmissionId,
                    rating: newRating,
                    comment: comment || null
                })
            });
            const result = await resp.json();
            if (result.success) {
                showToast('Голоса сохранены');
                currentCard.dataset.myRating = newRating;
                currentCard.dataset.myComment = comment;
                if (result.avg_rating !== undefined) {
                    const ratingSpan = currentCard.querySelector('.preview-rating');
                    if (ratingSpan) {
                        ratingSpan.innerHTML = `⭐ Средняя: ${result.avg_rating} (${result.votes_count} гол.)`;
                    }
                }
                const metaSpan = currentCard.querySelector('.preview-meta span:last-child');
                if (metaSpan) metaSpan.textContent = newRating > 0 ? `Вы: ${newRating}` : 'Не оценено';
                if (result.remaining_budget !== undefined) {
                    updateRemainingDisplay(result.remaining_budget);
                }
                selectVote(newRating);
            } else {
                showToast(result.message || 'Ошибка сохранения', true);
            }
        } catch (err) {
            console.error(err);
            showToast('Ошибка сети', true);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Сохранить голоса';
        }
    }

    function closeModal() {
        document.getElementById('gameModal').classList.remove('active');
        currentSubmissionId = null;
        currentCard = null;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    document.querySelectorAll('.game-preview').forEach(card => {
        card.addEventListener('click', () => openModal(card));
    });
    document.getElementById('gameModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('gameModal')) closeModal();
    });
</script>
</body>
</html>