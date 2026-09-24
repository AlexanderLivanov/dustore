<?php
/**
 * swad/static/elements/gpi_block.php — блок Game Platform Index на /stat.
 *
 * Ожидает в области видимости:
 *   $GPI       — результат GPI::snapshot()
 *   $GPI_ADMIN — bool, показывать ли денежные значения
 * Рендерится ВНУТРИ .dst-st: берёт токены, .frame/.pix и спарклайны оттуда.
 */

if (!function_exists('gpi_fmt')) {
    function gpi_fmt($v, string $fmt): string
    {
        if ($v === null) return '—';
        return match ($fmt) {
            'pct'   => number_format($v * 100, 1, ',', ' ') . '%',
            'dec'   => number_format($v, 1, ',', ' '),
            'rub'   => number_format($v, 0, ',', ' ') . ' ₽',
            default => number_format($v, 0, ',', ' '),
        };
    }

    /** Изменение к базе: для долей — в п.п., для счётчиков — в %. */
    function gpi_change(array $m): ?array
    {
        if ($m['value'] === null || $m['base'] === null) return null;
        if ($m['fmt'] === 'pct') {
            $d = ($m['value'] - $m['base']) * 100;
            return [$d, ($d > 0 ? '+' : '') . number_format($d, 1, ',', ' ') . ' п.п.'];
        }
        if ($m['base'] == 0) return $m['value'] > 0 ? [100, 'новое'] : [0, '0%'];
        $d = ($m['value'] / $m['base'] - 1) * 100;
        return [$d, ($d > 0 ? '+' : '') . number_format($d, 0, ',', ' ') . '%'];
    }

    function gpi_days(int $n): string
    {
        $a = $n % 10; $b = $n % 100;
        $w = ($a === 1 && $b !== 11) ? 'день' : (($a >= 2 && $a <= 4 && ($b < 12 || $b > 14)) ? 'дня' : 'дней');
        return "$n $w";
    }
}

$g       = $GPI['gpi'];
$verdict = $g === null ? ['collect', 'Индекс собирается']
         : ($g >= 1030 ? ['up', 'Ускорение'] : ($g <= 970 ? ['down', 'Замедление'] : ['flat', 'Обычный темп']));
