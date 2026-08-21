<?php
require 'config.php';
require 'auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)$_POST['id'];
    $uid = current_user_id();

    $conn->begin_transaction();

    $stmt = $conn->prepare('SELECT event_id, quantity FROM orders WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        $stmt = $conn->prepare('UPDATE seats SET is_booked = 0 WHERE id IN (SELECT seat_id FROM tickets WHERE order_id = ? AND seat_id IS NOT NULL)');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM tickets WHERE order_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM orders WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $id, $uid);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE events SET tickets_sold = tickets_sold - ? WHERE id = ?');
        $stmt->bind_param('ii', $order['quantity'], $order['event_id']);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
}

header('Location: index.php');
exit;
