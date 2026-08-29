<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$uid = current_user_id();
$error = '';

$stmt = $conn->prepare('SELECT t.*, r.price, r.departure_time, r.route_name FROM tickets t JOIN routes r ON r.id = t.route_id WHERE t.id = ? AND t.user_id = ?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die('Ticket not found or you do not have permission to edit it.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $travel_date   = $_POST['travel_date'] ?? '';
    $seat_quantity = (int)($_POST['seat_quantity'] ?? 0);

    // 2. Validate basic input and single-transaction limit of 3 seats
    if ($travel_date === '' || $seat_quantity < 1 || $seat_quantity > 3) {
        $error = 'Please choose a travel date and between 1 to 3 seats.';
    } elseif ($travel_date < date('Y-m-d')) {
        $error = 'Travel date cannot be in the past.';
    } elseif (is_departure_in_past($travel_date, $ticket['departure_time'])) {
        $error = 'This route has already departed today. Please choose a later date.';
    } else {
        $conn->begin_transaction();

        // 3. Enforce per-user limit (Total 3 seats max across account for this date, excluding this ticket)
        $stmtLimit = $conn->prepare('SELECT COALESCE(SUM(seat_quantity), 0) AS total_other_seats FROM tickets WHERE user_id = ? AND travel_date = ? AND id != ? FOR UPDATE');
        $stmtLimit->bind_param('isi', $uid, $travel_date, $id);
        $stmtLimit->execute();
        $otherSeats = (int)$stmtLimit->get_result()->fetch_assoc()['total_other_seats'];
        $stmtLimit->close();

        if ($otherSeats + $seat_quantity > 3) {
            $remaining = 3 - $otherSeats;
            $error = $remaining > 0 
                ? "You can only book $remaining more seat(s) for this date (Account Limit: 3 seats total per date)."
                : "You have reached your maximum limit of 3 booked seats for this date.";
            $conn->rollback();
        } else {
            $route_id = (int)$ticket['route_id'];

            $stmt = $conn->prepare('SELECT total_seats FROM routes WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $route_id);
            $stmt->execute();
            $route = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare('SELECT COALESCE(SUM(seat_quantity), 0) AS booked FROM tickets WHERE route_id = ? AND travel_date = ? AND id != ?');
            $stmt->bind_param('isi', $route_id, $travel_date, $id);
            $stmt->execute();
            $booked = (int)$stmt->get_result()->fetch_assoc()['booked'];
            $stmt->close();

            if ($booked + $seat_quantity > $route['total_seats']) {
                $available = $route['total_seats'] - $booked;
                $error = $available > 0
                    ? "Only $available seat(s) remaining on this route for that date."
                    : 'This route is fully booked for that date.';
                $conn->rollback();
            } else {
                $total_price = $ticket['price'] * $seat_quantity;

                $stmt = $conn->prepare('UPDATE tickets SET travel_date=?, seat_quantity=?, total_price=? WHERE id=? AND user_id=?');
                $stmt->bind_param('sidii', $travel_date, $seat_quantity, $total_price, $id, $uid);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                header('Location: index.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Edit Ticket';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Ticket</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<!-- CSRF Hidden Field -->
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
<input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
<label>Route <input type="text" value="<?= htmlspecialchars($ticket['route_name']) ?> (departs <?= htmlspecialchars($ticket['departure_time']) ?>)" disabled></label>
<label>Travel Date <input type="date" name="travel_date" id="travel-date" value="<?= htmlspecialchars($ticket['travel_date']) ?>" min="<?= date('Y-m-d') ?>" required></label>
<p class="form-hint" id="route-availability-hint"></p>
<!-- Added max="3" constraint -->
<label>Number of Seats <input type="number" name="seat_quantity" min="1" max="3" value="<?= (int)$ticket['seat_quantity'] ?>" required></label>
<button type="submit" id="submit-btn">Update Ticket</button>
</form>
<script>
(function () {
    var dateInput = document.getElementById('travel-date');
    var hint = document.getElementById('route-availability-hint');
    var submitBtn = document.getElementById('submit-btn');
    var routeId = <?= (int)$ticket['route_id'] ?>;
    var excludeTicketId = <?= (int)$ticket['id'] ?>;
    var today = '<?= date('Y-m-d') ?>';
    var nowMinutes = <?= (int)date('H') * 60 + (int)date('i') ?>;
    var departureMinutes = <?= (function () use ($ticket) {
        $parts = explode(':', $ticket['departure_time']);
        return (int)$parts[0] * 60 + (int)($parts[1] ?? 0);
    })() ?>;

    function refresh() {
        var date = dateInput.value;
        var departed = date === today && departureMinutes < nowMinutes;

        if (departed) {
            hint.textContent = 'This route has already departed today - choose a later date.';
            submitBtn.disabled = true;
            return;
        }

        submitBtn.disabled = false;
        hint.textContent = '';

        if (!date) { return; }

        fetch('route_availability.php?travel_date=' + encodeURIComponent(date) + '&exclude_ticket_id=' + excludeTicketId)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var fullRouteIds = (data.full_route_ids || []).map(String);
                var full = fullRouteIds.indexOf(String(routeId)) !== -1;
                hint.textContent = full ? 'This route is fully booked for that date - choose a different date.' : '';
                submitBtn.disabled = full;
            })
            .catch(function () { /* availability check is a convenience, not required to save */ });
    }

    dateInput.addEventListener('change', refresh);
    refresh();
})();
</script>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>