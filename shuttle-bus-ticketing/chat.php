<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$pageTitle = 'Live Support';
require 'partials/header.php';
?>

<div class="card" style="max-width: 600px; margin: 30px auto; padding: 20px;">
    <h2>Live Support Chat</h2>
    <div id="chat-box" style="height: 350px; overflow-y: auto; border: 1px solid #e5e7eb; padding: 12px; border-radius: 8px; margin: 15px 0; background: #fafafa;"></div>
    <div style="display: flex; gap: 10px; align-items: center; width: 100%; box-sizing: border-box;">
        <input type="text" id="chat-input" placeholder="Type your message..." style="flex: 1 1 auto; min-width: 0; width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; outline: none; background: #fff; height: 45px; box-sizing: border-box; display: block;">
        <button id="send-btn" class="btn" style="background: #49afdb; color: white; padding: 0 24px; height: 45px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap; flex-shrink: 0;">Send</button>
    </div>
</div>

<script>
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function fetchMessages() {
    try {
        const res = await fetch('chat_api.php');
        const data = await res.json();
        const box = document.getElementById('chat-box');
        box.innerHTML = (data.messages || []).map(m => `
            <div style="text-align: ${m.sender === 'user' ? 'right' : 'left'}; margin-bottom: 10px;">
                <span style="background: ${m.sender === 'user' ? '#49afdb' : '#e5e7eb'}; color: ${m.sender === 'user' ? '#fff' : '#000'}; padding: 8px 14px; border-radius: 12px; display: inline-block;">
                    ${escapeHtml(m.message)}
                </span>
                <small style="display:block; font-size: 0.7rem; color: #888; margin-top: 2px;">${escapeHtml(m.time)}</small>
            </div>
        `).join('');
        box.scrollTop = box.scrollHeight;
    } catch (err) {
        console.error('Failed to load chat messages:', err);
    }
}

document.getElementById('send-btn').addEventListener('click', async () => {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';
    await fetch('chat_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg })
    });
    fetchMessages();
});

// Allow pressing Enter key to send message
document.getElementById('chat-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        document.getElementById('send-btn').click();
    }
});

setInterval(fetchMessages, 3000);
fetchMessages();
</script>

<?php require 'partials/footer.php'; ?>