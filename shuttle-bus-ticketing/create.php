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
    $uid           = (int)current_user_id();

    // Block invalid or lost session IDs across load balancer targets
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
        // Fix 1: Start transaction before running database queries
        $conn->begin_transaction();

        // Fix 2: Lock the user's ticket rows using FOR UPDATE to sum existing seats accurately
        $stmtLimit = $conn->prepare('SELECT COALESCE(SUM(seat_quantity), 0) AS total_user_seats FROM tickets WHERE user_id = ? AND travel_date = ? FOR UPDATE');
        $stmtLimit->bind_param('is', $uid, $travel_date);
        $stmtLimit->execute();
        $res = $stmtLimit->get_result()->fetch_assoc();
        $currentSeats = (int)($res['total_user_seats'] ?? 0);
        $stmtLimit->close();

        // Check: If (existing seats + requested seats) exceeds 3, rollback and set error
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
                    $conn->rollback();
                } else {
                    $total_price = $route['price'] * $seat_quantity;

                    $stmt = $conn->prepare('INSERT INTO tickets (user_id, route_id, travel_date, seat_quantity, total_price) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('iisid', $uid, $route_id, $travel_date, $seat_quantity, $total_price);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Commit database changes
                    $conn->commit();
                    header('Location: index.php');
                    exit;
                }
            }
        }
    }
}