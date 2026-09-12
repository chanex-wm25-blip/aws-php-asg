<?php
// Sessions are stored in the database (not local disk) so that any EC2
// instance behind an ALB/ASG can read a session written by a different
// instance - PHP's default file-based sessions only live on the instance
// that created them, so a request an ALB routes to a different instance
// would otherwise see the user as logged out. See schema.sql's
// `sessions` table.
class DbSessionHandler implements SessionHandlerInterface {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function open($path, $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string {
        $stmt = $this->conn->prepare('SELECT data FROM sessions WHERE id = ?');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool {
        $now = time();
        $stmt = $this->conn->prepare('INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)');
        $stmt->bind_param('ssi', $id, $data, $now);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function destroy($id): bool {
        $stmt = $this->conn->prepare('DELETE FROM sessions WHERE id = ?');
        $stmt->bind_param('s', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function gc($maxLifetime): int|false {
        $threshold = time() - $maxLifetime;
        $stmt = $this->conn->prepare('DELETE FROM sessions WHERE last_activity < ?');
        $stmt->bind_param('i', $threshold);
        $stmt->execute();
        $count = $stmt->affected_rows;
        $stmt->close();
        return $count;
    }
}

session_set_save_handler(new DbSessionHandler($conn), true);
register_shutdown_function('session_write_close');
session_start();

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user_name() {
    return $_SESSION['user_name'] ?? null;
}

function current_user_is_admin() {
    return !empty($_SESSION['is_admin']);
}

function require_login() {
    if (!current_user_id()) {
        $loginPath = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false
            ? '../login.php'
            : 'login.php';
        header('Location: ' . $loginPath);
        exit;
    }
}

function require_admin() {
    require_login();
    if (!current_user_is_admin()) {
        http_response_code(403);
        die('Admins only.');
    }
}
