<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$uploadDir = __DIR__ . '/../uploads';

$stmt = $conn->prepare('SELECT * FROM events WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    die('Event not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name    = trim($_POST['event_name']);
    $event_date    = $_POST['event_date'];
    $venue         = trim($_POST['venue']);
    $ticket_price  = (float)$_POST['ticket_price'];
    $total_tickets = (int)$_POST['total_tickets'];

    [$newImageUrl, $uploadError] = handle_image_upload($_FILES['image'] ?? null, $uploadDir, 'event');
    $image_url = $newImageUrl ?? $event['image_url'];

    if ($event['has_seating']) {
        $total_tickets = (int)$event['total_tickets'];
    }

    if ($event_name === '' || $event_date === '' || $venue === '' || $total_tickets < $event['tickets_sold']) {
        $error = 'All fields are required and total tickets cannot be less than tickets already sold (' . (int)$event['tickets_sold'] . ').';
        $event = array_merge($event, compact('event_name', 'event_date', 'venue', 'ticket_price', 'total_tickets', 'image_url'));
    } elseif ($event_date < date('Y-m-d')) {
        $error = 'Event date cannot be in the past.';
        $event = array_merge($event, compact('event_name', 'event_date', 'venue', 'ticket_price', 'total_tickets', 'image_url'));
    } elseif ($uploadError) {
        $error = $uploadError;
    } else {
        if ($newImageUrl) {
            delete_image_file($event['image_url'], $uploadDir);
        }

        $stmt = $conn->prepare('UPDATE events SET event_name=?, event_date=?, venue=?, ticket_price=?, total_tickets=?, image_url=? WHERE id=?');
        $stmt->bind_param('sssdisi', $event_name, $event_date, $venue, $ticket_price, $total_tickets, $image_url, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: events.php');
        exit;
    }
}

$pageTitle = 'Edit Event';
require 'partials/header.php';
?>
<div class="form-card">
<h1>Edit Event</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
<label>Event Name <input type="text" name="event_name" value="<?= htmlspecialchars($event['event_name']) ?>" required></label>
<label>Date <input type="date" name="event_date" value="<?= htmlspecialchars($event['event_date']) ?>" min="<?= date('Y-m-d') ?>" required></label>
<label>Venue <input type="text" name="venue" value="<?= htmlspecialchars($event['venue']) ?>" required></label>
<label>Ticket Price (RM) <input type="number" name="ticket_price" step="0.01" min="0" value="<?= htmlspecialchars($event['ticket_price']) ?>" required></label>
<?php if ($event['has_seating']): ?>
<label>Seat Map <input type="text" value="<?= (int)$event['seat_rows'] ?> rows &times; <?= (int)$event['seats_per_row'] ?> seats = <?= (int)$event['total_tickets'] ?> total" disabled></label>
<p class="form-hint">This event uses assigned seating. The seat map cannot be changed after creation.</p>
<?php else: ?>
<label>Total Tickets <input type="number" name="total_tickets" min="1" value="<?= (int)$event['total_tickets'] ?>" required></label>
<?php endif; ?>
<label>Current Photo
<img class="table-thumb" style="width:96px;height:96px;" src="<?= htmlspecialchars(entity_image_url($event)) ?>" alt="<?= htmlspecialchars($event['event_name']) ?>">
</label>
<label>Replace Photo <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<p>Tickets sold so far: <?= (int)$event['tickets_sold'] ?></p>
<button type="submit">Update Event</button>
</form>
<p><a class="btn btn-secondary btn-small" href="events.php">Back to events</a></p>
</div>
<?php require 'partials/footer.php'; ?>
