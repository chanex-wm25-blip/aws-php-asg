```php
<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

// List all non-admin users
$users = $conn->query("
    SELECT id, name, email
    FROM users
    WHERE is_admin = 0
    ORDER BY name ASC
")->fetch_all(MYSQLI_ASSOC);

// Get selected user
$activeUserId = (int)($_GET['user_id'] ?? 0);

// If no user is selected, select the first user
if ($activeUserId <= 0 && !empty($users)) {
    $activeUserId = (int)$users[0]['id'];
}

// Make sure selected user is actually a normal user
if ($activeUserId > 0) {
    $validUser = false;

    foreach ($users as $u) {
        if ((int)$u['id'] === $activeUserId) {
            $validUser = true;
            break;
        }
    }

    if (!$validUser) {
        $activeUserId = !empty($users) ? (int)$users[0]['id'] : 0;
    }
}

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
                    <a
                        href="admin_chat.php?user_id=<?= (int)$u['id'] ?>"
                        class="btn btn-small <?= $activeUserId === (int)$u['id'] ? '' : 'btn-secondary' ?>"
                        style="display: block; text-align: left;"
                    >
                        <?= htmlspecialchars($u['name']) ?>
                    </a>
                </li>

            <?php endforeach; ?>

            <?php if (empty($users)): ?>
                <p>No users found.</p>
            <?php endif; ?>

        </ul>
    </div>


    <!-- Chat Message Box -->
    <div class="card" style="flex: 1; padding: 20px;">

        <?php if ($activeUserId > 0): ?>

            <div
                id="chat-box"
                style="
                    height: 350px;
                    overflow-y: auto;
                    border: 1px solid var(--border);
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 12px;
                "
            ></div>

            <form
                id="chat-form"
                style="display: flex; gap: 8px;"
            >

                <input
                    type="text"
                    id="chat-input"
                    name="message"
                    placeholder="Type a response..."
                    autocomplete="off"
                    required
                    style="
                        flex: 1;
                        color: #191c22;
                        pointer-events: auto;
                    "
                >

                <button
                    type="submit"
                    id="send-btn"
                    class="btn"
                >
                    Send
                </button>

            </form>

        <?php else: ?>

            <p>No users available for support.</p>

        <?php endif; ?>

    </div>

</div>


<script>

const activeUserId = <?= $activeUserId ?>;


function escapeHtml(text) {

    const div = document.createElement('div');

    div.textContent = text;

    return div.innerHTML;
}


async function fetchMessages() {

    if (!activeUserId) return;

    try {

        const res = await fetch(
            `../chat_api.php?user_id=${activeUserId}`
        );

        if (!res.ok) {
            throw new Error('Failed to load messages');
        }

        const data = await res.json();

        const box = document.getElementById('chat-box');

        box.innerHTML = (data.messages || []).map(m => `

            <div
                style="
                    text-align: ${m.sender === 'admin' ? 'right' : 'left'};
                    margin-bottom: 10px;
                "
            >

                <span
                    style="
                        background: ${m.sender === 'admin' ? '#0066ff' : '#e5e7eb'};
                        color: ${m.sender === 'admin' ? '#fff' : '#000'};
                        padding: 8px 14px;
                        border-radius: 12px;
                        display: inline-block;
                    "
                >
                    ${escapeHtml(m.message)}
                </span>

                <small
                    style="
                        display: block;
                        font-size: 0.7rem;
                        color: #888;
                        margin-top: 2px;
                    "
                >
                    ${escapeHtml(m.time)}
                </small>

            </div>

        `).join('');

        box.scrollTop = box.scrollHeight;

    } catch (error) {

        console.error('Failed to load chat:', error);

    }
}


document
    .getElementById('chat-form')
    ?.addEventListener('submit', async (event) => {

        event.preventDefault();

        const input = document.getElementById('chat-input');

        const message = input.value.trim();

        if (!message) return;


        try {

            const res = await fetch('../chat_api.php', {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/json'
                },

                body: JSON.stringify({
                    user_id: activeUserId,
                    message: message
                })

            });


            if (!res.ok) {

                console.error(
                    'Chat message failed:',
                    await res.text()
                );

                return;
            }


            input.value = '';

            fetchMessages();

        } catch (error) {

            console.error(
                'Failed to send message:',
                error
            );

        }

    });


if (activeUserId) {

    fetchMessages();

    setInterval(fetchMessages, 3000);

}

</script>


<?php require 'partials/footer.php'; ?>
```
