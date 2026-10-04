<?php

session_start();

$dataFile = __DIR__ . '/data/versions.json';
$adminUsername = 'TheCreator';

if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$versions = json_decode(file_get_contents($dataFile), true);

if (!is_array($versions)) {
    $versions = [];
}

$isAdmin = isset($_SESSION['USERDATA']['username'])
    && $_SESSION['USERDATA']['username'] === $adminUsername;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $version = trim($_POST['version'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($version !== '' && $title !== '' && $content !== '') {
            $version = ltrim($version, 'vV');

            $versions = array_values(array_filter(
                $versions,
                fn($item) => ($item['version'] ?? '') !== $version
            ));

            $versions[] = [
                'version' => $version,
                'title' => $title,
                'date' => $date !== '' ? $date : date('Y-m-d'),
                'content' => $content
            ];

            usort($versions, function ($a, $b) {
                return version_compare($b['version'], $a['version']);
            });

            file_put_contents(
                $dataFile,
                json_encode($versions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
        }

        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#' . rawurlencode($version));
        exit;
    }

    if ($action === 'delete') {
        $version = trim($_POST['version'] ?? '');

        $versions = array_values(array_filter(
            $versions,
            fn($item) => ($item['version'] ?? '') !== $version
        ));

        file_put_contents(
            $dataFile,
            json_encode($versions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}

$selectedVersion = $_GET['edit'] ?? null;
$editing = null;

if ($selectedVersion !== null && $isAdmin) {
    foreach ($versions as $item) {
        if (($item['version'] ?? '') === $selectedVersion) {
            $editing = $item;
            break;
        }
    }
}

$latest = $versions[0] ?? null;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$siteUrl = 'https://dustore.ru';
$currentUrl = $siteUrl . '/whatsnew';

if ($latest) {
    $ogImage = $siteUrl . '/whatsnew/og.php?v=' . rawurlencode($latest['version']);
    $ogTitle = 'DUSTORE — ' . $latest['title'];
} else {
    $ogImage = $siteUrl . '/whatsnew/og.php';
    $ogTitle = 'DUSTORE — What’s New';
}

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title><?= e($ogTitle) ?></title>
    <meta name="description" content="Обновления DUSTORE">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="Обновления и изменения DUSTORE">
    <meta property="og:url" content="<?= e($currentUrl) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($ogTitle) ?>">
    <meta name="twitter:description" content="Обновления и изменения DUSTORE">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <link rel="stylesheet" href="style.css">

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>
<body>

<header class="topbar">
    <a href="/" class="brand">DUSTORE</a>

    <div class="topbar-right">
        <span>WHAT'S NEW</span>

        <?php if ($isAdmin): ?>
            <a href="?edit=new" class="admin-link">+ update</a>
        <?php endif; ?>
    </div>
</header>

<main class="layout">

    <aside class="sidebar">
        <div class="sidebar-title">Versions</div>

        <?php if (!$versions): ?>
            <div class="empty-small">Пока ничего нет.</div>
        <?php endif; ?>

        <?php foreach ($versions as $item): ?>
            <a
                href="#<?= e($item['version']) ?>"
                class="version-link"
            >
                <strong><?= e($item['version']) ?></strong>
                <span><?= e($item['date']) ?></span>
            </a>
        <?php endforeach; ?>
    </aside>

    <section class="content">

        <?php if (!$versions): ?>

            <div class="empty">
                <div class="empty-code">404</div>
                <h2>Обновлений пока нет</h2>
                <p>Но это ненадолго.</p>
            </div>

        <?php else: ?>

            <?php foreach ($versions as $item): ?>

                <article
                    class="release"
                    id="<?= e($item['version']) ?>"
                >
                    <div class="release-meta">
                        <a href="#<?= e($item['version']) ?>" class="hash">#</a>
                        <span><?= e($item['date']) ?></span>

                        <?php if ($isAdmin): ?>
                            <a
                                href="?edit=<?= rawurlencode($item['version']) ?>"
                                class="edit-link"
                            >edit</a>
                        <?php endif; ?>
                    </div>

                    <h2>
                        <a href="#<?= e($item['version']) ?>">
                            <?= e($item['version']) ?>
                        </a>
                    </h2>

                    <h3><?= e($item['title']) ?></h3>

                    <div
                        class="markdown"
                        data-markdown="<?= e(base64_encode($item['content'])) ?>"
                    ></div>
                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

<?php if ($isAdmin && ($selectedVersion !== null)): ?>

<div class="editor-overlay">
    <div class="editor">

        <div class="editor-head">
            <div>
                <div class="eyebrow">ADMIN</div>
                <h2><?= $editing ? 'Edit update' : 'New update' ?></h2>
            </div>

            <a href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>" class="close">×</a>
        </div>

        <form method="post">

            <input type="hidden" name="action" value="save">

            <div class="fields">

                <label>
                    <span>Version</span>
                    <input
                        name="version"
                        placeholder="1.4.0"
                        value="<?= e($editing['version'] ?? '') ?>"
                        required
                    >
                </label>

                <label>
                    <span>Title</span>
                    <input
                        name="title"
                        placeholder="Новый профиль разработчика"
                        value="<?= e($editing['title'] ?? '') ?>"
                        required
                    >
                </label>

                <label>
                    <span>Date</span>
                    <input
                        type="date"
                        name="date"
                        value="<?= e($editing['date'] ?? date('Y-m-d')) ?>"
                    >
                </label>

            </div>

            <div class="editor-grid">

                <div>
                    <div class="editor-label">Markdown</div>

                    <textarea
                        name="content"
                        id="markdownInput"
                        spellcheck="false"
                        placeholder="# Что нового

- Добавили ...
- Исправили ...
- Ускорили ...

## Важное изменение

Теперь разработчики могут..."
                    ><?= e($editing['content'] ?? '') ?></textarea>
                </div>

                <div>
                    <div class="editor-label">Preview</div>
                    <div class="preview markdown" id="preview"></div>
                </div>

            </div>

            <div class="editor-actions">

                <?php if ($editing): ?>
                    <button
                        type="submit"
                        name="action"
                        value="save"
                        class="button"
                    >
                        Save changes
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="delete"
                        class="button danger"
                        formaction=""
                        onclick="return confirm('Удалить обновление?')"
                    >
                        Delete
                    </button>
                <?php else: ?>
                    <button type="submit" class="button">
                        Publish update
                    </button>
                <?php endif; ?>

            </div>

        </form>

    </div>
</div>

<?php endif; ?>

<script>
document.querySelectorAll('.markdown[data-markdown]').forEach(el => {
    const encoded = el.dataset.markdown;
    const markdown = decodeURIComponent(
        Array.prototype.map.call(
            atob(encoded),
            c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
        ).join('')
    );

    el.innerHTML = marked.parse(markdown);
});

const input = document.getElementById('markdownInput');
const preview = document.getElementById('preview');

if (input && preview) {
    const render = () => {
        preview.innerHTML = marked.parse(input.value);
    };

    input.addEventListener('input', render);
    render();
}
</script>

</body>
</html>