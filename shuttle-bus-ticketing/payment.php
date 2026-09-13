<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$ticketId = (int)($_GET['id'] ?? 0);
$uid = current_user_id();

// Fetch ticket and route info
$stmt = $conn->prepare("
    SELECT t.*, r.route_name, r.departure_time 
    FROM tickets t 
    JOIN routes r ON r.id = t.route_id 
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->bind_param('ii', $ticketId, $uid);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    header('Location: user_tickets.php');
    exit;
}

$rawSeats = trim((string)($ticket['seat_numbers'] ?? ''));
if ($rawSeats === '') {
    $rawSeats = (string)$ticket['seat_quantity'];
}

$seatArray = array_filter(array_map('trim', explode(',', $rawSeats)));
$formattedSeats = array_map(function ($seat) {
    if (is_numeric($seat) && (int)$seat > 0) {
        $num = (int)$seat;
        $r = (int)ceil($num / 4);
        $letters = ['A', 'B', 'C', 'D'];
        return $r . $letters[($num - 1) % 4];
    }
    return $seat;
}, $seatArray);

$seatDisplay = implode(', ', $formattedSeats);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    // Simulate successful payment by updating status to 'confirmed'
    $updateStmt = $conn->prepare("UPDATE tickets SET status = 'confirmed' WHERE id = ? AND user_id = ?");
    $updateStmt->bind_param('ii', $ticketId, $uid);
    if ($updateStmt->execute()) {
        $updateStmt->close();
        header('Location: user_tickets.php');
        exit;
    } else {
        $error = 'Payment processing failed. Please try again.';
        $updateStmt->close();
    }
}

$pageTitle = 'Mock Payment Gateway';
require 'partials/header.php';
?>
<div class="card form-card" style="max-width: 500px; margin: 40px auto; padding: 24px; border-radius: 12px;">
    <h2>Payment Checkout</h2>
    <p style="opacity: 0.8; margin-bottom: 20px;">Complete your transaction for <strong><?= htmlspecialchars($ticket['route_name']) ?></strong>.</p>

    <?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div style="background: rgba(0,0,0,0.04); padding: 15px; border-radius: 8px; border: 1px solid var(--border, #e5e7eb); margin-bottom: 20px;">
        <p style="margin: 0 0 8px 0;"><strong>Seat(s):</strong> <?= htmlspecialchars($seatDisplay) ?></p>
        <p style="margin: 0 0 8px 0;"><strong>Travel Date:</strong> <?= htmlspecialchars($ticket['travel_date']) ?></p>
        <p style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #a855f7;">Total Amount: RM<?= number_format($ticket['total_price'], 2) ?></p>
    </div>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <button type="submit" class="btn" style="background: #2563eb; color: white; width: 100%; padding: 12px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer;">Pay RM<?= number_format($ticket['total_price'], 2) ?></button>
    </form>

    <p style="margin-top: 15px; text-align: center;"><a href="user_tickets.php" style="opacity: 0.7; font-size: 0.85rem; text-decoration: none;">Cancel / Pay Later</a></p>
</div>
<?php require 'partials/footer.php'; ?>