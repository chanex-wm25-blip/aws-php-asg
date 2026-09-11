<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$uid = current_user_id();
$myTickets = [];
if ($uid) {
    $stmt = $conn->prepare("
        SELECT t.id, r.route_name, r.origin, r.destination, r.departure_time, 
               t.travel_date, t.seat_quantity, t.total_price,
               COALESCE(t.seat_numbers, '-') AS seat_numbers,
               COALESCE(t.status, 'pending') AS status
        FROM tickets t
        JOIN routes r ON r.id = t.route_id
        WHERE t.user_id = ?
        ORDER BY t.travel_date DESC
    ");
    if (!$stmt) {
        error_log('User tickets query prepare failed: ' . $conn->error);
        http_response_code(500);
        die('Unable to load your tickets. Check the server error log.');
    }
    $stmt->bind_param('i', $uid);
    if (!$stmt->execute()) {
        error_log('User tickets query execute failed: ' . $stmt->error);
        $stmt->close();
        http_response_code(500);
        die('Unable to load your tickets. Check the server error log.');
    }
    $myTickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'My Tickets - Campus Shuttle Bus Ticketing';
require 'partials/header.php';
?>
<div style="max-width: 800px; margin: 30px auto; padding: 0 20px;">
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 5px;">My Tickets</h2>
    <p style="color: #6b7280; font-size: 0.95rem; margin-bottom: 25px;">Show ticket's QR code at the entrance to check in.</p>

    <?php if (empty($myTickets)): ?>
        <div class="empty-state" style="background: #fff; padding: 40px; border-radius: 12px; text-align: center; border: 1px solid #e5e7eb;">
            <div class="empty-state-icon" style="font-size: 2.5rem; margin-bottom: 10px;">&#128196;</div>
            <p style="color: #4b5563; font-size: 1rem;">You haven't booked any tickets yet.</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($myTickets as $t): 
                $status = $t['status'];
                $bgColors = [
                    'pending'   => '#fef3c7; color: #92400e;',
                    'confirmed' => '#d1fae5; color: #065f46;',
                    'cancelled' => '#fee2e2; color: #991b1b;'
                ];
                $style = $bgColors[$status] ?? '#f3f4f6; color: #374151;';

                $seats = array_map('trim', explode(',', $t['seat_numbers']));
                $totalSeatsInTicket = count($seats);
                $pricePerSeat = $totalSeatsInTicket > 0 ? ($t['total_price'] / $totalSeatsInTicket) : $t['total_price'];

                foreach ($seats as $index => $seatNum):
                    $ticketIndex = $index + 1;
                    $uniqueHash = md5('ticket_' . $t['id'] . '_seat_' . $seatNum);

                    $qrData = sprintf(
                        "TicketID:%d|Route:%s|Date:%s|Seat:%s|User:%d",
                        $t['id'],
                        $t['route_name'],
                        $t['travel_date'],
                        $seatNum,
                        $uid
                    );
                    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);
            ?>
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                
                <!-- Left: QR Code & Details -->
                <div style="display: flex; align-items: center; gap: 20px;">
                    <?php if ($status === 'confirmed'): ?>
                        <img src="<?= $qrApiUrl ?>" alt="Boarding QR Code" style="width: 120px; height: 120px; border-radius: 8px; border: 1px solid #e5e7eb; padding: 6px; background: #fff;" title="Show this to the driver">
                    <?php else: ?>
                        <div style="width: 120px; height: 120px; border-radius: 8px; border: 1px solid #e5e7eb; background: #f9fafb; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 0.8rem; text-align: center; padding: 10px;">
                            QR Available when Confirmed
                        </div>
                    <?php endif; ?>

                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                            <h3 style="font-size: 1.1rem; font-weight: 700; color: #1f2937; margin: 0;">Ticket <?= $ticketIndex ?> of <?= $totalSeatsInTicket ?> &middot; <?= htmlspecialchars($t['route_name']) ?></h3>
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; background-color: <?= $style ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                        </div>
                        <p style="margin: 0 0 4px 0; font-size: 1rem; font-weight: 600; color: #6b21a8;">Seat <?= htmlspecialchars($seatNum) ?></p>
                        <p style="margin: 0 0 4px 0; font-size: 0.85rem; color: #4b5563;">Travel Date: <strong><?= htmlspecialchars($t['travel_date']) ?></strong> (Departs <?= htmlspecialchars($t['departure_time']) ?>)</p>
                        <p style="margin: 0; font-size: 0.75rem; color: #9ca3af; font-family: monospace; word-break: break-all; max-width: 320px;"><?= htmlspecialchars($uniqueHash) ?></p>
                    </div>
                </div>

                <!-- Right: Price & Actions -->
                <div style="display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
                    <span style="font-size: 1rem; font-weight: 600; color: #1f2937;">RM<?= number_format($pricePerSeat, 2) ?></span>
                    <?php if ($status !== 'cancelled'): ?>
                        <div style="display: flex; gap: 8px;">
                            <a class="btn btn-secondary btn-small" href="edit.php?id=<?= (int)$t['id'] ?>" style="padding: 6px 12px; font-size: 0.85rem;">Edit</a>
                            <form action="delete.php" method="post" style="display:inline" onsubmit="return confirm('Cancel this ticket?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn-small btn-danger" style="padding: 6px 12px; font-size: 0.85rem;">Cancel</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span style="color: #9ca3af; font-size: 0.85rem;">Cancelled</span>
                    <?php endif; ?>
                </div>

            </div>
            <?php 
                endforeach; 
            endforeach; 
            ?>
        </div>
    <?php endif; ?>
</div>
<?php require 'partials/footer.php'; ?>