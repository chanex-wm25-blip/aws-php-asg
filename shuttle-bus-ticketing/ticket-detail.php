<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$id  = (int)($_GET['id'] ?? 0);
$uid = current_user_id();

$stmt = $conn->prepare('SELECT t.*, r.route_name, r.departure_time, r.price, r.image_url FROM tickets t JOIN routes r ON r.id = t.route_id WHERE t.id = ? AND t.user_id = ?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die('Ticket not found or permission denied.');
}

$pageTitle = 'Ticket Details';
require 'partials/header.php';
?>
<div class="container">
    <div class="form-card" style="max-width: 560px;">
        <h1>Shuttle Ticket Pass</h1>
        <p class="form-hint">Present this digital ticket pass upon boarding.</p>

        <div style="text-align: center; margin: 20px 0;">
            <img src="<?= htmlspecialchars(entity_image_url($ticket)) ?>" alt="Bus Image" class="card-thumb" style="max-height: 200px; cursor: default;">
        </div>

        <table style="margin-bottom: 20px;">
            <tr>
                <th>Ticket ID</th>
                <td>#<?= sprintf('%05d', $ticket['id']) ?></td>
            </tr>
            <tr>
                <th>Route Name</th>
                <td><strong><?= htmlspecialchars($ticket['route_name']) ?></strong></td>
            </tr>
            <tr>
                <th>Travel Date</th>
                <td><?= htmlspecialchars($ticket['travel_date']) ?></td>
            </tr>
            <tr>
                <th>Departure Time</th>
                <td><?= htmlspecialchars($ticket['departure_time']) ?></td>
            </tr>
            <tr>
                <th>Seats Reserved</th>
                <td><?= (int)$ticket['seat_quantity'] ?> Seat(s)</td>
            </tr>
            <tr>
                <th>Total Price</th>
                <td>RM <?= number_format($ticket['total_price'], 2) ?></td>
            </tr>
            <tr>
                <th>Booked At</th>
                <td><?= htmlspecialchars($ticket['created_at']) ?></td>
            </tr>
        </table>

        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn btn-secondary btn-small">Back to My Tickets</a>
            <a href="edit.php?id=<?= (int)$ticket['id'] ?>" class="btn btn-small">Edit Ticket</a>
            <button onclick="window.print()" class="btn btn-secondary btn-small">Print Ticket</button>
        </div>
    </div>
</div>
<?php require 'partials/footer.php'; ?>