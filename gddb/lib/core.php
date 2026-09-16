<?php
require_once __DIR__ . '/../config.php';

/* --- БД ------------------------------------------------------------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            setup_screen($ex->getMessage());
        }
    }
    return $pdo;
}

/** Без БД приложение бесполезно — объясняем, что сделать, вместо стектрейса. */
function setup_screen(string $why): void
{
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "БД недоступна: $why\n"); exit(1); }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><title>GDDB — нужна установка</title>'
       . '<style>body{background:#0C0C0E;color:#F0EDE8;font:15px/1.6 system-ui;padding:60px 32px;max-width:720px;margin:0 auto}'
       . 'h1{font-size:26px;margin-bottom:12px}code{background:#1C1C20;border:1px solid #262628;border-radius:5px;padding:2px 6px;font-size:13px}'
       . 'ol{margin:18px 0 18px 22px}li{margin-bottom:10px}.e{color:#E05252;font-family:monospace;font-size:12.5px;margin-top:24px}</style>'
       . '<h1>База ещё не создана</h1><p>Запусти в XAMPP MySQL и импортируй две схемы:</p><ol>'
       . '<li>phpMyAdmin → <b>Import</b> → <code>db/schema.sql</code></li>'
       . '<li>затем <code>db/seed.sql</code> — он засеет первые 10 тем</li>'
       . '<li>если пользователь MySQL не <code>root</code> без пароля — поправь <code>config.php</code></li>'
       . '</ol><p>Из консоли то же самое: <code>mysql -u root &lt; db/schema.sql &amp;&amp; mysql -u root &lt; db/seed.sql</code></p>'
       . '<div class="e">' . htmlspecialchars($why, ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* --- Слаги ---------------------------------------------------------- */

const TRANSLIT = [
    'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh',
    'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
    'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c',
    'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
];

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, TRANSLIT);
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    $s = trim((string)$s, '-');
    if ($s === '') $s = 'note';
    return mb_substr($s, 0, 110, 'UTF-8');
}

function unique_slug(string $base, ?int $exceptId = null): string
{
    $slug = slugify($base);
    $i = 1;
    while (true) {
        $try = $i === 1 ? $slug : "$slug-$i";
        $st = db()->prepare('SELECT id FROM articles WHERE slug = ? LIMIT 1');
        $st->execute([$try]);
        $row = $st->fetch();
        if (!$row || (int)$row['id'] === $exceptId) return $try;
        $i++;
    }
}

/* --- Markdown ------------------------------------------------------- */
/* Намеренно маленький парсер: заголовки, списки, цитаты, код, hr,
   **жирный**, *курсив*, `код`, [ссылки](url) и [[вики-ссылки]].
   Один рендерер на весь проект — и для просмотра, и для превью. */

function md_anchor(string $s): string
{
    return 'h-' . slugify($s);
}

function md_inline(string $s, array $linkmap = []): string
{
    $spans = [];
    $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$spans) {
        $i = count($spans);
        $spans[$i] = '<code class="md-ic">' . e($m[1]) . '</code>';
        return "\x02$i\x02";
    }, $s);

    $s = e($s);

    $s = preg_replace_callback('/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/u', function ($m) use ($linkmap) {
        $target = trim($m[1]);
        $label  = trim(($m[2] ?? '') !== '' ? $m[2] : $m[1]);
        $row    = $linkmap[mb_strtolower($target, 'UTF-8')] ?? null;
        if ($row) {
            $cls = $row['status'] === 'stub' ? 'wikilink is-stub' : 'wikilink';
            return '<a class="' . $cls . '" href="note.php?slug=' . urlencode($row['slug']) . '">' . $label . '</a>';
        }
        return '<span class="wikilink is-missing" title="заметки ещё нет">' . $label . '</span>';
    }, $s);

    $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', function ($m) {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        return '<a href="' . e($url) . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
    }, $s);

    $s = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/u', '<em>$1</em>', $s);

    foreach ($spans as $i => $html) {
        $s = str_replace("\x02$i\x02", $html, (string)$s);
    }
    return (string)$s;
}

