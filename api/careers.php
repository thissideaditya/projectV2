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
require __DIR__ . '/notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed.');
}

// Bots get the normal success reply, and nothing (including any resume) is stored.
if (looks_like_spam($_POST)) {
    json_ok(['message' => 'Application received. Thank you — our Chambers will reach out if there is a fit.'], 201);
}

// At most 5 applications per visitor per 10 minutes.
rate_limit('careers:' . client_ip(), 5, 600, 'Too many applications sent. Please try again in a few minutes.');

$type = $_POST['application_type'] ?? '';
if (!in_array($type, ['internship', 'associate'], true)) {
    json_error(400, 'Invalid application_type.');
}

// Cleaned (no hidden control characters) and length-checked to fit the database.
$fullName = clean_input($_POST['full_name'] ?? '', 190);
$email = clean_input($_POST['email'] ?? '', 190);
$phone = clean_input($_POST['phone'] ?? '', 40);
$message = clean_input($_POST['message'] ?? '', 5000, true);
if ($fullName === null || $email === null || $phone === null || $message === null) {
    json_error(400, 'One of the fields is too long.');
}

if (!$fullName || !$email) {
    json_error(400, 'Full name and email are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error(400, 'Please provide a valid email address.');
}

$college = $type === 'internship' ? clean_input($_POST['college'] ?? '', 255) : null;
$studyYear = $type === 'internship' ? clean_input($_POST['study_year'] ?? '', 60) : null;
$duration = $type === 'internship' ? clean_input($_POST['duration'] ?? '', 60) : null;

$barEnrolment = $type === 'associate' ? clean_input($_POST['bar_enrolment_no'] ?? '', 120) : null;
$yearsExperience = $type === 'associate' ? clean_input($_POST['years_experience'] ?? '', 60) : null;
$practiceArea = $type === 'associate' ? clean_input($_POST['practice_area'] ?? '', 190) : null;
foreach ([[$type === 'internship', [$college, $studyYear, $duration]], [$type === 'associate', [$barEnrolment, $yearsExperience, $practiceArea]]] as [$applies, $vals]) {
    if ($applies && in_array(null, $vals, true)) {
        json_error(400, 'One of the fields is too long.');
    }
}

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

    // PERSIST_DIR is outside public_html: survives redeploys, and has no
    // public URL — resumes are only served by the admin-only
    // api/download-resume.php.
    $dir = PERSIST_DIR . '/resumes';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = bin2hex(random_bytes(8)) . '-' . time() . '.' . $ext;
    $destination = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        json_error(500, 'Could not save the uploaded resume.');
    }

    $resumePath = UPLOAD_URL_BASE . '/resumes/' . $filename;
    // Visitor-supplied filename: keep only a short, plain name (it's used in
    // the admin download and the notification email).
    $resumeOriginalName = preg_replace('~[^\w .()\-]+~u', '_', basename((string) $file['name']));
    $resumeOriginalName = function_exists('mb_substr') ? mb_substr($resumeOriginalName, 0, 120, 'UTF-8') : substr($resumeOriginalName, 0, 120);
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

// Reply to the applicant first, then email the application to the Chambers.
// The résumé itself is not emailed (it stays private on this server); the
// email says whether one was attached and where to download it.
json_ok_then(['message' => 'Application received. Thank you — our Chambers will reach out if there is a fit.'], 201,
    function () use ($type, $fullName, $email, $phone, $college, $studyYear, $duration, $barEnrolment, $yearsExperience, $practiceArea, $message, $resumeOriginalName) {
        $fields = ['Application' => $type === 'internship' ? 'Internship' : 'Associate', 'Name' => $fullName, 'Phone' => $phone ?: '—'];
        if ($type === 'internship') {
            $fields += ['College' => $college ?: '—', 'Year of study' => $studyYear ?: '—', 'Duration' => $duration ?: '—'];
        } else {
            $fields += ['Bar enrolment no.' => $barEnrolment ?: '—', 'Experience' => $yearsExperience ?: '—', 'Practice area' => $practiceArea ?: '—'];
        }
        $fields['Note'] = $message ?: '—';
        $fields['Résumé'] = $resumeOriginalName
            ? "Attached ($resumeOriginalName) — download it from the admin panel: " . SITE_URL . '/admin/applications.html'
            : 'Not attached';
        $label = $type === 'internship' ? 'Internship' : 'Associate';
        send_form_email("New $label application — $fullName", $fields, $email, 'careers.html');
    });
