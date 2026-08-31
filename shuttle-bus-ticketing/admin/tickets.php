<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

// Auto-add the missing status column to RDS if it doesn't exist yet
$conn->query("ALTER TABLE tickets ADD COLUMN status ENUM('pending', 'confirmed', 'done', 'cancelled') DEFAULT 'pending'");
$conn->query("UPDATE tickets SET status = 'pending' WHERE status IS NULL OR status = ''");

$result = $conn->query("
    SELECT t.id, r.route_name, t.travel_date, t.seat_quantity, t.total_price, 
           COALESCE(t.status, 'pending') AS status, u.name AS user_name, u.email AS user_email
    FROM tickets t
    JOIN routes r ON r.id = t.route_id
    JOIN users u ON u.id = t.user_id
    ORDER BY t.travel_date DESC
");

$tickets = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

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
<?php foreach ($tickets as $t): 
    $status = $t['status'];
    $bgColors = [
        'pending'   => '#fef3c7; color: #92400e;',
        'confirmed' => '#d1fae5; color: #065f46;',
        'done'      => '#e0f2fe; color: #075985;',
        'cancelled' => '#fee2e2; color: #991b1b;'
    ];
    $style = $bgColors[$status] ?? '#f3f4f6; color: #374151;';
?>
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
<select name="status" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 6px; font-weight: 600; border: 1px solid transparent; background-color: <?= $style ?>">
<option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
<option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
<option value="done" <?= $status === 'done' ? 'selected' : '' ?>>Done</option>
<option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
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