<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$error = '';
$selectedRoute = (int)($_GET['route_id'] ?? 0);
$selectedDate  = $_GET['travel_date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $route_id      = (int)($_POST['route_id'] ?? 0);
    $travel_date   = $_POST['travel_date'] ?? '';
    $seat_quantity = (int)($_POST['seat_quantity'] ?? 0);
    $seat_numbers  = trim($_POST['seat_numbers'] ?? '');
    $uid           = (int)current_user_id();

    if ($uid <= 0) {
        die('Invalid session. Please log in again.');
    }

    $selectedRoute = $route_id;
    $selectedDate  = $travel_date;

    if ($travel_date === '' || $seat_quantity < 1 || $seat_quantity > 3) {
        $error = 'Please choose a travel date and 1 to 3 seats.';
    } elseif ($travel_date < date('Y-m-d')) {
        $error = 'Travel date cannot be in the past.';
    } else {
        $conn->begin_transaction();

        $stmtLimit = $conn->prepare('SELECT COALESCE(SUM(seat_quantity), 0) AS total_user_seats FROM tickets WHERE user_id = ? AND travel_date = ? FOR UPDATE');
        $stmtLimit->bind_param('is', $uid, $travel_date);
        $stmtLimit->execute();
        $res = $stmtLimit->get_result()->fetch_assoc();
        $currentSeats = (int)($res['total_user_seats'] ?? 0);
        $stmtLimit->close();

        if (($currentSeats + $seat_quantity) > 3) {
            $remaining = 3 - $currentSeats;
            $error = $remaining > 0 
                ? "You can only book $remaining more seat(s) for this date (Account Limit: 3 seats total per date)."
                : "You have already reached your maximum limit of 3 booked seats for this date.";
            send_sns_alert(
                'Booking failed: user limit reached',
                sprintf(
                    'User ID %d attempted to book %d seat(s) on %s but exceeded the daily limit. Current seats: %d',
                    $uid,
                    $seat_quantity,
                    $travel_date,
                    $currentSeats
                )
            );
            $conn->rollback();
        } else {
            $stmt = $conn->prepare('SELECT price, total_seats, departure_time FROM routes WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $route_id);
            $stmt->execute();
            $route = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$route) {
                $error = 'Route not found.';
                send_sns_alert('Booking failed: route not found', sprintf('User ID %d attempted booking for route ID %d on %s.', $uid, $route_id, $travel_date));
                $conn->rollback();
            } elseif (is_departure_in_past($travel_date, $route['departure_time'])) {
                $error = 'This route has already departed today. Please choose a later route or date.';
                send_sns_alert('Booking failed: route already departed', sprintf('User ID %d attempted booking for route ID %d on %s after departure time %s.', $uid, $route_id, $travel_date, $route['departure_time']));
                $conn->rollback();
            } else {
                $stmt = $conn->prepare('SELECT COALESCE(SUM(seat_quantity), 0) AS booked FROM tickets WHERE route_id = ? AND travel_date = ? FOR UPDATE');
                $stmt->bind_param('is', $route_id, $travel_date);
                $stmt->execute();
                $booked = (int)$stmt->get_result()->fetch_assoc()['booked'];
                $stmt->close();

                if ($booked + $seat_quantity > $route['total_seats']) {
                    $available = $route['total_seats'] - $booked;
                    $error = $available > 0
                        ? "Only $available seat(s) remaining on this route for that date."
                        : 'This route is fully booked for that date.';
                    send_sns_alert(
                        'Booking failed: route full',
                        sprintf(
                            'User ID %d attempted to book %d seat(s) for route ID %d on %s. Available seats: %d',
                            $uid,
                            $seat_quantity,
                            $route_id,
                            $travel_date,
                            $available
                        )
                    );
                    $conn->rollback();
                } else {
                    $total_price = $route['price'] * $seat_quantity;

                    $stmt = $conn->prepare("INSERT INTO tickets (user_id, route_id, travel_date, seat_quantity, seat_numbers, total_price, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->bind_param('iissds', $uid, $route_id, $travel_date, $seat_quantity, $seat_numbers, $total_price);
                    
                    if ($stmt->execute()) {
                        $newTicketId = $stmt->insert_id;
                        $stmt->close();
                        $conn->commit();
                        header('Location: payment.php?id=' . $newTicketId);
                        exit;
                    } else {
                        $stmt->close();
                        $conn->rollback();
                        $error = 'Booking failed due to a system error. Please try again.';
                    }
                }
            }
        }
    }
}

$routesData = [];
$routesQuery = $conn->query('SELECT id, route_name, price, total_seats, departure_time FROM routes ORDER BY departure_time');
while ($r = $routesQuery->fetch_assoc()) {
    $routesData[] = $r;
}

$pageTitle = 'Select Your Seats';
require 'partials/header.php';
?>
<div class="form-card" style="max-width: 600px; margin: 20px auto;">
<h1>Select Your Seats</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<form method="post" id="ticket-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

<label>Route
<select name="route_id" id="route-select" required>
<?php foreach ($routesData as $r): ?>
<option value="<?= (int)$r['id'] ?>" 
        data-price="<?= (float)$r['price'] ?>" 
        data-seats="<?= (int)$r['total_seats'] ?>" 
        data-departure="<?= htmlspecialchars($r['departure_time']) ?>" 
        <?= $r['id'] == $selectedRoute ? 'selected' : '' ?>>
    <?= htmlspecialchars($r['route_name']) ?> - RM<?= number_format($r['price'], 2) ?> (departs <?= htmlspecialchars($r['departure_time']) ?>)
</option>
<?php endforeach; ?>
</select>
</label>

<label>Travel Date 
<input type="date" name="travel_date" id="travel-date" value="<?= htmlspecialchars($selectedDate) ?>" min="<?= date('Y-m-d') ?>" required>
</label>

<p class="form-hint" id="route-availability-hint"></p>

<div style="display: flex; gap: 20px; align-items: center; margin: 20px 0 15px 0; font-size: 0.9rem;">
    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 16px; height: 16px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; display:inline-block;"></span> Available</div>
    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 16px; height: 16px; background: #49afdb; border-radius: 4px; display:inline-block;"></span> Selected</div>
    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 16px; height: 16px; background: #e5e7eb; border-radius: 4px; display:inline-block;"></span> Taken</div>
</div>

<!-- Bus Seating Layout Container -->
<div id="seat-grid-container" style="display: grid; grid-template-columns: 45px 45px 25px 45px 45px; gap: 8px; justify-content: center; margin-bottom: 20px; background: #f9fafb; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
    <!-- Rendered dynamically -->
</div>

<input type="hidden" name="seat_quantity" id="seat-quantity" value="1">
<input type="hidden" name="seat_numbers" id="seat-numbers" value="1A">

<div style="margin-bottom: 20px; font-size: 1.05rem; font-weight: 600; text-align: center;">
    Selected: <span id="selected-count-display">1</span> seat(s) &middot; Total: RM<span id="total-price-display">0.00</span>
</div>

<button type="submit" id="submit-btn" style="background: #76d8d8; color: white; padding: 12px 24px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; width: 100%;">Book Now</button>
</form>

<script>
(function () {
    var routeSelect = document.getElementById('route-select');
    var dateInput = document.getElementById('travel-date');
    var hint = document.getElementById('route-availability-hint');
    var seatGrid = document.getElementById('seat-grid-container');
    var seatQuantityInput = document.getElementById('seat-quantity');
    var seatNumbersInput = document.getElementById('seat-numbers');
    var countDisplay = document.getElementById('selected-count-display');
    var priceDisplay = document.getElementById('total-price-display');
    var submitBtn = document.getElementById('submit-btn');

    var today = '<?= date('Y-m-d') ?>';
    var nowMinutes = <?= (int)date('H') * 60 + (int)date('i') ?>;
    var selectedSeatLabels = ['1A'];

    function departureMinutes(value) {
        var parts = value.split(':');
        return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
    }

    function resetOptions() {
        Array.prototype.forEach.call(routeSelect.options, function (opt) {
            if (opt.dataset.originalText) {
                opt.textContent = opt.dataset.originalText;
            }
            opt.disabled = false;
        });
        hint.textContent = '';
    }

    function markDisabled(opt, label) {
        opt.dataset.originalText = opt.dataset.originalText || opt.textContent;
        opt.textContent = opt.dataset.originalText + ' (' + label + ')';
        opt.disabled = true;
    }

    function renderBusLayout(totalSeats, price, bookedSeatLabels) {
        seatGrid.innerHTML = '';
        selectedSeatLabels = [];

        var rows = Math.ceil(totalSeats / 4);
        var seatCount = 0;
        var letters = ['A', 'B', 'C', 'D'];
        var availableCount = 0;

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
                    availableCount++;
                    btn.style.border = '1px solid #d1d5db';
                    btn.style.background = '#ffffff';
                    btn.style.color = '#1f2937';
                    btn.style.cursor = 'pointer';

                    if (selectedSeatLabels.length === 0) {
                        selectedSeatLabels.push(seatLabel);
                        btn.style.background = '#49afdb';
                        btn.style.color = '#ffffff';
                        btn.style.borderColor = '#49afdb';
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
                            this.style.background = '#49afdb';
                            this.style.color = '#ffffff';
                            this.style.borderColor = '#49afdb';
                        }
                        updateTotals(price);
                    });
                }
                seatGrid.appendChild(btn);
            }
        }

        if (availableCount === 0) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        } else {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        }

        updateTotals(price);
    }

    function updateTotals(price) {
        var count = selectedSeatLabels.length;
        seatQuantityInput.value = count;
        seatNumbersInput.value = selectedSeatLabels.join(', ');
        countDisplay.textContent = count;
        priceDisplay.textContent = (count * price).toFixed(2);
    }

    function refresh() {
        // Step 1: Wipe clean previous disabled states
        resetOptions();

        var date = dateInput.value;
        var isToday = (date === today);

        // Step 2: Only disable departed times if selected date is EXACTLY today
        if (isToday) {
            Array.prototype.forEach.call(routeSelect.options, function (opt) {
                if (opt.dataset.departure && departureMinutes(opt.dataset.departure) < nowMinutes) {
                    markDisabled(opt, 'Departed');
                }
            });
        }

        // Step 3: Pick first valid enabled option if current choice is empty or disabled
        if (routeSelect.selectedIndex === -1 || routeSelect.options[routeSelect.selectedIndex].disabled) {
            for (var i = 0; i < routeSelect.options.length; i++) {
                if (!routeSelect.options[i].disabled) {
                    routeSelect.selectedIndex = i;
                    break;
                }
            }
        }

        var selectedOpt = routeSelect.options[routeSelect.selectedIndex];
        if (!selectedOpt || selectedOpt.disabled) {
            seatGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #6b7280;">Please select an available route.</p>';
            return;
        }

        var routeId = selectedOpt.value;
        var totalSeats = parseInt(selectedOpt.dataset.seats || '32', 10);
        var price = parseFloat(selectedOpt.dataset.price || '0');

        if (!date) { return; }

        // Step 4: Fetch booking data for the selected date
        fetch('route_availability.php?travel_date=' + encodeURIComponent(date) + '&route_id=' + encodeURIComponent(routeId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var fullRouteIds = (data.full_route_ids || []).map(String);
                Array.prototype.forEach.call(routeSelect.options, function (opt) {
                    if (fullRouteIds.indexOf(opt.value) !== -1 && !opt.disabled) {
                        markDisabled(opt, 'Fully Booked');
                    }
                });
                hint.textContent = 'Greyed-out routes have already departed today or are fully booked for this date.';
                
                var bookedLabels = data.booked_seat_labels || [];
                renderBusLayout(totalSeats, price, bookedLabels);
            })
            .catch(function () {
                renderBusLayout(totalSeats, price, []);
            });
    }

    routeSelect.addEventListener('change', refresh);
    dateInput.addEventListener('change', refresh);
    refresh();
})();
</script>
<p style="margin-top: 20px;"><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>