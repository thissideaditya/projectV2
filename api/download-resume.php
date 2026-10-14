<?php
/**
 * ADA LAW Chambers — api/download-resume.php
 * ---------------------------------------------------------------
 * GET ?id=<career_applications.id>  (admin only)
 * Streams the resume attached to that application, using the
 * applicant's original filename rather than the randomized name it's
 * stored under. This is admin-gated (unlike a plain link into
 * /uploads/resumes/) so resumes can't be downloaded by anyone who
 * happens to guess or intercept a file URL.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';
require_admin();

$id = $_GET['id'] ?? null;
if (!$id) {
    json_error(400, 'Missing ?id=.');
}

$stmt = db()->prepare('SELECT resume_path, resume_original_name FROM career_applications WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row || !$row['resume_path']) {
    json_error(404, 'No resume found for this application.');
}

// resume_path is stored as e.g. "/uploads/resumes/ab12cd34-169999.pdf" —
// resolve it to the real file on disk under UPLOAD_DIR.
$filename = basename($row['resume_path']);
$filePath = UPLOAD_DIR . '/resumes/' . $filename;

if (!is_file($filePath)) {
    json_error(404, 'Resume file is missing from the server.');
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mime = $ext === 'pdf'
    ? 'application/pdf'
    : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

$downloadName = $row['resume_original_name'] ?: $filename;

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
