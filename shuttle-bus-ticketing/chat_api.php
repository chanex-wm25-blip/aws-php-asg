```php
<?php

require 'config.php';
require 'auth.php';

require_login();

header('Content-Type: application/json');

$uid = current_user_id();
$isAdmin = current_user_is_admin();

/*
|--------------------------------------------------------------------------
| Determine target user
|--------------------------------------------------------------------------
| Normal user:
|   - Can only access their own chat.
|
| Admin:
|   - Can access a selected normal user's chat.
|--------------------------------------------------------------------------
*/

if ($isAdmin) {
    $targetUser = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);

    if ($targetUser <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'A valid user is required.'
        ]);
        exit;
    }

    // Make sure target user exists and is not an admin
    $checkUser = $conn->prepare("
        SELECT id
        FROM users
        WHERE id = ?
        AND is_admin = 0
        LIMIT 1
    ");

    if (!$checkUser) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Unable to validate user.'
        ]);
        exit;
    }

    $checkUser->bind_param("i", $targetUser);
    $checkUser->execute();

    $result = $checkUser->get_result();

    if ($result->num_rows === 0) {
        $checkUser->close();

        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'User not found.'
        ]);
        exit;
    }

    $checkUser->close();

} else {

    // Normal user can ONLY access their own chat
    $targetUser = $uid;
}


/*
|--------------------------------------------------------------------------
| SEND MESSAGE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'Invalid request body.'
        ]);

        exit;
    }


    $msg = trim($input['message'] ?? '');

    if ($msg === '') {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'Message cannot be empty.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    */

    $sender = $isAdmin ? 'admin' : 'user';


    /*
    |--------------------------------------------------------------------------
    | Insert message
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO chat_messages
        (user_id, sender, message)
        VALUES (?, ?, ?)
    ");

    if (!$stmt) {

        error_log(
            'Chat insert prepare failed: ' . $conn->error
        );

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'error' => 'Chat service is unavailable.'
        ]);

        exit;
    }


    $stmt->bind_param(
        "iss",
        $targetUser,
        $sender,
        $msg
    );


    if (!$stmt->execute()) {

        error_log(
            'Chat insert failed: ' . $stmt->error
        );

        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'error' => 'Message could not be sent.'
        ]);

        exit;
    }


    $stmt->close();


    echo json_encode([
        'success' => true
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET MESSAGES
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        sender,
        message,
        DATE_FORMAT(created_at, '%H:%i') AS time
    FROM chat_messages
    WHERE user_id = ?
    ORDER BY id ASC
");


if (!$stmt) {

    error_log(
        'Chat select prepare failed: ' . $conn->error
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Unable to load chat messages.'
    ]);

    exit;
}


$stmt->bind_param(
    "i",
    $targetUser
);

$stmt->execute();


$messages = $stmt
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);


$stmt->close();


echo json_encode([
    'success' => true,
    'messages' => $messages
]);