$d1 = $GPI['delta_1d'];
$d7 = $GPI['delta_7d'];
$dcls = fn($d) => $d === null ? 'flat' : ($d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'));
?>
<style>
    .dst-st .gpi { margin-bottom: 46px; }
    .dst-st .gpi__top { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 12px; }
    @media (max-width: 900px) { .dst-st .gpi__top { grid-template-columns: 1fr; } }

    .dst-st .gpi__hero .frame__i { padding: 20px 22px 14px; display: flex; flex-direction: column; gap: 6px; }
    .dst-st .gpi__eyebrow { font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: .12em;
        text-transform: uppercase; color: var(--muted); display: flex; justify-content: space-between; gap: 10px; }
    .dst-st .gpi__row { display: flex; align-items: baseline; gap: 16px; flex-wrap: wrap; }
    .dst-st .gpi__value { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: clamp(46px, 7vw, 68px);
        letter-spacing: -.04em; line-height: 1; }
    .dst-st .gpi__value--none { font-size: 28px; color: var(--muted); letter-spacing: 0; }
    .dst-st .gpi__deltas { display: flex; flex-direction: column; gap: 2px; font-family: 'JetBrains Mono', monospace; font-size: 13px; }
    .dst-st .gpi__deltas span.muted { color: var(--muted); font-size: 11px; margin-left: 4px; }
    .dst-st .gpi__verdict { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;
        padding: 5px 10px; --s: 3px; background: rgba(255,255,255,.07); width: fit-content; }
    .dst-st .gpi__verdict--up { background: rgba(46,230,168,.14); color: var(--up); }
    .dst-st .gpi__verdict--down { background: rgba(255,95,122,.14); color: var(--down); }
    .dst-st .gpi__breadth { font-size: 13px; color: var(--muted); }
    .dst-st .gpi__breadth b { color: var(--fg); font-family: 'JetBrains Mono', monospace; }
    .dst-st .gpi__chart { position: relative; height: 150px; margin-top: 8px; }
    .dst-st .gpi__chart-empty { height: 150px; display: grid; place-items: center; color: var(--muted); font-size: 13px;
        border: 1px dashed var(--line); margin-top: 8px; text-align: center; padding: 0 20px; }

    .dst-st .gpi__pillars .frame__i { padding: 18px 20px; }
    .dst-st .gpi__pl { display: grid; grid-template-columns: 118px 1fr 64px; align-items: center; gap: 12px;
        padding: 9px 0; border-bottom: 1px solid var(--line); cursor: pointer; }
    .dst-st .gpi__pl:last-child { border-bottom: 0; }
    .dst-st .gpi__pl-name { font-weight: 600; font-size: 14px; }
    .dst-st .gpi__pl-name small { display: block; font-weight: 400; font-size: 11px; color: var(--muted);
        font-family: 'JetBrains Mono', monospace; }
    .dst-st .gpi__bar { position: relative; height: 10px; background: rgba(255,255,255,.05); }
    .dst-st .gpi__bar::before { content: ''; position: absolute; left: 50%; top: -3px; bottom: -3px; width: 1px; background: var(--muted); }
    .dst-st .gpi__bar i { position: absolute; top: 0; bottom: 0; }
    .dst-st .gpi__bar i.up { left: 50%; background: var(--up); }
    .dst-st .gpi__bar i.down { right: 50%; background: var(--down); }
    .dst-st .gpi__bar--none { background: repeating-linear-gradient(90deg, rgba(255,255,255,.06) 0 6px, transparent 6px 12px); }
    .dst-st .gpi__pl-val { font-family: 'JetBrains Mono', monospace; font-size: 14px; text-align: right; }
    .dst-st .gpi__pl-val--none { font-size: 11px; color: var(--muted); }

    .dst-st .gpi__groups { margin-top: 12px; display: grid; gap: 8px; }
    .dst-st .gpi__group { background: rgba(255,255,255,.035); box-shadow: inset 0 0 0 1px var(--line); }
    .dst-st .gpi__group[open] { background: rgba(255,255,255,.05); }
    .dst-st .gpi__list { padding-bottom: 4px; }
    .dst-st .gpi__group > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 12px;
        padding: 12px 16px; font-family: 'Syne', sans-serif; font-weight: 600; font-size: 15px; }
    .dst-st .gpi__group > summary::-webkit-details-marker { display: none; }
    .dst-st .gpi__group > summary::after { content: '+'; margin-left: auto; font-family: 'JetBrains Mono', monospace; color: var(--muted); }
    .dst-st .gpi__group[open] > summary::after { content: '−'; }
    .dst-st .gpi__group summary .gpi__about { font-family: 'Inter', sans-serif; font-weight: 400; font-size: 13px; color: var(--muted); }
    .dst-st .gpi__group summary .gpi__ix { font-family: 'JetBrains Mono', monospace; font-size: 13px; }

    .dst-st .gpi__m { display: grid; grid-template-columns: minmax(0, 1.6fr) 110px 96px 80px; gap: 14px; align-items: center;
        padding: 10px 16px; border-top: 1px solid var(--line); }
    @media (max-width: 640px) {
        .dst-st .gpi__m { grid-template-columns: minmax(0, 1fr) auto; }
        .dst-st .gpi__m .dst-st__spark, .dst-st .gpi__m-chg { display: none; }
    }
    .dst-st .gpi__m-name { font-size: 14px; }
    .dst-st .gpi__m-name small { display: block; font-size: 12px; color: var(--muted); line-height: 1.4; margin-top: 2px; }
    .dst-st .gpi__m-val { font-family: 'JetBrains Mono', monospace; font-size: 15px; font-weight: 700; text-align: right; }
    .dst-st .gpi__m-chg { font-family: 'JetBrains Mono', monospace; font-size: 12px; text-align: right; }
    .dst-st .gpi__m-chg.up { color: var(--up); } .dst-st .gpi__m-chg.down { color: var(--down); } .dst-st .gpi__m-chg.flat { color: var(--muted); }
    .dst-st .gpi__m--off .gpi__m-name { color: var(--muted); }
    .dst-st .gpi__plate { grid-column: 2 / -1; justify-self: end; font-family: 'JetBrains Mono', monospace; font-size: 11px;
        letter-spacing: .04em; padding: 5px 9px; --s: 3px; background: rgba(255,176,32,.12); color: #ffcf73; white-space: nowrap; }
    .dst-st .gpi__plate--none { background: rgba(255,255,255,.06); color: var(--muted); }
    .dst-st .gpi__tag { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--muted); border: 1px solid var(--line);
        padding: 1px 5px; margin-left: 6px; vertical-align: 1px; }

    .dst-st .gpi__how { margin-top: 12px; }
    .dst-st .gpi__how > summary { cursor: pointer; color: var(--muted); font-size: 13px; padding: 6px 0; }
    .dst-st .gpi__how-body { font-size: 14px; color: rgba(244,238,248,.8); max-width: 820px; line-height: 1.65; }
    .dst-st .gpi__how-body code { font-family: 'JetBrains Mono', monospace; font-size: 12.5px; background: rgba(255,255,255,.07); padding: 1px 5px; }
