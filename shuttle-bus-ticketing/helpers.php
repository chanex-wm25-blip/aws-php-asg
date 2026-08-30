<?php
// TAR UMT's faculties and centres, used to populate the Faculty dropdown on
// registration and the account page instead of a free-text field.
function tarumt_faculties() {
    return [
        'Faculty of Accountancy, Finance and Business',
        'Faculty of Applied Sciences',
        'Faculty of Computing and Information Technology',
        'Faculty of Built Environment',
        'Faculty of Engineering and Technology',
        'Faculty of Communication and Creative Industries',
        'Faculty of Social Science and Humanities',
        'Centre for Pre-University Studies',
        'Centre for Postgraduate Studies and Research',
        'Centre for Continuing and Professional Education',
        'Centre for Business Incubation and Entrepreneurial Ventures',
        'SME Centre',
        'Student Career Development Centre',
        'Institute of Social Economic Research (ISER)',
    ];
}

// True if a route's departure time (e.g. "08:00") on the given date has
// already passed relative to now.
function is_departure_in_past($date, $departureTime) {
    $depart = strtotime($date . ' ' . $departureTime);
    return $depart !== false && $depart < time();
}

// Falls back to a neutral placeholder until an admin uploads a real photo.
function entity_image_url($row) {
    if (!empty($row['image_url'])) {
        $url = $row['image_url'];

        // 1. If it's already a full http/https URL, return as-is
        if (str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) {
            return $url;
        }

        // 2. If S3 bucket is defined, construct full S3 URL with encoded spaces
        if (defined('AWS_S3_BUCKET') && AWS_S3_BUCKET !== '') {
            $relative = ltrim($url, '/');
            $s3Path = implode('/', array_map('rawurlencode', explode('/', $relative)));
            return 'https://' . AWS_S3_BUCKET . '.s3.' . AWS_S3_REGION . '.amazonaws.com/' . $s3Path;
        }

        // 3. Fallback for local disk development
        $relative = ltrim($url, '/');
        $prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '';
        $path = __DIR__ . '/' . $relative;
        $version = is_file($path) ? '?v=' . filemtime($path) : '';

        return $prefix . $relative . $version;
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300">'
         . '<rect width="100%" height="100%" fill="#e1e5eb"/>'
         . '<text x="50%" y="50%" font-size="18" fill="#626a76" text-anchor="middle" dy=".3em">No photo yet</text>'
         . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// Validates an uploaded photo, then stores it either on S3 or local disk.
function handle_image_upload($file, $uploadDir, $prefix = 'photo') {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Image upload failed. Please try again.'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return [null, 'Image must be smaller than 5MB.'];
    }

    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return [null, 'The uploaded file is not a valid image.'];
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowedMimes[$imageInfo['mime']])) {
        return [null, 'Only JPG, PNG, GIF or WEBP images are allowed.'];
    }

    $filename = uniqid($prefix . '_', true) . '.' . $allowedMimes[$imageInfo['mime']];

    // For this assignment, uploaded route images must live in S3 under an
    // uploads/ prefix so every EC2 instance behind the ALB can read them.
    if (defined('AWS_S3_BUCKET') && AWS_S3_BUCKET !== '') {
        $objectKey = 'uploads/' . $filename;
        [$s3Url, $s3Error] = s3_put_object($objectKey, file_get_contents($file['tmp_name']), $imageInfo['mime']);
        if ($s3Url === null) {
            return [null, $s3Error ?: 'S3 upload failed.'];
        }
        return [$s3Url, null];
    }

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return [null, 'Could not create the uploads directory.'];
        }
    }
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        return [null, 'Could not save the uploaded image.'];
    }

    return ['/uploads/' . $filename, null];
}

// Deletes a previously uploaded image.
function delete_image_file($imageUrl, $uploadDir) {
    if (!$imageUrl) {
        return;
    }
    if (str_starts_with($imageUrl, 'https://') || str_starts_with($imageUrl, 'http://')) {
        s3_delete_object($imageUrl);
        return;
    }
    if (str_starts_with($imageUrl, '/uploads/')) {
        $path = $uploadDir . '/' . basename($imageUrl);
        if (is_file($path)) {
            unlink($path);
        }
    }
}

// ============================================================================
// S3 Signature Version 4 Helper Functions
// ============================================================================

function s3_canonical_request($method, $path, $headers, $payloadHash) {
    $sorted = $headers;
    ksort($sorted);
    $canonicalHeaders = '';
    foreach ($sorted as $name => $value) {
        $canonicalHeaders .= strtolower($name) . ':' . trim($value) . "\n";
    }
    $signedHeaders = implode(';', array_map('strtolower', array_keys($sorted)));
    $canonicalRequest = implode("\n", [$method, $path, '', $canonicalHeaders, $signedHeaders, $payloadHash]);
    return [$canonicalRequest, $signedHeaders];
}

