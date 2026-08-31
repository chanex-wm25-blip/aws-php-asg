<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';

    $allowed = ['pending', 'confirmed', 'done', 'cancelled'];

    if ($id > 0 && in_array($status, $allowed, true)) {
        $stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: tickets.php');
exit;