<?php

/**
 * m/views/search.php
 */
$q = trim($_GET['q'] ?? '');
?>
<div style="padding:12px 14px 0;background:linear-gradient(180deg,var(--dark2),var(--dark))">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <a href="javascript:history.back()" style="color:var(--txt2);font-size:22px;flex-shrink:0;line-height:1">
            <i class="ti ti-arrow-left"></i>
        </a>
        <div class="search-pill" style="flex:1">
            <i class="ti ti-search"></i>
            <input type="search" id="live-q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="Игры, студии..."
                autocomplete="off" autofocus>
        </div>
    </div>
</div>

<div id="search-results" style="padding:0">
    <?php if (!$q): ?>
        <div class="sec-label">Популярные жанры</div>
        <?php
        $pop_genres = ['Экшен' => 'action', 'RPG' => 'rpg', 'Инди' => 'indie', 'Хоррор' => 'horror', 'Пазл' => 'puzzle'];
        foreach ($pop_genres as $label => $slug):
        ?>
            <a href="/m/catalog?genre=<?= $slug ?>" class="list-row">
                <div style="width:52px;height:52px;border-radius:8px;background:var(--surf2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="ti ti-tag" style="font-size:22px;color:var(--txt3)"></i>
                </div>
                <div class="list-body">
                    <div class="list-title"><?= $label ?></div>
                </div>
                <i class="ti ti-chevron-right" style="color:var(--txt3)"></i>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    var _t = null;
    document.getElementById('live-q').addEventListener('input', function() {
        clearTimeout(_t);
        _t = setTimeout(doSearch, 260);
    });

    function doSearch() {
        var q = document.getElementById('live-q').value.trim();
        var box = document.getElementById('search-results');
        if (q.length < 2) {
            box.innerHTML = '';
            return;
        }

        box.innerHTML = '<div class="skel" style="height:64px;margin:8px 14px;"></div>'.repeat(4);

        fetch('/m/api/search.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(renderResults)
            .catch(() => {
                box.innerHTML = '<div style="padding:24px 14px;color:var(--txt3);font-size:14px">Ошибка поиска</div>';
            });
    }

    function renderResults(data) {
        var box = document.getElementById('search-results');
        if (!data.games.length && !data.studios.length) {
            box.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="ti ti-mood-empty" style="font-size:44px"></i></div><div class="empty-title">Ничего не найдено</div></div>';
            return;
        }

        var html = '';

        if (data.studios.length) {
            html += '<div class="sec-label">Студии</div>';
            data.studios.forEach(function(s) {
                html += '<a href="/m/developer/' + s.id + '" class="list-row">' +
                    '<div class="list-cover" style="border-radius:8px;overflow:hidden">' +
                    (s.avatar ? '<img src="' + esc(s.avatar) + '" style="width:100%;height:100%;object-fit:cover" loading="lazy">' : '') +
                    '</div>' +
                    '<div class="list-body"><div class="list-title">' + esc(s.name) + '</div>' +
                    '<div class="list-meta">' + s.game_count + ' игр</div></div>' +
                    '<i class="ti ti-chevron-right" style="color:var(--txt3)"></i></a>';
            });
        }

        if (data.games.length) {
            html += '<div class="sec-label">Игры (' + data.games.length + ')</div>';
            data.games.forEach(function(g) {
                var free = g.price == 0;
                var price = free ?
                    '<span style="color:var(--ok);font-size:11px">Бесплатно</span>' :
                    '<span style="color:var(--primary);font-size:13px;font-weight:700">' + parseInt(g.price).toLocaleString('ru') + ' ₽</span>';
                html += '<a href="/m/game/' + g.id + '" class="list-row">' +
                    '<div class="list-cover">' +
                    (g.cover ? '<img src="' + esc(g.cover) + '" style="width:100%;height:100%;object-fit:cover" loading="lazy" onerror="this.style.display=\'none\'">' : '') +
                    '</div>' +
                    '<div class="list-body"><div class="list-title">' + esc(g.name) + '</div>' +
                    '<div class="list-meta">' + esc(g.studio) + '</div></div>' +
                    '<div class="list-right">' + price + '</div></a>';
            });
        }

        box.innerHTML = html;
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    <?php if ($q): ?>doSearch();
    <?php endif; ?>
</script>