<?php
require '../config.php';
require '../auth.php';
require_admin();

$token = trim($_GET['token'] ?? '');
$justMarked = isset($_GET['msg']) && $_GET['msg'] === 'marked';
$ticket = null;
$notFound = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $postToken = trim($_POST['token'] ?? '');
    $stmt = $conn->prepare('UPDATE tickets SET checked_in_at = NOW() WHERE id = ? AND checked_in_at IS NULL');
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $stmt->close();
    header('Location: checkin.php?msg=marked&token=' . urlencode($postToken));
    exit;
}

if ($token !== '') {
    $stmt = $conn->prepare('
        SELECT t.id, t.qr_token, t.checked_in_at, o.id AS order_id,
               u.name AS user_name, u.email AS user_email,
               e.event_name, e.event_date, e.venue,
               s.row_label, s.seat_number
        FROM tickets t
        JOIN orders o ON o.id = t.order_id
        JOIN users u ON u.id = o.user_id
        JOIN events e ON e.id = o.event_id
        LEFT JOIN seats s ON s.id = t.seat_id
        WHERE t.qr_token = ?
    ');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $notFound = !$ticket;
}

$pageTitle = 'Ticket Check-In';
require 'partials/header.php';
?>
<h1>Ticket Check-In</h1>

<?php if ($justMarked): ?><p class="alert alert-success">Checked in successfully.</p><?php endif; ?>

<div class="form-card" style="max-width:480px;">
<form method="get" action="checkin.php">
<label>Ticket Code <input type="text" name="token" id="checkin-token" placeholder="Scan or paste the ticket's QR code" autocomplete="off" autofocus></label>
<button type="submit">Look Up</button>
</form>

<?php if ($notFound): ?>
<p class="alert alert-error">No ticket found for that code.</p>
<?php elseif ($ticket): ?>
<div class="order-summary" style="margin-top:20px;">
<div class="order-summary-row"><span>Attendee</span><span><?= htmlspecialchars($ticket['user_name']) ?></span></div>
<div class="order-summary-row"><span>Email</span><span><?= htmlspecialchars($ticket['user_email']) ?></span></div>
<div class="order-summary-row"><span>Event</span><span><?= htmlspecialchars($ticket['event_name']) ?></span></div>
<div class="order-summary-row"><span>Date / Venue</span><span><?= htmlspecialchars(date('d M Y', strtotime($ticket['event_date']))) ?> &middot; <?= htmlspecialchars($ticket['venue']) ?></span></div>
<?php if ($ticket['row_label']): ?>
<div class="order-summary-row"><span>Seat</span><span><?= htmlspecialchars($ticket['row_label'] . $ticket['seat_number']) ?></span></div>
<?php endif; ?>
</div>

<?php if ($ticket['checked_in_at']): ?>
<p class="alert" style="margin-top:16px;">Already checked in at <?= htmlspecialchars(date('d M Y, H:i', strtotime($ticket['checked_in_at']))) ?>.</p>
<?php else: ?>
<form method="post" action="checkin.php" style="margin-top:16px;">
<input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
<input type="hidden" name="token" value="<?= htmlspecialchars($ticket['qr_token']) ?>">
<button type="submit">Mark Present</button>
</form>
<?php endif; ?>
<?php endif; ?>
</div>
<script>
(function () {
    var input = document.getElementById('checkin-token');
    if (input) { input.select(); }
})();
</script>
<?php require 'partials/footer.php'; ?>
