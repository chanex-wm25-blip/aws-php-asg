<?php
require 'config.php';
require 'auth.php';

$events = $conn->query('SELECT *, (total_tickets - tickets_sold) AS remaining FROM events ORDER BY event_date')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Event Schedule';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Event Schedule</h1>
<p>Full timetable of upcoming society events, earliest first.</p>
</div>

<?php if (empty($events)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128197;</div>
<p>No events scheduled yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Date</th><th>Event</th><th>Venue</th><th>Price (RM)</th><th>Seats Remaining</th><th>Actions</th></tr>
<?php foreach ($events as $e): ?>
<tr>
<td><?= htmlspecialchars(date('D, d M Y', strtotime($e['event_date']))) ?></td>
<td><?= htmlspecialchars($e['event_name']) ?></td>
<td><?= htmlspecialchars($e['venue']) ?></td>
<td><?= number_format($e['ticket_price'], 2) ?></td>
<td><?= (int)$e['remaining'] ?> / <?= (int)$e['total_tickets'] ?></td>
<td>
<?php if (current_user_id()): ?>
<a class="btn btn-small" href="create.php?event_id=<?= (int)$e['id'] ?>">Buy Tickets</a>
<?php else: ?>
<a class="btn btn-small btn-secondary" href="login.php">Login to Buy</a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
