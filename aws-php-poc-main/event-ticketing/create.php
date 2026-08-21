<?php
require 'config.php';
require 'auth.php';
require_login();

$error = '';
$selectedEvent = (int)($_GET['event_id'] ?? 0);

if ($selectedEvent) {
    $stmt = $conn->prepare('SELECT has_seating FROM events WHERE id = ?');
    $stmt->bind_param('i', $selectedEvent);
    $stmt->execute();
    $selected = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($selected && $selected['has_seating']) {
        header('Location: seat_select.php?event_id=' . $selectedEvent);
        exit;
    }
}

$events = $conn->query('SELECT *, (total_tickets - tickets_sold) AS remaining FROM events WHERE has_seating = 0 ORDER BY event_date');

$pageTitle = 'Buy Tickets';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Buy Event Tickets</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" action="payment.php">
<label>Event
<select name="event_id" required>
<?php while ($e = $events->fetch_assoc()): ?>
<?php $soldOut = $e['remaining'] <= 0; ?>
<option value="<?= (int)$e['id'] ?>" <?= $e['id'] == $selectedEvent ? 'selected' : '' ?> <?= $soldOut ? 'disabled' : '' ?>><?= htmlspecialchars($e['event_name']) ?> - RM<?= number_format($e['ticket_price'], 2) ?> (<?= $soldOut ? 'Sold Out' : (int)$e['remaining'] . ' left' ?>)</option>
<?php endwhile; ?>
</select>
</label>
<label>Quantity <input type="number" name="quantity" min="1" value="1" required></label>
<button type="submit">Continue to Payment</button>
</form>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
