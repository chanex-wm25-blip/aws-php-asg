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

$configuredHost = getenv('DB_HOST') ?: '';
$isLocalXampp = PHP_OS_FAMILY === 'Windows' && $configuredHost === '';
$host   = $secret['DB_HOST'] ?? $secret['host'] ?? ($configuredHost ?: ($isLocalXampp ? '127.0.0.1' : ''));
$port   = (int)($secret['DB_PORT'] ?? $secret['port'] ?? (getenv('DB_PORT') ?: 3306));
$user   = $secret['DB_USER'] ?? $secret['username'] ?? (getenv('DB_USER') ?: 'root');
$pass   = $secret['DB_PASS'] ?? $secret['password'] ?? (getenv('DB_PASS') ?: '');
$dbname = $secret['DB_NAME'] ?? $secret['dbname'] ?? (getenv('DB_NAME') ?: 'shuttle_bus_db');

if ($host === '') {
    http_response_code(500);
    error_log('Database connection configuration is missing DB_HOST. Check Secrets Manager and Apache environment variables.');
    die('Database connection is not configured. Check the server error log.');
}

$conn = @new mysqli($host, $user, $pass, $dbname, $port);
if ($conn->connect_error) {
    http_response_code(500);
    error_log(sprintf('Database connection failed: host=%s port=%d database=%s error=%s', $host, $port, $dbname, $conn->connect_error));
    die('Database connection failed. Check the server error log.');
}

// Keep MySQL's NOW()/CURRENT_TIMESTAMP aligned with PHP timezone
$conn->query("SET time_zone = '+08:00'");

// Photo storage definitions (S3)
define('AWS_S3_BUCKET', getenv('AWS_S3_BUCKET') ?: '');
define('AWS_S3_REGION', getenv('AWS_S3_REGION') ?: (getenv('AWS_REGION') ?: 'us-east-1'));
define('AWS_ACCESS_KEY_ID', getenv('AWS_ACCESS_KEY_ID') ?: '');
define('AWS_SECRET_ACCESS_KEY', getenv('AWS_SECRET_ACCESS_KEY') ?: '');
define('AWS_SESSION_TOKEN', getenv('AWS_SESSION_TOKEN') ?: '');
define('SNS_TOPIC_ARN', getenv('SNS_TOPIC_ARN') ?: '');