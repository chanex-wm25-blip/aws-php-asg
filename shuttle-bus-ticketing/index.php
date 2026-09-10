<?php
error_log(sprintf(
    'Index request started: method=%s path=%s remote=%s search_present=%s',
    $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    isset($_GET['q']) && trim((string)$_GET['q']) !== '' ? 'true' : 'false'
));

require 'config.php';
require 'auth.php';
require 'helpers.php';

error_log('Index database connection is ready');

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $conn->prepare('
        SELECT r.*, COALESCE(SUM(t.seat_quantity), 0) AS booked_seats 
        FROM routes r 
        LEFT JOIN tickets t ON r.id = t.route_id AND t.travel_date = CURDATE() 
        WHERE r.route_name LIKE ? 
        GROUP BY r.id 
        ORDER BY r.departure_time
    ');
    if (!$stmt) {
        error_log('Index route search prepare failed: ' . $conn->error);
        http_response_code(500);
        die('Unable to load routes. Check the server error log.');
    }
    $likeSearch = '%' . $search . '%';
    $stmt->bind_param('s', $likeSearch);
    if (!$stmt->execute()) {
        error_log('Index route search execute failed: ' . $stmt->error);
        http_response_code(500);
        die('Unable to load routes. Check the server error log.');
    }
    $routes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $routeResult = $conn->query('
        SELECT r.*, COALESCE(SUM(t.seat_quantity), 0) AS booked_seats 
        FROM routes r 
        LEFT JOIN tickets t ON r.id = t.route_id AND t.travel_date = CURDATE() 
        GROUP BY r.id 
        ORDER BY r.departure_time
    ');
    if (!$routeResult) {
        error_log('Index route query failed: ' . $conn->error);
        http_response_code(500);
        die('Unable to load routes. Check the server error log.');
    }
    $routes = $routeResult->fetch_all(MYSQLI_ASSOC);
}

error_log('Index routes loaded: count=' . count($routes));

$pageTitle = 'Campus Shuttle Bus Ticketing';
require 'partials/header.php';
?>
<section class="hero">
<h1>TARUMT Campus Shuttle Bus Ticketing</h1>
<p>Book your seat on a campus shuttle route ahead of time.</p>
</section>

<section>
<h2>Available Routes</h2>
<form method="get" class="filter-bar" id="route-filter-form">
<label>Search <input type="text" name="q" id="route-search" placeholder="Route name..." value="<?= htmlspecialchars($search) ?>" autocomplete="off"></label>
<button type="submit">Search</button>
<?php if ($search !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
</form>
<script>
(function () {
    var input = document.getElementById('route-search');
    var form = document.getElementById('route-filter-form');
    if (!input || !form) return;
    var timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            form.submit();
        }, 500);
    });
})();
</script>

<?php if (empty($routes)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128269;</div>
<p>No routes match your search.</p>
<a class="btn btn-small btn-secondary" href="index.php">Clear filters</a>
</div>
<?php else: ?>
<div class="card-grid">
<?php foreach ($routes as $r): 
    $total = (int)$r['total_seats'];
    $booked = (int)($r['booked_seats'] ?? 0);
    $available = max(0, $total - $booked);
    $percent = $total > 0 ? min(100, round(($booked / $total) * 100)) : 0;
?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($r)) ?>" alt="<?= htmlspecialchars($r['route_name']) ?>" loading="lazy">
<h3><?= htmlspecialchars($r['route_name']) ?></h3>
<p><?= htmlspecialchars($r['origin']) ?> &rarr; <?= htmlspecialchars($r['destination']) ?></p>
<p>Departs <?= htmlspecialchars($r['departure_time']) ?> &middot; RM<?= number_format($r['price'], 2) ?> &middot; <?= (int)$r['total_seats'] ?> seats/bus</p>

<div class="seat-progress-container">
    <div class="seat-progress-labels">
        <span>Available: <strong><?= $available ?></strong>/<?= $total ?></span>
        <span><?= $percent ?>% Booked</span>
    </div>
    <div class="progress-bar-bg">
        <div class="progress-bar-fill" style="width: <?= $percent ?>%;"></div>
    </div>
</div>

<?php if (current_user_id()): ?>
<a class="btn" href="create.php?route_id=<?= (int)$r['id'] ?>">Book Ticket</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Book</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<?php require 'partials/footer.php'; ?>