<?php
require 'config.php';
require 'auth.php';
require_login();

header('Content-Type: application/json');

$date      = $_GET['travel_date'] ?? '';
$excludeId = (int)($_GET['exclude_ticket_id'] ?? 0);

if ($date === '') {
    echo json_encode(['full_route_ids' => []]);
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

echo json_encode(['full_route_ids' => $fullRouteIds]);
