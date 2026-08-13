<?php
// expert/admin/_sidebar.php — единый сайдбар админки экспертов (со своими стилями).
// Перед include задай: $active_page = 'index' | 'experts' | 'moderation' | 'reviews'
// Требует доступный $pdo (иначе поднимет сам) и $_SESSION['USERDATA'].

if (!isset($pdo) || !$pdo) { $pdo = (new Database())->connect(); }
$sb_isAdmin = isset($isAdmin) ? (bool)$isAdmin : (((int)($_SESSION['USERDATA']['global_role'] ?? 0)) === -1);
$sb_pExperts = isset($pendingExperts) ? (int)$pendingExperts : (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
$sb_pGames   = isset($pendingGames)   ? (int)$pendingGames   : (int)$pdo->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();
$sb_uname    = $_SESSION['USERDATA']['username'] ?? '—';
$sb_active   = $active_page ?? '';
?>
<style>
    .ea-side{width:240px;flex-shrink:0;background:var(--surface,#131720);border-right:1px solid var(--border,#232b3a);
        display:flex;flex-direction:column;min-height:100vh;position:sticky;top:0;height:100vh;overflow-y:auto;}
    .ea-logo{padding:26px 22px 18px;font-family:'Syne',sans-serif;font-weight:800;font-size:1.25rem;
        color:var(--brand,#8b5cf6);letter-spacing:-.5px;border-bottom:1px solid var(--border,#232b3a);margin-bottom:8px;}
    .ea-logo span{display:block;color:var(--muted,#6b7a99);font-size:.68rem;font-weight:400;letter-spacing:.5px;text-transform:uppercase;margin-top:3px;}
    .ea-nav-title{padding:12px 18px 4px;font-size:.66rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted,#6b7a99);}
    .ea-nav{display:flex;align-items:center;gap:10px;margin:2px 10px;padding:10px 14px;border-radius:9px;
        color:var(--muted,#6b7a99);font-size:.9rem;font-weight:500;text-decoration:none;transition:.15s;}
    .ea-nav:hover{background:var(--surface2,#1a2030);color:var(--text,#e8edf5);}
    .ea-nav.active{background:rgba(139,92,246,.14);color:#c4b5fd;}
    .ea-badge{margin-left:auto;background:var(--brand,#8b5cf6);color:#fff;font-size:.7rem;font-weight:700;border-radius:12px;padding:2px 8px;}
    .ea-side-foot{margin-top:auto;padding:14px;border-top:1px solid var(--border,#232b3a);}
    .ea-user{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--surface2,#1a2030);border-radius:10px;}
    .ea-ava{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#8b5cf6,#22d3ee);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:.85rem;color:#0b0e13;}
    .ea-uname{font-size:.85rem;font-weight:600;} .ea-urole{font-size:.72rem;color:var(--muted,#6b7a99);}
    .ea-logout{display:block;margin-top:8px;padding:8px 12px;font-size:.85rem;color:var(--danger,#f87171);text-decoration:none;}
    .ea-logout:hover{color:#fca5a5;}
</style>
<aside class="ea-side">
    <div class="ea-logo">Dustore <span><?= $sb_isAdmin ? 'Admin Panel' : 'Expert Panel' ?></span></div>
    <div class="ea-nav-title">Меню</div>
    <a href="/expert/admin/index" class="ea-nav <?= $sb_active === 'index' ? 'active' : '' ?>">🏠 Главная</a>
    <?php if ($sb_isAdmin): ?>
    <a href="/expert/admin/expert-requests" class="ea-nav <?= $sb_active === 'experts' ? 'active' : '' ?>">
        👤 Заявки экспертов <?php if ($sb_pExperts > 0): ?><span class="ea-badge"><?= $sb_pExperts ?></span><?php endif; ?>
    </a>
    <?php endif; ?>
    <a href="/expert/admin/moderation" class="ea-nav <?= $sb_active === 'moderation' ? 'active' : '' ?>">
        🎮 Модерация игр <?php if ($sb_pGames > 0): ?><span class="ea-badge"><?= $sb_pGames ?></span><?php endif; ?>
    </a>
    <a href="/expert/admin/all-reviews" class="ea-nav <?= $sb_active === 'reviews' ? 'active' : '' ?>">📊 Все оценки</a>
    <div class="ea-side-foot">
        <div class="ea-user">
            <div class="ea-ava"><?= mb_strtoupper(mb_substr($sb_uname, 0, 1)) ?></div>
            <div>
                <div class="ea-uname"><?= htmlspecialchars($sb_uname) ?></div>
                <div class="ea-urole"><?= $sb_isAdmin ? 'Администратор' : 'Эксперт' ?></div>
            </div>
        </div>
        <a href="/expert/admin/logout" class="ea-logout">Выйти →</a>
    </div>
</aside>