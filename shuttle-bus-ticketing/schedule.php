<?php
require 'config.php';
require 'auth.php';

$result = $conn->query('SELECT * FROM routes ORDER BY departure_time');
if (!$result) {
    http_response_code(500);
    error_log('Routes query failed: ' . $conn->error);
    die('Failed to load routes. Check the server error log.');
}
$routes = $result->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Shuttle Timetable';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Shuttle Timetable</h1>
<p>Daily departure schedule for every campus shuttle route.</p>
</div>

<?php if (empty($routes)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128652;</div>
<p>No routes scheduled yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Departs</th><th>Route</th><th>Origin</th><th>Destination</th><th>Price (RM)</th><th>Seats</th><th>Actions</th></tr>
<?php foreach ($routes as $r): ?>
<tr>
<td><?= htmlspecialchars($r['departure_time']) ?></td>
<td><?= htmlspecialchars($r['route_name']) ?></td>
<td><?= htmlspecialchars($r['origin']) ?></td>
<td><?= htmlspecialchars($r['destination']) ?></td>
<td><?= number_format($r['price'], 2) ?></td>
<td><?= (int)$r['total_seats'] ?></td>
<td>
<?php if (current_user_id()): ?>
<a class="btn btn-small" href="create.php?route_id=<?= (int)$r['id'] ?>">Book Ticket</a>
<?php else: ?>
<a class="btn btn-small btn-secondary" href="login.php">Login to Book</a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
