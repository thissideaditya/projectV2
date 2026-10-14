<?php
/**
 * ADA LAW Chambers — api/upload-image.php
 * ---------------------------------------------------------------
 * POST (multipart/form-data, field name "image") — admin only.
 * Saves the uploaded picture into /uploads/posts/ and returns its
 * public URL, ready to drop straight into a post's cover_image.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed.');
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_error(400, 'No image uploaded, or the upload failed.');
}

$file = $_FILES['image'];

if ($file['size'] > IMAGE_MAX_BYTES) {
    json_error(400, 'Image is too large (max ' . (IMAGE_MAX_BYTES / 1024 / 1024) . 'MB).');
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, IMAGE_ALLOWED_EXT, true)) {
    json_error(400, 'Unsupported image type. Use JPG, PNG, or WEBP.');
}

// Verify the actual file content (not just the extension) is really an image.
$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, IMAGE_ALLOWED_MIME, true)) {
    json_error(400, 'File does not look like a valid image.');
}

$dir = UPLOAD_DIR . '/posts';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$filename = bin2hex(random_bytes(8)) . '-' . time() . '.' . $ext;
$destination = $dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    json_error(500, 'Could not save the uploaded image.');
}

json_ok(['url' => UPLOAD_URL_BASE . '/posts/' . $filename]);
