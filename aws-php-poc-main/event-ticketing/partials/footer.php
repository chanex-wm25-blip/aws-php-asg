</main>
<footer class="site-footer">
<div class="footer-grid">
<div class="footer-brand">
<div class="footer-brand-name">Student Society Event Ticketing</div>
<p>Buy tickets for campus society events &mdash; cultural nights, band battles, charity dinners and more.</p>
</div>
<div class="footer-links">
<h4>Explore</h4>
<a href="index.php">Home</a>
<a href="events.php">Events</a>
<a href="schedule.php">Schedule</a>
<a href="testimonials.php">Testimonials</a>
</div>
<div class="footer-links">
<h4>Company</h4>
<a href="about.php">About Us</a>
<a href="contact.php">Contact Us</a>
</div>
<div class="footer-links">
<h4>Contact</h4>
<p>Student Societies Office<br>Student Centre, Level 1</p>
<p>+60 3-4145 0450</p>
</div>
</div>
<div class="footer-bottom">
<p>&copy; <?= date('Y') ?> Student Society Event Ticketing &middot; AMIT3253 Sample Project</p>
</div>
</footer>
<div id="lightbox" class="lightbox-overlay">
<button type="button" class="lightbox-close" aria-label="Close">&times;</button>
<img id="lightbox-img" src="" alt="">
</div>
<script>
(function () {
    var lightbox = document.getElementById('lightbox');
    var lightboxImg = document.getElementById('lightbox-img');
    if (lightbox && lightboxImg) {
        document.addEventListener('click', function (e) {
            var img = e.target.closest('.card-thumb, .table-thumb');
            if (img) {
                lightboxImg.src = img.src;
                lightboxImg.alt = img.alt;
                lightbox.classList.add('open');
            }
        });
        lightbox.addEventListener('click', function () {
            lightbox.classList.remove('open');
            lightboxImg.src = '';
        });
    }
})();
</script>
<script>
(function () {
    var trigger = document.querySelector('.user-menu-trigger');
    var dropdown = document.querySelector('.user-menu-dropdown');
    if (trigger && dropdown) {
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = dropdown.classList.toggle('open');
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function () {
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        });
    }
})();
</script>
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
<script>
(function () {
    var EYE_OPEN = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_CLOSED = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.06 21.06 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.13 21.13 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    var toggles = document.querySelectorAll('.password-toggle');
    toggles.forEach(function (toggle) {
        var input = toggle.previousElementSibling;
        if (!input) return;
        toggle.innerHTML = EYE_OPEN;
        toggle.addEventListener('click', function () {
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.innerHTML = showing ? EYE_OPEN : EYE_CLOSED;
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });
})();
</script>
</body>
</html>
