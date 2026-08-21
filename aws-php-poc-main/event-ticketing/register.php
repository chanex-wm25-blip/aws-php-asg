<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $confirm       = $_POST['confirm_password'] ?? '';
    $id_number     = trim($_POST['id_number'] ?? '');
    $faculty       = trim($_POST['faculty'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $date_of_birth === '' || $id_number === '' || $faculty === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = 'An account with this email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (name, email, password_hash, id_number, faculty, date_of_birth) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssss', $name, $email, $password_hash, $id_number, $faculty, $date_of_birth);
            $stmt->execute();
            $user_id = $stmt->insert_id;
            $stmt->close();

            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['is_admin'] = false;
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Register';
require 'partials/header.php';
?>
<div class="auth-card">
<h1>Create an Account</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Full Name <span class="required-mark">*</span> <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required></label>
<label>Email <span class="required-mark">*</span> <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></label>
<label>Student ID / Staff ID <span class="required-mark">*</span> <input type="text" name="id_number" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>" required></label>
<label>Faculty <span class="required-mark">*</span>
<select name="faculty" required>
<option value="">-- Select Faculty / Centre --</option>
<?php foreach (tarumt_faculties() as $f): ?>
<option value="<?= htmlspecialchars($f) ?>" <?= ($_POST['faculty'] ?? '') === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Date of Birth <span class="required-mark">*</span> <input type="date" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>" required></label>
<label>Password <span class="required-mark">*</span>
<div class="password-field">
<input type="password" name="password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<label>Confirm Password <span class="required-mark">*</span>
<div class="password-field">
<input type="password" name="confirm_password" required>
<button type="button" class="password-toggle" tabindex="-1" aria-label="Show password"></button>
</div>
</label>
<button type="submit">Register</button>
</form>
<p>Already have an account? <a href="login.php">Login here</a></p>
</div>
<?php require 'partials/footer.php'; ?>
