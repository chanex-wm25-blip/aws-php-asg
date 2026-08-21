<?php
require '../config.php';
require '../auth.php';
require_admin();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$myId = current_user_id();
$users = $conn->query('SELECT id, name, email, is_admin, created_at FROM users ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage Users';
require 'partials/header.php';
?>
<h1>Users</h1>
<p>Grant or revoke admin access, or remove an account.</p>
<p><a class="btn btn-small" href="user_create.php">+ Add Admin</a></p>
<?php if ($flashError): ?><p class="alert alert-error"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>
<table>
<tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr>
<?php foreach ($users as $u): ?>
<tr>
<td><?= htmlspecialchars($u['name']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= $u['is_admin'] ? '<span class="badge badge-neutral">Admin</span>' : 'User' ?></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($u['created_at']))) ?></td>
<td>
<?php if ((int)$u['id'] === (int)$myId): ?>
<span class="stat-label">(you)</span>
<?php else: ?>
<form action="user_toggle_admin.php" method="post" style="display:inline" onsubmit="return confirm('<?= $u['is_admin'] ? 'Remove admin access from ' : 'Grant admin access to ' ?><?= htmlspecialchars($u['name']) ?>?');">
<input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
<button type="submit" class="btn btn-secondary btn-small"><?= $u['is_admin'] ? 'Revoke Admin' : 'Make Admin' ?></button>
</form>
<form action="user_delete.php" method="post" style="display:inline" onsubmit="return confirm('Delete this user? This will PERMANENTLY delete their account together with ALL of their orders (restoring ticket inventory) and testimonials. This cannot be undone.');">
<input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
<button type="submit" class="btn-small btn-danger">Delete</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php require 'partials/footer.php'; ?>
