</main>
<footer class="footer">
<p>&copy; <?= date('Y') ?> Student Society Event Ticketing &middot; Admin Panel</p>
</footer>
<script>
(function () {
    var btn = document.getElementById('theme-toggle');
    if (!btn) return;

    function currentTheme() {
        var attr = document.documentElement.getAttribute('data-theme');
        if (attr) return attr;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function updateIcon() {
        btn.innerHTML = currentTheme() === 'dark' ? '&#9728;' : '&#127769;';
    }

    btn.addEventListener('click', function () {
        var next = currentTheme() === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        updateIcon();
    });

    updateIcon();
})();
</script>
</body>
</html>
