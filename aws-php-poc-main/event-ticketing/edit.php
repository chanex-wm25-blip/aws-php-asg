<?php
require 'config.php';
require 'auth.php';
require_login();

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$uid = current_user_id();
$error = '';

$stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die('Order not found or you do not have permission to edit it.');
}

$stmt = $conn->prepare('SELECT has_seating FROM events WHERE id = ?');
$stmt->bind_param('i', $order['event_id']);
$stmt->execute();
$eventSeating = $stmt->get_result()->fetch_assoc();
$stmt->close();
$isSeated = (bool)($eventSeating['has_seating'] ?? false);

if ($isSeated) {
    die('This order has assigned seats and cannot be changed. Cancel the order and book again if you need different seats.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_quantity = (int)$_POST['quantity'];

    $conn->begin_transaction();

    $stmt = $conn->prepare('SELECT ticket_price, total_tickets, tickets_sold FROM events WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $order['event_id']);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $available = $event['total_tickets'] - $event['tickets_sold'] + $order['quantity'];

    if ($new_quantity < 1 || $new_quantity > $available) {
        $error = 'Invalid quantity. Only ' . $available . ' tickets available.';
        $conn->rollback();
    } else {
        $diff = $new_quantity - $order['quantity'];
        $total_price = $event['ticket_price'] * $new_quantity;

        $stmt = $conn->prepare('UPDATE orders SET quantity=?, total_price=? WHERE id=? AND user_id=?');
        $stmt->bind_param('idii', $new_quantity, $total_price, $id, $uid);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE events SET tickets_sold = tickets_sold + ? WHERE id = ?');
        $stmt->bind_param('ii', $diff, $order['event_id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        header('Location: index.php');
        exit;
    }

    $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$pageTitle = 'Edit Order';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Ticket Order</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
<label>Quantity <input type="number" name="quantity" min="1" value="<?= (int)$order['quantity'] ?>" required></label>
<button type="submit">Update Order</button>
</form>
<p><a class="btn btn-secondary btn-small" href="index.php">Back to home</a></p>
</div>
<?php require 'partials/footer.php'; ?>
