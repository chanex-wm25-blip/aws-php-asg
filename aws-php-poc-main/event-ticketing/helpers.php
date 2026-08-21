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

// Falls back to a neutral placeholder until an admin uploads a real photo.
//
// image_url is either a full S3 URL (production/AWS - used as-is) or a path
// relative to the current script (local/Docker dev fallback, see
// handle_image_upload() below) - this app may be hosted as a subdirectory
// alongside sibling apps (not at the web server's document root), so a
// leading "/uploads/..." would resolve to the wrong app's uploads folder.
function entity_image_url($row) {
    if (!empty($row['image_url'])) {
        $url = $row['image_url'];

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $relative = ltrim($url, '/');
        $prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '';

        $path = __DIR__ . '/' . $relative;
        $version = is_file($path) ? '?v=' . filemtime($path) : '';
        return $prefix . $relative . $version;
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300">'
         . '<rect width="100%" height="100%" fill="#e6e1ea"/>'
         . '<text x="50%" y="50%" font-size="18" fill="#6b6470" text-anchor="middle" dy=".3em">No photo yet</text>'
         . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// Lazily creates an S3 client using this EC2 instance's IAM instance profile
// credentials (no access keys ever stored in app code/config, same pattern as
// config.php's DB_* env vars). Returns null - meaning "fall back to local
// disk" - if the AWS SDK for PHP isn't installed (composer install not run,
// i.e. local/Docker dev) or S3_BUCKET isn't set.
function s3_client() {
    static $client = null;
    static $initialized = false;
    if ($initialized) {
        return $client;
    }
    $initialized = true;

    $bucket = getenv('S3_BUCKET');
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!$bucket || !is_file($autoload)) {
        return null;
    }

    require_once $autoload;
    $client = new Aws\S3\S3Client([
        'version' => 'latest',
        'region'  => getenv('AWS_REGION') ?: 'us-east-1',
    ]);
    return $client;
}

// Validates an uploaded photo and stores it in S3 (when S3_BUCKET/the AWS SDK
// are configured) or on local disk otherwise. Returns [webPath, error] -
// webPath is a full S3 URL or a root-relative local path, or null if no file
// was uploaded or it failed.
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

    // Check the actual file content, not just the extension/MIME the browser
    // claims, so a renamed .php file can't slip through.
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

    $s3 = s3_client();
    if ($s3) {
        $bucket = getenv('S3_BUCKET');
        $region = getenv('AWS_REGION') ?: 'us-east-1';
        try {
            $s3->putObject([
                'Bucket'      => $bucket,
                'Key'         => 'uploads/' . $filename,
                'SourceFile'  => $file['tmp_name'],
                'ContentType' => $imageInfo['mime'],
            ]);
        } catch (\Throwable $e) {
            return [null, 'Could not upload the image to storage. Please try again.'];
        }
        return ["https://{$bucket}.s3.{$region}.amazonaws.com/uploads/{$filename}", null];
    }

    // Local/Docker fallback - no AWS configured.
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        return [null, 'Could not save the uploaded image.'];
    }

    return ['/uploads/' . $filename, null];
}

// Deletes a previously uploaded image, from S3 or local disk depending on
// which one handle_image_upload() used to store it.
function delete_image_file($imageUrl, $uploadDir) {
    if (!$imageUrl) {
        return;
    }

    if (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
        $s3 = s3_client();
        if ($s3) {
            try {
                $s3->deleteObject([
                    'Bucket' => getenv('S3_BUCKET'),
                    'Key'    => 'uploads/' . basename(parse_url($imageUrl, PHP_URL_PATH)),
                ]);
            } catch (\Throwable $e) {
                // Best-effort cleanup - a storage hiccup shouldn't block the
                // DB row from being deleted.
            }
        }
        return;
    }

    if (str_starts_with($imageUrl, '/uploads/')) {
        $path = $uploadDir . '/' . basename($imageUrl);
        if (is_file($path)) {
            unlink($path);
        }
    }
}

// Converts a 0-based row index into a spreadsheet-style row letter:
// 0 -> A, 25 -> Z, 26 -> AA, 27 -> AB, ...
function seat_row_label($index) {
    $label = '';
    $index++;
    while ($index > 0) {
        $index--;
        $label = chr(65 + ($index % 26)) . $label;
        $index = intdiv($index, 26);
    }
    return $label;
}

// A long random opaque token for a ticket's QR code. Deliberately NOT the
// attendee's name/email - anyone who glimpses or photographs a printed QR
// code should not be able to read personal info from it. The check-in
// scanner looks up the attendee/event/seat server-side using this token.
function generate_qr_token() {
    return bin2hex(random_bytes(20));
}
