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
    <div style="display: flex; gap: 8px;">
        <input type="text" id="chat-input" placeholder="Type your message..." style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
        <button id="send-btn" class="btn" style="background: #49afdb; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">Send</button>
    </div>
</div>

<script>
async function fetchMessages() {
    const res = await fetch('chat_api.php');
    const data = await res.json();
    const box = document.getElementById('chat-box');
    box.innerHTML = (data.messages || []).map(m => `
        <div style="text-align: ${m.sender === 'user' ? 'right' : 'left'}; margin-bottom: 10px;">
            <span style="background: ${m.sender === 'user' ? '#49afdb' : '#e5e7eb'}; color: ${m.sender === 'user' ? '#fff' : '#000'}; padding: 8px 14px; border-radius: 12px; display: inline-block;">
                ${m.message}
            </span>
            <small style="display:block; font-size: 0.7rem; color: #888; margin-top: 2px;">${m.time}</small>
        </div>
    `).join('');
}

document.getElementById('send-btn').addEventListener('click', async () => {
    const input = document.getElementById('chat-input');
    if (!input.value.trim()) return;

    await fetch('chat_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: input.value })
    });
    input.value = '';
    fetchMessages();
});

setInterval(fetchMessages, 3000);
fetchMessages();
</script>

<?php require 'partials/footer.php'; ?>