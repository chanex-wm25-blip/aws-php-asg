<?php
$loggedIn = current_user_id() !== null;
$currentPage = basename($_SERVER['PHP_SELF']);
function nav_active($page, $current) {
    return $page === $current ? ' active' : '';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<script>
(function () {
    var saved = localStorage.getItem('theme');
    if (saved === 'dark' || saved === 'light') {
        document.documentElement.setAttribute('data-theme', saved);
    }
})();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Book a seat on a campus shuttle bus route online.') ?>">
<title><?= htmlspecialchars($pageTitle ?? 'Campus Shuttle Bus Ticketing') ?></title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/../style.css') ?>">
</head>
<body>
<nav class="navbar">
<a class="brand" href="index.php"><img src="assets/tarumt-logo.png" alt="TAR UMT" class="brand-logo">Campus Shuttle Bus Ticketing</a>
<div class="nav-links">
<a href="index.php" class="<?= trim(nav_active('index.php', $currentPage)) ?>">Home</a>
<a href="routes.php" class="<?= trim(nav_active('routes.php', $currentPage)) ?>">Routes</a>
<a href="schedule.php" class="<?= trim(nav_active('schedule.php', $currentPage)) ?>">Schedule</a>
<a href="testimonials.php" class="<?= trim(nav_active('testimonials.php', $currentPage)) ?>">Testimonials</a>
<a href="about.php" class="<?= trim(nav_active('about.php', $currentPage)) ?>">About</a>
<a href="contact.php" class="<?= trim(nav_active('contact.php', $currentPage)) ?>">Contact</a>
<?php if ($loggedIn): ?>
<div class="user-menu">
<button type="button" class="nav-user user-menu-trigger" aria-haspopup="true" aria-expanded="false">
<span class="user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr(current_user_name(), 0, 1))) ?></span> Hi, <?= htmlspecialchars(current_user_name()) ?>
</button>
<div class="user-menu-dropdown">
<a href="account.php">My Account</a>
<a href="logout.php">Logout</a>
</div>
</div>
<?php else: ?>
<a href="login.php">Login</a>
<a href="register.php">Register</a>
<?php endif; ?>
<button id="theme-toggle" class="theme-toggle" type="button" aria-label="Toggle dark mode">&#9728;</button>
</div>
</nav>
<main class="container">
