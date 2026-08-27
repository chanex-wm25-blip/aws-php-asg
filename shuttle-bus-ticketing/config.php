<?php
// ============================================================================
// Load .env (optional)
// ============================================================================
// Lets DB_HOST/DB_USER/... and AWS_S3_BUCKET/... etc. below be set from a
// plain KEY=VALUE text file in this folder instead of Apache SetEnv
// directives - copy .env.example to .env and fill in real values there.
// .env itself is git-ignored (see .gitignore) so real credentials never get
// committed to this public repo - only .env.example, with blank placeholder
// values, is tracked. A value already set as a real environment variable
// (e.g. via Apache SetEnv) takes priority over .env and is left untouched.
// No web server restart needed - config.php re-reads .env on every request.
// ============================================================================
$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

// Use legacy-style error reporting: mysqli functions return false on
// failure (e.g. a foreign-key violation) instead of throwing an exception,
// so ordinary "if (!$stmt->execute())" checks work as expected below.
mysqli_report(MYSQLI_REPORT_OFF);

// TAR UMT is in Malaysia (UTC+8), but this server's OS clock defaults to UTC
// (true both locally in Docker and on a stock EC2 instance) - without this,
// every date()/time() call here (departure date validation, "today"
// defaults) runs 8 hours behind real local time.
date_default_timezone_set('Asia/Kuala_Lumpur');

// ============================================================================
// Database connection
// ============================================================================
// LOCAL / DOCKER (current default below): connects to a MySQL server on this
// same machine using the credentials below.
//
// AWS RDS (Phase 3 of the assignment): once you provision an Amazon RDS
// MySQL instance, point this app at it. Recommended: copy .env.example to
// .env in this folder (see the loader above) and set these there - or, if
// you prefer, set them as Apache SetEnv directives instead. Either way this
// file never needs to change between local, EC2, and RDS:
//
//   DB_HOST = your-db-identifier.xxxxxxxxxxxx.us-east-1.rds.amazonaws.com
//   DB_USER = admin              (the master username you set when creating the RDS instance)
//   DB_PASS = ********           (the master password you set when creating the RDS instance)
//   DB_NAME = shuttle_bus_db
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
$dbname = getenv('DB_NAME') ?: 'shuttle_bus_db';

// @-suppressed: even with MYSQLI_REPORT_OFF (no exception), a failed
// connection still emits a PHP-level warning straight into the response
// body. With display_errors on, that warning is output before the
// connect_error check below runs, which flushes headers with the default
// 200 status - making the http_response_code(500) call too late to matter.
// Suppressing it here keeps the failure check as the single source of truth
// for what gets reported.
$conn = @new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    // Signal unhealthy to an ALB health check (or anything else probing this
    // page) instead of silently returning 200 OK with an error message body -
    // otherwise a target group would keep routing real traffic to an
    // instance that can't reach its database.
    http_response_code(500);
    die('Database connection failed: ' . $conn->connect_error);
}

// Keep MySQL's NOW()/CURRENT_TIMESTAMP in step with the PHP timezone above -
// otherwise created_at/returned_at etc. would still be recorded 8 hours off.
$conn->query("SET time_zone = '+08:00'");

// ============================================================================
// Photo storage (S3) - optional
// ============================================================================
// LOCAL / DOCKER (current default below, all blank): uploaded photos are
// saved to this app's local uploads/ folder, exactly as before. Nothing to
// configure for local development.
//
// Once there's more than one EC2 instance behind an ALB/ASG, local disk
// uploads only exist on whichever instance handled that request - the next
// instance (or a newly launched ASG instance) won't have the file, so the
// image breaks. Uploading to S3 instead fixes this, since every instance
// reads/writes the same bucket. This is already wired up (see helpers.php)
// - to turn it on:
//   1. Create an S3 bucket and a bucket policy allowing public s3:GetObject
//      on it (or put CloudFront in front of it instead).
//   2. Set AWS_S3_BUCKET / AWS_S3_REGION in your .env file (copy
//      .env.example to .env - see the loader at the top of this file).
//   3. Give this app permission to write to that bucket, either:
//      a) Attach an IAM role to your EC2 instance/launch template with an
//         s3:PutObject + s3:DeleteObject permission scoped to the bucket -
//         credentials are then fetched automatically from the instance's
//         own metadata service (IMDSv2), nothing to set below. Tried first.
//      b) On an AWS Academy Learner Lab where you can't attach or inspect
//         IAM roles yourself, use the temporary Access Key ID / Secret
//         Access Key / Session Token shown in the lab's "AWS Details"
//         panel instead - set AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY /
//         AWS_SESSION_TOKEN in your .env file (never commit real values -
//         .env is git-ignored, only .env.example is tracked). These expire
//         and rotate periodically in a Learner Lab; if uploads that were
//         working suddenly start failing, that's almost always why - grab
//         fresh values from "AWS Details" and update .env (no restart
//         needed).
// ============================================================================
define('AWS_S3_BUCKET', getenv('AWS_S3_BUCKET') ?: (getenv('S3_BUCKET') ?: 'shuttlebusticketing'));
define('AWS_S3_REGION', getenv('AWS_S3_REGION') ?: (getenv('AWS_REGION') ?: 'us-east-1'));
define('AWS_ACCESS_KEY_ID', getenv('AWS_ACCESS_KEY_ID') ?: '');
define('AWS_SECRET_ACCESS_KEY', getenv('AWS_SECRET_ACCESS_KEY') ?: '');
define('AWS_SESSION_TOKEN', getenv('AWS_SESSION_TOKEN') ?: '');
