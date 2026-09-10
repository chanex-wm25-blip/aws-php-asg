<?php
require 'config.php';
require 'auth.php';
require_login();

header('Content-Type: application/json');

$date      = $_GET['travel_date'] ?? '';
$routeId   = (int)($_GET['route_id'] ?? 0);
$excludeId = (int)($_GET['exclude_ticket_id'] ?? 0);

if ($date === '') {
    echo json_encode(['full_route_ids' => [], 'booked_seat_labels' => []]);
    exit;
}

$result = $conn->query('SELECT id, total_seats FROM routes');
if (!$result) {
    http_response_code(500);
    error_log('Routes query failed: ' . $conn->error);
    echo json_encode(['error' => 'Database error. Check the server error log.']);
    exit;
}
$routes = $result->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare('SELECT route_id, COALESCE(SUM(seat_quantity), 0) AS booked FROM tickets WHERE travel_date = ? AND id != ? GROUP BY route_id');
$stmt->bind_param('si', $date, $excludeId);
$stmt->execute();
$bookedByRoute = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $bookedByRoute[(int)$row['route_id']] = (int)$row['booked'];
}
$stmt->close();

$fullRouteIds = [];
foreach ($routes as $r) {
    $booked = $bookedByRoute[(int)$r['id']] ?? 0;
    if ($booked >= (int)$r['total_seats']) {
        $fullRouteIds[] = (int)$r['id'];
    }
}

$bookedSeatLabels = [];
if ($routeId > 0) {
    $stmtSeats = $conn->prepare('SELECT seat_numbers FROM tickets WHERE route_id = ? AND travel_date = ? AND id != ?');
    $stmtSeats->bind_param('isi', $routeId, $date, $excludeId);
    $stmtSeats->execute();
    $seatRes = $stmtSeats->get_result();
    while ($sRow = $seatRes->fetch_assoc()) {
        if (!empty($sRow['seat_numbers'])) {
            foreach (explode(',', $sRow['seat_numbers']) as $lbl) {
                $cleaned = trim($lbl);
                if ($cleaned !== '') {
                    $bookedSeatLabels[] = $cleaned;
                }
            }
        }
    }
    $stmtSeats->close();
}

echo json_encode([
    'full_route_ids' => $fullRouteIds,
    'booked_seat_labels' => $bookedSeatLabels
]);