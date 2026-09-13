<?php
require 'config.php';
require 'auth.php';
require_login();

header('Content-Type: application/json');

$uid = current_user_id();
$isAdmin = current_user_is_admin();
$targetUser = $isAdmin ? (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0) : $uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request body.']);
        exit;
    }
    $msg = trim($input['message'] ?? '');
    $user_id = $isAdmin ? (int)($input['user_id'] ?? 0) : $uid;
    $sender = $isAdmin ? 'admin' : 'user';

    if ($msg === '' || $user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A message and user are required.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        error_log('Chat insert prepare failed: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Chat service is unavailable.']);
        exit;
    }
    $stmt->bind_param("iss", $user_id, $sender, $msg);
    if (!$stmt->execute()) {
        error_log('Chat insert failed: ' . $stmt->error);
        $stmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Message could not be sent.']);
        exit;
    }
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

$stmt = $conn->prepare("SELECT sender, message, DATE_FORMAT(created_at, '%H:%i') as time FROM chat_messages WHERE user_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $targetUser);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['messages' => $messages]);