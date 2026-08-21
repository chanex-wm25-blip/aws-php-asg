<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';

$events = $conn->query('SELECT *, (total_tickets - tickets_sold) AS remaining FROM events ORDER BY event_date')->fetch_all(MYSQLI_ASSOC);

$totalEvents = count($events);
$totalRemaining = array_sum(array_column($events, 'remaining'));

$pageTitle = 'All Events';
require 'partials/header.php';
?>
<div class="page-header">
<h1>All Events</h1>
<p>Every upcoming society event, with live ticket availability.</p>
</div>

<section>
<div class="card-grid">
<div class="card"><h3><?= (int)$totalEvents ?></h3><p>Upcoming events</p></div>
<div class="card"><h3><?= (int)$totalRemaining ?></h3><p>Tickets still available</p></div>
</div>
</section>

<?php foreach ($events as $e): ?>
<section>
<div class="card" style="max-width:720px;">
<img class="card-thumb" src="<?= htmlspecialchars(entity_image_url($e)) ?>" alt="<?= htmlspecialchars($e['event_name']) ?>" loading="lazy">
<span class="badge badge-accent"><?= htmlspecialchars(date('d M Y', strtotime($e['event_date']))) ?></span>
<h3><?= htmlspecialchars($e['event_name']) ?></h3>
<p>&#128205; <?= htmlspecialchars($e['venue']) ?></p>
<p>RM<?= number_format($e['ticket_price'], 2) ?> per ticket &middot; <?= (int)$e['remaining'] ?> / <?= (int)$e['total_tickets'] ?> tickets remaining</p>
<?php if (current_user_id()): ?>
<a class="btn" href="create.php?event_id=<?= (int)$e['id'] ?>">Buy Tickets</a>
<?php else: ?>
<a class="btn" href="login.php">Login to Buy</a>
<?php endif; ?>
</div>
</section>
<?php endforeach; ?>
<?php require 'partials/footer.php'; ?>
