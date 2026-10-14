<?php
/**
 * ADA LAW Chambers — api/media.php
 * ---------------------------------------------------------------
 * Streams uploaded files (articles, resources, post images, resumes)
 * from PERSIST_DIR — a folder that sits OUTSIDE public_html, one
 * level above your git repo's root. Hostinger's GitHub deployment
 * only manages the contents of public_html, so anything stored
 * outside it survives every future redeploy untouched.
 *
 * Usage:  /api/media.php?type=articles&file=<filename>
 * "type" must be one of the whitelisted subfolders below.
 * "file" is sanitised with basename() so it can never escape its
 * folder (no ../ path traversal), and only the exact filename
 * that was generated at upload time will ever match a real file.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$file = isset($_GET['file']) ? basename($_GET['file']) : '';

$allowedTypes = ['articles', 'resources', 'posts', 'resumes'];
if (!in_array($type, $allowedTypes, true) || $file === '') {
    http_response_code(404);
    exit('Not found.');
}

$path = PERSIST_DIR . '/' . $type . '/' . $file;

if (!is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
$mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
// "inline" lets PDFs open in the browser tab and lets the Microsoft
// Office viewer fetch pptx/docx without forcing a download prompt.
header('Content-Disposition: inline; filename="' . rawurlencode(basename($path)) . '"');
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
