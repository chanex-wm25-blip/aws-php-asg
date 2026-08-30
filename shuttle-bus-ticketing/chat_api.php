<?php
require 'config.php';
require 'auth.php';
require_login();

header('Content-Type: application/json');

$uid = current_user_id();
$isAdmin = is_admin();
$targetUser = $isAdmin ? (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0) : $uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $msg = trim($input['message'] ?? '');
    $user_id = $isAdmin ? (int)($input['user_id'] ?? 0) : $uid;
    $sender = $isAdmin ? 'admin' : 'user';

    if ($msg !== '' && $user_id > 0) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $sender, $msg);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
}

$stmt = $conn->prepare("SELECT sender, message, DATE_FORMAT(created_at, '%H:%i') as time FROM chat_messages WHERE user_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $targetUser);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['messages' => $messages]);