</style>

<section class="gpi" id="gpi">
    <h2 class="dst-st__section-title">Game Platform Index</h2>

    <div class="gpi__top">
        <!-- ── Главное число ─────────────────────────────────── -->
        <div class="gpi__hero frame pix">
            <div class="frame__i pix">
                <div class="gpi__eyebrow"><span>GPI · база 1000</span><span>на <?= date('d.m.Y', strtotime($GPI['asof'])) ?></span></div>
                <div class="gpi__row">
                    <?php if ($g === null): ?>
                        <div class="gpi__value gpi__value--none">Аналитика собирается</div>
                    <?php else: ?>
                        <div class="gpi__value"><?= number_format($g, 1, ',', ' ') ?></div>
                        <div class="gpi__deltas">
                            <span class="dst-st__delta dst-st__delta--<?= $dcls($d1) ?>"><?= $d1 === null ? '—' : (($d1 > 0 ? '▲ ' : ($d1 < 0 ? '▼ ' : '• ')) . number_format(abs($d1), 1, ',', ' ')) ?><span class="muted">за день</span></span>
                            <span class="dst-st__delta dst-st__delta--<?= $dcls($d7) ?>"><?= $d7 === null ? '—' : (($d7 > 0 ? '▲ ' : ($d7 < 0 ? '▼ ' : '• ')) . number_format(abs($d7), 1, ',', ' ')) ?><span class="muted">за неделю</span></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="gpi__row" style="gap:12px;align-items:center">
                    <span class="gpi__verdict gpi__verdict--<?= $verdict[0] ?> pix"><?= $verdict[1] ?></span>
                    <?php if ($GPI['breadth'] !== null): ?>
                        <span class="gpi__breadth">растут <b><?= round($GPI['breadth']) ?>%</b> метрик · в индексе <b><?= (int)$GPI['counted'] ?></b> из <?= (int)$GPI['total'] ?></span>
                    <?php endif; ?>
                </div>

                <?php if (count($GPI['history']) > 1): ?>
                    <div class="gpi__chart"><canvas id="gpiChart"></canvas></div>
                <?php else: ?>
                    <div class="gpi__chart-empty">История индекса появится, когда наберётся хотя бы два дня расчёта.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Столпы ────────────────────────────────────────── -->
        <div class="gpi__pillars frame pix">
            <div class="frame__i pix">
                <div class="gpi__eyebrow" style="margin-bottom:6px"><span>Столпы</span><span>вес</span></div>
                <?php foreach ($GPI['pillars'] as $pid => $p):
                    $ix = $p['index'];
                    $pos = $ix ? max(-1, min(1, log($ix / 1000) / log(2))) : 0;   // ×2 = край шкалы
                ?>
                    <div class="gpi__pl" data-open="gpi-<?= $pid ?>">
                        <div class="gpi__pl-name"><?= htmlspecialchars($p['label']) ?><small><?= round($p['weight'] * 100) ?>%</small></div>
                        <div class="gpi__bar <?= $ix ? '' : 'gpi__bar--none' ?>">
                            <?php if ($ix): ?><i class="<?= $pos >= 0 ? 'up' : 'down' ?>" style="width:<?= round(abs($pos) * 50, 1) ?>%"></i><?php endif; ?>
                        </div>
                        <div class="gpi__pl-val <?= $ix ? '' : 'gpi__pl-val--none' ?>"><?= $ix ? number_format($ix, 0, ',', ' ') : 'сбор' ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── Метрики по столпам ─────────────────────────────────── -->
    <div class="gpi__groups">
        <?php foreach ($GPI['pillars'] as $pid => $p): ?>
            <details class="gpi__group pix" id="gpi-<?= $pid ?>">
                <summary>
                    <?= htmlspecialchars($p['label']) ?>
                    <span class="gpi__about"><?= htmlspecialchars($p['about']) ?></span>
                    <span class="gpi__ix"><?= $p['index'] ? number_format($p['index'], 0, ',', ' ') : '' ?></span>
                </summary>
                <div class="gpi__list">
                    <?php foreach ($p['metrics'] as $mid):
                        $m = $GPI['metrics'][$mid];
                        $off = $m['status'] !== 'ok';
                        $hideMoney = $m['money'] && empty($GPI_ADMIN);
                        $chg = $off ? null : gpi_change($m);
                        $ccls = $chg === null ? 'flat' : ($chg[0] > 0.5 ? 'up' : ($chg[0] < -0.5 ? 'down' : 'flat'));
                    ?>
                        <div class="gpi__m <?= $off ? 'gpi__m--off' : '' ?>">
                            <div class="gpi__m-name">
                                <?= htmlspecialchars($m['label']) ?><?php if ($m['info']): ?><span class="gpi__tag">вне индекса</span><?php endif; ?>
                                <?php if ($m['hint']): ?><small><?= htmlspecialchars($m['hint']) ?></small><?php endif; ?>
                            </div>
                            <?php if ($m['status'] === 'nosource'): ?>
                                <div class="gpi__plate gpi__plate--none pix">нет источника в БД</div>
                            <?php elseif ($m['status'] === 'collecting' && $m['value'] === null): ?>
                                <div class="gpi__plate pix">аналитика собирается<?= $m['days_left'] ? ' · ещё ' . gpi_days((int)$m['days_left']) : '' ?></div>
                            <?php else: ?>
                                <div class="gpi__m-val"><?= $hideMoney ? '•••' : gpi_fmt($m['value'], $m['fmt']) ?></div>
                                <?php if ($m['status'] === 'collecting'): ?>
                                    <div class="gpi__m-chg flat" title="Не хватает прошлых недель для базы"><?= $m['days_left'] ? 'база через ' . gpi_days((int)$m['days_left']) : 'копим базу' ?></div>
                                <?php else: ?>
                                    <div class="gpi__m-chg <?= $ccls ?>" title="к среднему за 4 прошлые недели"><?= $chg ? $chg[1] : '—' ?></div>
                                <?php endif; ?>
                                <?php if (!$hideMoney && count($m['spark']) > 1): ?>
                                    <svg class="dst-st__spark" style="width:80px" viewBox="0 0 100 28" preserveAspectRatio="none"
                                        data-spark="<?= htmlspecialchars(json_encode($m['spark']), ENT_QUOTES) ?>"></svg>
                                <?php else: ?><span></span><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <details class="gpi__how">
        <summary>Как считается индекс</summary>
        <div class="gpi__how-body">
            <p>GPI показывает не размер платформы, а её <b>темп</b>. 1000 — платформа живёт как обычно, 1100 — на 10% живее,
               900 — на 10% медленнее. Похоже на индекс деловой активности PMI: там 50 — граница роста и спада, у нас 1000.</p>
            <p>Каждая метрика берётся за последние 7 дней и сравнивается со средним за 4 предыдущие недели:
               <code>(сейчас + k) / (база + k)</code>. Сглаживание <code>k</code> не даёт одному отзыву превратиться
               в «рост на бесконечность». Метрики внутри столпа и столпы между собой усредняются геометрически —
               так рост вдвое и падение вдвое честно гасят друг друга.</p>
            <p>Метрики без данных в индекс не входят, а веса остальных перенормируются. Показатели активности
               (DAU, retention, сессии) считаются только по живому трекингу<?= $GPI['activity_since'] ? ' — он идёт с ' . date('d.m.Y', strtotime($GPI['activity_since'])) : '' ?>.
               «Растут N% метрик» — ширина рынка: высокий GPI при узкой ширине значит, что тянет одна-две метрики.</p>
        </div>
    </details>
