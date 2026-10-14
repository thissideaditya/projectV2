<?php
/**
 * ADA LAW Chambers — api/careers.php
 * ---------------------------------------------------------------
 * POST (multipart/form-data) — used by both the Internship and
 * Associate application forms on careers.html.
 *
 * Expected fields (see careers.html / js/careers-form.js):
 *   application_type   "internship" or "associate"
 *   full_name, email, phone, message
 *   college, study_year, duration        (internship)
 *   bar_enrolment_no, years_experience, practice_area   (associate)
 *   resume   file, optional, PDF or DOCX, max 5MB
 *
 * Stores the application in career_applications. If a resume was
 * attached, it's saved under /uploads/resumes/ and the path is
 * stored alongside the application. View/download submissions any
 * time via phpMyAdmin.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed.');
}

$type = $_POST['application_type'] ?? '';
if (!in_array($type, ['internship', 'associate'], true)) {
    json_error(400, 'Invalid application_type.');
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$fullName || !$email) {
    json_error(400, 'Full name and email are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error(400, 'Please provide a valid email address.');
}

$college = $type === 'internship' ? trim($_POST['college'] ?? '') : null;
$studyYear = $type === 'internship' ? trim($_POST['study_year'] ?? '') : null;
$duration = $type === 'internship' ? trim($_POST['duration'] ?? '') : null;

$barEnrolment = $type === 'associate' ? trim($_POST['bar_enrolment_no'] ?? '') : null;
$yearsExperience = $type === 'associate' ? trim($_POST['years_experience'] ?? '') : null;
$practiceArea = $type === 'associate' ? trim($_POST['practice_area'] ?? '') : null;

// ---------------------------------------------------------------
// Resume upload (optional, but validated if present)
// ---------------------------------------------------------------
$resumePath = null;
$resumeOriginalName = null;

if (!empty($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['resume'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error(400, 'Resume upload failed. Please try again.');
    }
    if ($file['size'] > RESUME_MAX_BYTES) {
        json_error(400, 'Resume is too large (max ' . (RESUME_MAX_BYTES / 1024 / 1024) . 'MB).');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, RESUME_ALLOWED_EXT, true)) {
        json_error(400, 'Resume must be a PDF or DOCX file.');
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, RESUME_ALLOWED_MIME, true)) {
        json_error(400, 'Resume file does not look like a valid PDF or DOCX.');
    }

    $dir = UPLOAD_DIR . '/resumes';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = bin2hex(random_bytes(8)) . '-' . time() . '.' . $ext;
    $destination = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        json_error(500, 'Could not save the uploaded resume.');
    }

    $resumePath = UPLOAD_URL_BASE . '/resumes/' . $filename;
    $resumeOriginalName = $file['name'];
}

$stmt = db()->prepare(
    'INSERT INTO career_applications
        (application_type, full_name, email, phone, college, study_year, duration,
         bar_enrolment_no, years_experience, practice_area, message,
         resume_path, resume_original_name)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $type, $fullName, $email, $phone, $college, $studyYear, $duration,
    $barEnrolment, $yearsExperience, $practiceArea, $message,
    $resumePath, $resumeOriginalName,
]);

json_ok(['message' => 'Application received. Thank you — our Chambers will reach out if there is a fit.'], 201);
