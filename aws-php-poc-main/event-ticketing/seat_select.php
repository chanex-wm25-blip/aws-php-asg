<?php
require 'config.php';
require 'auth.php';
require_login();

$event_id = (int)($_GET['event_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM events WHERE id = ?');
$stmt->bind_param('i', $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    die('Event not found.');
}

if (!$event['has_seating']) {
    header('Location: create.php?event_id=' . $event_id);
    exit;
}

$stmt = $conn->prepare('SELECT id, row_label, seat_number, is_booked FROM seats WHERE event_id = ? ORDER BY row_label, seat_number');
$stmt->bind_param('i', $event_id);
$stmt->execute();
$seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$rows = [];
foreach ($seats as $seat) {
    $rows[$seat['row_label']][] = $seat;
}

$pageTitle = 'Select Seats';
require 'partials/header.php';
?>
<div class="form-card" style="max-width:720px;">
<h1>Select Your Seats</h1>
<p><?= htmlspecialchars($event['event_name']) ?> &middot; <?= htmlspecialchars(date('d M Y', strtotime($event['event_date']))) ?> &middot; <?= htmlspecialchars($event['venue']) ?> &middot; RM<?= number_format($event['ticket_price'], 2) ?> / seat</p>

<div class="seat-legend">
<span class="seat-legend-item"><span class="seat-legend-swatch"></span> Available</span>
<span class="seat-legend-item"><span class="seat-legend-swatch selected"></span> Selected</span>
<span class="seat-legend-item"><span class="seat-legend-swatch booked"></span> Taken</span>
</div>

<form method="post" action="payment.php" id="seat-form">
<input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
<div class="seat-map">
<?php foreach ($rows as $rowLabel => $rowSeats): ?>
<div class="seat-row">
<span class="seat-row-label"><?= htmlspecialchars($rowLabel) ?></span>
<?php foreach ($rowSeats as $seat): ?>
<?php if ($seat['is_booked']): ?>
<span class="seat booked" title="Taken"><?= (int)$seat['seat_number'] ?></span>
<?php else: ?>
<label class="seat" data-seat-toggle>
<input type="checkbox" name="seat_ids[]" value="<?= (int)$seat['id'] ?>" style="display:none;">
<?= (int)$seat['seat_number'] ?>
</label>
<?php endif; ?>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>
<p>Selected: <span id="selected-count">0</span> seat(s) &middot; Total: RM<span id="selected-total">0.00</span></p>
<button type="submit" id="seat-submit" disabled>Continue to Payment</button>
</form>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<script>
(function () {
    var price = <?= (float)$event['ticket_price'] ?>;
    var seatLabels = document.querySelectorAll('[data-seat-toggle]');
    var countEl = document.getElementById('selected-count');
    var totalEl = document.getElementById('selected-total');
    var submitBtn = document.getElementById('seat-submit');

    function update() {
        var checked = document.querySelectorAll('[data-seat-toggle] input:checked').length;
        countEl.textContent = checked;
        totalEl.textContent = (checked * price).toFixed(2);
        submitBtn.disabled = checked < 1;
    }

    seatLabels.forEach(function (label) {
        var input = label.querySelector('input');
        label.addEventListener('click', function (e) {
            e.preventDefault();
            input.checked = !input.checked;
            label.classList.toggle('selected', input.checked);
            update();
        });
    });

    update();
})();
</script>
<?php require 'partials/footer.php'; ?>
