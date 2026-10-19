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
foreach (['articles', 'resources', 'posts', 'resumes', 'magazines'] as $__sub) {
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

// Magazines — the issue itself is a PDF; its cover is an optional image
// (JPG/PNG/WEBP, same rules as IMAGE_* above). Magazine PDFs run larger
// than articles, so the limit is higher — PHP's own upload_max_filesize
// and post_max_size (hPanel → Advanced → PHP Configuration) must be at
// least this big too, or the upload fails before reaching this code.
define('MAGAZINE_ALLOWED_EXT', ['pdf']);
define('MAGAZINE_ALLOWED_MIME', ['application/pdf']);
define('MAGAZINE_MAX_BYTES', 50 * 1024 * 1024); // 50MB

// Public pages rendered by render.php / post.php / sitemap.php only need
// the settings above, not an admin session or CORS headers — they define
// ADA_PAGE_RENDER first, so visitors don't get a session cookie on every
// page view (which would also stop pages from being cached).
// Don't advertise the exact PHP version to every visitor.
header_remove('X-Powered-By');

if (!defined('ADA_PAGE_RENDER')) {

// Admin login cookie: HttpOnly (scripts can't read it) and SameSite=Lax
// (other websites can't send it along with their requests, so a page
// elsewhere can't act as a logged-in admin). Secure whenever the site is
// on HTTPS — Hostinger's CDN reports that through X-Forwarded-Proto.
$__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $__https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ---------------------------------------------------------------
// CORS — the site and /api are on the same domain, so browsers don't
// need any CORS headers for the site itself. They are sent only to the
// site's own addresses; any other website gets none, so it cannot read
// API responses (messages, applications) with a logged-in admin's session.
// ---------------------------------------------------------------
$__origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($__origin, ['https://www.adalawchambers.com', 'https://adalawchambers.com'], true)) {
    header('Access-Control-Allow-Origin: ' . $__origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

/**
 * Spam check for the public Contact / Careers forms. True when the
 * submission looks automated:
 *   - "website" is a hidden honeypot field people never see, so only
 *     bots fill it in;
 *   - "elapsed_ms" is how long the form was open before sending, added
 *     by the site's own JavaScript. A missing value (a bot posting
 *     straight to the API) or under 3 seconds is not a person typing.
 * Callers answer spam with the normal success response and store
 * nothing, so bots get no signal to adapt to.
 */
function looks_like_spam(array $data): bool
{
    if (trim((string) ($data['website'] ?? '')) !== '') {
        return true;
    }
    $elapsed = $data['elapsed_ms'] ?? null;
    if (!is_numeric($elapsed) || (int) $elapsed < 3000) {
        return true;
    }
    return false;
}

/**
 * Deletes a file this site uploaded, given the URL stored in the database
 * (e.g. "/api/media.php?type=magazines&file=ab12-1700000000.pdf" or a
 * legacy "/uploads/posts/ab12-1700000000.png"). Anything else — external
 * URLs, placeholder images, bundled assets — is left alone. Called when a
 * post/article/magazine is deleted or its file replaced, so removed files
 * stop being downloadable.
 */
function delete_uploaded_file(?string $url): void
{
    if (!$url) return;
    $path = null;
    $parts = parse_url($url);
    if (($parts['path'] ?? '') === MEDIA_URL_BASE && !empty($parts['query'])) {
        parse_str($parts['query'], $q);
        $type = $q['type'] ?? '';
        $file = basename((string) ($q['file'] ?? ''));
        if (in_array($type, ['articles', 'resources', 'posts', 'magazines'], true) && $file !== '') {
            $path = PERSIST_DIR . '/' . $type . '/' . $file;
        }
    } elseif (preg_match('~^' . preg_quote(UPLOAD_URL_BASE, '~') . '/posts/([^/?#]+)$~', $parts['path'] ?? '', $m)) {
        $path = UPLOAD_DIR . '/posts/' . basename($m[1]);
    }
    if ($path && is_file($path)) {
        @unlink($path);
    }
}

/**
 * delete_uploaded_file() for each of $urls that no row in any content
 * table still points to (the same picture can be reused by two posts).
 */
function delete_unreferenced_uploads(PDO $pdo, array $urls): void
{
    $checks = [
        'SELECT COUNT(*) FROM posts WHERE cover_image = ?',
        'SELECT COUNT(*) FROM articles WHERE file_url = ? OR cover_image = ?',
        'SELECT COUNT(*) FROM magazines WHERE file_url = ? OR cover_image = ?',
    ];
    foreach (array_unique(array_filter($urls)) as $url) {
        $inUse = 0;
        foreach ($checks as $sql) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_fill(0, substr_count($sql, '?'), $url));
                $inUse += (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                $inUse++; // table missing or unreadable: don't risk deleting
            }
        }
        if ($inUse === 0) {
            delete_uploaded_file($url);
        }
    }
}
