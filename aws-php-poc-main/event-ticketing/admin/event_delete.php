<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $uploadDir = __DIR__ . '/../uploads';

    $stmt = $conn->prepare('SELECT image_url FROM events WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Only removes seats that have no tickets referencing them. If any order
    // (and therefore tickets) exist for this event, this silently does
    // nothing and the events DELETE below fails with the usual FK message.
    $stmt = $conn->prepare('DELETE FROM seats WHERE event_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM events WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        if ($event) {
            delete_image_file($event['image_url'], $uploadDir);
        }
    } else {
        $_SESSION['flash_error'] = 'Cannot delete this event: it still has orders referencing it.';
    }
    $stmt->close();
}

header('Location: events.php');
exit;
