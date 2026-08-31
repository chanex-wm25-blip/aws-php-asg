<?php
require 'config.php';
require 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $route_id = (int)$_POST['route_id'];
    $comment  = trim($_POST['comment']);
    $rating   = (int)$_POST['rating'];
    $uid      = current_user_id();

    if ($route_id < 1 || $comment === '' || $rating < 1 || $rating > 5) {
        $error = 'Please choose a route, write a comment, and pick a rating between 1 and 5.';
    } else {
        $stmt = $conn->prepare('INSERT INTO testimonials (user_id, route_id, comment, rating) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iisi', $uid, $route_id, $comment, $rating);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: testimonials.php');
            exit;
        }
        $error = 'Could not post your comment. Please try again.';
        $stmt->close();
    }
}

$result = $conn->query('SELECT id, route_name FROM routes ORDER BY route_name');
if (!$result) {
    http_response_code(500);
    error_log('Routes query failed: ' . $conn->error);
    die('Failed to load routes. Check the server error log.');
}
$routes = $result->fetch_all(MYSQLI_ASSOC);

$result = $conn->query('
    SELECT t.id, t.comment, t.rating, t.created_at, t.user_id, u.name AS user_name, r.route_name
    FROM testimonials t
    JOIN users u ON u.id = t.user_id
    JOIN routes r ON r.id = t.route_id
    ORDER BY t.created_at DESC
');
if (!$result) {
    http_response_code(500);
    error_log('Testimonials query failed: ' . $conn->error);
    die('Failed to load testimonials. Check the server error log.');
}
$testimonials = $result->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Testimonials';
require 'partials/header.php';
?>
<div class="page-header">
<h1>Testimonials</h1>
<p>What riders are saying about the campus shuttle routes.</p>
</div>

<?php if (current_user_id()): ?>
<div class="form-card" style="margin-bottom:24px; max-width:600px;">
<h2>Leave a Comment</h2>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Route
<select name="route_id" required>
<option value="">-- Select a route --</option>
<?php foreach ($routes as $r): ?>
<option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['route_name']) ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Rating
<select name="rating" required>
<option value="5">&#9733;&#9733;&#9733;&#9733;&#9733; Excellent</option>
<option value="4">&#9733;&#9733;&#9733;&#9733;&#9734; Good</option>
<option value="3">&#9733;&#9733;&#9733;&#9734;&#9734; Okay</option>
<option value="2">&#9733;&#9733;&#9734;&#9734;&#9734; Poor</option>
<option value="1">&#9733;&#9734;&#9734;&#9734;&#9734; Bad</option>
</select>
</label>
<label>Comment <textarea name="comment" rows="3" required></textarea></label>
<button type="submit">Post Comment</button>
</form>
</div>
<?php else: ?>
<p><a href="login.php">Login</a> or <a href="register.php">register</a> to leave a comment.</p>
<?php endif; ?>

<?php if (empty($testimonials)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128172;</div>
<p>No comments yet. Be the first to share your experience.</p>
</div>
<?php else: ?>
<div class="testimonial-list">
<?php foreach ($testimonials as $t): ?>
<div class="testimonial-card">
<span class="badge badge-accent"><?= htmlspecialchars($t['route_name']) ?></span>
<div class="testimonial-stars"><?= str_repeat('&#9733;', $t['rating']) . str_repeat('&#9734;', 5 - $t['rating']) ?></div>
<p class="testimonial-comment"><?= nl2br(htmlspecialchars($t['comment'])) ?></p>
<div class="testimonial-meta">
<span><?= htmlspecialchars($t['user_name']) ?> &middot; <?= htmlspecialchars(date('d M Y', strtotime($t['created_at']))) ?></span>
<?php if (current_user_id() == $t['user_id']): ?>
<form action="testimonial_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this comment?');">
<input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