function md_render(string $md, array $linkmap = []): string
{
    $md = str_replace("\r\n", "\n", $md);

    $code = [];
    $md = preg_replace_callback('/^```([A-Za-z0-9+#.-]*)[ \t]*\n(.*?)\n?^```[ \t]*$/ms',
        function ($m) use (&$code) {
            $i = count($code);
            $lang = $m[1] !== '' ? ' data-lang="' . e($m[1]) . '"' : '';
            $code[$i] = '<pre class="md-code"' . $lang . '><code>' . e($m[2]) . '</code></pre>';
            return "\x01$i\x01";
        }, $md);

    $out = [];
    $list = null;
    $items = [];
    $para = [];
    $quote = [];

    $closeList = function () use (&$out, &$list, &$items) {
        if ($list === null) return;
        $li = '';
        foreach ($items as $it) $li .= "<li>$it</li>";
        $out[] = "<$list class=\"md-list\">$li</$list>";
        $list = null;
        $items = [];
    };
    $closePara = function () use (&$out, &$para) {
        if (!$para) return;
        $out[] = '<p>' . implode('<br>', $para) . '</p>';
        $para = [];
    };
    $closeQuote = function () use (&$out, &$quote) {
        if (!$quote) return;
        $out[] = '<blockquote class="md-quote">' . implode('<br>', $quote) . '</blockquote>';
        $quote = [];
    };

    foreach (explode("\n", (string)$md) as $line) {
        $t = rtrim($line);
        $tt = trim($t);

        if (preg_match('/^\x01(\d+)\x01$/', $tt, $m)) {
            $closeList(); $closePara(); $closeQuote();
            $out[] = $code[(int)$m[1]];
            continue;
        }
        if ($tt === '') { $closeList(); $closePara(); $closeQuote(); continue; }
        if (preg_match('/^(#{1,4})\s+(.+)$/', $t, $m)) {
            $closeList(); $closePara(); $closeQuote();
            $lvl = min(max(strlen($m[1]), 2), 5);
            $out[] = "<h$lvl id=\"" . md_anchor($m[2]) . "\" class=\"md-h\">" . md_inline($m[2], $linkmap) . "</h$lvl>";
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,})$/', $tt)) {
            $closeList(); $closePara(); $closeQuote();
            $out[] = '<hr class="md-hr">';
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $t, $m)) {
            $closeList(); $closePara();
            $quote[] = md_inline($m[1], $linkmap);
            continue;
        }
        if (preg_match('/^[-*+]\s+(.+)$/', $t, $m)) {
            $closePara(); $closeQuote();
            if ($list !== 'ul') { $closeList(); $list = 'ul'; }
            $items[] = md_inline($m[1], $linkmap);
            continue;
        }
        if (preg_match('/^\d+[.)]\s+(.+)$/', $t, $m)) {
            $closePara(); $closeQuote();
            if ($list !== 'ol') { $closeList(); $list = 'ol'; }
            $items[] = md_inline($m[1], $linkmap);
            continue;
        }
        $closeList(); $closeQuote();
        $para[] = md_inline($t, $linkmap);
    }
    $closeList(); $closePara(); $closeQuote();

    return implode("\n", $out);
}

function md_outline(string $md): array
{
    $res = [];
    foreach (explode("\n", str_replace("\r\n", "\n", $md)) as $line) {
        if (preg_match('/^(#{1,3})\s+(.+)$/', rtrim($line), $m)) {
            $res[] = ['level' => strlen($m[1]), 'text' => trim($m[2]), 'anchor' => md_anchor($m[2])];
        }
    }
    return $res;
}

/* --- Вики-ссылки → граф --------------------------------------------- */

function wiki_targets(string $md): array
{
    preg_match_all('/\[\[([^\]|]+)(?:\|[^\]]+)?\]\]/u', $md, $m);
    $out = [];
    foreach ($m[1] as $t) {
        $t = trim($t);
        if ($t !== '') $out[mb_strtolower($t, 'UTF-8')] = $t;
    }
    return $out;
}

/** Карта «что написано в скобках» → заметка, для рендера. */
function wiki_linkmap(string $md): array
{
    $targets = wiki_targets($md);
    if (!$targets) return [];
    $in = implode(',', array_fill(0, count($targets), '?'));
    $vals = array_merge(array_keys($targets), array_map('slugify', array_values($targets)));
    $st = db()->prepare(
        "SELECT id, slug, title, status FROM articles
         WHERE LOWER(title) IN ($in) OR slug IN ($in)"
    );
    $st->execute($vals);
    $map = [];
    foreach ($st->fetchAll() as $r) {
        $map[mb_strtolower($r['title'], 'UTF-8')] = $r;
        $map[$r['slug']] = $r;
    }
    return $map;
}

/**
 * Пересобирает автоматические связи статьи по её тексту.
 * Отсутствующая цель становится stub-заметкой — это и есть бэклог обучения.
 * Ручные связи (auto = 0) не трогаются никогда.
 */
