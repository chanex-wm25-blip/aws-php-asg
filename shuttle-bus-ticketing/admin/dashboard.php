<?php
require '../config.php';
require '../auth.php';
require '../helpers.php';
require_admin();

// 1. Analytics Queries
$result = $conn->query("SELECT COUNT(*) AS total FROM tickets");
$totalBookings = ($result && ($row = $result->fetch_assoc())) ? $row['total'] : 0;

$result = $conn->query("SELECT SUM(total_price) AS rev FROM tickets");
$totalRevenue = ($result && ($row = $result->fetch_assoc())) ? (float)$row['rev'] : 0.00;

$result = $conn->query("SELECT COUNT(*) AS today FROM tickets WHERE DATE(created_at) = CURDATE()");
$todayBookings = ($result && ($row = $result->fetch_assoc())) ? $row['today'] : 0;

$result = $conn->query("SELECT COUNT(*) AS users FROM users");
$totalUsers = ($result && ($row = $result->fetch_assoc())) ? $row['users'] : 0;

$busiestQuery = $conn->query("
    SELECT r.route_name, SUM(t.seat_quantity) AS seats_booked 
    FROM tickets t JOIN routes r ON r.id = t.route_id 
    GROUP BY t.route_id ORDER BY seats_booked DESC LIMIT 1
");
$busiestRoute = ($busiestQuery && ($row = $busiestQuery->fetch_assoc())) ? $row['route_name'] : 'None';

// 2. Data for Charts: Bookings & Revenue per Route
$result = $conn->query("
    SELECT r.route_name, COUNT(t.id) AS booking_count, COALESCE(SUM(t.total_price), 0) AS revenue
    FROM routes r LEFT JOIN tickets t ON r.id = t.route_id 
    GROUP BY r.id
");
$routeChartData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$routeLabels = array_column($routeChartData, 'route_name');
$routeCounts = array_column($routeChartData, 'booking_count');
$routeRevenues = array_column($routeChartData, 'revenue');

$pageTitle = 'Dashboard';
require 'partials/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.dash-header h1 { font-size: 1.8rem; font-weight: 700; margin: 0; }

.small-box-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.small-box { border-radius: 8px; color: #fff; position: relative; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
.small-box .inner { padding: 20px; }
.small-box .inner h3 { font-size: 2rem; font-weight: 800; margin: 0 0 8px 0; }
.small-box .inner p { margin: 0; opacity: 0.9; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
.small-box-footer { display: block; background: rgba(0, 0, 0, 0.15); color: rgba(255, 255, 255, 0.9); text-align: center; padding: 6px 0; text-decoration: none; font-size: 0.85rem; transition: background 0.2s; }
.small-box-footer:hover { background: rgba(0, 0, 0, 0.25); color: #fff; }

.bg-blue { background: #007bff; }
.bg-green { background: #28a745; }
.bg-yellow { background: #ffc107; color: #1f2d3d !important; }
.bg-yellow .small-box-footer { color: #1f2d3d; background: rgba(0, 0, 0, 0.08); }
.bg-red { background: #dc3545; }

.charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
.chart-card { background: var(--bg-card, #fff); border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid var(--border, #e5e7eb); }
.chart-card h3 { margin-top: 0; margin-bottom: 16px; font-size: 1.1rem; border-bottom: 1px solid var(--border, #eee); padding-bottom: 10px; }
</style>

<div class="dash-header">
    <h1>Dashboard</h1>
    <div>
        <span class="btn btn-secondary btn-small" style="pointer-events:none;">Overview</span>
    </div>
</div>

<!-- AdminLTE Colored Metric Tiles -->
<div class="small-box-grid">
    <div class="small-box bg-blue">
        <div class="inner">
            <h3><?= $totalBookings ?></h3>
            <p>Total Bookings</p>
        </div>
        <a href="tickets.php" class="small-box-footer">More info &rarr;</a>
    </div>
    
    <div class="small-box bg-green">
        <div class="inner">
            <h3>RM <?= number_format($totalRevenue, 2) ?></h3>
            <p>Total Revenue</p>
        </div>
        <a href="tickets.php" class="small-box-footer">More info &rarr;</a>
    </div>

    <div class="small-box bg-yellow">
        <div class="inner">
            <h3><?= $todayBookings ?></h3>
            <p>Bookings Today</p>
        </div>
        <a href="tickets.php" class="small-box-footer">More info &rarr;</a>
    </div>

    <div class="small-box bg-red">
        <div class="inner">
            <h3><?= $totalUsers ?></h3>
            <p>User Registrations</p>
        </div>
        <a href="users.php" class="small-box-footer">More info &rarr;</a>
    </div>
</div>

<!-- Side-by-Side Charts -->
<div class="charts-grid">
    <div class="chart-card">
        <h3>Doughnut Chart (Route Popularity)</h3>
        <div style="max-height: 280px; display:flex; justify-content:center;">
            <canvas id="doughnutChart"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3>Bar Chart (Revenue per Route)</h3>
        <div style="max-height: 280px;">
            <canvas id="barChart"></canvas>
        </div>
    </div>
</div>

<script>
// Doughnut Chart Configuration
new Chart(document.getElementById('doughnutChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($routeLabels) ?>,
        datasets: [{
            data: <?= json_encode($routeCounts) ?>,
            backgroundColor: ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'right' } }
    }
});

// Bar Chart Configuration
new Chart(document.getElementById('barChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($routeLabels) ?>,
        datasets: [{
            label: 'Revenue (RM)',
            data: <?= json_encode($routeRevenues) ?>,
            backgroundColor: '#00a65a',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php require 'partials/footer.php'; ?>