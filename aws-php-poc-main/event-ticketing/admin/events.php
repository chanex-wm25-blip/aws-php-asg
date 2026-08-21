<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$events = $conn->query('SELECT *, (total_tickets - tickets_sold) AS remaining FROM events ORDER BY event_date');

$pageTitle = 'Manage Events';
require 'partials/header.php';
?>
<h1>Events</h1>
<p><a class="btn btn-small" href="event_create.php">+ Add Event</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<table>
<tr><th>Photo</th><th>Name</th><th>Date</th><th>Venue</th><th>Price (RM)</th><th>Type</th><th>Sold / Total</th><th>Actions</th></tr>
<?php while ($e = $events->fetch_assoc()): ?>
<tr>
<td><img class="table-thumb" src="<?= htmlspecialchars(entity_image_url($e)) ?>" alt="<?= htmlspecialchars($e['event_name']) ?>" loading="lazy"></td>
<td><?= htmlspecialchars($e['event_name']) ?></td>
<td><?= htmlspecialchars($e['event_date']) ?></td>
<td><?= htmlspecialchars($e['venue']) ?></td>
<td><?= number_format($e['ticket_price'], 2) ?></td>
<td><?php if ($e['has_seating']): ?><span class="badge badge-neutral">Seated (<?= (int)$e['seat_rows'] ?>&times;<?= (int)$e['seats_per_row'] ?>)</span><?php else: ?><span class="badge badge-neutral">General</span><?php endif; ?></td>
<td><?= (int)$e['tickets_sold'] ?> / <?= (int)$e['total_tickets'] ?></td>
<td>
<a class="btn btn-secondary btn-small" href="event_edit.php?id=<?= (int)$e['id'] ?>">Edit</a>
<form action="event_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this event? Any existing orders for it must be removed first.');">
<input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php require 'partials/footer.php'; ?>
