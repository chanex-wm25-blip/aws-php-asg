<?php
require '../config.php';
require '../auth.php';
require_admin();

$testimonials = $conn->query('
    SELECT t.id, t.comment, t.rating, t.created_at, u.name AS user_name, e.event_name
    FROM testimonials t
    JOIN users u ON u.id = t.user_id
    JOIN events e ON e.id = t.event_id
    ORDER BY t.created_at DESC
')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Testimonials';
require 'partials/header.php';
?>
<h1>Testimonials</h1>
<?php if (empty($testimonials)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128172;</div>
<p>No comments yet.</p>
</div>
<?php else: ?>
<div class="testimonial-list">
<?php foreach ($testimonials as $t): ?>
<div class="testimonial-card">
<span class="badge badge-accent"><?= htmlspecialchars($t['event_name']) ?></span>
<div class="testimonial-stars"><?= str_repeat('&#9733;', $t['rating']) . str_repeat('&#9734;', 5 - $t['rating']) ?></div>
<p class="testimonial-comment"><?= nl2br(htmlspecialchars($t['comment'])) ?></p>
<div class="testimonial-meta">
<span><?= htmlspecialchars($t['user_name']) ?> &middot; <?= htmlspecialchars(date('d M Y', strtotime($t['created_at']))) ?></span>
<form action="testimonial_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this comment?');">
<input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
