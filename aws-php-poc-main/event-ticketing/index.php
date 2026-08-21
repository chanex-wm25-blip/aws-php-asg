<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $conn->prepare('SELECT *, (total_tickets - tickets_sold) AS remaining FROM events WHERE event_name LIKE ? ORDER BY event_date');
    $likeSearch = '%' . $search . '%';
    $stmt->bind_param('s', $likeSearch);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $events = $conn->query('SELECT *, (total_tickets - tickets_sold) AS remaining FROM events ORDER BY event_date')->fetch_all(MYSQLI_ASSOC);
}

$myOrders = [];
if ($uid = current_user_id()) {
    $stmt = $conn->prepare('
        SELECT o.id, e.event_name, e.has_seating, o.quantity, o.total_price
        FROM orders o
        JOIN events e ON e.id = o.event_id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $myOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'Society Event Ticketing';
require 'partials/header.php';
?>
<section class="hero">
<h1>Student Society Event Ticketing</h1>
<p>Grab your tickets for upcoming campus events before they sell out.</p>
</section>

<section>
<h2>Upcoming Events</h2>
<form method="get" class="filter-bar" id="event-filter-form">
<label>Search <input type="text" name="q" id="event-search" placeholder="Event name..." value="<?= htmlspecialchars($search) ?>" autocomplete="off"></label>
<button type="submit">Search</button>
<?php if ($search !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
</form>
<script>
(function () {
    var input = document.getElementById('event-search');
    var form = document.getElementById('event-filter-form');
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

<?php if (empty($events)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128269;</div>
<p>No events match your search.</p>
<a class="btn btn-small btn-secondary" href="index.php">Clear filters</a>
</div>
<?php else: ?>
<div class="card-grid">
<?php foreach ($events as $e): ?>
<div class="card">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($e)) ?>" alt="<?= htmlspecialchars($e['event_name']) ?>" loading="lazy">
<h3><?= htmlspecialchars($e['event_name']) ?></h3>
<p><?= htmlspecialchars($e['event_date']) ?> &middot; <?= htmlspecialchars($e['venue']) ?></p>
<p>RM<?= number_format($e['ticket_price'], 2) ?> &middot; <?= (int)$e['remaining'] ?> / <?= (int)$e['total_tickets'] ?> left</p>
<?php if ($e['remaining'] <= 0): ?>
<button class="btn" disabled>Sold Out</button>
<?php elseif (current_user_id()): ?>
<?php if ($e['has_seating']): ?>
<a class="btn" href="seat_select.php?event_id=<?= (int)$e['id'] ?>">Select Seats</a>
<?php else: ?>
<a class="btn" href="create.php?event_id=<?= (int)$e['id'] ?>">Buy Tickets</a>
<?php endif; ?>
<?php else: ?>
<a class="btn" href="login.php">Login to Buy</a>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<section>
<h2>My Orders</h2>
<?php if (!current_user_id()): ?>
<p><a href="login.php">Login</a> or <a href="register.php">register</a> to view and manage your ticket orders.</p>
<?php elseif (empty($myOrders)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#127903;</div>
<p>You haven't bought any tickets yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Event</th><th>Qty</th><th>Total (RM)</th><th>Actions</th></tr>
<?php foreach ($myOrders as $o): ?>
<tr>
<td><?= htmlspecialchars($o['event_name']) ?></td>
<td><?= (int)$o['quantity'] ?></td>
<td><?= number_format($o['total_price'], 2) ?></td>
<td>
<a class="btn btn-secondary btn-small" href="confirmation.php?id=<?= (int)$o['id'] ?>">View Tickets</a>
<?php if (!$o['has_seating']): ?>
<a class="btn btn-secondary btn-small" href="edit.php?id=<?= (int)$o['id'] ?>">Edit</a>
<?php endif; ?>
<form action="delete.php" method="post" style="display:inline" onsubmit="return confirm('Cancel this order?');">
<input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
<button type="submit" class="btn-small btn-danger">Cancel</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</section>
<?php require 'partials/footer.php'; ?>
