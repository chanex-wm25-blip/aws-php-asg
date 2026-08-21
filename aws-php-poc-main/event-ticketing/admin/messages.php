<?php
require '../config.php';
require '../auth.php';
require_admin();

$messages = $conn->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Contact Messages';
require 'partials/header.php';
?>
<h1>Contact Messages</h1>
<?php if (empty($messages)): ?>
<div class="empty-state">
<div class="empty-state-icon">&#128231;</div>
<p>No messages yet.</p>
</div>
<?php else: ?>
<div class="testimonial-list">
<?php foreach ($messages as $m): ?>
<div class="testimonial-card">
<div class="testimonial-meta">
<span><strong><?= htmlspecialchars($m['subject']) ?></strong></span>
<span><?= htmlspecialchars(date('d M Y, g:i a', strtotime($m['created_at']))) ?></span>
</div>
<p class="testimonial-comment"><?= nl2br(htmlspecialchars($m['message'])) ?></p>
<div class="testimonial-meta">
<span><?= htmlspecialchars($m['name']) ?> &middot; <?= htmlspecialchars($m['email']) ?></span>
<form action="message_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this message?');">
<input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require 'partials/footer.php'; ?>
