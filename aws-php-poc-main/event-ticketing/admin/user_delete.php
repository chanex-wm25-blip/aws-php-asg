<?php
require '../config.php';
require '../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $myId = (int)current_user_id();

    if ($id === $myId) {
        $_SESSION['flash_error'] = 'You cannot delete your own account.';
    } else {
        $conn->begin_transaction();

        // Restore ticket inventory for every order this user made before deleting them.
        $stmt = $conn->prepare('SELECT event_id, quantity FROM orders WHERE user_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($orders as $order) {
            $stmt = $conn->prepare('UPDATE events SET tickets_sold = tickets_sold - ? WHERE id = ?');
            $stmt->bind_param('ii', $order['quantity'], $order['event_id']);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare('UPDATE seats SET is_booked = 0 WHERE id IN (SELECT seat_id FROM tickets WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?) AND seat_id IS NOT NULL)');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM tickets WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM orders WHERE user_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM testimonials WHERE user_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
    }
}

header('Location: users.php');
exit;