function sync_wiki_links(int $articleId, string $md): array
{
    $db = db();
    $targets = wiki_targets($md);
    $created = [];
    $ids = [];

    foreach ($targets as $target) {
        $st = $db->prepare('SELECT id FROM articles WHERE LOWER(title) = ? OR slug = ? LIMIT 1');
        $st->execute([mb_strtolower($target, 'UTF-8'), slugify($target)]);
        $row = $st->fetch();
        if ($row) {
            $id = (int)$row['id'];
        } else {
            $slug = unique_slug($target);
            $ins = $db->prepare(
                "INSERT INTO articles (slug, title, summary, body_md, status)
                 VALUES (?, ?, '', ?, 'stub')"
            );
            $ins->execute([$slug, $target, NOTE_TEMPLATE]);
            $id = (int)$db->lastInsertId();
            $created[] = ['id' => $id, 'slug' => $slug, 'title' => $target];
        }
        if ($id !== $articleId) $ids[$id] = true;
    }

    $ids = array_keys($ids);

    // Снести устаревшие авто-связи.
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("DELETE FROM article_links WHERE from_id = ? AND auto = 1 AND to_id NOT IN ($in)");
        $st->execute(array_merge([$articleId], $ids));
    } else {
        $db->prepare('DELETE FROM article_links WHERE from_id = ? AND auto = 1')->execute([$articleId]);
    }

    // Добавить новые — но только там, где ручной связи ещё нет.
    foreach ($ids as $id) {
        $st = $db->prepare('SELECT 1 FROM article_links WHERE from_id = ? AND to_id = ? LIMIT 1');
        $st->execute([$articleId, $id]);
        if ($st->fetch()) continue;
        $db->prepare("INSERT INTO article_links (from_id, to_id, type, auto) VALUES (?, ?, 'related', 1)")
           ->execute([$articleId, $id]);
    }

    return $created;
}

/* --- Выборки -------------------------------------------------------- */

function sections_tree(): array
{
    $rows = db()->query(
        'SELECT s.*, (
            SELECT COUNT(*) FROM articles a
            LEFT JOIN sections s2 ON s2.id = a.section_id
            WHERE a.section_id = s.id OR s2.parent_id = s.id
          ) AS cnt
          FROM sections s ORDER BY s.position, s.title'
    )->fetchAll();
    $by = [];
    foreach ($rows as $r) { $r['children'] = []; $by[$r['id']] = $r; }
    $tree = [];
    foreach ($by as $id => $r) {
        if ($r['parent_id'] && isset($by[$r['parent_id']])) continue;
        $tree[$id] = &$by[$id];
    }
    foreach ($by as $id => $r) {
        if ($r['parent_id'] && isset($by[$r['parent_id']])) {
            $by[$r['parent_id']]['children'][] = &$by[$id];
        }
    }
    return array_values($tree);
}

function article_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM articles WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function article_tags(int $id): array
{
    $st = db()->prepare(
        'SELECT t.* FROM tags t JOIN article_tags at ON at.tag_id = t.id
         WHERE at.article_id = ? ORDER BY t.slug'
    );
    $st->execute([$id]);
    return $st->fetchAll();
}

/** Исходящие связи: «для этой заметки нужно / она объясняет». */
function article_links_out(int $id): array
{
    $st = db()->prepare(
        'SELECT l.type, l.auto, a.id, a.slug, a.title, a.status
         FROM article_links l JOIN articles a ON a.id = l.to_id
         WHERE l.from_id = ? ORDER BY l.type, a.title'
    );
    $st->execute([$id]);
    return $st->fetchAll();
}

/** Обратные ссылки — бесплатный продукт графа. */
function article_links_in(int $id): array
{
    $st = db()->prepare(
        'SELECT l.type, l.auto, a.id, a.slug, a.title, a.status
         FROM article_links l JOIN articles a ON a.id = l.from_id
         WHERE l.to_id = ? ORDER BY l.type, a.title'
    );
    $st->execute([$id]);
    return $st->fetchAll();
}

function article_questions(int $id): array
{
    $st = db()->prepare('SELECT * FROM questions WHERE article_id = ? ORDER BY status, created_at');
    $st->execute([$id]);
    return $st->fetchAll();
}

const LINK_LABELS = [
    'prerequisite' => 'нужно знать заранее',
    'explains'     => 'объясняет',
    'used_by'      => 'используется в',
    'related'      => 'связано',
];

const STATUS_LABELS = ['stub' => 'заготовка', 'draft' => 'черновик', 'published' => 'готово'];

const CONFIDENCE_LABELS = [
    0 => 'не оценено',
    1 => 'пересказал',
    2 => 'отвечу на вопросы',
    3 => 'объясню с нуля',
];
