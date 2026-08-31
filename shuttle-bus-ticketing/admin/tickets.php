<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();


$result = $conn->query("
    SELECT t.id, r.route_name, t.travel_date, t.seat_quantity, t.total_price, 
           t.status, u.name AS user_name, u.email AS user_email
    FROM tickets t
    JOIN routes r ON r.id = t.route_id
    JOIN users u ON u.id = t.user_id
    ORDER BY t.travel_date DESC
");

if (!$result) {
    http_response_code(500);
    error_log('Tickets query failed: ' . $conn->error);
    die('Failed to load tickets. Check the server error log.');
}

$tickets = $result->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'All Tickets';
require 'partials/header.php';
?>
<h1>All Tickets</h1>
<?php if (empty($tickets)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128196;</div>
<p>No tickets booked yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Route</th><th>Travel Date</th><th>Seats</th><th>Total (RM)</th><th>Booked By</th><th>Email</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($tickets as $t): ?>
<tr>
<td><?= htmlspecialchars($t['route_name']) ?></td>
<td><?= htmlspecialchars($t['travel_date']) ?></td>
<td><?= (int)$t['seat_quantity'] ?></td>
<td><?= number_format($t['total_price'], 2) ?></td>
<td><?= htmlspecialchars($t['user_name']) ?></td>
<td><?= htmlspecialchars($t['user_email']) ?></td>
<td>
<form action="ticket_status.php" method="post" style="display:inline;">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
<input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
<select name="status" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 4px;">
<option value="pending" <?= ($t['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
<option value="confirmed" <?= ($t['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
<option value="done" <?= ($t['status'] ?? '') === 'done' ? 'selected' : '' ?>>Done</option>
<option value="cancelled" <?= ($t['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
</select>
</form>
</td>
<td>
<form action="ticket_cancel.php" method="post" style="display:inline" onsubmit="return confirm('Cancel this ticket?');">
<input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
<button type="submit" class="btn-small btn-danger">Cancel</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>