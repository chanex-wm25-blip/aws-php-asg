<?php
require 'config.php';
require 'auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $email, $subject, $message);
        if ($stmt->execute()) {
            $stmt->close();
            $success = "Thanks for reaching out — we'll get back to you soon.";
        } else {
            $error = 'Could not send your message. Please try again.';
            $stmt->close();
        }
    }
}

$pageTitle = 'Contact Us';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Contact Us</h1>
<p>Have a question about an event or an order? Get in touch.</p>
</div>

<section>
<div class="card-grid">
<div class="card">
<div class="card-icon">&#128205;</div>
<h3>Location</h3>
<p>Student Societies Office<br>Student Centre, Level 1</p>
</div>
<div class="card">
<div class="card-icon">&#128222;</div>
<h3>Contact</h3>
<p>+60 3-4145 0450<br>societies@tarumt.edu.my</p>
</div>
<div class="card">
<div class="card-icon">&#128337;</div>
<h3>Office Hours</h3>
<p>Mon - Fri: 9:00 AM - 5:00 PM</p>
</div>
</div>
</section>

<section>
<h2>Send Us a Message</h2>
<div class="form-card" style="max-width:560px;">
<?php if ($success): ?><p class="alert alert-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if (!$success): ?>
<form method="post">
<label>Your Name <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required></label>
<label>Email <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></label>
<label>Subject <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required></label>
<label>Message <textarea name="message" rows="5" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea></label>
<button type="submit">Send Message</button>
</form>
<?php endif; ?>
</div>
</section>
<?php require 'partials/footer.php'; ?>
