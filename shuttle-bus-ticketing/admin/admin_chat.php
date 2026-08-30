<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

// Get list of users who have sent support messages
$users = $conn->query("
    SELECT DISTINCT u.id, u.name, u.email 
    FROM chat_messages cm 
    JOIN users u ON u.id = cm.user_id 
    ORDER BY cm.id DESC
")->fetch_all(MYSQLI_ASSOC);

$activeUserId = (int)($_GET['user_id'] ?? ($users[0]['id'] ?? 0));

$pageTitle = 'Admin Live Support';
require 'partials/header.php';
?>
<h1>Support Conversations</h1>

<div style="display: flex; gap: 20px; margin-top: 20px;">
    <!-- User List Sidebar -->
    <div class="card" style="width: 250px; padding: 10px;">
        <h3>Users</h3>
        <ul style="list-style: none; padding: 0;">
            <?php foreach ($users as $u): ?>
                <li style="margin-bottom: 8px;">
                    <a href="chat.php?user_id=<?= $u['id'] ?>" class="btn btn-small <?= $activeUserId === $u['id'] ? '' : 'btn-secondary' ?>" style="display: block; text-align: left;">
                        <?= htmlspecialchars($u['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($users)): ?><p>No active chats.</p><?php endif; ?>
        </ul>
    </div>

    <!-- Chat Message Box -->
    <div class="card" style="flex: 1; padding: 20px;">
        <?php if ($activeUserId > 0): ?>
            <div id="chat-box" style="height: 350px; overflow-y: auto; border: 1px solid var(--border); padding: 12px; border-radius: 8px; margin-bottom: 12px;"></div>
            <div style="display: flex; gap: 8px;">
                <input type="text" id="chat-input" placeholder="Type a response..." style="flex: 1;">
                <button id="send-btn" class="btn">Send</button>
            </div>
        <?php else: ?>
            <p>Select a user to start chatting.</p>
        <?php endif; ?>
    </div>
</div>

<script>
const activeUserId = <?= $activeUserId ?>;

async function fetchMessages() {
    if (!activeUserId) return;
    const res = await fetch(`../chat_api.php?user_id=${activeUserId}`);
    const data = await res.json();
    const box = document.getElementById('chat-box');
    box.innerHTML = data.messages.map(m => `
        <div style="text-align: ${m.sender === 'admin' ? 'right' : 'left'}; margin-bottom: 10px;">
            <span style="background: ${m.sender === 'admin' ? '#0066ff' : '#e5e7eb'}; color: ${m.sender === 'admin' ? '#fff' : '#000'}; padding: 8px 14px; border-radius: 12px; display: inline-block;">
                ${m.message}
            </span>
            <small style="display:block; font-size: 0.7rem; color: #888; margin-top: 2px;">${m.time}</small>
        </div>
    `).join('');
}

document.getElementById('send-btn')?.addEventListener('click', async () => {
    const input = document.getElementById('chat-input');
    if (!input.value.trim()) return;

    await fetch('../chat_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: activeUserId, message: input.value })
    });
    input.value = '';
    fetchMessages();
});

if (activeUserId) {
    setInterval(fetchMessages, 3000);
    fetchMessages();
}
</script>

<?php require 'partials/footer.php'; ?>