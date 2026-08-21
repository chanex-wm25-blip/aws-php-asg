<?php
require '../config.php';
require '../auth.php';
require_admin();

$orders = $conn->query('
    SELECT o.id, e.event_name, o.quantity, o.total_price, u.name AS user_name, u.email AS user_email,
           COUNT(t.id) AS checked_in
    FROM orders o
    JOIN events e ON e.id = o.event_id
    JOIN users u ON u.id = o.user_id
    LEFT JOIN tickets t ON t.order_id = o.id AND t.checked_in_at IS NOT NULL
    GROUP BY o.id, e.event_name, o.quantity, o.total_price, u.name, u.email
    ORDER BY o.created_at DESC
')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'All Orders';
require 'partials/header.php';
?>
<h1>All Orders</h1>
<?php if (empty($orders)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#127903;</div>
<p>No ticket orders yet.</p>
</div>
<?php else: ?>
<table>
<tr><th>Event</th><th>Qty</th><th>Total (RM)</th><th>Bought By</th><th>Email</th><th>Checked In</th><th>Actions</th></tr>
<?php foreach ($orders as $o): ?>
<tr>
<td><?= htmlspecialchars($o['event_name']) ?></td>
<td><?= (int)$o['quantity'] ?></td>
<td><?= number_format($o['total_price'], 2) ?></td>
<td><?= htmlspecialchars($o['user_name']) ?></td>
<td><?= htmlspecialchars($o['user_email']) ?></td>
<td><?php if ((int)$o['checked_in'] === (int)$o['quantity']): ?><span class="badge badge-good"><?= (int)$o['checked_in'] ?> / <?= (int)$o['quantity'] ?></span><?php else: ?><span class="badge badge-neutral"><?= (int)$o['checked_in'] ?> / <?= (int)$o['quantity'] ?></span><?php endif; ?></td>
<td>
<form action="order_cancel.php" method="post" style="display:inline" onsubmit="return confirm('Cancel this order?');">
<input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
<button type="submit" class="btn-small btn-danger">Cancel</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
