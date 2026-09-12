<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$uid = current_user_id();
$error = '';

$stmt = $conn->prepare('SELECT t.*, r.price, r.departure_time, r.route_name, r.total_seats, r.id AS route_id FROM tickets t JOIN routes r ON r.id = t.route_id WHERE t.id = ? AND t.user_id = ?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die('Ticket not found or you do not have permission to edit it.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $travel_date   = $_POST['travel_date'] ?? '';
    $seat_quantity = (int)($_POST['seat_quantity'] ?? 0);
    $seat_numbers  = trim($_POST['seat_numbers'] ?? '');

    if ($travel_date === '' || $seat_quantity < 1 || $seat_quantity > 3) {
        $error = 'Please choose a travel date and between 1 to 3 seats.';
    } elseif ($travel_date < date('Y-m-d')) {
        $error = 'Travel date cannot be in the past.';
    } elseif (is_departure_in_past($travel_date, $ticket['departure_time'])) {
        $error = 'This route has already departed today. Please choose a later date.';
    } else {
        $conn->begin_transaction();

        $stmtUserLock = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $stmtUserLock->bind_param('i', $uid);
        $stmtUserLock->execute();
        $stmtUserLock->close();

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

                $stmt = $conn->prepare('UPDATE tickets SET travel_date=?, seat_quantity=?, seat_numbers=?, total_price=? WHERE id=? AND user_id=?');
                $stmt->bind_param('sisdii', $travel_date, $seat_quantity, $seat_numbers, $total_price, $id, $uid);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                header('Location: user_tickets.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Edit Ticket';
require 'partials/header.php';
?>
<div class="card form-card" style="max-width: 600px; margin: 20px auto; padding: 24px; border-radius: 12px;">
<h1>Edit Ticket</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
<input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
<label>Route <input type="text" value="<?= htmlspecialchars($ticket['route_name']) ?> (departs <?= htmlspecialchars($ticket['departure_time']) ?>)" disabled></label>
<label>Travel Date <input type="date" name="travel_date" id="travel-date" value="<?= htmlspecialchars($ticket['travel_date']) ?>" min="<?= date('Y-m-d') ?>" required></label>
<p class="form-hint" id="route-availability-hint"></p>

<div style="display: flex; gap: 20px; align-items: center; margin: 20px 0 15px 0; font-size: 0.9rem;">
    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 16px; height: 16px; border: 1px solid var(--border, #d1d5db); border-radius: 4px; background: var(--bg-card, #fff); display:inline-block;"></span> Available</div>
    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 16px; height: 16px; background: #49afdb; border-radius: 4px; display:inline-block;"></span> Selected</div>
    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 16px; height: 16px; background: #e5e7eb; border-radius: 4px; display:inline-block;"></span> Taken</div>
</div>

<div id="seat-grid-container" style="display: grid; grid-template-columns: 45px 45px 25px 45px 45px; gap: 8px; justify-content: center; margin-bottom: 20px; background: rgba(0,0,0,0.03); padding: 20px; border-radius: 12px; border: 1px solid var(--border, #e5e7eb);"></div>

<input type="hidden" name="seat_quantity" id="seat-quantity" value="<?= (int)$ticket['seat_quantity'] ?>">
<input type="hidden" name="seat_numbers" id="seat-numbers" value="<?= htmlspecialchars($ticket['seat_numbers'] ?? '') ?>">

<div style="margin-bottom: 20px; font-size: 1.05rem; font-weight: 600; text-align: center;">
    Selected: <span id="selected-count-display"><?= (int)$ticket['seat_quantity'] ?></span> seat(s) &middot; Total: RM<span id="total-price-display"><?= number_format($ticket['price'] * $ticket['seat_quantity'], 2) ?></span>
</div>

<button type="submit" id="submit-btn" style="background: #49afdb; color: white; padding: 12px 24px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; width: 100%;">Update Ticket</button>
</form>
<script>
(function () {
    var dateInput = document.getElementById('travel-date');
    var hint = document.getElementById('route-availability-hint');
    var submitBtn = document.getElementById('submit-btn');
    var seatGrid = document.getElementById('seat-grid-container');
    var seatQuantityInput = document.getElementById('seat-quantity');
    var seatNumbersInput = document.getElementById('seat-numbers');
    var countDisplay = document.getElementById('selected-count-display');
    var priceDisplay = document.getElementById('total-price-display');

    var routeId = <?= (int)$ticket['route_id'] ?>;
    var excludeTicketId = <?= (int)$ticket['id'] ?>;
    var totalSeats = <?= (int)$ticket['total_seats'] ?>;
    var price = <?= (float)$ticket['price'] ?>;

    // Helper: Map single integers (1, 2) to (1A, 1B)
    function formatSeatLabel(rawSeat) {
        var str = String(rawSeat).trim();
        if (!isNaN(str) && parseInt(str) > 0) {
            var num = parseInt(str);
            var r = Math.ceil(num / 4);
            var letters = ['A', 'B', 'C', 'D'];
            var l = letters[(num - 1) % 4];
            return r + l;
        }
        return str;
    }

    var rawSeats = "<?= htmlspecialchars($ticket['seat_numbers'] ?? '') ?>".split(',').map(s => s.trim()).filter(Boolean);
    var selectedSeatLabels = rawSeats.map(formatSeatLabel);
    if (selectedSeatLabels.length === 0) { selectedSeatLabels = ['1A']; }

    var today = '<?= date('Y-m-d') ?>';
    var nowMinutes = <?= (int)date('H') * 60 + (int)date('i') ?>;
    var departureMinutes = <?= (function () use ($ticket) {
        $parts = explode(':', $ticket['departure_time']);
        return (int)$parts[0] * 60 + (int)($parts[1] ?? 0);
    })() ?>;

    function renderBusLayout(bookedSeatLabels) {
        // Standardize booked seat labels from DB to 1A, 1B format
        bookedSeatLabels = bookedSeatLabels.map(formatSeatLabel);
        seatGrid.innerHTML = '';
        var rows = Math.ceil(totalSeats / 4);
        var seatCount = 0;
        var letters = ['A', 'B', 'C', 'D'];

        // Remove any selected seats that are already taken by others on this date
        selectedSeatLabels = selectedSeatLabels.filter(lbl => !bookedSeatLabels.includes(lbl));

        for (var r = 1; r <= rows; r++) {
            for (var gridCol = 0; gridCol < 5; gridCol++) {
                if (gridCol === 2) {
                    var aisle = document.createElement('div');
                    seatGrid.appendChild(aisle);
                    continue;
                }

                seatCount++;
                if (seatCount > totalSeats) {
                    var empty = document.createElement('div');
                    seatGrid.appendChild(empty);
                    continue;
                }

                var letterIdx = (gridCol < 2) ? gridCol : gridCol - 1;
                var seatLabel = r + letters[letterIdx];
                var isTaken = bookedSeatLabels.includes(seatLabel);
                var isSelected = selectedSeatLabels.includes(seatLabel);

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'seat-btn';
                btn.textContent = seatLabel;
                btn.dataset.label = seatLabel;
                btn.style.width = '45px';
                btn.style.height = '45px';
                btn.style.borderRadius = '8px';
                btn.style.fontWeight = '600';
                btn.style.fontSize = '0.85rem';
                btn.style.transition = 'all 0.2s';

                if (isTaken) {
                    btn.disabled = true;
                    btn.style.border = '1px solid #e5e7eb';
                    btn.style.background = '#e5e7eb';
                    btn.style.color = '#9ca3af';
                    btn.style.cursor = 'not-allowed';
                } else {
                    btn.style.cursor = 'pointer';
                    if (isSelected) {
                        btn.style.background = '#2198a8';
                        btn.style.color = '#ffffff';
                        btn.style.borderColor = '#49afdb';
                    } else {
                        btn.style.background = '#ffffff';
                        btn.style.color = '#1f2937';
                        btn.style.borderColor = '#d1d5db';
                    }

                    btn.addEventListener('click', function () {
                        var lbl = this.dataset.label;
                        var idx = selectedSeatLabels.indexOf(lbl);

                        if (idx !== -1) {
                            if (selectedSeatLabels.length === 1) return;
                            selectedSeatLabels.splice(idx, 1);
                            this.style.background = '#ffffff';
                            this.style.color = '#1f2937';
                            this.style.borderColor = '#d1d5db';
                        } else {
                            if (selectedSeatLabels.length >= 3) {
                                alert('You can select a maximum of 3 seats per booking.');
                                return;
                            }
                            selectedSeatLabels.push(lbl);
                            this.style.background = '#2198a8';
                            this.style.color = '#ffffff';
                            this.style.borderColor = '#49afdb';
                        }
                        updateTotals();
                    });
                }
                seatGrid.appendChild(btn);
            }
        }
        updateTotals();
    }

    function updateTotals() {
        var count = selectedSeatLabels.length;
        seatQuantityInput.value = count;
        seatNumbersInput.value = selectedSeatLabels.join(', ');
        countDisplay.textContent = count;
        priceDisplay.textContent = (count * price).toFixed(2);
    }

    function refresh() {
        var date = dateInput.value;
        var departed = date === today && departureMinutes < nowMinutes;

        if (departed) {
            hint.textContent = 'This route has already departed today - choose a later date.';
            submitBtn.disabled = true;
            seatGrid.innerHTML = '';
            return;
        }

        submitBtn.disabled = false;
        hint.textContent = '';

        if (!date) { return; }

        fetch('route_availability.php?route_id=' + routeId + '&travel_date=' + encodeURIComponent(date) + '&exclude_ticket_id=' + excludeTicketId)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var fullRouteIds = (data.full_route_ids || []).map(String);
                var full = fullRouteIds.indexOf(String(routeId)) !== -1;
                hint.textContent = full ? 'This route is fully booked for that date - choose a different date.' : '';
                submitBtn.disabled = full;
                
                var bookedLabels = data.booked_seat_labels || [];
                renderBusLayout(bookedLabels);
            })
            .catch(function () {
                renderBusLayout([]);
            });
    }

    dateInput.addEventListener('change', refresh);
    refresh();
})();
</script>
<p style="margin-top: 20px;"><a class="btn btn-secondary btn-small" href="user_tickets.php">Back to My Tickets</a></p>
</div>
<?php require 'partials/footer.php'; ?>