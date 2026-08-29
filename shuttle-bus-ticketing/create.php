<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$error = '';
$selectedRoute = (int)($_GET['route_id'] ?? 0);
$selectedDate  = $_GET['travel_date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $route_id      = (int)($_POST['route_id'] ?? 0);
    $travel_date   = $_POST['travel_date'] ?? '';
    $seat_quantity = (int)($_POST['seat_quantity'] ?? 0);
    $uid           = current_user_id();
    $selectedRoute = $route_id;
    $selectedDate  = $travel_date;

    if ($travel_date === '' || $seat_quantity < 1 || $seat_quantity > 3) {
        $error = 'Please choose a travel date and 1 to 3 seats.';
    } elseif ($travel_date < date('Y-m-d')) {
        $error = 'Travel date cannot be in the past.';
    } else {
        $conn->begin_transaction();

        // Ensure user ID is a valid integer
        $uid = (int)current_user_id();

        // 2. Enforce per-user seat limit (Maximum 3 seats total per user per date)
        $stmtUserLock = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $stmtUserLock->bind_param('i', $uid);
        $stmtUserLock->execute();
        $stmtUserLock->close();

        $stmtLimit = $conn->prepare('SELECT COALESCE(SUM(seat_quantity), 0) AS total_user_seats FROM tickets WHERE user_id = ? AND travel_date = ?');
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
            $conn->rollback();
        } else {
            $stmt = $conn->prepare('SELECT price, total_seats, departure_time FROM routes WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $route_id);
            $stmt->execute();
            $route = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$route) {
                $error = 'Route not found.';
                $conn->rollback();
            } elseif (is_departure_in_past($travel_date, $route['departure_time'])) {
                $error = 'This route has already departed today. Please choose a later route or date.';
                $conn->rollback();
            } else {
                $stmt = $conn->prepare('SELECT COALESCE(SUM(seat_quantity), 0) AS booked FROM tickets WHERE route_id = ? AND travel_date = ?');
                $stmt->bind_param('is', $route_id, $travel_date);
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
                    $total_price = $route['price'] * $seat_quantity;

                    $stmt = $conn->prepare('INSERT INTO tickets (user_id, route_id, travel_date, seat_quantity, total_price) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('iisid', $uid, $route_id, $travel_date, $seat_quantity, $total_price);
                    $stmt->execute();
                    $stmt->close();
                    $conn->commit();
                    header('Location: index.php');
                    exit;
                }
            }
        }
    }
}

$routes = $conn->query('SELECT * FROM routes ORDER BY departure_time');

$pageTitle = 'Book Shuttle Ticket';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Book a Shuttle Ticket</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" id="ticket-form">
<!-- 3. Hidden CSRF Token Field -->
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

<label>Route
<select name="route_id" id="route-select" required>
<?php while ($r = $routes->fetch_assoc()): ?>
<option value="<?= (int)$r['id'] ?>" data-departure="<?= htmlspecialchars($r['departure_time']) ?>" <?= $r['id'] == $selectedRoute ? 'selected' : '' ?>><?= htmlspecialchars($r['route_name']) ?> - RM<?= number_format($r['price'], 2) ?> (departs <?= htmlspecialchars($r['departure_time']) ?>)</option>
<?php endwhile; ?>
</select>
</label>
<label>Travel Date <input type="date" name="travel_date" id="travel-date" value="<?= htmlspecialchars($selectedDate) ?>" min="<?= date('Y-m-d') ?>" required></label>
<p class="form-hint" id="route-availability-hint"></p>
<label>Number of Seats <input type="number" name="seat_quantity" min="1" max="3" value="1" required></label>
<button type="submit">Book Ticket</button>
</form>
<script>
(function () {
    var routeSelect = document.getElementById('route-select');
    var dateInput = document.getElementById('travel-date');
    var hint = document.getElementById('route-availability-hint');
    var today = '<?= date('Y-m-d') ?>';
    var nowMinutes = <?= (int)date('H') * 60 + (int)date('i') ?>;

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
        if (routeSelect.value === opt.value) {
            routeSelect.value = '';
        }
    }

    function refresh() {
        var date = dateInput.value;
        var isToday = date === today;

        resetOptions();

        if (isToday) {
            Array.prototype.forEach.call(routeSelect.options, function (opt) {
                if (opt.dataset.departure && departureMinutes(opt.dataset.departure) < nowMinutes) {
                    markDisabled(opt, 'Departed');
                }
            });
        }

        if (!date) { return; }

        fetch('route_availability.php?travel_date=' + encodeURIComponent(date))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var fullRouteIds = (data.full_route_ids || []).map(String);
                Array.prototype.forEach.call(routeSelect.options, function (opt) {
                    if (fullRouteIds.indexOf(opt.value) !== -1 && !opt.disabled) {
                        markDisabled(opt, 'Fully Booked');
                    }
                });
                hint.textContent = 'Greyed-out routes have already departed today or are fully booked for this date.';
            })
            .catch(function () { /* availability check is a convenience, not required to book */ });
    }

    routeSelect.addEventListener('change', refresh);
    dateInput.addEventListener('change', refresh);
    refresh();
})();
</script>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>