function s3_sign($method, $bucket, $region, $key, $payload, $credentials, $contentType = '') {
    $host = "$bucket.s3.$region.amazonaws.com";
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = gmdate('Ymd');
    $payloadHash = hash('sha256', $payload);

    $headers = [
        'Host' => $host,
        'X-Amz-Date' => $amzDate,
        'X-Amz-Content-Sha256' => $payloadHash,
    ];
    if ($contentType !== '') {
        $headers['Content-Type'] = $contentType;
    }
    if (!empty($credentials['token'])) {
        $headers['X-Amz-Security-Token'] = $credentials['token'];
    }

    [$canonicalRequest, $signedHeaders] = s3_canonical_request($method, '/' . $key, $headers, $payloadHash);

    $service = 's3';
    $credentialScope = "$dateStamp/$region/$service/aws4_request";
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amzDate,
        $credentialScope,
        hash('sha256', $canonicalRequest),
    ]);

    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $credentials['secret_key'], true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $signingKey = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$credentials['access_key']}/$credentialScope, "
        . "SignedHeaders=$signedHeaders, Signature=$signature";

    return [$host, $headers];
}

function s3_instance_credentials() {
    $credentials = s3_role_credentials();
    if ($credentials) {
        return $credentials;
    }

    if (defined('AWS_ACCESS_KEY_ID') && AWS_ACCESS_KEY_ID !== '' && AWS_SECRET_ACCESS_KEY !== '') {
        return [
            'access_key' => AWS_ACCESS_KEY_ID,
            'secret_key' => AWS_SECRET_ACCESS_KEY,
            'token' => AWS_SESSION_TOKEN ?? '',
        ];
    }

    return null;
}

function s3_role_credentials() {
    $tokenCtx = stream_context_create(['http' => [
        'method' => 'PUT',
        'header' => "X-aws-ec2-metadata-token-ttl-seconds: 21600\r\n",
        'timeout' => 1,
        'ignore_errors' => true,
    ]]);
    $token = @file_get_contents('http://169.254.169.254/latest/api/token', false, $tokenCtx);
    if ($token === false || $token === '') {
        return null;
    }

    $metaCtx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "X-aws-ec2-metadata-token: $token\r\n",
        'timeout' => 1,
        'ignore_errors' => true,
    ]]);
    $roleName = trim((string)@file_get_contents(
        'http://169.254.169.254/latest/meta-data/iam/security-credentials/',
        false,
        $metaCtx
    ));
    if ($roleName === '') {
        return null;
    }

    $credsJson = @file_get_contents(
        "http://169.254.169.254/latest/meta-data/iam/security-credentials/$roleName",
        false,
        $metaCtx
    );
    $creds = $credsJson ? json_decode($credsJson, true) : null;
    if (!isset($creds['AccessKeyId'], $creds['SecretAccessKey'], $creds['Token'])) {
        return null;
    }

    return [
        'access_key' => $creds['AccessKeyId'],
        'secret_key' => $creds['SecretAccessKey'],
        'token' => $creds['Token'],
    ];
}

// FIX 2: Pass $contentType into s3_sign so it gets signed in SigV4
function s3_put_object($key, $data, $contentType) {
    $credentials = s3_instance_credentials();
    if (!$credentials) {
        return [null, 'Could not get S3 credentials: no IAM role is attached to this instance, and AWS credentials are not set in config.php.'];
    }

    [$host, $headers] = s3_sign('PUT', AWS_S3_BUCKET, AWS_S3_REGION, $key, $data, $credentials, $contentType);

    $headerLines = '';
    foreach ($headers as $name => $value) {
        $headerLines .= "$name: $value\r\n";
    }

    $context = stream_context_create(['http' => [
        'method' => 'PUT',
        'header' => $headerLines,
        'content' => $data,
        'timeout' => 20,
        'ignore_errors' => true,
    ]]);

    @file_get_contents("https://$host/$key", false, $context);
    $status = s3_response_status($http_response_header ?? []);

    if ($status !== 200) {
        return [null, "S3 upload failed (HTTP $status)."];
    }

    return ["https://$host/$key", null];
}

function s3_delete_object($url) {
    $host = AWS_S3_BUCKET . '.s3.' . AWS_S3_REGION . '.amazonaws.com';
    $prefix = "https://$host/";
    if (!str_starts_with($url, $prefix)) {
        return;
    }
    $key = substr($url, strlen($prefix));

    $credentials = s3_instance_credentials();
    if (!$credentials) {
        return;
    }

    [, $headers] = s3_sign('DELETE', AWS_S3_BUCKET, AWS_S3_REGION, $key, '', $credentials);
    $headerLines = '';
    foreach ($headers as $name => $value) {
        $headerLines .= "$name: $value\r\n";
    }

    $context = stream_context_create(['http' => [
        'method' => 'DELETE',
        'header' => $headerLines,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    @file_get_contents("https://$host/$key", false, $context);
}

function s3_response_status($responseHeaders) {
    foreach ($responseHeaders as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            return (int)$m[1];
        }
    }
    return 0;
}

function send_sns_alert($subject, $message) {
    $topicArn = getenv('SNS_TOPIC_ARN') ?: (defined('SNS_TOPIC_ARN') ? SNS_TOPIC_ARN : '');
    if ($topicArn === '') {
        return false;
    }

    $region = getenv('AWS_REGION') ?: AWS_S3_REGION;
    $escapedSubject = escapeshellarg((string)$subject);
    $escapedMessage = escapeshellarg((string)$message);
    $escapedArn = escapeshellarg((string)$topicArn);
    $escapedRegion = escapeshellarg((string)$region);

    $cmd = sprintf(
        'aws sns publish --topic-arn %s --subject %s --message %s --region %s 2>/dev/null',
        $escapedArn,
        $escapedSubject,
        $escapedMessage,
        $escapedRegion
    );

    $output = shell_exec($cmd);
    return $output !== null && $output !== '';
}

function generate_csrf_token(){
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}