<?php
// Use legacy-style error reporting: mysqli functions return false on
// failure (e.g. a foreign-key violation) instead of throwing an exception,
// so ordinary "if (!$stmt->execute())" checks work as expected below.
mysqli_report(MYSQLI_REPORT_OFF);

// TAR UMT is in Malaysia (UTC+8), but this server's OS clock defaults to UTC
// (true both locally in Docker and on a stock EC2 instance) - without this,
// every date()/time() call here (booking date validation, "today" defaults,
// past-slot checks) runs 8 hours behind real local time.
date_default_timezone_set('Asia/Kuala_Lumpur');

// ============================================================================
// Database connection
// ============================================================================
// LOCAL / DOCKER (current default below): connects to a MySQL server on this
// same machine using the credentials below.
//
// AWS RDS (Phase 3 of the assignment): once you provision an Amazon RDS
// MySQL instance, point this app at it. Recommended: set these as
// environment variables on your EC2 instance / Apache vhost so this file
// never needs to change between local, EC2, and RDS:
//
//   DB_HOST = your-db-identifier.xxxxxxxxxxxx.us-east-1.rds.amazonaws.com
//   DB_USER = admin              (the master username you set when creating the RDS instance)
//   DB_PASS = ********           (the master password you set when creating the RDS instance)
//   DB_NAME = event_ticketing_db
//
// Or, to hardcode it instead of using environment variables, replace the
// fallback values below directly, e.g.:
//   $host = 'your-db-identifier.xxxxxxxxxxxx.us-east-1.rds.amazonaws.com';
//   $user = 'admin';
//   $pass = 'your-rds-master-password';
// ============================================================================
$host   = getenv('DB_HOST') ?: 'localhost';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'event_ticketing_db';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Keep MySQL's NOW()/CURRENT_TIMESTAMP in step with the PHP timezone above -
// otherwise created_at/returned_at etc. would still be recorded 8 hours off.
$conn->query("SET time_zone = '+08:00'");
