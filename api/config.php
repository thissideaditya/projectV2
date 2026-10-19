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

// Production mode: PHP errors go to the server's error log, never into a
// page or API response (they can reveal file paths, SQL and settings).
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
// Anything unexpected (e.g. a database error in an endpoint without its own
// try/catch) is logged privately; the visitor only gets a generic message.
set_exception_handler(function (Throwable $e) {
    error_log('[ADA] Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['ok' => false, 'error' => 'Something went wrong on the server. Please try again.']);
});

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

// Admin sessions end after 2 hours without activity, and after 12 hours
// in total, so a forgotten signed-in browser doesn't stay an open door.
if (!empty($_SESSION['admin_id'])) {
    $__now = time();
    $__idle = $__now - (int) ($_SESSION['last_seen'] ?? $__now);
    $__age = $__now - (int) ($_SESSION['signed_in_at'] ?? $__now);
    if ($__idle > 2 * 3600 || $__age > 12 * 3600) {
        $_SESSION = [];
        session_regenerate_id(true);
    } else {
        $_SESSION['last_seen'] = $__now;
    }
}

// Requests that change data (POST/PUT/DELETE) must come from this
// website's own pages. Browsers always send Origin on these; one from any
// other site is refused (defence against cross-site request forgery, on
// top of the SameSite cookie). Server-to-server calls send no Origin.
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !empty($_SERVER['HTTP_ORIGIN'])) {
    $__originHost = strtolower((string) parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST));
    $__ownHost = strtolower(preg_replace('~:\d+$~', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($__originHost === '' || ($__originHost !== $__ownHost && !in_array($__originHost, ['www.adalawchambers.com', 'adalawchambers.com'], true))) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Request not allowed from this origin.']);
        exit;
    }
}

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

/**
 * Makes post HTML (from the admin rich-text editor) safe to show to
 * visitors: keeps formatting the editor can produce — paragraphs,
 * bold/italic/underline, headings, lists, links, font family/size —
 * and removes everything else: <script>, <iframe>, forms, event
 * handlers (onclick, onerror, ...), javascript: links and CSS that
 * could load or run anything. <h1> becomes <h2> (the post title is the
 * page's only H1). Applied when a post is saved AND when it is shown,
 * so older posts are covered too.
 */
