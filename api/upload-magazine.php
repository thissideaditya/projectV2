<?php
/**
 * ADA LAW Chambers — api/upload-magazine.php
 * ---------------------------------------------------------------
 * POST (multipart/form-data) — admin only. Send ONE of:
 *   field "file"  -> the magazine issue itself (PDF, max 50MB)
 *   field "cover" -> its cover picture (JPG/PNG/WEBP, max 4MB)
 * Saves into PERSIST_DIR/magazines/ (outside public_html, so it
 * survives every GitHub redeploy — see config.php) and returns its
 * public URL, ready to drop into a magazine's file_url/cover_image.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed.');
}

// An upload bigger than PHP's post_max_size arrives with $_FILES empty,
// which would otherwise look like "nothing was sent".
if (empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    json_error(400, 'File is larger than the server allows. Raise upload_max_filesize and post_max_size in hPanel → PHP Configuration.');
}

if (!empty($_FILES['file'])) {
    $file = $_FILES['file'];
    $maxBytes = MAGAZINE_MAX_BYTES;
    $allowedExt = MAGAZINE_ALLOWED_EXT;
    $allowedMime = MAGAZINE_ALLOWED_MIME;
    $typeError = 'Unsupported file type. Magazines must be a PDF.';
} elseif (!empty($_FILES['cover'])) {
    $file = $_FILES['cover'];
    $maxBytes = IMAGE_MAX_BYTES;
    $allowedExt = IMAGE_ALLOWED_EXT;
    $allowedMime = IMAGE_ALLOWED_MIME;
    $typeError = 'Unsupported image type. Use JPG, PNG, or WEBP.';
} else {
    json_error(400, 'No file uploaded.');
}

if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    json_error(400, 'File is larger than the server allows. Raise upload_max_filesize and post_max_size in hPanel → PHP Configuration.');
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    json_error(400, 'The upload failed. Please try again.');
}

if ($file['size'] > $maxBytes) {
    json_error(400, 'File is too large (max ' . ($maxBytes / 1024 / 1024) . 'MB).');
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    json_error(400, $typeError);
}

// Verify the actual file content (not just the extension).
$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowedMime, true)) {
    json_error(400, $typeError);
}

$dir = PERSIST_DIR . '/magazines';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$filename = bin2hex(random_bytes(8)) . '-' . time() . '.' . $ext;
$destination = $dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    json_error(500, 'Could not save the uploaded file.');
}

json_ok(['url' => MEDIA_URL_BASE . '?type=magazines&file=' . urlencode($filename)]);
