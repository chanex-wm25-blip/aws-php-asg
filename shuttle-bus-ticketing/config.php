<?php
// Use legacy-style error reporting: mysqli functions return false on
// failure (e.g. a foreign-key violation) instead of throwing an exception.
mysqli_report(MYSQLI_REPORT_OFF);

// Set local timezone to Kuala Lumpur
date_default_timezone_set('Asia/Kuala_Lumpur');

// Import helper functions (includes get_db_secret)
require_once __DIR__ . '/helpers.php';

// ============================================================================
// Database connection using AWS Secrets Manager
// ============================================================================
$secret = get_db_secret();

$host   = $secret['DB_HOST']   ?? (getenv('DB_HOST')   ?: 'localhost');
$user   = $secret['DB_USER']   ?? (getenv('DB_USER')   ?: 'root');
$pass   = $secret['DB_PASS']   ?? (getenv('DB_PASS')   ?: '');
$dbname = $secret['DB_NAME']   ?? (getenv('DB_NAME')   ?: 'shuttle_bus_db');

$conn = @new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    error_log('Database connection failed: ' . $conn->connect_error);
    die('Database connection failed. Check the server error log.');
}

// Keep MySQL's NOW()/CURRENT_TIMESTAMP aligned with PHP timezone
$conn->query("SET time_zone = '+08:00'");

// Photo storage definitions (S3)
define('AWS_S3_BUCKET', 'shuttlebusticketing');
define('AWS_S3_REGION', getenv('AWS_S3_REGION') ?: (getenv('AWS_REGION') ?: 'us-east-1'));
define('AWS_ACCESS_KEY_ID', getenv('AWS_ACCESS_KEY_ID') ?: '');
define('AWS_SECRET_ACCESS_KEY', getenv('AWS_SECRET_ACCESS_KEY') ?: '');
define('AWS_SESSION_TOKEN', getenv('AWS_SESSION_TOKEN') ?: '');
define('SNS_TOPIC_ARN', getenv('SNS_TOPIC_ARN') ?: 'arn:aws:sns:us-east-1:150194514143:shuttle-bus-alerts');