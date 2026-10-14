<?php
/**
 * ADA LAW Chambers — api/config.php
 * ---------------------------------------------------------------
 * Central database connection and shared helpers for every endpoint
 * in /api. This is the ONE file you edit with your real Hostinger
 * database credentials — every other API file includes this one.
 *
 * WHERE TO GET THESE VALUES
 * Hostinger hPanel → Databases → MySQL Databases. Create a database
 * there (or use the one Hostinger auto-creates for you), and it will
 * show you the database name, username, password, and host — copy
 * them in below. On Hostinger shared hosting the host is almost
 * always "localhost".
 * ---------------------------------------------------------------
 */

// ===================== EDIT THESE FOUR VALUES =====================
define('DB_HOST', 'localhost');
define('DB_NAME', 'u925204098_adalawdatabase');
define('DB_USER', 'u925204098_adalawusername');
define('DB_PASS', 'adalawchambers@DB1');
// ====================================================================

// A random secret used only once, by api/setup-admin.php, to create
// your first admin login. Change this to your own random string
// before uploading, then see api/setup-admin.php for how it's used.
// https://darkorchid-herring-326698.hostingersite.com/api/setup-admin.php?secret=ylwBjUZ1I2uCQqsQZrzPYVoIl5LBmMx7PZAdBXWp5Pc&email=admin@adalawChambers.com&password=@AdaPass123
// email=admin@adalawChambers.com
// password=@AdaPass123

define('SETUP_SECRET', 'ylwBjUZ1I2uCQqsQZrzPYVoIl5LBmMx7PZAdBXWp5Pc');


// Where uploaded files are stored on the server (resumes + post images)
// and the public URL prefix used to link to them from the site.
// KEPT for backward compatibility with any code that still reads it —
// no longer used for NEW uploads, see PERSIST_DIR below.
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL_BASE', '/uploads');

// ---------------------------------------------------------------
// PERSISTENT UPLOAD STORAGE — survives GitHub redeploys
// ---------------------------------------------------------------
// Hostinger's git deployment only manages the contents of
// public_html (confirmed in hPanel: "Root directory: public_html").
// Anything saved INSIDE public_html/uploads gets wiped on every
// redeploy, because it isn't part of the git repo. Files saved here
// instead — one level ABOVE public_html — are completely outside
// what git deployment touches, so they survive redeploys forever.
// $_SERVER['DOCUMENT_ROOT'] is public_html's real server path;
// dirname() of that is the folder directly above it.
define('PERSIST_DIR', dirname($_SERVER['DOCUMENT_ROOT']) . '/persistent-uploads');
foreach (['articles', 'resources', 'posts', 'resumes'] as $__sub) {
    $__path = PERSIST_DIR . '/' . $__sub;
    if (!is_dir($__path)) {
        mkdir($__path, 0755, true);
    }
}
// Files are served through api/media.php?type=...&file=... rather
// than a direct URL, since PERSIST_DIR sits outside the web root
// and has no URL of its own.
define('MEDIA_URL_BASE', '/api/media.php');

// Allowed resume file types for Careers applications
define('RESUME_ALLOWED_EXT', ['pdf', 'docx']);
// Note: .docx files are internally zip archives, so depending on the
// server's fileinfo magic database, PHP sometimes reports their MIME
// type as application/zip or application/octet-stream instead of the
// "correct" Office MIME type. All of these are accepted for .docx —
// the file extension check above already does most of the real work.
define('RESUME_ALLOWED_MIME', [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'application/octet-stream',
]);
define('RESUME_MAX_BYTES', 5 * 1024 * 1024); // 5MB

// Allowed image types for Rules/Thoughts cover images
define('IMAGE_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp']);
define('IMAGE_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp']);
define('IMAGE_MAX_BYTES', 4 * 1024 * 1024); // 4MB

// Allowed document types for the Articles section (PPT/PDF/Doc downloads)
define('ARTICLE_ALLOWED_EXT', ['pdf', 'ppt', 'pptx', 'doc', 'docx']);
define('ARTICLE_ALLOWED_MIME', [
    'application/pdf',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip', // .pptx/.docx sometimes report as zip depending on server config
    'application/octet-stream',
]);
define('ARTICLE_MAX_BYTES', 20 * 1024 * 1024); // 20MB

session_start();

// ---------------------------------------------------------------
// CORS — allows the frontend to call these endpoints. If your site
// and this /api folder are on the SAME domain (the normal Hostinger
// setup — everything under one public_html), this is not strictly
// needed, but it's harmless to leave in.
// ---------------------------------------------------------------
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Returns a PDO connection, or halts the request with a clear JSON
 * error if the database credentials in this file haven't been
 * filled in yet, or the connection otherwise fails.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (DB_NAME === 'your_hostinger_db_name') {
        json_error(500, 'Database is not configured yet. Edit api/config.php with your Hostinger database credentials.');
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        json_error(500, 'Could not connect to the database. Check the credentials in api/config.php.');
    }
}

/** Send a JSON success response and stop. */
function json_ok($data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true] + (is_array($data) ? $data : ['data' => $data]));
    exit;
}

/** Send a JSON error response and stop. */
function json_error(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

/** Read and decode a JSON request body (used by POST/PUT endpoints that aren't file uploads). */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** True if the current visitor has a logged-in admin session. */
function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** Stop the request with 401 unless the visitor is a logged-in admin. */
function require_admin(): void
{
    if (!is_admin()) {
        json_error(401, 'Please log in to the admin panel first.');
    }
}

/** Basic slugify, matching the one used client-side. */
function slugify(string $str): string
{
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}
