<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$result = $conn->query('SELECT * FROM routes ORDER BY departure_time');
if (!$result) {
    http_response_code(500);
    error_log('Routes query failed: ' . $conn->error);
    die('Failed to load routes. Check the server error log.');
}
$routes = $result->fetch_all(MYSQLI_ASSOC);

$totalRoutes = count($routes);
$totalSeats = array_sum(array_column($routes, 'total_seats'));

$pageTitle = 'All Routes';
require 'partials/header.php';
?>
<div class="page-header">
<h1>All Routes</h1>
<p>Every campus shuttle route, with departure time and seat capacity.</p>
</div>

<section>
<div class="card-grid">
<div class="card"><h3><?= (int)$totalRoutes ?></h3><p>Active routes</p></div>
<div class="card"><h3><?= (int)$totalSeats ?></h3><p>Combined seats per run</p></div>
</div>
</section>

<?php foreach ($routes as $r): ?>
<section>
<div class="card" style="max-width:720px;">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($r)) ?>" alt="<?= htmlspecialchars($r['route_name']) ?>" loading="lazy">
<h3><?= htmlspecialchars($r['route_name']) ?></h3>
<p>&#128205; <?= htmlspecialchars($r['origin']) ?> &rarr; <?= htmlspecialchars($r['destination']) ?></p>
<p>Departs <?= htmlspecialchars($r['departure_time']) ?> &middot; RM<?= number_format($r['price'], 2) ?> &middot; <?= (int)$r['total_seats'] ?> seats/bus</p>
<?php if (current_user_id()): ?>
<a class="btn" href="create.php?route_id=<?= (int)$r['id'] ?>">Book Ticket</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Book</a>
<?php endif; ?>
</div>
</section>
<?php endforeach; ?>
<?php require 'partials/footer.php'; ?>
