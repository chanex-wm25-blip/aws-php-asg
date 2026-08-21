<?php
// ALB target group health check. Does its own lightweight, timeout-bounded DB
// connectivity check instead of requiring config.php, so a hanging/unreachable
// database fails fast within the target group's health check timeout (5s,
// see infra/modules/alb) rather than hanging the request or relying on
// config.php's die(). This is a deliberate fail-fast choice: this app is a
// booking/ticketing system that's useless without a database anyway, so an
// instance (and likely the whole fleet, since they share one RDS instance)
// should be reported unhealthy rather than staying "up" while broken.
mysqli_report(MYSQLI_REPORT_OFF);

$host   = getenv('DB_HOST') ?: 'localhost';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'event_ticketing_db';

$mysqli = mysqli_init();
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
$connected = @$mysqli->real_connect($host, $user, $pass, $dbname);

if (!$connected) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'db unreachable';
    exit;
}

$mysqli->close();

http_response_code(200);
header('Content-Type: text/plain');
echo 'ok';