function sanitize_post_html(?string $html): string
{
    $html = (string) $html;
    if (trim($html) === '') return '';
    if (!class_exists('DOMDocument')) {
        // Without the DOM extension, fall back to plain text with line breaks.
        return nl2br(htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    $allowed = [
        'p' => ['style'], 'div' => ['style'], 'span' => ['style'], 'br' => [], 'hr' => [],
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'strike' => [], 'sub' => [], 'sup' => [],
        'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'], 'h5' => ['style'], 'h6' => ['style'],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'pre' => [], 'code' => [],
        'a' => ['href', 'title'], 'font' => ['face', 'size', 'color'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];
    // Removed together with everything inside them.
    $drop = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math',
             'template', 'noscript', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta', 'base', 'title', 'head'];
    $cssAllowed = ['font-family', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'text-align', 'color', 'background-color'];

    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__ada_root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__ada_root');
    if (!$root) return '';

    $clean = function (DOMNode $node) use (&$clean, $doc, $allowed, $drop, $cssAllowed) {
        // Iterate over a copy: children are removed/replaced while walking.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction || $child instanceof DOMCdataSection) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) continue; // text stays (escaped on output)

            $tag = strtolower($child->tagName);
            if (in_array($tag, $drop, true)) {
                $node->removeChild($child);
                continue;
            }
            if ($tag === 'h1') { // rename to h2, keeping the content
                $h2 = $doc->createElement('h2');
                while ($child->firstChild) $h2->appendChild($child->firstChild);
                if ($child->hasAttribute('style')) $h2->setAttribute('style', $child->getAttribute('style'));
                $node->replaceChild($h2, $child);
                $child = $h2;
                $tag = 'h2';
            }
            if (!isset($allowed[$tag])) { // unknown tag: keep its content, drop the tag
                $clean($child);
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = $attr->value;
                $keep = in_array($name, $allowed[$tag], true);
                if ($keep && $name === 'href') {
                    $v = preg_replace('~[\x00-\x20]+~', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $keep = (bool) preg_match('~^(https?:|mailto:|tel:|/(?!/)|#)~i', $v);
                }
                if ($keep && $name === 'style') {
                    $parts = [];
                    foreach (explode(';', $value) as $decl) {
                        if (!preg_match('~^\s*([a-z-]+)\s*:\s*(.+?)\s*$~i', $decl, $m)) continue;
                        $prop = strtolower($m[1]);
                        $val = $m[2];
                        // No url(), expression(), escapes or other tricks — plain values only.
                        if (in_array($prop, $cssAllowed, true) && !preg_match('~url\s*\(|expression|javascript|\\\\|[<>@{}]|/\*~i', $val)) {
                            $parts[] = $prop . ': ' . $val;
                        }
                    }
                    $value = implode('; ', $parts);
                    $keep = $value !== '';
                }
                if ($keep && in_array($name, ['colspan', 'rowspan', 'size'], true)) {
                    $keep = (bool) preg_match('~^[0-9]{1,2}$~', $value);
                }
                if ($keep && $name === 'color') {
                    $keep = (bool) preg_match('~^(#[0-9a-f]{3,8}|[a-z]{3,20})$~i', $value);
                }
                if ($keep) {
                    $child->setAttribute($name, $value);
                } else {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->hasAttribute('href')) {
                // Links open safely: no access back to this page from the target.
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
            }
            $clean($child);
        }
    };
    $clean($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

/** ?id= as a positive whole number, or a 400 error (rejects arrays, text, 0). */
function query_id(): int
{
    $id = $_GET['id'] ?? null;
    if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
        json_error(400, 'Missing or invalid ?id=.');
    }
    return (int) $id;
}

/** A ?name= query value as plain text ('' when missing or not text). */
function query_str(string $name): string
{
    $v = $_GET[$name] ?? '';
    return is_string($v) ? trim($v) : '';
}

/**
 * The visitor's IP address. Behind Hostinger's CDN the real address is
 * the LAST X-Forwarded-For entry (the one the CDN itself added); earlier
 * entries can be typed by the visitor, so they are never trusted.
 */
function client_ip(): string
{
    $parts = array_filter(array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))));
    $last = $parts ? end($parts) : '';
    return filter_var($last, FILTER_VALIDATE_IP) ? $last : (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/**
 * Simple rate limit: allows $max hits per $windowSeconds for $key, stored
 * as small files in PERSIST_DIR/rate-limits (outside the website). Over
 * the limit, the request ends with 429 and $message.
 */
function rate_limit(string $key, int $max, int $windowSeconds, string $message): void
{
    $dir = PERSIST_DIR . '/rate-limits';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $file = $dir . '/' . hash('sha256', $key) . '.json';
    $fp = @fopen($file, 'c+');
    if (!$fp) return; // can't count: fail open rather than block real visitors
    flock($fp, LOCK_EX);
    $hits = json_decode((string) stream_get_contents($fp), true);
    $cutoff = time() - $windowSeconds;
    $hits = array_values(array_filter(is_array($hits) ? $hits : [], function ($t) use ($cutoff) { return $t > $cutoff; }));
    $blocked = count($hits) >= $max;
    if (!$blocked) $hits[] = time();
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($hits));
    flock($fp, LOCK_UN);
    fclose($fp);
    if ($blocked) {
        header('Retry-After: ' . $windowSeconds);
        json_error(429, $message);
    }
}

/**
 * Cleans one text field from a public form: drops invisible control
 * characters (keeping line breaks in multi-line fields) and trims it.
 * Returns null when it is longer than $max characters.
 */
function clean_input($value, int $max, bool $multiline = false): ?string
{
    $v = is_string($value) ? $value : '';
    $v = $multiline
        ? preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~u', '', str_replace("\r\n", "\n", $v))
        : preg_replace('~[\x00-\x1F\x7F]~u', ' ', $v);
    $v = trim((string) $v);
    $len = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
    return $len > $max ? null : $v;
}

/** Logs a database problem server-side and answers with a generic 500 (no SQL or paths). */
function db_failure(Throwable $e, string $where): void
{
    error_log("[ADA] $where: " . $e->getMessage());
    json_error(500, 'Something went wrong on the server. Please try again.');
}
