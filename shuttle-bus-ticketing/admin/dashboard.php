<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

// 1. Analytics Queries
$result = $conn->query("SELECT COUNT(*) AS total FROM tickets");
$totalBookings = ($result && ($row = $result->fetch_assoc())) ? $row['total'] : 0;

$result = $conn->query("SELECT SUM(total_price) AS rev FROM tickets");
$totalRevenue = ($result && ($row = $result->fetch_assoc())) ? $row['rev'] : 0.00;

$result = $conn->query("SELECT COUNT(*) AS today FROM tickets WHERE DATE(created_at) = CURDATE()");
$todayBookings = ($result && ($row = $result->fetch_assoc())) ? $row['today'] : 0;

$busiestQuery  = $conn->query("
    SELECT r.route_name, SUM(t.seat_quantity) AS seats_booked 
    FROM tickets t JOIN routes r ON r.id = t.route_id 
    GROUP BY t.route_id ORDER BY seats_booked DESC LIMIT 1
");
$busiestRoute = ($busiestQuery && ($row = $busiestQuery->fetch_assoc())) ? $row['route_name'] : 'None';

// 2. Data for Chart: Bookings per Route
$result = $conn->query("
    SELECT r.route_name, COUNT(t.id) AS booking_count 
    FROM routes r LEFT JOIN tickets t ON r.id = t.route_id 
    GROUP BY r.id
");
$routeChartData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$routeLabels = array_column($routeChartData, 'route_name');
$routeCounts = array_column($routeChartData, 'booking_count');

$pageTitle = 'Admin Dashboard';
require 'partials/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<h1>Admin Analytics & Dashboard</h1>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0;">
    <div class="card" style="padding: 16px; text-align: center;">
        <span style="font-size: 0.85rem; color: var(--text-muted);">Total Bookings</span>
        <h2 style="margin: 8px 0; font-size: 1.8rem;"><?= $totalBookings ?></h2>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <span style="font-size: 0.85rem; color: var(--text-muted);">Total Revenue</span>
        <h2 style="margin: 8px 0; font-size: 1.8rem; color: #10b981;">RM <?= number_format($totalRevenue, 2) ?></h2>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <span style="font-size: 0.85rem; color: var(--text-muted);">Busiest Route</span>
        <h3 style="margin: 8px 0; font-size: 1.1rem;"><?= htmlspecialchars($busiestRoute) ?></h3>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <span style="font-size: 0.85rem; color: var(--text-muted);">Bookings Today</span>
        <h2 style="margin: 8px 0; font-size: 1.8rem; color: #0066ff;"><?= $todayBookings ?></h2>
    </div>
</div>

<div class="card" style="max-width: 650px; padding: 20px; margin-top: 24px;">
    <h3>Bookings Breakdown per Route</h3>
    <canvas id="routeChart"></canvas>
</div>

<script>
const ctx = document.getElementById('routeChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($routeLabels) ?>,
        datasets: [{
            label: 'Total Bookings',
            data: <?= json_encode($routeCounts) ?>,
            backgroundColor: '#0066ff',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php require 'partials/footer.php'; ?>