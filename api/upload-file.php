<?php
/**
 * ADA LAW Chambers — api/upload-file.php
 * ---------------------------------------------------------------
 * POST (multipart/form-data, field name "file") — admin only.
 * Saves an uploaded Article/PPT/PDF/Doc file into /uploads/articles/
 * and returns its public URL and detected file type, ready to drop
 * straight into an article's file_url/file_type.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed.');
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_error(400, 'No file uploaded, or the upload failed.');
}

$file = $_FILES['file'];

if ($file['size'] > ARTICLE_MAX_BYTES) {
    json_error(400, 'File is too large (max ' . (ARTICLE_MAX_BYTES / 1024 / 1024) . 'MB).');
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ARTICLE_ALLOWED_EXT, true)) {
    json_error(400, 'Unsupported file type. Use PDF, PPT, PPTX, DOC, or DOCX.');
}

$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, ARTICLE_ALLOWED_MIME, true)) {
    json_error(400, 'File does not look like a valid document.');
}

// Saved to PERSIST_DIR (outside public_html) so this file survives
// every future GitHub redeploy — see config.php for why.
$dir = PERSIST_DIR . '/articles';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$filename = bin2hex(random_bytes(8)) . '-' . time() . '.' . $ext;
$destination = $dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    json_error(500, 'Could not save the uploaded file.');
}

json_ok([
    'url' => MEDIA_URL_BASE . '?type=articles&file=' . urlencode($filename),
    'file_type' => $ext,
]);
