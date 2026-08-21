<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$error = '';
$uploadDir = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name    = trim($_POST['event_name']);
    $event_date    = $_POST['event_date'];
    $venue         = trim($_POST['venue']);
    $ticket_price  = (float)$_POST['ticket_price'];
    $has_seating   = isset($_POST['has_seating']);
    $seat_rows     = (int)($_POST['seat_rows'] ?? 0);
    $seats_per_row = (int)($_POST['seats_per_row'] ?? 0);
    $total_tickets = $has_seating ? $seat_rows * $seats_per_row : (int)($_POST['total_tickets'] ?? 0);

    [$image_url, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'event');

    if ($event_name === '' || $event_date === '' || $venue === '') {
        $error = 'All fields are required.';
    } elseif ($has_seating && ($seat_rows < 1 || $seats_per_row < 1)) {
        $error = 'Rows and seats per row must each be at least 1.';
    } elseif (!$has_seating && $total_tickets < 1) {
        $error = 'Total tickets must be at least 1.';
    } elseif ($event_date < date('Y-m-d')) {
        $error = 'Event date cannot be in the past.';
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        $stmt = $conn->prepare('INSERT INTO events (event_name, event_date, venue, ticket_price, total_tickets, image_url, has_seating, seat_rows, seats_per_row) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssdisiii', $event_name, $event_date, $venue, $ticket_price, $total_tickets, $image_url, $has_seating, $seat_rows, $seats_per_row);
        $stmt->execute();
        $eventId = $stmt->insert_id;
        $stmt->close();

        if ($has_seating) {
            $stmt = $conn->prepare('INSERT INTO seats (event_id, row_label, seat_number) VALUES (?, ?, ?)');
            for ($r = 0; $r < $seat_rows; $r++) {
                $rowLabel = seat_row_label($r);
                for ($n = 1; $n <= $seats_per_row; $n++) {
                    $stmt->bind_param('isi', $eventId, $rowLabel, $n);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        header('Location: events.php');
        exit;
    }
}

$pageTitle = 'Add Event';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Add Event</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<label>Event Name <input type="text" name="event_name" required></label>
<label>Date <input type="date" name="event_date" min="<?= date('Y-m-d') ?>" required></label>
<label>Venue <input type="text" name="venue" required></label>
<label>Ticket Price (RM) <input type="number" name="ticket_price" step="0.01" min="0" required></label>

<label class="checkbox-label"><input type="checkbox" name="has_seating" id="has_seating"> Allow buyers to pick a specific seat</label>

<div id="general-fields">
<label>Total Tickets <input type="number" name="total_tickets" min="1" id="total_tickets_input"></label>
</div>

<div id="seating-fields" style="display:none;">
<div class="card-row">
<label>Rows <input type="number" name="seat_rows" id="seat_rows" min="1" max="26"></label>
<label>Seats per Row <input type="number" name="seats_per_row" id="seats_per_row" min="1"></label>
</div>
<p class="form-hint">Total capacity: <span id="seat-total">0</span> seats. Rows are lettered A, B, C... Seat map cannot be changed after the event is created.</p>
</div>

<label>Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button type="submit">Add Event</button>
</form>
<script>
(function () {
    var seatingCheckbox = document.getElementById('has_seating');
    var generalFields = document.getElementById('general-fields');
    var seatingFields = document.getElementById('seating-fields');
    var totalTicketsInput = document.getElementById('total_tickets_input');
    var rowsInput = document.getElementById('seat_rows');
    var perRowInput = document.getElementById('seats_per_row');
    var seatTotal = document.getElementById('seat-total');

    function update() {
        var seated = seatingCheckbox.checked;
        generalFields.style.display = seated ? 'none' : '';
        seatingFields.style.display = seated ? '' : 'none';
        totalTicketsInput.required = !seated;
        rowsInput.required = seated;
        perRowInput.required = seated;
        seatTotal.textContent = (parseInt(rowsInput.value, 10) || 0) * (parseInt(perRowInput.value, 10) || 0);
    }

    seatingCheckbox.addEventListener('change', update);
    rowsInput.addEventListener('input', update);
    perRowInput.addEventListener('input', update);
    update();
})();
</script>
<p><a class="btn btn-secondary btn-small" href="events.php">Back to events</a></p>
</div>
<?php require 'partials/footer.php'; ?>
