<?php
require 'config.php';
require 'auth.php';

$pageTitle = 'About';
require 'partials/header.php';
?>
<div class="page-header">
<h1>About This Platform</h1>
<p>What Student Society Event Ticketing is, and how it works.</p>
</div>

<section>
<h2>Our Mission</h2>
<p>Student Society Event Ticketing gives every registered campus society a single place to sell
tickets for their events — cultural nights, band battles, charity dinners and more — without
relying on manual sign-up sheets or spreadsheets that run out of seats.</p>
</section>

<section>
<h2>How It Works</h2>
<div class="card-grid">
<div class="card">
<div class="card-icon">&#128197;</div>
<h3>1. Browse Events</h3>
<p>See every upcoming event with its date, venue and live ticket availability.</p>
</div>
<div class="card">
<div class="card-icon">&#127903;</div>
<h3>2. Buy Tickets</h3>
<p>Choose how many tickets you need — the system checks availability in real time.</p>
</div>
<div class="card">
<div class="card-icon">&#9989;</div>
<h3>3. Manage Orders</h3>
<p>Change the quantity or cancel an order anytime from your homepage.</p>
</div>
</div>
</section>

<section>
<h2>Who Runs This</h2>
<p>This platform is a sample project built for the AMIT3253 Cloud Computing for Business
capstone assignment, demonstrating a simple booking/ticketing system deployed on AWS.</p>
</section>
<?php require 'partials/footer.php'; ?>
