</main>
</div>
</div>
<script>
    function openSidebar() {
        document.getElementById('sidebar').classList.add('open');
        document.getElementById('sb-overlay').classList.add('open');
    }

    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sb-overlay').classList.remove('open');
    }
</script>
<?php if (!empty($extra_js)) echo $extra_js; ?>
</body>

</html>