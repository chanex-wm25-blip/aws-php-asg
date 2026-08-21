<?php
require 'config.php';
require 'auth.php';
require_login();

$id  = (int)($_GET['id'] ?? 0);
$uid = current_user_id();

$stmt = $conn->prepare('
    SELECT o.id, o.quantity, o.total_price, o.created_at, e.event_name, e.event_date, e.venue
    FROM orders o
    JOIN events e ON e.id = o.event_id
    WHERE o.id = ? AND o.user_id = ?
');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die('Order not found.');
}

$reference = 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);

$stmt = $conn->prepare('
    SELECT t.id, t.qr_token, t.checked_in_at, s.row_label, s.seat_number
    FROM tickets t
    LEFT JOIN seats s ON s.id = t.seat_id
    WHERE t.order_id = ?
    ORDER BY t.id
');
$stmt->bind_param('i', $id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Payment Successful';
require 'partials/header.php';
?>
<div class="form-card confirmation-card">
<div class="confirmation-icon">&#10003;</div>
<h1>Payment Successful</h1>
<p style="color:var(--text-muted);">Reference <strong><?= htmlspecialchars($reference) ?></strong></p>

<table class="confirmation-table">
<tr><th>Event</th><td><?= htmlspecialchars($order['event_name']) ?></td></tr>
<tr><th>Date</th><td><?= htmlspecialchars(date('d M Y', strtotime($order['event_date']))) ?></td></tr>
<tr><th>Venue</th><td><?= htmlspecialchars($order['venue']) ?></td></tr>
<tr><th>Quantity</th><td><?= (int)$order['quantity'] ?></td></tr>
<tr><th>Total Paid</th><td>RM<?= number_format($order['total_price'], 2) ?></td></tr>
</table>

<div class="card-actions confirmation-actions">
<a class="btn" href="index.php">View My Orders</a>
<a class="btn btn-secondary" href="events.php">Browse More Events</a>
</div>
</div>

<div class="form-card" style="max-width:460px; margin-top:20px;">
<h2>Your Tickets</h2>
<p style="color:var(--text-muted); font-size:0.9rem;">Show a ticket's QR code (or its reference code) at the entrance to check in.</p>
<?php foreach ($tickets as $i => $t): ?>
<div class="ticket-card">
<div class="qr-code" data-token="<?= htmlspecialchars($t['qr_token']) ?>"></div>
<div class="ticket-card-info">
<strong>Ticket <?= $i + 1 ?> of <?= count($tickets) ?></strong>
<?php if ($t['row_label']): ?><p>Seat <?= htmlspecialchars($t['row_label'] . $t['seat_number']) ?></p><?php endif; ?>
<p class="ticket-token"><?= htmlspecialchars($t['qr_token']) ?></p>
</div>
</div>
<?php endforeach; ?>
</div>

<script src="assets/js/qrcode.js"></script>
<script>
(function () {
    document.querySelectorAll('.qr-code[data-token]').forEach(function (el) {
        var qr = qrcode(0, 'M');
        qr.addData(el.dataset.token);
        qr.make();
        el.innerHTML = qr.createSvgTag(4, 4);
    });
})();
</script>
<?php require 'partials/footer.php'; ?>