</section>

<script>
    (function () {
        var H = <?= json_encode($GPI['history']) ?>;
        document.querySelectorAll('.dst-st .gpi__pl').forEach(function (row) {
            row.addEventListener('click', function () {
                var d = document.getElementById(row.dataset.open);
                if (d) { d.open = true; d.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });
        var el = document.getElementById('gpiChart');
        if (!el || !window.Chart || H.length < 2) return;
        var pts = H.map(function (p) { return { x: p[0], y: p[1] }; });
        new Chart(el, {
            type: 'line',
            data: { datasets: [
                { data: pts, borderColor: '#c32178', borderWidth: 2, pointRadius: 0, tension: .25, fill: false },
                { data: [{ x: pts[0].x, y: 1000 }, { x: pts[pts.length - 1].x, y: 1000 }], borderColor: 'rgba(244,238,248,.28)',
                  borderWidth: 1, borderDash: [4, 4], pointRadius: 0 }
            ] },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: { legend: { display: false },
                    tooltip: { filter: function (i) { return i.datasetIndex === 0; },
                        backgroundColor: 'rgba(20,4,29,.95)', borderColor: 'rgba(255,255,255,.15)', borderWidth: 1,
                        callbacks: { label: function (c) { return ' GPI ' + c.parsed.y.toFixed(1).replace('.', ','); } } } },
                scales: {
                    x: { type: 'time', time: { unit: 'week', tooltipFormat: 'dd.MM.yyyy' }, grid: { display: false },
                         ticks: { color: 'rgba(244,238,248,.45)', maxTicksLimit: 6 } },
                    y: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(244,238,248,.45)', maxTicksLimit: 4 } }
                }
            }
        });
    })();
</script>
