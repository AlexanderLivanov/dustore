    </main>
    <script>
        // Встроенный режим: связь окна с оболочкой (тот же origin, поэтому parent доступен напрямую)
        (() => {
            const wm = window.parent !== window ? window.parent.dsWM : null;

            // Ссылки за пределы консоли и «Сменить студию» — на всю вкладку, а не внутрь окна
            document.addEventListener('click', e => {
                const a = e.target.closest('a[href]');
                if (!a || a.target) return;
                const u = new URL(a.href, location.href);
                if (u.origin !== location.origin || !u.pathname.startsWith('/devs') || /^\/devs\/(select|goto|regorg)/.test(u.pathname)) {
                    a.target = '_top';
                }
            }, true);

            if (!wm) return;
            wm.loaded(window, <?= json_encode(['app' => $active_nav, 'title' => html_entity_decode(strip_tags($page_title))], JSON_UNESCAPED_UNICODE) ?>);
            // Клик внутри окна — фокус окна; горячие клавиши — оболочке
            document.addEventListener('pointerdown', () => wm.focusFrame(window), true);
            document.addEventListener('keydown', e => wm.key(e), true);
        })();
    </script>
    <?php if (!empty($extra_js)) echo $extra_js; ?>
</body>

</html>
